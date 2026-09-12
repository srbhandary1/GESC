<?php

declare(strict_types=1);

namespace App\Identity;

use App\Authentication\PhoneNumberNormalizer;
use App\Identity\Exceptions\LocalOperatorIdentityBootstrapRejected;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

final readonly class LocalOperatorIdentityBootstrapper
{
    public const string APPROVAL_REFERENCE = 'APP-0049';

    public function __construct(
        private ProtectedLocalIdentityInputReader $input,
        private PhoneNumberNormalizer $phoneNumbers,
        private IdentityIdentifierDigester $identifiers,
    ) {}

    public function bootstrap(
        string $phoneFile,
        string $expectedUserId,
        string $expectedMembershipId,
        string $approvalReference,
    ): LocalOperatorIdentityBootstrapResult {
        $this->assertExecutionAllowed(
            $expectedUserId,
            $expectedMembershipId,
            $approvalReference,
        );

        try {
            $input = $this->input->read($phoneFile);
            $normalizedPhone = $this->phoneNumbers->normalize($input);

            if (! hash_equals($normalizedPhone, $input)) {
                throw new LocalOperatorIdentityBootstrapRejected;
            }

            $identifierDigest = $this->identifiers->digest(
                'phone',
                $normalizedPhone,
            );
        } catch (LocalOperatorIdentityBootstrapRejected $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new LocalOperatorIdentityBootstrapRejected;
        }

        return DB::transaction(function () use (
            $expectedUserId,
            $expectedMembershipId,
            $approvalReference,
            $identifierDigest,
        ): LocalOperatorIdentityBootstrapResult {
            $this->lockIdentifierDigest($identifierDigest);

            $user = User::query()
                ->whereKey($expectedUserId)
                ->lockForUpdate()
                ->first();
            $membership = DB::table('memberships')
                ->where('id', $expectedMembershipId)
                ->lockForUpdate()
                ->first();

            if (
                ! $user instanceof User
                || $user->status !== User::STATUS_ACTIVE
                || ! $membership instanceof stdClass
                || $membership->user_id !== $expectedUserId
                || $membership->status !== Membership::STATUS_ACTIVE
                || $membership->membership_type
                    !== Membership::TYPE_ORGANIZATION_ADMIN
            ) {
                throw new LocalOperatorIdentityBootstrapRejected;
            }

            $organization = DB::table('organizations')
                ->where('id', $membership->organization_id)
                ->lockForUpdate()
                ->first();

            if (
                ! $organization instanceof stdClass
                || $organization->system_key !== Organization::DIRECT_SYSTEM_KEY
                || $organization->kind !== Organization::KIND_PLATFORM_DIRECT
                || $organization->status !== Organization::STATUS_ACTIVE
            ) {
                throw new LocalOperatorIdentityBootstrapRejected;
            }

            $role = DB::table('roles')
                ->where('organization_id', $organization->id)
                ->where('key', Role::DIRECT_ORGANIZATION_ADMIN_KEY)
                ->lockForUpdate()
                ->first();

            if (
                ! $role instanceof stdClass
                || ! in_array($role->is_system, [true, 1, '1'], true)
                || $role->status !== Role::STATUS_ACTIVE
                || ! DB::table('membership_roles')
                    ->where('organization_id', $organization->id)
                    ->where('membership_id', $membership->id)
                    ->where('role_id', $role->id)
                    ->lockForUpdate()
                    ->first(['membership_id'])
            ) {
                throw new LocalOperatorIdentityBootstrapRejected;
            }

            if (
                UserIdentity::query()
                    ->where('user_id', $user->getKey())
                    ->lockForUpdate()
                    ->first(['id']) instanceof UserIdentity
                || UserIdentity::query()
                    ->where('type', 'phone')
                    ->where('identifier_digest', $identifierDigest)
                    ->lockForUpdate()
                    ->first(['id']) instanceof UserIdentity
            ) {
                throw new LocalOperatorIdentityBootstrapRejected;
            }

            $boundAt = CarbonImmutable::now();
            $identity = new UserIdentity;
            $identity->forceFill([
                'user_id' => $user->getKey(),
                'type' => 'phone',
                'identifier_digest' => $identifierDigest,
                'display_hint' => null,
                'status' => UserIdentity::STATUS_VERIFIED,
                'verified_at' => $boundAt,
            ])->save();

            return new LocalOperatorIdentityBootstrapResult(
                userId: (string) $user->getKey(),
                membershipId: (string) $membership->id,
                identityId: (string) $identity->getKey(),
                environment: 'local',
                approvalReference: $approvalReference,
                boundAt: $boundAt,
            );
        }, 3);
    }

    private function assertExecutionAllowed(
        string $expectedUserId,
        string $expectedMembershipId,
        string $approvalReference,
    ): void {
        if (
            ! app()->environment('local')
            || config('authentication.otp.driver') !== 'local'
            || config('authentication.identity.local_operator_bootstrap_enabled')
                !== true
            || ! hash_equals(self::APPROVAL_REFERENCE, $approvalReference)
            || ! Str::isUlid($expectedUserId)
            || ! Str::isUlid($expectedMembershipId)
        ) {
            throw new LocalOperatorIdentityBootstrapRejected;
        }
    }

    private function lockIdentifierDigest(string $identifierDigest): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::selectOne(
            'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
            [$identifierDigest],
        );
    }
}

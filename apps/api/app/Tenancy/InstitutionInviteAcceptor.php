<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Audit\AuditEventRecorder;
use App\Authentication\AuthenticationSecretDigester;
use App\Models\Cohort;
use App\Models\Enrollment;
use App\Models\InstitutionInvite;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Outbox\OutboxEventRecorder;
use App\Tenancy\Exceptions\FoundationConfigurationException;
use App\Tenancy\Exceptions\IdempotencyKeyConflict;
use App\Tenancy\Exceptions\InstitutionInviteUnavailable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use stdClass;

final readonly class InstitutionInviteAcceptor
{
    public function __construct(
        private InstitutionInviteCodeDigester $codes,
        private AuthenticationSecretDigester $secrets,
        private OrganizationContextManager $contexts,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function accept(
        string $rawCode,
        User $user,
        ?string $correlationId = null,
        ?string $idempotencyKey = null,
    ): InviteAcceptanceResult {
        if (
            $idempotencyKey !== null
            && (
                $idempotencyKey === ''
                || strlen($idempotencyKey) > 36
            )
        ) {
            throw new InvalidArgumentException('The idempotency key is invalid.');
        }

        $codeDigest = $this->codes->digest($rawCode);
        $requestFingerprint = $idempotencyKey === null
            ? null
            : $this->secrets->digest(
                'institution-invite-accept-request',
                $rawCode,
            );

        try {
            return DB::transaction(
                function () use (
                    $codeDigest,
                    $user,
                    $correlationId,
                    $idempotencyKey,
                    $requestFingerprint,
                ): InviteAcceptanceResult {
                    // Keep the invite+OTP lock order: identity key, user row,
                    // then invite row. Authenticated acceptance starts here at
                    // the user row and must not invert that order.
                    $lockedUser = User::query()
                        ->whereKey($user->getKey())
                        ->lockForUpdate()
                        ->first();

                    if (
                        ! $lockedUser instanceof User
                        || $lockedUser->status !== User::STATUS_ACTIVE
                    ) {
                        throw new InstitutionInviteUnavailable;
                    }

                    $candidates = DB::table('institution_invites')
                        ->join(
                            'organizations',
                            'organizations.id',
                            '=',
                            'institution_invites.organization_id',
                        )
                        ->where('institution_invites.code_digest', $codeDigest)
                        ->orderBy('institution_invites.id')
                        ->limit(2)
                        ->lockForUpdate()
                        ->get([
                            'institution_invites.id',
                            'institution_invites.organization_id',
                            'organizations.status as organization_status',
                        ]);
                    $candidate = $candidates->count() === 1
                        ? $candidates->first()
                        : null;

                    if (
                        ! $candidate instanceof stdClass
                        || ! is_string($candidate->id)
                        || ! is_string($candidate->organization_id)
                        || $candidate->organization_status !== Organization::STATUS_ACTIVE
                    ) {
                        throw new InstitutionInviteUnavailable;
                    }

                    $this->assertIdempotencyKeyAvailable(
                        (string) $lockedUser->getKey(),
                        $candidate->id,
                        $idempotencyKey,
                        $requestFingerprint,
                    );

                    $context = new OrganizationContext(
                        organizationId: $candidate->organization_id,
                        actorUserId: (string) $lockedUser->getKey(),
                        membershipId: null,
                        source: OrganizationContextSource::InstitutionInvite,
                        correlationId: $correlationId,
                    );

                    return $this->contexts->run(
                        $context,
                        fn (): InviteAcceptanceResult => $this->acceptInContext(
                            $candidate->id,
                            $codeDigest,
                            $lockedUser,
                            $idempotencyKey,
                            $requestFingerprint,
                        ),
                    );
                },
                3,
            );
        } catch (QueryException $exception) {
            if (
                $exception->getCode() === '23505'
                && str_contains(
                    $exception->getMessage(),
                    'institution_invites_acceptance_idempotency_unique',
                )
            ) {
                throw new IdempotencyKeyConflict;
            }

            throw $exception;
        }
    }

    private function acceptInContext(
        string $inviteId,
        string $codeDigest,
        User $user,
        ?string $idempotencyKey,
        ?string $requestFingerprint,
    ): InviteAcceptanceResult {
        $invite = InstitutionInvite::query()
            ->whereKey($inviteId)
            ->where('code_digest', $codeDigest)
            ->lockForUpdate()
            ->first();

        if (! $invite instanceof InstitutionInvite) {
            throw new InstitutionInviteUnavailable;
        }

        if ($invite->status === InstitutionInvite::STATUS_ACCEPTED) {
            return $this->replayAcceptedInvite(
                $invite,
                $user,
                $idempotencyKey,
                $requestFingerprint,
            );
        }

        $expiresAt = $invite->getAttribute('expires_at');

        if (
            $invite->status !== InstitutionInvite::STATUS_PENDING
            || ! $expiresAt instanceof CarbonInterface
            || $expiresAt->isPast()
        ) {
            throw new InstitutionInviteUnavailable;
        }

        $membership = Membership::query()
            ->where('user_id', $user->getKey())
            ->lockForUpdate()
            ->first();

        if ($membership instanceof Membership) {
            if (
                $membership->status !== Membership::STATUS_ACTIVE
                || $membership->membership_type !== $invite->membership_type
            ) {
                throw new InstitutionInviteUnavailable;
            }
        } else {
            $membership = new Membership;
            $membership->forceFill([
                'user_id' => $user->getKey(),
                'membership_type' => $invite->membership_type,
                'status' => Membership::STATUS_ACTIVE,
                'joined_at' => now(),
            ])->save();
        }

        $this->attachRole($invite, $membership);
        $enrollment = $this->attachCohort($invite, $membership);

        $invite->forceFill([
            'status' => InstitutionInvite::STATUS_ACCEPTED,
            'accepted_membership_id' => $membership->getKey(),
            'accepted_by_user_id' => $user->getKey(),
            'accepted_idempotency_key' => $idempotencyKey,
            'accepted_request_fingerprint' => $requestFingerprint,
            'accepted_at' => now(),
        ])->save();

        $this->audit->recordTenant(
            'institution_invite.accepted',
            'institution_invite',
            (string) $invite->getKey(),
        );

        $payload = [
            'organization_id' => $membership->organizationId(),
            'invite_id' => (string) $invite->getKey(),
            'membership_id' => (string) $membership->getKey(),
            'user_id' => (string) $user->getKey(),
        ];

        if ($enrollment instanceof Enrollment) {
            $payload['enrollment_id'] = (string) $enrollment->getKey();
        }

        $this->outbox->recordTenant(
            'institution_invite.accepted',
            'institution_invite',
            (string) $invite->getKey(),
            $payload,
        );

        return new InviteAcceptanceResult(
            organization: $this->organization($invite->organizationId()),
            membership: $membership,
            enrollment: $enrollment,
            replayed: false,
        );
    }

    private function replayAcceptedInvite(
        InstitutionInvite $invite,
        User $user,
        ?string $idempotencyKey,
        ?string $requestFingerprint,
    ): InviteAcceptanceResult {
        $membershipId = $invite->accepted_membership_id;

        if (
            ! is_string($membershipId)
            || ! is_string($invite->accepted_by_user_id)
        ) {
            throw new FoundationConfigurationException(
                'An accepted institution invite has incomplete acceptance state.',
            );
        }

        if (! hash_equals($invite->accepted_by_user_id, (string) $user->getKey())) {
            throw new InstitutionInviteUnavailable;
        }

        $this->assertMatchingIdempotencyBinding(
            $invite,
            $idempotencyKey,
            $requestFingerprint,
        );

        $membership = Membership::query()
            ->whereKey($membershipId)
            ->where('user_id', $user->getKey())
            ->where('status', Membership::STATUS_ACTIVE)
            ->first();

        if (! $membership instanceof Membership) {
            throw new InstitutionInviteUnavailable;
        }

        $enrollment = Enrollment::query()
            ->where('learner_membership_id', $membership->getKey())
            ->when(
                $invite->cohort_id !== null,
                fn ($query) => $query->where('cohort_id', $invite->cohort_id),
            )
            ->first();

        return new InviteAcceptanceResult(
            organization: $this->organization($invite->organizationId()),
            membership: $membership,
            enrollment: $enrollment,
            replayed: true,
        );
    }

    private function assertIdempotencyKeyAvailable(
        string $userId,
        string $candidateInviteId,
        ?string $idempotencyKey,
        ?string $requestFingerprint,
    ): void {
        if ($idempotencyKey === null || $requestFingerprint === null) {
            return;
        }

        $bound = DB::table('institution_invites')
            ->where('accepted_by_user_id', $userId)
            ->where('accepted_idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first(['id', 'accepted_request_fingerprint']);

        if (! $bound instanceof stdClass) {
            return;
        }

        if (
            ! is_string($bound->id)
            || ! is_string($bound->accepted_request_fingerprint)
            || ! hash_equals($bound->id, $candidateInviteId)
            || ! hash_equals(
                $bound->accepted_request_fingerprint,
                $requestFingerprint,
            )
        ) {
            throw new IdempotencyKeyConflict;
        }
    }

    private function assertMatchingIdempotencyBinding(
        InstitutionInvite $invite,
        ?string $idempotencyKey,
        ?string $requestFingerprint,
    ): void {
        if ($idempotencyKey === null && $requestFingerprint === null) {
            return;
        }

        if (
            ! is_string($invite->accepted_idempotency_key)
            || ! is_string($invite->accepted_request_fingerprint)
            || ! is_string($idempotencyKey)
            || ! is_string($requestFingerprint)
            || ! hash_equals(
                $invite->accepted_idempotency_key,
                $idempotencyKey,
            )
            || ! hash_equals(
                $invite->accepted_request_fingerprint,
                $requestFingerprint,
            )
        ) {
            throw new IdempotencyKeyConflict;
        }
    }

    private function attachRole(
        InstitutionInvite $invite,
        Membership $membership,
    ): void {
        if ($invite->role_id === null) {
            return;
        }

        $now = now();

        DB::table('membership_roles')->insertOrIgnore([
            'organization_id' => $membership->organizationId(),
            'membership_id' => $membership->getKey(),
            'role_id' => $invite->role_id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function attachCohort(
        InstitutionInvite $invite,
        Membership $membership,
    ): ?Enrollment {
        if ($invite->cohort_id === null) {
            return null;
        }

        $cohort = Cohort::query()
            ->whereKey($invite->cohort_id)
            ->where('status', Cohort::STATUS_ACTIVE)
            ->first();

        if (! $cohort instanceof Cohort) {
            throw new FoundationConfigurationException(
                'The institution invite references an unavailable cohort.',
            );
        }

        if ($membership->membership_type === Membership::TYPE_TRAINER) {
            $now = now();

            DB::table('cohort_trainers')->insertOrIgnore([
                'organization_id' => $membership->organizationId(),
                'cohort_id' => $cohort->getKey(),
                'trainer_membership_id' => $membership->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return null;
        }

        if ($membership->membership_type !== Membership::TYPE_LEARNER) {
            throw new FoundationConfigurationException(
                'Only learner or trainer invitations may reference a cohort.',
            );
        }

        $enrollment = Enrollment::query()
            ->where('learner_membership_id', $membership->getKey())
            ->where('cohort_id', $cohort->getKey())
            ->lockForUpdate()
            ->first();

        if ($enrollment instanceof Enrollment) {
            return $enrollment;
        }

        $enrollment = new Enrollment;
        $enrollment->forceFill([
            'learner_membership_id' => $membership->getKey(),
            'cohort_id' => $cohort->getKey(),
            'status' => Enrollment::STATUS_PENDING_CURRICULUM,
            'enrolled_at' => now(),
        ])->save();

        return $enrollment;
    }

    private function organization(string $organizationId): Organization
    {
        $organization = Organization::query()->find($organizationId);

        if (
            ! $organization instanceof Organization
            || $organization->status !== Organization::STATUS_ACTIVE
        ) {
            throw new FoundationConfigurationException(
                'The institution invite organization does not exist.',
            );
        }

        return $organization;
    }
}

<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Audit\AuditEventRecorder;
use App\Identity\VerifiedIdentityProvisioner;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserIdentity;
use App\Outbox\OutboxEventRecorder;
use App\Tenancy\Exceptions\FoundationConfigurationException;
use App\Tenancy\Exceptions\IdentityUnavailable;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class DirectLearnerProvisioner
{
    public function __construct(
        private VerifiedIdentityProvisioner $verifiedIdentities,
        private OrganizationContextManager $contexts,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function provisionVerifiedIdentity(
        string $type,
        string $normalizedIdentifier,
        ?string $displayHint = null,
    ): DirectLearnerProvisioningResult {
        return DB::transaction(
            function () use (
                $type,
                $normalizedIdentifier,
                $displayHint,
            ): DirectLearnerProvisioningResult {
                $verifiedIdentity = $this->verifiedIdentities->provision(
                    $type,
                    $normalizedIdentifier,
                    $displayHint,
                );

                return $this->provisionTransaction(
                    $verifiedIdentity->user,
                    $verifiedIdentity->identity,
                );
            },
            3,
        );
    }

    private function provisionTransaction(
        User $user,
        UserIdentity $identity,
    ): DirectLearnerProvisioningResult {
        $organization = Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->first();

        if (
            ! $organization instanceof Organization
            || $organization->kind !== Organization::KIND_PLATFORM_DIRECT
            || $organization->status !== Organization::STATUS_ACTIVE
        ) {
            throw new FoundationConfigurationException(
                'The active Global English Direct organization is not provisioned.',
            );
        }

        $existingMembership = DB::table('memberships')
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $user->getKey())
            ->first(['id']);
        $existingMembershipId = $existingMembership instanceof stdClass
            && is_string($existingMembership->id)
                ? $existingMembership->id
                : null;

        $context = new OrganizationContext(
            organizationId: (string) $organization->getKey(),
            actorUserId: (string) $user->getKey(),
            membershipId: $existingMembershipId,
            source: OrganizationContextSource::DirectProvisioning,
        );

        return $this->contexts->run(
            $context,
            function () use ($user, $identity): DirectLearnerProvisioningResult {
                $membership = Membership::query()
                    ->where('user_id', $user->getKey())
                    ->lockForUpdate()
                    ->first();
                $membershipCreated = false;

                if ($membership instanceof Membership) {
                    if (
                        $membership->status !== Membership::STATUS_ACTIVE
                        || ! in_array(
                            $membership->membership_type,
                            [
                                Membership::TYPE_LEARNER,
                                Membership::TYPE_TRAINER,
                                Membership::TYPE_ORGANIZATION_ADMIN,
                            ],
                            true,
                        )
                    ) {
                        throw new IdentityUnavailable;
                    }

                    // Existing verified staff identities authenticate through
                    // the ordinary OTP path without being converted to a
                    // learner or receiving any additional role.
                    if (
                        $membership->membership_type
                            !== Membership::TYPE_LEARNER
                    ) {
                        return new DirectLearnerProvisioningResult(
                            user: $user,
                            identity: $identity,
                            membership: $membership,
                            replayed: true,
                        );
                    }
                } else {
                    $membership = new Membership;
                    $membership->forceFill([
                        'user_id' => $user->getKey(),
                        'membership_type' => Membership::TYPE_LEARNER,
                        'status' => Membership::STATUS_ACTIVE,
                        'joined_at' => now(),
                    ])->save();
                    $membershipCreated = true;
                }

                $role = Role::query()
                    ->where('key', Role::DIRECT_LEARNER_KEY)
                    ->where('is_system', true)
                    ->first();

                if (! $role instanceof Role) {
                    throw new FoundationConfigurationException(
                        'The Global English Direct learner role is not provisioned.',
                    );
                }

                $now = now();
                $roleAttached = DB::table('membership_roles')->insertOrIgnore([
                    'organization_id' => $membership->organizationId(),
                    'membership_id' => $membership->getKey(),
                    'role_id' => $role->getKey(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]) === 1;
                $replayed = ! $membershipCreated && ! $roleAttached;

                if (! $replayed) {
                    $this->audit->recordTenant(
                        'membership.direct_assigned',
                        'membership',
                        (string) $membership->getKey(),
                    );
                    $this->outbox->recordTenant(
                        'membership.direct_assigned',
                        'membership',
                        (string) $membership->getKey(),
                        [
                            'organization_id' => $membership->organizationId(),
                            'user_id' => (string) $user->getKey(),
                            'identity_id' => (string) $identity->getKey(),
                            'membership_id' => (string) $membership->getKey(),
                        ],
                    );
                }

                return new DirectLearnerProvisioningResult(
                    user: $user,
                    identity: $identity,
                    membership: $membership,
                    replayed: $replayed,
                );
            },
        );
    }
}

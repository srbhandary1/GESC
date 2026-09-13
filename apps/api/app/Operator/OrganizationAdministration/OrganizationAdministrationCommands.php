<?php

declare(strict_types=1);

namespace App\Operator\OrganizationAdministration;

use App\Audit\AuditEventRecorder;
use App\Curriculum\CanonicalJson;
use App\Curriculum\Governance\AuthorityAppointmentCommands;
use App\Curriculum\Governance\AuthorityKind;
use App\Models\Membership;
use App\Models\User;
use App\Operator\OrganizationAdministration\Exceptions\OrganizationAdministrationConflict;
use App\Operator\OrganizationAdministration\Exceptions\OrganizationAdministrationRejected;
use App\Tenancy\InstitutionInviteCodeDigester;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class OrganizationAdministrationCommands
{
    public function __construct(
        private OrganizationAdministrationAuthorization $authorization,
        private OrganizationAdministrationIdempotency $idempotency,
        private OrganizationAdministrationEventRecorder $events,
        private MembershipSelectedCredentialService $credentials,
        private AuthorityAppointmentCommands $appointments,
        private InstitutionInviteCodeDigester $inviteCodes,
        private AuditEventRecorder $audit,
    ) {}

    public function createBranch(
        User $actor,
        string $key,
        string $name,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permission: OrganizationAdministrationAuthorization::BRANCHES_MANAGE,
            operation: 'branch.create',
            targetType: 'branch_collection',
            targetId: $this->organizationId($actor),
            body: [
                'expected_version' => $expectedVersion,
                'key' => $key,
                'name' => $name,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($key, $name, $expectedVersion): array {
                if ($expectedVersion !== 0) {
                    throw new OrganizationAdministrationConflict;
                }

                $branchId = (string) Str::ulid();
                DB::table('branches')->insert([
                    'id' => $branchId,
                    'organization_id' => $organizationId,
                    'key' => $key,
                    'name' => $name,
                    'status' => 'active',
                    'version' => 1,
                    'archived_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    'branch.created',
                    ['branch_id' => $branchId],
                    $now,
                );
                $this->audit->recordTenant(
                    'organization_administration.branch.created',
                    'branch',
                    $branchId,
                );

                return ['result_type' => 'branch', 'result_id' => $branchId];
            },
        );
    }

    public function updateBranch(
        User $actor,
        string $branchId,
        string $name,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permission: OrganizationAdministrationAuthorization::BRANCHES_MANAGE,
            operation: 'branch.update',
            targetType: 'branch',
            targetId: $branchId,
            body: ['expected_version' => $expectedVersion, 'name' => $name],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($branchId, $name, $expectedVersion): array {
                $branch = $this->lockedAggregate(
                    'branches',
                    $organizationId,
                    $branchId,
                );
                $this->assertActiveVersion($branch, $expectedVersion);
                DB::table('branches')->where('id', $branchId)->update([
                    'name' => $name,
                    'version' => $expectedVersion + 1,
                    'updated_at' => $now,
                ]);
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    'branch.updated',
                    ['branch_id' => $branchId],
                    $now,
                );
                $this->audit->recordTenant(
                    'organization_administration.branch.updated',
                    'branch',
                    $branchId,
                );

                return ['result_type' => 'branch', 'result_id' => $branchId];
            },
        );
    }

    public function archiveBranch(
        User $actor,
        string $branchId,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permission: OrganizationAdministrationAuthorization::BRANCHES_MANAGE,
            operation: 'branch.archive',
            targetType: 'branch',
            targetId: $branchId,
            body: ['expected_version' => $expectedVersion],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($branchId, $expectedVersion): array {
                $branch = $this->lockedAggregate('branches', $organizationId, $branchId);
                $this->assertActiveVersion($branch, $expectedVersion);
                $activeCohorts = DB::table('cohorts')
                    ->where('organization_id', $organizationId)
                    ->where('branch_id', $branchId)
                    ->where('status', 'active')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->exists();

                if ($activeCohorts) {
                    throw new OrganizationAdministrationConflict(
                        OrganizationAdministrationConflict::ACTIVE_DEPENDENCY,
                    );
                }

                DB::table('branches')->where('id', $branchId)->update([
                    'status' => 'archived',
                    'version' => $expectedVersion + 1,
                    'archived_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    'branch.archived',
                    ['branch_id' => $branchId],
                    $now,
                );
                $this->audit->recordTenant(
                    'organization_administration.branch.archived',
                    'branch',
                    $branchId,
                );

                return ['result_type' => 'branch', 'result_id' => $branchId];
            },
        );
    }

    public function createCohort(
        User $actor,
        string $key,
        string $name,
        ?string $branchId,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permission: OrganizationAdministrationAuthorization::COHORTS_MANAGE,
            operation: 'cohort.create',
            targetType: 'cohort_collection',
            targetId: $this->organizationId($actor),
            body: [
                'branch_id' => $branchId,
                'expected_version' => $expectedVersion,
                'key' => $key,
                'name' => $name,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($key, $name, $branchId, $expectedVersion): array {
                if ($expectedVersion !== 0) {
                    throw new OrganizationAdministrationConflict;
                }

                $this->assertActiveBranch($organizationId, $branchId);
                $cohortId = (string) Str::ulid();
                DB::table('cohorts')->insert([
                    'id' => $cohortId,
                    'organization_id' => $organizationId,
                    'branch_id' => $branchId,
                    'key' => $key,
                    'name' => $name,
                    'status' => 'active',
                    'version' => 1,
                    'archived_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    'cohort.created',
                    ['cohort_id' => $cohortId],
                    $now,
                );
                $this->audit->recordTenant(
                    'organization_administration.cohort.created',
                    'cohort',
                    $cohortId,
                );

                return ['result_type' => 'cohort', 'result_id' => $cohortId];
            },
        );
    }

    public function updateCohort(
        User $actor,
        string $cohortId,
        string $name,
        ?string $branchId,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permission: OrganizationAdministrationAuthorization::COHORTS_MANAGE,
            operation: 'cohort.update',
            targetType: 'cohort',
            targetId: $cohortId,
            body: [
                'branch_id' => $branchId,
                'expected_version' => $expectedVersion,
                'name' => $name,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($cohortId, $name, $branchId, $expectedVersion): array {
                $cohort = $this->lockedAggregate('cohorts', $organizationId, $cohortId);
                $this->assertActiveVersion($cohort, $expectedVersion);
                $this->assertActiveBranch($organizationId, $branchId);
                DB::table('cohorts')->where('id', $cohortId)->update([
                    'name' => $name,
                    'branch_id' => $branchId,
                    'version' => $expectedVersion + 1,
                    'updated_at' => $now,
                ]);
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    'cohort.updated',
                    ['cohort_id' => $cohortId],
                    $now,
                );
                $this->audit->recordTenant(
                    'organization_administration.cohort.updated',
                    'cohort',
                    $cohortId,
                );

                return ['result_type' => 'cohort', 'result_id' => $cohortId];
            },
        );
    }

    public function archiveCohort(
        User $actor,
        string $cohortId,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permission: OrganizationAdministrationAuthorization::COHORTS_MANAGE,
            operation: 'cohort.archive',
            targetType: 'cohort',
            targetId: $cohortId,
            body: ['expected_version' => $expectedVersion],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($cohortId, $expectedVersion): array {
                $cohort = $this->lockedAggregate('cohorts', $organizationId, $cohortId);
                $this->assertActiveVersion($cohort, $expectedVersion);
                $trainers = DB::table('cohort_trainers')
                    ->where('organization_id', $organizationId)
                    ->where('cohort_id', $cohortId)
                    ->orderBy('trainer_membership_id')
                    ->lockForUpdate()
                    ->exists();
                $learners = DB::table('enrollments')
                    ->where('organization_id', $organizationId)
                    ->where('cohort_id', $cohortId)
                    ->whereIn('status', ['active', 'pending_curriculum'])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->exists();

                if ($trainers || $learners) {
                    throw new OrganizationAdministrationConflict(
                        OrganizationAdministrationConflict::ACTIVE_DEPENDENCY,
                    );
                }

                DB::table('cohorts')->where('id', $cohortId)->update([
                    'status' => 'archived',
                    'version' => $expectedVersion + 1,
                    'archived_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    'cohort.archived',
                    ['cohort_id' => $cohortId],
                    $now,
                );
                $this->audit->recordTenant(
                    'organization_administration.cohort.archived',
                    'cohort',
                    $cohortId,
                );

                return ['result_type' => 'cohort', 'result_id' => $cohortId];
            },
        );
    }

    public function issueInvitation(
        User $actor,
        string $membershipType,
        int $expiresInHours,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permission: OrganizationAdministrationAuthorization::INVITATIONS_MANAGE,
            operation: 'invitation.issue',
            targetType: 'invitation_collection',
            targetId: $this->organizationId($actor),
            body: [
                'expected_version' => $expectedVersion,
                'expires_in_hours' => $expiresInHours,
                'membership_type' => $membershipType,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($membershipType, $expiresInHours, $expectedVersion): array {
                if ($expectedVersion !== 0) {
                    throw new OrganizationAdministrationConflict;
                }

                $rawCode = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
                $invitationId = (string) Str::ulid();
                DB::table('institution_invites')->insert([
                    'id' => $invitationId,
                    'organization_id' => $organizationId,
                    'code_digest' => $this->inviteCodes->digest($rawCode),
                    'membership_type' => $membershipType,
                    'role_id' => null,
                    'cohort_id' => null,
                    'status' => 'pending',
                    'expires_at' => $now->addHours($expiresInHours),
                    'accepted_membership_id' => null,
                    'accepted_by_user_id' => null,
                    'accepted_idempotency_key' => null,
                    'accepted_request_fingerprint' => null,
                    'accepted_at' => null,
                    'version' => 1,
                    'issued_by_membership_id' => $actorMembershipId,
                    'revoked_by_membership_id' => null,
                    'revoked_at' => null,
                    'code_discard_after' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    'invitation.issued',
                    ['invitation_id' => $invitationId],
                    $now,
                );
                $this->audit->recordTenant(
                    'organization_administration.invitation.issued',
                    'institution_invitation',
                    $invitationId,
                );

                return [
                    'result_type' => 'institution_invitation',
                    'result_id' => $invitationId,
                    'activation_code' => $rawCode,
                ];
            },
        );
    }

    public function revokeInvitation(
        User $actor,
        string $invitationId,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permission: OrganizationAdministrationAuthorization::INVITATIONS_MANAGE,
            operation: 'invitation.revoke',
            targetType: 'institution_invitation',
            targetId: $invitationId,
            body: ['expected_version' => $expectedVersion],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($invitationId, $expectedVersion): array {
                $invitation = $this->lockedAggregate(
                    'institution_invites',
                    $organizationId,
                    $invitationId,
                );

                if (
                    $this->integer($invitation, 'version') !== $expectedVersion
                    || $this->string($invitation, 'status') !== 'pending'
                    || $this->carbon($invitation, 'expires_at')->lessThanOrEqualTo($now)
                    || ! in_array(
                        $this->string($invitation, 'membership_type'),
                        [Membership::TYPE_TRAINER, Membership::TYPE_ORGANIZATION_ADMIN],
                        true,
                    )
                ) {
                    throw new OrganizationAdministrationConflict;
                }

                DB::table('institution_invites')->where('id', $invitationId)->update([
                    'status' => 'revoked',
                    'version' => $expectedVersion + 1,
                    'revoked_by_membership_id' => $actorMembershipId,
                    'revoked_at' => $now,
                    'code_discard_after' => $now,
                    'updated_at' => $now,
                ]);
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    'invitation.revoked',
                    ['invitation_id' => $invitationId],
                    $now,
                );
                $this->audit->recordTenant(
                    'organization_administration.invitation.revoked',
                    'institution_invitation',
                    $invitationId,
                );

                return [
                    'result_type' => 'institution_invitation',
                    'result_id' => $invitationId,
                ];
            },
        );
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    public function createRole(
        User $actor,
        string $key,
        string $name,
        array $permissionKeys,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permission: OrganizationAdministrationAuthorization::ROLES_MANAGE,
            operation: 'role.create',
            targetType: 'role_collection',
            targetId: $this->organizationId($actor),
            body: [
                'expected_version' => $expectedVersion,
                'key' => $key,
                'name' => $name,
                'permission_keys' => $permissionKeys,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($actor, $key, $name, $permissionKeys, $expectedVersion): array {
                if ($expectedVersion !== 0) {
                    throw new OrganizationAdministrationConflict;
                }

                $this->lockAuthorityGraph($organizationId);
                $permissionRows = $this->assertDelegablePermissions(
                    $actor,
                    $permissionKeys,
                );
                $roleId = (string) Str::ulid();
                DB::table('roles')->insert([
                    'id' => $roleId,
                    'organization_id' => $organizationId,
                    'key' => $key,
                    'name' => $name,
                    'is_system' => false,
                    'status' => 'active',
                    'version' => 1,
                    'retired_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->insertPermissionGrants(
                    $organizationId,
                    $roleId,
                    $permissionRows,
                    $now,
                );
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    'role.created',
                    ['role_id' => $roleId],
                    $now,
                );

                foreach ($permissionRows as $permission) {
                    $this->events->record(
                        $organizationId,
                        $actorMembershipId,
                        $commandId,
                        'role.permission_granted',
                        [
                            'role_id' => $roleId,
                            'permission_id' => $this->string($permission, 'id'),
                        ],
                        $now,
                    );
                }

                $this->assertLastManager($organizationId);
                $this->audit->recordTenant(
                    'organization_administration.role.created',
                    'role',
                    $roleId,
                );

                return ['result_type' => 'role', 'result_id' => $roleId];
            },
        );
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    public function updateRole(
        User $actor,
        string $roleId,
        string $name,
        array $permissionKeys,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permission: OrganizationAdministrationAuthorization::ROLES_MANAGE,
            operation: 'role.update',
            targetType: 'role',
            targetId: $roleId,
            body: [
                'expected_version' => $expectedVersion,
                'name' => $name,
                'permission_keys' => $permissionKeys,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($actor, $roleId, $name, $permissionKeys, $expectedVersion): array {
                $this->lockAuthorityGraph($organizationId);
                $role = $this->lockedAggregate('roles', $organizationId, $roleId);

                if (
                    $this->boolean($role, 'is_system')
                    || $this->string($role, 'status') !== 'active'
                    || $this->integer($role, 'version') !== $expectedVersion
                    || DB::table('membership_roles')
                        ->where('organization_id', $organizationId)
                        ->where('membership_id', $actorMembershipId)
                        ->where('role_id', $roleId)
                        ->exists()
                ) {
                    throw new OrganizationAdministrationRejected;
                }

                $permissionRows = $this->assertDelegablePermissions(
                    $actor,
                    $permissionKeys,
                );
                $assignedToLearner = DB::table('membership_roles')
                    ->join('memberships', function ($join): void {
                        $join
                            ->on('memberships.organization_id', '=', 'membership_roles.organization_id')
                            ->on('memberships.id', '=', 'membership_roles.membership_id');
                    })
                    ->where('membership_roles.organization_id', $organizationId)
                    ->where('membership_roles.role_id', $roleId)
                    ->where('memberships.membership_type', Membership::TYPE_LEARNER)
                    ->exists();
                $normalizedPermissionKeys = array_values(array_unique($permissionKeys));
                sort($normalizedPermissionKeys);

                if (
                    $assignedToLearner
                    && $normalizedPermissionKeys !== ['learning.participate']
                ) {
                    throw new OrganizationAdministrationConflict(
                        OrganizationAdministrationConflict::ACTIVE_DEPENDENCY,
                    );
                }
                $currentRows = DB::table('role_permissions')
                    ->where('organization_id', $organizationId)
                    ->where('role_id', $roleId)
                    ->orderBy('permission_id')
                    ->lockForUpdate()
                    ->get(['permission_id']);
                $currentIds = $currentRows->pluck('permission_id')
                    ->filter(static fn (mixed $id): bool => is_string($id))
                    ->values()
                    ->all();
                $requestedIds = array_map(
                    fn (stdClass $permission): string => $this->string($permission, 'id'),
                    $permissionRows,
                );
                $revoked = array_values(array_diff($currentIds, $requestedIds));
                $granted = array_values(array_diff($requestedIds, $currentIds));

                if ($revoked !== []) {
                    DB::table('role_permissions')
                        ->where('organization_id', $organizationId)
                        ->where('role_id', $roleId)
                        ->whereIn('permission_id', $revoked)
                        ->delete();
                }

                if ($granted !== []) {
                    $newRows = array_values(array_filter(
                        $permissionRows,
                        fn (stdClass $permission): bool => in_array(
                            $this->string($permission, 'id'),
                            $granted,
                            true,
                        ),
                    ));
                    $this->insertPermissionGrants(
                        $organizationId,
                        $roleId,
                        $newRows,
                        $now,
                    );
                }

                DB::table('roles')->where('id', $roleId)->update([
                    'name' => $name,
                    'version' => $expectedVersion + 1,
                    'updated_at' => $now,
                ]);
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    'role.updated',
                    ['role_id' => $roleId],
                    $now,
                );

                foreach ($granted as $permissionId) {
                    $this->events->record(
                        $organizationId,
                        $actorMembershipId,
                        $commandId,
                        'role.permission_granted',
                        ['role_id' => $roleId, 'permission_id' => $permissionId],
                        $now,
                    );
                }

                foreach ($revoked as $permissionId) {
                    $this->events->record(
                        $organizationId,
                        $actorMembershipId,
                        $commandId,
                        'role.permission_revoked',
                        ['role_id' => $roleId, 'permission_id' => $permissionId],
                        $now,
                    );
                }

                $this->assertLastManager($organizationId);
                $this->audit->recordTenant(
                    'organization_administration.role.updated',
                    'role',
                    $roleId,
                );

                return ['result_type' => 'role', 'result_id' => $roleId];
            },
        );
    }

    public function retireRole(
        User $actor,
        string $roleId,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permission: OrganizationAdministrationAuthorization::ROLES_MANAGE,
            operation: 'role.retire',
            targetType: 'role',
            targetId: $roleId,
            body: ['expected_version' => $expectedVersion],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($roleId, $expectedVersion): array {
                $this->lockAuthorityGraph($organizationId);
                $role = $this->lockedAggregate('roles', $organizationId, $roleId);
                $assigned = DB::table('membership_roles')
                    ->where('organization_id', $organizationId)
                    ->where('role_id', $roleId)
                    ->orderBy('membership_id')
                    ->lockForUpdate()
                    ->exists();

                if ($assigned) {
                    throw new OrganizationAdministrationConflict(
                        OrganizationAdministrationConflict::ACTIVE_DEPENDENCY,
                    );
                }

                if (
                    $this->boolean($role, 'is_system')
                    || $this->string($role, 'status') !== 'active'
                    || $this->integer($role, 'version') !== $expectedVersion
                ) {
                    throw new OrganizationAdministrationConflict;
                }

                DB::table('roles')->where('id', $roleId)->update([
                    'status' => 'retired',
                    'version' => $expectedVersion + 1,
                    'retired_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    'role.retired',
                    ['role_id' => $roleId],
                    $now,
                );
                $this->assertLastManager($organizationId);
                $this->audit->recordTenant(
                    'organization_administration.role.retired',
                    'role',
                    $roleId,
                );

                return ['result_type' => 'role', 'result_id' => $roleId];
            },
        );
    }

    public function assignMembershipRole(
        User $actor,
        string $membershipId,
        string $roleId,
        int $expectedMembershipVersion,
        int $expectedRoleVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->changeMembershipRole(
            actor: $actor,
            membershipId: $membershipId,
            roleId: $roleId,
            expectedMembershipVersion: $expectedMembershipVersion,
            expectedRoleVersion: $expectedRoleVersion,
            idempotencyKey: $idempotencyKey,
            assign: true,
        );
    }

    public function revokeMembershipRole(
        User $actor,
        string $membershipId,
        string $roleId,
        int $expectedMembershipVersion,
        int $expectedRoleVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->changeMembershipRole(
            actor: $actor,
            membershipId: $membershipId,
            roleId: $roleId,
            expectedMembershipVersion: $expectedMembershipVersion,
            expectedRoleVersion: $expectedRoleVersion,
            idempotencyKey: $idempotencyKey,
            assign: false,
        );
    }

    private function changeMembershipRole(
        User $actor,
        string $membershipId,
        string $roleId,
        int $expectedMembershipVersion,
        int $expectedRoleVersion,
        string $idempotencyKey,
        bool $assign,
    ): OrganizationAdministrationCommandResult {
        $operation = $assign
            ? 'membership.assign_role'
            : 'membership.revoke_role';

        return $this->execute(
            actor: $actor,
            permission: OrganizationAdministrationAuthorization::ROLES_MANAGE,
            operation: $operation,
            targetType: 'membership',
            targetId: $membershipId,
            body: [
                'expected_membership_version' => $expectedMembershipVersion,
                'expected_role_version' => $expectedRoleVersion,
                'role_id' => $roleId,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use (
                $actor,
                $membershipId,
                $roleId,
                $expectedMembershipVersion,
                $expectedRoleVersion,
                $assign,
            ): array {
                if (hash_equals($actorMembershipId, $membershipId)) {
                    throw new OrganizationAdministrationRejected(
                        OrganizationAdministrationRejected::SELF_TARGET,
                        422,
                    );
                }

                $this->lockAuthorityGraph($organizationId);
                $membership = $this->lockedStaffMembership(
                    $organizationId,
                    $membershipId,
                );
                $role = $this->lockedAggregate('roles', $organizationId, $roleId);

                if (
                    $this->string($membership, 'status') !== Membership::STATUS_ACTIVE
                    || $this->integer($membership, 'version') !== $expectedMembershipVersion
                    || $this->string($role, 'status') !== 'active'
                    || $this->integer($role, 'version') !== $expectedRoleVersion
                ) {
                    throw new OrganizationAdministrationConflict;
                }

                if ($assign) {
                    $rolePermissionKeys = array_values(DB::table('role_permissions')
                        ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                        ->where('role_permissions.organization_id', $organizationId)
                        ->where('role_permissions.role_id', $roleId)
                        ->orderBy('permissions.key')
                        ->lockForUpdate()
                        ->pluck('permissions.key')
                        ->filter(static fn (mixed $key): bool => is_string($key))
                        ->values()
                        ->all());
                    $this->assertDelegablePermissions($actor, $rolePermissionKeys);
                }
                $relationship = DB::table('membership_roles')
                    ->where('organization_id', $organizationId)
                    ->where('membership_id', $membershipId)
                    ->where('role_id', $roleId)
                    ->lockForUpdate()
                    ->exists();

                if ($assign === $relationship) {
                    throw new OrganizationAdministrationConflict;
                }

                if ($assign) {
                    DB::table('membership_roles')->insert([
                        'organization_id' => $organizationId,
                        'membership_id' => $membershipId,
                        'role_id' => $roleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    DB::table('membership_roles')
                        ->where('organization_id', $organizationId)
                        ->where('membership_id', $membershipId)
                        ->where('role_id', $roleId)
                        ->delete();
                }

                DB::table('memberships')->where('id', $membershipId)->update([
                    'version' => $expectedMembershipVersion + 1,
                    'updated_at' => $now,
                ]);
                $eventType = $assign
                    ? 'membership.role_assigned'
                    : 'membership.role_revoked';
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    $eventType,
                    ['membership_id' => $membershipId, 'role_id' => $roleId],
                    $now,
                );
                $this->assertLastManager($organizationId);
                $this->audit->recordTenant(
                    'organization_administration.'.$eventType,
                    'membership',
                    $membershipId,
                );

                return ['result_type' => 'membership', 'result_id' => $membershipId];
            },
        );
    }

    public function deactivateMembership(
        User $actor,
        string $membershipId,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permission: OrganizationAdministrationAuthorization::MEMBERSHIPS_MANAGE,
            operation: 'membership.deactivate',
            targetType: 'membership',
            targetId: $membershipId,
            body: ['expected_version' => $expectedVersion],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($membershipId, $expectedVersion): array {
                if (hash_equals($actorMembershipId, $membershipId)) {
                    throw new OrganizationAdministrationRejected(
                        OrganizationAdministrationRejected::SELF_TARGET,
                        422,
                    );
                }

                $this->lockAuthorityGraph($organizationId);
                $membership = $this->lockedStaffMembership(
                    $organizationId,
                    $membershipId,
                );

                if (
                    $this->string($membership, 'status') !== Membership::STATUS_ACTIVE
                    || $this->integer($membership, 'version') !== $expectedVersion
                ) {
                    throw new OrganizationAdministrationConflict;
                }

                $roleIds = DB::table('membership_roles')
                    ->where('organization_id', $organizationId)
                    ->where('membership_id', $membershipId)
                    ->orderBy('role_id')
                    ->lockForUpdate()
                    ->pluck('role_id')
                    ->filter(static fn (mixed $id): bool => is_string($id))
                    ->values()
                    ->all();
                $cohortIds = DB::table('cohort_trainers')
                    ->where('organization_id', $organizationId)
                    ->where('trainer_membership_id', $membershipId)
                    ->orderBy('cohort_id')
                    ->lockForUpdate()
                    ->pluck('cohort_id')
                    ->filter(static fn (mixed $id): bool => is_string($id))
                    ->values()
                    ->all();
                $appointmentIds = DB::table('authority_appointments')
                    ->where('organization_id', $organizationId)
                    ->where('appointee_membership_id', $membershipId)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->pluck('id')
                    ->filter(static fn (mixed $id): bool => is_string($id))
                    ->values()
                    ->all();
                $credentialSnapshot = $this->credentials->snapshot(
                    $organizationId,
                    $membershipId,
                    Membership::STATUS_ACTIVE,
                    $expectedVersion,
                    true,
                );

                DB::table('membership_roles')
                    ->where('organization_id', $organizationId)
                    ->where('membership_id', $membershipId)
                    ->delete();
                DB::table('cohort_trainers')
                    ->where('organization_id', $organizationId)
                    ->where('trainer_membership_id', $membershipId)
                    ->delete();

                foreach ($roleIds as $roleId) {
                    $this->events->record(
                        $organizationId,
                        $actorMembershipId,
                        $commandId,
                        'membership.role_revoked',
                        ['membership_id' => $membershipId, 'role_id' => $roleId],
                        $now,
                        'membership_suspended',
                    );
                }

                foreach ($cohortIds as $cohortId) {
                    $this->events->record(
                        $organizationId,
                        $actorMembershipId,
                        $commandId,
                        'trainer.cohort_revoked',
                        ['membership_id' => $membershipId, 'cohort_id' => $cohortId],
                        $now,
                        'membership_suspended',
                    );
                }

                foreach ($appointmentIds as $appointmentId) {
                    if ($this->appointmentIsTerminal(
                        $organizationId,
                        $appointmentId,
                        $now,
                    )) {
                        continue;
                    }

                    DB::table('authority_appointment_events')->insert([
                        'id' => (string) Str::ulid(),
                        'organization_id' => $organizationId,
                        'authority_appointment_id' => $appointmentId,
                        'event_type' => 'revoked',
                        'actor_membership_id' => $actorMembershipId,
                        'reason_key' => 'membership_suspended',
                        'invalidates_prior_approvals' => false,
                        'effective_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $this->credentials->revoke(
                    $credentialSnapshot['revocable_web'],
                    $credentialSnapshot['revocable_mobile'],
                    'membership_suspended',
                    $now,
                );
                DB::table('memberships')->where('id', $membershipId)->update([
                    'status' => Membership::STATUS_SUSPENDED,
                    'version' => $expectedVersion + 1,
                    'updated_at' => $now,
                ]);
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    'membership.suspended',
                    ['membership_id' => $membershipId],
                    $now,
                );
                $this->assertLastManager($organizationId);
                $this->audit->recordTenant(
                    'organization_administration.membership.suspended',
                    'membership',
                    $membershipId,
                );

                return ['result_type' => 'membership', 'result_id' => $membershipId];
            },
        );
    }

    public function reactivateMembership(
        User $actor,
        string $membershipId,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permission: OrganizationAdministrationAuthorization::MEMBERSHIPS_MANAGE,
            operation: 'membership.reactivate',
            targetType: 'membership',
            targetId: $membershipId,
            body: ['expected_version' => $expectedVersion],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($membershipId, $expectedVersion): array {
                if (hash_equals($actorMembershipId, $membershipId)) {
                    throw new OrganizationAdministrationRejected(
                        OrganizationAdministrationRejected::SELF_TARGET,
                        422,
                    );
                }

                $this->lockAuthorityGraph($organizationId);
                $membership = $this->lockedStaffMembership(
                    $organizationId,
                    $membershipId,
                );

                if (
                    $this->string($membership, 'status') !== Membership::STATUS_SUSPENDED
                    || $this->integer($membership, 'version') !== $expectedVersion
                ) {
                    throw new OrganizationAdministrationConflict;
                }

                DB::table('memberships')->where('id', $membershipId)->update([
                    'status' => Membership::STATUS_ACTIVE,
                    'version' => $expectedVersion + 1,
                    'updated_at' => $now,
                ]);
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    'membership.reactivated',
                    ['membership_id' => $membershipId],
                    $now,
                );
                $this->assertLastManager($organizationId);
                $this->audit->recordTenant(
                    'organization_administration.membership.reactivated',
                    'membership',
                    $membershipId,
                );

                return ['result_type' => 'membership', 'result_id' => $membershipId];
            },
        );
    }

    public function assignTrainerCohort(
        User $actor,
        string $membershipId,
        string $cohortId,
        int $expectedMembershipVersion,
        int $expectedCohortVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->changeTrainerCohort(
            actor: $actor,
            membershipId: $membershipId,
            cohortId: $cohortId,
            expectedMembershipVersion: $expectedMembershipVersion,
            expectedCohortVersion: $expectedCohortVersion,
            idempotencyKey: $idempotencyKey,
            assign: true,
        );
    }

    public function revokeTrainerCohort(
        User $actor,
        string $membershipId,
        string $cohortId,
        int $expectedMembershipVersion,
        int $expectedCohortVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->changeTrainerCohort(
            actor: $actor,
            membershipId: $membershipId,
            cohortId: $cohortId,
            expectedMembershipVersion: $expectedMembershipVersion,
            expectedCohortVersion: $expectedCohortVersion,
            idempotencyKey: $idempotencyKey,
            assign: false,
        );
    }

    private function changeTrainerCohort(
        User $actor,
        string $membershipId,
        string $cohortId,
        int $expectedMembershipVersion,
        int $expectedCohortVersion,
        string $idempotencyKey,
        bool $assign,
    ): OrganizationAdministrationCommandResult {
        $operation = $assign
            ? 'trainer.assign_cohort'
            : 'trainer.revoke_cohort';

        return $this->execute(
            actor: $actor,
            permission: OrganizationAdministrationAuthorization::TRAINERS_MANAGE,
            operation: $operation,
            targetType: 'trainer_profile',
            targetId: $membershipId,
            body: [
                'cohort_id' => $cohortId,
                'expected_cohort_version' => $expectedCohortVersion,
                'expected_membership_version' => $expectedMembershipVersion,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use (
                $membershipId,
                $cohortId,
                $expectedMembershipVersion,
                $expectedCohortVersion,
                $assign,
            ): array {
                $membership = $this->lockedStaffMembership(
                    $organizationId,
                    $membershipId,
                );
                $cohort = $this->lockedAggregate('cohorts', $organizationId, $cohortId);

                if (
                    $this->string($membership, 'membership_type') !== Membership::TYPE_TRAINER
                    || $this->string($membership, 'status') !== Membership::STATUS_ACTIVE
                    || $this->integer($membership, 'version') !== $expectedMembershipVersion
                    || $this->string($cohort, 'status') !== 'active'
                    || $this->integer($cohort, 'version') !== $expectedCohortVersion
                ) {
                    throw new OrganizationAdministrationConflict;
                }

                $relationship = DB::table('cohort_trainers')
                    ->where('organization_id', $organizationId)
                    ->where('trainer_membership_id', $membershipId)
                    ->where('cohort_id', $cohortId)
                    ->lockForUpdate()
                    ->exists();

                if ($assign === $relationship) {
                    throw new OrganizationAdministrationConflict;
                }

                if ($assign) {
                    DB::table('cohort_trainers')->insert([
                        'organization_id' => $organizationId,
                        'trainer_membership_id' => $membershipId,
                        'cohort_id' => $cohortId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    DB::table('cohort_trainers')
                        ->where('organization_id', $organizationId)
                        ->where('trainer_membership_id', $membershipId)
                        ->where('cohort_id', $cohortId)
                        ->delete();
                }

                DB::table('memberships')->where('id', $membershipId)->update([
                    'version' => $expectedMembershipVersion + 1,
                    'updated_at' => $now,
                ]);
                $eventType = $assign
                    ? 'trainer.cohort_assigned'
                    : 'trainer.cohort_revoked';
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    $eventType,
                    ['membership_id' => $membershipId, 'cohort_id' => $cohortId],
                    $now,
                );
                $this->audit->recordTenant(
                    'organization_administration.'.$eventType,
                    'membership',
                    $membershipId,
                );

                return ['result_type' => 'trainer_profile', 'result_id' => $membershipId];
            },
        );
    }

    public function createAppointment(
        User $actor,
        string $membershipId,
        string $authorityKind,
        string $scopeType,
        ?string $programId,
        CarbonInterface $validFrom,
        ?CarbonInterface $validUntil,
        string $qualificationBasis,
        string $approvalEvidenceKey,
        int $expectedMembershipVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permission: OrganizationAdministrationAuthorization::APPOINTMENTS_MANAGE,
            operation: 'appointment.create',
            targetType: 'trainer_profile',
            targetId: $membershipId,
            body: [
                'approval_evidence_key' => $approvalEvidenceKey,
                'authority_kind' => $authorityKind,
                'expected_membership_version' => $expectedMembershipVersion,
                'program_id' => $programId,
                'qualification_basis' => $qualificationBasis,
                'scope_type' => $scopeType,
                'valid_from' => $validFrom->toIso8601String(),
                'valid_until' => $validUntil?->toIso8601String(),
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use (
                $actor,
                $membershipId,
                $authorityKind,
                $scopeType,
                $programId,
                $validFrom,
                $validUntil,
                $qualificationBasis,
                $approvalEvidenceKey,
                $expectedMembershipVersion,
                $idempotencyKey,
            ): array {
                $membership = $this->lockedStaffMembership(
                    $organizationId,
                    $membershipId,
                );

                if (
                    $this->string($membership, 'membership_type') !== Membership::TYPE_TRAINER
                    || $this->string($membership, 'status') !== Membership::STATUS_ACTIVE
                    || $this->integer($membership, 'version') !== $expectedMembershipVersion
                ) {
                    throw new OrganizationAdministrationConflict;
                }

                $appointmentId = $this->appointments->appoint(
                    actor: $actor,
                    appointeeUserId: $this->string($membership, 'user_id'),
                    appointeeMembershipId: $membershipId,
                    authorityKind: AuthorityKind::from($authorityKind),
                    scopeType: $scopeType,
                    programId: $programId,
                    validFrom: $validFrom,
                    validUntil: $validUntil,
                    qualificationBasis: $qualificationBasis,
                    approvalEvidenceKey: $approvalEvidenceKey,
                    idempotencyKey: $idempotencyKey,
                );

                return [
                    'result_type' => 'authority_appointment',
                    'result_id' => $appointmentId,
                ];
            },
        );
    }

    public function acceptAppointment(
        User $actor,
        string $membershipId,
        string $appointmentId,
        string $stateFingerprint,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->changeOwnAppointment(
            actor: $actor,
            membershipId: $membershipId,
            appointmentId: $appointmentId,
            stateFingerprint: $stateFingerprint,
            idempotencyKey: $idempotencyKey,
            accept: true,
        );
    }

    public function surrenderAppointment(
        User $actor,
        string $membershipId,
        string $appointmentId,
        string $stateFingerprint,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->changeOwnAppointment(
            actor: $actor,
            membershipId: $membershipId,
            appointmentId: $appointmentId,
            stateFingerprint: $stateFingerprint,
            idempotencyKey: $idempotencyKey,
            accept: false,
        );
    }

    private function changeOwnAppointment(
        User $actor,
        string $membershipId,
        string $appointmentId,
        string $stateFingerprint,
        string $idempotencyKey,
        bool $accept,
    ): OrganizationAdministrationCommandResult {
        $operation = $accept ? 'appointment.accept' : 'appointment.surrender';

        return $this->execute(
            actor: $actor,
            permission: null,
            operation: $operation,
            targetType: 'authority_appointment',
            targetId: $appointmentId,
            body: ['state_fingerprint' => $stateFingerprint],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use (
                $actor,
                $membershipId,
                $appointmentId,
                $stateFingerprint,
                $idempotencyKey,
                $accept,
            ): array {
                if (! hash_equals($actorMembershipId, $membershipId)) {
                    throw new OrganizationAdministrationRejected;
                }

                $appointment = $this->lockedAppointment(
                    $organizationId,
                    $membershipId,
                    $appointmentId,
                );
                $this->assertAppointmentNotEffectivelyExpired(
                    $appointment,
                    $now,
                );

                if (
                    ! hash_equals(
                        $this->string($appointment, 'appointee_user_id'),
                        (string) $actor->getKey(),
                    )
                    || ! hash_equals(
                        $this->appointmentStateFingerprint(
                            $organizationId,
                            $appointmentId,
                            true,
                        ),
                        $stateFingerprint,
                    )
                ) {
                    throw new OrganizationAdministrationConflict;
                }

                $eventId = $accept
                    ? $this->appointments->accept($actor, $appointmentId, $idempotencyKey)
                    : $this->appointments->surrender($actor, $appointmentId, $idempotencyKey);

                return [
                    'result_type' => 'authority_appointment_event',
                    'result_id' => $eventId,
                ];
            },
        );
    }

    public function revokeAppointment(
        User $actor,
        string $membershipId,
        string $appointmentId,
        string $stateFingerprint,
        string $reasonKey,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        if ($reasonKey !== 'operator_revoked') {
            throw new OrganizationAdministrationRejected;
        }

        return $this->execute(
            actor: $actor,
            permission: OrganizationAdministrationAuthorization::APPOINTMENTS_MANAGE,
            operation: 'appointment.revoke',
            targetType: 'authority_appointment',
            targetId: $appointmentId,
            body: [
                'reason_key' => $reasonKey,
                'state_fingerprint' => $stateFingerprint,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use (
                $actor,
                $membershipId,
                $appointmentId,
                $stateFingerprint,
                $reasonKey,
                $idempotencyKey,
            ): array {
                $appointment = $this->lockedAppointment(
                    $organizationId,
                    $membershipId,
                    $appointmentId,
                );
                $this->assertAppointmentNotEffectivelyExpired(
                    $appointment,
                    $now,
                );

                if (! hash_equals(
                    $this->appointmentStateFingerprint(
                        $organizationId,
                        $appointmentId,
                        true,
                    ),
                    $stateFingerprint,
                )) {
                    throw new OrganizationAdministrationConflict;
                }

                $eventId = $this->appointments->revoke(
                    actor: $actor,
                    appointmentId: $appointmentId,
                    reasonKey: $reasonKey,
                    idempotencyKey: $idempotencyKey,
                );

                return [
                    'result_type' => 'authority_appointment_event',
                    'result_id' => $eventId,
                ];
            },
        );
    }

    public function revokeMembershipSessions(
        User $actor,
        string $membershipId,
        string $stateFingerprint,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permission: OrganizationAdministrationAuthorization::MEMBERSHIP_SESSIONS_REVOKE,
            operation: 'membership_session.revoke',
            targetType: 'membership',
            targetId: $membershipId,
            body: ['state_fingerprint' => $stateFingerprint],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($membershipId, $stateFingerprint): array {
                if (hash_equals($actorMembershipId, $membershipId)) {
                    throw new OrganizationAdministrationRejected(
                        OrganizationAdministrationRejected::SELF_TARGET,
                        422,
                    );
                }

                $membership = $this->lockedStaffMembership(
                    $organizationId,
                    $membershipId,
                );

                if ($this->string($membership, 'status') !== Membership::STATUS_ACTIVE) {
                    throw new OrganizationAdministrationConflict;
                }

                $snapshot = $this->credentials->snapshot(
                    $organizationId,
                    $membershipId,
                    $this->string($membership, 'status'),
                    $this->integer($membership, 'version'),
                    true,
                );

                if (
                    ! hash_equals($snapshot['fingerprint'], $stateFingerprint)
                    || ($snapshot['web']->isEmpty() && $snapshot['mobile']->isEmpty())
                ) {
                    throw new OrganizationAdministrationConflict;
                }

                $revoked = $this->credentials->revoke(
                    $snapshot['revocable_web'],
                    $snapshot['revocable_mobile'],
                    'operator_membership_revoked',
                    $now,
                );
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    'membership_session.revoked',
                    ['membership_id' => $membershipId],
                    $now,
                    'operator_membership_revoked',
                );
                $this->audit->recordTenant(
                    'organization_administration.membership_session.revoked',
                    'membership',
                    $membershipId,
                );

                return [
                    'result_type' => 'membership_sessions',
                    'result_id' => $membershipId,
                    'revoked_web_session_count' => $revoked['web'],
                    'revoked_android_family_count' => $revoked['mobile'],
                ];
            },
        );
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  Closure(string, string, string, CarbonImmutable): array{
     *     result_type: string,
     *     result_id: string,
     *     activation_code?: string,
     *     revoked_web_session_count?: int,
     *     revoked_android_family_count?: int
     * }  $command
     */
    private function execute(
        User $actor,
        ?string $permission,
        string $operation,
        string $targetType,
        string $targetId,
        array $body,
        string $idempotencyKey,
        Closure $command,
    ): OrganizationAdministrationCommandResult {
        try {
            return DB::transaction(function () use (
                $actor,
                $permission,
                $operation,
                $targetType,
                $targetId,
                $body,
                $idempotencyKey,
                $command,
            ): OrganizationAdministrationCommandResult {
                $fingerprint = $this->idempotency->fingerprint(
                    $operation,
                    $targetType,
                    $targetId,
                    $body,
                );
                $claim = $this->idempotency->claim(
                    $actor,
                    $idempotencyKey,
                    $operation,
                    $targetType,
                    $targetId,
                    $fingerprint,
                );
                $context = $this->authorization->contextFor($actor);
                $organization = DB::table('organizations')
                    ->where('id', $context->organizationId)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first(['id']);

                if (! $organization instanceof stdClass) {
                    throw new OrganizationAdministrationRejected;
                }

                $actorMembership = $this->authorization->actorMembership(
                    $actor,
                    true,
                );

                if ($permission !== null) {
                    $this->authorization->assert($actor, $permission);
                }

                if ($claim->replayed) {
                    if (
                        $claim->resultType === null
                        || $claim->resultId === null
                        || $claim->completedAt === null
                    ) {
                        throw new OrganizationAdministrationConflict;
                    }

                    $counts = $claim->resultType === 'membership_sessions'
                        ? $this->revokedCredentialCounts(
                            $context->organizationId,
                            $claim->resultId,
                            $claim->completedAt,
                        )
                        : ['web' => null, 'mobile' => null];

                    return new OrganizationAdministrationCommandResult(
                        resultType: $claim->resultType,
                        resultId: $claim->resultId,
                        replayed: true,
                        completedAt: $claim->completedAt,
                        activationCode: null,
                        revokedWebSessionCount: $counts['web'],
                        revokedAndroidFamilyCount: $counts['mobile'],
                    );
                }

                $now = CarbonImmutable::now();
                $outcome = $command(
                    $context->organizationId,
                    $this->string($actorMembership, 'id'),
                    $claim->id,
                    $now,
                );
                $resultType = $outcome['result_type'];
                $resultId = $outcome['result_id'];
                $this->idempotency->complete(
                    $claim,
                    $resultType,
                    $resultId,
                    $now,
                );

                return new OrganizationAdministrationCommandResult(
                    resultType: $resultType,
                    resultId: $resultId,
                    replayed: false,
                    completedAt: $now,
                    activationCode: $outcome['activation_code'] ?? null,
                    revokedWebSessionCount: $outcome['revoked_web_session_count'] ?? null,
                    revokedAndroidFamilyCount: $outcome['revoked_android_family_count'] ?? null,
                );
            }, 3);
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '23503', '23505'], true)) {
                throw new OrganizationAdministrationConflict(previous: $exception);
            }

            throw $exception;
        }
    }

    private function organizationId(User $actor): string
    {
        return $this->authorization->contextFor($actor)->organizationId;
    }

    private function lockedAggregate(
        string $table,
        string $organizationId,
        string $id,
    ): stdClass {
        $row = DB::table($table)
            ->where('organization_id', $organizationId)
            ->where('id', $id)
            ->lockForUpdate()
            ->first();

        if (! $row instanceof stdClass) {
            throw new OrganizationAdministrationRejected;
        }

        return $row;
    }

    private function lockedStaffMembership(
        string $organizationId,
        string $membershipId,
    ): stdClass {
        $membership = DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->where('id', $membershipId)
            ->whereIn('membership_type', [
                Membership::TYPE_TRAINER,
                Membership::TYPE_ORGANIZATION_ADMIN,
            ])
            ->lockForUpdate()
            ->first([
                'id',
                'organization_id',
                'user_id',
                'membership_type',
                'status',
                'version',
            ]);

        if (! $membership instanceof stdClass) {
            throw new OrganizationAdministrationRejected;
        }

        return $membership;
    }

    private function assertActiveVersion(stdClass $row, int $expectedVersion): void
    {
        if (
            $this->string($row, 'status') !== 'active'
            || $this->integer($row, 'version') !== $expectedVersion
        ) {
            throw new OrganizationAdministrationConflict;
        }
    }

    private function assertActiveBranch(
        string $organizationId,
        ?string $branchId,
    ): void {
        if ($branchId === null) {
            return;
        }

        $branch = DB::table('branches')
            ->where('organization_id', $organizationId)
            ->where('id', $branchId)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first(['id']);

        if (! $branch instanceof stdClass) {
            throw new OrganizationAdministrationRejected;
        }
    }

    private function lockAuthorityGraph(string $organizationId): void
    {
        DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->whereIn('membership_type', [
                Membership::TYPE_TRAINER,
                Membership::TYPE_ORGANIZATION_ADMIN,
            ])
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
        DB::table('membership_roles')
            ->where('organization_id', $organizationId)
            ->orderBy('membership_id')
            ->orderBy('role_id')
            ->lockForUpdate()
            ->get(['membership_id', 'role_id']);
        DB::table('roles')
            ->where('organization_id', $organizationId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
        DB::table('role_permissions')
            ->where('organization_id', $organizationId)
            ->orderBy('role_id')
            ->orderBy('permission_id')
            ->lockForUpdate()
            ->get(['role_id', 'permission_id']);
    }

    /**
     * @param  list<string>  $permissionKeys
     * @return list<stdClass>
     */
    private function assertDelegablePermissions(
        User $actor,
        array $permissionKeys,
    ): array {
        $held = $this->authorization->permissionRows($actor, true)
            ->keyBy(static fn (stdClass $permission): string => (
                is_string($permission->key ?? null) ? $permission->key : ''
            ));
        $rows = [];

        foreach ($permissionKeys as $permissionKey) {
            $permission = $held->get($permissionKey);

            if (! $permission instanceof stdClass) {
                throw new OrganizationAdministrationRejected;
            }

            $rows[] = $permission;
        }

        return $rows;
    }

    /**
     * @param  list<stdClass>  $permissions
     */
    private function insertPermissionGrants(
        string $organizationId,
        string $roleId,
        array $permissions,
        CarbonInterface $now,
    ): void {
        $rows = array_map(fn (stdClass $permission): array => [
            'organization_id' => $organizationId,
            'role_id' => $roleId,
            'permission_id' => $this->string($permission, 'id'),
            'created_at' => $now,
            'updated_at' => $now,
        ], $permissions);

        if ($rows !== []) {
            DB::table('role_permissions')->insert($rows);
        }
    }

    private function assertLastManager(string $organizationId): void
    {
        $exists = DB::table('memberships as manager_memberships')
            ->where('manager_memberships.organization_id', $organizationId)
            ->where('manager_memberships.status', Membership::STATUS_ACTIVE)
            ->whereIn('manager_memberships.membership_type', [
                Membership::TYPE_TRAINER,
                Membership::TYPE_ORGANIZATION_ADMIN,
            ])
            ->whereExists(function ($query) use ($organizationId): void {
                $query->selectRaw('1')
                    ->from('membership_roles as manager_role_memberships')
                    ->join('roles as manager_roles', function ($join): void {
                        $join
                            ->on('manager_roles.organization_id', '=', 'manager_role_memberships.organization_id')
                            ->on('manager_roles.id', '=', 'manager_role_memberships.role_id');
                    })
                    ->join('role_permissions as manager_grants', function ($join): void {
                        $join
                            ->on('manager_grants.organization_id', '=', 'manager_roles.organization_id')
                            ->on('manager_grants.role_id', '=', 'manager_roles.id');
                    })
                    ->join('permissions as manager_permissions', 'manager_permissions.id', '=', 'manager_grants.permission_id')
                    ->whereColumn('manager_role_memberships.membership_id', 'manager_memberships.id')
                    ->where('manager_role_memberships.organization_id', $organizationId)
                    ->where('manager_roles.status', 'active')
                    ->where('manager_permissions.key', OrganizationAdministrationAuthorization::ROLES_MANAGE);
            })
            ->whereExists(function ($query) use ($organizationId): void {
                $query->selectRaw('1')
                    ->from('membership_roles as lifecycle_role_memberships')
                    ->join('roles as lifecycle_roles', function ($join): void {
                        $join
                            ->on('lifecycle_roles.organization_id', '=', 'lifecycle_role_memberships.organization_id')
                            ->on('lifecycle_roles.id', '=', 'lifecycle_role_memberships.role_id');
                    })
                    ->join('role_permissions as lifecycle_grants', function ($join): void {
                        $join
                            ->on('lifecycle_grants.organization_id', '=', 'lifecycle_roles.organization_id')
                            ->on('lifecycle_grants.role_id', '=', 'lifecycle_roles.id');
                    })
                    ->join('permissions as lifecycle_permissions', 'lifecycle_permissions.id', '=', 'lifecycle_grants.permission_id')
                    ->whereColumn('lifecycle_role_memberships.membership_id', 'manager_memberships.id')
                    ->where('lifecycle_role_memberships.organization_id', $organizationId)
                    ->where('lifecycle_roles.status', 'active')
                    ->where('lifecycle_permissions.key', OrganizationAdministrationAuthorization::MEMBERSHIPS_MANAGE);
            })
            ->exists();

        if (! $exists) {
            throw new OrganizationAdministrationConflict(
                OrganizationAdministrationConflict::LAST_MANAGER,
            );
        }
    }

    private function lockedAppointment(
        string $organizationId,
        string $membershipId,
        string $appointmentId,
    ): stdClass {
        $appointment = DB::table('authority_appointments')
            ->where('organization_id', $organizationId)
            ->where('appointee_membership_id', $membershipId)
            ->where('id', $appointmentId)
            ->lockForUpdate()
            ->first();

        if (! $appointment instanceof stdClass) {
            throw new OrganizationAdministrationRejected;
        }

        return $appointment;
    }

    private function appointmentStateFingerprint(
        string $organizationId,
        string $appointmentId,
        bool $lock,
    ): string {
        $query = DB::table('authority_appointment_events')
            ->where('organization_id', $organizationId)
            ->where('authority_appointment_id', $appointmentId)
            ->orderBy('effective_at')
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $events = $query->get(['id', 'event_type', 'effective_at']);

        return CanonicalJson::sha256([
            'appointment_id' => $appointmentId,
            'events' => $events->map(fn (stdClass $event): array => [
                'id' => $this->string($event, 'id'),
                'event_type' => $this->string($event, 'event_type'),
                'effective_at' => $this->carbon($event, 'effective_at')
                    ->toIso8601String(),
            ])->all(),
        ]);
    }

    private function appointmentIsTerminal(
        string $organizationId,
        string $appointmentId,
        CarbonInterface $now,
    ): bool {
        $validUntil = DB::table('authority_appointments')
            ->where('organization_id', $organizationId)
            ->where('id', $appointmentId)
            ->lockForUpdate()
            ->value('valid_until');

        if (
            $validUntil !== null
            && CarbonImmutable::parse((string) $validUntil)->lessThanOrEqualTo(
                $now,
            )
        ) {
            return true;
        }

        return DB::table('authority_appointment_events')
            ->where('organization_id', $organizationId)
            ->where('authority_appointment_id', $appointmentId)
            ->whereIn('event_type', [
                'surrendered',
                'revoked',
                'revoked_for_cause',
                'expired',
            ])
            ->orderBy('id')
            ->lockForUpdate()
            ->exists();
    }

    private function assertAppointmentNotEffectivelyExpired(
        stdClass $appointment,
        CarbonInterface $now,
    ): void {
        $validUntil = $appointment->valid_until ?? null;

        if (
            $validUntil !== null
            && CarbonImmutable::parse((string) $validUntil)->lessThanOrEqualTo(
                $now,
            )
        ) {
            throw new OrganizationAdministrationConflict;
        }
    }

    /**
     * @return array{web: int|null, mobile: int|null}
     */
    private function revokedCredentialCounts(
        string $organizationId,
        string $membershipId,
        CarbonInterface $completedAt,
    ): array {
        return [
            'web' => DB::table('authentication_sessions')
                ->where('selected_organization_id', $organizationId)
                ->where('selected_membership_id', $membershipId)
                ->where('revocation_reason', 'operator_membership_revoked')
                ->where('revoked_at', $completedAt)
                ->count(),
            'mobile' => DB::table('mobile_token_families')
                ->where('selected_organization_id', $organizationId)
                ->where('selected_membership_id', $membershipId)
                ->where('revocation_reason', 'operator_membership_revoked')
                ->where('revoked_at', $completedAt)
                ->count(),
        ];
    }

    private function string(stdClass $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        if (! is_string($value) || $value === '') {
            throw new OrganizationAdministrationRejected;
        }

        return $value;
    }

    private function integer(stdClass $row, string $property): int
    {
        return (int) ($row->{$property} ?? 0);
    }

    private function boolean(stdClass $row, string $property): bool
    {
        return (bool) ($row->{$property} ?? false);
    }

    private function carbon(stdClass $row, string $property): CarbonImmutable
    {
        $value = $row->{$property} ?? null;

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value)) {
            return CarbonImmutable::parse($value);
        }

        throw new OrganizationAdministrationRejected;
    }
}

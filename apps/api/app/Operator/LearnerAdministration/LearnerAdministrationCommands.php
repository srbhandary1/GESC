<?php

declare(strict_types=1);

namespace App\Operator\LearnerAdministration;

use App\Audit\AuditEventRecorder;
use App\Curriculum\Enrollment\EnrollmentCurriculumPinner;
use App\Models\Membership;
use App\Models\User;
use App\Operator\LearnerAdministration\Exceptions\LearnerAdministrationConflict;
use App\Operator\LearnerAdministration\Exceptions\LearnerAdministrationRejected;
use App\Operator\OrganizationAdministration\MembershipSelectedCredentialService;
use App\Operator\OrganizationAdministration\OrganizationAdministrationCommandResult;
use App\Operator\OrganizationAdministration\OrganizationAdministrationEventRecorder;
use App\Tenancy\InstitutionInviteCodeDigester;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class LearnerAdministrationCommands
{
    public function __construct(
        private LearnerAdministrationAuthorization $authorization,
        private LearnerAdministrationIdempotency $idempotency,
        private OrganizationAdministrationEventRecorder $events,
        private MembershipSelectedCredentialService $credentials,
        private InstitutionInviteCodeDigester $inviteCodes,
        private EnrollmentCurriculumPinner $curriculumPinner,
        private AuditEventRecorder $audit,
    ) {}

    public function issueInvitation(
        User $actor,
        string $roleId,
        int $expectedRoleVersion,
        ?string $cohortId,
        ?int $expectedCohortVersion,
        int $expiresInHours,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permissions: LearnerAdministrationAuthorization::INVITATION_PERMISSIONS,
            operation: 'learner_invitation.issue',
            targetType: 'learner_invitation_collection',
            targetId: $this->organizationId($actor),
            body: [
                'cohort_id' => $cohortId,
                'expected_cohort_version' => $expectedCohortVersion,
                'expected_role_version' => $expectedRoleVersion,
                'expected_version' => $expectedVersion,
                'expires_in_hours' => $expiresInHours,
                'role_id' => $roleId,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use (
                $roleId,
                $expectedRoleVersion,
                $cohortId,
                $expectedCohortVersion,
                $expiresInHours,
                $expectedVersion,
            ): array {
                if ($expectedVersion !== 0) {
                    throw new LearnerAdministrationConflict;
                }

                $this->assertLearnerSafeRole(
                    $organizationId,
                    $roleId,
                    $expectedRoleVersion,
                );
                $this->assertCohortVersion(
                    $organizationId,
                    $cohortId,
                    $expectedCohortVersion,
                );

                $rawCode = rtrim(
                    strtr(base64_encode(random_bytes(16)), '+/', '-_'),
                    '=',
                );
                $invitationId = (string) Str::ulid();
                DB::table('institution_invites')->insert([
                    'id' => $invitationId,
                    'organization_id' => $organizationId,
                    'code_digest' => $this->inviteCodes->digest($rawCode),
                    'membership_type' => Membership::TYPE_LEARNER,
                    'role_id' => $roleId,
                    'cohort_id' => $cohortId,
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
                    'learner_invitation.issued',
                    ['invitation_id' => $invitationId],
                    $now,
                );
                $this->audit->recordTenant(
                    'learner_administration.invitation.issued',
                    'institution_invitation',
                    $invitationId,
                );

                return [
                    'result_type' => 'learner_invitation',
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
            permissions: [
                LearnerAdministrationAuthorization::LIFECYCLE_MANAGE,
                LearnerAdministrationAuthorization::INVITATIONS_MANAGE,
            ],
            operation: 'learner_invitation.revoke',
            targetType: 'learner_invitation',
            targetId: $invitationId,
            body: ['expected_version' => $expectedVersion],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($invitationId, $expectedVersion): array {
                $invitation = DB::table('institution_invites')
                    ->where('organization_id', $organizationId)
                    ->where('id', $invitationId)
                    ->where('membership_type', Membership::TYPE_LEARNER)
                    ->lockForUpdate()
                    ->first();

                if (! $invitation instanceof stdClass) {
                    throw new LearnerAdministrationRejected;
                }

                if (
                    $this->string($invitation, 'status') !== 'pending'
                    || $this->integer($invitation, 'version') !== $expectedVersion
                    || $this->carbon($invitation, 'expires_at')->lessThanOrEqualTo($now)
                ) {
                    throw new LearnerAdministrationConflict;
                }

                DB::table('institution_invites')
                    ->where('organization_id', $organizationId)
                    ->where('id', $invitationId)
                    ->update([
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
                    'learner_invitation.revoked',
                    ['invitation_id' => $invitationId],
                    $now,
                );
                $this->audit->recordTenant(
                    'learner_administration.invitation.revoked',
                    'institution_invitation',
                    $invitationId,
                );

                return [
                    'result_type' => 'learner_invitation',
                    'result_id' => $invitationId,
                ];
            },
        );
    }

    public function correctGoal(
        User $actor,
        string $membershipId,
        string $goalKey,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permissions: [LearnerAdministrationAuthorization::PROFILE_CORRECT],
            operation: 'learner_goal.correct',
            targetType: 'learner_membership',
            targetId: $membershipId,
            body: [
                'expected_version' => $expectedVersion,
                'goal_key' => $goalKey,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($membershipId, $goalKey, $expectedVersion): array {
                $this->lockedLearnerMembership(
                    $organizationId,
                    $membershipId,
                    Membership::STATUS_ACTIVE,
                );
                $profile = DB::table('learner_profiles')
                    ->where('organization_id', $organizationId)
                    ->where('learner_membership_id', $membershipId)
                    ->lockForUpdate()
                    ->first([
                        'id',
                        'adult_confirmed_at',
                        'onboarding_completed_at',
                    ]);

                if (
                    ! $profile instanceof stdClass
                    || $profile->adult_confirmed_at === null
                    || $profile->onboarding_completed_at === null
                ) {
                    throw new LearnerAdministrationConflict(
                        LearnerAdministrationConflict::ACTIVE_DEPENDENCY,
                    );
                }

                $profileId = $this->string($profile, 'id');
                $current = DB::table('learner_profile_current_goals')
                    ->where('organization_id', $organizationId)
                    ->where('learner_profile_id', $profileId)
                    ->where('learner_membership_id', $membershipId)
                    ->lockForUpdate()
                    ->first(['id', 'goal_key', 'version']);

                if (! $current instanceof stdClass) {
                    throw new LearnerAdministrationConflict(
                        LearnerAdministrationConflict::ACTIVE_DEPENDENCY,
                    );
                }

                $previousGoal = $this->string($current, 'goal_key');

                if (
                    $this->integer($current, 'version') !== $expectedVersion
                    || hash_equals($previousGoal, $goalKey)
                ) {
                    throw new LearnerAdministrationConflict;
                }

                DB::table('learner_goal_corrections')->insert([
                    'id' => (string) Str::ulid(),
                    'organization_id' => $organizationId,
                    'learner_profile_id' => $profileId,
                    'learner_membership_id' => $membershipId,
                    'actor_membership_id' => $actorMembershipId,
                    'command_id' => $commandId,
                    'previous_goal_key' => $previousGoal,
                    'successor_goal_key' => $goalKey,
                    'effective_at' => $now,
                    'created_at' => $now,
                ]);
                DB::table('learner_profile_current_goals')
                    ->where('organization_id', $organizationId)
                    ->where('id', $this->string($current, 'id'))
                    ->where('version', $expectedVersion)
                    ->update([
                        'goal_key' => $goalKey,
                        'version' => $expectedVersion + 1,
                        'corrected_at' => $now,
                        'updated_at' => $now,
                    ]);
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    'learner_goal.corrected',
                    [
                        'membership_id' => $membershipId,
                        'learner_profile_id' => $profileId,
                    ],
                    $now,
                );
                $this->audit->recordTenant(
                    'learner_administration.goal.corrected',
                    'learner_profile',
                    $profileId,
                );

                return [
                    'result_type' => 'learner',
                    'result_id' => $membershipId,
                ];
            },
        );
    }

    public function createEnrollment(
        User $actor,
        string $membershipId,
        int $expectedMembershipVersion,
        ?string $cohortId,
        ?int $expectedCohortVersion,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permissions: [LearnerAdministrationAuthorization::ENROLLMENTS_MANAGE],
            operation: 'learner_enrollment.create',
            targetType: 'learner_membership',
            targetId: $membershipId,
            body: [
                'cohort_id' => $cohortId,
                'expected_cohort_version' => $expectedCohortVersion,
                'expected_membership_version' => $expectedMembershipVersion,
                'expected_version' => $expectedVersion,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use (
                $membershipId,
                $expectedMembershipVersion,
                $cohortId,
                $expectedCohortVersion,
                $expectedVersion,
            ): array {
                if ($expectedVersion !== 0) {
                    throw new LearnerAdministrationConflict;
                }

                $membership = $this->lockedLearnerMembership(
                    $organizationId,
                    $membershipId,
                    Membership::STATUS_ACTIVE,
                );

                if ($this->integer($membership, 'version') !== $expectedMembershipVersion) {
                    throw new LearnerAdministrationConflict;
                }

                $currentExists = DB::table('enrollments')
                    ->where('organization_id', $organizationId)
                    ->where('learner_membership_id', $membershipId)
                    ->whereIn('status', ['pending_curriculum', 'active'])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->exists();

                if ($currentExists) {
                    throw new LearnerAdministrationConflict(
                        LearnerAdministrationConflict::ACTIVE_DEPENDENCY,
                    );
                }

                $this->assertCohortVersion(
                    $organizationId,
                    $cohortId,
                    $expectedCohortVersion,
                );
                $enrollmentId = (string) Str::ulid();
                DB::table('enrollments')->insert([
                    'id' => $enrollmentId,
                    'organization_id' => $organizationId,
                    'learner_membership_id' => $membershipId,
                    'cohort_id' => $cohortId,
                    'curriculum_owner_organization_id' => null,
                    'curriculum_version_id' => null,
                    'curriculum_publication_id' => null,
                    'curriculum_share_grant_id' => null,
                    'status' => 'pending_curriculum',
                    'version' => 1,
                    'enrolled_at' => $now,
                    'activated_at' => null,
                    'ended_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    'learner_enrollment.created',
                    [
                        'membership_id' => $membershipId,
                        'enrollment_id' => $enrollmentId,
                        ...($cohortId === null ? [] : ['cohort_id' => $cohortId]),
                    ],
                    $now,
                );
                $this->audit->recordTenant(
                    'learner_administration.enrollment.created',
                    'enrollment',
                    $enrollmentId,
                );

                return [
                    'result_type' => 'learner_enrollment',
                    'result_id' => $enrollmentId,
                ];
            },
        );
    }

    public function changeEnrollmentCohort(
        User $actor,
        string $enrollmentId,
        ?string $cohortId,
        ?int $expectedCohortVersion,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permissions: [LearnerAdministrationAuthorization::ENROLLMENTS_MANAGE],
            operation: 'learner_enrollment.change_cohort',
            targetType: 'learner_enrollment',
            targetId: $enrollmentId,
            body: [
                'cohort_id' => $cohortId,
                'expected_cohort_version' => $expectedCohortVersion,
                'expected_version' => $expectedVersion,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use (
                $enrollmentId,
                $cohortId,
                $expectedCohortVersion,
                $expectedVersion,
            ): array {
                $enrollment = $this->lockedEnrollment($organizationId, $enrollmentId);
                $membershipId = $this->string($enrollment, 'learner_membership_id');
                $this->lockedLearnerMembership(
                    $organizationId,
                    $membershipId,
                    Membership::STATUS_ACTIVE,
                );

                if (
                    ! in_array($this->string($enrollment, 'status'), ['pending_curriculum', 'active'], true)
                    || $this->integer($enrollment, 'version') !== $expectedVersion
                    || $this->nullableString($enrollment, 'cohort_id') === $cohortId
                ) {
                    throw new LearnerAdministrationConflict;
                }

                $this->assertCohortVersion(
                    $organizationId,
                    $cohortId,
                    $expectedCohortVersion,
                );
                DB::table('enrollments')
                    ->where('organization_id', $organizationId)
                    ->where('id', $enrollmentId)
                    ->where('version', $expectedVersion)
                    ->update([
                        'cohort_id' => $cohortId,
                        'version' => $expectedVersion + 1,
                        'updated_at' => $now,
                    ]);
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    'learner_enrollment.cohort_changed',
                    [
                        'membership_id' => $membershipId,
                        'enrollment_id' => $enrollmentId,
                        ...($cohortId === null ? [] : ['cohort_id' => $cohortId]),
                    ],
                    $now,
                );
                $this->audit->recordTenant(
                    'learner_administration.enrollment.cohort_changed',
                    'enrollment',
                    $enrollmentId,
                );

                return [
                    'result_type' => 'learner_enrollment',
                    'result_id' => $enrollmentId,
                ];
            },
        );
    }

    public function activateEnrollment(
        User $actor,
        string $enrollmentId,
        string $publicationId,
        ?string $shareGrantId,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permissions: [LearnerAdministrationAuthorization::CURRICULUM_ASSIGN],
            operation: 'learner_enrollment.activate_curriculum',
            targetType: 'learner_enrollment',
            targetId: $enrollmentId,
            body: [
                'expected_version' => $expectedVersion,
                'publication_id' => $publicationId,
                'share_grant_id' => $shareGrantId,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use (
                $actor,
                $enrollmentId,
                $publicationId,
                $shareGrantId,
                $expectedVersion,
            ): array {
                $enrollment = $this->lockedEnrollment($organizationId, $enrollmentId);
                $membershipId = $this->string($enrollment, 'learner_membership_id');
                $this->lockedLearnerMembership(
                    $organizationId,
                    $membershipId,
                    Membership::STATUS_ACTIVE,
                );

                if (
                    $this->string($enrollment, 'status') !== 'pending_curriculum'
                    || $this->integer($enrollment, 'version') !== $expectedVersion
                ) {
                    throw new LearnerAdministrationConflict;
                }

                $this->curriculumPinner->activateForAdministration(
                    actor: $actor,
                    enrollmentId: $enrollmentId,
                    publicationId: $publicationId,
                    shareGrantId: $shareGrantId,
                    expectedVersion: $expectedVersion,
                );
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    'learner_enrollment.curriculum_activated',
                    [
                        'membership_id' => $membershipId,
                        'enrollment_id' => $enrollmentId,
                    ],
                    $now,
                );

                return [
                    'result_type' => 'learner_enrollment',
                    'result_id' => $enrollmentId,
                ];
            },
        );
    }

    public function withdrawEnrollment(
        User $actor,
        string $enrollmentId,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permissions: [LearnerAdministrationAuthorization::ENROLLMENTS_MANAGE],
            operation: 'learner_enrollment.withdraw',
            targetType: 'learner_enrollment',
            targetId: $enrollmentId,
            body: ['expected_version' => $expectedVersion],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($enrollmentId, $expectedVersion): array {
                $enrollment = $this->lockedEnrollment($organizationId, $enrollmentId);
                $membershipId = $this->string($enrollment, 'learner_membership_id');
                $this->lockedLearnerMembership($organizationId, $membershipId);

                if (
                    ! in_array($this->string($enrollment, 'status'), ['pending_curriculum', 'active'], true)
                    || $this->integer($enrollment, 'version') !== $expectedVersion
                ) {
                    throw new LearnerAdministrationConflict;
                }

                DB::table('enrollments')
                    ->where('organization_id', $organizationId)
                    ->where('id', $enrollmentId)
                    ->where('version', $expectedVersion)
                    ->update([
                        'status' => 'withdrawn',
                        'version' => $expectedVersion + 1,
                        'ended_at' => $now,
                        'updated_at' => $now,
                    ]);
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    'learner_enrollment.withdrawn',
                    [
                        'membership_id' => $membershipId,
                        'enrollment_id' => $enrollmentId,
                    ],
                    $now,
                );
                $this->audit->recordTenant(
                    'learner_administration.enrollment.withdrawn',
                    'enrollment',
                    $enrollmentId,
                );

                return [
                    'result_type' => 'learner_enrollment',
                    'result_id' => $enrollmentId,
                ];
            },
        );
    }

    public function suspendMembership(
        User $actor,
        string $membershipId,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->changeMembershipLifecycle(
            $actor,
            $membershipId,
            $expectedVersion,
            $idempotencyKey,
            true,
        );
    }

    public function reactivateMembership(
        User $actor,
        string $membershipId,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->changeMembershipLifecycle(
            $actor,
            $membershipId,
            $expectedVersion,
            $idempotencyKey,
            false,
        );
    }

    private function changeMembershipLifecycle(
        User $actor,
        string $membershipId,
        int $expectedVersion,
        string $idempotencyKey,
        bool $suspend,
    ): OrganizationAdministrationCommandResult {
        $operation = $suspend
            ? 'learner_membership.suspend'
            : 'learner_membership.reactivate';

        return $this->execute(
            actor: $actor,
            permissions: [LearnerAdministrationAuthorization::LIFECYCLE_MANAGE],
            operation: $operation,
            targetType: 'learner_membership',
            targetId: $membershipId,
            body: ['expected_version' => $expectedVersion],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($membershipId, $expectedVersion, $suspend): array {
                $membership = $this->lockedLearnerMembership(
                    $organizationId,
                    $membershipId,
                );
                $expectedStatus = $suspend
                    ? Membership::STATUS_ACTIVE
                    : Membership::STATUS_SUSPENDED;

                if (
                    $this->string($membership, 'status') !== $expectedStatus
                    || $this->integer($membership, 'version') !== $expectedVersion
                ) {
                    throw new LearnerAdministrationConflict;
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
                $safeRoleCount = 0;

                foreach ($roleIds as $roleId) {
                    if ($this->roleIsLearnerSafe($organizationId, $roleId)) {
                        $safeRoleCount++;

                        continue;
                    }

                    if (! $suspend) {
                        throw new LearnerAdministrationConflict(
                            LearnerAdministrationConflict::UNSAFE_ROLE,
                        );
                    }

                    DB::table('membership_roles')
                        ->where('organization_id', $organizationId)
                        ->where('membership_id', $membershipId)
                        ->where('role_id', $roleId)
                        ->delete();
                    $this->events->record(
                        $organizationId,
                        $actorMembershipId,
                        $commandId,
                        'learner_membership.role_removed',
                        [
                            'membership_id' => $membershipId,
                            'role_id' => $roleId,
                        ],
                        $now,
                        'unsafe_role_containment',
                    );
                }

                if (! $suspend && $safeRoleCount === 0) {
                    throw new LearnerAdministrationConflict(
                        LearnerAdministrationConflict::UNSAFE_ROLE,
                    );
                }

                $revoked = ['web' => 0, 'mobile' => 0];

                if ($suspend) {
                    $snapshot = $this->credentials->snapshot(
                        $organizationId,
                        $membershipId,
                        Membership::STATUS_ACTIVE,
                        $expectedVersion,
                        true,
                    );
                    $revoked = $this->credentials->revoke(
                        $snapshot['revocable_web'],
                        $snapshot['revocable_mobile'],
                        'learner_membership_suspended',
                        $now,
                    );
                }

                $newStatus = $suspend
                    ? Membership::STATUS_SUSPENDED
                    : Membership::STATUS_ACTIVE;
                DB::table('memberships')
                    ->where('organization_id', $organizationId)
                    ->where('id', $membershipId)
                    ->where('version', $expectedVersion)
                    ->update([
                        'status' => $newStatus,
                        'version' => $expectedVersion + 1,
                        'updated_at' => $now,
                    ]);
                $eventType = $suspend
                    ? 'learner_membership.suspended'
                    : 'learner_membership.reactivated';
                $this->events->record(
                    $organizationId,
                    $actorMembershipId,
                    $commandId,
                    $eventType,
                    ['membership_id' => $membershipId],
                    $now,
                );
                $this->audit->recordTenant(
                    'learner_administration.'.$eventType,
                    'membership',
                    $membershipId,
                );

                return [
                    'result_type' => 'learner',
                    'result_id' => $membershipId,
                    'revoked_web_session_count' => $revoked['web'],
                    'revoked_android_family_count' => $revoked['mobile'],
                ];
            },
        );
    }

    /**
     * @param  list<string>  $permissions
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
        array $permissions,
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
                $permissions,
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
                    throw new LearnerAdministrationRejected;
                }

                $actorMembership = $this->authorization->actorMembership($actor, true);
                $this->authorization->permissionRows($actor, true);
                $this->authorization->assertAll($actor, $permissions);

                if ($claim->replayed) {
                    if (
                        $claim->resultType === null
                        || $claim->resultId === null
                        || $claim->completedAt === null
                    ) {
                        throw new LearnerAdministrationConflict;
                    }

                    return new OrganizationAdministrationCommandResult(
                        resultType: $claim->resultType,
                        resultId: $claim->resultId,
                        replayed: true,
                        completedAt: $claim->completedAt,
                    );
                }

                $now = CarbonImmutable::now();
                $outcome = $command(
                    $context->organizationId,
                    $this->string($actorMembership, 'id'),
                    $claim->id,
                    $now,
                );
                $this->idempotency->complete(
                    $claim,
                    $outcome['result_type'],
                    $outcome['result_id'],
                    $now,
                );

                return new OrganizationAdministrationCommandResult(
                    resultType: $outcome['result_type'],
                    resultId: $outcome['result_id'],
                    replayed: false,
                    completedAt: $now,
                    activationCode: $outcome['activation_code'] ?? null,
                    revokedWebSessionCount: $outcome['revoked_web_session_count'] ?? null,
                    revokedAndroidFamilyCount: $outcome['revoked_android_family_count'] ?? null,
                );
            }, 3);
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), [
                '23000',
                '23503',
                '23505',
                '23514',
                '55000',
            ], true)) {
                throw new LearnerAdministrationConflict(previous: $exception);
            }

            throw $exception;
        }
    }

    private function organizationId(User $actor): string
    {
        return $this->authorization->contextFor($actor)->organizationId;
    }

    private function lockedLearnerMembership(
        string $organizationId,
        string $membershipId,
        ?string $status = null,
    ): stdClass {
        $query = DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->where('id', $membershipId)
            ->where('membership_type', Membership::TYPE_LEARNER);

        if ($status !== null) {
            $query->where('status', $status);
        }

        $membership = $query->lockForUpdate()->first([
            'id',
            'organization_id',
            'membership_type',
            'status',
            'version',
        ]);

        if (! $membership instanceof stdClass) {
            throw new LearnerAdministrationRejected;
        }

        return $membership;
    }

    private function lockedEnrollment(
        string $organizationId,
        string $enrollmentId,
    ): stdClass {
        $enrollment = DB::table('enrollments')
            ->where('organization_id', $organizationId)
            ->where('id', $enrollmentId)
            ->lockForUpdate()
            ->first();

        if (! $enrollment instanceof stdClass) {
            throw new LearnerAdministrationRejected;
        }

        return $enrollment;
    }

    private function assertLearnerSafeRole(
        string $organizationId,
        string $roleId,
        int $expectedVersion,
    ): void {
        $role = DB::table('roles')
            ->where('organization_id', $organizationId)
            ->where('id', $roleId)
            ->lockForUpdate()
            ->first(['id', 'status', 'version']);

        if (
            ! $role instanceof stdClass
            || $this->string($role, 'status') !== 'active'
            || $this->integer($role, 'version') !== $expectedVersion
            || ! $this->roleIsLearnerSafe($organizationId, $roleId)
        ) {
            throw new LearnerAdministrationConflict(
                LearnerAdministrationConflict::UNSAFE_ROLE,
            );
        }
    }

    private function roleIsLearnerSafe(
        string $organizationId,
        string $roleId,
    ): bool {
        $role = DB::table('roles')
            ->where('organization_id', $organizationId)
            ->where('id', $roleId)
            ->lockForUpdate()
            ->first(['id', 'status']);

        if (
            ! $role instanceof stdClass
            || $this->string($role, 'status') !== 'active'
        ) {
            return false;
        }

        $permissions = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.organization_id', $organizationId)
            ->where('role_permissions.role_id', $roleId)
            ->orderBy('permissions.key')
            ->lockForUpdate()
            ->pluck('permissions.key')
            ->filter(static fn (mixed $key): bool => is_string($key))
            ->values()
            ->all();

        return $permissions === ['learning.participate'];
    }

    private function assertCohortVersion(
        string $organizationId,
        ?string $cohortId,
        ?int $expectedVersion,
    ): void {
        if ($cohortId === null || $expectedVersion === null) {
            if ($cohortId !== null || $expectedVersion !== null) {
                throw new LearnerAdministrationConflict;
            }

            return;
        }

        $cohort = DB::table('cohorts')
            ->where('organization_id', $organizationId)
            ->where('id', $cohortId)
            ->lockForUpdate()
            ->first(['id', 'status', 'version']);

        if (
            ! $cohort instanceof stdClass
            || $this->string($cohort, 'status') !== 'active'
            || $this->integer($cohort, 'version') !== $expectedVersion
        ) {
            throw new LearnerAdministrationConflict;
        }
    }

    private function string(stdClass $row, string $property): string
    {
        return $this->nullableString($row, $property)
            ?? throw new LearnerAdministrationRejected;
    }

    private function nullableString(stdClass $row, string $property): ?string
    {
        $value = $row->{$property} ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function integer(stdClass $row, string $property): int
    {
        $value = $row->{$property} ?? null;

        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new LearnerAdministrationRejected;
        }

        return (int) $value;
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

        throw new LearnerAdministrationRejected;
    }
}

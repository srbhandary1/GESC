<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Mobile\MobileTokenFamilyService;
use App\Curriculum\CanonicalJson;
use App\Models\InstitutionInvite;
use App\Models\Membership;
use App\Models\MobileTokenFamily;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Operator\LearnerAdministration\Exceptions\LearnerAdministrationConflict;
use App\Operator\LearnerAdministration\Exceptions\LearnerAdministrationRejected;
use App\Operator\LearnerAdministration\LearnerAdministrationCommands;
use App\Operator\OrganizationAdministration\Exceptions\OrganizationAdministrationConflict;
use App\Operator\OrganizationAdministration\Exceptions\OrganizationAdministrationRejected;
use App\Operator\OrganizationAdministration\MembershipSelectedCredentialService;
use App\Operator\OrganizationAdministration\OrganizationAdministrationCommands;
use App\Tenancy\Exceptions\InstitutionInviteUnavailable;
use App\Tenancy\InstitutionInviteAcceptor;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextSource;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * @phpstan-type WorkerSpec array<string, string>
 * @phpstan-type Worker array{pid: int, pipe: resource, reaped: bool}
 * @phpstan-type WorkerResult array{operation: string, outcome: string}
 */
final class ConcurrentOrganizationAdministrationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FoundationSeeder::class);
    }

    protected function tearDown(): void
    {
        if (
            DB::connection()->getDriverName() === 'pgsql'
            && Schema::hasTable('roleplay_scenarios')
        ) {
            DB::statement(
                <<<'SQL'
                    TRUNCATE TABLE
                        roleplay_scenarios,
                        learning_commands,
                        review_schedules,
                        mastery_states,
                        attempt_submissions,
                        item_responses,
                        learner_attempts,
                        learner_goals,
                        learner_profiles,
                        enrollments
                    CASCADE
                    SQL,
            );
        }

        parent::tearDown();
    }

    public function test_concurrent_role_revocations_preserve_one_final_manager(): void
    {
        $organization = $this->createOrganization('Concurrent final manager');
        $firstActor = $this->createUser();
        $secondActor = $this->createUser();
        $firstMembershipId = $this->createMembership(
            $organization,
            $firstActor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $secondMembershipId = $this->createMembership(
            $organization,
            $secondActor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $firstRoleId = $this->grantPermissions(
            $organization,
            $firstMembershipId,
            ['memberships.manage', 'roles.manage'],
            'first-manager',
        );
        $secondRoleId = $this->grantPermissions(
            $organization,
            $secondMembershipId,
            ['memberships.manage', 'roles.manage'],
            'second-manager',
        );

        $results = $this->runRace(
            [
                'operation' => 'revoke-role',
                'organization_id' => (string) $organization->getKey(),
                'actor_user_id' => (string) $firstActor->getKey(),
                'actor_membership_id' => $firstMembershipId,
                'target_membership_id' => $secondMembershipId,
                'target_role_id' => $secondRoleId,
                'idempotency_key' => 'concurrent-final-manager-first',
            ],
            [
                'operation' => 'revoke-role',
                'organization_id' => (string) $organization->getKey(),
                'actor_user_id' => (string) $secondActor->getKey(),
                'actor_membership_id' => $secondMembershipId,
                'target_membership_id' => $firstMembershipId,
                'target_role_id' => $firstRoleId,
                'idempotency_key' => 'concurrent-final-manager-second',
            ],
            'organizations',
            (string) $organization->getKey(),
        );

        self::assertSame(
            ['completed', 'rejected'],
            $this->sortedOutcomes($results),
        );
        self::assertSame(
            1,
            DB::table('membership_roles')
                ->where('organization_id', $organization->getKey())
                ->whereIn('role_id', [$firstRoleId, $secondRoleId])
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('organization_administration_commands')
                ->where('organization_id', $organization->getKey())
                ->where('operation', 'membership.revoke_role')
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('organization_administration_events')
                ->where('organization_id', $organization->getKey())
                ->where('event_type', 'membership.role_revoked')
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('audit_events')
                ->where('organization_id', $organization->getKey())
                ->where(
                    'action',
                    'organization_administration.membership.role_revoked',
                )
                ->count(),
        );
    }

    public function test_concurrent_stale_branch_updates_have_exactly_one_winner(): void
    {
        $organization = $this->createOrganization('Concurrent branch version');
        $actor = $this->createUser();
        $membershipId = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantPermissions(
            $organization,
            $membershipId,
            ['branches.manage'],
            'branch-manager',
        );
        $branchId = $this->createBranch($organization, 'race-branch');

        $results = $this->runRace(
            [
                'operation' => 'update-branch',
                'organization_id' => (string) $organization->getKey(),
                'actor_user_id' => (string) $actor->getKey(),
                'actor_membership_id' => $membershipId,
                'target_branch_id' => $branchId,
                'name' => 'First concurrent name',
                'idempotency_key' => 'concurrent-branch-first',
            ],
            [
                'operation' => 'update-branch',
                'organization_id' => (string) $organization->getKey(),
                'actor_user_id' => (string) $actor->getKey(),
                'actor_membership_id' => $membershipId,
                'target_branch_id' => $branchId,
                'name' => 'Second concurrent name',
                'idempotency_key' => 'concurrent-branch-second',
            ],
            'organizations',
            (string) $organization->getKey(),
        );

        self::assertSame(
            ['completed', 'rejected'],
            $this->sortedOutcomes($results),
        );
        $branch = DB::table('branches')->where('id', $branchId)->sole();
        self::assertSame(2, (int) $branch->version);
        self::assertContains(
            $branch->name,
            ['First concurrent name', 'Second concurrent name'],
        );
        self::assertSame(
            1,
            DB::table('organization_administration_commands')
                ->where('organization_id', $organization->getKey())
                ->where('operation', 'branch.update')
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('organization_administration_events')
                ->where('organization_id', $organization->getKey())
                ->where('event_type', 'branch.updated')
                ->count(),
        );
    }

    public function test_invitation_acceptance_and_revocation_have_one_terminal_winner(): void
    {
        $organization = $this->createOrganization('Concurrent invitation');
        $actor = $this->createUser();
        $actorMembershipId = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantPermissions(
            $organization,
            $actorMembershipId,
            ['institution_invites.manage'],
            'invitation-manager',
        );
        $invitee = $this->createUser();
        $context = $this->context(
            $organization,
            $actor,
            $actorMembershipId,
        );
        $issued = $this->app
            ->make(OrganizationContextManager::class)
            ->run(
                $context,
                fn () => $this->app
                    ->make(OrganizationAdministrationCommands::class)
                    ->issueInvitation(
                        actor: $actor,
                        membershipType: Membership::TYPE_TRAINER,
                        expiresInHours: 24,
                        expectedVersion: 0,
                        idempotencyKey: 'concurrent-invitation-issue',
                    ),
            );
        self::assertNotNull($issued->activationCode);

        $results = $this->runRace(
            [
                'operation' => 'accept-invitation',
                'invitee_user_id' => (string) $invitee->getKey(),
                'raw_code' => $issued->activationCode,
                'idempotency_key' => 'concurrent-invitation-accept',
            ],
            [
                'operation' => 'revoke-invitation',
                'organization_id' => (string) $organization->getKey(),
                'actor_user_id' => (string) $actor->getKey(),
                'actor_membership_id' => $actorMembershipId,
                'target_invitation_id' => $issued->resultId,
                'idempotency_key' => 'concurrent-invitation-revoke',
            ],
            'institution_invites',
            $issued->resultId,
        );

        self::assertSame(
            ['completed', 'rejected'],
            $this->sortedOutcomes($results),
        );
        $invitation = DB::table('institution_invites')
            ->where('id', $issued->resultId)
            ->sole();
        self::assertContains(
            $invitation->status,
            [InstitutionInvite::STATUS_ACCEPTED, InstitutionInvite::STATUS_REVOKED],
        );
        self::assertSame(2, (int) $invitation->version);
        self::assertNotNull($invitation->code_discard_after);
        $acceptedMemberships = DB::table('memberships')
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $invitee->getKey())
            ->count();

        if ($invitation->status === InstitutionInvite::STATUS_ACCEPTED) {
            self::assertSame(1, $acceptedMemberships);
            self::assertSame(
                (string) $invitee->getKey(),
                $invitation->accepted_by_user_id,
            );
        } else {
            self::assertSame(0, $acceptedMemberships);
            self::assertNull($invitation->accepted_by_user_id);
        }
    }

    public function test_two_concurrent_current_enrollment_creates_have_one_winner(): void
    {
        $organization = $this->createOrganization('Concurrent learner enrollment');
        $actor = $this->createUser();
        $actorMembershipId = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantPermissions(
            $organization,
            $actorMembershipId,
            ['enrollments.manage'],
            'learner-enrollment-manager',
        );
        $learner = $this->createUser();
        $learnerMembershipId = $this->createMembership(
            $organization,
            $learner,
            Membership::TYPE_LEARNER,
        );

        $base = [
            'operation' => 'create-learner-enrollment',
            'organization_id' => (string) $organization->getKey(),
            'actor_user_id' => (string) $actor->getKey(),
            'actor_membership_id' => $actorMembershipId,
            'target_membership_id' => $learnerMembershipId,
        ];
        $results = $this->runRace(
            [...$base, 'idempotency_key' => 'learner-enrollment-first'],
            [...$base, 'idempotency_key' => 'learner-enrollment-second'],
            'organizations',
            (string) $organization->getKey(),
        );

        self::assertSame(['completed', 'rejected'], $this->sortedOutcomes($results));
        self::assertSame(1, DB::table('enrollments')
            ->where('organization_id', $organization->getKey())
            ->where('learner_membership_id', $learnerMembershipId)
            ->whereIn('status', ['pending_curriculum', 'active'])
            ->count());
        self::assertSame(1, DB::table('organization_administration_commands')
            ->where('organization_id', $organization->getKey())
            ->where('operation', 'learner_enrollment.create')
            ->count());
        self::assertSame(1, DB::table('organization_administration_events')
            ->where('organization_id', $organization->getKey())
            ->where('event_type', 'learner_enrollment.created')
            ->count());
    }

    public function test_concurrent_goal_corrections_advance_the_projection_once(): void
    {
        $organization = $this->createOrganization('Concurrent learner goal');
        $actor = $this->createUser();
        $actorMembershipId = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantPermissions(
            $organization,
            $actorMembershipId,
            ['learners.profile.correct'],
            'learner-goal-manager',
        );
        $learner = $this->createUser();
        $learnerMembershipId = $this->createMembership(
            $organization,
            $learner,
            Membership::TYPE_LEARNER,
        );
        $this->createLearnerProfile(
            $organization,
            $learnerMembershipId,
            'everyday-conversation',
        );
        $base = [
            'operation' => 'correct-learner-goal',
            'organization_id' => (string) $organization->getKey(),
            'actor_user_id' => (string) $actor->getKey(),
            'actor_membership_id' => $actorMembershipId,
            'target_membership_id' => $learnerMembershipId,
        ];

        $results = $this->runRace(
            [
                ...$base,
                'goal_key' => 'workplace-confidence',
                'idempotency_key' => 'learner-goal-first',
            ],
            [
                ...$base,
                'goal_key' => 'travel-confidence',
                'idempotency_key' => 'learner-goal-second',
            ],
            'organizations',
            (string) $organization->getKey(),
        );

        self::assertSame(['completed', 'rejected'], $this->sortedOutcomes($results));
        $current = DB::table('learner_profile_current_goals')
            ->where('organization_id', $organization->getKey())
            ->where('learner_membership_id', $learnerMembershipId)
            ->sole();
        self::assertSame(2, (int) $current->version);
        self::assertContains(
            $current->goal_key,
            ['workplace-confidence', 'travel-confidence'],
        );
        self::assertSame(1, DB::table('learner_goal_corrections')
            ->where('organization_id', $organization->getKey())
            ->where('learner_membership_id', $learnerMembershipId)
            ->count());
    }

    public function test_cohort_change_and_withdrawal_have_one_version_winner(): void
    {
        $organization = $this->createOrganization('Concurrent enrollment lifecycle');
        $actor = $this->createUser();
        $actorMembershipId = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantPermissions(
            $organization,
            $actorMembershipId,
            ['enrollments.manage'],
            'learner-lifecycle-manager',
        );
        $learner = $this->createUser();
        $learnerMembershipId = $this->createMembership(
            $organization,
            $learner,
            Membership::TYPE_LEARNER,
        );
        $originalCohortId = $this->createCohort($organization, 'original-race-cohort');
        $successorCohortId = $this->createCohort($organization, 'successor-race-cohort');
        $enrollmentId = $this->createPendingEnrollment(
            $organization,
            $learnerMembershipId,
            $originalCohortId,
        );
        $base = [
            'organization_id' => (string) $organization->getKey(),
            'actor_user_id' => (string) $actor->getKey(),
            'actor_membership_id' => $actorMembershipId,
            'target_enrollment_id' => $enrollmentId,
        ];

        $results = $this->runRace(
            [
                ...$base,
                'operation' => 'change-learner-enrollment-cohort',
                'target_cohort_id' => $successorCohortId,
                'idempotency_key' => 'learner-cohort-change',
            ],
            [
                ...$base,
                'operation' => 'withdraw-learner-enrollment',
                'idempotency_key' => 'learner-enrollment-withdraw',
            ],
            'organizations',
            (string) $organization->getKey(),
        );

        self::assertSame(['completed', 'rejected'], $this->sortedOutcomes($results));
        $enrollment = DB::table('enrollments')->where('id', $enrollmentId)->sole();
        self::assertSame(2, (int) $enrollment->version);

        if ($enrollment->status === 'withdrawn') {
            self::assertSame($originalCohortId, $enrollment->cohort_id);
            self::assertNotNull($enrollment->ended_at);
        } else {
            self::assertSame('pending_curriculum', $enrollment->status);
            self::assertSame($successorCohortId, $enrollment->cohort_id);
            self::assertNull($enrollment->ended_at);
        }
    }

    public function test_cohort_change_and_curriculum_activation_have_one_version_winner(): void
    {
        $organization = $this->createOrganization('Concurrent enrollment activation');
        $actor = $this->createUser();
        $actorMembershipId = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantPermissions(
            $organization,
            $actorMembershipId,
            ['enrollments.manage', 'enrollments.curriculum.assign'],
            'learner-activation-manager',
        );
        $learner = $this->createUser();
        $learnerMembershipId = $this->createMembership(
            $organization,
            $learner,
            Membership::TYPE_LEARNER,
        );
        $originalCohortId = $this->createCohort($organization, 'activation-original-cohort');
        $successorCohortId = $this->createCohort($organization, 'activation-successor-cohort');
        $enrollmentId = $this->createPendingEnrollment(
            $organization,
            $learnerMembershipId,
            $originalCohortId,
        );
        $publicationId = $this->createVerifiedEmptyPublication(
            $organization,
            $actor,
            $actorMembershipId,
        );
        $base = [
            'organization_id' => (string) $organization->getKey(),
            'actor_user_id' => (string) $actor->getKey(),
            'actor_membership_id' => $actorMembershipId,
            'target_enrollment_id' => $enrollmentId,
        ];

        $results = $this->runRace(
            [
                ...$base,
                'operation' => 'change-learner-enrollment-cohort',
                'target_cohort_id' => $successorCohortId,
                'idempotency_key' => 'race-activation-cohort-change',
            ],
            [
                ...$base,
                'operation' => 'activate-learner-enrollment',
                'target_publication_id' => $publicationId,
                'idempotency_key' => 'race-enrollment-activate',
            ],
            'organizations',
            (string) $organization->getKey(),
        );

        self::assertSame(['completed', 'rejected'], $this->sortedOutcomes($results));
        $enrollment = DB::table('enrollments')->where('id', $enrollmentId)->sole();
        self::assertSame(2, (int) $enrollment->version);

        if ($enrollment->status === 'active') {
            self::assertSame($originalCohortId, $enrollment->cohort_id);
            self::assertSame($publicationId, $enrollment->curriculum_publication_id);
            self::assertNotNull($enrollment->activated_at);
        } else {
            self::assertSame('pending_curriculum', $enrollment->status);
            self::assertSame($successorCohortId, $enrollment->cohort_id);
            self::assertNull($enrollment->curriculum_publication_id);
            self::assertNull($enrollment->activated_at);
        }
    }

    public function test_learner_invitation_acceptance_and_revocation_have_one_terminal_winner(): void
    {
        $organization = $this->createOrganization('Concurrent learner invitation');
        $actor = $this->createUser();
        $actorMembershipId = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantPermissions(
            $organization,
            $actorMembershipId,
            [
                'learners.lifecycle.manage',
                'institution_invites.manage',
                'roles.manage',
                'cohorts.read',
            ],
            'learner-invitation-manager',
        );
        $learnerRoleId = $this->grantPermissions(
            $organization,
            $actorMembershipId,
            ['learning.participate'],
            'learner-safe-race-role',
        );
        $invitee = $this->createUser();
        $issued = $this->app
            ->make(OrganizationContextManager::class)
            ->run(
                $this->context($organization, $actor, $actorMembershipId),
                fn () => $this->app->make(LearnerAdministrationCommands::class)
                    ->issueInvitation(
                        actor: $actor,
                        roleId: $learnerRoleId,
                        expectedRoleVersion: 1,
                        cohortId: null,
                        expectedCohortVersion: null,
                        expiresInHours: 24,
                        expectedVersion: 0,
                        idempotencyKey: 'concurrent-learner-invitation-issue',
                    ),
            );
        self::assertNotNull($issued->activationCode);

        $results = $this->runRace(
            [
                'operation' => 'accept-invitation',
                'invitee_user_id' => (string) $invitee->getKey(),
                'raw_code' => $issued->activationCode,
                'idempotency_key' => 'concurrent-learner-invitation-accept',
            ],
            [
                'operation' => 'revoke-learner-invitation',
                'organization_id' => (string) $organization->getKey(),
                'actor_user_id' => (string) $actor->getKey(),
                'actor_membership_id' => $actorMembershipId,
                'target_invitation_id' => $issued->resultId,
                'idempotency_key' => 'concurrent-learner-invitation-revoke',
            ],
            'institution_invites',
            $issued->resultId,
        );

        self::assertSame(['completed', 'rejected'], $this->sortedOutcomes($results));
        $invitation = DB::table('institution_invites')->where('id', $issued->resultId)->sole();
        self::assertContains(
            $invitation->status,
            [InstitutionInvite::STATUS_ACCEPTED, InstitutionInvite::STATUS_REVOKED],
        );
        self::assertSame(2, (int) $invitation->version);
        $accepted = DB::table('memberships')
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $invitee->getKey())
            ->where('membership_type', Membership::TYPE_LEARNER)
            ->count();
        self::assertSame(
            $invitation->status === InstitutionInvite::STATUS_ACCEPTED ? 1 : 0,
            $accepted,
        );
    }

    public function test_membership_selection_is_safe_against_concurrent_learner_suspension(): void
    {
        $primaryOrganization = $this->createOrganization('Learner credential primary');
        $otherOrganization = $this->createOrganization('Learner credential other');
        $actor = $this->createUser();
        $actorMembershipId = $this->createMembership(
            $primaryOrganization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantPermissions(
            $primaryOrganization,
            $actorMembershipId,
            ['learners.lifecycle.manage'],
            'learner-suspension-manager',
        );
        $target = $this->createUser();
        $primaryMembershipId = $this->createMembership(
            $primaryOrganization,
            $target,
            Membership::TYPE_LEARNER,
        );
        $otherMembershipId = $this->createMembership(
            $otherOrganization,
            $target,
            Membership::TYPE_LEARNER,
        );
        $this->grantPermissions(
            $primaryOrganization,
            $primaryMembershipId,
            ['learning.participate'],
            'primary-learner-safe-role',
        );
        $family = $this->createSelectedMobileFamily(
            $target,
            $primaryOrganization,
            $primaryMembershipId,
        );

        $results = $this->runRace(
            [
                'operation' => 'select-mobile-membership',
                'family_id' => (string) $family->getKey(),
                'target_user_id' => (string) $target->getKey(),
                'target_membership_id' => $otherMembershipId,
            ],
            [
                'operation' => 'suspend-learner',
                'organization_id' => (string) $primaryOrganization->getKey(),
                'actor_user_id' => (string) $actor->getKey(),
                'actor_membership_id' => $actorMembershipId,
                'target_membership_id' => $primaryMembershipId,
                'idempotency_key' => 'concurrent-learner-suspension',
            ],
            'mobile_token_families',
            (string) $family->getKey(),
        );

        $suspension = collect($results)->firstWhere('operation', 'suspend-learner');
        self::assertIsArray($suspension);
        self::assertSame('completed', $suspension['outcome']);
        $this->assertDatabaseHas('memberships', [
            'id' => $primaryMembershipId,
            'status' => Membership::STATUS_SUSPENDED,
            'version' => 2,
        ]);
        $freshFamily = DB::table('mobile_token_families')
            ->where('id', $family->getKey())
            ->sole();
        self::assertTrue(
            $freshFamily->revoked_at !== null
            || $freshFamily->selected_membership_id === $otherMembershipId,
            'A live credential must not retain the concurrently suspended learner membership.',
        );
    }

    #[DataProvider('selectedCredentialRaceOperations')]
    public function test_membership_selection_is_safe_against_concurrent_admin_commands(
        string $adminOperation,
    ): void {
        $primaryOrganization = $this->createOrganization(
            'Concurrent selected credential primary',
        );
        $otherOrganization = $this->createOrganization(
            'Concurrent selected credential other',
        );
        $actor = $this->createUser();
        $actorMembershipId = $this->createMembership(
            $primaryOrganization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantPermissions(
            $primaryOrganization,
            $actorMembershipId,
            [
                'membership_sessions.revoke',
                'memberships.manage',
                'roles.manage',
            ],
            'credential-manager',
        );
        $target = $this->createUser();
        $primaryMembershipId = $this->createMembership(
            $primaryOrganization,
            $target,
            Membership::TYPE_TRAINER,
        );
        $otherMembershipId = $this->createMembership(
            $otherOrganization,
            $target,
            Membership::TYPE_TRAINER,
        );
        $family = $this->createSelectedMobileFamily(
            $target,
            $primaryOrganization,
            $primaryMembershipId,
        );
        $snapshot = $this->app
            ->make(MembershipSelectedCredentialService::class)
            ->snapshot(
                (string) $primaryOrganization->getKey(),
                $primaryMembershipId,
                Membership::STATUS_ACTIVE,
                1,
            );
        $adminSpec = [
            'operation' => $adminOperation,
            'organization_id' => (string) $primaryOrganization->getKey(),
            'actor_user_id' => (string) $actor->getKey(),
            'actor_membership_id' => $actorMembershipId,
            'target_membership_id' => $primaryMembershipId,
            'state_fingerprint' => $snapshot['fingerprint'],
            'idempotency_key' => $adminOperation === 'deactivate-membership'
                ? 'selected-race-deactivate'
                : 'selected-race-revoke',
        ];

        $results = $this->runRace(
            [
                'operation' => 'select-mobile-membership',
                'family_id' => (string) $family->getKey(),
                'target_user_id' => (string) $target->getKey(),
                'target_membership_id' => $otherMembershipId,
            ],
            $adminSpec,
            'mobile_token_families',
            (string) $family->getKey(),
        );

        $adminResult = collect($results)
            ->firstWhere('operation', $adminOperation);
        self::assertIsArray($adminResult);

        if ($adminOperation === 'deactivate-membership') {
            self::assertSame('completed', $adminResult['outcome']);
            $this->assertDatabaseHas('memberships', [
                'id' => $primaryMembershipId,
                'status' => Membership::STATUS_SUSPENDED,
                'version' => 2,
            ]);
        } else {
            self::assertContains($adminResult['outcome'], ['completed', 'rejected']);
            $this->assertDatabaseHas('memberships', [
                'id' => $primaryMembershipId,
                'status' => Membership::STATUS_ACTIVE,
                'version' => 1,
            ]);
        }

        $freshFamily = DB::table('mobile_token_families')
            ->where('id', $family->getKey())
            ->sole();
        self::assertTrue(
            $freshFamily->revoked_at !== null
            || $freshFamily->selected_membership_id === $otherMembershipId,
            'A live credential must not retain the membership targeted by the concurrent command.',
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function selectedCredentialRaceOperations(): array
    {
        return [
            'session revocation' => ['revoke-membership-sessions'],
            'membership deactivation' => ['deactivate-membership'],
        ];
    }

    private function createOrganization(string $name): Organization
    {
        return Organization::query()->create([
            'system_key' => null,
            'name' => $name,
            'kind' => Organization::KIND_INSTITUTION,
            'status' => Organization::STATUS_ACTIVE,
            'timezone' => 'Asia/Kolkata',
        ]);
    }

    private function createUser(): User
    {
        return User::query()->forceCreate([
            'display_name' => null,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function createMembership(
        Organization $organization,
        User $user,
        string $membershipType,
    ): string {
        $membershipId = (string) Str::ulid();
        DB::table('memberships')->insert([
            'id' => $membershipId,
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'membership_type' => $membershipType,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
            'ended_at' => null,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $membershipId;
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function grantPermissions(
        Organization $organization,
        string $membershipId,
        array $permissionKeys,
        string $key,
    ): string {
        $roleId = (string) Str::ulid();
        $now = now();
        DB::table('roles')->insert([
            'id' => $roleId,
            'organization_id' => $organization->getKey(),
            'key' => $key,
            'name' => Str::headline($key),
            'is_system' => false,
            'status' => 'active',
            'version' => 1,
            'retired_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('membership_roles')->insert([
            'organization_id' => $organization->getKey(),
            'membership_id' => $membershipId,
            'role_id' => $roleId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($permissionKeys as $permissionKey) {
            $permissionId = Permission::query()
                ->where('key', $permissionKey)
                ->value('id');

            if (! is_string($permissionId)) {
                throw new RuntimeException(
                    sprintf('Required permission %s is not seeded.', $permissionKey),
                );
            }

            DB::table('role_permissions')->insert([
                'organization_id' => $organization->getKey(),
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $roleId;
    }

    private function createBranch(
        Organization $organization,
        string $key,
    ): string {
        $branchId = (string) Str::ulid();
        DB::table('branches')->insert([
            'id' => $branchId,
            'organization_id' => $organization->getKey(),
            'key' => $key,
            'name' => 'Original branch name',
            'status' => 'active',
            'version' => 1,
            'archived_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $branchId;
    }

    private function createLearnerProfile(
        Organization $organization,
        string $membershipId,
        string $goalKey,
    ): void {
        $profileId = (string) Str::ulid();
        $goalId = (string) Str::ulid();
        $now = now();
        DB::table('learner_profiles')->insert([
            'id' => $profileId,
            'organization_id' => $organization->getKey(),
            'learner_membership_id' => $membershipId,
            'adult_confirmed_at' => $now,
            'onboarding_completed_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('learner_goals')->insert([
            'id' => $goalId,
            'organization_id' => $organization->getKey(),
            'learner_profile_id' => $profileId,
            'learner_membership_id' => $membershipId,
            'goal_key' => $goalKey,
            'selected_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('learner_profile_current_goals')->insert([
            'id' => $goalId,
            'organization_id' => $organization->getKey(),
            'learner_profile_id' => $profileId,
            'learner_membership_id' => $membershipId,
            'goal_key' => $goalKey,
            'version' => 1,
            'corrected_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function createCohort(
        Organization $organization,
        string $key,
    ): string {
        $cohortId = (string) Str::ulid();
        $now = now();
        DB::table('cohorts')->insert([
            'id' => $cohortId,
            'organization_id' => $organization->getKey(),
            'branch_id' => null,
            'key' => $key,
            'name' => Str::headline($key),
            'status' => 'active',
            'version' => 1,
            'archived_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $cohortId;
    }

    private function createPendingEnrollment(
        Organization $organization,
        string $membershipId,
        ?string $cohortId,
    ): string {
        $enrollmentId = (string) Str::ulid();
        $now = now();
        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'organization_id' => $organization->getKey(),
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

        return $enrollmentId;
    }

    private function createVerifiedEmptyPublication(
        Organization $organization,
        User $reviewer,
        string $membershipId,
    ): string {
        $organizationId = (string) $organization->getKey();
        $now = now()->subMinute();
        $programId = (string) Str::ulid();
        $versionId = (string) Str::ulid();
        $graphRevisionId = (string) Str::ulid();
        $appointmentId = (string) Str::ulid();
        $acceptanceId = (string) Str::ulid();
        $reviewId = (string) Str::ulid();
        $publicationId = (string) Str::ulid();
        $activationEventId = (string) Str::ulid();
        $graph = [
            'objectives' => [],
            'objective_prerequisites' => [],
            'modules' => [],
            'lessons' => [],
            'lesson_objectives' => [],
            'learning_items' => [],
            'missions' => [],
            'mission_items' => [],
        ];
        $graphHash = CanonicalJson::sha256($graph);

        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organizationId,
            'key' => 'verified-empty-race',
            'internal_name' => 'Verified Empty Race Publication',
            'created_by_membership_id' => $membershipId,
            'created_by_attribution' => 'synthetic_concurrency_test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_versions')->insert([
            'id' => $versionId,
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'key' => 'release-1',
            'created_by_membership_id' => $membershipId,
            'created_by_attribution' => 'synthetic_concurrency_test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_graph_revisions')->insert([
            'id' => $graphRevisionId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'parent_revision_id' => null,
            'parent_revision_number' => null,
            'revision_number' => 1,
            'graph' => CanonicalJson::encode($graph),
            'graph_hash' => $graphHash,
            'authored_by_membership_id' => $membershipId,
            'author_attribution' => 'synthetic_concurrency_test',
            'authored_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('authority_appointments')->insert([
            'id' => $appointmentId,
            'organization_id' => $organizationId,
            'appointee_user_id' => $reviewer->getKey(),
            'appointee_membership_id' => $membershipId,
            'authority_kind' => 'english_academic_reviewer',
            'scope_type' => 'organization',
            'program_id' => null,
            'valid_from' => $now,
            'valid_until' => null,
            'qualification_basis' => 'Synthetic concurrency verification fixture.',
            'approval_evidence_key' => 'synthetic_concurrency_test',
            'appointed_by_membership_id' => $membershipId,
            'appointed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('authority_appointment_events')->insert([
            'id' => $acceptanceId,
            'organization_id' => $organizationId,
            'authority_appointment_id' => $appointmentId,
            'event_type' => 'accepted',
            'actor_membership_id' => $membershipId,
            'reason_key' => 'synthetic_concurrency_test',
            'invalidates_prior_approvals' => false,
            'effective_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_graph_reviews')->insert([
            'id' => $reviewId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $graphHash,
            'authority_kind' => 'english_academic_reviewer',
            'authority_appointment_id' => $appointmentId,
            'authority_appointment_event_id' => $acceptanceId,
            'authority_appointment_event_type' => 'accepted',
            'reviewer_membership_id' => $membershipId,
            'decision' => 'approved',
            'rationale_key' => 'synthetic_concurrency_test',
            'reviewed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $manifest = [
            'graph_revision' => [
                'id' => $graphRevisionId,
                'revision_number' => 1,
                'graph_hash' => $graphHash,
                'author_membership_id' => $membershipId,
                'graph' => $graph,
                'review' => [
                    'id' => $reviewId,
                    'decision' => 'approved',
                    'appointment_id' => $appointmentId,
                    'appointment_event_id' => $acceptanceId,
                    'reviewer_membership_id' => $membershipId,
                    'rationale_key' => 'synthetic_concurrency_test',
                ],
            ],
            'content' => [],
        ];
        $manifestJson = CanonicalJson::encode($manifest);
        DB::table('curriculum_publications')->insert([
            'id' => $publicationId,
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'curriculum_version_id' => $versionId,
            'publication_number' => 1,
            'manifest_schema_version' => 1,
            'manifest' => $manifestJson,
            'manifest_hash' => hash('sha256', $manifestJson),
            'created_by_membership_id' => $membershipId,
            'created_at' => $now,
        ]);
        DB::table('publication_graph_components')->insert([
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'curriculum_version_id' => $versionId,
            'curriculum_publication_id' => $publicationId,
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $graphHash,
            'curriculum_graph_review_id' => $reviewId,
            'graph_review_decision' => 'approved',
            'academic_appointment_id' => $appointmentId,
            'academic_appointment_event_id' => $acceptanceId,
            'reviewer_membership_id' => $membershipId,
            'created_at' => $now,
        ]);
        DB::table('publication_events')->insert([
            'id' => $activationEventId,
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'curriculum_version_id' => $versionId,
            'curriculum_publication_id' => $publicationId,
            'event_type' => 'activated',
            'previous_curriculum_version_id' => null,
            'previous_publication_id' => null,
            'actor_type' => 'owner_membership',
            'actor_membership_id' => $membershipId,
            'platform_actor_user_id' => null,
            'authentication_session_id' => null,
            'platform_audit_event_id' => null,
            'reason_key' => null,
            'remediation_reference' => null,
            'correlation_id' => (string) Str::uuid(),
            'effective_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('publication_heads')->insert([
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'active_curriculum_version_id' => $versionId,
            'active_publication_id' => $publicationId,
            'last_event_id' => $activationEventId,
            'is_suspended' => false,
            'lock_version' => 1,
            'updated_at' => $now,
        ]);

        return $publicationId;
    }

    private function createSelectedMobileFamily(
        User $user,
        Organization $organization,
        string $membershipId,
    ): MobileTokenFamily {
        $client = Client::query()->create([
            'name' => 'Concurrent ADMIN-ORG mobile client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => ['com.globalenglish.app:/oauth/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ]);

        /** @var MobileTokenFamily */
        return MobileTokenFamily::query()->forceCreate([
            'user_id' => $user->getKey(),
            'client_id' => $client->getKey(),
            'selected_organization_id' => $organization->getKey(),
            'selected_membership_id' => $membershipId,
            'installation_digest' => str_repeat('a', 64),
            'last_used_at' => now(),
            'absolute_expires_at' => now()->addDay(),
            'revoked_at' => null,
            'revocation_reason' => null,
        ]);
    }

    private function context(
        Organization $organization,
        User $actor,
        string $membershipId,
    ): OrganizationContext {
        return new OrganizationContext(
            organizationId: (string) $organization->getKey(),
            actorUserId: (string) $actor->getKey(),
            membershipId: $membershipId,
            source: OrganizationContextSource::Membership,
        );
    }

    /**
     * @param  WorkerSpec  $firstSpec
     * @param  WorkerSpec  $secondSpec
     * @return list<WorkerResult>
     */
    private function runRace(
        array $firstSpec,
        array $secondSpec,
        string $lockTable,
        string $lockId,
    ): array {
        self::assertSame('pgsql', DB::connection()->getDriverName());
        self::assertTrue(
            function_exists('pcntl_fork'),
            'The ADMIN-ORG concurrency gate requires the pcntl extension.',
        );
        $databaseConfig = DB::connection()->getConfig();
        DB::disconnect();

        /** @var list<Worker> $workers */
        $workers = [];
        /** @var list<int> $backendPids */
        $backendPids = [];
        /** @var list<WorkerResult> $results */
        $results = [];
        $lockConnection = null;
        $bothWorkersBlocked = false;

        try {
            $workers[] = $this->spawnWorker($firstSpec);
            $workers[] = $this->spawnWorker($secondSpec);

            foreach ($workers as $worker) {
                stream_set_timeout($worker['pipe'], 20);
                $ready = fgets($worker['pipe']);

                if (
                    ! is_string($ready)
                    || preg_match('/^ready:(\d+)\n$/', $ready, $matches) !== 1
                ) {
                    throw new RuntimeException(
                        'A concurrent ADMIN-ORG worker did not become ready.',
                    );
                }

                $backendPids[] = (int) $matches[1];
            }

            $lockConnection = $this->openPostgreSqlConnection($databaseConfig);
            $lockConnection->beginTransaction();
            $lockSql = match ($lockTable) {
                'organizations' => 'select id from organizations where id = :id for update',
                'institution_invites' => 'select id from institution_invites where id = :id for update',
                'mobile_token_families' => 'select id from mobile_token_families where id = :id for update',
                default => throw new RuntimeException('Unsupported race lock table.'),
            };
            $lock = $lockConnection->prepare($lockSql);
            $lock->execute(['id' => $lockId]);

            if ($lock->fetchColumn() === false) {
                throw new RuntimeException('The ADMIN-ORG race lock target is missing.');
            }

            foreach ($workers as $worker) {
                if (fwrite($worker['pipe'], "go\n") === false) {
                    throw new RuntimeException(
                        'A concurrent ADMIN-ORG worker could not be released.',
                    );
                }

                fflush($worker['pipe']);
            }

            $bothWorkersBlocked = $this->waitUntilWorkersBlock(
                $lockConnection,
                $backendPids,
            );
            $lockConnection->commit();

            foreach ($workers as $index => $worker) {
                $line = fgets($worker['pipe']);

                if (! is_string($line)) {
                    throw new RuntimeException(
                        'A concurrent ADMIN-ORG worker returned no result.',
                    );
                }

                /** @var array{operation?: mixed, outcome?: mixed, worker_exception?: mixed} $decoded */
                $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                $operation = $decoded['operation'] ?? null;
                $outcome = $decoded['outcome'] ?? null;

                if (! is_string($operation) || ! is_string($outcome)) {
                    throw new RuntimeException(
                        'A concurrent ADMIN-ORG worker returned an invalid result.',
                    );
                }

                if ($outcome === 'error') {
                    $exception = $decoded['worker_exception'] ?? 'unknown';
                    throw new RuntimeException(sprintf(
                        'A concurrent ADMIN-ORG worker failed with %s.',
                        is_string($exception) ? $exception : 'unknown',
                    ));
                }

                $results[] = [
                    'operation' => $operation,
                    'outcome' => $outcome,
                ];
                $waited = pcntl_waitpid($worker['pid'], $processStatus);

                if (
                    $waited !== $worker['pid']
                    || pcntl_wexitstatus($processStatus) !== 0
                ) {
                    throw new RuntimeException(
                        'A concurrent ADMIN-ORG worker exited unsuccessfully.',
                    );
                }

                $workers[$index]['reaped'] = true;
            }
        } finally {
            if (
                $lockConnection instanceof PDO
                && $lockConnection->inTransaction()
            ) {
                $lockConnection->rollBack();
            }

            foreach ($workers as $index => $worker) {
                if (is_resource($worker['pipe'])) {
                    fclose($worker['pipe']);
                }

                if ($worker['reaped']) {
                    continue;
                }

                $waited = pcntl_waitpid(
                    $worker['pid'],
                    $processStatus,
                    WNOHANG,
                );

                if ($waited === 0 && function_exists('posix_kill')) {
                    posix_kill($worker['pid'], SIGKILL);
                    pcntl_waitpid($worker['pid'], $processStatus);
                }

                $workers[$index]['reaped'] = true;
            }

            DB::purge();
            DB::reconnect();
        }

        self::assertTrue(
            $bothWorkersBlocked,
            sprintf(
                'Both ADMIN-ORG workers must enter the PostgreSQL lock queue; results: %s.',
                json_encode($results, JSON_THROW_ON_ERROR),
            ),
        );

        return $results;
    }

    /**
     * @param  WorkerSpec  $spec
     * @return Worker
     */
    private function spawnWorker(array $spec): array
    {
        $sockets = stream_socket_pair(
            STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP,
        );

        if ($sockets === false) {
            throw new RuntimeException(
                'The concurrent ADMIN-ORG worker pipe could not be created.',
            );
        }

        $pid = pcntl_fork();

        if ($pid === -1) {
            fclose($sockets[0]);
            fclose($sockets[1]);

            throw new RuntimeException(
                'The concurrent ADMIN-ORG worker could not be forked.',
            );
        }

        if ($pid === 0) {
            fclose($sockets[0]);
            $this->runWorker($sockets[1], $spec);
        }

        fclose($sockets[1]);

        return [
            'pid' => $pid,
            'pipe' => $sockets[0],
            'reaped' => false,
        ];
    }

    /**
     * @param  resource  $pipe
     * @param  WorkerSpec  $spec
     */
    private function runWorker(mixed $pipe, array $spec): never
    {
        $operation = $this->specValue($spec, 'operation');

        try {
            DB::purge();
            DB::reconnect();
            $backend = DB::selectOne('select pg_backend_pid() as pid');
            $backendPid = $backend->pid ?? null;

            if (! is_int($backendPid) && ! is_string($backendPid)) {
                throw new RuntimeException(
                    'The concurrent ADMIN-ORG backend PID is unavailable.',
                );
            }

            fwrite($pipe, sprintf("ready:%d\n", (int) $backendPid));
            fflush($pipe);

            if (fgets($pipe) !== "go\n") {
                throw new RuntimeException(
                    'The concurrent ADMIN-ORG worker received an invalid release signal.',
                );
            }

            $completed = $this->performWorkerOperation($spec);
            $payload = [
                'operation' => $operation,
                'outcome' => $completed ? 'completed' : 'rejected',
            ];
        } catch (
            InstitutionInviteUnavailable
            |LearnerAdministrationConflict
            |LearnerAdministrationRejected
            |OrganizationAdministrationConflict
            |OrganizationAdministrationRejected
        ) {
            $payload = [
                'operation' => $operation,
                'outcome' => 'rejected',
            ];
        } catch (Throwable $exception) {
            $this->writeWorkerResult($pipe, [
                'operation' => $operation,
                'outcome' => 'error',
                'worker_exception' => $exception::class,
            ]);

            exit(1);
        }

        $this->writeWorkerResult($pipe, $payload);

        exit(0);
    }

    /**
     * @param  WorkerSpec  $spec
     */
    private function performWorkerOperation(array $spec): bool
    {
        $operation = $this->specValue($spec, 'operation');

        if ($operation === 'accept-invitation') {
            $invitee = User::query()->findOrFail(
                $this->specValue($spec, 'invitee_user_id'),
            );
            $this->app->make(InstitutionInviteAcceptor::class)->accept(
                rawCode: $this->specValue($spec, 'raw_code'),
                user: $invitee,
                idempotencyKey: $this->specValue($spec, 'idempotency_key'),
            );

            return true;
        }

        if ($operation === 'select-mobile-membership') {
            return $this->app
                ->make(MobileTokenFamilyService::class)
                ->selectMembership(
                    $this->specValue($spec, 'family_id'),
                    $this->specValue($spec, 'target_user_id'),
                    $this->specValue($spec, 'target_membership_id'),
                ) instanceof MobileTokenFamily;
        }

        $actor = User::query()->findOrFail(
            $this->specValue($spec, 'actor_user_id'),
        );
        $context = new OrganizationContext(
            organizationId: $this->specValue($spec, 'organization_id'),
            actorUserId: (string) $actor->getKey(),
            membershipId: $this->specValue($spec, 'actor_membership_id'),
            source: OrganizationContextSource::Membership,
        );

        return $this->app
            ->make(OrganizationContextManager::class)
            ->run($context, function () use ($actor, $operation, $spec): bool {
                $commands = $this->app->make(
                    OrganizationAdministrationCommands::class,
                );
                $learnerCommands = $this->app->make(
                    LearnerAdministrationCommands::class,
                );

                match ($operation) {
                    'revoke-role' => $commands->revokeMembershipRole(
                        actor: $actor,
                        membershipId: $this->specValue(
                            $spec,
                            'target_membership_id',
                        ),
                        roleId: $this->specValue($spec, 'target_role_id'),
                        expectedMembershipVersion: 1,
                        expectedRoleVersion: 1,
                        idempotencyKey: $this->specValue(
                            $spec,
                            'idempotency_key',
                        ),
                    ),
                    'update-branch' => $commands->updateBranch(
                        actor: $actor,
                        branchId: $this->specValue($spec, 'target_branch_id'),
                        name: $this->specValue($spec, 'name'),
                        expectedVersion: 1,
                        idempotencyKey: $this->specValue(
                            $spec,
                            'idempotency_key',
                        ),
                    ),
                    'revoke-invitation' => $commands->revokeInvitation(
                        actor: $actor,
                        invitationId: $this->specValue(
                            $spec,
                            'target_invitation_id',
                        ),
                        expectedVersion: 1,
                        idempotencyKey: $this->specValue(
                            $spec,
                            'idempotency_key',
                        ),
                    ),
                    'revoke-membership-sessions' => $commands->revokeMembershipSessions(
                        actor: $actor,
                        membershipId: $this->specValue(
                            $spec,
                            'target_membership_id',
                        ),
                        stateFingerprint: $this->specValue(
                            $spec,
                            'state_fingerprint',
                        ),
                        idempotencyKey: $this->specValue(
                            $spec,
                            'idempotency_key',
                        ),
                    ),
                    'deactivate-membership' => $commands->deactivateMembership(
                        actor: $actor,
                        membershipId: $this->specValue(
                            $spec,
                            'target_membership_id',
                        ),
                        expectedVersion: 1,
                        idempotencyKey: $this->specValue(
                            $spec,
                            'idempotency_key',
                        ),
                    ),
                    'create-learner-enrollment' => $learnerCommands->createEnrollment(
                        actor: $actor,
                        membershipId: $this->specValue(
                            $spec,
                            'target_membership_id',
                        ),
                        expectedMembershipVersion: 1,
                        cohortId: null,
                        expectedCohortVersion: null,
                        expectedVersion: 0,
                        idempotencyKey: $this->specValue(
                            $spec,
                            'idempotency_key',
                        ),
                    ),
                    'correct-learner-goal' => $learnerCommands->correctGoal(
                        actor: $actor,
                        membershipId: $this->specValue(
                            $spec,
                            'target_membership_id',
                        ),
                        goalKey: $this->specValue($spec, 'goal_key'),
                        expectedVersion: 1,
                        idempotencyKey: $this->specValue(
                            $spec,
                            'idempotency_key',
                        ),
                    ),
                    'change-learner-enrollment-cohort' => $learnerCommands->changeEnrollmentCohort(
                        actor: $actor,
                        enrollmentId: $this->specValue(
                            $spec,
                            'target_enrollment_id',
                        ),
                        cohortId: $this->specValue(
                            $spec,
                            'target_cohort_id',
                        ),
                        expectedCohortVersion: 1,
                        expectedVersion: 1,
                        idempotencyKey: $this->specValue(
                            $spec,
                            'idempotency_key',
                        ),
                    ),
                    'activate-learner-enrollment' => $learnerCommands->activateEnrollment(
                        actor: $actor,
                        enrollmentId: $this->specValue(
                            $spec,
                            'target_enrollment_id',
                        ),
                        publicationId: $this->specValue(
                            $spec,
                            'target_publication_id',
                        ),
                        shareGrantId: null,
                        expectedVersion: 1,
                        idempotencyKey: $this->specValue(
                            $spec,
                            'idempotency_key',
                        ),
                    ),
                    'withdraw-learner-enrollment' => $learnerCommands->withdrawEnrollment(
                        actor: $actor,
                        enrollmentId: $this->specValue(
                            $spec,
                            'target_enrollment_id',
                        ),
                        expectedVersion: 1,
                        idempotencyKey: $this->specValue(
                            $spec,
                            'idempotency_key',
                        ),
                    ),
                    'revoke-learner-invitation' => $learnerCommands->revokeInvitation(
                        actor: $actor,
                        invitationId: $this->specValue(
                            $spec,
                            'target_invitation_id',
                        ),
                        expectedVersion: 1,
                        idempotencyKey: $this->specValue(
                            $spec,
                            'idempotency_key',
                        ),
                    ),
                    'suspend-learner' => $learnerCommands->suspendMembership(
                        actor: $actor,
                        membershipId: $this->specValue(
                            $spec,
                            'target_membership_id',
                        ),
                        expectedVersion: 1,
                        idempotencyKey: $this->specValue(
                            $spec,
                            'idempotency_key',
                        ),
                    ),
                    default => throw new RuntimeException(
                        'Unsupported concurrent ADMIN-ORG operation.',
                    ),
                };

                return true;
            });
    }

    /**
     * @param  WorkerSpec  $spec
     */
    private function specValue(array $spec, string $key): string
    {
        $value = $spec[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new RuntimeException(sprintf(
                'The concurrent ADMIN-ORG worker is missing %s.',
                $key,
            ));
        }

        return $value;
    }

    /**
     * @param  resource  $pipe
     * @param  array<string, string>  $payload
     */
    private function writeWorkerResult(mixed $pipe, array $payload): void
    {
        if (! is_resource($pipe)) {
            return;
        }

        fwrite($pipe, json_encode($payload, JSON_THROW_ON_ERROR)."\n");
        fflush($pipe);
        fclose($pipe);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function openPostgreSqlConnection(array $config): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
            (string) ($config['host'] ?? '127.0.0.1'),
            (string) ($config['port'] ?? '5432'),
            (string) ($config['database'] ?? ''),
            (string) ($config['sslmode'] ?? 'prefer'),
        );

        return new PDO(
            $dsn,
            (string) ($config['username'] ?? ''),
            (string) ($config['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        );
    }

    /**
     * @param  list<int>  $backendPids
     */
    private function waitUntilWorkersBlock(
        PDO $connection,
        array $backendPids,
    ): bool {
        if (count($backendPids) !== 2) {
            throw new RuntimeException(
                'Exactly two ADMIN-ORG worker backend PIDs are required.',
            );
        }

        $waiting = $connection->prepare(
            <<<'SQL'
                select count(*)
                from pg_stat_activity
                where pid in (:first_pid, :second_pid)
                  and wait_event_type = 'Lock'
                SQL,
        );

        for ($attempt = 0; $attempt < 200; $attempt++) {
            $connection->exec('select pg_stat_clear_snapshot()');
            $waiting->execute([
                'first_pid' => $backendPids[0],
                'second_pid' => $backendPids[1],
            ]);

            if ((int) $waiting->fetchColumn() === 2) {
                return true;
            }

            usleep(10_000);
        }

        return false;
    }

    /**
     * @param  list<WorkerResult>  $results
     * @return list<string>
     */
    private function sortedOutcomes(array $results): array
    {
        $outcomes = array_column($results, 'outcome');
        sort($outcomes);

        return $outcomes;
    }
}

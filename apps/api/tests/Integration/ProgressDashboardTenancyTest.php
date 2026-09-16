<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Learning\Progress\LearnerProgressDashboardService;
use App\Learning\Review\DelayedReviewScheduler;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Operator\OperatorLearningMetricsQuery;
use App\Tenancy\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class ProgressDashboardTenancyTest extends TenancyIntegrationTestCase
{
    public function test_progress_dashboard_excludes_other_organization_rows(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            self::markTestSkipped(
                'PostgreSQL integration tests require pdo_pgsql; this host cannot run them.',
            );
        }

        [$learnerA, $membershipA, $contextA] = $this->learner();
        $organizationB = $this->createOrganization('Progress tenant B');
        [$learnerB, $membershipB, $contextB] = $this->learner($organizationB);
        $curriculum = $this->foundationPublication($membershipA);
        $shareGrantId = $this->shareWithOrganization(
            $curriculum,
            $membershipA,
            $organizationB,
        );
        $this->reviewOccurrence(
            $membershipA,
            $curriculum,
            null,
            CarbonImmutable::parse('2026-09-15T03:30:00Z'),
            CarbonImmutable::parse('2026-09-15T18:29:59Z'),
        );
        $this->reviewOccurrence(
            $membershipB,
            $curriculum,
            $shareGrantId,
            CarbonImmutable::parse('2026-09-15T03:30:00Z'),
            CarbonImmutable::parse('2026-09-15T18:30:00Z'),
        );

        DB::table('learner_error_patterns')->insert([
            [
                'id' => (string) Str::ulid(),
                'organization_id' => $membershipA->organizationId(),
                'learner_membership_id' => $membershipA->getKey(),
                'error_category' => 'grammar.article',
                'observation_count' => 2,
                'first_observed_at' => now()->subDays(10),
                'last_observed_at' => now()->subDay(),
            ],
            [
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationB->getKey(),
                'learner_membership_id' => $membershipB->getKey(),
                'error_category' => 'grammar.preposition',
                'observation_count' => 3,
                'first_observed_at' => now()->subDays(8),
                'last_observed_at' => now()->subDay(),
            ],
        ]);

        self::assertSame(2, DB::table('learner_error_patterns')->count());

        $dashboard = $this->app->make(LearnerProgressDashboardService::class);
        $payloadA = $this->contexts()->run(
            $contextA,
            fn (): array => $dashboard->forLearner($learnerA),
        );
        $payloadB = $this->contexts()->run(
            $contextB,
            fn (): array => $dashboard->forLearner($learnerB),
        );

        self::assertCount(1, $payloadA['recurring_errors']['patterns']);
        self::assertSame(
            'grammar.article',
            $payloadA['recurring_errors']['patterns'][0]['error_category'],
        );
        self::assertCount(1, $payloadB['recurring_errors']['patterns']);
        self::assertSame(
            'grammar.preposition',
            $payloadB['recurring_errors']['patterns'][0]['error_category'],
        );
        self::assertCount(1, $payloadA['objectives']);
        self::assertCount(1, $payloadB['objectives']);
        self::assertSame(1, $payloadA['delayed_review']['reviewed_count']);
        self::assertSame(1, $payloadA['delayed_review']['reviewed_on_time_count']);
        self::assertSame(0, $payloadA['delayed_review']['reviewed_late_count']);
        self::assertSame(1, $payloadB['delayed_review']['reviewed_count']);
        self::assertSame(0, $payloadB['delayed_review']['reviewed_on_time_count']);
        self::assertSame(1, $payloadB['delayed_review']['reviewed_late_count']);
        $operatorMetrics = app(OperatorLearningMetricsQuery::class);
        self::assertSame(
            1,
            $operatorMetrics->summarize($membershipA->organizationId())[
                'review_return_attempted_count'
            ],
        );
        self::assertSame(
            1,
            $operatorMetrics->summarize($membershipB->organizationId())[
                'review_return_attempted_count'
            ],
        );
        self::assertSame(0, $payloadA['speaking_activity']['meaningful_speaking_days_count']);
        self::assertSame(0, $payloadB['speaking_activity']['meaningful_speaking_days_count']);
    }

    public function test_review_attempt_writer_never_clears_another_organization_occurrence(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            self::markTestSkipped(
                'PostgreSQL integration tests require pdo_pgsql; this host cannot run them.',
            );
        }

        [, $membershipA] = $this->learner();
        $organizationB = $this->createOrganization('Review writer tenant B');
        [, $membershipB] = $this->learner($organizationB);
        $curriculum = $this->foundationPublication($membershipA);
        $shareGrantId = $this->shareWithOrganization(
            $curriculum,
            $membershipA,
            $organizationB,
        );
        $dueAt = CarbonImmutable::parse('2026-09-15T03:30:00Z');
        $occurrenceA = $this->reviewOccurrence(
            $membershipA,
            $curriculum,
            null,
            $dueAt,
            null,
        );
        $occurrenceB = $this->reviewOccurrence(
            $membershipB,
            $curriculum,
            $shareGrantId,
            $dueAt,
            null,
        );

        app(DelayedReviewScheduler::class)->recordEligibleAttempt(
            $occurrenceA['attempt'],
            $dueAt,
        );

        $this->assertDatabaseHas('delayed_review_occurrences', [
            'organization_id' => $membershipA->organizationId(),
            'id' => $occurrenceA['occurrence_id'],
            'completion_attempt_id' => $occurrenceA['attempt']->id,
        ]);
        $this->assertDatabaseHas('delayed_review_occurrences', [
            'organization_id' => $membershipB->organizationId(),
            'id' => $occurrenceB['occurrence_id'],
            'completed_at' => null,
            'completion_attempt_id' => null,
        ]);
    }

    /**
     * @return array{User, Membership, OrganizationContext}
     */
    private function learner(
        ?Organization $organization = null,
    ): array {
        $organization ??= Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->firstOrFail();
        $user = $this->createUser();
        [$membership, $context] = $this->createMembership(
            $organization,
            $user,
        );
        $role = $this->createRole(
            $context,
            'progress-'.strtolower((string) Str::ulid()),
        );
        $permission = Permission::query()
            ->where('key', 'learning.participate')
            ->firstOrFail();
        $now = now();
        DB::table('membership_roles')->insert([
            'organization_id' => $organization->getKey(),
            'membership_id' => $membership->getKey(),
            'role_id' => $role->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('role_permissions')->insert([
            'organization_id' => $organization->getKey(),
            'role_id' => $role->getKey(),
            'permission_id' => $permission->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$user, $membership, $context];
    }

    /**
     * @return array{
     *   owner_organization_id: string,
     *   program_id: string,
     *   version_id: string,
     *   publication_id: string,
     *   manifest_hash: string,
     *   objective_id: string,
     *   mission_id: string,
     *   learning_item_id: string
     * }
     */
    private function foundationPublication(Membership $publisher): array
    {
        $ownerOrganizationId = $publisher->organizationId();
        $program = DB::table('programs')
            ->where('organization_id', $ownerOrganizationId)
            ->where('key', 'synthetic-foundation-preview')
            ->firstOrFail();
        $version = DB::table('curriculum_versions')
            ->where('organization_id', $ownerOrganizationId)
            ->where('program_id', $program->id)
            ->where('key', 'draft-1')
            ->firstOrFail();
        $objective = DB::table('learning_objectives')
            ->where('organization_id', $ownerOrganizationId)
            ->where('curriculum_version_id', $version->id)
            ->where('key', 'introduce-yourself')
            ->firstOrFail();
        $mission = DB::table('curriculum_missions')
            ->where('organization_id', $ownerOrganizationId)
            ->where('curriculum_version_id', $version->id)
            ->where('key', 'synthetic-introduction-mission')
            ->firstOrFail();
        $missionItem = DB::table('mission_items')
            ->where('organization_id', $ownerOrganizationId)
            ->where('curriculum_version_id', $version->id)
            ->where('mission_id', $mission->id)
            ->firstOrFail();
        $publicationId = (string) Str::ulid();
        $manifestHash = hash('sha256', 'ux-07a-tenancy-publication');

        DB::table('curriculum_publications')->insert([
            'id' => $publicationId,
            'organization_id' => $ownerOrganizationId,
            'program_id' => $program->id,
            'curriculum_version_id' => $version->id,
            'publication_number' => 1,
            'manifest_schema_version' => 1,
            'manifest' => json_encode(['test' => 'ux-07a'], JSON_THROW_ON_ERROR),
            'manifest_hash' => $manifestHash,
            'created_by_membership_id' => $publisher->getKey(),
            'created_at' => now(),
        ]);

        return [
            'owner_organization_id' => $ownerOrganizationId,
            'program_id' => (string) $program->id,
            'version_id' => (string) $version->id,
            'publication_id' => $publicationId,
            'manifest_hash' => $manifestHash,
            'objective_id' => (string) $objective->id,
            'mission_id' => (string) $mission->id,
            'learning_item_id' => (string) $missionItem->learning_item_id,
        ];
    }

    /**
     * @param  array<string, string>  $curriculum
     */
    private function shareWithOrganization(
        array $curriculum,
        Membership $issuer,
        Organization $recipient,
    ): string {
        $id = (string) Str::ulid();
        $now = now();
        DB::table('curriculum_share_grants')->insert([
            'id' => $id,
            'organization_id' => $curriculum['owner_organization_id'],
            'recipient_organization_id' => $recipient->getKey(),
            'curriculum_version_id' => $curriculum['version_id'],
            'curriculum_publication_id' => $curriculum['publication_id'],
            'allow_preview' => false,
            'allow_enrollment' => true,
            'allow_clone' => false,
            'effective_at' => $now->subDay(),
            'expires_at' => null,
            'issued_by_membership_id' => $issuer->getKey(),
            'issued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    /**
     * @param  array<string, string>  $curriculum
     * @return array{occurrence_id: string, attempt: stdClass}
     */
    private function reviewOccurrence(
        Membership $learner,
        array $curriculum,
        ?string $shareGrantId,
        CarbonImmutable $dueAt,
        ?CarbonImmutable $attemptedAt,
    ): array {
        $organizationId = $learner->organizationId();
        $enrollmentId = (string) Str::ulid();
        $now = $dueAt->subDays(2);
        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'organization_id' => $organizationId,
            'learner_membership_id' => $learner->getKey(),
            'cohort_id' => null,
            'curriculum_owner_organization_id' => $curriculum['owner_organization_id'],
            'curriculum_version_id' => $curriculum['version_id'],
            'curriculum_publication_id' => $curriculum['publication_id'],
            'curriculum_share_grant_id' => $shareGrantId,
            'status' => 'active',
            'enrolled_at' => $now,
            'activated_at' => $now,
            'ended_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sourceAttempt = $this->masteredAttempt(
            $learner,
            $enrollmentId,
            $curriculum,
            1,
            $now,
        );
        $attemptId = (string) Str::ulid();
        DB::table('learner_attempts')->insert([
            'id' => $attemptId,
            'organization_id' => $organizationId,
            'enrollment_id' => $enrollmentId,
            'learner_membership_id' => $learner->getKey(),
            'curriculum_owner_organization_id' => $curriculum['owner_organization_id'],
            'curriculum_version_id' => $curriculum['version_id'],
            'curriculum_publication_id' => $curriculum['publication_id'],
            'curriculum_mission_id' => $curriculum['mission_id'],
            'learning_objective_id' => $curriculum['objective_id'],
            'publication_manifest_hash' => $curriculum['manifest_hash'],
            'mission_definition_hash' => hash('sha256', 'ux-07a-review-attempt'),
            'rule_version' => 'daily-speaking-v1',
            'attempt_number' => 2,
            'status' => 'in_progress',
            'started_at' => $dueAt,
            'submitted_at' => null,
            'created_at' => $dueAt,
            'updated_at' => $dueAt,
        ]);
        $occurrenceId = (string) Str::ulid();
        DB::table('delayed_review_occurrences')->insert([
            'id' => $occurrenceId,
            'organization_id' => $organizationId,
            'enrollment_id' => $enrollmentId,
            'learner_membership_id' => $learner->getKey(),
            'learning_objective_id' => $curriculum['objective_id'],
            'source_attempt_id' => $sourceAttempt->id,
            'curriculum_mission_id' => $curriculum['mission_id'],
            'sequence' => 1,
            'interval_days' => 1,
            'rule_version' => 'provisional-v1',
            'timezone' => 'Asia/Kolkata',
            'due_at' => $dueAt,
            'completed_at' => $attemptedAt,
            'completion_attempt_id' => $attemptedAt === null ? null : $attemptId,
            'created_at' => $now,
            'updated_at' => $attemptedAt ?? $now,
        ]);

        return [
            'occurrence_id' => $occurrenceId,
            'attempt' => DB::table('learner_attempts')
                ->where('organization_id', $organizationId)
                ->where('id', $attemptId)
                ->firstOrFail(),
        ];
    }

    /**
     * @param  array<string, string>  $curriculum
     */
    private function masteredAttempt(
        Membership $learner,
        string $enrollmentId,
        array $curriculum,
        int $attemptNumber,
        CarbonImmutable $startedAt,
    ): stdClass {
        $organizationId = $learner->organizationId();
        $attemptId = (string) Str::ulid();
        $respondedAt = $startedAt->addMinute();
        $submittedAt = $respondedAt->addMinute();
        DB::table('learner_attempts')->insert([
            'id' => $attemptId,
            'organization_id' => $organizationId,
            'enrollment_id' => $enrollmentId,
            'learner_membership_id' => $learner->getKey(),
            'curriculum_owner_organization_id' => $curriculum['owner_organization_id'],
            'curriculum_version_id' => $curriculum['version_id'],
            'curriculum_publication_id' => $curriculum['publication_id'],
            'curriculum_mission_id' => $curriculum['mission_id'],
            'learning_objective_id' => $curriculum['objective_id'],
            'publication_manifest_hash' => $curriculum['manifest_hash'],
            'mission_definition_hash' => hash('sha256', 'ux-07a-source-attempt'),
            'rule_version' => 'daily-speaking-v1',
            'attempt_number' => $attemptNumber,
            'status' => 'in_progress',
            'started_at' => $startedAt,
            'submitted_at' => null,
            'created_at' => $startedAt,
            'updated_at' => $startedAt,
        ]);
        DB::table('item_responses')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'learner_attempt_id' => $attemptId,
            'learner_membership_id' => $learner->getKey(),
            'curriculum_owner_organization_id' => $curriculum['owner_organization_id'],
            'curriculum_version_id' => $curriculum['version_id'],
            'curriculum_mission_id' => $curriculum['mission_id'],
            'learning_item_id' => $curriculum['learning_item_id'],
            'response_number' => 1,
            'check_id' => 'ux-07a-check',
            'selected_option_id' => 'correct',
            'check_correct' => true,
            'client_capture_id' => null,
            'capture_mime_type' => null,
            'capture_byte_size' => null,
            'capture_duration_ms' => null,
            'captured_at' => null,
            'created_at' => $respondedAt,
        ]);
        DB::table('learner_attempts')
            ->where('organization_id', $organizationId)
            ->where('id', $attemptId)
            ->update([
                'status' => 'submitted',
                'submitted_at' => $submittedAt,
                'updated_at' => $submittedAt,
            ]);
        DB::table('attempt_submissions')->insert([
            'organization_id' => $organizationId,
            'learner_attempt_id' => $attemptId,
            'learner_membership_id' => $learner->getKey(),
            'attempt_number' => $attemptNumber,
            'knowledge_score' => 100,
            'knowledge_check_correct' => true,
            'local_capture_receipt_present' => false,
            'knowledge_state' => 'mastered',
            'spoken_state' => 'unassessed',
            'result_key' => 'mission.passed',
            'rule_version' => 'daily-speaking-v1',
            'submitted_at' => $submittedAt,
            'created_at' => $submittedAt,
        ]);
        DB::table('mastery_states')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'enrollment_id' => $enrollmentId,
            'learner_membership_id' => $learner->getKey(),
            'curriculum_owner_organization_id' => $curriculum['owner_organization_id'],
            'curriculum_version_id' => $curriculum['version_id'],
            'curriculum_publication_id' => $curriculum['publication_id'],
            'learning_objective_id' => $curriculum['objective_id'],
            'knowledge_state' => 'mastered',
            'spoken_state' => 'unassessed',
            'best_knowledge_score' => 100,
            'evidence_attempt_id' => $attemptId,
            'rule_version' => 'daily-speaking-v1',
            'mastered_at' => $submittedAt,
            'lock_version' => 0,
            'created_at' => $submittedAt,
            'updated_at' => $submittedAt,
        ]);

        return DB::table('learner_attempts')
            ->where('organization_id', $organizationId)
            ->where('id', $attemptId)
            ->firstOrFail();
    }
}

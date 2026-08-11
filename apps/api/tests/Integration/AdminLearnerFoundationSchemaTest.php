<?php

declare(strict_types=1);

namespace Tests\Integration;

use Closure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class AdminLearnerFoundationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_round_trip_backfills_and_protects_learner_authority(): void
    {
        $migration = $this->migration();
        $migration->down();

        self::assertFalse(Schema::hasTable('learner_profile_current_goals'));
        self::assertFalse(Schema::hasTable('learner_goal_corrections'));
        self::assertFalse(Schema::hasColumn('enrollments', 'version'));
        self::assertFalse(Schema::hasColumn(
            'organization_administration_events',
            'subject_enrollment_id',
        ));

        $fixture = $this->legacyFixture();
        $migration->up();

        self::assertTrue(Schema::hasTable('learner_profile_current_goals'));
        self::assertTrue(Schema::hasTable('learner_goal_corrections'));
        self::assertTrue(Schema::hasColumn('enrollments', 'version'));
        self::assertTrue(Schema::hasColumn(
            'organization_administration_events',
            'subject_learner_profile_id',
        ));
        $current = DB::table('learner_profile_current_goals')
            ->where('learner_membership_id', $fixture['learner_membership_id'])
            ->sole();
        self::assertSame($fixture['goal_id'], $current->id);
        self::assertSame('everyday-conversation', $current->goal_key);
        self::assertSame(1, (int) $current->version);
        self::assertNull($current->corrected_at);
        self::assertSame(
            1,
            (int) DB::table('enrollments')
                ->where('id', $fixture['enrollment_id'])
                ->value('version'),
        );

        try {
            DB::transaction(fn () => $this->insertEnrollment(
                $fixture,
                (string) Str::ulid(),
                'pending_curriculum',
            ));
            self::fail('A learner must not have two current enrollments.');
        } catch (QueryException $exception) {
            self::assertContains((string) $exception->getCode(), ['23000', '23505']);
        }

        $historicalEnrollmentId = (string) Str::ulid();
        $this->insertEnrollment(
            $fixture,
            $historicalEnrollmentId,
            'withdrawn',
        );
        self::assertSame(2, DB::table('enrollments')->count());

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $this->expectDatabaseRejection(function () use ($fixture): void {
            DB::table('learner_profile_current_goals')
                ->where('learner_membership_id', $fixture['learner_membership_id'])
                ->update([
                    'goal_key' => 'workplace-confidence',
                    'version' => 2,
                    'corrected_at' => now(),
                    'updated_at' => now(),
                ]);
        }, '55000');

        $effectiveAt = now();
        $commandId = (string) Str::ulid();
        DB::table('organization_administration_commands')->insert([
            'id' => $commandId,
            'organization_id' => $fixture['organization_id'],
            'actor_membership_id' => $fixture['actor_membership_id'],
            'idempotency_key' => (string) Str::uuid(),
            'operation' => 'learner_goal.correct',
            'target_type' => 'learner_membership',
            'target_id' => $fixture['learner_membership_id'],
            'request_fingerprint' => hash('sha256', 'goal-correction'),
            'result_type' => 'learner',
            'result_id' => $fixture['learner_membership_id'],
            'completed_at' => $effectiveAt,
            'expires_at' => $effectiveAt->copy()->addDays(365),
            'created_at' => $effectiveAt,
        ]);
        $correctionId = (string) Str::ulid();
        DB::table('learner_goal_corrections')->insert([
            'id' => $correctionId,
            'organization_id' => $fixture['organization_id'],
            'learner_profile_id' => $fixture['profile_id'],
            'learner_membership_id' => $fixture['learner_membership_id'],
            'actor_membership_id' => $fixture['actor_membership_id'],
            'command_id' => $commandId,
            'previous_goal_key' => 'everyday-conversation',
            'successor_goal_key' => 'workplace-confidence',
            'effective_at' => $effectiveAt,
            'created_at' => $effectiveAt,
        ]);
        DB::table('learner_profile_current_goals')
            ->where('learner_membership_id', $fixture['learner_membership_id'])
            ->update([
                'goal_key' => 'workplace-confidence',
                'version' => 2,
                'corrected_at' => $effectiveAt,
                'updated_at' => $effectiveAt,
            ]);
        self::assertSame(
            'workplace-confidence',
            DB::table('learner_profile_current_goals')
                ->where('learner_membership_id', $fixture['learner_membership_id'])
                ->value('goal_key'),
        );
        $this->expectDatabaseRejection(
            fn (): int => DB::table('learner_goal_corrections')
                ->where('id', $correctionId)
                ->delete(),
            '55000',
        );
        $this->expectDatabaseRejection(function () use ($fixture): void {
            DB::table('enrollments')
                ->where('id', $fixture['enrollment_id'])
                ->update(['cohort_id' => null, 'updated_at' => now()]);
        }, '55000');
    }

    public function test_migration_fails_closed_on_duplicate_current_enrollments(): void
    {
        $migration = $this->migration();
        $migration->down();
        $fixture = $this->legacyFixture();
        $duplicateId = (string) Str::ulid();
        $this->insertEnrollment($fixture, $duplicateId, 'pending_curriculum');

        try {
            $migration->up();
            self::fail('The migration must not choose a current enrollment winner.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Cannot enable learner administration while a learner has more than one current enrollment.',
                $exception->getMessage(),
            );
        }

        DB::table('enrollments')->where('id', $duplicateId)->delete();
        $migration->up();
        self::assertTrue(Schema::hasColumn('enrollments', 'version'));
    }

    /**
     * @return array{
     *     organization_id: string,
     *     actor_membership_id: string,
     *     learner_membership_id: string,
     *     cohort_id: string,
     *     enrollment_id: string,
     *     profile_id: string,
     *     goal_id: string
     * }
     */
    private function legacyFixture(): array
    {
        $organizationId = (string) Str::ulid();
        $actorUserId = (string) Str::ulid();
        $actorMembershipId = (string) Str::ulid();
        $learnerUserId = (string) Str::ulid();
        $learnerMembershipId = (string) Str::ulid();
        $cohortId = (string) Str::ulid();
        $enrollmentId = (string) Str::ulid();
        $profileId = (string) Str::ulid();
        $goalId = (string) Str::ulid();
        $now = now();
        DB::table('organizations')->insert([
            'id' => $organizationId,
            'system_key' => null,
            'name' => 'Learner Migration Tenant',
            'kind' => 'institution',
            'status' => 'active',
            'timezone' => 'Asia/Kolkata',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('users')->insert([
            [
                'id' => $actorUserId,
                'display_name' => 'Migration Actor',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $learnerUserId,
                'display_name' => 'Migration Learner',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
        DB::table('memberships')->insert([
            [
                'id' => $actorMembershipId,
                'organization_id' => $organizationId,
                'user_id' => $actorUserId,
                'membership_type' => 'organization_admin',
                'status' => 'active',
                'version' => 1,
                'joined_at' => $now,
                'ended_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $learnerMembershipId,
                'organization_id' => $organizationId,
                'user_id' => $learnerUserId,
                'membership_type' => 'learner',
                'status' => 'active',
                'version' => 1,
                'joined_at' => $now,
                'ended_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
        DB::table('cohorts')->insert([
            'id' => $cohortId,
            'organization_id' => $organizationId,
            'branch_id' => null,
            'key' => 'migration-cohort',
            'name' => 'Migration Cohort',
            'status' => 'active',
            'version' => 1,
            'archived_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $fixture = [
            'organization_id' => $organizationId,
            'actor_membership_id' => $actorMembershipId,
            'learner_membership_id' => $learnerMembershipId,
            'cohort_id' => $cohortId,
            'enrollment_id' => $enrollmentId,
            'profile_id' => $profileId,
            'goal_id' => $goalId,
        ];
        $this->insertEnrollment($fixture, $enrollmentId, 'pending_curriculum');
        DB::table('learner_profiles')->insert([
            'id' => $profileId,
            'organization_id' => $organizationId,
            'learner_membership_id' => $learnerMembershipId,
            'adult_confirmed_at' => $now,
            'onboarding_completed_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('learner_goals')->insert([
            'id' => $goalId,
            'organization_id' => $organizationId,
            'learner_profile_id' => $profileId,
            'learner_membership_id' => $learnerMembershipId,
            'goal_key' => 'everyday-conversation',
            'selected_at' => $now,
            'created_at' => $now,
        ]);

        return $fixture;
    }

    /**
     * @param  array{organization_id: string, learner_membership_id: string, cohort_id: string}  $fixture
     */
    private function insertEnrollment(
        array $fixture,
        string $enrollmentId,
        string $status,
    ): void {
        $now = now();
        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'organization_id' => $fixture['organization_id'],
            'learner_membership_id' => $fixture['learner_membership_id'],
            'cohort_id' => $fixture['cohort_id'],
            'curriculum_owner_organization_id' => null,
            'curriculum_version_id' => null,
            'curriculum_publication_id' => null,
            'curriculum_share_grant_id' => null,
            'status' => $status,
            'enrolled_at' => $now,
            'activated_at' => null,
            'ended_at' => in_array($status, ['completed', 'withdrawn'], true)
                ? $now
                : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function migration(): AdminLearnerMigrationRunner
    {
        $migration = require database_path(
            'migrations/2026_08_09_000130_create_admin_learner_foundation.php',
        );

        if (
            ! $migration instanceof Migration
            || ! method_exists($migration, 'up')
            || ! method_exists($migration, 'down')
        ) {
            throw new RuntimeException('The ADMIN-LEARNER-01 migration is not executable.');
        }

        return new AdminLearnerMigrationRunner(
            Closure::fromCallable([$migration, 'up']),
            Closure::fromCallable([$migration, 'down']),
        );
    }

    private function expectDatabaseRejection(
        callable $operation,
        string $code,
    ): void {
        try {
            DB::transaction(static fn () => $operation());
        } catch (QueryException $exception) {
            self::assertSame($code, (string) $exception->getCode());

            return;
        }

        self::fail('The database should reject the invalid authoritative mutation.');
    }
}

final readonly class AdminLearnerMigrationRunner
{
    /**
     * @param  Closure(): void  $up
     * @param  Closure(): void  $down
     */
    public function __construct(
        private Closure $up,
        private Closure $down,
    ) {}

    public function up(): void
    {
        ($this->up)();
    }

    public function down(): void
    {
        ($this->down)();
    }
}

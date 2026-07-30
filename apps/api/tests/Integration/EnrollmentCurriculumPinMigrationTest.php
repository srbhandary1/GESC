<?php

declare(strict_types=1);

namespace Tests\Integration;

use Closure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class EnrollmentCurriculumPinMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_active_enrollment_becomes_unpinned_pending_curriculum(): void
    {
        self::assertSame('pgsql', DB::connection()->getDriverName());
        self::assertSame(0, DB::table('enrollments')->count());
        self::assertSame(0, DB::table('curriculum_publications')->count());

        $this->runEnrollmentPinMigration('down');

        self::assertFalse(
            Schema::hasColumn('enrollments', 'curriculum_publication_id'),
        );

        $enrollmentId = $this->createEnrollment('active');

        self::assertDatabaseHas('enrollments', [
            'id' => $enrollmentId,
            'status' => 'active',
        ]);

        $this->runEnrollmentPinMigration('up');

        $enrollment = DB::table('enrollments')
            ->where('id', $enrollmentId)
            ->sole();

        self::assertSame('pending_curriculum', $enrollment->status);
        self::assertNull($enrollment->curriculum_owner_organization_id);
        self::assertNull($enrollment->curriculum_version_id);
        self::assertNull($enrollment->curriculum_publication_id);
        self::assertNull($enrollment->curriculum_share_grant_id);
        self::assertNull($enrollment->activated_at);
    }

    public function test_rollback_fails_closed_and_preserves_an_existing_enrollment(): void
    {
        self::assertSame('pgsql', DB::connection()->getDriverName());

        $enrollmentId = $this->createEnrollment('pending_curriculum');

        try {
            $this->runEnrollmentPinMigration('down');
            self::fail(
                'A populated PR-005 enrollment migration rollback must fail closed.',
            );
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Cannot roll back PR-005 enrollment pins while enrollment records exist. Preserve authoritative pins and deploy a reviewed forward-fix migration.',
                $exception->getMessage(),
            );
        }

        self::assertTrue(
            Schema::hasColumn('enrollments', 'curriculum_publication_id'),
        );
        self::assertDatabaseHas('enrollments', [
            'id' => $enrollmentId,
            'status' => 'pending_curriculum',
            'curriculum_owner_organization_id' => null,
            'curriculum_version_id' => null,
            'curriculum_publication_id' => null,
            'curriculum_share_grant_id' => null,
            'activated_at' => null,
        ]);
    }

    /**
     * @param  'down'|'up'  $direction
     */
    private function runEnrollmentPinMigration(string $direction): void
    {
        if ($direction === 'down') {
            $this->runDeterministicLearningMigration('down');
        }

        $migration = require database_path(
            'migrations/2026_07_30_000023_pin_enrollments_to_curriculum_publications.php',
        );

        if (
            ! $migration instanceof Migration
            || ! is_callable([$migration, $direction])
        ) {
            throw new RuntimeException(
                'The PR-005 enrollment-pin migration is not executable.',
            );
        }

        Closure::fromCallable([$migration, $direction])();

        if ($direction === 'up') {
            $this->runDeterministicLearningMigration('up');
        }
    }

    /**
     * @param  'down'|'up'  $direction
     */
    private function runDeterministicLearningMigration(string $direction): void
    {
        $migration = require database_path(
            'migrations/2026_07_30_000024_create_deterministic_learning_tables.php',
        );

        if (
            ! $migration instanceof Migration
            || ! is_callable([$migration, $direction])
        ) {
            throw new RuntimeException(
                'The PR-006 deterministic-learning migration is not executable.',
            );
        }

        Closure::fromCallable([$migration, $direction])();
    }

    private function createEnrollment(string $status): string
    {
        $organizationId = (string) Str::ulid();
        $userId = (string) Str::ulid();
        $membershipId = (string) Str::ulid();
        $cohortId = (string) Str::ulid();
        $enrollmentId = (string) Str::ulid();
        $now = now();

        DB::table('organizations')->insert([
            'id' => $organizationId,
            'system_key' => null,
            'name' => 'Migration Safety Tenant',
            'kind' => 'institution',
            'status' => 'active',
            'timezone' => 'Asia/Kolkata',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('users')->insert([
            'id' => $userId,
            'display_name' => null,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('memberships')->insert([
            'id' => $membershipId,
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'membership_type' => 'learner',
            'status' => 'active',
            'joined_at' => $now,
            'ended_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('cohorts')->insert([
            'id' => $cohortId,
            'organization_id' => $organizationId,
            'key' => 'migration-safety',
            'name' => 'Migration Safety',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'organization_id' => $organizationId,
            'learner_membership_id' => $membershipId,
            'cohort_id' => $cohortId,
            'status' => $status,
            'enrolled_at' => $now,
            'ended_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $enrollmentId;
    }
}

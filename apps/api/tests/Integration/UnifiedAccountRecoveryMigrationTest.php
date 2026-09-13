<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\User;
use Closure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class UnifiedAccountRecoveryMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_waiting_case_is_superseded_without_losing_approval_and_rollback_restores_it(): void
    {
        self::assertSame('pgsql', DB::connection()->getDriverName());
        $this->runMigration('down');

        $target = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        $actor = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        $caseId = (string) Str::ulid();
        $approvalId = (string) Str::ulid();
        $challengeId = (string) Str::ulid();
        $now = now();

        DB::table('privileged_password_recovery_cases')->insert([
            'id' => $caseId,
            'target_user_id' => (string) $target->getKey(),
            'requested_by_user_id' => (string) $actor->getKey(),
            'reason_key' => 'excluded_account_recovery',
            'recovery_kind' => 'excluded_credential_establishment',
            'evidence_reference' => 'historical-support-case',
            'evidence_digest' => str_repeat('a', 64),
            'status' => 'waiting_delay',
            'version' => 2,
            'requested_at' => $now,
            'not_before_at' => $now->copy()->addDay(),
            'expires_at' => $now->copy()->addDays(2),
            'completed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('privileged_password_recovery_approvals')->insert([
            'id' => $approvalId,
            'case_id' => $caseId,
            'actor_user_id' => (string) $actor->getKey(),
            'case_version' => 2,
            'evidence_digest' => str_repeat('b', 64),
            'approved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('password_recovery_challenges')->insert([
            'id' => $challengeId,
            'user_id' => (string) $target->getKey(),
            'recovery_identity_id' => null,
            'purpose' => 'excluded_credential_establishment',
            'privileged_recovery_case_id' => $caseId,
            'lookup_digest' => str_repeat('c', 64),
            'token_digest' => str_repeat('d', 64),
            'status' => 'pending',
            'failed_attempts' => 0,
            'delivery_outcome' => 'accepted',
            'expires_at' => $now->copy()->addMinutes(15),
            'consumed_at' => null,
            'superseded_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->runMigration('up');

        $this->assertDatabaseHas('privileged_password_recovery_cases', [
            'id' => $caseId,
            'status' => 'superseded',
            'superseded_from_status' => 'waiting_delay',
            'supersession_reason' => 'unified_account_recovery',
        ]);
        $this->assertDatabaseHas('password_recovery_challenges', [
            'id' => $challengeId,
            'status' => 'superseded',
        ]);
        $this->assertDatabaseHas('privileged_password_recovery_approvals', [
            'id' => $approvalId,
            'case_id' => $caseId,
            'case_version' => 2,
        ]);

        $this->runMigration('down');

        $this->assertDatabaseHas('privileged_password_recovery_cases', [
            'id' => $caseId,
            'status' => 'waiting_delay',
        ]);
        $this->assertDatabaseHas('password_recovery_challenges', [
            'id' => $challengeId,
            'status' => 'pending',
            'superseded_at' => null,
        ]);
        $this->assertDatabaseHas('privileged_password_recovery_approvals', [
            'id' => $approvalId,
            'case_id' => $caseId,
        ]);
    }

    /** @param 'up'|'down' $direction */
    private function runMigration(string $direction): void
    {
        $migration = require database_path(
            'migrations/2026_08_13_000200_create_unified_account_recovery.php',
        );

        if (! $migration instanceof Migration || ! is_callable([$migration, $direction])) {
            throw new RuntimeException('The unified account-recovery migration is not executable.');
        }

        Closure::fromCallable([$migration, $direction])();
    }
}

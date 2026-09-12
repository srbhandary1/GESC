<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_recovery_management_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('target_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 48);
            $table->string('reason_key', 80);
            $table->char('evidence_digest', 64);
            $table->foreignUlid('prior_identity_id')->nullable()
                ->constrained('user_identities')->restrictOnDelete();
            $table->foreignUlid('replacement_identity_id')->nullable()
                ->constrained('user_identities')->restrictOnDelete();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['target_user_id', 'occurred_at']);
            $table->index(['actor_user_id', 'occurred_at']);
        });

        Schema::create('account_recovery_notification_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('management_event_id');
            $table->ulid('former_identity_id');
            $table->string('outcome', 24);
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->unique(
                'management_event_id',
                'account_recovery_notification_management_unique',
            );
            $table->foreign(
                'management_event_id',
                'account_recovery_notification_management_fk',
            )->references('id')->on('account_recovery_management_events')
                ->restrictOnDelete();
            $table->foreign(
                'former_identity_id',
                'account_recovery_notification_former_identity_fk',
            )->references('id')->on('user_identities')->restrictOnDelete();
        });

        Schema::table('password_recovery_challenges', function (Blueprint $table): void {
            $table->ulid('account_recovery_management_event_id')
                ->nullable()->after('privileged_recovery_case_id');
            $table->unique(
                'account_recovery_management_event_id',
                'password_recovery_account_management_unique',
            );
            $table->foreign(
                'account_recovery_management_event_id',
                'password_recovery_account_management_fk',
            )->references('id')->on('account_recovery_management_events')
                ->restrictOnDelete();
        });

        Schema::table('privileged_password_recovery_cases', function (Blueprint $table): void {
            $table->string('superseded_from_status', 32)->nullable()->after('status');
            $table->timestampTz('superseded_at')->nullable()->after('completed_at');
            $table->string('supersession_reason', 80)->nullable()->after('superseded_at');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE privileged_password_recovery_cases DROP CONSTRAINT privileged_password_recovery_case_contract_check');
        }

        $transitionedAt = now();

        DB::table('password_recovery_challenges')
            ->whereNotNull('privileged_recovery_case_id')
            ->where('status', 'pending')
            ->update([
                'status' => 'superseded',
                'superseded_at' => $transitionedAt,
                'updated_at' => $transitionedAt,
            ]);

        DB::table('privileged_password_recovery_cases')
            ->whereIn('status', ['pending_approval', 'waiting_delay'])
            ->update([
                'superseded_from_status' => DB::raw('status'),
                'status' => 'superseded',
                'superseded_at' => $transitionedAt,
                'supersession_reason' => 'unified_account_recovery',
                'updated_at' => $transitionedAt,
            ]);

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE password_recovery_challenges DROP CONSTRAINT password_recovery_contract_check');
        DB::statement(<<<'SQL'
ALTER TABLE password_recovery_challenges ADD CONSTRAINT password_recovery_contract_check CHECK (
    purpose IN ('password_reset', 'excluded_credential_establishment', 'account_setup')
    AND status IN ('pending', 'consumed', 'expired', 'superseded')
    AND failed_attempts BETWEEN 0 AND 5
    AND ((status = 'consumed' AND consumed_at IS NOT NULL) OR status <> 'consumed')
    AND (
      (purpose = 'excluded_credential_establishment' AND privileged_recovery_case_id IS NOT NULL AND account_recovery_management_event_id IS NULL)
      OR (purpose = 'account_setup' AND privileged_recovery_case_id IS NULL AND account_recovery_management_event_id IS NOT NULL)
      OR (purpose = 'password_reset' AND account_recovery_management_event_id IS NULL)
    )
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE privileged_password_recovery_cases ADD CONSTRAINT privileged_password_recovery_case_contract_check CHECK (
    recovery_kind IN ('password_reset', 'excluded_credential_establishment')
    AND status IN ('pending_approval', 'waiting_delay', 'completed', 'expired', 'cancelled', 'superseded')
    AND version > 0 AND not_before_at > requested_at AND expires_at > not_before_at
    AND ((status = 'completed' AND completed_at IS NOT NULL) OR status <> 'completed')
    AND (
      (status = 'superseded' AND superseded_from_status IN ('pending_approval', 'waiting_delay') AND superseded_at IS NOT NULL AND supersession_reason = 'unified_account_recovery')
      OR (status <> 'superseded' AND superseded_from_status IS NULL AND superseded_at IS NULL AND supersession_reason IS NULL)
    )
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE account_recovery_management_events ADD CONSTRAINT account_recovery_management_events_contract_check CHECK (
    action IN ('credential_setup_requested', 'recovery_contact_replaced')
    AND reason_key IN ('account_recovery.migration_setup', 'account_recovery.verified_contact_replacement')
    AND (
      (action = 'credential_setup_requested' AND prior_identity_id IS NULL AND replacement_identity_id IS NULL)
      OR (action = 'recovery_contact_replaced' AND prior_identity_id IS NOT NULL AND replacement_identity_id IS NOT NULL)
    )
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE account_recovery_notification_events ADD CONSTRAINT account_recovery_notification_events_contract_check CHECK (
    outcome IN ('accepted', 'unavailable', 'not_possible')
)
SQL);
        DB::unprepared(<<<'SQL'
CREATE TRIGGER account_recovery_management_events_append_only
BEFORE UPDATE OR DELETE ON account_recovery_management_events
FOR EACH ROW EXECUTE FUNCTION password_auth_reject_append_only_mutation();
SQL);
        DB::unprepared(<<<'SQL'
CREATE TRIGGER account_recovery_notification_events_append_only
BEFORE UPDATE OR DELETE ON account_recovery_notification_events
FOR EACH ROW EXECUTE FUNCTION password_auth_reject_append_only_mutation();
SQL);
    }

    public function down(): void
    {
        if (
            DB::table('account_recovery_management_events')->exists()
            || DB::table('account_recovery_notification_events')->exists()
            || DB::table('password_recovery_challenges')->where('purpose', 'account_setup')->exists()
        ) {
            throw new RuntimeException(
                'Unified account recovery cannot be rolled back after evidence exists.',
            );
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS account_recovery_notification_events_append_only ON account_recovery_notification_events;');
            DB::unprepared('DROP TRIGGER IF EXISTS account_recovery_management_events_append_only ON account_recovery_management_events;');
            DB::statement('ALTER TABLE password_recovery_challenges DROP CONSTRAINT password_recovery_contract_check');
            DB::statement(<<<'SQL'
ALTER TABLE password_recovery_challenges ADD CONSTRAINT password_recovery_contract_check CHECK (
    purpose IN ('password_reset', 'excluded_credential_establishment')
    AND status IN ('pending', 'consumed', 'expired', 'superseded')
    AND failed_attempts BETWEEN 0 AND 5
    AND ((status = 'consumed' AND consumed_at IS NOT NULL) OR status <> 'consumed')
    AND ((purpose = 'excluded_credential_establishment' AND privileged_recovery_case_id IS NOT NULL) OR purpose = 'password_reset')
)
SQL);
            DB::statement('ALTER TABLE privileged_password_recovery_cases DROP CONSTRAINT privileged_password_recovery_case_contract_check');
        }

        $transitionedCases = DB::table('privileged_password_recovery_cases')
            ->where('status', 'superseded')
            ->where('supersession_reason', 'unified_account_recovery')
            ->get(['id', 'superseded_at']);

        foreach ($transitionedCases as $transitionedCase) {
            DB::table('password_recovery_challenges')
                ->where('privileged_recovery_case_id', (string) $transitionedCase->id)
                ->where('status', 'superseded')
                ->where('superseded_at', $transitionedCase->superseded_at)
                ->update([
                    'status' => 'pending',
                    'superseded_at' => null,
                    'updated_at' => now(),
                ]);
        }

        DB::table('privileged_password_recovery_cases')
            ->where('status', 'superseded')
            ->where('supersession_reason', 'unified_account_recovery')
            ->update([
                'status' => DB::raw('superseded_from_status'),
                'superseded_from_status' => null,
                'superseded_at' => null,
                'supersession_reason' => null,
                'updated_at' => now(),
            ]);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
ALTER TABLE privileged_password_recovery_cases ADD CONSTRAINT privileged_password_recovery_case_contract_check CHECK (
    recovery_kind IN ('password_reset', 'excluded_credential_establishment')
    AND status IN ('pending_approval', 'waiting_delay', 'completed', 'expired', 'cancelled')
    AND version > 0 AND not_before_at > requested_at AND expires_at > not_before_at
    AND ((status = 'completed' AND completed_at IS NOT NULL) OR status <> 'completed')
)
SQL);
        }

        Schema::table('privileged_password_recovery_cases', function (Blueprint $table): void {
            $table->dropColumn([
                'superseded_from_status',
                'superseded_at',
                'supersession_reason',
            ]);
        });
        Schema::table('password_recovery_challenges', function (Blueprint $table): void {
            $table->dropForeign('password_recovery_account_management_fk');
            $table->dropUnique('password_recovery_account_management_unique');
            $table->dropColumn('account_recovery_management_event_id');
        });
        Schema::dropIfExists('account_recovery_notification_events');
        Schema::dropIfExists('account_recovery_management_events');
    }
};

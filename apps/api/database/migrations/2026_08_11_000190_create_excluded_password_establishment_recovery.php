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
        Schema::create('password_cutover_exclusions', function (Blueprint $table): void {
            $table->foreignUlid('user_id')->primary()->constrained('users')->restrictOnDelete();
            $table->string('cutover_scope_key', 32);
            $table->string('reason_key', 80);
            $table->foreignUlid('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->char('manifest_digest', 64);
            $table->timestampTz('reconciled_at');
            $table->timestampsTz();

            $table->foreign('cutover_scope_key')
                ->references('scope_key')->on('password_authentication_cutovers')
                ->restrictOnDelete();
            $table->index(['owner_user_id', 'reconciled_at']);
        });

        Schema::table('privileged_password_recovery_cases', function (Blueprint $table): void {
            $table->string('recovery_kind', 48)->default('password_reset')->after('reason_key');
        });
        Schema::table('password_recovery_challenges', function (Blueprint $table): void {
            $table->string('purpose', 48)->default('password_reset')->after('recovery_identity_id');
            $table->foreignUlid('privileged_recovery_case_id')->nullable()->unique()
                ->after('purpose')
                ->constrained('privileged_password_recovery_cases')->restrictOnDelete();
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE password_recovery_challenges DROP CONSTRAINT password_recovery_contract_check');
        DB::statement(<<<'SQL'
ALTER TABLE password_recovery_challenges ADD CONSTRAINT password_recovery_contract_check CHECK (
    purpose IN ('password_reset', 'excluded_credential_establishment')
    AND status IN ('pending', 'consumed', 'expired', 'superseded')
    AND failed_attempts BETWEEN 0 AND 5
    AND ((status = 'consumed' AND consumed_at IS NOT NULL) OR status <> 'consumed')
    AND ((purpose = 'excluded_credential_establishment' AND privileged_recovery_case_id IS NOT NULL)
      OR purpose = 'password_reset')
)
SQL);
        DB::statement('ALTER TABLE privileged_password_recovery_cases DROP CONSTRAINT privileged_password_recovery_case_contract_check');
        DB::statement(<<<'SQL'
ALTER TABLE privileged_password_recovery_cases ADD CONSTRAINT privileged_password_recovery_case_contract_check CHECK (
    recovery_kind IN ('password_reset', 'excluded_credential_establishment')
    AND status IN ('pending_approval', 'waiting_delay', 'completed', 'expired', 'cancelled')
    AND version > 0 AND not_before_at > requested_at AND expires_at > not_before_at
    AND ((status = 'completed' AND completed_at IS NOT NULL) OR status <> 'completed')
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE password_cutover_exclusions ADD CONSTRAINT password_cutover_exclusions_contract_check CHECK (
    cutover_scope_key = 'global'
    AND reason_key ~ '^cutover_excluded\.[a-z0-9_.-]{3,60}$'
    AND owner_user_id <> user_id
)
SQL);
        DB::unprepared(<<<'SQL'
CREATE TRIGGER password_cutover_exclusions_append_only
BEFORE UPDATE OR DELETE ON password_cutover_exclusions
FOR EACH ROW EXECUTE FUNCTION password_auth_reject_append_only_mutation();
SQL);
    }

    public function down(): void
    {
        if (
            DB::table('password_cutover_exclusions')->exists()
            || DB::table('password_recovery_challenges')
                ->where('purpose', 'excluded_credential_establishment')->exists()
        ) {
            throw new RuntimeException(
                'Excluded-user recovery cannot be rolled back after evidence exists.',
            );
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS password_cutover_exclusions_append_only ON password_cutover_exclusions;');
            DB::statement('ALTER TABLE password_recovery_challenges DROP CONSTRAINT password_recovery_contract_check');
            DB::statement("ALTER TABLE password_recovery_challenges ADD CONSTRAINT password_recovery_contract_check CHECK (status IN ('pending', 'consumed', 'expired', 'superseded') AND failed_attempts BETWEEN 0 AND 5 AND ((status = 'consumed' AND consumed_at IS NOT NULL) OR status <> 'consumed'))");
            DB::statement('ALTER TABLE privileged_password_recovery_cases DROP CONSTRAINT privileged_password_recovery_case_contract_check');
            DB::statement("ALTER TABLE privileged_password_recovery_cases ADD CONSTRAINT privileged_password_recovery_case_contract_check CHECK (status IN ('pending_approval', 'waiting_delay', 'completed', 'expired', 'cancelled') AND version > 0 AND not_before_at > requested_at AND expires_at > not_before_at AND ((status = 'completed' AND completed_at IS NOT NULL) OR status <> 'completed'))");
        }

        Schema::table('password_recovery_challenges', function (Blueprint $table): void {
            $table->dropUnique(['privileged_recovery_case_id']);
            $table->dropConstrainedForeignId('privileged_recovery_case_id');
            $table->dropColumn('purpose');
        });
        Schema::table('privileged_password_recovery_cases', function (Blueprint $table): void {
            $table->dropColumn('recovery_kind');
        });
        Schema::dropIfExists('password_cutover_exclusions');
    }
};

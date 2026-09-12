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
        Schema::create('privileged_mfa_factors', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->index()
                ->constrained('users')->cascadeOnDelete();
            $table->string('factor_type', 24);
            $table->text('secret_ciphertext')->nullable();
            $table->string('status', 24);
            $table->timestampTz('pending_expires_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('replaced_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->bigInteger('last_used_time_step')->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'user_id'], 'privileged_mfa_factors_identity_unique');
            $table->index(
                ['user_id', 'status'],
                'privileged_mfa_factors_user_status_idx',
            );
        });

        Schema::table('authentication_sessions', function (Blueprint $table): void {
            $table->ulid('mfa_factor_id')->nullable()->after('mfa_verified_at');
            $table->string('mfa_verified_method', 24)->nullable()
                ->after('mfa_factor_id');
            $table->string('mfa_verified_scope', 32)->nullable()
                ->after('mfa_verified_method');
            $table->unique(['id', 'user_id'], 'auth_sessions_identity_unique');
            $table->foreign(
                ['mfa_factor_id', 'user_id'],
                'auth_sessions_mfa_factor_user_fk',
            )->references(['id', 'user_id'])
                ->on('privileged_mfa_factors')
                ->restrictOnDelete();
        });

        Schema::create('privileged_mfa_challenges', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->index()
                ->constrained('users')->cascadeOnDelete();
            $table->ulid('authentication_session_id');
            $table->ulid('factor_id');
            $table->string('purpose', 32);
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampsTz();

            $table->foreign(
                ['authentication_session_id', 'user_id'],
                'privileged_mfa_challenges_session_user_fk',
            )->references(['id', 'user_id'])
                ->on('authentication_sessions')
                ->cascadeOnDelete();
            $table->foreign(
                ['factor_id', 'user_id'],
                'privileged_mfa_challenges_factor_user_fk',
            )->references(['id', 'user_id'])
                ->on('privileged_mfa_factors')
                ->cascadeOnDelete();
            $table->index(
                ['user_id', 'authentication_session_id', 'consumed_at', 'expires_at'],
                'privileged_mfa_challenges_active_idx',
            );
        });

        Schema::create('privileged_mfa_recovery_codes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->index()
                ->constrained('users')->cascadeOnDelete();
            $table->ulid('factor_id');
            $table->char('code_digest', 64)->unique();
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('invalidated_at')->nullable();
            $table->timestampsTz();

            $table->foreign(
                ['factor_id', 'user_id'],
                'privileged_mfa_recovery_codes_factor_user_fk',
            )->references(['id', 'user_id'])
                ->on('privileged_mfa_factors')
                ->cascadeOnDelete();
            $table->index(
                ['user_id', 'factor_id', 'consumed_at', 'invalidated_at'],
                'privileged_mfa_recovery_codes_active_idx',
            );
        });

        DB::statement(
            "CREATE UNIQUE INDEX privileged_mfa_factors_one_active_per_user ON privileged_mfa_factors (user_id) WHERE status = 'active'",
        );
        DB::statement(
            "CREATE UNIQUE INDEX privileged_mfa_factors_one_pending_per_user ON privileged_mfa_factors (user_id) WHERE status = 'pending'",
        );

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE privileged_mfa_factors ADD CONSTRAINT privileged_mfa_factors_values_check CHECK (factor_type = 'totp' AND status IN ('pending', 'active', 'replaced', 'revoked', 'abandoned'))",
            );
            DB::statement(
                "ALTER TABLE privileged_mfa_factors ADD CONSTRAINT privileged_mfa_factors_lifecycle_check CHECK ((status = 'pending' AND secret_ciphertext IS NOT NULL AND pending_expires_at IS NOT NULL AND confirmed_at IS NULL AND replaced_at IS NULL AND revoked_at IS NULL) OR (status = 'active' AND secret_ciphertext IS NOT NULL AND pending_expires_at IS NULL AND confirmed_at IS NOT NULL AND replaced_at IS NULL AND revoked_at IS NULL) OR (status = 'replaced' AND secret_ciphertext IS NULL AND pending_expires_at IS NULL AND confirmed_at IS NOT NULL AND replaced_at IS NOT NULL AND revoked_at IS NULL) OR (status = 'revoked' AND secret_ciphertext IS NULL AND pending_expires_at IS NULL AND confirmed_at IS NOT NULL AND replaced_at IS NULL AND revoked_at IS NOT NULL) OR (status = 'abandoned' AND secret_ciphertext IS NULL AND pending_expires_at IS NULL AND confirmed_at IS NULL AND replaced_at IS NULL AND revoked_at IS NULL))",
            );
            DB::statement(
                "ALTER TABLE privileged_mfa_challenges ADD CONSTRAINT privileged_mfa_challenges_values_check CHECK (purpose IN ('enrollment_confirmation', 'assertion') AND failed_attempts BETWEEN 0 AND 5)",
            );
            DB::statement(
                "ALTER TABLE authentication_sessions ADD CONSTRAINT auth_sessions_mfa_assertion_check CHECK ((mfa_verified_at IS NULL AND mfa_factor_id IS NULL AND mfa_verified_method IS NULL AND mfa_verified_scope IS NULL) OR (is_privileged = TRUE AND mfa_verified_at IS NOT NULL AND mfa_factor_id IS NOT NULL AND ((mfa_verified_method = 'totp' AND mfa_verified_scope = 'privileged_actions') OR (mfa_verified_method = 'recovery_code' AND mfa_verified_scope = 'factor_replacement'))))",
            );
        }

        $this->replaceEmergencyEvidenceFunction(true);
    }

    public function down(): void
    {
        $this->replaceEmergencyEvidenceFunction(false);

        Schema::dropIfExists('privileged_mfa_recovery_codes');
        Schema::dropIfExists('privileged_mfa_challenges');

        Schema::table('authentication_sessions', function (Blueprint $table): void {
            $table->dropForeign('auth_sessions_mfa_factor_user_fk');
            $table->dropUnique('auth_sessions_identity_unique');
            $table->dropColumn([
                'mfa_factor_id',
                'mfa_verified_method',
                'mfa_verified_scope',
            ]);
        });

        Schema::dropIfExists('privileged_mfa_factors');
    }

    private function replaceEmergencyEvidenceFunction(bool $requireTotp): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $factorEvidence = $requireTotp
            ? <<<'SQL'
              AND session.mfa_factor_id IS NOT NULL
              AND session.mfa_verified_method = 'totp'
              AND session.mfa_verified_scope = 'privileged_actions'
SQL
            : '';

        DB::unprepared(<<<SQL
CREATE OR REPLACE FUNCTION curriculum_validate_emergency_evidence()
RETURNS trigger
LANGUAGE plpgsql
AS \$\$
DECLARE
    expected_action text;
BEGIN
    IF NEW.event_type NOT IN ('suspended', 'resumed') THEN
        RETURN NEW;
    END IF;

    IF NEW.actor_type = 'owner_membership' THEN
        IF NOT EXISTS (
            SELECT 1
            FROM memberships membership
            JOIN authentication_sessions session
              ON session.user_id = membership.user_id
            WHERE membership.organization_id = NEW.organization_id
              AND membership.id = NEW.actor_membership_id
              AND session.id = NEW.authentication_session_id
              AND session.is_privileged = TRUE
              AND session.revoked_at IS NULL
              AND session.mfa_verified_at IS NOT NULL
{$factorEvidence}
              AND session.mfa_verified_at <= NEW.effective_at
              AND session.idle_expires_at > NEW.effective_at
              AND session.absolute_expires_at > NEW.effective_at
        ) THEN
            RAISE EXCEPTION 'Owner emergency evidence is not bound to the accountable actor session'
                USING ERRCODE = '23514';
        END IF;
    ELSIF NEW.actor_type = 'platform_user' THEN
        expected_action := 'curriculum.publication.' || NEW.event_type;

        IF NOT EXISTS (
            SELECT 1
            FROM authentication_sessions session
            WHERE session.id = NEW.authentication_session_id
              AND session.user_id = NEW.platform_actor_user_id
              AND session.is_privileged = TRUE
              AND session.revoked_at IS NULL
              AND session.mfa_verified_at IS NOT NULL
{$factorEvidence}
              AND session.mfa_verified_at <= NEW.effective_at
              AND session.idle_expires_at > NEW.effective_at
              AND session.absolute_expires_at > NEW.effective_at
        ) OR NOT EXISTS (
            SELECT 1
            FROM audit_events audit
            WHERE audit.id = NEW.platform_audit_event_id
              AND audit.scope_kind = 'platform'
              AND audit.organization_id IS NULL
              AND audit.target_organization_id = NEW.organization_id
              AND audit.actor_user_id = NEW.platform_actor_user_id
              AND audit.actor_membership_id IS NULL
              AND audit.capability_key = 'content.emergency-suspend'
              AND audit.reason_key = NEW.reason_key
              AND audit.action = expected_action
              AND audit.subject_type = 'program'
              AND audit.subject_id = NEW.program_id
              AND audit.correlation_id = NEW.correlation_id
              AND audit.occurred_at <= NEW.effective_at
        ) THEN
            RAISE EXCEPTION 'Platform emergency evidence is not bound to the exact authorization audit'
                USING ERRCODE = '23514';
        END IF;
    ELSE
        RAISE EXCEPTION 'Emergency evidence has an unsupported actor type'
            USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
\$\$;
SQL);
    }
};

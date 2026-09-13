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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 64)->nullable()->unique()->after('display_name');
            $table->timestampTz('username_established_at')->nullable()->after('username');
            $table->unsignedInteger('credential_version')->default(0)->after('status');
            $table->timestampTz('credential_cutover_at')->nullable()->after('credential_version');
        });

        Schema::create('password_credentials', function (Blueprint $table): void {
            $table->foreignUlid('user_id')->primary()
                ->constrained('users')->restrictOnDelete();
            $table->string('password_hash', 255);
            $table->string('algorithm', 16)->default('argon2id');
            $table->unsignedInteger('policy_version');
            $table->unsignedInteger('credential_version');
            $table->timestampTz('established_at');
            $table->timestampTz('changed_at');
            $table->timestampTz('compromised_at')->nullable();
            $table->timestampsTz();
        });

        Schema::table('authentication_sessions', function (Blueprint $table): void {
            $table->timestampTz('password_confirmed_at')->nullable()
                ->after('mfa_verified_scope');
            $table->unsignedInteger('password_confirmed_credential_version')
                ->nullable()->after('password_confirmed_at');
        });

        Schema::create('password_recovery_challenges', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()
                ->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('recovery_identity_id')->nullable()
                ->constrained('user_identities')->nullOnDelete();
            $table->char('lookup_digest', 64);
            $table->char('token_digest', 64);
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->string('delivery_outcome', 24);
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampsTz();

            $table->index(['lookup_digest', 'created_at']);
            $table->index(['user_id', 'status', 'expires_at']);
        });

        Schema::create('privileged_password_recovery_cases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('target_user_id')
                ->constrained('users')->restrictOnDelete();
            $table->foreignUlid('requested_by_user_id')
                ->constrained('users')->restrictOnDelete();
            $table->string('reason_key', 80);
            $table->string('evidence_reference', 160);
            $table->char('evidence_digest', 64);
            $table->string('status', 32)->default('pending_approval');
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('requested_at');
            $table->timestampTz('not_before_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['target_user_id', 'status']);
            $table->index(['status', 'not_before_at', 'expires_at']);
        });

        Schema::create('privileged_password_recovery_approvals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('case_id')
                ->constrained('privileged_password_recovery_cases')->restrictOnDelete();
            $table->foreignUlid('actor_user_id')
                ->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('case_version');
            $table->char('evidence_digest', 64);
            $table->timestampTz('approved_at');
            $table->timestampsTz();

            $table->unique(['case_id', 'actor_user_id']);
            $table->index(['actor_user_id', 'approved_at']);
        });

        Schema::create('password_security_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('event_type', 64);
            $table->string('outcome', 32);
            $table->char('request_digest', 64);
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['user_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
        });

        Schema::create('password_authentication_cutovers', function (Blueprint $table): void {
            $table->string('scope_key', 32)->primary();
            $table->string('status', 24);
            $table->char('manifest_digest', 64);
            $table->char('evidence_digest', 64);
            $table->unsignedInteger('active_user_count');
            $table->unsignedInteger('credential_ready_count');
            $table->unsignedInteger('excluded_user_count');
            $table->timestampTz('completed_at');
            $table->timestampsTz();
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            "ALTER TABLE users ADD CONSTRAINT users_username_contract_check CHECK ((username IS NULL AND username_established_at IS NULL) OR (username IS NOT NULL AND username_established_at IS NOT NULL AND username = lower(username) AND username ~ '^[a-z][a-z0-9._-]{3,63}$'))",
        );
        DB::statement(
            'ALTER TABLE users ADD CONSTRAINT users_credential_version_check CHECK (credential_version >= 0)',
        );
        DB::statement(
            'ALTER TABLE password_credentials ADD CONSTRAINT password_credentials_contract_check CHECK (algorithm = \'argon2id\' AND policy_version > 0 AND credential_version > 0)',
        );
        DB::statement(
            "ALTER TABLE password_recovery_challenges ADD CONSTRAINT password_recovery_contract_check CHECK (status IN ('pending', 'consumed', 'expired', 'superseded') AND failed_attempts BETWEEN 0 AND 5 AND ((status = 'consumed' AND consumed_at IS NOT NULL) OR status <> 'consumed'))",
        );
        DB::statement(
            "ALTER TABLE password_authentication_cutovers ADD CONSTRAINT password_authentication_cutovers_contract_check CHECK (scope_key = 'global' AND status = 'password_only' AND active_user_count = credential_ready_count + excluded_user_count)",
        );
        DB::statement(
            "CREATE UNIQUE INDEX password_recovery_one_pending_per_user ON password_recovery_challenges (user_id) WHERE user_id IS NOT NULL AND status = 'pending'",
        );
        DB::statement(
            "ALTER TABLE privileged_password_recovery_cases ADD CONSTRAINT privileged_password_recovery_case_contract_check CHECK (status IN ('pending_approval', 'waiting_delay', 'completed', 'expired', 'cancelled') AND version > 0 AND not_before_at > requested_at AND expires_at > not_before_at AND ((status = 'completed' AND completed_at IS NOT NULL) OR status <> 'completed'))",
        );
        DB::statement(
            'ALTER TABLE authentication_sessions DROP CONSTRAINT IF EXISTS auth_sessions_mfa_assertion_check',
        );
        DB::statement(<<<'SQL'
ALTER TABLE authentication_sessions ADD CONSTRAINT auth_sessions_authentication_freshness_check CHECK (
    ((password_confirmed_at IS NULL AND password_confirmed_credential_version IS NULL)
      OR (is_privileged = TRUE AND password_confirmed_at IS NOT NULL AND password_confirmed_credential_version > 0))
    AND
    ((mfa_verified_at IS NULL AND mfa_factor_id IS NULL AND mfa_verified_method IS NULL AND mfa_verified_scope IS NULL)
      OR (is_privileged = TRUE AND mfa_verified_at IS NOT NULL AND mfa_factor_id IS NOT NULL
          AND ((mfa_verified_method = 'totp' AND mfa_verified_scope = 'privileged_actions')
            OR (mfa_verified_method = 'recovery_code' AND mfa_verified_scope = 'factor_replacement'))))
)
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION password_auth_reject_append_only_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION '% is append-only', TG_TABLE_NAME USING ERRCODE = '55000';
END;
$$;

CREATE TRIGGER privileged_password_recovery_approvals_append_only
BEFORE UPDATE OR DELETE ON privileged_password_recovery_approvals
FOR EACH ROW EXECUTE FUNCTION password_auth_reject_append_only_mutation();

CREATE TRIGGER password_security_events_append_only
BEFORE UPDATE OR DELETE ON password_security_events
FOR EACH ROW EXECUTE FUNCTION password_auth_reject_append_only_mutation();

CREATE TRIGGER password_authentication_cutovers_append_only
BEFORE UPDATE OR DELETE ON password_authentication_cutovers
FOR EACH ROW EXECUTE FUNCTION password_auth_reject_append_only_mutation();

CREATE OR REPLACE FUNCTION password_auth_validate_user_credential()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.credential_cutover_at IS NOT NULL AND (
        NEW.username IS NULL OR NOT EXISTS (
            SELECT 1 FROM password_credentials credential
            WHERE credential.user_id = NEW.id
              AND credential.credential_version = NEW.credential_version
        )
    ) THEN
        RAISE EXCEPTION 'credential cutover requires a matching active password credential' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;

CREATE CONSTRAINT TRIGGER users_password_credential_contract
AFTER INSERT OR UPDATE OF username, credential_version, credential_cutover_at ON users
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION password_auth_validate_user_credential();

CREATE OR REPLACE FUNCTION password_auth_validate_credential_version()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM users actor
        WHERE actor.id = NEW.user_id
          AND actor.username IS NOT NULL
          AND actor.credential_version = NEW.credential_version
    ) THEN
        RAISE EXCEPTION 'password credential version must match its user' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;

CREATE CONSTRAINT TRIGGER password_credentials_user_version_contract
AFTER INSERT OR UPDATE OF credential_version ON password_credentials
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION password_auth_validate_credential_version();
SQL);

        $this->replaceEmergencyEvidenceFunction();
    }

    public function down(): void
    {
        if (
            Schema::hasTable('password_authentication_cutovers')
            && DB::table('password_authentication_cutovers')
                ->where('scope_key', 'global')
                ->where('status', 'password_only')
                ->exists()
        ) {
            throw new RuntimeException(
                'Password authentication cannot be rolled back after verified cutover.',
            );
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->restoreLegacyEmergencyEvidenceFunction();
            DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS password_security_events_append_only ON password_security_events;
DROP TRIGGER IF EXISTS privileged_password_recovery_approvals_append_only ON privileged_password_recovery_approvals;
DROP TRIGGER IF EXISTS password_authentication_cutovers_append_only ON password_authentication_cutovers;
DROP TRIGGER IF EXISTS users_password_credential_contract ON users;
DROP TRIGGER IF EXISTS password_credentials_user_version_contract ON password_credentials;
DROP FUNCTION IF EXISTS password_auth_validate_user_credential();
DROP FUNCTION IF EXISTS password_auth_validate_credential_version();
DROP FUNCTION IF EXISTS password_auth_reject_append_only_mutation();
ALTER TABLE authentication_sessions DROP CONSTRAINT IF EXISTS auth_sessions_authentication_freshness_check;
ALTER TABLE users DROP CONSTRAINT IF EXISTS users_username_contract_check;
ALTER TABLE users DROP CONSTRAINT IF EXISTS users_credential_version_check;
SQL);
        }

        Schema::dropIfExists('password_authentication_cutovers');
        Schema::dropIfExists('password_security_events');
        Schema::dropIfExists('privileged_password_recovery_approvals');
        Schema::dropIfExists('privileged_password_recovery_cases');
        Schema::dropIfExists('password_recovery_challenges');

        Schema::table('authentication_sessions', function (Blueprint $table): void {
            $table->dropColumn([
                'password_confirmed_at',
                'password_confirmed_credential_version',
            ]);
        });
        Schema::dropIfExists('password_credentials');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn([
                'username',
                'username_established_at',
                'credential_version',
                'credential_cutover_at',
            ]);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE authentication_sessions ADD CONSTRAINT auth_sessions_mfa_assertion_check CHECK ((mfa_verified_at IS NULL AND mfa_factor_id IS NULL AND mfa_verified_method IS NULL AND mfa_verified_scope IS NULL) OR (is_privileged = TRUE AND mfa_verified_at IS NOT NULL AND mfa_factor_id IS NOT NULL AND ((mfa_verified_method = 'totp' AND mfa_verified_scope = 'privileged_actions') OR (mfa_verified_method = 'recovery_code' AND mfa_verified_scope = 'factor_replacement'))))",
            );
        }
    }

    private function replaceEmergencyEvidenceFunction(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION curriculum_validate_emergency_evidence()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    expected_action text;
BEGIN
    IF NEW.event_type NOT IN ('suspended', 'resumed') THEN RETURN NEW; END IF;

    IF NEW.actor_type = 'owner_membership' THEN
        IF NOT EXISTS (
            SELECT 1 FROM memberships membership
            JOIN authentication_sessions session ON session.user_id = membership.user_id
            JOIN users actor ON actor.id = session.user_id
            WHERE membership.organization_id = NEW.organization_id
              AND membership.id = NEW.actor_membership_id
              AND session.id = NEW.authentication_session_id
              AND session.is_privileged = TRUE AND session.revoked_at IS NULL
              AND session.idle_expires_at > NEW.effective_at
              AND session.absolute_expires_at > NEW.effective_at
              AND (
                (session.password_confirmed_at IS NOT NULL
                  AND session.password_confirmed_at <= NEW.effective_at
                  AND session.password_confirmed_credential_version = actor.credential_version)
                OR
                (session.mfa_verified_at IS NOT NULL
                  AND session.mfa_factor_id IS NOT NULL
                  AND session.mfa_verified_method = 'totp'
                  AND session.mfa_verified_scope = 'privileged_actions'
                  AND session.mfa_verified_at <= NEW.effective_at)
              )
        ) THEN RAISE EXCEPTION 'Owner emergency evidence is not bound to the accountable actor session' USING ERRCODE = '23514'; END IF;
    ELSIF NEW.actor_type = 'platform_user' THEN
        expected_action := 'curriculum.publication.' || NEW.event_type;
        IF NOT EXISTS (
            SELECT 1 FROM authentication_sessions session
            JOIN users actor ON actor.id = session.user_id
            WHERE session.id = NEW.authentication_session_id
              AND session.user_id = NEW.platform_actor_user_id
              AND session.is_privileged = TRUE AND session.revoked_at IS NULL
              AND session.idle_expires_at > NEW.effective_at
              AND session.absolute_expires_at > NEW.effective_at
              AND (
                (session.password_confirmed_at IS NOT NULL
                  AND session.password_confirmed_at <= NEW.effective_at
                  AND session.password_confirmed_credential_version = actor.credential_version)
                OR
                (session.mfa_verified_at IS NOT NULL
                  AND session.mfa_factor_id IS NOT NULL
                  AND session.mfa_verified_method = 'totp'
                  AND session.mfa_verified_scope = 'privileged_actions'
                  AND session.mfa_verified_at <= NEW.effective_at)
              )
        ) OR NOT EXISTS (
            SELECT 1 FROM audit_events audit
            WHERE audit.id = NEW.platform_audit_event_id
              AND audit.scope_kind = 'platform' AND audit.organization_id IS NULL
              AND audit.target_organization_id = NEW.organization_id
              AND audit.actor_user_id = NEW.platform_actor_user_id
              AND audit.actor_membership_id IS NULL
              AND audit.capability_key = 'content.emergency-suspend'
              AND audit.reason_key = NEW.reason_key AND audit.action = expected_action
              AND audit.subject_type = 'program' AND audit.subject_id = NEW.program_id
              AND audit.correlation_id = NEW.correlation_id
              AND audit.occurred_at <= NEW.effective_at
        ) THEN RAISE EXCEPTION 'Platform emergency evidence is not bound to the exact authorization audit' USING ERRCODE = '23514'; END IF;
    ELSE
        RAISE EXCEPTION 'Emergency evidence has an unsupported actor type' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;
SQL);
    }

    private function restoreLegacyEmergencyEvidenceFunction(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION curriculum_validate_emergency_evidence()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE expected_action text;
BEGIN
    IF NEW.event_type NOT IN ('suspended', 'resumed') THEN RETURN NEW; END IF;
    IF NEW.actor_type = 'owner_membership' THEN
        IF NOT EXISTS (
            SELECT 1 FROM memberships membership
            JOIN authentication_sessions session ON session.user_id = membership.user_id
            WHERE membership.organization_id = NEW.organization_id
              AND membership.id = NEW.actor_membership_id
              AND session.id = NEW.authentication_session_id
              AND session.is_privileged = TRUE AND session.revoked_at IS NULL
              AND session.mfa_verified_at IS NOT NULL
              AND session.mfa_factor_id IS NOT NULL
              AND session.mfa_verified_method = 'totp'
              AND session.mfa_verified_scope = 'privileged_actions'
              AND session.mfa_verified_at <= NEW.effective_at
              AND session.idle_expires_at > NEW.effective_at
              AND session.absolute_expires_at > NEW.effective_at
        ) THEN RAISE EXCEPTION 'Owner emergency evidence is not bound to the accountable actor session' USING ERRCODE = '23514'; END IF;
    ELSIF NEW.actor_type = 'platform_user' THEN
        expected_action := 'curriculum.publication.' || NEW.event_type;
        IF NOT EXISTS (
            SELECT 1 FROM authentication_sessions session
            WHERE session.id = NEW.authentication_session_id
              AND session.user_id = NEW.platform_actor_user_id
              AND session.is_privileged = TRUE AND session.revoked_at IS NULL
              AND session.mfa_verified_at IS NOT NULL
              AND session.mfa_factor_id IS NOT NULL
              AND session.mfa_verified_method = 'totp'
              AND session.mfa_verified_scope = 'privileged_actions'
              AND session.mfa_verified_at <= NEW.effective_at
              AND session.idle_expires_at > NEW.effective_at
              AND session.absolute_expires_at > NEW.effective_at
        ) OR NOT EXISTS (
            SELECT 1 FROM audit_events audit
            WHERE audit.id = NEW.platform_audit_event_id
              AND audit.scope_kind = 'platform' AND audit.organization_id IS NULL
              AND audit.target_organization_id = NEW.organization_id
              AND audit.actor_user_id = NEW.platform_actor_user_id
              AND audit.actor_membership_id IS NULL
              AND audit.capability_key = 'content.emergency-suspend'
              AND audit.reason_key = NEW.reason_key
              AND audit.action = expected_action
              AND audit.subject_type = 'program' AND audit.subject_id = NEW.program_id
              AND audit.correlation_id = NEW.correlation_id
              AND audit.occurred_at <= NEW.effective_at
        ) THEN RAISE EXCEPTION 'Platform emergency evidence is not bound to the exact authorization audit' USING ERRCODE = '23514'; END IF;
    ELSE
        RAISE EXCEPTION 'Emergency evidence has an unsupported actor type' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;
SQL);
    }
};

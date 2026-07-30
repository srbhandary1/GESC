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
        Schema::create('ai_personas', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->string('key', 100);
            $table->string('status', 32);
            $table->string('created_by_attribution', 120);
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'key'],
                'ai_personas_key_unique',
            );
        });

        Schema::create('persona_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('persona_id');
            $table->unsignedInteger('version');
            $table->string('display_name', 120);
            $table->string('disclosure', 240);
            $table->string('style_code', 100);
            $table->char('definition_hash', 64);
            $table->string('review_status', 40);
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'persona_id', 'version'],
                'persona_versions_version_unique',
            );
            $table->unique(
                ['organization_id', 'id', 'persona_id'],
                'persona_versions_identity_unique',
            );
            $table->foreign(
                ['organization_id', 'persona_id'],
                'persona_versions_persona_fk',
            )->references(['organization_id', 'id'])
                ->on('ai_personas')
                ->restrictOnDelete();
        });

        Schema::create('roleplay_scenarios', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->string('key', 100);
            $table->string('mode', 24);
            $table->string('status', 32);
            $table->string('created_by_attribution', 120);
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'key'],
                'roleplay_scenarios_key_unique',
            );
        });

        Schema::create('scenario_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('scenario_id');
            $table->unsignedInteger('version');
            $table->string('topic_code', 100);
            $table->string('response_code', 100);
            $table->char('definition_hash', 64);
            $table->string('review_status', 40);
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'scenario_id', 'version'],
                'scenario_versions_version_unique',
            );
            $table->unique(
                ['organization_id', 'id', 'scenario_id'],
                'scenario_versions_identity_unique',
            );
            $table->foreign(
                ['organization_id', 'scenario_id'],
                'scenario_versions_scenario_fk',
            )->references(['organization_id', 'id'])
                ->on('roleplay_scenarios')
                ->restrictOnDelete();
        });

        Schema::create('conversation_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('learner_membership_id');
            $table->ulid('persona_id');
            $table->ulid('persona_version_id');
            $table->char('persona_definition_hash', 64);
            $table->ulid('scenario_id');
            $table->ulid('scenario_version_id');
            $table->char('scenario_definition_hash', 64);
            $table->string('mode', 24);
            $table->string('coaching_mode', 24);
            $table->string('status', 24);
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'id', 'learner_membership_id'],
                'conversation_sessions_identity_unique',
            );
            $table->index(
                [
                    'organization_id',
                    'learner_membership_id',
                    'started_at',
                ],
                'conversation_sessions_learner_index',
            );
            $table->foreign(
                ['organization_id', 'learner_membership_id'],
                'conversation_sessions_membership_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'persona_version_id',
                    'persona_id',
                ],
                'conversation_sessions_persona_version_fk',
            )->references([
                'organization_id',
                'id',
                'persona_id',
            ])->on('persona_versions')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'scenario_version_id',
                    'scenario_id',
                ],
                'conversation_sessions_scenario_version_fk',
            )->references([
                'organization_id',
                'id',
                'scenario_id',
            ])->on('scenario_versions')
                ->restrictOnDelete();
        });

        Schema::create('conversation_turns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('conversation_session_id');
            $table->ulid('learner_membership_id');
            $table->unsignedInteger('turn_number');
            $table->string('status', 32);
            $table->char('utterance_digest', 64);
            $table->char('transcript_digest', 64)->nullable();
            $table->char('assistant_reply_digest', 64)->nullable();
            $table->string('failed_stage', 24)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestampTz('accepted_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                [
                    'organization_id',
                    'id',
                    'conversation_session_id',
                    'learner_membership_id',
                ],
                'conversation_turns_identity_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'conversation_session_id',
                    'turn_number',
                ],
                'conversation_turns_number_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'conversation_session_id',
                    'learner_membership_id',
                ],
                'conversation_turns_session_fk',
            )->references([
                'organization_id',
                'id',
                'learner_membership_id',
            ])->on('conversation_sessions')
                ->restrictOnDelete();
        });

        Schema::create('conversation_turn_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('conversation_turn_id');
            $table->ulid('conversation_session_id');
            $table->ulid('learner_membership_id');
            $table->unsignedInteger('sequence');
            $table->string('state', 32);
            $table->string('event_code', 64);
            $table->string('provider_stage', 24)->nullable();
            $table->unsignedSmallInteger('provider_attempt')->nullable();
            $table->timestampTz('recorded_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                [
                    'organization_id',
                    'conversation_turn_id',
                    'sequence',
                ],
                'conversation_turn_events_sequence_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'conversation_turn_id',
                    'conversation_session_id',
                    'learner_membership_id',
                ],
                'conversation_turn_events_turn_fk',
            )->references([
                'organization_id',
                'id',
                'conversation_session_id',
                'learner_membership_id',
            ])->on('conversation_turns')
                ->restrictOnDelete();
        });

        Schema::create('ai_cost_ledger', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('conversation_turn_id');
            $table->ulid('conversation_session_id');
            $table->ulid('learner_membership_id');
            $table->string('provider_stage', 24);
            $table->string('provider_key', 64);
            $table->string('model_version', 100);
            $table->unsignedSmallInteger('provider_attempt');
            $table->unsignedInteger('latency_ms');
            $table->unsignedInteger('input_units');
            $table->unsignedInteger('output_units');
            $table->unsignedBigInteger('estimated_cost_microunits');
            $table->string('result_status', 24);
            $table->timestampTz('recorded_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                [
                    'organization_id',
                    'conversation_turn_id',
                    'provider_stage',
                    'provider_attempt',
                ],
                'ai_cost_ledger_attempt_unique',
            );
            $table->index(
                [
                    'organization_id',
                    'learner_membership_id',
                    'recorded_at',
                ],
                'ai_cost_ledger_learner_index',
            );
            $table->foreign(
                [
                    'organization_id',
                    'conversation_turn_id',
                    'conversation_session_id',
                    'learner_membership_id',
                ],
                'ai_cost_ledger_turn_fk',
            )->references([
                'organization_id',
                'id',
                'conversation_session_id',
                'learner_membership_id',
            ])->on('conversation_turns')
                ->restrictOnDelete();
        });

        Schema::create('conversation_commands', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->string('operation', 64);
            $table->string('aggregate_type', 48);
            $table->ulid('aggregate_id');
            $table->string('idempotency_key', 128);
            $table->char('request_fingerprint', 64);
            $table->string('result_type', 48);
            $table->ulid('result_id');
            $table->ulid('actor_membership_id');
            $table->foreignUlid('actor_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestampTz('completed_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                [
                    'organization_id',
                    'operation',
                    'aggregate_type',
                    'aggregate_id',
                    'idempotency_key',
                ],
                'conversation_commands_idempotency_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'actor_user_id',
                    'actor_membership_id',
                ],
                'conversation_commands_actor_fk',
            )->references(['organization_id', 'user_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE ai_personas ADD CONSTRAINT ai_personas_values_check CHECK (status = 'development_only' AND key ~ '^[a-z0-9][a-z0-9._-]{0,99}$')",
            );
            DB::statement(
                "ALTER TABLE persona_versions ADD CONSTRAINT persona_versions_values_check CHECK (version > 0 AND style_code ~ '^[a-z0-9][a-z0-9._-]{0,99}$' AND definition_hash ~ '^[0-9a-f]{64}$' AND review_status = 'synthetic_unreviewed')",
            );
            DB::statement(
                "ALTER TABLE roleplay_scenarios ADD CONSTRAINT roleplay_scenarios_values_check CHECK (mode = 'friend' AND status = 'development_only' AND key ~ '^[a-z0-9][a-z0-9._-]{0,99}$')",
            );
            DB::statement(
                "ALTER TABLE scenario_versions ADD CONSTRAINT scenario_versions_values_check CHECK (version > 0 AND topic_code ~ '^[a-z0-9][a-z0-9._-]{0,99}$' AND response_code ~ '^[a-z0-9][a-z0-9._-]{0,99}$' AND definition_hash ~ '^[0-9a-f]{64}$' AND review_status = 'synthetic_unreviewed')",
            );
            DB::statement(
                "ALTER TABLE conversation_sessions ADD CONSTRAINT conversation_sessions_values_check CHECK (persona_definition_hash ~ '^[0-9a-f]{64}$' AND scenario_definition_hash ~ '^[0-9a-f]{64}$' AND mode = 'friend' AND coaching_mode = 'gentle' AND status = 'active' AND ended_at IS NULL)",
            );
            DB::statement(
                "ALTER TABLE conversation_turns ADD CONSTRAINT conversation_turns_values_check CHECK (turn_number > 0 AND utterance_digest ~ '^[0-9a-f]{64}$' AND (transcript_digest IS NULL OR transcript_digest ~ '^[0-9a-f]{64}$') AND (assistant_reply_digest IS NULL OR assistant_reply_digest ~ '^[0-9a-f]{64}$') AND status IN ('accepted', 'transcribing', 'generating_reply', 'synthesizing', 'completed', 'failed') AND ((status = 'completed' AND transcript_digest IS NOT NULL AND assistant_reply_digest IS NOT NULL AND completed_at IS NOT NULL AND failed_stage IS NULL AND failure_code IS NULL) OR (status = 'failed' AND completed_at IS NOT NULL AND failed_stage IN ('stt', 'model', 'tts') AND failure_code IS NOT NULL) OR (status NOT IN ('completed', 'failed') AND completed_at IS NULL AND failed_stage IS NULL AND failure_code IS NULL)))",
            );
            DB::statement(
                "ALTER TABLE conversation_turn_events ADD CONSTRAINT conversation_turn_events_values_check CHECK (sequence > 0 AND state IN ('accepted', 'transcribing', 'generating_reply', 'synthesizing', 'completed', 'failed') AND event_code IN ('turn.accepted', 'turn.transcribing', 'turn.generating_reply', 'turn.synthesizing', 'turn.provider_retrying', 'turn.completed', 'turn.failed') AND ((provider_stage IS NULL AND provider_attempt IS NULL) OR (provider_stage IN ('stt', 'model', 'tts') AND provider_attempt > 0)))",
            );
            DB::statement(
                "ALTER TABLE ai_cost_ledger ADD CONSTRAINT ai_cost_ledger_values_check CHECK (provider_stage IN ('stt', 'model', 'tts') AND provider_key = 'deterministic-fake' AND model_version ~ '^fake-' AND provider_attempt > 0 AND estimated_cost_microunits = 0 AND result_status IN ('succeeded', 'failed'))",
            );

            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION conversation_reject_immutable_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'Conversation version, event, usage and command records are immutable'
        USING ERRCODE = '55000';
END;
$$;

CREATE OR REPLACE FUNCTION conversation_enforce_turn_transition()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF OLD.id IS DISTINCT FROM NEW.id
        OR OLD.organization_id IS DISTINCT FROM NEW.organization_id
        OR OLD.conversation_session_id IS DISTINCT FROM NEW.conversation_session_id
        OR OLD.learner_membership_id IS DISTINCT FROM NEW.learner_membership_id
        OR OLD.turn_number IS DISTINCT FROM NEW.turn_number
        OR OLD.utterance_digest IS DISTINCT FROM NEW.utterance_digest
        OR OLD.accepted_at IS DISTINCT FROM NEW.accepted_at
        OR OLD.created_at IS DISTINCT FROM NEW.created_at
    THEN
        RAISE EXCEPTION 'Conversation turn identity is immutable'
            USING ERRCODE = '55000';
    END IF;

    IF NOT (
        (OLD.status = 'accepted' AND NEW.status IN ('transcribing', 'failed'))
        OR (OLD.status = 'transcribing' AND NEW.status IN ('generating_reply', 'failed'))
        OR (OLD.status = 'generating_reply' AND NEW.status IN ('synthesizing', 'failed'))
        OR (OLD.status = 'synthesizing' AND NEW.status IN ('completed', 'failed'))
    ) THEN
        RAISE EXCEPTION 'Conversation turn transition is not allowed'
            USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER persona_versions_immutable
BEFORE UPDATE OR DELETE ON persona_versions
FOR EACH ROW EXECUTE FUNCTION conversation_reject_immutable_mutation();

CREATE TRIGGER scenario_versions_immutable
BEFORE UPDATE OR DELETE ON scenario_versions
FOR EACH ROW EXECUTE FUNCTION conversation_reject_immutable_mutation();

CREATE TRIGGER conversation_sessions_immutable
BEFORE UPDATE OR DELETE ON conversation_sessions
FOR EACH ROW EXECUTE FUNCTION conversation_reject_immutable_mutation();

CREATE TRIGGER conversation_turns_transition
BEFORE UPDATE ON conversation_turns
FOR EACH ROW EXECUTE FUNCTION conversation_enforce_turn_transition();

CREATE TRIGGER conversation_turns_no_delete
BEFORE DELETE ON conversation_turns
FOR EACH ROW EXECUTE FUNCTION conversation_reject_immutable_mutation();

CREATE TRIGGER conversation_turn_events_immutable
BEFORE UPDATE OR DELETE ON conversation_turn_events
FOR EACH ROW EXECUTE FUNCTION conversation_reject_immutable_mutation();

CREATE TRIGGER ai_cost_ledger_immutable
BEFORE UPDATE OR DELETE ON ai_cost_ledger
FOR EACH ROW EXECUTE FUNCTION conversation_reject_immutable_mutation();

CREATE TRIGGER conversation_commands_immutable
BEFORE UPDATE OR DELETE ON conversation_commands
FOR EACH ROW EXECUTE FUNCTION conversation_reject_immutable_mutation();
SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
DROP FUNCTION IF EXISTS conversation_enforce_turn_transition() CASCADE;
DROP FUNCTION IF EXISTS conversation_reject_immutable_mutation() CASCADE;
SQL);
        }

        Schema::dropIfExists('conversation_commands');
        Schema::dropIfExists('ai_cost_ledger');
        Schema::dropIfExists('conversation_turn_events');
        Schema::dropIfExists('conversation_turns');
        Schema::dropIfExists('conversation_sessions');
        Schema::dropIfExists('scenario_versions');
        Schema::dropIfExists('roleplay_scenarios');
        Schema::dropIfExists('persona_versions');
        Schema::dropIfExists('ai_personas');
    }
};

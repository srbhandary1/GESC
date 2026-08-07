<?php

declare(strict_types=1);

use App\Conversation\Support\ConversationUiLocales;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenario_objectives', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('scenario_version_id');
            $table->string('objective_key', 100);
            $table->string('title', 160);
            $table->string('completion_criteria_code', 100);
            $table->unsignedSmallInteger('sort_order');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['scenario_version_id', 'objective_key'],
                'scenario_objectives_key_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'scenario_version_id',
                    'objective_key',
                ],
                'scenario_objectives_org_key_unique',
            );
            $table->index(
                [
                    'organization_id',
                    'scenario_version_id',
                    'sort_order',
                ],
                'scenario_objectives_version_order_index',
            );
            $table->foreign(
                ['organization_id', 'scenario_version_id'],
                'scenario_objectives_scenario_version_fk',
            )->references(['organization_id', 'id'])
                ->on('scenario_versions')
                ->restrictOnDelete();
        });

        Schema::create('conversation_session_stage_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('conversation_session_id');
            $table->ulid('learner_membership_id');
            $table->string('from_stage', 24)->nullable();
            $table->string('to_stage', 24);
            $table->unsignedInteger('sequence');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['conversation_session_id', 'sequence'],
                'conversation_session_stage_events_sequence_unique',
            );
            $table->index(
                [
                    'organization_id',
                    'conversation_session_id',
                    'sequence',
                ],
                'conversation_session_stage_events_session_index',
            );
            $table->foreign(
                [
                    'organization_id',
                    'conversation_session_id',
                    'learner_membership_id',
                ],
                'conversation_session_stage_events_session_fk',
            )->references([
                'organization_id',
                'id',
                'learner_membership_id',
            ])->on('conversation_sessions')
                ->restrictOnDelete();
        });

        Schema::create('roleplay_feedback_evidence', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('conversation_session_id');
            $table->ulid('learner_membership_id');
            $table->ulid('scenario_id');
            $table->ulid('scenario_version_id');
            $table->ulid('scenario_objective_id');
            $table->char('evidence_digest', 64);
            $table->string('completion_state', 16);
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                [
                    'organization_id',
                    'conversation_session_id',
                    'scenario_objective_id',
                ],
                'roleplay_feedback_evidence_objective_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'conversation_session_id',
                    'learner_membership_id',
                ],
                'roleplay_feedback_evidence_session_fk',
            )->references([
                'organization_id',
                'id',
                'learner_membership_id',
            ])->on('conversation_sessions')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'scenario_version_id',
                    'scenario_id',
                ],
                'roleplay_feedback_evidence_scenario_version_fk',
            )->references([
                'organization_id',
                'id',
                'scenario_id',
            ])->on('scenario_versions')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'scenario_objective_id',
                ],
                'roleplay_feedback_evidence_objective_fk',
            )->references(['organization_id', 'id'])
                ->on('scenario_objectives')
                ->restrictOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            $locales = "'".implode("','", ConversationUiLocales::all())."'";

            DB::statement(
                'ALTER TABLE roleplay_scenarios DROP CONSTRAINT IF EXISTS roleplay_scenarios_values_check',
            );
            DB::statement(
                "ALTER TABLE roleplay_scenarios ADD CONSTRAINT roleplay_scenarios_values_check CHECK (mode IN ('friend', 'roleplay') AND status = 'development_only' AND key ~ '^[a-z0-9][a-z0-9._-]{0,99}$')",
            );

            DB::statement(
                'ALTER TABLE conversation_sessions DROP CONSTRAINT IF EXISTS conversation_sessions_values_check',
            );
            DB::statement(
                "ALTER TABLE conversation_sessions ADD CONSTRAINT conversation_sessions_values_check CHECK (persona_definition_hash ~ '^[0-9a-f]{64}$' AND scenario_definition_hash ~ '^[0-9a-f]{64}$' AND mode IN ('friend', 'roleplay') AND coaching_mode IN ('gentle', 'tutor', 'fluency') AND ui_locale IN ({$locales}) AND status = 'active' AND ended_at IS NULL)",
            );

            DB::statement(
                "ALTER TABLE scenario_objectives ADD CONSTRAINT scenario_objectives_values_check CHECK (objective_key ~ '^[a-z0-9][a-z0-9._-]{0,99}$' AND completion_criteria_code ~ '^[a-z0-9][a-z0-9._-]{0,99}$' AND sort_order > 0 AND char_length(title) BETWEEN 1 AND 160)",
            );
            DB::statement(
                "ALTER TABLE conversation_session_stage_events ADD CONSTRAINT conversation_session_stage_events_values_check CHECK (sequence > 0 AND to_stage IN ('observe', 'rehearse', 'perform', 'reflect') AND (from_stage IS NULL OR from_stage IN ('observe', 'rehearse', 'perform', 'reflect')))",
            );
            DB::statement(
                "ALTER TABLE roleplay_feedback_evidence ADD CONSTRAINT roleplay_feedback_evidence_values_check CHECK (evidence_digest ~ '^[0-9a-f]{64}$' AND completion_state IN ('met', 'unmet', 'skipped'))",
            );

            DB::unprepared(<<<'SQL'
CREATE TRIGGER scenario_objectives_immutable
BEFORE UPDATE OR DELETE ON scenario_objectives
FOR EACH ROW EXECUTE FUNCTION conversation_reject_immutable_mutation();

CREATE TRIGGER conversation_session_stage_events_immutable
BEFORE UPDATE OR DELETE ON conversation_session_stage_events
FOR EACH ROW EXECUTE FUNCTION conversation_reject_immutable_mutation();

CREATE TRIGGER roleplay_feedback_evidence_immutable
BEFORE UPDATE OR DELETE ON roleplay_feedback_evidence
FOR EACH ROW EXECUTE FUNCTION conversation_reject_immutable_mutation();
SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS roleplay_feedback_evidence_immutable ON roleplay_feedback_evidence;
DROP TRIGGER IF EXISTS conversation_session_stage_events_immutable ON conversation_session_stage_events;
DROP TRIGGER IF EXISTS scenario_objectives_immutable ON scenario_objectives;
SQL);

            $locales = "'".implode("','", ConversationUiLocales::all())."'";

            DB::statement(
                'ALTER TABLE conversation_sessions DROP CONSTRAINT IF EXISTS conversation_sessions_values_check',
            );
            DB::statement(
                "ALTER TABLE conversation_sessions ADD CONSTRAINT conversation_sessions_values_check CHECK (persona_definition_hash ~ '^[0-9a-f]{64}$' AND scenario_definition_hash ~ '^[0-9a-f]{64}$' AND mode = 'friend' AND coaching_mode IN ('gentle', 'tutor', 'fluency') AND ui_locale IN ({$locales}) AND status = 'active' AND ended_at IS NULL)",
            );

            DB::statement(
                'ALTER TABLE roleplay_scenarios DROP CONSTRAINT IF EXISTS roleplay_scenarios_values_check',
            );
            DB::statement(
                "ALTER TABLE roleplay_scenarios ADD CONSTRAINT roleplay_scenarios_values_check CHECK (mode = 'friend' AND status = 'development_only' AND key ~ '^[a-z0-9][a-z0-9._-]{0,99}$')",
            );
        }

        Schema::dropIfExists('roleplay_feedback_evidence');
        Schema::dropIfExists('conversation_session_stage_events');
        Schema::dropIfExists('scenario_objectives');
    }
};

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
        Schema::create('meaningful_speaking_days', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('learner_membership_id');
            $table->date('speaking_date');
            $table->string('timezone', 64);
            $table->ulid('source_turn_id');
            $table->timestampTz('created_at');
            $table->unique(
                ['organization_id', 'learner_membership_id', 'speaking_date'],
                'meaningful_speaking_days_date_unique',
            );
            $table->foreign(
                ['organization_id', 'learner_membership_id'],
                'meaningful_speaking_days_membership_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'source_turn_id'],
                'meaningful_speaking_days_turn_fk',
            )->references(['organization_id', 'id'])
                ->on('conversation_turns')
                ->restrictOnDelete();
        });

        Schema::create('delayed_review_occurrences', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('enrollment_id');
            $table->ulid('learner_membership_id');
            $table->ulid('learning_objective_id');
            $table->ulid('source_attempt_id');
            $table->ulid('curriculum_mission_id');
            $table->unsignedSmallInteger('sequence');
            $table->unsignedSmallInteger('interval_days');
            $table->string('rule_version', 64);
            $table->string('timezone', 64);
            $table->timestampTz('due_at');
            $table->timestampTz('completed_at')->nullable();
            $table->ulid('completion_attempt_id')->nullable();
            $table->timestampsTz();
            $table->unique(
                ['organization_id', 'source_attempt_id', 'sequence'],
                'delayed_review_occurrences_event_unique',
            );
            $table->index(
                ['organization_id', 'learner_membership_id', 'completed_at', 'due_at'],
                'delayed_review_occurrences_due_index',
            );
            $table->foreign(
                ['organization_id', 'learner_membership_id'],
                'delayed_review_occurrences_membership_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'source_attempt_id'],
                'delayed_review_occurrences_source_fk',
            )->references(['organization_id', 'id'])
                ->on('learner_attempts')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'completion_attempt_id'],
                'delayed_review_occurrences_completion_fk',
            )->references(['organization_id', 'id'])
                ->on('learner_attempts')
                ->restrictOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE delayed_review_occurrences ADD CONSTRAINT delayed_review_occurrences_values_check CHECK (sequence > 0 AND interval_days > 0 AND rule_version = \'provisional-v1\' AND ((completed_at IS NULL AND completion_attempt_id IS NULL) OR (completed_at IS NOT NULL AND completion_attempt_id IS NOT NULL)))',
            );
            DB::unprepared(
                <<<'SQL'
CREATE OR REPLACE FUNCTION enforce_deterministic_review_occurrence()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM mastery_states mastery
        INNER JOIN learner_attempts attempt
            ON attempt.organization_id = mastery.organization_id
            AND attempt.id = mastery.evidence_attempt_id
        WHERE mastery.organization_id = NEW.organization_id
            AND mastery.enrollment_id = NEW.enrollment_id
            AND mastery.learner_membership_id = NEW.learner_membership_id
            AND mastery.learning_objective_id = NEW.learning_objective_id
            AND mastery.evidence_attempt_id = NEW.source_attempt_id
            AND mastery.knowledge_state = 'mastered'
            AND attempt.curriculum_mission_id = NEW.curriculum_mission_id
    ) THEN
        RAISE EXCEPTION 'Review occurrence requires deterministic mastery evidence'
            USING ERRCODE = '55000';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER delayed_review_occurrences_insert
BEFORE INSERT ON delayed_review_occurrences
FOR EACH ROW EXECUTE FUNCTION enforce_deterministic_review_occurrence();
SQL,
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS enforce_deterministic_review_occurrence() CASCADE');
        }

        Schema::dropIfExists('delayed_review_occurrences');
        Schema::dropIfExists('meaningful_speaking_days');
    }
};

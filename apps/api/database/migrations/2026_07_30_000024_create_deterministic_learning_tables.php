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
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->unique(
                [
                    'organization_id',
                    'id',
                    'learner_membership_id',
                    'curriculum_owner_organization_id',
                    'curriculum_version_id',
                    'curriculum_publication_id',
                ],
                'enrollments_learning_pin_unique',
            );
        });

        Schema::create('learner_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('learner_membership_id');
            $table->timestampTz('adult_confirmed_at');
            $table->timestampTz('onboarding_completed_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'learner_membership_id'],
                'learner_profiles_membership_unique',
            );
            $table->unique(
                ['organization_id', 'id', 'learner_membership_id'],
                'learner_profiles_identity_unique',
            );
            $table->foreign(
                ['organization_id', 'learner_membership_id'],
                'learner_profiles_membership_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        Schema::create('learner_goals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('learner_profile_id');
            $table->ulid('learner_membership_id');
            $table->string('goal_key', 64);
            $table->timestampTz('selected_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'learner_membership_id'],
                'learner_goals_membership_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'learner_profile_id',
                    'learner_membership_id',
                ],
                'learner_goals_profile_fk',
            )->references([
                'organization_id',
                'id',
                'learner_membership_id',
            ])->on('learner_profiles')
                ->restrictOnDelete();
        });

        Schema::create('learner_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('enrollment_id');
            $table->ulid('learner_membership_id');
            $table->foreignUlid('curriculum_owner_organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->ulid('curriculum_publication_id');
            $table->ulid('curriculum_mission_id');
            $table->ulid('learning_objective_id');
            $table->char('publication_manifest_hash', 64);
            $table->char('mission_definition_hash', 64);
            $table->string('rule_version', 64);
            $table->unsignedInteger('attempt_number');
            $table->string('status', 24)->default('in_progress');
            $table->timestampTz('started_at');
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                [
                    'organization_id',
                    'id',
                    'learner_membership_id',
                    'curriculum_owner_organization_id',
                    'curriculum_version_id',
                    'curriculum_mission_id',
                    'attempt_number',
                ],
                'learner_attempts_identity_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'id',
                    'learner_membership_id',
                    'curriculum_owner_organization_id',
                    'curriculum_version_id',
                    'curriculum_mission_id',
                ],
                'learner_attempts_response_identity_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'id',
                    'learner_membership_id',
                    'attempt_number',
                ],
                'learner_attempts_submission_identity_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'id',
                    'enrollment_id',
                    'learner_membership_id',
                    'curriculum_owner_organization_id',
                    'curriculum_version_id',
                    'curriculum_publication_id',
                    'learning_objective_id',
                ],
                'learner_attempts_progress_identity_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'enrollment_id',
                    'curriculum_mission_id',
                    'attempt_number',
                ],
                'learner_attempts_number_unique',
            );
            $table->index(
                [
                    'organization_id',
                    'learner_membership_id',
                    'enrollment_id',
                    'status',
                ],
                'learner_attempts_status_index',
            );
            $table->foreign(
                [
                    'organization_id',
                    'enrollment_id',
                    'learner_membership_id',
                    'curriculum_owner_organization_id',
                    'curriculum_version_id',
                    'curriculum_publication_id',
                ],
                'learner_attempts_enrollment_pin_fk',
            )->references([
                'organization_id',
                'id',
                'learner_membership_id',
                'curriculum_owner_organization_id',
                'curriculum_version_id',
                'curriculum_publication_id',
            ])->on('enrollments')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'curriculum_owner_organization_id',
                    'curriculum_version_id',
                    'curriculum_mission_id',
                ],
                'learner_attempts_mission_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('curriculum_missions')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'curriculum_owner_organization_id',
                    'curriculum_version_id',
                    'learning_objective_id',
                ],
                'learner_attempts_objective_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('learning_objectives')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'curriculum_owner_organization_id',
                    'curriculum_version_id',
                    'curriculum_publication_id',
                ],
                'learner_attempts_publication_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('curriculum_publications')
                ->restrictOnDelete();
        });

        Schema::create('item_responses', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('learner_attempt_id');
            $table->ulid('learner_membership_id');
            $table->foreignUlid('curriculum_owner_organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->ulid('curriculum_mission_id');
            $table->ulid('learning_item_id');
            $table->unsignedInteger('response_number');
            $table->string('check_id', 100);
            $table->string('selected_option_id', 100);
            $table->boolean('check_correct');
            $table->uuid('client_capture_id')->nullable();
            $table->string('capture_mime_type', 80)->nullable();
            $table->unsignedBigInteger('capture_byte_size')->nullable();
            $table->unsignedInteger('capture_duration_ms')->nullable();
            $table->timestampTz('captured_at')->nullable();
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                [
                    'organization_id',
                    'learner_attempt_id',
                    'response_number',
                ],
                'item_responses_attempt_number_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'learner_attempt_id',
                    'client_capture_id',
                ],
                'item_responses_capture_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'learner_attempt_id',
                    'learner_membership_id',
                    'curriculum_owner_organization_id',
                    'curriculum_version_id',
                    'curriculum_mission_id',
                ],
                'item_responses_attempt_fk',
            )->references([
                'organization_id',
                'id',
                'learner_membership_id',
                'curriculum_owner_organization_id',
                'curriculum_version_id',
                'curriculum_mission_id',
            ])->on('learner_attempts')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'curriculum_owner_organization_id',
                    'curriculum_version_id',
                    'curriculum_mission_id',
                    'learning_item_id',
                ],
                'item_responses_mission_item_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'mission_id',
                'learning_item_id',
            ])->on('mission_items')
                ->restrictOnDelete();
        });

        Schema::create('attempt_submissions', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('learner_attempt_id');
            $table->ulid('learner_membership_id');
            $table->unsignedInteger('attempt_number');
            $table->unsignedSmallInteger('knowledge_score');
            $table->boolean('knowledge_check_correct');
            $table->boolean('local_capture_receipt_present');
            $table->string('knowledge_state', 24);
            $table->string('spoken_state', 24)->default('unassessed');
            $table->string('result_key', 64);
            $table->string('rule_version', 64);
            $table->timestampTz('submitted_at');
            $table->timestampTz('created_at');

            $table->primary(
                ['organization_id', 'learner_attempt_id'],
                'attempt_submissions_primary',
            );
            $table->foreign(
                [
                    'organization_id',
                    'learner_attempt_id',
                    'learner_membership_id',
                    'attempt_number',
                ],
                'attempt_submissions_attempt_fk',
            )->references([
                'organization_id',
                'id',
                'learner_membership_id',
                'attempt_number',
            ])->on('learner_attempts')
                ->restrictOnDelete();
        });

        Schema::create('mastery_states', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('enrollment_id');
            $table->ulid('learner_membership_id');
            $table->foreignUlid('curriculum_owner_organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->ulid('curriculum_publication_id');
            $table->ulid('learning_objective_id');
            $table->string('knowledge_state', 24);
            $table->string('spoken_state', 24)->default('unassessed');
            $table->unsignedSmallInteger('best_knowledge_score');
            $table->ulid('evidence_attempt_id');
            $table->string('rule_version', 64);
            $table->timestampTz('mastered_at')->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                [
                    'organization_id',
                    'enrollment_id',
                    'learning_objective_id',
                ],
                'mastery_states_objective_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'enrollment_id',
                    'learner_membership_id',
                    'curriculum_owner_organization_id',
                    'curriculum_version_id',
                    'curriculum_publication_id',
                ],
                'mastery_states_enrollment_pin_fk',
            )->references([
                'organization_id',
                'id',
                'learner_membership_id',
                'curriculum_owner_organization_id',
                'curriculum_version_id',
                'curriculum_publication_id',
            ])->on('enrollments')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'curriculum_owner_organization_id',
                    'curriculum_version_id',
                    'learning_objective_id',
                ],
                'mastery_states_objective_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('learning_objectives')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'evidence_attempt_id',
                    'enrollment_id',
                    'learner_membership_id',
                    'curriculum_owner_organization_id',
                    'curriculum_version_id',
                    'curriculum_publication_id',
                    'learning_objective_id',
                ],
                'mastery_states_attempt_fk',
            )->references([
                'organization_id',
                'id',
                'enrollment_id',
                'learner_membership_id',
                'curriculum_owner_organization_id',
                'curriculum_version_id',
                'curriculum_publication_id',
                'learning_objective_id',
            ])
                ->on('learner_attempts')
                ->restrictOnDelete();
        });

        Schema::create('review_schedules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('enrollment_id');
            $table->ulid('learner_membership_id');
            $table->foreignUlid('curriculum_owner_organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->ulid('curriculum_publication_id');
            $table->ulid('learning_objective_id');
            $table->ulid('source_attempt_id');
            $table->string('schedule_kind', 24);
            $table->string('rule_version', 64);
            $table->string('timezone', 64);
            $table->timestampTz('due_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                [
                    'organization_id',
                    'source_attempt_id',
                    'learning_objective_id',
                ],
                'review_schedules_source_unique',
            );
            $table->index(
                [
                    'organization_id',
                    'learner_membership_id',
                    'due_at',
                ],
                'review_schedules_due_index',
            );
            $table->foreign(
                [
                    'organization_id',
                    'enrollment_id',
                    'learner_membership_id',
                    'curriculum_owner_organization_id',
                    'curriculum_version_id',
                    'curriculum_publication_id',
                ],
                'review_schedules_enrollment_pin_fk',
            )->references([
                'organization_id',
                'id',
                'learner_membership_id',
                'curriculum_owner_organization_id',
                'curriculum_version_id',
                'curriculum_publication_id',
            ])->on('enrollments')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'curriculum_owner_organization_id',
                    'curriculum_version_id',
                    'learning_objective_id',
                ],
                'review_schedules_objective_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('learning_objectives')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'source_attempt_id',
                    'enrollment_id',
                    'learner_membership_id',
                    'curriculum_owner_organization_id',
                    'curriculum_version_id',
                    'curriculum_publication_id',
                    'learning_objective_id',
                ],
                'review_schedules_attempt_fk',
            )->references([
                'organization_id',
                'id',
                'enrollment_id',
                'learner_membership_id',
                'curriculum_owner_organization_id',
                'curriculum_version_id',
                'curriculum_publication_id',
                'learning_objective_id',
            ])
                ->on('learner_attempts')
                ->restrictOnDelete();
        });

        Schema::create('learning_commands', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->string('operation', 48);
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
                'learning_commands_idempotency_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'actor_user_id',
                    'actor_membership_id',
                ],
                'learning_commands_actor_fk',
            )->references(['organization_id', 'user_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "CREATE UNIQUE INDEX learner_attempts_one_open_unique ON learner_attempts (organization_id, enrollment_id, curriculum_mission_id) WHERE status = 'in_progress'",
            );
            DB::statement(
                "ALTER TABLE learner_goals ADD CONSTRAINT learner_goals_key_check CHECK (goal_key IN ('everyday-conversation', 'workplace-confidence', 'travel-confidence'))",
            );
            DB::statement(
                "ALTER TABLE learner_attempts ADD CONSTRAINT learner_attempts_status_check CHECK ((status = 'in_progress' AND submitted_at IS NULL) OR (status = 'submitted' AND submitted_at IS NOT NULL))",
            );
            DB::statement(
                "ALTER TABLE learner_attempts ADD CONSTRAINT learner_attempts_rule_version_check CHECK (rule_version = 'daily-speaking-v1')",
            );
            DB::statement(
                'ALTER TABLE learner_attempts ADD CONSTRAINT learner_attempts_number_check CHECK (attempt_number > 0)',
            );
            DB::statement(
                'ALTER TABLE item_responses ADD CONSTRAINT item_responses_capture_check CHECK ((client_capture_id IS NULL AND capture_mime_type IS NULL AND capture_byte_size IS NULL AND capture_duration_ms IS NULL AND captured_at IS NULL) OR (client_capture_id IS NOT NULL AND capture_mime_type IS NOT NULL AND capture_byte_size > 0 AND capture_duration_ms > 0 AND captured_at IS NOT NULL))',
            );
            DB::statement(
                'ALTER TABLE item_responses ADD CONSTRAINT item_responses_number_check CHECK (response_number > 0)',
            );
            DB::statement(
                "ALTER TABLE item_responses ADD CONSTRAINT item_responses_identifier_check CHECK (check_id ~ '^[a-z0-9][a-z0-9._-]{0,99}$' AND selected_option_id ~ '^[a-z0-9][a-z0-9._-]{0,99}$')",
            );
            DB::statement(
                "ALTER TABLE attempt_submissions ADD CONSTRAINT attempt_submissions_state_check CHECK (knowledge_state IN ('mastered', 'not_mastered') AND spoken_state = 'unassessed' AND result_key IN ('mission.passed', 'mission.retry') AND knowledge_score IN (0, 100) AND ((knowledge_check_correct = TRUE AND knowledge_state = 'mastered' AND knowledge_score = 100 AND result_key = 'mission.passed') OR (knowledge_check_correct = FALSE AND knowledge_state = 'not_mastered' AND knowledge_score = 0 AND result_key = 'mission.retry')))",
            );
            DB::statement(
                "ALTER TABLE mastery_states ADD CONSTRAINT mastery_states_state_check CHECK (knowledge_state IN ('mastered', 'not_mastered') AND spoken_state = 'unassessed' AND best_knowledge_score IN (0, 100) AND ((knowledge_state = 'mastered' AND best_knowledge_score = 100 AND mastered_at IS NOT NULL) OR (knowledge_state = 'not_mastered' AND best_knowledge_score = 0 AND mastered_at IS NULL)))",
            );
            DB::statement(
                "ALTER TABLE review_schedules ADD CONSTRAINT review_schedules_kind_check CHECK (schedule_kind IN ('immediate_retry', 'delayed_review'))",
            );

            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION learning_reject_append_only_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'Learning evidence is append-only'
        USING ERRCODE = '55000';
END;
$$;

CREATE OR REPLACE FUNCTION learning_enforce_attempt_transition()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF OLD.id IS DISTINCT FROM NEW.id
        OR OLD.organization_id IS DISTINCT FROM NEW.organization_id
        OR OLD.enrollment_id IS DISTINCT FROM NEW.enrollment_id
        OR OLD.learner_membership_id IS DISTINCT FROM NEW.learner_membership_id
        OR OLD.curriculum_owner_organization_id IS DISTINCT FROM NEW.curriculum_owner_organization_id
        OR OLD.curriculum_version_id IS DISTINCT FROM NEW.curriculum_version_id
        OR OLD.curriculum_publication_id IS DISTINCT FROM NEW.curriculum_publication_id
        OR OLD.curriculum_mission_id IS DISTINCT FROM NEW.curriculum_mission_id
        OR OLD.learning_objective_id IS DISTINCT FROM NEW.learning_objective_id
        OR OLD.publication_manifest_hash IS DISTINCT FROM NEW.publication_manifest_hash
        OR OLD.mission_definition_hash IS DISTINCT FROM NEW.mission_definition_hash
        OR OLD.rule_version IS DISTINCT FROM NEW.rule_version
        OR OLD.attempt_number IS DISTINCT FROM NEW.attempt_number
        OR OLD.started_at IS DISTINCT FROM NEW.started_at
        OR OLD.created_at IS DISTINCT FROM NEW.created_at
    THEN
        RAISE EXCEPTION 'Attempt identity and snapshot are immutable'
            USING ERRCODE = '55000';
    END IF;

    IF NOT (
        OLD.status = 'in_progress'
        AND OLD.submitted_at IS NULL
        AND NEW.status = 'submitted'
        AND NEW.submitted_at IS NOT NULL
        AND NEW.updated_at IS NOT DISTINCT FROM NEW.submitted_at
    ) THEN
        RAISE EXCEPTION 'Attempt transition is not allowed'
            USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION learning_enforce_item_response_insert()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    locked_attempt learner_attempts%ROWTYPE;
    expected_response_number integer;
BEGIN
    SELECT attempt.*
    INTO locked_attempt
    FROM learner_attempts AS attempt
    WHERE attempt.organization_id = NEW.organization_id
        AND attempt.id = NEW.learner_attempt_id
        AND attempt.learner_membership_id = NEW.learner_membership_id
        AND attempt.curriculum_owner_organization_id = NEW.curriculum_owner_organization_id
        AND attempt.curriculum_version_id = NEW.curriculum_version_id
        AND attempt.curriculum_mission_id = NEW.curriculum_mission_id
    FOR UPDATE;

    SELECT COALESCE(MAX(response.response_number), 0) + 1
    INTO expected_response_number
    FROM item_responses AS response
    WHERE response.organization_id = NEW.organization_id
        AND response.learner_attempt_id = NEW.learner_attempt_id;

    IF locked_attempt.id IS NULL
        OR locked_attempt.status <> 'in_progress'
        OR locked_attempt.submitted_at IS NOT NULL
        OR NEW.created_at < locked_attempt.started_at
        OR NEW.response_number <> expected_response_number
        OR (
            NEW.client_capture_id IS NULL
            AND (
                NEW.capture_mime_type IS NOT NULL
                OR NEW.capture_byte_size IS NOT NULL
                OR NEW.capture_duration_ms IS NOT NULL
                OR NEW.captured_at IS NOT NULL
            )
        )
        OR (
            NEW.client_capture_id IS NOT NULL
            AND (
                NEW.capture_mime_type IS NULL
                OR NEW.capture_byte_size IS NULL
                OR NEW.capture_duration_ms IS NULL
                OR NEW.captured_at IS NULL
                OR NEW.capture_mime_type NOT IN (
                    'audio/webm',
                    'audio/webm;codecs=opus',
                    'audio/ogg',
                    'audio/ogg;codecs=opus',
                    'audio/mp4'
                )
                OR NEW.capture_byte_size < 128
                OR NEW.capture_byte_size > 10000000
                OR NEW.capture_duration_ms < 250
                OR NEW.capture_duration_ms > 120000
                OR NEW.captured_at IS DISTINCT FROM NEW.created_at
            )
        )
    THEN
        RAISE EXCEPTION 'Item response attempt is not open'
            USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION learning_enforce_attempt_submission_insert()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.rule_version <> 'daily-speaking-v1'
        OR NEW.created_at IS DISTINCT FROM NEW.submitted_at
        OR NOT EXISTS (
            SELECT 1
            FROM learner_attempts AS attempt
            INNER JOIN LATERAL (
                SELECT response.check_correct, response.created_at
                FROM item_responses AS response
                WHERE response.organization_id = attempt.organization_id
                    AND response.learner_attempt_id = attempt.id
                ORDER BY response.response_number DESC
                LIMIT 1
            ) AS latest ON TRUE
            WHERE attempt.organization_id = NEW.organization_id
                AND attempt.id = NEW.learner_attempt_id
                AND attempt.learner_membership_id = NEW.learner_membership_id
                AND attempt.attempt_number = NEW.attempt_number
                AND attempt.status = 'submitted'
                AND attempt.submitted_at = NEW.submitted_at
                AND attempt.rule_version = NEW.rule_version
                AND latest.created_at <= NEW.submitted_at
                AND latest.check_correct = NEW.knowledge_check_correct
                AND NEW.local_capture_receipt_present = EXISTS (
                    SELECT 1
                    FROM item_responses AS receipt
                    WHERE receipt.organization_id = attempt.organization_id
                        AND receipt.learner_attempt_id = attempt.id
                        AND receipt.client_capture_id IS NOT NULL
                )
        )
    THEN
        RAISE EXCEPTION 'Attempt submission result is not authoritative'
            USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION learning_require_attempt_submission()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.status = 'submitted'
        AND NOT EXISTS (
            SELECT 1
            FROM attempt_submissions AS submission
            WHERE submission.organization_id = NEW.organization_id
                AND submission.learner_attempt_id = NEW.id
                AND submission.learner_membership_id = NEW.learner_membership_id
                AND submission.attempt_number = NEW.attempt_number
                AND submission.submitted_at = NEW.submitted_at
        )
    THEN
        RAISE EXCEPTION 'Submitted attempt requires an authoritative result'
            USING ERRCODE = '55000';
    END IF;

    RETURN NULL;
END;
$$;

CREATE OR REPLACE FUNCTION learning_enforce_mastery_insert()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.lock_version <> 0
        OR NEW.created_at IS DISTINCT FROM NEW.updated_at
    THEN
        RAISE EXCEPTION 'Initial mastery projection metadata is not allowed'
            USING ERRCODE = '55000';
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM attempt_submissions AS submission
        INNER JOIN learner_attempts AS attempt
            ON attempt.organization_id = submission.organization_id
            AND attempt.id = submission.learner_attempt_id
        WHERE submission.organization_id = NEW.organization_id
            AND submission.learner_attempt_id = NEW.evidence_attempt_id
            AND submission.knowledge_score = NEW.best_knowledge_score
            AND submission.knowledge_state = NEW.knowledge_state
            AND submission.spoken_state = NEW.spoken_state
            AND submission.rule_version = NEW.rule_version
            AND submission.submitted_at = NEW.created_at
            AND attempt.enrollment_id = NEW.enrollment_id
            AND attempt.learner_membership_id = NEW.learner_membership_id
            AND attempt.curriculum_owner_organization_id = NEW.curriculum_owner_organization_id
            AND attempt.curriculum_version_id = NEW.curriculum_version_id
            AND attempt.curriculum_publication_id = NEW.curriculum_publication_id
            AND attempt.learning_objective_id = NEW.learning_objective_id
            AND attempt.rule_version = NEW.rule_version
            AND (
                (
                    submission.knowledge_check_correct = TRUE
                    AND NEW.knowledge_state = 'mastered'
                    AND NEW.best_knowledge_score = 100
                    AND NEW.mastered_at = submission.submitted_at
                )
                OR (
                    submission.knowledge_check_correct = FALSE
                    AND NEW.knowledge_state = 'not_mastered'
                    AND NEW.best_knowledge_score = 0
                    AND NEW.mastered_at IS NULL
                )
            )
    ) THEN
        RAISE EXCEPTION 'Initial mastery projection evidence is not authoritative'
            USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION learning_enforce_review_schedule_insert()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM attempt_submissions AS submission
        INNER JOIN learner_attempts AS attempt
            ON attempt.organization_id = submission.organization_id
            AND attempt.id = submission.learner_attempt_id
        INNER JOIN organizations AS organization
            ON organization.id = submission.organization_id
        WHERE submission.organization_id = NEW.organization_id
            AND submission.learner_attempt_id = NEW.source_attempt_id
            AND submission.rule_version = NEW.rule_version
            AND NEW.rule_version = 'daily-speaking-v1'
            AND submission.submitted_at = NEW.created_at
            AND attempt.enrollment_id = NEW.enrollment_id
            AND attempt.learner_membership_id = NEW.learner_membership_id
            AND attempt.curriculum_owner_organization_id = NEW.curriculum_owner_organization_id
            AND attempt.curriculum_version_id = NEW.curriculum_version_id
            AND attempt.curriculum_publication_id = NEW.curriculum_publication_id
            AND attempt.learning_objective_id = NEW.learning_objective_id
            AND attempt.rule_version = NEW.rule_version
            AND NEW.timezone = organization.timezone
            AND (
                (
                    submission.knowledge_check_correct = FALSE
                    AND NEW.schedule_kind = 'immediate_retry'
                    AND NEW.due_at = submission.submitted_at
                )
                OR (
                    submission.knowledge_check_correct = TRUE
                    AND NEW.schedule_kind = 'delayed_review'
                    AND NEW.due_at = (
                        date_trunc(
                            'day',
                            submission.submitted_at
                                AT TIME ZONE organization.timezone
                        )
                        + INTERVAL '1 day 9 hours'
                    ) AT TIME ZONE organization.timezone
                )
            )
    ) THEN
        RAISE EXCEPTION 'Review schedule is not deterministic'
            USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION learning_enforce_mastery_projection()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF OLD.id IS DISTINCT FROM NEW.id
        OR OLD.organization_id IS DISTINCT FROM NEW.organization_id
        OR OLD.enrollment_id IS DISTINCT FROM NEW.enrollment_id
        OR OLD.learner_membership_id IS DISTINCT FROM NEW.learner_membership_id
        OR OLD.curriculum_owner_organization_id IS DISTINCT FROM NEW.curriculum_owner_organization_id
        OR OLD.curriculum_version_id IS DISTINCT FROM NEW.curriculum_version_id
        OR OLD.curriculum_publication_id IS DISTINCT FROM NEW.curriculum_publication_id
        OR OLD.learning_objective_id IS DISTINCT FROM NEW.learning_objective_id
        OR OLD.spoken_state IS DISTINCT FROM NEW.spoken_state
        OR OLD.created_at IS DISTINCT FROM NEW.created_at
        OR OLD.knowledge_state <> 'not_mastered'
        OR OLD.best_knowledge_score <> 0
        OR OLD.mastered_at IS NOT NULL
        OR NEW.knowledge_state <> 'mastered'
        OR NEW.best_knowledge_score <> 100
        OR NEW.mastered_at IS NULL
        OR NEW.updated_at IS DISTINCT FROM NEW.mastered_at
        OR NEW.evidence_attempt_id IS NOT DISTINCT FROM OLD.evidence_attempt_id
        OR NEW.lock_version <> OLD.lock_version + 1
    THEN
        RAISE EXCEPTION 'Mastery projection transition is not allowed'
            USING ERRCODE = '55000';
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM attempt_submissions AS submission
        INNER JOIN learner_attempts AS attempt
            ON attempt.organization_id = submission.organization_id
            AND attempt.id = submission.learner_attempt_id
        WHERE submission.organization_id = NEW.organization_id
            AND submission.learner_attempt_id = NEW.evidence_attempt_id
            AND submission.knowledge_check_correct = TRUE
            AND submission.knowledge_score = 100
            AND submission.knowledge_state = 'mastered'
            AND submission.rule_version = NEW.rule_version
            AND submission.submitted_at = NEW.mastered_at
            AND attempt.enrollment_id = NEW.enrollment_id
            AND attempt.learner_membership_id = NEW.learner_membership_id
            AND attempt.curriculum_owner_organization_id = NEW.curriculum_owner_organization_id
            AND attempt.curriculum_version_id = NEW.curriculum_version_id
            AND attempt.curriculum_publication_id = NEW.curriculum_publication_id
            AND attempt.learning_objective_id = NEW.learning_objective_id
            AND attempt.rule_version = NEW.rule_version
    ) THEN
        RAISE EXCEPTION 'Mastery projection evidence is not authoritative'
            USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$;
SQL);

            foreach ([
                'learner_profiles',
                'learner_goals',
                'item_responses',
                'attempt_submissions',
                'review_schedules',
                'learning_commands',
            ] as $table) {
                DB::statement(sprintf(
                    'CREATE TRIGGER %s_append_only BEFORE UPDATE OR DELETE ON %s FOR EACH ROW EXECUTE FUNCTION learning_reject_append_only_mutation()',
                    $table,
                    $table,
                ));
            }

            DB::statement(
                'CREATE TRIGGER learner_attempts_transition BEFORE UPDATE ON learner_attempts FOR EACH ROW EXECUTE FUNCTION learning_enforce_attempt_transition()',
            );
            DB::statement(
                'CREATE TRIGGER learner_attempts_no_delete BEFORE DELETE ON learner_attempts FOR EACH ROW EXECUTE FUNCTION learning_reject_append_only_mutation()',
            );
            DB::statement(
                'CREATE TRIGGER item_responses_insert BEFORE INSERT ON item_responses FOR EACH ROW EXECUTE FUNCTION learning_enforce_item_response_insert()',
            );
            DB::statement(
                'CREATE TRIGGER attempt_submissions_insert BEFORE INSERT ON attempt_submissions FOR EACH ROW EXECUTE FUNCTION learning_enforce_attempt_submission_insert()',
            );
            DB::statement(
                'CREATE CONSTRAINT TRIGGER learner_attempts_submission_required AFTER INSERT OR UPDATE ON learner_attempts DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION learning_require_attempt_submission()',
            );
            DB::statement(
                'CREATE TRIGGER mastery_states_insert BEFORE INSERT ON mastery_states FOR EACH ROW EXECUTE FUNCTION learning_enforce_mastery_insert()',
            );
            DB::statement(
                'CREATE TRIGGER mastery_states_transition BEFORE UPDATE ON mastery_states FOR EACH ROW EXECUTE FUNCTION learning_enforce_mastery_projection()',
            );
            DB::statement(
                'CREATE TRIGGER mastery_states_no_delete BEFORE DELETE ON mastery_states FOR EACH ROW EXECUTE FUNCTION learning_reject_append_only_mutation()',
            );
            DB::statement(
                'CREATE TRIGGER review_schedules_insert BEFORE INSERT ON review_schedules FOR EACH ROW EXECUTE FUNCTION learning_enforce_review_schedule_insert()',
            );
        } else {
            DB::statement(
                "CREATE UNIQUE INDEX learner_attempts_one_open_unique ON learner_attempts (organization_id, enrollment_id, curriculum_mission_id) WHERE status = 'in_progress'",
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('learner_attempts')
            && (
                DB::table('learner_attempts')->exists()
                || DB::table('learner_profiles')->exists()
            )
        ) {
            throw new RuntimeException(
                'Cannot roll back PR-006 while authoritative learner records exist. Deploy a reviewed forward fix.',
            );
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS learning_enforce_mastery_projection() CASCADE');
            DB::statement('DROP FUNCTION IF EXISTS learning_enforce_review_schedule_insert() CASCADE');
            DB::statement('DROP FUNCTION IF EXISTS learning_enforce_mastery_insert() CASCADE');
            DB::statement('DROP FUNCTION IF EXISTS learning_enforce_attempt_submission_insert() CASCADE');
            DB::statement('DROP FUNCTION IF EXISTS learning_require_attempt_submission() CASCADE');
            DB::statement('DROP FUNCTION IF EXISTS learning_enforce_item_response_insert() CASCADE');
            DB::statement('DROP FUNCTION IF EXISTS learning_enforce_attempt_transition() CASCADE');
            DB::statement('DROP FUNCTION IF EXISTS learning_reject_append_only_mutation() CASCADE');
        }

        Schema::dropIfExists('learning_commands');
        Schema::dropIfExists('review_schedules');
        Schema::dropIfExists('mastery_states');
        Schema::dropIfExists('attempt_submissions');
        Schema::dropIfExists('item_responses');
        Schema::dropIfExists('learner_attempts');
        Schema::dropIfExists('learner_goals');
        Schema::dropIfExists('learner_profiles');

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropUnique('enrollments_learning_pin_unique');
        });
    }
};

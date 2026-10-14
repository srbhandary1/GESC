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
        Schema::table('conversation_recovery_artifacts', function (Blueprint $table): void {
            $table->unique(['organization_id', 'id', 'conversation_session_id', 'learner_membership_id'], 'recovery_artifact_owner_unique');
        });
        Schema::create('conversation_dialogue_states', function (Blueprint $table): void {
            $table->ulid('conversation_session_id')->primary();
            $table->ulid('organization_id');
            $table->ulid('learner_membership_id');
            $table->string('flow', 32);
            $table->string('state', 40);
            $table->unsignedInteger('revision')->default(0);
            $table->unsignedInteger('opportunity')->default(1);
            $table->ulid('start_artifact_id');
            $table->ulid('latest_artifact_id')->nullable();
            $table->ulid('latest_turn_id')->nullable();
            $table->ulid('original_turn_id')->nullable();
            $table->ulid('partner_turn_id')->nullable();
            $table->string('notice_version', 80);
            $table->timestampTz('notice_accepted_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->foreign(['organization_id', 'conversation_session_id', 'learner_membership_id'], 'dialogue_state_owner_fk')
                ->references(['organization_id', 'id', 'learner_membership_id'])->on('conversation_sessions')->restrictOnDelete();
            foreach (['start_artifact_id', 'latest_artifact_id'] as $column) {
                $table->foreign(['organization_id', $column, 'conversation_session_id', 'learner_membership_id'], 'dialogue_state_'.$column.'_fk')
                    ->references(['organization_id', 'id', 'conversation_session_id', 'learner_membership_id'])->on('conversation_recovery_artifacts')->restrictOnDelete();
            }
            foreach (['latest_turn_id', 'original_turn_id', 'partner_turn_id'] as $column) {
                $table->foreign(['organization_id', $column, 'conversation_session_id', 'learner_membership_id'], 'dialogue_state_'.$column.'_fk')
                    ->references(['organization_id', 'id', 'conversation_session_id', 'learner_membership_id'])->on('conversation_turns')->restrictOnDelete();
            }
        });
        Schema::create('conversation_dialogue_outcomes', function (Blueprint $table): void {
            $table->ulid('conversation_turn_id')->primary();
            $table->ulid('organization_id');
            $table->ulid('learner_membership_id');
            $table->ulid('conversation_session_id');
            $table->ulid('artifact_id');
            $table->unsignedInteger('revision');
            $table->unsignedInteger('opportunity');
            $table->string('assessment', 24);
            $table->string('action', 32);
            $table->json('objective_evidence');
            $table->timestampTz('created_at');
            $table->unique(['conversation_session_id', 'revision']);
            $table->foreign(['organization_id', 'conversation_session_id', 'learner_membership_id'], 'dialogue_outcome_owner_fk')
                ->references(['organization_id', 'id', 'learner_membership_id'])->on('conversation_sessions')->restrictOnDelete();
            $table->foreign(['organization_id', 'conversation_turn_id', 'conversation_session_id', 'learner_membership_id'], 'dialogue_outcome_turn_owner_fk')
                ->references(['organization_id', 'id', 'conversation_session_id', 'learner_membership_id'])->on('conversation_turns')->restrictOnDelete();
            $table->foreign(['organization_id', 'artifact_id', 'conversation_session_id', 'learner_membership_id'], 'dialogue_outcome_artifact_owner_fk')
                ->references(['organization_id', 'id', 'conversation_session_id', 'learner_membership_id'])->on('conversation_recovery_artifacts')->restrictOnDelete();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE conversation_dialogue_states ADD CONSTRAINT dialogue_state_values CHECK (flow IN ('roleplay_dialogue','question_help') AND state IN ('awaiting_reply','retry_required','awaiting_question','awaiting_explanation_permission','complete','closed') AND revision >= 0 AND opportunity >= 1)");
            DB::statement("ALTER TABLE conversation_dialogue_outcomes ADD CONSTRAINT dialogue_outcome_values CHECK (assessment IN ('accepted','retry_required','unassessed') AND revision > 0 AND opportunity > 0)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_dialogue_outcomes');
        Schema::dropIfExists('conversation_dialogue_states');
        Schema::table('conversation_recovery_artifacts', fn (Blueprint $table) => $table->dropUnique('recovery_artifact_owner_unique'));
    }
};

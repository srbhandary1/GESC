<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE conversation_sessions DROP CONSTRAINT IF EXISTS conversation_sessions_values_check',
            );
            DB::statement(
                "ALTER TABLE conversation_sessions ADD CONSTRAINT conversation_sessions_values_check CHECK (persona_definition_hash ~ '^[0-9a-f]{64}$' AND scenario_definition_hash ~ '^[0-9a-f]{64}$' AND mode = 'friend' AND coaching_mode IN ('gentle', 'tutor', 'fluency') AND status = 'active' AND ended_at IS NULL)",
            );
        }

        Schema::create('prompt_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('prompt_key', 100);
            $table->unsignedInteger('version');
            $table->string('coaching_mode', 24);
            $table->string('locale', 16);
            $table->string('body_sha256', 64);
            $table->text('body');
            $table->string('status', 32);
            $table->timestampTz('created_at');
            $table->unique(
                ['prompt_key', 'coaching_mode', 'version'],
                'prompt_versions_mode_version_unique',
            );
            $table->unique(
                ['prompt_key', 'coaching_mode', 'status'],
                'prompt_versions_active_mode_unique',
            );
        });

        Schema::create('correction_suggestions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->ulid('conversation_turn_id');
            $table->ulid('conversation_session_id');
            $table->ulid('learner_membership_id');
            $table->string('coaching_mode', 24);
            $table->string('error_category', 64);
            $table->string('correction_digest', 64);
            $table->string('explanation_kn_digest', 64);
            $table->boolean('retry_recommended');
            $table->string('safety_state', 32);
            $table->string('prompt_version_id', 26);
            $table->timestampTz('recorded_at');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->restrictOnDelete();
            $table->foreign('conversation_turn_id')
                ->references('id')
                ->on('conversation_turns')
                ->cascadeOnDelete();
            $table->unique('conversation_turn_id');
            $table->index(['organization_id', 'learner_membership_id']);
        });

        Schema::create('learner_error_patterns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->ulid('learner_membership_id');
            $table->string('error_category', 64);
            $table->unsignedInteger('observation_count');
            $table->timestampTz('first_observed_at');
            $table->timestampTz('last_observed_at');
            $table->unique(
                ['organization_id', 'learner_membership_id', 'error_category'],
                'learner_error_patterns_unique',
            );
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->restrictOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE prompt_versions ADD CONSTRAINT prompt_versions_values_check CHECK (prompt_key ~ '^[a-z0-9][a-z0-9._-]{0,99}$' AND version >= 1 AND coaching_mode IN ('gentle', 'tutor', 'fluency') AND locale = 'en-IN' AND body_sha256 ~ '^[0-9a-f]{64}$' AND status = 'active')",
            );
            DB::statement(
                "ALTER TABLE correction_suggestions ADD CONSTRAINT correction_suggestions_values_check CHECK (coaching_mode IN ('gentle', 'tutor', 'fluency') AND error_category ~ '^[a-z0-9][a-z0-9._-]{0,63}$' AND correction_digest ~ '^[0-9a-f]{64}$' AND explanation_kn_digest ~ '^[0-9a-f]{64}$' AND safety_state IN ('ok', 'fallback') AND prompt_version_id ~ '^[0-9A-HJKMNP-TV-Z]{26}$')",
            );
            DB::statement(
                "ALTER TABLE learner_error_patterns ADD CONSTRAINT learner_error_patterns_values_check CHECK (error_category ~ '^[a-z0-9][a-z0-9._-]{0,63}$' AND observation_count >= 1)",
            );
        }

        $now = now('UTC');
        foreach (
            [
                'gentle' => "You are a patient Friend coach.\nReturn one short English conversational reply.\nAlso return one logically separate priority correction with a Kannada explanation when helpful.\nNever invent scores or change learning records.",
                'tutor' => "You are a Tutor-mode Friend coach.\nReturn one short English conversational reply.\nProvide one explicit priority correction with a Kannada explanation and whether retry is recommended.\nNever invent scores or change learning records.",
                'fluency' => "You are a Fluency-mode Friend coach.\nReturn one short English conversational reply focused on flow.\nProvide at most one light priority correction with a Kannada explanation.\nNever invent scores or change learning records.",
            ] as $mode => $body
        ) {
            DB::table('prompt_versions')->insert([
                'id' => (string) Str::ulid(),
                'prompt_key' => 'friend.turn.v1',
                'version' => 1,
                'coaching_mode' => $mode,
                'locale' => 'en-IN',
                'body_sha256' => hash('sha256', $body),
                'body' => $body,
                'status' => 'active',
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_error_patterns');
        Schema::dropIfExists('correction_suggestions');
        Schema::dropIfExists('prompt_versions');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE conversation_sessions DROP CONSTRAINT IF EXISTS conversation_sessions_values_check',
            );
            DB::statement(
                "ALTER TABLE conversation_sessions ADD CONSTRAINT conversation_sessions_values_check CHECK (persona_definition_hash ~ '^[0-9a-f]{64}$' AND scenario_definition_hash ~ '^[0-9a-f]{64}$' AND mode = 'friend' AND coaching_mode = 'gentle' AND status = 'active' AND ended_at IS NULL)",
            );
        }
    }
};

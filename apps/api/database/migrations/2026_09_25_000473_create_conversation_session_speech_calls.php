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
        Schema::create('conversation_session_speech_calls', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->ulid('learner_membership_id');
            $table->ulid('conversation_session_id');
            $table->string('operation', 32);
            $table->string('status', 24);
            $table->string('provider_key', 64)->nullable();
            $table->string('model_version', 100)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedBigInteger('input_units')->nullable();
            $table->unsignedBigInteger('output_units')->nullable();
            $table->string('input_unit_kind', 32)->nullable();
            $table->string('output_unit_kind', 32)->nullable();
            $table->unsignedBigInteger('estimated_cost_microunits')->nullable();
            $table->string('dispatch_kind', 32)->nullable();
            $table->string('api_version', 80)->nullable();
            $table->string('voice_key', 100)->nullable();
            $table->string('locale', 16)->nullable();
            $table->string('region', 80)->nullable();
            $table->char('configuration_sha256', 64)->nullable();
            $table->string('safe_failure_code', 64)->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('completed_at')->nullable();
            $table->unique(['organization_id', 'conversation_session_id', 'operation'], 'session_speech_operation_unique');
            $table->foreign(['organization_id', 'conversation_session_id', 'learner_membership_id'], 'session_speech_owner_fk')
                ->references(['organization_id', 'id', 'learner_membership_id'])->on('conversation_sessions')->restrictOnDelete();
        });
        Schema::table('conversation_dialogue_states', function (Blueprint $table): void {
            $table->ulid('opener_speech_artifact_id')->nullable();
            $table->foreign(['organization_id', 'opener_speech_artifact_id'], 'dialogue_opener_recovery_fk')
                ->references(['organization_id', 'id'])->on('conversation_recovery_artifacts')->restrictOnDelete();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE conversation_session_speech_calls ADD CONSTRAINT session_speech_values CHECK (operation = 'roleplay.opener' AND status IN ('dispatching','succeeded','text_first','failed') AND (configuration_sha256 IS NULL OR configuration_sha256 ~ '^[0-9a-f]{64}$'))");
        }
    }

    public function down(): void
    {
        Schema::table('conversation_dialogue_states', function (Blueprint $table): void {
            $table->dropForeign('dialogue_opener_recovery_fk');
            $table->dropColumn('opener_speech_artifact_id');
        });
        Schema::dropIfExists('conversation_session_speech_calls');
    }
};

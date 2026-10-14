<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replace(true);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        if (DB::table('ai_cost_ledger')->where('provider_key', 'local-dialogue-control')->exists()) {
            throw new RuntimeException('Cannot remove the local dialogue constraint while immutable usage evidence exists.');
        }
        $this->replace(false);
    }

    private function replace(bool $includeDialogue): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        $dialogue = $includeDialogue ? <<<'SQL'
OR (
    provider_stage = 'model'
    AND provider_key = 'local-dialogue-control'
    AND model_version = 'dialogue-control-v1'
    AND input_units = 0 AND output_units = 0
    AND estimated_cost_microunits = 0
)
SQL : '';
        DB::statement('ALTER TABLE ai_cost_ledger DROP CONSTRAINT ai_cost_ledger_values_check');
        DB::statement(<<<SQL
ALTER TABLE ai_cost_ledger ADD CONSTRAINT ai_cost_ledger_values_check CHECK (
    provider_stage IN ('stt', 'model', 'tts')
    AND provider_attempt > 0 AND latency_ms >= 0
    AND input_units >= 0 AND output_units >= 0 AND estimated_cost_microunits >= 0
    AND result_status IN ('succeeded', 'failed')
    AND (
        (provider_key = 'deterministic-fake' AND model_version ~ '^fake-' AND estimated_cost_microunits = 0)
        OR (provider_stage = 'stt' AND provider_key = 'project-confirmed-text' AND model_version = 'confirmed-text-v1' AND estimated_cost_microunits = 0)
        OR (provider_stage = 'stt' AND provider_key = 'indic-http-stt' AND model_version ~ '^indic-http-stt-v1:' AND estimated_cost_microunits >= 0)
        OR (provider_stage = 'stt' AND provider_key = 'azure-speech-soft-launch-stt' AND model_version = 'fast-transcription-2025-10-15-provider-managed' AND estimated_cost_microunits >= 0)
        OR (provider_stage = 'model' AND provider_key = 'azure-openai' AND model_version ~ '^[A-Za-z0-9._:-]{1,100}$')
        OR (provider_stage = 'model' AND provider_key = 'project-lesson-drill' AND model_version = 'strict-pattern-gate-v1' AND estimated_cost_microunits = 0)
        OR (provider_stage = 'tts' AND provider_key = 'project-text-first' AND model_version = 'text-first-v1' AND estimated_cost_microunits = 0)
        OR (provider_stage = 'tts' AND provider_key = 'azure-speech-guidance' AND model_version ~ '^soft-launch-guidance-tts-v1:' AND estimated_cost_microunits >= 0)
        {$dialogue}
    )
)
SQL);
    }
};

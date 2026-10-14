<?php

declare(strict_types=1);

namespace App\Conversation\Metering;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final class LearnerProviderUsageQuery
{
    /** Caller must authorize the exact organization and learner. No content is returned.
     * @return array<string, mixed>
     */
    public function read(string $organizationId, string $membershipId): array
    {
        $since = Date::now()->subDays(30);
        $query = DB::table('ai_cost_ledger as ledger')
            ->leftJoin('speech_provider_attempt_details as details', function ($join): void {
                $join->on('details.organization_id', '=', 'ledger.organization_id')->on('details.ai_cost_ledger_id', '=', 'ledger.id');
            })
            ->where('ledger.organization_id', $organizationId)->where('ledger.learner_membership_id', $membershipId)
            ->where('ledger.recorded_at', '>=', $since)->where('ledger.provider_stage', 'model')
            ->whereNotIn('ledger.provider_key', ['project-lesson-drill', 'local-dialogue-control', 'deterministic-fake']);
        $inputKind = "COALESCE(NULLIF(ledger.input_unit_kind, 'unknown'), details.input_unit_kind, 'unknown')";
        $outputKind = "COALESCE(NULLIF(ledger.output_unit_kind, 'unknown'), details.output_unit_kind, 'unknown')";
        $rows = $query->selectRaw("ledger.provider_key, COUNT(*) AS attempts, SUM(CASE WHEN $inputKind = 'provider_input_tokens' THEN ledger.input_units ELSE 0 END) AS input_tokens, SUM(CASE WHEN $outputKind = 'provider_output_tokens' THEN ledger.output_units ELSE 0 END) AS output_tokens, SUM(CASE WHEN $inputKind <> 'provider_input_tokens' OR $outputKind <> 'provider_output_tokens' THEN 1 ELSE 0 END) AS unknown_usage_attempts")
            ->groupBy('ledger.provider_key')->orderBy('ledger.provider_key')->get();
        $providers = [];
        foreach ($rows as $row) {
            $providers[] = ['provider' => $row->provider_key, 'attempts' => (int) $row->attempts,
                'input_tokens' => (int) $row->input_tokens, 'output_tokens' => (int) $row->output_tokens,
                'unknown_usage_attempts' => (int) $row->unknown_usage_attempts];
        }

        return ['period_start' => $since->toIso8601String(), 'period_days' => 30,
            'input_tokens' => array_sum(array_column($providers, 'input_tokens')),
            'output_tokens' => array_sum(array_column($providers, 'output_tokens')),
            'unknown_usage_attempts' => array_sum(array_column($providers, 'unknown_usage_attempts')),
            'providers' => $providers];
    }
}

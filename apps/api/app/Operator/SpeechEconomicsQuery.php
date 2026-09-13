<?php

declare(strict_types=1);

namespace App\Operator;

use App\Models\User;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class SpeechEconomicsQuery
{
    public function __construct(
        private OrganizationContextManager $contexts,
        private SpeechEconomicsAuthorization $authorization,
    ) {}

    /**
     * @param  array<string, string>  $filters
     * @return array{
     *   generated_at: string,
     *   organization_id: string,
     *   presentation: string,
     *   empty: bool,
     *   partial: bool,
     *   stale_rate: bool,
     *   unreconciled: bool,
     *   suppressed_row_count: int,
     *   visible_row_count: int,
     *   totals: array<string, int|null>,
     *   rows: list<array<string, mixed>>
     * }
     */
    public function summarize(User $actor, array $filters): array
    {
        $this->authorization->assertUsage($actor);
        $organizationId = $this->contexts->current()->organizationId;
        $query = DB::table('speech_usage_daily_aggregates')
            ->where('organization_id', $organizationId);
        $query = $this->applyFilters($query, $filters);
        $records = $query
            ->orderBy('usage_date')
            ->orderBy('provider_stage')
            ->orderBy('provider_key')
            ->get();

        $rows = [];
        $suppressed = 0;
        $staleRate = false;
        $unreconciled = false;
        $totals = [
            'attempt_count' => 0,
            'dispatch_count' => 0,
            'success_count' => 0,
            'failure_count' => 0,
            'cancelled_count' => 0,
            'partial_count' => 0,
            'fallback_count' => 0,
            'billable_unit_quantity' => 0,
            'allocated_inr_microunits' => 0,
        ];

        foreach ($records as $record) {
            $status = (string) ($record->reconciliation_status ?? '');
            if (in_array($status, ['rate_missing', 'rate_stale', 'rate_ambiguous'], true)) {
                $staleRate = true;
            }
            if ($status === 'unreconciled') {
                $unreconciled = true;
            }
            if ((bool) ($record->small_cell_suppressed ?? true)) {
                $suppressed++;
                $rows[] = $this->suppressedRow($record);

                continue;
            }

            $row = $this->visibleRow($record);
            $rows[] = $row;
            foreach (array_keys($totals) as $key) {
                $totals[$key] += (int) ($row[$key] ?? 0);
            }
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'organization_id' => $organizationId,
            'presentation' => 'allocated_usage',
            'empty' => $records->isEmpty(),
            'partial' => $suppressed > 0 && $rows !== [],
            'stale_rate' => $staleRate,
            'unreconciled' => $unreconciled,
            'suppressed_row_count' => $suppressed,
            'visible_row_count' => count($rows) - $suppressed,
            'totals' => $totals,
            'rows' => $rows,
            'budget_alert' => $this->budgetAlert($organizationId),
        ];
    }

    /**
     * @param  Builder  $query
     * @param  array<string, string>  $filters
     * @return Builder
     */
    private function applyFilters($query, array $filters)
    {
        $allowed = [
            'environment' => 'environment',
            'provider_stage' => 'provider_stage',
            'provider_key' => 'provider_key',
            'locale' => 'locale',
            'conversation_mode' => 'conversation_mode',
            'result_status' => 'result_status',
            'dispatch_kind' => 'dispatch_kind',
            'route_role' => 'route_role',
        ];
        foreach ($allowed as $input => $column) {
            if (($filters[$input] ?? '') !== '') {
                $query->where($column, $filters[$input]);
            }
        }
        if (($filters['from'] ?? '') !== '') {
            $query->whereDate('usage_date', '>=', $filters['from']);
        }
        if (($filters['to'] ?? '') !== '') {
            $query->whereDate('usage_date', '<=', $filters['to']);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function suppressedRow(stdClass $record): array
    {
        return [
            'usage_date' => (string) $record->usage_date,
            'environment' => (string) $record->environment,
            'aggregate_kind' => (string) $record->aggregate_kind,
            'provider_stage' => (string) $record->provider_stage,
            'provider_key' => (string) $record->provider_key,
            'model_version' => (string) $record->model_version,
            'route_role' => (string) $record->route_role,
            'locale' => (string) $record->locale,
            'conversation_mode' => (string) $record->conversation_mode,
            'result_status' => (string) $record->result_status,
            'dispatch_kind' => (string) $record->dispatch_kind,
            'billable_unit_kind' => (string) $record->billable_unit_kind,
            'reconciliation_status' => (string) $record->reconciliation_status,
            'small_cell_suppressed' => true,
            'attempt_count' => null,
            'dispatch_count' => null,
            'success_count' => null,
            'failure_count' => null,
            'cancelled_count' => null,
            'partial_count' => null,
            'fallback_count' => null,
            'billable_unit_quantity' => null,
            'allocated_inr_microunits' => null,
            'first_result_p50_ms' => null,
            'first_result_p95_ms' => null,
            'completion_p95_ms' => null,
            'first_audible_p95_ms' => null,
            'cost_per_successful_turn_inr_microunits' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function visibleRow(stdClass $record): array
    {
        $success = (int) $record->success_count;
        $allocated = (int) $record->estimated_inr_cost_microunits;

        return [
            'usage_date' => (string) $record->usage_date,
            'environment' => (string) $record->environment,
            'aggregate_kind' => (string) $record->aggregate_kind,
            'provider_stage' => (string) $record->provider_stage,
            'provider_key' => (string) $record->provider_key,
            'model_version' => (string) $record->model_version,
            'route_role' => (string) $record->route_role,
            'locale' => (string) $record->locale,
            'conversation_mode' => (string) $record->conversation_mode,
            'result_status' => (string) $record->result_status,
            'dispatch_kind' => (string) $record->dispatch_kind,
            'billable_unit_kind' => (string) $record->billable_unit_kind,
            'reconciliation_status' => (string) $record->reconciliation_status,
            'small_cell_suppressed' => false,
            'attempt_count' => (int) $record->attempt_count,
            'dispatch_count' => (int) $record->dispatch_count,
            'success_count' => $success,
            'failure_count' => (int) $record->failure_count,
            'cancelled_count' => (int) $record->cancelled_count,
            'partial_count' => (int) $record->partial_count,
            'fallback_count' => (int) $record->fallback_count,
            'billable_unit_quantity' => (int) $record->billable_unit_quantity,
            'allocated_inr_microunits' => $allocated,
            'first_result_p50_ms' => $this->percentile($record->first_result_latency_histogram, 50),
            'first_result_p95_ms' => $this->percentile($record->first_result_latency_histogram, 95),
            'completion_p95_ms' => $this->percentile($record->completion_latency_histogram, 95),
            'first_audible_p95_ms' => $this->percentile($record->first_audible_latency_histogram, 95),
            'cost_per_successful_turn_inr_microunits' => $success > 0
                ? intdiv($allocated, $success)
                : null,
        ];
    }

    private function percentile(mixed $encoded, int $percentile): ?int
    {
        $histogram = is_string($encoded)
            ? json_decode($encoded, true)
            : (is_array($encoded) ? $encoded : null);
        if (! is_array($histogram)) {
            return null;
        }
        $order = [
            'le_100' => 100,
            'le_250' => 250,
            'le_500' => 500,
            'le_1000' => 1000,
            'le_2000' => 2000,
            'le_4000' => 4000,
            'le_8000' => 8000,
            'le_16000' => 16000,
            'over_16000' => 16000,
        ];
        $total = 0;
        foreach (array_keys($order) as $bucket) {
            $total += (int) ($histogram[$bucket] ?? 0);
        }
        if ($total < 1) {
            return null;
        }
        $threshold = (int) ceil($total * $percentile / 100);
        $seen = 0;
        foreach ($order as $bucket => $ms) {
            $seen += (int) ($histogram[$bucket] ?? 0);
            if ($seen >= $threshold) {
                return $ms;
            }
        }

        return 16000;
    }

    /**
     * @return array{
     *   active: bool,
     *   approved_spend_cap_inr: int,
     *   estimated_spend_inr_microunits: int,
     *   warning_threshold_percent: int
     * }
     */
    private function budgetAlert(string $organizationId): array
    {
        $capInr = (int) config('conversation.budget_alert.approved_spend_cap_inr', 0);
        $threshold = max(
            1,
            min(
                100,
                (int) config(
                    'conversation.budget_alert.warning_threshold_percent',
                    80,
                ),
            ),
        );
        $estimatedMicrounits = (int) DB::table('ai_cost_ledger')
            ->where('organization_id', $organizationId)
            ->sum('estimated_cost_microunits');
        $capMicrounits = $capInr * 1_000_000;
        $active = $capMicrounits > 0
            && $estimatedMicrounits >= (int) floor($capMicrounits * ($threshold / 100));

        return [
            'active' => $active,
            'approved_spend_cap_inr' => $capInr,
            'estimated_spend_inr_microunits' => $estimatedMicrounits,
            'warning_threshold_percent' => $threshold,
        ];
    }
}

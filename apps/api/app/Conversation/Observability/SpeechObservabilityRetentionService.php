<?php

declare(strict_types=1);

namespace App\Conversation\Observability;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class SpeechObservabilityRetentionService
{
    /**
     * @return array{
     *   provider_attempts_aggregated: int,
     *   charge_components_aggregated: int,
     *   client_samples_aggregated: int,
     *   source_rows_deleted: int
     * }
     */
    public function purgeDue(?int $limit = null): array
    {
        $batchSize = min(1000, max(1, $limit ?? (int) config(
            'speech_observability.retention_batch_size',
            500,
        )));
        $now = Date::now()->toImmutable();

        return DB::transaction(function () use ($batchSize, $now): array {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::select(
                    'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
                    ['speech-observability-retention-v1'],
                );
            }

            $providerRows = DB::table('speech_provider_usage_read_model')
                ->where('expires_at', '<=', $now)
                ->orderBy('recorded_at')
                ->orderBy('source_id')
                ->limit($batchSize)
                ->get();

            $detailIds = $providerRows
                ->pluck('speech_provider_attempt_detail_id')
                ->filter(static fn (mixed $id): bool => is_string($id))
                ->values();
            $chargeRows = $detailIds->isEmpty()
                ? collect()
                : DB::table('speech_provider_charge_usage_read_model')
                    ->whereIn(
                        'speech_provider_attempt_detail_id',
                        $detailIds->all(),
                    )
                    ->orderBy('charge_id')
                    ->get();

            foreach ($providerRows as $row) {
                $this->aggregateProviderAttempt($row, $now);
            }
            foreach ($chargeRows as $row) {
                $this->aggregateProviderCharge($row, $now);
            }

            $remaining = max(0, $batchSize - $providerRows->count());
            $clientRows = $remaining === 0
                ? collect()
                : DB::table('speech_client_latency_samples')
                    ->where('expires_at', '<=', $now)
                    ->orderBy('recorded_at')
                    ->orderBy('id')
                    ->limit($remaining)
                    ->get();

            foreach ($clientRows as $row) {
                $this->aggregateClientSample($row, $now);
            }

            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement(
                    "SELECT set_config('app.speech_usage_retention_purge', 'on', true)",
                );
            }

            $deleted = 0;
            foreach ($providerRows->groupBy('source_ledger') as $ledger => $rows) {
                $table = match ($ledger) {
                    'ai_cost_ledger' => 'ai_cost_ledger',
                    'voice_provider_runs' => 'voice_provider_runs',
                    default => null,
                };
                if ($table === null) {
                    continue;
                }
                $deleted += DB::table($table)
                    ->whereIn('id', $rows->pluck('source_id')->all())
                    ->delete();
            }
            if ($clientRows->isNotEmpty()) {
                $deleted += DB::table('speech_client_latency_samples')
                    ->whereIn('id', $clientRows->pluck('id')->all())
                    ->delete();
            }

            return [
                'provider_attempts_aggregated' => $providerRows->count(),
                'charge_components_aggregated' => $chargeRows->count(),
                'client_samples_aggregated' => $clientRows->count(),
                'source_rows_deleted' => $deleted,
            ];
        }, 3);
    }

    private function aggregateProviderAttempt(
        stdClass $row,
        CarbonImmutable $now,
    ): void {
        $status = in_array((string) $row->result_status, [
            'succeeded', 'failed', 'cancelled', 'partial',
        ], true) ? (string) $row->result_status : 'failed';
        $dimension = [
            'organization_id' => (string) $row->organization_id,
            'usage_date' => CarbonImmutable::parse((string) $row->recorded_at)
                ->toDateString(),
            'environment' => (string) $row->environment,
            'aggregate_kind' => 'provider_attempt',
            'provider_stage' => (string) $row->provider_stage,
            'provider_key' => (string) $row->provider_key,
            'model_version' => (string) $row->model_version,
            'route_role' => (string) $row->route_role,
            'locale' => is_string($row->locale) && $row->locale !== ''
                ? $row->locale
                : 'unknown',
            'conversation_mode' => (string) $row->conversation_mode,
            'result_status' => $status,
            'dispatch_kind' => (string) $row->dispatch_kind,
            'billable_unit_kind' => 'unknown',
            'native_currency' => 'unknown',
            'reconciliation_status' => 'attempt_only',
            'sample_rate_bps' => '0',
        ];
        $this->incrementAggregate($dimension, [
            'attempt_count' => 1,
            'dispatch_count' => $row->dispatch_kind === 'provider_dispatch' ? 1 : 0,
            'success_count' => $status === 'succeeded' ? 1 : 0,
            'failure_count' => $status === 'failed' ? 1 : 0,
            'cancelled_count' => $status === 'cancelled' ? 1 : 0,
            'partial_count' => $status === 'partial' ? 1 : 0,
            'fallback_count' => $row->route_role === 'fallback' ? 1 : 0,
            'billable_unit_quantity' => 0,
            'estimated_native_cost_microunits' => 0,
            'estimated_inr_cost_microunits' => 0,
            'first_result_latency_ms' => $row->first_result_latency_ms === null
                ? null
                : (int) $row->first_result_latency_ms,
            'completion_latency_ms' => (int) $row->completion_latency_ms,
            'first_audible_latency_ms' => null,
            'timing_sample_count' => 1,
            'source_sample_count' => 1,
            'recorded_at' => (string) $row->recorded_at,
        ], $now);
    }

    private function aggregateProviderCharge(
        stdClass $row,
        CarbonImmutable $now,
    ): void {
        $status = in_array((string) $row->result_status, [
            'succeeded', 'failed', 'cancelled', 'partial',
        ], true) ? (string) $row->result_status : 'failed';
        $dimension = [
            'organization_id' => (string) $row->organization_id,
            'usage_date' => CarbonImmutable::parse((string) $row->recorded_at)
                ->toDateString(),
            'environment' => (string) $row->environment,
            'aggregate_kind' => 'provider_charge',
            'provider_stage' => (string) $row->provider_stage,
            'provider_key' => (string) $row->provider_key,
            'model_version' => (string) $row->model_version,
            'route_role' => (string) $row->route_role,
            'locale' => is_string($row->locale) && $row->locale !== ''
                ? $row->locale
                : 'unknown',
            'conversation_mode' => (string) $row->conversation_mode,
            'result_status' => $status,
            'dispatch_kind' => (string) $row->dispatch_kind,
            'billable_unit_kind' => (string) $row->billable_unit_kind,
            'native_currency' => is_string($row->native_currency)
                && $row->native_currency !== ''
                ? $row->native_currency
                : 'unknown',
            'reconciliation_status' => (string) $row->reconciliation_status,
            'sample_rate_bps' => '0',
        ];
        $this->incrementAggregate($dimension, [
            'attempt_count' => 0,
            'dispatch_count' => 0,
            'success_count' => 0,
            'failure_count' => 0,
            'cancelled_count' => 0,
            'partial_count' => 0,
            'fallback_count' => 0,
            'billable_unit_quantity' => (int) $row->billable_unit_quantity,
            'estimated_native_cost_microunits' => $row->estimated_native_cost_microunits === null
                ? 0
                : (int) $row->estimated_native_cost_microunits,
            'estimated_inr_cost_microunits' => $row->estimated_inr_cost_microunits === null
                ? 0
                : (int) $row->estimated_inr_cost_microunits,
            'first_result_latency_ms' => null,
            'completion_latency_ms' => null,
            'first_audible_latency_ms' => null,
            'timing_sample_count' => 0,
            'source_sample_count' => 1,
            'recorded_at' => (string) $row->recorded_at,
        ], $now);
    }

    private function aggregateClientSample(
        stdClass $row,
        CarbonImmutable $now,
    ): void {
        $dimension = [
            'organization_id' => (string) $row->organization_id,
            'usage_date' => CarbonImmutable::parse((string) $row->recorded_at)
                ->toDateString(),
            'environment' => (string) $row->environment,
            'aggregate_kind' => 'client_latency',
            'provider_stage' => 'end_to_end',
            'provider_key' => 'client',
            'model_version' => (string) $row->sample_policy_version,
            'route_role' => 'client',
            'locale' => 'unknown',
            'conversation_mode' => (string) $row->conversation_mode,
            'result_status' => (string) $row->playback_outcome,
            'dispatch_kind' => 'client_sample',
            'billable_unit_kind' => 'unknown',
            'native_currency' => 'unknown',
            'reconciliation_status' => 'not_billable',
            'sample_rate_bps' => (string) $row->sample_rate_bps,
        ];
        $this->incrementAggregate($dimension, [
            'attempt_count' => 1,
            'dispatch_count' => 0,
            'success_count' => $row->playback_outcome === 'started' ? 1 : 0,
            'failure_count' => $row->playback_outcome === 'failed' ? 1 : 0,
            'cancelled_count' => $row->playback_outcome === 'cancelled' ? 1 : 0,
            'partial_count' => 0,
            'fallback_count' => 0,
            'billable_unit_quantity' => 0,
            'estimated_native_cost_microunits' => 0,
            'estimated_inr_cost_microunits' => 0,
            'first_result_latency_ms' => $row->release_to_first_signal_ms === null
                ? null
                : (int) $row->release_to_first_signal_ms,
            'completion_latency_ms' => null,
            'first_audible_latency_ms' => $row->release_to_first_audio_ms === null
                ? null
                : (int) $row->release_to_first_audio_ms,
            'timing_sample_count' => 1,
            'source_sample_count' => 1,
            'recorded_at' => (string) $row->recorded_at,
        ], $now);
    }

    /**
     * @param  array<string, string>  $dimension
     * @param  array<string, int|string|null>  $values
     */
    private function incrementAggregate(
        array $dimension,
        array $values,
        CarbonImmutable $now,
    ): void {
        $existing = DB::table('speech_usage_daily_aggregates')
            ->where($dimension)
            ->lockForUpdate()
            ->first();
        $empty = $this->emptyHistogram();

        if (! $existing instanceof stdClass) {
            $attemptCount = (int) $values['attempt_count'];
            $sourceCount = (int) $values['source_sample_count'];
            DB::table('speech_usage_daily_aggregates')->insert([
                'id' => (string) Str::ulid(),
                ...$dimension,
                'attempt_count' => $attemptCount,
                'dispatch_count' => (int) $values['dispatch_count'],
                'success_count' => (int) $values['success_count'],
                'failure_count' => (int) $values['failure_count'],
                'cancelled_count' => (int) $values['cancelled_count'],
                'partial_count' => (int) $values['partial_count'],
                'fallback_count' => (int) $values['fallback_count'],
                'billable_unit_quantity' => (int) $values['billable_unit_quantity'],
                'estimated_native_cost_microunits' => (int) $values['estimated_native_cost_microunits'],
                'estimated_inr_cost_microunits' => (int) $values['estimated_inr_cost_microunits'],
                'first_result_latency_histogram' => json_encode(
                    $this->addHistogram($empty, $values['first_result_latency_ms']),
                    JSON_THROW_ON_ERROR,
                ),
                'completion_latency_histogram' => json_encode(
                    $this->addHistogram($empty, $values['completion_latency_ms']),
                    JSON_THROW_ON_ERROR,
                ),
                'first_audible_latency_histogram' => json_encode(
                    $this->addHistogram($empty, $values['first_audible_latency_ms']),
                    JSON_THROW_ON_ERROR,
                ),
                'timing_sample_count' => (int) $values['timing_sample_count'],
                'source_sample_count' => $sourceCount,
                'small_cell_suppressed' => $sourceCount < (int) config(
                    'speech_observability.small_cell_minimum',
                    10,
                ),
                'first_recorded_at' => $values['recorded_at'],
                'last_recorded_at' => $values['recorded_at'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        $attemptCount = (int) $existing->attempt_count
            + (int) $values['attempt_count'];
        $sourceCount = (int) $existing->source_sample_count
            + (int) $values['source_sample_count'];
        DB::table('speech_usage_daily_aggregates')
            ->where('id', (string) $existing->id)
            ->update([
                'attempt_count' => $attemptCount,
                'dispatch_count' => (int) $existing->dispatch_count + (int) $values['dispatch_count'],
                'success_count' => (int) $existing->success_count + (int) $values['success_count'],
                'failure_count' => (int) $existing->failure_count + (int) $values['failure_count'],
                'cancelled_count' => (int) $existing->cancelled_count + (int) $values['cancelled_count'],
                'partial_count' => (int) $existing->partial_count + (int) $values['partial_count'],
                'fallback_count' => (int) $existing->fallback_count + (int) $values['fallback_count'],
                'billable_unit_quantity' => (int) $existing->billable_unit_quantity + (int) $values['billable_unit_quantity'],
                'estimated_native_cost_microunits' => (int) $existing->estimated_native_cost_microunits + (int) $values['estimated_native_cost_microunits'],
                'estimated_inr_cost_microunits' => (int) $existing->estimated_inr_cost_microunits + (int) $values['estimated_inr_cost_microunits'],
                'first_result_latency_histogram' => json_encode(
                    $this->addHistogram(
                        $this->decodeHistogram($existing->first_result_latency_histogram),
                        $values['first_result_latency_ms'],
                    ),
                    JSON_THROW_ON_ERROR,
                ),
                'completion_latency_histogram' => json_encode(
                    $this->addHistogram(
                        $this->decodeHistogram($existing->completion_latency_histogram),
                        $values['completion_latency_ms'],
                    ),
                    JSON_THROW_ON_ERROR,
                ),
                'first_audible_latency_histogram' => json_encode(
                    $this->addHistogram(
                        $this->decodeHistogram($existing->first_audible_latency_histogram),
                        $values['first_audible_latency_ms'],
                    ),
                    JSON_THROW_ON_ERROR,
                ),
                'timing_sample_count' => (int) $existing->timing_sample_count
                    + (int) $values['timing_sample_count'],
                'source_sample_count' => $sourceCount,
                'small_cell_suppressed' => $sourceCount < (int) config(
                    'speech_observability.small_cell_minimum',
                    10,
                ),
                'last_recorded_at' => $values['recorded_at'],
                'updated_at' => $now,
            ]);
    }

    /** @return array<string, int> */
    private function emptyHistogram(): array
    {
        return [
            'le_100' => 0,
            'le_250' => 0,
            'le_500' => 0,
            'le_1000' => 0,
            'le_2000' => 0,
            'le_4000' => 0,
            'le_8000' => 0,
            'le_16000' => 0,
            'over_16000' => 0,
        ];
    }

    /**
     * @param  array<string, int>  $histogram
     * @return array<string, int>
     */
    private function addHistogram(array $histogram, int|string|null $latency): array
    {
        if ($latency === null) {
            return $histogram;
        }
        $value = (int) $latency;
        $bucket = match (true) {
            $value <= 100 => 'le_100',
            $value <= 250 => 'le_250',
            $value <= 500 => 'le_500',
            $value <= 1000 => 'le_1000',
            $value <= 2000 => 'le_2000',
            $value <= 4000 => 'le_4000',
            $value <= 8000 => 'le_8000',
            $value <= 16000 => 'le_16000',
            default => 'over_16000',
        };
        $histogram[$bucket]++;

        return $histogram;
    }

    /** @return array<string, int> */
    private function decodeHistogram(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        if (! is_array($decoded)) {
            return $this->emptyHistogram();
        }
        $histogram = $this->emptyHistogram();
        foreach ($histogram as $bucket => $_count) {
            $histogram[$bucket] = max(0, (int) ($decoded[$bucket] ?? 0));
        }

        return $histogram;
    }
}

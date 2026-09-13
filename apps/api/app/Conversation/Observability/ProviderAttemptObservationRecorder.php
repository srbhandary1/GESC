<?php

declare(strict_types=1);

namespace App\Conversation\Observability;

use App\Conversation\Providers\ProviderBillableUsage;
use App\Conversation\Providers\ProviderUsage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ProviderAttemptObservationRecorder
{
    public function __construct(private SpeechRateCardResolver $rates) {}

    public function recordAiLedger(
        string $organizationId,
        string $ledgerId,
        string $sessionId,
        string $stage,
        string $semanticOperation,
        string $routeRole,
        string $resultStatus,
        ?string $safeFailureCode,
        ProviderUsage $usage,
        CarbonImmutable $recordedAt,
    ): void {
        $this->record(
            organizationId: $organizationId,
            aiLedgerId: $ledgerId,
            voiceRunId: null,
            sessionId: $sessionId,
            stage: $stage,
            semanticOperation: $semanticOperation,
            routeRole: $routeRole,
            resultStatus: $resultStatus,
            safeFailureCode: $safeFailureCode,
            usage: $usage,
            recordedAt: $recordedAt,
        );
    }

    public function recordVoiceRun(
        string $organizationId,
        string $runId,
        string $sessionId,
        string $stage,
        string $semanticOperation,
        string $routeRole,
        string $resultStatus,
        ?string $safeFailureCode,
        ProviderUsage $usage,
        CarbonImmutable $recordedAt,
    ): void {
        $this->record(
            organizationId: $organizationId,
            aiLedgerId: null,
            voiceRunId: $runId,
            sessionId: $sessionId,
            stage: $stage,
            semanticOperation: $semanticOperation,
            routeRole: $routeRole,
            resultStatus: $resultStatus,
            safeFailureCode: $safeFailureCode,
            usage: $usage,
            recordedAt: $recordedAt,
        );
    }

    private function record(
        string $organizationId,
        ?string $aiLedgerId,
        ?string $voiceRunId,
        string $sessionId,
        string $stage,
        string $semanticOperation,
        string $routeRole,
        string $resultStatus,
        ?string $safeFailureCode,
        ProviderUsage $usage,
        CarbonImmutable $recordedAt,
    ): void {
        $components = $usage->normalizedBillableComponents();
        /**
         * @var list<array{
         *   component: ProviderBillableUsage,
         *   quote: SpeechRateQuote|null,
         *   status: string
         * }> $outcomes
         */
        $outcomes = [];
        foreach ($components as $component) {
            $quote = $this->rates->quoteComponent(
                $stage,
                $usage,
                $component,
                $recordedAt,
            );
            $outcomes[] = [
                'component' => $component,
                'quote' => $quote,
                'status' => $quote !== null
                    ? 'unreconciled'
                    : $this->rates->componentResolutionStatus(
                        $stage,
                        $usage,
                        $component,
                        $recordedAt,
                    ),
            ];
        }
        $allQuoted = $outcomes !== [];
        $currencyValues = [];
        $nativeCostMicrounits = 0;
        $inrCostMicrounits = 0;
        foreach ($outcomes as $outcome) {
            $quote = $outcome['quote'];
            if (! $quote instanceof SpeechRateQuote) {
                $allQuoted = false;

                continue;
            }
            $currencyValues[$quote->nativeCurrency] = true;
            $nativeCostMicrounits += $quote->nativeCostMicrounits;
            $inrCostMicrounits += $quote->inrCostMicrounits;
        }
        $summaryCurrency = $allQuoted && count($currencyValues) === 1
            ? array_key_first($currencyValues)
            : null;
        if ($outcomes === []) {
            $summaryStatus = $this->rates->unresolvedStatus(
                $stage,
                $usage,
                $recordedAt,
            );
        } elseif (collect($outcomes)->pluck('status')->unique()->count() === 1) {
            $summaryStatus = (string) $outcomes[0]['status'];
        } else {
            $summaryStatus = 'partially_rated';
        }
        $singleOutcome = count($outcomes) === 1 ? $outcomes[0] : null;
        $singleQuote = $singleOutcome !== null
            && $singleOutcome['quote'] instanceof SpeechRateQuote
            ? $singleOutcome['quote']
            : null;
        $detailId = (string) Str::ulid();
        $conversationMode = DB::table('conversation_sessions')
            ->where('organization_id', $organizationId)
            ->where('id', $sessionId)
            ->value('mode');
        $expiresAt = $recordedAt->addDays((int) config(
            'speech_observability.attempt_retention_days',
            30,
        ));

        DB::transaction(function () use (
            $detailId,
            $organizationId,
            $aiLedgerId,
            $voiceRunId,
            $conversationMode,
            $semanticOperation,
            $routeRole,
            $stage,
            $usage,
            $resultStatus,
            $safeFailureCode,
            $singleOutcome,
            $singleQuote,
            $allQuoted,
            $summaryCurrency,
            $nativeCostMicrounits,
            $inrCostMicrounits,
            $summaryStatus,
            $recordedAt,
            $expiresAt,
            $outcomes,
        ): void {
            DB::table('speech_provider_attempt_details')->insert([
                'id' => $detailId,
                'organization_id' => $organizationId,
                'ai_cost_ledger_id' => $aiLedgerId,
                'voice_provider_run_id' => $voiceRunId,
                'environment' => app()->environment(),
                'conversation_mode' => is_string($conversationMode)
                    ? $conversationMode
                    : 'unknown',
                'semantic_operation' => $semanticOperation,
                'route_role' => $routeRole,
                'dispatch_kind' => $usage->dispatchKind,
                'provider_stage' => $stage,
                'provider_key' => $usage->providerKey,
                'model_version' => $usage->modelVersion,
                'api_version' => $usage->apiVersion,
                'voice_key' => $usage->voiceKey,
                'locale' => $usage->locale,
                'region' => $usage->region,
                'configuration_sha256' => $usage->configurationSha256,
                'result_status' => $resultStatus,
                'safe_failure_code' => $safeFailureCode,
                'connection_latency_ms' => $usage->connectionLatencyMilliseconds,
                'first_result_latency_ms' => $usage->firstResultLatencyMilliseconds,
                'completion_latency_ms' => max(0, $usage->latencyMilliseconds),
                'input_unit_kind' => $usage->inputUnitKind,
                'input_unit_quantity' => max(0, $usage->inputUnits),
                'output_unit_kind' => $usage->outputUnitKind,
                'output_unit_quantity' => max(0, $usage->outputUnits),
                'billable_unit_kind' => $singleOutcome['component']->unitKind ?? null,
                'billable_unit_quantity' => $singleOutcome['component']->quantity ?? null,
                'speech_rate_card_id' => $singleQuote?->rateCardId,
                'native_currency' => $summaryCurrency,
                'estimated_native_cost_microunits' => $summaryCurrency !== null
                    ? $nativeCostMicrounits
                    : null,
                'estimated_inr_cost_microunits' => $allQuoted
                    ? $inrCostMicrounits
                    : null,
                'reconciliation_status' => $summaryStatus,
                'recorded_at' => $recordedAt,
                'expires_at' => $expiresAt,
            ]);

            foreach ($outcomes as $outcome) {
                $component = $outcome['component'];
                $quote = $outcome['quote'];
                DB::table('speech_provider_attempt_charges')->insert([
                    'id' => (string) Str::ulid(),
                    'organization_id' => $organizationId,
                    'speech_provider_attempt_detail_id' => $detailId,
                    'component_key' => $component->componentKey,
                    'billable_unit_kind' => $component->unitKind,
                    'billable_unit_quantity' => $component->quantity,
                    'speech_rate_card_id' => $quote?->rateCardId,
                    'native_currency' => $quote?->nativeCurrency,
                    'estimated_native_cost_microunits' => $quote?->nativeCostMicrounits,
                    'estimated_inr_cost_microunits' => $quote?->inrCostMicrounits,
                    'reconciliation_status' => $outcome['status'],
                    'recorded_at' => $recordedAt,
                    'expires_at' => $expiresAt,
                ]);
            }
        }, 3);
    }
}

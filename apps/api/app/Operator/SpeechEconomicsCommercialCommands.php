<?php

declare(strict_types=1);

namespace App\Operator;

use App\Audit\AuditEventRecorder;
use App\Conversation\Observability\SpeechProviderStatementRecorder;
use App\Conversation\Observability\SpeechRateCardRecorder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class SpeechEconomicsCommercialCommands
{
    public function __construct(private SpeechEconomicsAuthorization $authorization, private SpeechRateCardRecorder $rateCards, private SpeechProviderStatementRecorder $statements, private AuditEventRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{rate_card_id: string, replayed: bool}
     */
    public function recordRateCard(User $actor, array $payload): array
    {
        $this->authorization->assertCommercial($actor);

        return DB::transaction(function () use ($payload): array {
            $card = $payload;
            unset($card['conversion_observed_at'], $card['effective_from'], $card['effective_until'], $card['idempotency_key']);
            $result = $this->rateCards->record($card, CarbonImmutable::parse((string) $payload['conversion_observed_at']), CarbonImmutable::parse((string) $payload['effective_from']), isset($payload['effective_until']) ? CarbonImmutable::parse((string) $payload['effective_until']) : null);
            $this->audit->recordTenant('speech.economics.rate_card.recorded', 'speech_rate_card', $result['rate_card_id']);

            return $result;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{statement_id: string, replayed: bool, reconciliations: int}
     */
    public function recordStatement(User $actor, array $payload): array
    {
        $this->authorization->assertCommercial($actor);

        return DB::transaction(function () use ($payload): array {
            $result = $this->statements->record(
                (string) $payload['provider_key'], (string) $payload['statement_reference_sha256'],
                CarbonImmutable::parse((string) $payload['period_start']), CarbonImmutable::parse((string) $payload['period_end']),
                (string) $payload['billing_currency'], (int) $payload['gross_cost_microunits'], (int) $payload['tax_microunits'],
                (int) $payload['discount_microunits'], (int) $payload['credit_microunits'], (int) $payload['net_cost_microunits'],
                (int) $payload['inr_per_native_unit_microunits'], (string) $payload['conversion_source'], CarbonImmutable::parse((string) $payload['conversion_observed_at']),
                (string) $payload['evidence_sha256'], (string) $payload['approval_evidence'], $payload['lines'],
            );
            $this->audit->recordTenant('speech.economics.statement.recorded', 'speech_provider_statement', $result['statement_id']);

            return $result;
        }, 3);
    }
}

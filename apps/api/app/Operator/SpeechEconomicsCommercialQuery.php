<?php

declare(strict_types=1);

namespace App\Operator;

use App\Audit\AuditEventRecorder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class SpeechEconomicsCommercialQuery
{
    public function __construct(
        private SpeechEconomicsAuthorization $authorization,
        private AuditEventRecorder $audit,
    ) {}

    /**
     * @return array{
     *   generated_at: string,
     *   presentation: 'commercial_terms',
     *   empty: bool,
     *   rate_cards: list<array<string, mixed>>,
     *   statements: list<array<string, mixed>>
     * }
     */
    public function show(User $actor): array
    {
        $this->authorization->assertCommercial($actor);
        $this->audit->recordTenant(
            'speech.economics.commercial.read',
            'speech_commercial_read',
            (string) Str::ulid(),
        );

        $rateCards = [];
        foreach (DB::table('speech_rate_cards')->orderBy('recorded_at')->get() as $row) {
            $rateCards[] = [
                'id' => (string) $row->id,
                'provider_key' => (string) $row->provider_key,
                'provider_stage' => (string) $row->provider_stage,
                'model_version' => (string) $row->model_version,
                'rate_version' => (string) $row->rate_version,
                'billable_unit_kind' => (string) $row->billable_unit_kind,
                'billing_currency' => (string) $row->billing_currency,
                'gross_price_microunits' => (int) $row->gross_price_microunits,
                'tax_basis_points' => (int) $row->tax_basis_points,
                'discount_basis_points' => (int) $row->discount_basis_points,
                'credit_microunits_per_basis' => (int) $row->credit_microunits_per_basis,
                'inr_per_native_unit_microunits' => (int) $row->inr_per_native_unit_microunits,
                'conversion_source' => (string) $row->conversion_source,
                'approval_evidence' => (string) $row->approval_evidence,
                'effective_from' => (string) $row->effective_from,
                'effective_until' => $row->effective_until === null ? null : (string) $row->effective_until,
            ];
        }

        $statements = [];
        foreach (DB::table('speech_provider_statements')->orderBy('recorded_at')->get() as $row) {
            $statements[] = [
                'id' => (string) $row->id,
                'provider_key' => (string) $row->provider_key,
                'period_start' => (string) $row->period_start,
                'period_end' => (string) $row->period_end,
                'billing_currency' => (string) $row->billing_currency,
                'net_cost_microunits' => (int) $row->net_cost_microunits,
                'net_cost_inr_microunits' => (int) $row->net_cost_inr_microunits,
                'approval_evidence' => (string) $row->approval_evidence,
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'presentation' => 'commercial_terms',
            'empty' => $rateCards === [] && $statements === [],
            'rate_cards' => $rateCards,
            'statements' => $statements,
        ];
    }
}

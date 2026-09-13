<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator;

use Illuminate\Validation\Rule;

final class RecordSpeechProviderStatementRequest extends StrictSpeechEconomicsCommandRequest
{
    private const array KEYS = ['provider_key', 'statement_reference_sha256', 'period_start', 'period_end', 'billing_currency', 'gross_cost_microunits', 'tax_microunits', 'discount_microunits', 'credit_microunits', 'net_cost_microunits', 'inr_per_native_unit_microunits', 'conversion_source', 'conversion_observed_at', 'evidence_sha256', 'approval_evidence', 'lines'];

    protected function allowedPayloadKeys(): array
    {
        return self::KEYS;
    }

    protected function payloadRules(): array
    {
        return [
            'provider_key' => ['required', 'regex:/\A[a-z0-9][a-z0-9._-]{0,63}\z/'],
            'statement_reference_sha256' => ['required', 'regex:/\A[0-9a-f]{64}\z/'],
            'period_start' => ['required', 'date_format:Y-m-d'], 'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'billing_currency' => ['required', 'regex:/\A[A-Z]{3}\z/'],
            'gross_cost_microunits' => ['required', 'integer', 'min:0'], 'tax_microunits' => ['required', 'integer', 'min:0'],
            'discount_microunits' => ['required', 'integer', 'min:0'], 'credit_microunits' => ['required', 'integer', 'min:0'],
            'net_cost_microunits' => ['required', 'integer', 'min:0'], 'inr_per_native_unit_microunits' => ['required', 'integer', 'min:1'],
            'conversion_source' => ['required', 'string', 'max:160'], 'conversion_observed_at' => ['required', 'date'],
            'evidence_sha256' => ['required', 'regex:/\A[0-9a-f]{64}\z/'], 'approval_evidence' => ['required', 'string', 'max:64'],
            'lines' => ['required', 'array', 'min:1', 'max:30'], 'lines.*' => ['array:provider_stage,model_version,billable_unit_kind,observed_billable_quantity,estimated_inr_cost_microunits,allocated_statement_inr_microunits,evidence_sha256,approval_evidence'],
            'lines.*.provider_stage' => ['required', Rule::in(['stt', 'model', 'tts'])], 'lines.*.model_version' => ['required', 'string', 'max:100'],
            'lines.*.billable_unit_kind' => ['required', 'regex:/\A[a-z][a-z0-9._-]{0,39}\z/'],
            'lines.*.observed_billable_quantity' => ['required', 'integer', 'min:0'], 'lines.*.estimated_inr_cost_microunits' => ['required', 'integer', 'min:0'],
            'lines.*.allocated_statement_inr_microunits' => ['required', 'integer', 'min:0'], 'lines.*.evidence_sha256' => ['required', 'regex:/\A[0-9a-f]{64}\z/'],
            'lines.*.approval_evidence' => ['required', 'string', 'max:64'],
        ];
    }
}

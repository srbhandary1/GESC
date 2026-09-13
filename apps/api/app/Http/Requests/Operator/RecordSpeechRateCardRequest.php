<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator;

use Illuminate\Validation\Rule;

final class RecordSpeechRateCardRequest extends StrictSpeechEconomicsCommandRequest
{
    private const array KEYS = ['provider_key', 'provider_stage', 'model_version', 'api_version', 'voice_key', 'region', 'configuration_sha256', 'rate_version', 'billable_unit_kind', 'unit_basis', 'minimum_billable_units', 'tier_start_units', 'tier_end_units', 'billing_currency', 'gross_price_microunits', 'tax_basis_points', 'discount_basis_points', 'credit_microunits_per_basis', 'inr_per_native_unit_microunits', 'conversion_source', 'conversion_observed_at', 'source_evidence_sha256', 'approval_evidence', 'effective_from', 'effective_until'];

    protected function allowedPayloadKeys(): array
    {
        return self::KEYS;
    }

    protected function payloadRules(): array
    {
        return [
            'provider_key' => ['required', 'string', 'regex:/\A[a-z0-9][a-z0-9._-]{0,63}\z/'],
            'provider_stage' => ['required', Rule::in(['stt', 'model', 'tts'])],
            'model_version' => ['required', 'string', 'max:100'], 'api_version' => ['nullable', 'string', 'max:64'],
            'voice_key' => ['nullable', 'string', 'max:100'], 'region' => ['nullable', 'string', 'max:64'],
            'configuration_sha256' => ['nullable', 'regex:/\A[0-9a-f]{64}\z/'],
            'rate_version' => ['required', 'string', 'regex:/\A[a-zA-Z0-9._:-]{1,80}\z/'],
            'billable_unit_kind' => ['required', 'regex:/\A[a-z][a-z0-9._-]{0,39}\z/'],
            'unit_basis' => ['required', 'integer', 'min:1'], 'minimum_billable_units' => ['required', 'integer', 'min:0'],
            'tier_start_units' => ['required', 'integer', 'min:0'], 'tier_end_units' => ['nullable', 'integer', 'gt:tier_start_units'],
            'billing_currency' => ['required', 'regex:/\A[A-Z]{3}\z/'], 'gross_price_microunits' => ['required', 'integer', 'min:0'],
            'tax_basis_points' => ['required', 'integer', 'between:0,10000'], 'discount_basis_points' => ['required', 'integer', 'between:0,10000'],
            'credit_microunits_per_basis' => ['required', 'integer', 'min:0'], 'inr_per_native_unit_microunits' => ['required', 'integer', 'min:1'],
            'conversion_source' => ['required', 'string', 'max:160'], 'conversion_observed_at' => ['required', 'date'],
            'source_evidence_sha256' => ['required', 'regex:/\A[0-9a-f]{64}\z/'], 'approval_evidence' => ['required', 'string', 'max:64'],
            'effective_from' => ['required', 'date'], 'effective_until' => ['nullable', 'date', 'after:effective_from'],
        ];
    }
}

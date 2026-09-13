<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\EntitlementAdministration;

final class UpdateEntitlementPlanRequest extends StrictEntitlementAdministrationCommandRequest
{
    protected function allowedPayloadKeys(): array
    {
        return [
            'allows_roleplay',
            'amount_ex_gst_paise',
            'duration_value',
            'expected_version',
            'live_ai_cap_seconds',
            'max_level_number',
            'name',
            'status',
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function payloadRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:120'],
            'amount_ex_gst_paise' => ['required', 'integer', 'min:0', 'max:10000000'],
            'duration_value' => ['required', 'integer', 'min:1', 'max:8760'],
            'allows_roleplay' => ['required', 'boolean'],
            'max_level_number' => ['nullable', 'integer', 'min:1', 'max:17'],
            'live_ai_cap_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'status' => ['required', 'string', 'in:active,retired'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }
}

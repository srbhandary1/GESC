<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\EntitlementAdministration;

final class UpdateEntitlementTaxRequest extends StrictEntitlementAdministrationCommandRequest
{
    protected function allowedPayloadKeys(): array
    {
        return ['expected_version', 'gst_rate_bps', 'gst_registered', 'gstin'];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function payloadRules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'gst_registered' => ['required', 'boolean'],
            'gstin' => ['required', 'string', 'size:15'],
            'gst_rate_bps' => ['required', 'integer', 'min:0', 'max:5000'],
        ];
    }
}

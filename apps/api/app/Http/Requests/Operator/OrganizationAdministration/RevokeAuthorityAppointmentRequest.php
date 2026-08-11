<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\OrganizationAdministration;

final class RevokeAuthorityAppointmentRequest extends StrictOrganizationAdministrationCommandRequest
{
    protected function payloadRules(): array
    {
        return [
            'reason_key' => ['required', 'string', 'in:operator_revoked'],
            'state_fingerprint' => [
                'required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/',
            ],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return ['reason_key', 'state_fingerprint'];
    }
}

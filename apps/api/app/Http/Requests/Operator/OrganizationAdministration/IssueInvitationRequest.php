<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\OrganizationAdministration;

final class IssueInvitationRequest extends StrictOrganizationAdministrationCommandRequest
{
    protected function payloadRules(): array
    {
        return [
            'membership_type' => ['required', 'string', 'in:trainer,organization_admin'],
            'expires_in_hours' => ['sometimes', 'integer', 'in:24,72,168'],
            'expected_version' => ['required', 'integer', 'in:0'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return ['membership_type', 'expires_in_hours', 'expected_version'];
    }
}

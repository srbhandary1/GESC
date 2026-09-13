<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\OrganizationAdministration;

final class VersionedCommandRequest extends StrictOrganizationAdministrationCommandRequest
{
    protected function payloadRules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return ['expected_version'];
    }
}

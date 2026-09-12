<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\OrganizationAdministration;

final class UpdateBranchRequest extends StrictOrganizationAdministrationCommandRequest
{
    protected function prepareForValidation(): void
    {
        $this->trimStringFields(['name']);
    }

    protected function payloadRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'expected_version' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return ['name', 'expected_version'];
    }
}

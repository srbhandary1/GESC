<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\OrganizationAdministration;

final class CreateCohortRequest extends StrictOrganizationAdministrationCommandRequest
{
    protected function prepareForValidation(): void
    {
        $this->trimStringFields(['key', 'name']);
    }

    protected function payloadRules(): array
    {
        return [
            'key' => ['required', 'string', 'max:80', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
            'name' => ['required', 'string', 'max:160', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'branch_id' => ['sometimes', 'nullable', 'string', 'ulid'],
            'expected_version' => ['required', 'integer', 'in:0'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return ['key', 'name', 'branch_id', 'expected_version'];
    }
}

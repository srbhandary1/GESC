<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\OrganizationAdministration;

final class CreateAuthorityAppointmentRequest extends StrictOrganizationAdministrationCommandRequest
{
    protected function prepareForValidation(): void
    {
        $this->trimStringFields(['qualification_basis', 'approval_evidence_key']);
    }

    protected function payloadRules(): array
    {
        return [
            'authority_kind' => [
                'required', 'string',
                'in:english_academic_reviewer,kannada_reviewer,rights_confirmer',
            ],
            'scope_type' => ['required', 'string', 'in:organization,program'],
            'program_id' => [
                'nullable', 'string', 'ulid', 'required_if:scope_type,program',
                'prohibited_if:scope_type,organization',
            ],
            'valid_from' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'valid_until' => [
                'nullable', 'date_format:Y-m-d\TH:i:sP', 'after:valid_from',
            ],
            'qualification_basis' => [
                'required', 'string', 'max:1000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            ],
            'approval_evidence_key' => [
                'required', 'string', 'max:120', 'regex:/\A[a-z][a-z0-9_.:-]*\z/',
            ],
            'expected_membership_version' => [
                'required', 'integer', 'min:1', 'max:2147483647',
            ],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return [
            'authority_kind',
            'scope_type',
            'program_id',
            'valid_from',
            'valid_until',
            'qualification_basis',
            'approval_evidence_key',
            'expected_membership_version',
        ];
    }
}

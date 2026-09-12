<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\LearnerAdministration;

final class IssueLearnerInvitationRequest extends StrictLearnerAdministrationCommandRequest
{
    protected function payloadRules(): array
    {
        return [
            'role_id' => ['required', 'string', 'ulid'],
            'expected_role_version' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'cohort_id' => ['present', 'nullable', 'string', 'ulid'],
            'expected_cohort_version' => ['present', 'nullable', 'integer', 'min:1', 'max:2147483647'],
            'expires_in_hours' => ['required', 'integer', 'in:24,72,168'],
            'expected_version' => ['required', 'integer', 'in:0'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return [
            'role_id', 'expected_role_version', 'cohort_id',
            'expected_cohort_version', 'expires_in_hours', 'expected_version',
        ];
    }

    protected function nullableVersionPairs(): array
    {
        return [['cohort_id', 'expected_cohort_version']];
    }
}

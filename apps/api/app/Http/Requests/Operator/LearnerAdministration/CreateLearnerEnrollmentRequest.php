<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\LearnerAdministration;

final class CreateLearnerEnrollmentRequest extends StrictLearnerAdministrationCommandRequest
{
    protected function payloadRules(): array
    {
        return [
            'expected_membership_version' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'expected_version' => ['required', 'integer', 'in:0'],
            'cohort_id' => ['present', 'nullable', 'string', 'ulid'],
            'expected_cohort_version' => ['present', 'nullable', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return [
            'expected_membership_version', 'expected_version', 'cohort_id',
            'expected_cohort_version',
        ];
    }

    protected function nullableVersionPairs(): array
    {
        return [['cohort_id', 'expected_cohort_version']];
    }
}

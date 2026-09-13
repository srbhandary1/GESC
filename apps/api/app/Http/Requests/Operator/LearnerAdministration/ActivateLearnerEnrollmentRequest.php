<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\LearnerAdministration;

final class ActivateLearnerEnrollmentRequest extends StrictLearnerAdministrationCommandRequest
{
    protected function payloadRules(): array
    {
        return [
            'publication_id' => ['required', 'string', 'ulid'],
            'share_grant_id' => ['present', 'nullable', 'string', 'ulid'],
            'expected_version' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return ['publication_id', 'share_grant_id', 'expected_version'];
    }
}

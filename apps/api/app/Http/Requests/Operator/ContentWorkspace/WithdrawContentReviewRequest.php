<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\ContentWorkspace;

final class WithdrawContentReviewRequest extends StrictContentCommandRequest
{
    protected function payloadRules(): array
    {
        return [
            'review_kind' => ['required', 'string', 'in:structure,english,kannada'],
            'authority_appointment_id' => ['required', 'string', 'ulid'],
            'rationale_key' => ['required', 'string', 'in:evidence_withdrawn'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return ['review_kind', 'authority_appointment_id', 'rationale_key'];
    }
}

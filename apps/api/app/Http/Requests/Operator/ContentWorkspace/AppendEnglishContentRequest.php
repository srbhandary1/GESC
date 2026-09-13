<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\ContentWorkspace;

use Illuminate\Contracts\Validation\Validator;

final class AppendEnglishContentRequest extends StrictContentCommandRequest
{
    protected function prepareForValidation(): void
    {
        $this->trimStringFields(['content_type']);
    }

    protected function payloadRules(): array
    {
        return [
            'expected_head_revision_id' => ['present', 'nullable', 'string', 'ulid'],
            'expected_revision_number' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'content_type' => ['required', 'string', 'in:assessment_rationale,dialogue,explanation,feedback,instruction,learner_action,model_text,safety_note,title,transcript'],
            'learner_visible' => ['required', 'boolean'],
            'payload' => ['required', 'array'],
            ...$this->governedSourcePayloadRules(),
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return [
            'expected_head_revision_id',
            'expected_revision_number',
            'content_type',
            'learner_visible',
            'payload',
        ];
    }

    protected function validateCommand(Validator $validator): void
    {
        $this->validateGovernedSourcePayload($validator);
    }
}

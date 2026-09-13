<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\ContentWorkspace;

use Illuminate\Contracts\Validation\Validator;

final class CreateContentDocumentRequest extends StrictContentCommandRequest
{
    protected function prepareForValidation(): void
    {
        $this->trimStringFields(['document_key', 'content_type']);
    }

    protected function payloadRules(): array
    {
        return [
            'document_key' => ['required', 'string', 'max:120', 'regex:/\A[a-z0-9][a-z0-9._-]*\z/'],
            'content_type' => ['required', 'string', 'in:assessment_rationale,dialogue,explanation,feedback,instruction,learner_action,model_text,safety_note,title,transcript'],
            'learner_visible' => ['required', 'boolean'],
            'payload' => ['required', 'array'],
            ...$this->governedSourcePayloadRules(),
            'expected_workspace_version' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return [
            'document_key',
            'content_type',
            'learner_visible',
            'payload',
            'expected_workspace_version',
        ];
    }

    protected function validateCommand(Validator $validator): void
    {
        $this->validateGovernedSourcePayload($validator);
    }
}

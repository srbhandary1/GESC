<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\ContentWorkspace;

use Illuminate\Contracts\Validation\Validator;

final class AppendKannadaContentRequest extends StrictContentCommandRequest
{
    protected function payloadRules(): array
    {
        return [
            'source_content_hash' => ['required', 'string', 'regex:/\A[0-9a-f]{64}\z/'],
            'expected_source_revision_number' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'expected_head_variant_id' => ['present', 'nullable', 'string', 'ulid'],
            'expected_variant_revision_number' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'payload' => ['required', 'array'],
            ...$this->governedKannadaPayloadRules(),
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return [
            'source_content_hash',
            'expected_source_revision_number',
            'expected_head_variant_id',
            'expected_variant_revision_number',
            'payload',
        ];
    }

    protected function validateCommand(Validator $validator): void
    {
        $this->validateGovernedKannadaPayload($validator);
    }
}

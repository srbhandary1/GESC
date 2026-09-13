<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\ContentWorkspace;

final class CreateContentSuccessorRequest extends StrictContentCommandRequest
{
    protected function prepareForValidation(): void
    {
        $this->trimStringFields(['successor_version_key']);
    }

    protected function payloadRules(): array
    {
        return [
            'successor_version_key' => ['required', 'string', 'max:100', 'regex:/\A[a-z0-9][a-z0-9._-]*\z/'],
            'expected_source_version' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return ['successor_version_key', 'expected_source_version'];
    }
}

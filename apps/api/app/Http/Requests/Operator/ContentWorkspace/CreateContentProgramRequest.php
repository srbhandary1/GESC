<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\ContentWorkspace;

final class CreateContentProgramRequest extends StrictContentCommandRequest
{
    protected function prepareForValidation(): void
    {
        $this->trimStringFields(['program_key', 'internal_name', 'version_key']);
    }

    protected function payloadRules(): array
    {
        return [
            'program_key' => ['required', 'string', 'max:100', 'regex:/\A[a-z0-9][a-z0-9._-]*\z/'],
            'internal_name' => ['required', 'string', 'max:160', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'version_key' => ['required', 'string', 'max:100', 'regex:/\A[a-z0-9][a-z0-9._-]*\z/'],
            'expected_version' => ['required', 'integer', 'in:0'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return ['program_key', 'internal_name', 'version_key', 'expected_version'];
    }
}

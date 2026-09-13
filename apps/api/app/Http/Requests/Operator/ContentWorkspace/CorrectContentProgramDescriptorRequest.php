<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\ContentWorkspace;

final class CorrectContentProgramDescriptorRequest extends StrictContentCommandRequest
{
    protected function prepareForValidation(): void
    {
        $this->trimStringFields(['internal_name']);
    }

    protected function payloadRules(): array
    {
        return [
            'internal_name' => ['required', 'string', 'max:160', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'expected_descriptor_version' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return ['internal_name', 'expected_descriptor_version'];
    }
}

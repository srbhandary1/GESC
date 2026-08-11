<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\ContentWorkspace;

final class ActivateContentPublicationRequest extends StrictContentCommandRequest
{
    protected function payloadRules(): array
    {
        return [
            'publication_id' => ['required', 'string', 'ulid'],
            'expected_head_version' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return ['publication_id', 'expected_head_version'];
    }
}

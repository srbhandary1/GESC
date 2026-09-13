<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\ContentWorkspace;

final class RetireContentPublicationRequest extends StrictContentCommandRequest
{
    protected function payloadRules(): array
    {
        return [
            'program_id' => ['required', 'string', 'ulid'],
            'expected_head_version' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'reason_key' => ['required', 'string', 'in:verified_release_retirement,release_no_longer_current'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return ['program_id', 'expected_head_version', 'reason_key'];
    }
}

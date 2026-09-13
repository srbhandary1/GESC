<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\ContentWorkspace;

final class CancelContentPublicationScheduleRequest extends StrictContentCommandRequest
{
    protected function payloadRules(): array
    {
        return [
            'publication_id' => ['required', 'string', 'ulid'],
            'expected_head_version' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'reason_key' => ['required', 'string', 'in:schedule_replaced,release_withdrawn,timing_changed'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return ['publication_id', 'expected_head_version', 'reason_key'];
    }
}

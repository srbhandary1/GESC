<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\ContentWorkspace;

final class ScheduleContentPublicationRequest extends StrictContentCommandRequest
{
    protected function payloadRules(): array
    {
        return [
            'curriculum_version_id' => ['required', 'string', 'ulid'],
            'activation_at' => ['required', 'date_format:Y-m-d\\TH:i:sP', 'after:now'],
            'expected_head_version' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return ['curriculum_version_id', 'activation_at', 'expected_head_version'];
    }
}

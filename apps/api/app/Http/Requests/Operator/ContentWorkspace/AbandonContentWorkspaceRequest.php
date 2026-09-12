<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\ContentWorkspace;

final class AbandonContentWorkspaceRequest extends StrictContentCommandRequest
{
    protected function payloadRules(): array
    {
        return [
            'expected_workspace_version' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'reason_key' => ['required', 'string', 'in:work_discontinued,superseded,created_in_error'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return ['expected_workspace_version', 'reason_key'];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\ContentWorkspace;

final class SaveContentGraphRequest extends StrictContentCommandRequest
{
    protected function payloadRules(): array
    {
        return [
            'expected_workspace_version' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'graph' => ['required', 'array'],
            'graph.schema' => ['required', 'string', 'in:global-english.curriculum-draft-graph.v2'],
            'graph.levels' => ['required', 'array', 'max:25'],
            'graph.objectives' => ['required', 'array', 'max:500'],
            'graph.modules' => ['required', 'array', 'max:100'],
            'graph.lessons' => ['required', 'array', 'max:500'],
            'graph.items' => ['required', 'array', 'max:2000'],
            'graph.delivery_bundles' => ['required', 'array', 'max:500'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return ['expected_workspace_version', 'graph'];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Learner;

use App\Http\Requests\IdempotentRequest;

final class CompleteOfflineLevelCheckpointRequest extends IdempotentRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'bundle_id' => ['required', 'string', 'ulid'],
            'lease' => ['required', 'string', 'max:8192'],
            'level_number' => ['required', 'integer', 'min:1'],
            'answers' => ['required', 'array'],
            'answers.*' => ['integer', 'min:0'],
        ];
    }
}

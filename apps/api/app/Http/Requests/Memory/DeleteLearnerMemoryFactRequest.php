<?php

declare(strict_types=1);

namespace App\Http\Requests\Memory;

use App\Http\Requests\IdempotentRequest;

final class DeleteLearnerMemoryFactRequest extends IdempotentRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function payloadRules(): array
    {
        return [
            'fact' => [
                'required',
                'string',
                'regex:/\A[0-9A-HJKMNP-TV-Z]{26}\z/',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadValidationData(): array
    {
        return [
            'fact' => $this->route('fact'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Tuition;

use App\Http\Requests\IdempotentRequest;

final class AcknowledgeTuitionRequestRequest extends IdempotentRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function payloadRules(): array
    {
        return [];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Conversation;

use App\Http\Requests\IdempotentRequest;

final class ExitConversationSessionRequest extends IdempotentRequest
{
    /** @return array<string, list<string>> */
    protected function payloadRules(): array
    {
        return [];
    }
}

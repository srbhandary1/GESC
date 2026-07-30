<?php

declare(strict_types=1);

namespace App\Conversation\Providers;

interface ConversationModelProvider
{
    public function respond(
        ConversationModelRequest $request,
    ): ConversationModelResult;
}

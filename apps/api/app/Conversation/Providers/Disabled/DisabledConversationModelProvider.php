<?php

declare(strict_types=1);

namespace App\Conversation\Providers\Disabled;

use App\Conversation\Providers\ConversationModelProvider;
use App\Conversation\Providers\ConversationModelRequest;
use App\Conversation\Providers\ConversationModelResult;
use App\Conversation\Providers\ConversationProviderException;

final class DisabledConversationModelProvider implements ConversationModelProvider
{
    public function respond(
        ConversationModelRequest $request,
    ): ConversationModelResult {
        throw new ConversationProviderException(
            safeCode: 'provider_disabled',
            providerKey: 'disabled',
            modelVersion: 'disabled',
        );
    }
}

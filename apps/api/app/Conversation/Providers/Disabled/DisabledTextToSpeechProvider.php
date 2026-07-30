<?php

declare(strict_types=1);

namespace App\Conversation\Providers\Disabled;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\TextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechRequest;
use App\Conversation\Providers\TextToSpeechResult;

final class DisabledTextToSpeechProvider implements TextToSpeechProvider
{
    public function synthesize(
        TextToSpeechRequest $request,
    ): TextToSpeechResult {
        throw new ConversationProviderException(
            safeCode: 'provider_disabled',
            providerKey: 'disabled',
            modelVersion: 'disabled',
        );
    }
}

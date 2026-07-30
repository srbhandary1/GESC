<?php

declare(strict_types=1);

namespace App\Conversation\Providers\Disabled;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\SpeechToTextProvider;
use App\Conversation\Providers\SpeechToTextRequest;
use App\Conversation\Providers\SpeechToTextResult;

final class DisabledSpeechToTextProvider implements SpeechToTextProvider
{
    public function transcribe(
        SpeechToTextRequest $request,
    ): SpeechToTextResult {
        throw new ConversationProviderException(
            safeCode: 'provider_disabled',
            providerKey: 'disabled',
            modelVersion: 'disabled',
        );
    }
}

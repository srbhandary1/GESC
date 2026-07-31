<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Providers;

use App\Conversation\Providers\ConversationProviderException;

final class DisabledRecordedSpeechToTextProvider implements RecordedSpeechToTextProvider
{
    public function transcribe(
        RecordedSpeechToTextRequest $request,
    ): RecordedSpeechToTextResult {
        throw new ConversationProviderException(
            safeCode: 'provider_disabled',
            providerKey: 'disabled',
            modelVersion: 'disabled',
        );
    }
}

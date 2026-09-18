<?php

declare(strict_types=1);

namespace App\Conversation\Providers\Sarvam;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\TextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechRequest;
use App\Conversation\Providers\TextToSpeechResult;
use App\Conversation\Routing\SpeechDispatchAuthority;

final readonly class SarvamBulbulTextToSpeechProvider implements TextToSpeechProvider
{
    public function __construct(
        private SpeechDispatchAuthority $authority,
    ) {}

    public function synthesize(TextToSpeechRequest $request): TextToSpeechResult
    {
        $this->authority->assertDispatchAllowed('sarvam-bulbul-tts', 'tts');

        throw new ConversationProviderException(
            safeCode: 'provider_not_authorized',
            providerKey: 'sarvam-bulbul-tts',
            modelVersion: 'implementation-unavailable',
        );
    }
}

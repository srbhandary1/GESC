<?php

declare(strict_types=1);

namespace App\Conversation\Providers;

final readonly class TextToSpeechRequest
{
    public function __construct(
        public string $interactionId,
        public string $text,
        public string $voice = 'en-IN-NeerjaNeural',
        public string $purpose = 'model_en',
        public string $uiLocale = 'en-IN',
    ) {}
}

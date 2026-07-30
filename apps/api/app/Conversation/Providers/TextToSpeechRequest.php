<?php

declare(strict_types=1);

namespace App\Conversation\Providers;

final readonly class TextToSpeechRequest
{
    public function __construct(
        public string $interactionId,
        public string $text,
    ) {}
}

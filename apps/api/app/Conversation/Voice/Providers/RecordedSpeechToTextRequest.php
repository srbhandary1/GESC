<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Providers;

final readonly class RecordedSpeechToTextRequest
{
    public function __construct(
        public string $interactionId,
        public string $audioBytes,
        public string $mimeType,
        public string $language,
    ) {}
}

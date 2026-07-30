<?php

declare(strict_types=1);

namespace App\Conversation\Providers;

final readonly class SpeechToTextRequest
{
    public function __construct(
        public string $interactionId,
        public string $prototypeUtterance,
    ) {}
}

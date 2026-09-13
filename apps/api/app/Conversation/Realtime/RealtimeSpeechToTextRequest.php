<?php

declare(strict_types=1);

namespace App\Conversation\Realtime;

final readonly class RealtimeSpeechToTextRequest
{
    public function __construct(
        public string $interactionId,
        public string $language = 'en-IN',
        public ?string $prototypeUtterance = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Conversation\Realtime;

final readonly class RealtimeSpeechEvent
{
    public function __construct(
        public string $type,
        public ?string $text = null,
        public int $offsetMilliseconds = 0,
    ) {}
}

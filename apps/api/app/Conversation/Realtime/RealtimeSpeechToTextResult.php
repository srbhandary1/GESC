<?php

declare(strict_types=1);

namespace App\Conversation\Realtime;

use App\Conversation\Providers\ProviderUsage;

final readonly class RealtimeSpeechToTextResult
{
    public function __construct(
        public string $transcript,
        public ProviderUsage $usage,
        public bool $authoritative = false,
    ) {}
}

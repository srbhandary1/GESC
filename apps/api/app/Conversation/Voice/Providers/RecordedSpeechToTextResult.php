<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Providers;

use App\Conversation\Providers\ProviderUsage;

final readonly class RecordedSpeechToTextResult
{
    public function __construct(
        public string $transcript,
        public int $confidenceBps,
        public ProviderUsage $usage,
    ) {}
}

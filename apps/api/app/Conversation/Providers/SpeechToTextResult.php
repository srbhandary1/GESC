<?php

declare(strict_types=1);

namespace App\Conversation\Providers;

final readonly class SpeechToTextResult implements ProviderResult
{
    public function __construct(
        public string $transcript,
        private ProviderUsage $providerUsage,
    ) {}

    public function usage(): ProviderUsage
    {
        return $this->providerUsage;
    }
}

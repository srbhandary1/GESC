<?php

declare(strict_types=1);

namespace App\Conversation\Providers;

final readonly class TextToSpeechResult implements ProviderResult
{
    public function __construct(
        public bool $playbackAvailable,
        public string $renderingMode,
        private ProviderUsage $providerUsage,
        public ?string $audioBytes = null,
        public ?string $mimeType = null,
        public ?string $responseMetadataSha256 = null,
    ) {}

    public function usage(): ProviderUsage
    {
        return $this->providerUsage;
    }
}

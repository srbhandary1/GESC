<?php

declare(strict_types=1);

namespace App\Conversation\Providers;

final readonly class ConversationModelResult implements ProviderResult
{
    public function __construct(
        public string $understoodIntent,
        public string $assistantReply,
        private ProviderUsage $providerUsage,
    ) {}

    public function usage(): ProviderUsage
    {
        return $this->providerUsage;
    }
}

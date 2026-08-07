<?php

declare(strict_types=1);

namespace App\Conversation\Providers;

use App\Conversation\Orchestration\PriorityCorrection;

final readonly class ConversationModelResult implements ProviderResult
{
    public function __construct(
        public string $understoodIntent,
        public string $assistantReply,
        private ProviderUsage $providerUsage,
        public ?PriorityCorrection $priorityCorrection = null,
        public string $safetyState = 'ok',
        public bool $usedFallback = false,
    ) {}

    public function usage(): ProviderUsage
    {
        return $this->providerUsage;
    }
}

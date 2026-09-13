<?php

declare(strict_types=1);

namespace App\Conversation\Providers\AzureOpenAi;

final readonly class AzureOpenAiConversationStreamResult
{
    public function __construct(
        public string $content,
        public ?int $promptTokens,
        public ?int $completionTokens,
        public int $connectionLatencyMilliseconds,
        public int $firstResultLatencyMilliseconds,
        public int $completionLatencyMilliseconds,
    ) {}
}

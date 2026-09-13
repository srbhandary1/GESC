<?php

declare(strict_types=1);

namespace App\Conversation\Providers\AzureOpenAi;

use RuntimeException;

final class AzureOpenAiConversationStreamException extends RuntimeException
{
    public function __construct(
        public readonly string $safeCode,
        public readonly bool $retryable = false,
    ) {
        parent::__construct('The provider stream failed safely.');
    }
}

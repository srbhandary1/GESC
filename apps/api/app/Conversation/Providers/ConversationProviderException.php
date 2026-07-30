<?php

declare(strict_types=1);

namespace App\Conversation\Providers;

use RuntimeException;

final class ConversationProviderException extends RuntimeException
{
    public function __construct(
        public readonly string $safeCode,
        public readonly string $providerKey,
        public readonly string $modelVersion,
        public readonly int $latencyMilliseconds = 0,
        public readonly int $inputUnits = 0,
        public readonly int $outputUnits = 0,
    ) {
        parent::__construct('The conversation provider could not complete the request.');
    }
}

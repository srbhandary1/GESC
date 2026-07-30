<?php

declare(strict_types=1);

namespace App\Conversation\Providers;

final readonly class ProviderUsage
{
    public function __construct(
        public string $providerKey,
        public string $modelVersion,
        public int $latencyMilliseconds,
        public int $inputUnits,
        public int $outputUnits,
        public int $estimatedCostMicrounits,
    ) {}
}

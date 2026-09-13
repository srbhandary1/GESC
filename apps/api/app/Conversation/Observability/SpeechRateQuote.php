<?php

declare(strict_types=1);

namespace App\Conversation\Observability;

final readonly class SpeechRateQuote
{
    public function __construct(
        public string $rateCardId,
        public string $nativeCurrency,
        public int $nativeCostMicrounits,
        public int $inrCostMicrounits,
    ) {}
}

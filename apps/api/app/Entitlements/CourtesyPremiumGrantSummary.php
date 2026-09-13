<?php

declare(strict_types=1);

namespace App\Entitlements;

final readonly class CourtesyPremiumGrantSummary
{
    public function __construct(
        public int $considered,
        public int $granted,
        public int $skipped,
        public int $unmatchedUsernames,
    ) {}
}

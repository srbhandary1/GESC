<?php

declare(strict_types=1);

namespace App\Conversation\Providers;

use InvalidArgumentException;

final readonly class ProviderBillableUsage
{
    public function __construct(
        public string $componentKey,
        public string $unitKind,
        public int $quantity,
    ) {
        if (
            preg_match('/^[a-z][a-z0-9._-]{0,31}$/', $componentKey) !== 1
            || preg_match('/^[a-z][a-z0-9._-]{0,39}$/', $unitKind) !== 1
            || $quantity < 0
        ) {
            throw new InvalidArgumentException(
                'Provider billable usage must use safe keys and a non-negative quantity.',
            );
        }
    }
}

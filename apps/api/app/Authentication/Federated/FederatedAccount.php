<?php

declare(strict_types=1);

namespace App\Authentication\Federated;

final readonly class FederatedAccount
{
    public function __construct(
        public string $provider,
        public string $subject,
    ) {}
}

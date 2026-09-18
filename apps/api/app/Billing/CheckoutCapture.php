<?php

declare(strict_types=1);

namespace App\Billing;

final readonly class CheckoutCapture
{
    public function __construct(
        public string $status,
        public string $provider,
        public string $providerReference,
        public ?string $providerPaymentReference = null,
        public ?string $publicKey = null,
    ) {}
}

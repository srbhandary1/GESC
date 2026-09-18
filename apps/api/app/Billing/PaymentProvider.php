<?php

declare(strict_types=1);

namespace App\Billing;

interface PaymentProvider
{
    public function publicKey(): ?string;

    public function capture(CheckoutOrder $order): CheckoutCapture;

    public function confirm(
        CheckoutOrder $order,
        string $providerReference,
        string $providerPaymentReference,
        string $signature,
    ): CheckoutCapture;

    public function reconcile(
        CheckoutOrder $order,
        string $providerReference,
        ?string $providerPaymentReference = null,
    ): CheckoutCapture;
}

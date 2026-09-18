<?php

declare(strict_types=1);

namespace App\Billing;

use Illuminate\Support\Str;

final class DummyPaymentProvider implements PaymentProvider
{
    public function publicKey(): ?string
    {
        return null;
    }

    public function capture(CheckoutOrder $order): CheckoutCapture
    {
        return new CheckoutCapture(
            status: 'completed',
            provider: 'dummy',
            providerReference: 'dummy_'.Str::ulid(),
        );
    }

    public function confirm(
        CheckoutOrder $order,
        string $providerReference,
        string $providerPaymentReference,
        string $signature,
    ): CheckoutCapture {
        return new CheckoutCapture('completed', 'dummy', $providerReference, $providerPaymentReference);
    }

    public function reconcile(
        CheckoutOrder $order,
        string $providerReference,
        ?string $providerPaymentReference = null,
    ): CheckoutCapture {
        return new CheckoutCapture('completed', 'dummy', $providerReference, $providerPaymentReference);
    }
}

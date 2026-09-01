<?php

declare(strict_types=1);

namespace App\Billing;

use Illuminate\Support\Str;

final class DummyPaymentProvider implements PaymentProvider
{
    public function capture(CheckoutOrder $order): CheckoutCapture
    {
        return new CheckoutCapture(
            status: 'completed',
            provider: 'dummy',
            providerReference: 'dummy_'.Str::ulid(),
        );
    }
}

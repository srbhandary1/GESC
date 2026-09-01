<?php

declare(strict_types=1);

namespace App\Billing;

use App\Entitlements\Exceptions\EntitlementDenied;

final class DisabledPaymentProvider implements PaymentProvider
{
    public function capture(CheckoutOrder $order): CheckoutCapture
    {
        throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
    }
}

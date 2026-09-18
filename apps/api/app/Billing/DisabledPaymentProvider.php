<?php

declare(strict_types=1);

namespace App\Billing;

use App\Entitlements\Exceptions\EntitlementDenied;

final class DisabledPaymentProvider implements PaymentProvider
{
    public function publicKey(): ?string
    {
        return null;
    }

    public function capture(CheckoutOrder $order): CheckoutCapture
    {
        throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
    }

    public function confirm(
        CheckoutOrder $order,
        string $providerReference,
        string $providerPaymentReference,
        string $signature,
    ): CheckoutCapture {
        throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
    }

    public function reconcile(
        CheckoutOrder $order,
        string $providerReference,
        ?string $providerPaymentReference = null,
    ): CheckoutCapture {
        throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
    }
}

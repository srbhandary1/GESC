<?php

declare(strict_types=1);

namespace App\Billing;

interface PaymentProvider
{
    public function capture(CheckoutOrder $order): CheckoutCapture;
}

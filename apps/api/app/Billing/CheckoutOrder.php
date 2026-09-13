<?php

declare(strict_types=1);

namespace App\Billing;

final readonly class CheckoutOrder
{
    public function __construct(
        public string $checkoutId,
        public int $amountExGstPaise,
        public int $gstPaise,
        public int $totalPaise,
        public string $planCode,
    ) {}
}

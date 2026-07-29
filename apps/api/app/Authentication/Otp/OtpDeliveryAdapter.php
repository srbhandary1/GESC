<?php

declare(strict_types=1);

namespace App\Authentication\Otp;

interface OtpDeliveryAdapter
{
    public function send(string $normalizedMobileNumber, string $code): void;
}

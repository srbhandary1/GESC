<?php

declare(strict_types=1);

namespace App\Authentication\Otp;

final class SecureOtpCodeGenerator implements OtpCodeGenerator
{
    public function generate(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }
}

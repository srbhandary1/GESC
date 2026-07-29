<?php

declare(strict_types=1);

namespace App\Authentication\Otp;

interface OtpCodeGenerator
{
    public function generate(): string;
}

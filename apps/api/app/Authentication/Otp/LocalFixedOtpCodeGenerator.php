<?php

declare(strict_types=1);

namespace App\Authentication\Otp;

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use RuntimeException;

final readonly class LocalFixedOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(
        private Application $application,
        private string $code,
    ) {
        if (preg_match('/^[0-9]{6}$/', $code) !== 1) {
            throw new InvalidArgumentException('The local OTP code must contain six decimal digits.');
        }
    }

    public function generate(): string
    {
        if (! $this->application->environment(['local', 'testing'])) {
            throw new RuntimeException('The fixed OTP generator is disabled outside local and testing environments.');
        }

        return $this->code;
    }
}

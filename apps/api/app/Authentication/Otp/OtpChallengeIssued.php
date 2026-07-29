<?php

declare(strict_types=1);

namespace App\Authentication\Otp;

use Carbon\CarbonImmutable;

final readonly class OtpChallengeIssued
{
    public function __construct(
        public string $id,
        public CarbonImmutable $expiresAt,
    ) {}
}

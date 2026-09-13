<?php

declare(strict_types=1);

namespace App\Authentication\Mfa;

interface PrivilegedMfaVerifier
{
    /**
     * Return the accepted RFC 6238 time step, or null when the assertion is
     * invalid or would replay the factor's last accepted step.
     */
    public function verify(
        string $base32Secret,
        string $assertion,
        ?int $lastUsedTimeStep,
    ): ?int;
}

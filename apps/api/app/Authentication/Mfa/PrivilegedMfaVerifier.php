<?php

declare(strict_types=1);

namespace App\Authentication\Mfa;

use Illuminate\Contracts\Auth\Authenticatable;

interface PrivilegedMfaVerifier
{
    /**
     * A future reviewed MFA adapter must verify a factor independent of the
     * primary OTP before returning true.
     */
    public function verify(Authenticatable $user, string $assertion): bool;
}

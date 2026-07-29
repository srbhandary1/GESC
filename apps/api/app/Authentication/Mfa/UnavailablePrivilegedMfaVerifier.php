<?php

declare(strict_types=1);

namespace App\Authentication\Mfa;

use Illuminate\Contracts\Auth\Authenticatable;

final class UnavailablePrivilegedMfaVerifier implements PrivilegedMfaVerifier
{
    public function verify(Authenticatable $user, string $assertion): bool
    {
        return false;
    }
}

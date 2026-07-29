<?php

declare(strict_types=1);

namespace App\Authentication\Sessions;

use Illuminate\Contracts\Auth\Authenticatable;

interface PrivilegeClassifier
{
    public function isPrivileged(Authenticatable $user): bool;
}

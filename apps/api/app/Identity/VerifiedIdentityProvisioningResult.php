<?php

declare(strict_types=1);

namespace App\Identity;

use App\Models\User;
use App\Models\UserIdentity;

final readonly class VerifiedIdentityProvisioningResult
{
    public function __construct(
        public User $user,
        public UserIdentity $identity,
        public bool $replayed,
    ) {}
}

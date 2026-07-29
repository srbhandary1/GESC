<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Membership;
use App\Models\User;
use App\Models\UserIdentity;

final readonly class DirectLearnerProvisioningResult
{
    public function __construct(
        public User $user,
        public UserIdentity $identity,
        public Membership $membership,
        public bool $replayed,
    ) {}
}

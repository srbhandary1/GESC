<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Membership;
use App\Models\User;

final class MembershipPolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'memberships.read');
    }

    public function view(User $user, Membership $membership): bool
    {
        return $this->allows($user, 'memberships.read', $membership);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'memberships.manage');
    }

    public function update(User $user, Membership $membership): bool
    {
        return $this->allows($user, 'memberships.manage', $membership);
    }

    public function delete(User $user, Membership $membership): bool
    {
        return $this->allows($user, 'memberships.manage', $membership);
    }
}

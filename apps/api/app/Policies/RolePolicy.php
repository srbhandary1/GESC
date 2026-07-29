<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

final class RolePolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'roles.manage');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->allows($user, 'roles.manage', $role);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $this->allows($user, 'roles.manage', $role);
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->allows($user, 'roles.manage', $role);
    }
}

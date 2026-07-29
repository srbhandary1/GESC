<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InstitutionInvite;
use App\Models\User;

final class InstitutionInvitePolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'institution_invites.manage');
    }

    public function view(User $user, InstitutionInvite $invite): bool
    {
        return $this->allows($user, 'institution_invites.manage', $invite);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'institution_invites.manage');
    }

    public function update(User $user, InstitutionInvite $invite): bool
    {
        return $this->allows($user, 'institution_invites.manage', $invite);
    }

    public function delete(User $user, InstitutionInvite $invite): bool
    {
        return $this->allows($user, 'institution_invites.manage', $invite);
    }
}

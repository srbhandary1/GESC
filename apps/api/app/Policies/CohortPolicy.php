<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cohort;
use App\Models\User;

final class CohortPolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'cohorts.read');
    }

    public function view(User $user, Cohort $cohort): bool
    {
        return $this->allows($user, 'cohorts.read', $cohort);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'cohorts.manage');
    }

    public function update(User $user, Cohort $cohort): bool
    {
        return $this->allows($user, 'cohorts.manage', $cohort);
    }

    public function delete(User $user, Cohort $cohort): bool
    {
        return $this->allows($user, 'cohorts.manage', $cohort);
    }
}

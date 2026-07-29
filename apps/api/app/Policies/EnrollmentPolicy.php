<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

final class EnrollmentPolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'enrollments.read');
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return $this->allows($user, 'enrollments.read', $enrollment);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'enrollments.manage');
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $this->allows($user, 'enrollments.manage', $enrollment);
    }

    public function delete(User $user, Enrollment $enrollment): bool
    {
        return $this->allows($user, 'enrollments.manage', $enrollment);
    }
}

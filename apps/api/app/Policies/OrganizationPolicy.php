<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationOwned;

abstract class OrganizationPolicy
{
    public function __construct(
        private readonly OrganizationAuthorization $authorization,
    ) {}

    protected function allows(
        User $user,
        string $permission,
        ?OrganizationOwned $resource = null,
    ): bool {
        return $this->authorization->allows($user, $permission, $resource);
    }
}

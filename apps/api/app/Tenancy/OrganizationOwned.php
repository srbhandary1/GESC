<?php

declare(strict_types=1);

namespace App\Tenancy;

interface OrganizationOwned
{
    public function organizationId(): string;
}

<?php

declare(strict_types=1);

namespace App\Tenancy\Exceptions;

use DomainException;

final class InvalidOrganizationContext extends DomainException
{
    public function __construct()
    {
        parent::__construct('The selected membership cannot establish an organization context.');
    }
}

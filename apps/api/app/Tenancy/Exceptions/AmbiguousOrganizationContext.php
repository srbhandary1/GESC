<?php

declare(strict_types=1);

namespace App\Tenancy\Exceptions;

use DomainException;

final class AmbiguousOrganizationContext extends DomainException
{
    public function __construct()
    {
        parent::__construct('An explicit active membership selection is required.');
    }
}

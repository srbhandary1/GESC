<?php

declare(strict_types=1);

namespace App\Tenancy\Exceptions;

use DomainException;

final class IdentityUnavailable extends DomainException
{
    public function __construct()
    {
        parent::__construct('The verified identity is not available for sign-in.');
    }
}

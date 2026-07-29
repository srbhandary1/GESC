<?php

declare(strict_types=1);

namespace App\Tenancy\Exceptions;

use DomainException;

final class UnauthorizedPlatformOperation extends DomainException
{
    public function __construct()
    {
        parent::__construct('The platform operation is not authorized.');
    }
}

<?php

declare(strict_types=1);

namespace App\Tenancy\Exceptions;

use LogicException;

final class MissingOrganizationContext extends LogicException
{
    public function __construct()
    {
        parent::__construct('An authenticated organization context is required.');
    }
}

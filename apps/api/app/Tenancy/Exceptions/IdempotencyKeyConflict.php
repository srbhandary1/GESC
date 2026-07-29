<?php

declare(strict_types=1);

namespace App\Tenancy\Exceptions;

use DomainException;

final class IdempotencyKeyConflict extends DomainException
{
    public function __construct()
    {
        parent::__construct('The idempotency key is already bound to another request.');
    }
}

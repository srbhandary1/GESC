<?php

declare(strict_types=1);

namespace App\Operator\Exceptions;

use RuntimeException;

final class OperatorPrivacyIdempotencyConflict extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The privacy command conflicts with a prior idempotency key.');
    }
}

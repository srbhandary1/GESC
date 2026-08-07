<?php

declare(strict_types=1);

namespace App\Operator\Exceptions;

use RuntimeException;

final class OperatorPrivacyRejected extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The privacy or support operation is not permitted in the current state.');
    }
}

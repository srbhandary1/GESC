<?php

declare(strict_types=1);

namespace App\Identity\Exceptions;

use RuntimeException;

final class LocalOperatorIdentityBootstrapRejected extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Local operator identity bootstrap rejected.');
    }
}

<?php

declare(strict_types=1);

namespace App\Operator\Exceptions;

use RuntimeException;

final class OperatorShellRejected extends RuntimeException
{
    // Intentionally indistinguishable from a missing operator resource.
}

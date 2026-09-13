<?php

declare(strict_types=1);

namespace App\Operator\Exceptions;

use RuntimeException;

final class InvalidOperatorCursor extends RuntimeException
{
    // Deliberately carries no cursor value or decrypted position.
}

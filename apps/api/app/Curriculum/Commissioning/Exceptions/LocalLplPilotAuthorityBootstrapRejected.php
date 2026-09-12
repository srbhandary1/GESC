<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning\Exceptions;

use RuntimeException;
use Throwable;

final class LocalLplPilotAuthorityBootstrapRejected extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(
            'Local LPL pilot authority bootstrap rejected.',
            previous: $previous,
        );
    }
}

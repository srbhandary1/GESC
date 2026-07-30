<?php

declare(strict_types=1);

namespace App\Curriculum\Exceptions;

use RuntimeException;

final class CurriculumOperationRejected extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The curriculum operation is not permitted in the current state.');
    }
}

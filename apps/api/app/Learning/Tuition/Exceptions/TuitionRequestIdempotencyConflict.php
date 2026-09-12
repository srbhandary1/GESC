<?php

declare(strict_types=1);

namespace App\Learning\Tuition\Exceptions;

use RuntimeException;

final class TuitionRequestIdempotencyConflict extends RuntimeException {}

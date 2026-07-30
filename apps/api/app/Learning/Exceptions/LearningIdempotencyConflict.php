<?php

declare(strict_types=1);

namespace App\Learning\Exceptions;

use RuntimeException;

final class LearningIdempotencyConflict extends RuntimeException {}

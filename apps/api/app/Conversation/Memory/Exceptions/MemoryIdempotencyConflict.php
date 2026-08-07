<?php

declare(strict_types=1);

namespace App\Conversation\Memory\Exceptions;

use RuntimeException;

final class MemoryIdempotencyConflict extends RuntimeException {}

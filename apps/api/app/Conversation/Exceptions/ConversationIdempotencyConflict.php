<?php

declare(strict_types=1);

namespace App\Conversation\Exceptions;

use RuntimeException;

final class ConversationIdempotencyConflict extends RuntimeException {}

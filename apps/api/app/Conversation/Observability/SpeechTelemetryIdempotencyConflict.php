<?php

declare(strict_types=1);

namespace App\Conversation\Observability;

use RuntimeException;

final class SpeechTelemetryIdempotencyConflict extends RuntimeException {}

<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning\MediaTranscription;

use RuntimeException;
use Throwable;

final class MediaTranscriptionProviderException extends RuntimeException
{
    public function __construct(
        public readonly string $safeCode,
        public readonly bool $retryable = false,
        public readonly int $latencyMilliseconds = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            'The media transcription provider could not complete the request.',
            0,
            $previous,
        );
    }
}

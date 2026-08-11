<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning\MediaTranscription;

use RuntimeException;
use Throwable;

final class MediaTranscriptionRejected extends RuntimeException
{
    public function __construct(
        public readonly string $safeCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            'The media transcription operation was rejected.',
            0,
            $previous,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Learning\Media;

use RuntimeException;

final class VideoRangeNotSatisfiable extends RuntimeException
{
    public function __construct(
        public readonly int $totalBytes,
        public readonly string $etag,
    ) {
        parent::__construct('The requested lesson-video range is not satisfiable.');
    }
}

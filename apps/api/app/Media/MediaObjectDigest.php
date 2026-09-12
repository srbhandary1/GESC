<?php

declare(strict_types=1);

namespace App\Media;

final readonly class MediaObjectDigest
{
    public function __construct(
        public int $byteSize,
        public string $sha256,
    ) {}
}

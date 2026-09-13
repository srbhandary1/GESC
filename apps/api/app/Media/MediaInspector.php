<?php

declare(strict_types=1);

namespace App\Media;

interface MediaInspector
{
    /** @param resource $stream */
    public function inspect(
        $stream,
        string $assetKind,
        int $expectedBytes,
        string $expectedSha256,
    ): MediaInspection;
}

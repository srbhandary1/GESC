<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning\MediaTranscription;

interface CurriculumMediaSourceReader
{
    public function readExact(
        string $assetKind,
        string $storageObjectKey,
        int $expectedBytes,
        string $expectedSha256,
    ): string;
}

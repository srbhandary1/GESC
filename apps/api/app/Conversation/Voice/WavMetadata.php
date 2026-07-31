<?php

declare(strict_types=1);

namespace App\Conversation\Voice;

final readonly class WavMetadata
{
    public function __construct(
        public int $byteSize,
        public int $durationMs,
        public int $sampleRateHz,
        public int $channels,
        public string $codec = 'pcm_s16le',
        public string $mimeType = 'audio/wav',
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning\MediaTranscription;

final readonly class MediaTranscriptionRequest
{
    public function __construct(
        public string $audioBytes,
        public string $sourceSha256,
        public int $sourceDurationMilliseconds,
        public string $mimeType = 'audio/mpeg',
        public string $locale = 'en-IN',
    ) {}
}

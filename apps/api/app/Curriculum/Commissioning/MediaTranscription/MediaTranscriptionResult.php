<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning\MediaTranscription;

final readonly class MediaTranscriptionResult
{
    /** @param list<MediaTranscriptionSegment> $segments */
    public function __construct(
        public string $transcript,
        public array $segments,
        public string $responseMetadataSha256,
        public int $latencyMilliseconds,
        public int $usageMilliseconds,
    ) {}
}

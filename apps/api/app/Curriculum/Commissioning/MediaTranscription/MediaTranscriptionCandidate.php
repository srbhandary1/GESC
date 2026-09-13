<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning\MediaTranscription;

final readonly class MediaTranscriptionCandidate
{
    /** @param list<MediaTranscriptionSegment> $segments */
    public function __construct(
        public string $transcript,
        public string $locale,
        public array $segments,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'locale' => $this->locale,
            'segments' => array_map(
                static fn (MediaTranscriptionSegment $segment): array => $segment->toArray(),
                $this->segments,
            ),
            'transcript' => $this->transcript,
        ];
    }
}

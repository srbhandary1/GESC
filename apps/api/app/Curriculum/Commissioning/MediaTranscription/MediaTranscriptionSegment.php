<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning\MediaTranscription;

final readonly class MediaTranscriptionSegment
{
    public function __construct(
        public int $sequence,
        public string $text,
        public int $offsetMilliseconds,
        public int $durationMilliseconds,
        public int $confidenceBps,
    ) {}

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'confidence_bps' => $this->confidenceBps,
            'duration_milliseconds' => $this->durationMilliseconds,
            'offset_milliseconds' => $this->offsetMilliseconds,
            'sequence' => $this->sequence,
            'text' => $this->text,
        ];
    }
}

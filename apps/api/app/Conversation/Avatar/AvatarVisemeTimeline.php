<?php

declare(strict_types=1);

namespace App\Conversation\Avatar;

final readonly class AvatarVisemeTimeline
{
    /**
     * @param  list<array{offset_ms: int, viseme_id: int}>  $frames
     */
    public function __construct(
        public string $source,
        public int $durationMs,
        public array $frames,
    ) {}

    /**
     * @return array{
     *   source: string,
     *   duration_ms: int,
     *   frames: list<array{offset_ms: int, viseme_id: int}>
     * }
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'duration_ms' => $this->durationMs,
            'frames' => $this->frames,
        ];
    }
}

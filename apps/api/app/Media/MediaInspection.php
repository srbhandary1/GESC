<?php

declare(strict_types=1);

namespace App\Media;

final readonly class MediaInspection
{
    public function __construct(
        public string $outcome,
        public ?string $mimeType,
        public ?int $durationMilliseconds,
        public string $inspectorName,
        public string $policyVersion,
    ) {}

    public function passed(): bool
    {
        return $this->outcome === 'passed';
    }
}

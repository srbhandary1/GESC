<?php

declare(strict_types=1);

namespace App\Curriculum\Publication;

use Carbon\CarbonImmutable;

final readonly class PublicationActivationResult
{
    public function __construct(
        public string $publicationId,
        public string $curriculumVersionId,
        public string $manifestHash,
        public CarbonImmutable $activatedAt,
        public int $headVersion,
        public bool $replayed,
    ) {}
}

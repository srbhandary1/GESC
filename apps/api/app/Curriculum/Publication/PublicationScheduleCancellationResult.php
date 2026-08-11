<?php

declare(strict_types=1);

namespace App\Curriculum\Publication;

use Carbon\CarbonImmutable;

final readonly class PublicationScheduleCancellationResult
{
    public function __construct(
        public string $publicationId,
        public string $curriculumVersionId,
        public CarbonImmutable $cancelledAt,
        public int $headVersion,
        public bool $replayed,
    ) {}
}

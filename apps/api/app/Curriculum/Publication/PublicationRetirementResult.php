<?php

declare(strict_types=1);

namespace App\Curriculum\Publication;

final readonly class PublicationRetirementResult
{
    public function __construct(
        public string $programId,
        public string $curriculumVersionId,
        public string $publicationId,
        public int $headVersion,
        public bool $replayed,
    ) {}
}

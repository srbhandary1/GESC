<?php

declare(strict_types=1);

namespace App\Curriculum\Publication;

final readonly class PublicationResult
{
    public function __construct(
        public string $publicationId,
        public string $curriculumVersionId,
        public string $manifestHash,
        public int $headVersion,
        public bool $replayed,
    ) {}
}

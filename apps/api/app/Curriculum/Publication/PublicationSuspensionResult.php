<?php

declare(strict_types=1);

namespace App\Curriculum\Publication;

final readonly class PublicationSuspensionResult
{
    public function __construct(
        public string $programId,
        public string $curriculumVersionId,
        public string $publicationId,
        public bool $suspended,
        public int $headVersion,
        public bool $replayed,
    ) {}
}

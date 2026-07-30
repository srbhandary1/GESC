<?php

declare(strict_types=1);

namespace App\Curriculum\Authoring;

final readonly class ContentRevisionAppendResult
{
    public function __construct(
        public string $organizationId,
        public string $curriculumVersionId,
        public string $contentDocumentId,
        public string $contentRevisionId,
        public ?string $parentRevisionId,
        public int $revisionNumber,
        public string $contentHash,
        public bool $replayed,
    ) {}
}

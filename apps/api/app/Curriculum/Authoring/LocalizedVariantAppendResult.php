<?php

declare(strict_types=1);

namespace App\Curriculum\Authoring;

final readonly class LocalizedVariantAppendResult
{
    public function __construct(
        public string $organizationId,
        public string $curriculumVersionId,
        public string $sourceRevisionId,
        public string $localizedVariantId,
        public ?string $parentVariantId,
        public int $revisionNumber,
        public string $sourceContentHash,
        public string $contentHash,
        public bool $replayed,
    ) {}
}

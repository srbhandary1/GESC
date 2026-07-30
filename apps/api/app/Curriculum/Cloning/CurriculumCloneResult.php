<?php

declare(strict_types=1);

namespace App\Curriculum\Cloning;

final readonly class CurriculumCloneResult
{
    public function __construct(
        public string $curriculumCloneId,
        public string $recipientOrganizationId,
        public string $sourceOwnerOrganizationId,
        public string $sourcePublicationId,
        public string $sourceShareGrantId,
        public string $programId,
        public string $curriculumVersionId,
        public bool $replayed,
    ) {}
}

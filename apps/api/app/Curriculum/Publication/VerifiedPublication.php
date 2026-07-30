<?php

declare(strict_types=1);

namespace App\Curriculum\Publication;

final readonly class VerifiedPublication
{
    public function __construct(
        public string $ownerOrganizationId,
        public string $programId,
        public string $curriculumVersionId,
        public string $publicationId,
        public string $manifestHash,
        public bool $sharingAllowed,
        public bool $derivativeUseAllowed,
    ) {}
}

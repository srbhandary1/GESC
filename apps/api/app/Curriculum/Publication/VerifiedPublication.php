<?php

declare(strict_types=1);

namespace App\Curriculum\Publication;

final readonly class VerifiedPublication
{
    /**
     * @param  array<string, mixed>  $manifest
     */
    public function __construct(
        public string $ownerOrganizationId,
        public string $programId,
        public string $curriculumVersionId,
        public string $publicationId,
        public string $manifestHash,
        public array $manifest,
        public bool $sharingAllowed,
        public bool $derivativeUseAllowed,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Curriculum\Sharing;

final readonly class ShareGrantResult
{
    /**
     * @param  list<string>  $allowedUses
     */
    public function __construct(
        public string $shareGrantId,
        public string $ownerOrganizationId,
        public string $recipientOrganizationId,
        public string $curriculumVersionId,
        public string $publicationId,
        public array $allowedUses,
        public int $grantVersion,
        public string $status,
        public bool $replayed,
    ) {}
}

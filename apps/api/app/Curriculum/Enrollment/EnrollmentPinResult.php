<?php

declare(strict_types=1);

namespace App\Curriculum\Enrollment;

final readonly class EnrollmentPinResult
{
    public function __construct(
        public string $enrollmentId,
        public string $organizationId,
        public string $curriculumOwnerOrganizationId,
        public string $curriculumVersionId,
        public string $publicationId,
        public ?string $shareGrantId,
        public string $status,
        public bool $replayed,
    ) {}
}

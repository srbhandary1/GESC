<?php

declare(strict_types=1);

namespace App\Curriculum\Enrollment;

use App\Curriculum\Publication\VerifiedPublication;

final readonly class PinnedEnrollmentAccess
{
    public function __construct(
        public string $enrollmentId,
        public string $learnerMembershipId,
        public VerifiedPublication $publication,
    ) {}
}

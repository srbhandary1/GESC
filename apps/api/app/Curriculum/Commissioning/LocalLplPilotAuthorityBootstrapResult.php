<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning;

use Carbon\CarbonImmutable;

final readonly class LocalLplPilotAuthorityBootstrapResult
{
    public function __construct(
        public string $programId,
        public string $roleId,
        public string $membershipId,
        public string $appointmentId,
        public bool $replayed,
        public string $environment,
        public string $approvalReference,
        public string $ownerEvidenceReference,
        public CarbonImmutable $completedAt,
    ) {}
}

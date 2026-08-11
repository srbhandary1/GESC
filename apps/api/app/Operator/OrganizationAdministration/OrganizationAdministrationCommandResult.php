<?php

declare(strict_types=1);

namespace App\Operator\OrganizationAdministration;

use Carbon\CarbonImmutable;

final readonly class OrganizationAdministrationCommandResult
{
    public function __construct(
        public string $resultType,
        public string $resultId,
        public bool $replayed,
        public CarbonImmutable $completedAt,
        public ?string $activationCode = null,
        public ?int $revokedWebSessionCount = null,
        public ?int $revokedAndroidFamilyCount = null,
    ) {}
}

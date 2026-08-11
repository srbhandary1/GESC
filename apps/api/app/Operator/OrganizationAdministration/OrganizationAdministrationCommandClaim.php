<?php

declare(strict_types=1);

namespace App\Operator\OrganizationAdministration;

use Carbon\CarbonImmutable;

final readonly class OrganizationAdministrationCommandClaim
{
    public function __construct(
        public string $id,
        public bool $replayed,
        public ?string $resultType = null,
        public ?string $resultId = null,
        public ?CarbonImmutable $completedAt = null,
    ) {}
}

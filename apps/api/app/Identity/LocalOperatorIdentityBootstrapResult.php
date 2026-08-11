<?php

declare(strict_types=1);

namespace App\Identity;

use Carbon\CarbonImmutable;

final readonly class LocalOperatorIdentityBootstrapResult
{
    public function __construct(
        public string $userId,
        public string $membershipId,
        public string $identityId,
        public string $environment,
        public string $approvalReference,
        public CarbonImmutable $boundAt,
    ) {}
}

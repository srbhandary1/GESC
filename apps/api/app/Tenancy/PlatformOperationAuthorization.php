<?php

declare(strict_types=1);

namespace App\Tenancy;

final readonly class PlatformOperationAuthorization
{
    public function __construct(
        public PlatformCapability $capability,
        public string $reasonKey,
        public ?string $targetOrganizationId,
        public ?string $actorUserId,
        public string $action,
        public string $subjectType,
        public string $subjectId,
        public ?string $correlationId,
        public int $issuedAtEpoch,
        public string $nonce,
        public string $signature,
    ) {}
}

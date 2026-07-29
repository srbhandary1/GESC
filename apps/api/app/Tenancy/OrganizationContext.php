<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class OrganizationContext
{
    public function __construct(
        public string $organizationId,
        public string $actorUserId,
        public ?string $membershipId,
        public OrganizationContextSource $source,
        public ?string $correlationId = null,
        public ?PlatformOperationAuthorization $platformAuthorization = null,
        public ?string $platformAuditEventId = null,
    ) {
        if (! Str::isUlid($this->organizationId) || ! Str::isUlid($this->actorUserId)) {
            throw new InvalidArgumentException('Organization context identifiers must be ULIDs.');
        }

        if ($this->membershipId !== null && ! Str::isUlid($this->membershipId)) {
            throw new InvalidArgumentException('The organization-context membership must be a ULID.');
        }

        if ($this->correlationId !== null && ! Str::isUuid($this->correlationId)) {
            throw new InvalidArgumentException('The organization-context correlation identifier must be a UUID.');
        }

        if ($this->source === OrganizationContextSource::PlatformOperation) {
            if (
                $this->membershipId !== null
                || $this->platformAuthorization === null
                || $this->platformAuditEventId === null
                || ! Str::isUlid($this->platformAuditEventId)
            ) {
                throw new InvalidArgumentException('A platform context requires authorized audit evidence.');
            }

            app(PlatformOperationAuthorizer::class)->assertContextAuthorized(
                $this->platformAuthorization,
                $this->platformAuditEventId,
                $this->organizationId,
                $this->actorUserId,
            );
        } elseif (
            $this->platformAuthorization !== null
            || $this->platformAuditEventId !== null
        ) {
            throw new InvalidArgumentException('Only platform contexts may carry platform authorization.');
        }
    }
}

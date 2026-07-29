<?php

declare(strict_types=1);

namespace App\Audit;

use App\Models\AuditEvent;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\PlatformOperationAuthorization;
use App\Tenancy\PlatformOperationAuthorizer;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class AuditEventRecorder
{
    public function __construct(
        private OrganizationContextManager $contexts,
        private PlatformOperationAuthorizer $platformOperations,
    ) {}

    public function recordTenant(
        string $action,
        string $subjectType,
        string $subjectId,
        ?string $correlationId = null,
    ): AuditEvent {
        $this->assertSafeDescriptor($action);
        $this->assertSafeDescriptor($subjectType);
        $this->assertUlid($subjectId);

        $context = $this->contexts->current();

        return AuditEvent::query()->create([
            'scope_kind' => 'organization',
            'organization_id' => $context->organizationId,
            'actor_user_id' => $context->actorUserId,
            'actor_membership_id' => $context->membershipId,
            'target_organization_id' => null,
            'capability_key' => null,
            'reason_key' => null,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'correlation_id' => $correlationId ?? $context->correlationId,
            'occurred_at' => now(),
        ]);
    }

    public function recordPlatform(
        PlatformOperationAuthorization $authorization,
    ): AuditEvent {
        $this->platformOperations->assertAuditAuthorized($authorization);

        return AuditEvent::query()->create([
            'scope_kind' => 'platform',
            'organization_id' => null,
            'actor_user_id' => $authorization->actorUserId,
            'actor_membership_id' => null,
            'target_organization_id' => $authorization->targetOrganizationId,
            'capability_key' => $authorization->capability->value,
            'reason_key' => $authorization->reasonKey,
            'action' => $authorization->action,
            'subject_type' => $authorization->subjectType,
            'subject_id' => $authorization->subjectId,
            'correlation_id' => $authorization->correlationId,
            'occurred_at' => now(),
        ]);
    }

    private function assertSafeDescriptor(string $value): void
    {
        if (preg_match('/\A[a-z][a-z0-9_.-]{0,119}\z/', $value) !== 1) {
            throw new InvalidArgumentException('Audit descriptors must be non-sensitive stable keys.');
        }
    }

    private function assertUlid(string $value): void
    {
        if (! Str::isUlid($value)) {
            throw new InvalidArgumentException('Audit subject and actor identifiers must be ULIDs.');
        }
    }
}

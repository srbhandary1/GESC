<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Audit\AuditEventRecorder;
use App\Tenancy\Exceptions\UnauthorizedPlatformOperation;
use Closure;
use Illuminate\Support\Facades\DB;

final readonly class PlatformOperationExecutor
{
    public function __construct(
        private PlatformOperationAuthorizer $authorizer,
        private OrganizationContextManager $contexts,
        private AuditEventRecorder $audit,
    ) {}

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function run(
        PlatformOperationAuthorization $authorization,
        Closure $callback,
    ): mixed {
        return DB::transaction(function () use (
            $authorization,
            $callback,
        ): mixed {
            $this->authorizer->assertContextOperationAuthorized($authorization);

            $organizationId = $authorization->targetOrganizationId;
            $actorUserId = $authorization->actorUserId;

            if ($organizationId === null || $actorUserId === null) {
                throw new UnauthorizedPlatformOperation;
            }

            $auditEvent = $this->audit->recordPlatform($authorization);
            $context = new OrganizationContext(
                organizationId: $organizationId,
                actorUserId: $actorUserId,
                membershipId: null,
                source: OrganizationContextSource::PlatformOperation,
                correlationId: $authorization->correlationId,
                platformAuthorization: $authorization,
                platformAuditEventId: (string) $auditEvent->getKey(),
            );

            return $this->contexts->run($context, $callback);
        });
    }
}

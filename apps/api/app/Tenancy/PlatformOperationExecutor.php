<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Audit\AuditEventRecorder;
use App\Tenancy\Exceptions\UnauthorizedPlatformOperation;
use Closure;
use Illuminate\Database\QueryException;
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
     * @param  (Closure(): (TResult|null))|null  $replay
     * @return TResult
     */
    public function run(
        PlatformOperationAuthorization $authorization,
        Closure $callback,
        ?Closure $replay = null,
    ): mixed {
        try {
            return DB::transaction(function () use (
                $authorization,
                $callback,
                $replay,
            ): mixed {
                $this->authorizer->assertContextOperationAuthorized(
                    $authorization,
                );

                $organizationId = $authorization->targetOrganizationId;
                $actorUserId = $authorization->actorUserId;

                if ($organizationId === null || $actorUserId === null) {
                    throw new UnauthorizedPlatformOperation;
                }

                /*
                 * A completed idempotent command may be replayed after the
                 * signed authorization was consumed. Revalidate the current
                 * platform authority first, then return that exact stored
                 * result without manufacturing another privileged audit row.
                 */
                if ($replay !== null) {
                    $replayed = $replay();

                    if ($replayed !== null) {
                        return $replayed;
                    }
                }

                if (
                    DB::table('audit_events')
                        ->where(
                            'authorization_nonce',
                            $authorization->nonce,
                        )
                        ->exists()
                ) {
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
        } catch (QueryException $exception) {
            /*
             * The unique audit nonce is the concurrency backstop when two
             * workers race after the preflight check.
             */
            if (
                str_contains(
                    $exception->getMessage(),
                    'audit_events_authorization_nonce_unique',
                )
                || str_contains(
                    $exception->getMessage(),
                    'audit_events.authorization_nonce',
                )
            ) {
                throw new UnauthorizedPlatformOperation;
            }

            throw $exception;
        }
    }
}

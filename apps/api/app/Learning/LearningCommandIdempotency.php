<?php

declare(strict_types=1);

namespace App\Learning;

use App\Curriculum\CanonicalJson;
use App\Learning\Exceptions\LearningIdempotencyConflict;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Models\User;
use App\Tenancy\OrganizationContextManager;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class LearningCommandIdempotency
{
    public function __construct(
        private OrganizationContextManager $contexts,
    ) {}

    /**
     * @param  array<string, mixed>  $request
     */
    public function fingerprint(array $request): string
    {
        return CanonicalJson::sha256($request);
    }

    public function replay(
        User $actor,
        string $operation,
        string $aggregateType,
        string $aggregateId,
        string $idempotencyKey,
        string $fingerprint,
    ): ?stdClass {
        $context = $this->contexts->current();
        $row = DB::table('learning_commands')
            ->where('organization_id', $context->organizationId)
            ->where('operation', $operation)
            ->where('aggregate_type', $aggregateType)
            ->where('aggregate_id', $aggregateId)
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first([
                'request_fingerprint',
                'result_type',
                'result_id',
                'actor_membership_id',
                'actor_user_id',
            ]);

        if (! $row instanceof stdClass) {
            return null;
        }

        if (
            $row->actor_membership_id !== $context->membershipId
            || $row->actor_user_id !== $actor->getKey()
        ) {
            throw new LearningOperationRejected;
        }

        if (
            ! is_string($row->request_fingerprint)
            || ! hash_equals($row->request_fingerprint, $fingerprint)
        ) {
            throw new LearningIdempotencyConflict;
        }

        return $row;
    }

    public function record(
        User $actor,
        string $operation,
        string $aggregateType,
        string $aggregateId,
        string $idempotencyKey,
        string $fingerprint,
        string $resultType,
        string $resultId,
        CarbonInterface $completedAt,
    ): void {
        $context = $this->contexts->current();

        if (
            $context->membershipId === null
            || $context->actorUserId !== (string) $actor->getKey()
        ) {
            throw new LearningOperationRejected;
        }

        DB::table('learning_commands')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $context->organizationId,
            'operation' => $operation,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'idempotency_key' => $idempotencyKey,
            'request_fingerprint' => $fingerprint,
            'result_type' => $resultType,
            'result_id' => $resultId,
            'actor_membership_id' => $context->membershipId,
            'actor_user_id' => $actor->getKey(),
            'completed_at' => $completedAt,
            'created_at' => $completedAt,
        ]);
    }

    public function assertIdempotencyKey(string $key): void
    {
        if (
            strlen($key) > 128
            || (! Str::isUlid($key) && ! Str::isUuid($key))
        ) {
            throw new LearningOperationRejected;
        }
    }
}

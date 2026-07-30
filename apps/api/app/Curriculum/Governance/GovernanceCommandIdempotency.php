<?php

declare(strict_types=1);

namespace App\Curriculum\Governance;

use App\Curriculum\CanonicalJson;
use App\Curriculum\Exceptions\CurriculumIdempotencyConflict;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Models\User;
use App\Tenancy\OrganizationContextManager;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class GovernanceCommandIdempotency
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
    ): ?string {
        $actorMembershipId = $this->actorMembershipId($actor);
        GovernanceInput::idempotencyKey($idempotencyKey);
        $context = $this->contexts->current();
        $command = DB::table('publication_commands')
            ->where('organization_id', $context->organizationId)
            ->where('operation', $operation)
            ->where('aggregate_type', $aggregateType)
            ->where('aggregate_id', $aggregateId)
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first([
                'request_fingerprint',
                'result_id',
                'actor_type',
                'actor_membership_id',
                'actor_user_id',
            ]);

        if (! $command instanceof stdClass) {
            return null;
        }

        if (
            $this->stringProperty($command, 'actor_type')
                !== 'owner_membership'
            || ! hash_equals(
                $this->stringProperty($command, 'actor_membership_id'),
                $actorMembershipId,
            )
            || ! hash_equals(
                $this->stringProperty($command, 'actor_user_id'),
                (string) $actor->getKey(),
            )
        ) {
            throw new CurriculumOperationRejected;
        }

        if (
            ! hash_equals(
                $this->stringProperty($command, 'request_fingerprint'),
                $fingerprint,
            )
        ) {
            throw new CurriculumIdempotencyConflict;
        }

        return $this->stringProperty($command, 'result_id');
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
        GovernanceInput::idempotencyKey($idempotencyKey);
        $actorMembershipId = $this->actorMembershipId($actor);
        $context = $this->contexts->current();

        DB::table('publication_commands')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $context->organizationId,
            'operation' => $operation,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'idempotency_key' => $idempotencyKey,
            'request_fingerprint' => $fingerprint,
            'result_type' => $resultType,
            'result_id' => $resultId,
            'actor_type' => 'owner_membership',
            'actor_membership_id' => $actorMembershipId,
            'actor_user_id' => $actor->getKey(),
            'completed_at' => $completedAt,
            'created_at' => $completedAt,
        ]);
    }

    private function actorMembershipId(User $actor): string
    {
        $context = $this->contexts->current();

        if (
            $context->membershipId === null
            || ! hash_equals(
                $context->actorUserId,
                (string) $actor->getKey(),
            )
        ) {
            throw new CurriculumOperationRejected;
        }

        return $context->membershipId;
    }

    private function stringProperty(
        stdClass $record,
        string $property,
    ): string {
        $value = $record->{$property} ?? null;

        if (! is_string($value) || $value === '') {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }
}

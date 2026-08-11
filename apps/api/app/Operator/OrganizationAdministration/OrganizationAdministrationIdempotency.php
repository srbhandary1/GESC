<?php

declare(strict_types=1);

namespace App\Operator\OrganizationAdministration;

use App\Curriculum\CanonicalJson;
use App\Models\User;
use App\Operator\OrganizationAdministration\Exceptions\OrganizationAdministrationConflict;
use App\Operator\OrganizationAdministration\Exceptions\OrganizationAdministrationRejected;
use App\Tenancy\OrganizationContextManager;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class OrganizationAdministrationIdempotency
{
    private const string POLICY_REVISION = 'ADMIN-ORG-01-D-0132';

    public function __construct(
        private OrganizationContextManager $contexts,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public function fingerprint(
        string $operation,
        string $targetType,
        string $targetId,
        array $body,
    ): string {
        return CanonicalJson::sha256([
            'body' => $body,
            'operation' => $operation,
            'policy_revision' => self::POLICY_REVISION,
            'target_id' => $targetId,
            'target_type' => $targetType,
        ]);
    }

    public function claim(
        User $actor,
        string $idempotencyKey,
        string $operation,
        string $targetType,
        string $targetId,
        string $fingerprint,
    ): OrganizationAdministrationCommandClaim {
        $context = $this->contexts->current();
        $actorMembershipId = $context->membershipId;

        if (
            $actorMembershipId === null
            || ! hash_equals($context->actorUserId, (string) $actor->getKey())
        ) {
            throw new OrganizationAdministrationRejected;
        }

        $claimId = (string) Str::ulid();
        $createdAt = now();
        DB::table('organization_administration_commands')->insertOrIgnore([
            'id' => $claimId,
            'organization_id' => $context->organizationId,
            'actor_membership_id' => $actorMembershipId,
            'idempotency_key' => $idempotencyKey,
            'operation' => $operation,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'request_fingerprint' => $fingerprint,
            'result_type' => null,
            'result_id' => null,
            'completed_at' => null,
            'expires_at' => null,
            'created_at' => $createdAt,
        ]);

        $command = DB::table('organization_administration_commands')
            ->where('organization_id', $context->organizationId)
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();

        if (! $command instanceof stdClass) {
            throw new OrganizationAdministrationConflict(
                OrganizationAdministrationConflict::IDEMPOTENCY,
            );
        }

        if (
            ! $this->matches($command, 'actor_membership_id', $actorMembershipId)
            || ! $this->matches($command, 'operation', $operation)
            || ! $this->matches($command, 'target_type', $targetType)
            || ! $this->matches($command, 'target_id', $targetId)
            || ! $this->matches($command, 'request_fingerprint', $fingerprint)
        ) {
            throw new OrganizationAdministrationConflict(
                OrganizationAdministrationConflict::IDEMPOTENCY,
            );
        }

        $storedId = $this->string($command, 'id');
        $completedAt = $this->nullableDate($command, 'completed_at');

        if ($completedAt !== null) {
            $resultType = $this->nullableString($command, 'result_type');
            $resultId = $this->nullableString($command, 'result_id');

            if ($resultType === null || $resultId === null) {
                throw new OrganizationAdministrationConflict(
                    OrganizationAdministrationConflict::IDEMPOTENCY,
                );
            }

            return new OrganizationAdministrationCommandClaim(
                id: $storedId,
                replayed: true,
                resultType: $resultType,
                resultId: $resultId,
                completedAt: $completedAt,
            );
        }

        if (! hash_equals($storedId, $claimId)) {
            throw new OrganizationAdministrationConflict(
                OrganizationAdministrationConflict::IDEMPOTENCY,
            );
        }

        return new OrganizationAdministrationCommandClaim(
            id: $storedId,
            replayed: false,
        );
    }

    public function complete(
        OrganizationAdministrationCommandClaim $claim,
        string $resultType,
        string $resultId,
        CarbonInterface $completedAt,
    ): void {
        $historyDays = (int) config(
            'operator.organization_administration_history_days',
            365,
        );
        $updated = DB::table('organization_administration_commands')
            ->where('id', $claim->id)
            ->whereNull('completed_at')
            ->update([
                'result_type' => $resultType,
                'result_id' => $resultId,
                'completed_at' => $completedAt,
                'expires_at' => $completedAt->copy()->addDays($historyDays),
            ]);

        if ($updated !== 1) {
            throw new OrganizationAdministrationConflict(
                OrganizationAdministrationConflict::IDEMPOTENCY,
            );
        }
    }

    private function matches(stdClass $row, string $property, string $expected): bool
    {
        $actual = $this->nullableString($row, $property);

        return $actual !== null && hash_equals($actual, $expected);
    }

    private function string(stdClass $row, string $property): string
    {
        return $this->nullableString($row, $property)
            ?? throw new OrganizationAdministrationConflict(
                OrganizationAdministrationConflict::IDEMPOTENCY,
            );
    }

    private function nullableString(stdClass $row, string $property): ?string
    {
        $value = $row->{$property} ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function nullableDate(
        stdClass $row,
        string $property,
    ): ?CarbonImmutable {
        $value = $row->{$property} ?? null;

        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        return is_string($value)
            ? CarbonImmutable::parse($value)
            : throw new OrganizationAdministrationConflict(
                OrganizationAdministrationConflict::IDEMPOTENCY,
            );
    }
}

<?php

declare(strict_types=1);

namespace App\Curriculum\Sharing;

use App\Audit\AuditEventRecorder;
use App\Curriculum\CanonicalJson;
use App\Curriculum\Exceptions\CurriculumIdempotencyConflict;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Publication\PublicationEvidenceRevalidator;
use App\Models\User;
use App\Outbox\OutboxEventRecorder;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContextManager;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class CurriculumSharingService
{
    private const array USE_COLUMNS = [
        'clone' => 'allow_clone',
        'enrollment' => 'allow_enrollment',
        'preview' => 'allow_preview',
    ];

    public function __construct(
        private OrganizationContextManager $contexts,
        private OrganizationAuthorization $authorization,
        private PublicationEvidenceRevalidator $evidence,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    /**
     * @param  list<string>  $allowedUses
     */
    public function issue(
        User $actor,
        string $publicationId,
        string $recipientOrganizationId,
        array $allowedUses,
        CarbonInterface $effectiveAt,
        ?CarbonInterface $expiresAt,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): ShareGrantResult {
        $uses = array_values(array_unique($allowedUses));
        sort($uses, SORT_STRING);
        $this->assertIssueInput(
            $uses,
            $effectiveAt,
            $expiresAt,
            $idempotencyKey,
            $correlationId,
        );
        $this->assertPermission($actor);
        $context = $this->contexts->current();
        $actorMembershipId = $this->actorMembershipId($actor);

        return DB::transaction(function () use (
            $actor,
            $publicationId,
            $recipientOrganizationId,
            $uses,
            $effectiveAt,
            $expiresAt,
            $idempotencyKey,
            $correlationId,
            $context,
            $actorMembershipId,
        ): ShareGrantResult {
            $this->assertPermission($actor);
            $publication = DB::table('curriculum_publications')
                ->where('organization_id', $context->organizationId)
                ->where('id', $publicationId)
                ->lockForUpdate()
                ->first(['program_id', 'curriculum_version_id']);

            if (! $publication instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            $programId = $this->string($publication, 'program_id');
            $versionId = $this->string(
                $publication,
                'curriculum_version_id',
            );
            $fingerprint = CanonicalJson::sha256([
                'allowed_uses' => $uses,
                'actor_membership_id' => $actorMembershipId,
                'actor_user_id' => (string) $actor->getKey(),
                'effective_at' => $effectiveAt->toIso8601String(),
                'expires_at' => $expiresAt?->toIso8601String(),
                'operation' => 'curriculum.share.issue',
                'publication_id' => $publicationId,
                'recipient_organization_id' => $recipientOrganizationId,
            ]);
            $replay = $this->replay(
                organizationId: $context->organizationId,
                operation: 'curriculum.share.issue',
                aggregateType: 'curriculum_publication',
                aggregateId: $publicationId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if (is_string($replay)) {
                return $this->grantResult(
                    $replay,
                    true,
                    'active',
                    1,
                );
            }

            $head = DB::table('publication_heads')
                ->where('organization_id', $context->organizationId)
                ->where('program_id', $programId)
                ->where('active_curriculum_version_id', $versionId)
                ->where('active_publication_id', $publicationId)
                ->where('is_suspended', false)
                ->lockForUpdate()
                ->first(['program_id']) instanceof stdClass;
            $recipientActive = DB::table('organizations')
                ->where('id', $recipientOrganizationId)
                ->where('status', 'active')
                ->exists();

            if (
                ! $head
                || ! $recipientActive
                || $recipientOrganizationId === $context->organizationId
            ) {
                throw new CurriculumOperationRejected;
            }

            $this->evidence->lockMutableEvidence();
            $evaluatedNow = Date::now()->toImmutable();

            if (
                $expiresAt instanceof CarbonInterface
                && $expiresAt->lessThanOrEqualTo($evaluatedNow)
            ) {
                throw new CurriculumOperationRejected;
            }

            $verifiedNow = $this->evidence->verify(
                $context->organizationId,
                $publicationId,
                $evaluatedNow,
            );
            $verifiedEffective = $this->evidence->verify(
                $context->organizationId,
                $publicationId,
                $effectiveAt,
            );

            if (
                ! $verifiedNow->sharingAllowed
                || ! $verifiedEffective->sharingAllowed
                || (
                    in_array('clone', $uses, true)
                    && (
                        ! $verifiedNow->derivativeUseAllowed
                        || ! $verifiedEffective->derivativeUseAllowed
                    )
                )
            ) {
                throw new CurriculumOperationRejected;
            }

            $grantId = (string) Str::ulid();
            $eventId = (string) Str::ulid();
            $occurredAt = now();
            $eventCorrelationId = $correlationId
                ?? $context->correlationId
                ?? (string) Str::uuid();
            $useColumns = array_fill_keys(array_values(self::USE_COLUMNS), false);

            foreach ($uses as $use) {
                $column = self::USE_COLUMNS[$use] ?? null;

                if (! is_string($column)) {
                    throw new CurriculumOperationRejected;
                }

                $useColumns[$column] = true;
            }

            DB::table('curriculum_share_grants')->insert([
                'id' => $grantId,
                'organization_id' => $context->organizationId,
                'recipient_organization_id' => $recipientOrganizationId,
                'curriculum_version_id' => $versionId,
                'curriculum_publication_id' => $publicationId,
                ...$useColumns,
                'effective_at' => $effectiveAt,
                'expires_at' => $expiresAt,
                'issued_by_membership_id' => $actorMembershipId,
                'issued_at' => $occurredAt,
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);
            DB::table('curriculum_share_grant_events')->insert([
                'id' => $eventId,
                'organization_id' => $context->organizationId,
                'curriculum_share_grant_id' => $grantId,
                'event_type' => 'issued',
                'actor_membership_id' => $actorMembershipId,
                'reason_key' => null,
                'effective_at' => $effectiveAt,
                'created_at' => $occurredAt,
            ]);
            DB::table('curriculum_share_grant_heads')->insert([
                'organization_id' => $context->organizationId,
                'curriculum_share_grant_id' => $grantId,
                'status' => 'active',
                'last_event_id' => $eventId,
                'lock_version' => 1,
                'updated_at' => $occurredAt,
            ]);
            $this->recordCommand(
                actor: $actor,
                actorMembershipId: $actorMembershipId,
                organizationId: $context->organizationId,
                operation: 'curriculum.share.issue',
                aggregateType: 'curriculum_publication',
                aggregateId: $publicationId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultId: $grantId,
                occurredAt: $occurredAt,
            );
            $this->audit->recordTenant(
                'curriculum.share_grant.issued',
                'curriculum_share_grant',
                $grantId,
                $eventCorrelationId,
            );
            $this->outbox->recordTenant(
                'curriculum.share_grant.issued',
                'curriculum_share_grant',
                $grantId,
                [
                    'curriculum_share_grant_id' => $grantId,
                    'curriculum_version_id' => $versionId,
                    'organization_id' => $context->organizationId,
                    'publication_id' => $publicationId,
                    'recipient_organization_id' => $recipientOrganizationId,
                ],
                $eventCorrelationId,
            );

            return $this->grantResult($grantId, false, 'active', 1);
        }, 3);
    }

    public function withdraw(
        User $actor,
        string $shareGrantId,
        int $expectedGrantVersion,
        string $idempotencyKey,
        string $reasonKey,
        ?string $correlationId = null,
    ): ShareGrantResult {
        if (
            $expectedGrantVersion < 0
            || preg_match('/\A[a-z][a-z0-9_.-]{0,99}\z/', $reasonKey) !== 1
        ) {
            throw new CurriculumOperationRejected;
        }

        $this->assertIdempotencyInput($idempotencyKey, $correlationId);
        $this->assertPermission($actor);
        $context = $this->contexts->current();
        $actorMembershipId = $this->actorMembershipId($actor);

        return DB::transaction(function () use (
            $actor,
            $shareGrantId,
            $expectedGrantVersion,
            $idempotencyKey,
            $reasonKey,
            $correlationId,
            $context,
            $actorMembershipId,
        ): ShareGrantResult {
            $this->assertPermission($actor);
            $grant = DB::table('curriculum_share_grants')
                ->where('organization_id', $context->organizationId)
                ->where('id', $shareGrantId)
                ->lockForUpdate()
                ->first(['curriculum_publication_id']);

            if (! $grant instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            $fingerprint = CanonicalJson::sha256([
                'actor_membership_id' => $actorMembershipId,
                'actor_user_id' => (string) $actor->getKey(),
                'expected_grant_version' => $expectedGrantVersion,
                'operation' => 'curriculum.share.withdraw',
                'reason_key' => $reasonKey,
                'share_grant_id' => $shareGrantId,
            ]);
            $replay = $this->replay(
                organizationId: $context->organizationId,
                operation: 'curriculum.share.withdraw',
                aggregateType: 'curriculum_share_grant',
                aggregateId: $shareGrantId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if (is_string($replay)) {
                return $this->grantResult(
                    $replay,
                    true,
                    'withdrawn',
                    $expectedGrantVersion + 1,
                );
            }

            $head = DB::table('curriculum_share_grant_heads')
                ->where('organization_id', $context->organizationId)
                ->where('curriculum_share_grant_id', $shareGrantId)
                ->lockForUpdate()
                ->first(['status', 'lock_version']);

            if (
                ! $head instanceof stdClass
                || $this->string($head, 'status') !== 'active'
                || $this->integer($head, 'lock_version') !== $expectedGrantVersion
            ) {
                throw new CurriculumOperationRejected;
            }

            $eventId = (string) Str::ulid();
            $occurredAt = now();
            $nextVersion = $expectedGrantVersion + 1;
            $eventCorrelationId = $correlationId
                ?? $context->correlationId
                ?? (string) Str::uuid();

            DB::table('curriculum_share_grant_events')->insert([
                'id' => $eventId,
                'organization_id' => $context->organizationId,
                'curriculum_share_grant_id' => $shareGrantId,
                'event_type' => 'withdrawn',
                'actor_membership_id' => $actorMembershipId,
                'reason_key' => $reasonKey,
                'effective_at' => $occurredAt,
                'created_at' => $occurredAt,
            ]);
            DB::table('curriculum_share_grant_heads')
                ->where('organization_id', $context->organizationId)
                ->where('curriculum_share_grant_id', $shareGrantId)
                ->update([
                    'status' => 'withdrawn',
                    'last_event_id' => $eventId,
                    'lock_version' => $nextVersion,
                    'updated_at' => $occurredAt,
                ]);
            $this->recordCommand(
                actor: $actor,
                actorMembershipId: $actorMembershipId,
                organizationId: $context->organizationId,
                operation: 'curriculum.share.withdraw',
                aggregateType: 'curriculum_share_grant',
                aggregateId: $shareGrantId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultId: $shareGrantId,
                occurredAt: $occurredAt,
            );
            $this->audit->recordTenant(
                'curriculum.share_grant.withdrawn',
                'curriculum_share_grant',
                $shareGrantId,
                $eventCorrelationId,
            );
            $this->outbox->recordTenant(
                'curriculum.share_grant.withdrawn',
                'curriculum_share_grant',
                $shareGrantId,
                [
                    'curriculum_share_grant_id' => $shareGrantId,
                    'organization_id' => $context->organizationId,
                    'publication_id' => $this->string(
                        $grant,
                        'curriculum_publication_id',
                    ),
                ],
                $eventCorrelationId,
            );

            return $this->grantResult(
                $shareGrantId,
                false,
                'withdrawn',
                $nextVersion,
            );
        }, 3);
    }

    /**
     * @param  list<string>  $uses
     */
    private function assertIssueInput(
        array $uses,
        CarbonInterface $effectiveAt,
        ?CarbonInterface $expiresAt,
        string $idempotencyKey,
        ?string $correlationId,
    ): void {
        if (
            $uses === []
            || array_diff($uses, array_keys(self::USE_COLUMNS)) !== []
            || (
                $expiresAt instanceof CarbonInterface
                && $expiresAt->lessThanOrEqualTo(now())
            )
            || (
                $expiresAt instanceof CarbonInterface
                && $expiresAt->lessThanOrEqualTo($effectiveAt)
            )
        ) {
            throw new CurriculumOperationRejected;
        }

        $this->assertIdempotencyInput($idempotencyKey, $correlationId);
    }

    private function assertIdempotencyInput(
        string $idempotencyKey,
        ?string $correlationId,
    ): void {
        if (
            preg_match(
                '/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/',
                $idempotencyKey,
            ) !== 1
            || (
                $correlationId !== null
                && ! Str::isUuid($correlationId)
            )
        ) {
            throw new CurriculumOperationRejected;
        }
    }

    private function assertPermission(User $actor): void
    {
        if (
            ! $this->authorization->allows(
                $actor,
                'curriculum.share.manage',
            )
        ) {
            throw new CurriculumOperationRejected;
        }
    }

    private function actorMembershipId(User $actor): string
    {
        $context = $this->contexts->current();

        if (
            $context->membershipId === null
            || $context->actorUserId !== (string) $actor->getKey()
        ) {
            throw new CurriculumOperationRejected;
        }

        return $context->membershipId;
    }

    private function replay(
        string $organizationId,
        string $operation,
        string $aggregateType,
        string $aggregateId,
        string $idempotencyKey,
        string $fingerprint,
    ): ?string {
        $command = DB::table('publication_commands')
            ->where('organization_id', $organizationId)
            ->where('operation', $operation)
            ->where('aggregate_type', $aggregateType)
            ->where('aggregate_id', $aggregateId)
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first(['request_fingerprint', 'result_id']);

        if (! $command instanceof stdClass) {
            return null;
        }

        if (
            ! hash_equals(
                $this->string($command, 'request_fingerprint'),
                $fingerprint,
            )
        ) {
            throw new CurriculumIdempotencyConflict;
        }

        return $this->string($command, 'result_id');
    }

    private function recordCommand(
        User $actor,
        string $actorMembershipId,
        string $organizationId,
        string $operation,
        string $aggregateType,
        string $aggregateId,
        string $idempotencyKey,
        string $fingerprint,
        string $resultId,
        CarbonInterface $occurredAt,
    ): void {
        DB::table('publication_commands')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'operation' => $operation,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'idempotency_key' => $idempotencyKey,
            'request_fingerprint' => $fingerprint,
            'result_type' => 'curriculum_share_grant',
            'result_id' => $resultId,
            'actor_type' => 'owner_membership',
            'actor_membership_id' => $actorMembershipId,
            'actor_user_id' => $actor->getKey(),
            'completed_at' => $occurredAt,
            'created_at' => $occurredAt,
        ]);
    }

    private function grantResult(
        string $shareGrantId,
        bool $replayed,
        string $resultStatus,
        int $resultVersion,
    ): ShareGrantResult {
        $context = $this->contexts->current();
        $grant = DB::table('curriculum_share_grants')
            ->join('curriculum_share_grant_heads', function ($join): void {
                $join
                    ->on('curriculum_share_grant_heads.organization_id', '=', 'curriculum_share_grants.organization_id')
                    ->on('curriculum_share_grant_heads.curriculum_share_grant_id', '=', 'curriculum_share_grants.id');
            })
            ->where(
                'curriculum_share_grants.organization_id',
                $context->organizationId,
            )
            ->where('curriculum_share_grants.id', $shareGrantId)
            ->first([
                'curriculum_share_grants.organization_id',
                'curriculum_share_grants.recipient_organization_id',
                'curriculum_share_grants.curriculum_version_id',
                'curriculum_share_grants.curriculum_publication_id',
                'curriculum_share_grants.allow_preview',
                'curriculum_share_grants.allow_enrollment',
                'curriculum_share_grants.allow_clone',
                'curriculum_share_grant_heads.lock_version',
                'curriculum_share_grant_heads.status',
            ]);

        if (! $grant instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        $uses = [];

        foreach (self::USE_COLUMNS as $use => $column) {
            if ($this->boolean($grant, $column)) {
                $uses[] = $use;
            }
        }

        return new ShareGrantResult(
            shareGrantId: $shareGrantId,
            ownerOrganizationId: $this->string($grant, 'organization_id'),
            recipientOrganizationId: $this->string(
                $grant,
                'recipient_organization_id',
            ),
            curriculumVersionId: $this->string(
                $grant,
                'curriculum_version_id',
            ),
            publicationId: $this->string(
                $grant,
                'curriculum_publication_id',
            ),
            allowedUses: $uses,
            grantVersion: $resultVersion,
            status: $resultStatus,
            replayed: $replayed,
        );
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    private function integer(stdClass $row, string $field): int
    {
        $value = $row->{$field} ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new CurriculumOperationRejected;
    }

    private function boolean(stdClass $row, string $field): bool
    {
        $value = $row->{$field} ?? null;

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 0 || $value === '0') {
            return false;
        }

        if ($value === 1 || $value === '1') {
            return true;
        }

        throw new CurriculumOperationRejected;
    }
}

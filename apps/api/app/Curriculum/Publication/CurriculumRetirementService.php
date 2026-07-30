<?php

declare(strict_types=1);

namespace App\Curriculum\Publication;

use App\Audit\AuditEventRecorder;
use App\Curriculum\CanonicalJson;
use App\Curriculum\Exceptions\CurriculumIdempotencyConflict;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Models\User;
use App\Outbox\OutboxEventRecorder;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class CurriculumRetirementService
{
    private const string OPERATION = 'curriculum.retire';

    public function __construct(
        private OrganizationContextManager $contexts,
        private OrganizationAuthorization $authorization,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function retire(
        User $actor,
        string $programId,
        int $expectedHeadVersion,
        string $idempotencyKey,
        string $reasonKey,
        ?string $correlationId = null,
    ): PublicationRetirementResult {
        $this->assertInput(
            $programId,
            $expectedHeadVersion,
            $idempotencyKey,
            $reasonKey,
            $correlationId,
        );

        if (! $this->authorization->allows($actor, 'curriculum.publish')) {
            throw new CurriculumOperationRejected;
        }

        $context = $this->contexts->current();
        $actorMembershipId = $context->membershipId;

        if (
            $actorMembershipId === null
            || ! hash_equals($context->actorUserId, (string) $actor->getKey())
        ) {
            throw new CurriculumOperationRejected;
        }

        return DB::transaction(function () use (
            $actor,
            $programId,
            $expectedHeadVersion,
            $idempotencyKey,
            $reasonKey,
            $correlationId,
            $context,
            $actorMembershipId,
        ): PublicationRetirementResult {
            if (! $this->authorization->allows($actor, 'curriculum.publish')) {
                throw new CurriculumOperationRejected;
            }

            $program = DB::table('programs')
                ->where('organization_id', $context->organizationId)
                ->where('id', $programId)
                ->lockForUpdate()
                ->first(['id']);

            if (! $program instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            $fingerprint = CanonicalJson::sha256([
                'actor_membership_id' => $actorMembershipId,
                'actor_user_id' => (string) $actor->getKey(),
                'expected_head_version' => $expectedHeadVersion,
                'operation' => self::OPERATION,
                'program_id' => $programId,
                'reason_key' => $reasonKey,
            ]);
            $replay = DB::table('publication_commands')
                ->where('organization_id', $context->organizationId)
                ->where('operation', self::OPERATION)
                ->where('aggregate_type', 'program')
                ->where('aggregate_id', $programId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first(['request_fingerprint', 'result_id']);

            if ($replay instanceof stdClass) {
                if (
                    ! hash_equals(
                        $this->string($replay, 'request_fingerprint'),
                        $fingerprint,
                    )
                ) {
                    throw new CurriculumIdempotencyConflict;
                }

                return $this->result(
                    $context->organizationId,
                    $programId,
                    $this->string($replay, 'result_id'),
                    $expectedHeadVersion + 1,
                    true,
                );
            }

            $head = DB::table('publication_heads')
                ->where('organization_id', $context->organizationId)
                ->where('program_id', $programId)
                ->lockForUpdate()
                ->first([
                    'active_curriculum_version_id',
                    'active_publication_id',
                    'is_suspended',
                    'lock_version',
                ]);

            if (
                ! $head instanceof stdClass
                || $this->integer($head, 'lock_version')
                    !== $expectedHeadVersion
                || $this->boolean($head, 'is_suspended')
            ) {
                throw new CurriculumOperationRejected;
            }

            $curriculumVersionId = $this->string(
                $head,
                'active_curriculum_version_id',
            );
            $publicationId = $this->string(
                $head,
                'active_publication_id',
            );
            $now = Date::now();
            $eventId = (string) Str::ulid();
            $nextHeadVersion = $expectedHeadVersion + 1;
            $eventCorrelationId = $correlationId
                ?? $context->correlationId
                ?? (string) Str::uuid();

            DB::table('publication_events')->insert([
                'id' => $eventId,
                'organization_id' => $context->organizationId,
                'program_id' => $programId,
                'curriculum_version_id' => $curriculumVersionId,
                'curriculum_publication_id' => $publicationId,
                'event_type' => 'retired',
                'previous_curriculum_version_id' => null,
                'previous_publication_id' => null,
                'actor_type' => 'owner_membership',
                'actor_membership_id' => $actorMembershipId,
                'platform_actor_user_id' => null,
                'reason_key' => $reasonKey,
                'correlation_id' => $eventCorrelationId,
                'effective_at' => $now,
                'created_at' => $now,
            ]);
            DB::table('publication_heads')
                ->where('organization_id', $context->organizationId)
                ->where('program_id', $programId)
                ->update([
                    'active_curriculum_version_id' => null,
                    'active_publication_id' => null,
                    'last_event_id' => $eventId,
                    'is_suspended' => false,
                    'lock_version' => $nextHeadVersion,
                    'updated_at' => $now,
                ]);
            DB::table('publication_commands')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $context->organizationId,
                'operation' => self::OPERATION,
                'aggregate_type' => 'program',
                'aggregate_id' => $programId,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'result_type' => 'curriculum_publication',
                'result_id' => $publicationId,
                'actor_type' => 'owner_membership',
                'actor_membership_id' => $actorMembershipId,
                'actor_user_id' => $actor->getKey(),
                'completed_at' => $now,
                'created_at' => $now,
            ]);
            $this->audit->recordTenant(
                'curriculum.publication.retired',
                'curriculum_publication',
                $publicationId,
                $eventCorrelationId,
            );
            $this->outbox->recordTenant(
                'curriculum.publication.retired',
                'curriculum_publication',
                $publicationId,
                [
                    'curriculum_version_id' => $curriculumVersionId,
                    'organization_id' => $context->organizationId,
                    'program_id' => $programId,
                    'publication_event_id' => $eventId,
                    'publication_id' => $publicationId,
                ],
                $eventCorrelationId,
            );

            return new PublicationRetirementResult(
                programId: $programId,
                curriculumVersionId: $curriculumVersionId,
                publicationId: $publicationId,
                headVersion: $nextHeadVersion,
                replayed: false,
            );
        }, 3);
    }

    private function result(
        string $organizationId,
        string $programId,
        string $publicationId,
        int $headVersion,
        bool $replayed,
    ): PublicationRetirementResult {
        $publication = DB::table('curriculum_publications')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('id', $publicationId)
            ->first(['curriculum_version_id']);

        if (! $publication instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        return new PublicationRetirementResult(
            programId: $programId,
            curriculumVersionId: $this->string(
                $publication,
                'curriculum_version_id',
            ),
            publicationId: $publicationId,
            headVersion: $headVersion,
            replayed: $replayed,
        );
    }

    private function assertInput(
        string $programId,
        int $expectedHeadVersion,
        string $idempotencyKey,
        string $reasonKey,
        ?string $correlationId,
    ): void {
        if (
            ! Str::isUlid($programId)
            || $expectedHeadVersion < 1
            || preg_match(
                '/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/',
                $idempotencyKey,
            ) !== 1
            || preg_match('/\A[a-z][a-z0-9_.-]{0,99}\z/', $reasonKey) !== 1
            || (
                $correlationId !== null
                && ! Str::isUuid($correlationId)
            )
        ) {
            throw new CurriculumOperationRejected;
        }
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

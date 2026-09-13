<?php

declare(strict_types=1);

namespace App\Curriculum\Publication;

use App\Audit\AuditEventRecorder;
use App\Curriculum\CanonicalJson;
use App\Curriculum\Exceptions\CurriculumHeadConflict;
use App\Curriculum\Exceptions\CurriculumIdempotencyConflict;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Models\User;
use App\Outbox\OutboxEventRecorder;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class CurriculumPublisher
{
    private const string OPERATION = 'curriculum.publish';

    public function __construct(
        private OrganizationContextManager $contexts,
        private OrganizationAuthorization $authorization,
        private PublicationSnapshotWriter $snapshots,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function publish(
        User $actor,
        string $curriculumVersionId,
        int $expectedHeadVersion,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): PublicationResult {
        $this->assertCommandInput(
            $expectedHeadVersion,
            $idempotencyKey,
            $correlationId,
        );

        if (! $this->authorization->allows($actor, 'curriculum.publish')) {
            throw new CurriculumOperationRejected;
        }

        $context = $this->contexts->current();
        $actorMembershipId = $context->membershipId;

        if (
            $actorMembershipId === null
            || $context->actorUserId !== (string) $actor->getKey()
        ) {
            throw new CurriculumOperationRejected;
        }

        return DB::transaction(function () use (
            $actor,
            $curriculumVersionId,
            $expectedHeadVersion,
            $idempotencyKey,
            $correlationId,
            $context,
            $actorMembershipId,
        ): PublicationResult {
            if (! $this->authorization->allows($actor, 'curriculum.publish')) {
                throw new CurriculumOperationRejected;
            }

            $version = DB::table('curriculum_versions')
                ->where('organization_id', $context->organizationId)
                ->where('id', $curriculumVersionId)
                ->lockForUpdate()
                ->first(['id', 'program_id']);

            if (! $version instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            $programId = $this->requiredString($version, 'program_id');
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
                'curriculum_version_id' => $curriculumVersionId,
                'expected_head_version' => $expectedHeadVersion,
                'operation' => self::OPERATION,
                'program_id' => $programId,
            ]);
            $replay = $this->replay(
                organizationId: $context->organizationId,
                programId: $programId,
                curriculumVersionId: $curriculumVersionId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                expectedHeadVersion: $expectedHeadVersion,
            );

            if ($replay instanceof PublicationResult) {
                return $replay;
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
            $headVersion = $head instanceof stdClass
                ? $this->requiredInteger($head, 'lock_version')
                : 0;

            if ($headVersion !== $expectedHeadVersion) {
                throw new CurriculumHeadConflict;
            }

            $evaluatedAt = now();
            $snapshot = $this->snapshots->create(
                organizationId: $context->organizationId,
                programId: $programId,
                curriculumVersionId: $curriculumVersionId,
                actorMembershipId: $actorMembershipId,
                evaluatedAt: $evaluatedAt,
            );
            $publicationId = $snapshot->publicationId;
            $scheduledEventId = (string) Str::ulid();
            $eventId = (string) Str::ulid();
            $commandId = (string) Str::ulid();
            $nextHeadVersion = $headVersion + 1;
            $eventCorrelationId = $correlationId
                ?? $context->correlationId
                ?? (string) Str::uuid();
            $previousVersionId = $head instanceof stdClass
                ? $this->optionalString(
                    $head,
                    'active_curriculum_version_id',
                )
                : null;
            $previousPublicationId = $head instanceof stdClass
                ? $this->optionalString($head, 'active_publication_id')
                : null;

            DB::table('publication_events')->insert([
                [
                    'id' => $scheduledEventId,
                    'organization_id' => $context->organizationId,
                    'program_id' => $programId,
                    'curriculum_version_id' => $curriculumVersionId,
                    'curriculum_publication_id' => $publicationId,
                    'event_type' => 'scheduled',
                    'previous_curriculum_version_id' => null,
                    'previous_publication_id' => null,
                    'actor_type' => 'owner_membership',
                    'actor_membership_id' => $actorMembershipId,
                    'platform_actor_user_id' => null,
                    'reason_key' => 'immediate_publish',
                    'correlation_id' => $eventCorrelationId,
                    'effective_at' => $evaluatedAt,
                    'created_at' => $evaluatedAt,
                ],
                [
                    'id' => $eventId,
                    'organization_id' => $context->organizationId,
                    'program_id' => $programId,
                    'curriculum_version_id' => $curriculumVersionId,
                    'curriculum_publication_id' => $publicationId,
                    'event_type' => 'activated',
                    'previous_curriculum_version_id' => $previousVersionId,
                    'previous_publication_id' => $previousPublicationId,
                    'actor_type' => 'owner_membership',
                    'actor_membership_id' => $actorMembershipId,
                    'platform_actor_user_id' => null,
                    'reason_key' => $previousPublicationId === null
                        ? 'initial_publish'
                        : 'replacement_publish',
                    'correlation_id' => $eventCorrelationId,
                    'effective_at' => $evaluatedAt,
                    'created_at' => $evaluatedAt,
                ],
            ]);

            if ($head instanceof stdClass) {
                DB::table('publication_heads')
                    ->where('organization_id', $context->organizationId)
                    ->where('program_id', $programId)
                    ->update([
                        'active_curriculum_version_id' => $curriculumVersionId,
                        'active_publication_id' => $publicationId,
                        'last_event_id' => $eventId,
                        'lock_version' => $nextHeadVersion,
                        'updated_at' => $evaluatedAt,
                    ]);
            } else {
                DB::table('publication_heads')->insert([
                    'organization_id' => $context->organizationId,
                    'program_id' => $programId,
                    'active_curriculum_version_id' => $curriculumVersionId,
                    'active_publication_id' => $publicationId,
                    'last_event_id' => $eventId,
                    'is_suspended' => false,
                    'lock_version' => $nextHeadVersion,
                    'updated_at' => $evaluatedAt,
                ]);
            }

            try {
                DB::table('publication_commands')->insert([
                    'id' => $commandId,
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
                    'completed_at' => $evaluatedAt,
                    'created_at' => $evaluatedAt,
                ]);
            } catch (QueryException $exception) {
                if ((string) $exception->getCode() === '23505') {
                    throw new CurriculumIdempotencyConflict;
                }

                throw $exception;
            }

            $this->audit->recordTenant(
                'curriculum.publication.scheduled',
                'curriculum_publication',
                $publicationId,
                $eventCorrelationId,
            );
            $this->outbox->recordTenant(
                'curriculum.publication.scheduled',
                'curriculum_publication',
                $publicationId,
                [
                    'curriculum_version_id' => $curriculumVersionId,
                    'organization_id' => $context->organizationId,
                    'program_id' => $programId,
                    'publication_event_id' => $scheduledEventId,
                    'publication_id' => $publicationId,
                ],
                $eventCorrelationId,
            );
            $this->audit->recordTenant(
                'curriculum.publication.activated',
                'curriculum_publication',
                $publicationId,
                $eventCorrelationId,
            );
            $this->outbox->recordTenant(
                'curriculum.publication.activated',
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

            return new PublicationResult(
                publicationId: $publicationId,
                curriculumVersionId: $curriculumVersionId,
                manifestHash: $snapshot->manifestHash,
                headVersion: $nextHeadVersion,
                replayed: false,
            );
        }, 3);
    }

    private function replay(
        string $organizationId,
        string $programId,
        string $curriculumVersionId,
        string $idempotencyKey,
        string $fingerprint,
        int $expectedHeadVersion,
    ): ?PublicationResult {
        $command = DB::table('publication_commands')
            ->where('organization_id', $organizationId)
            ->where('operation', self::OPERATION)
            ->where('aggregate_type', 'program')
            ->where('aggregate_id', $programId)
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first(['request_fingerprint', 'result_id']);

        if (! $command instanceof stdClass) {
            return null;
        }

        if (
            ! hash_equals(
                $this->requiredString($command, 'request_fingerprint'),
                $fingerprint,
            )
        ) {
            throw new CurriculumIdempotencyConflict;
        }

        $publicationId = $this->requiredString($command, 'result_id');
        $publication = DB::table('curriculum_publications')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('id', $publicationId)
            ->first([
                'curriculum_version_id',
                'manifest_hash',
            ]);
        if (
            ! $publication instanceof stdClass
            || $this->requiredString(
                $publication,
                'curriculum_version_id',
            ) !== $curriculumVersionId
        ) {
            throw new CurriculumOperationRejected;
        }

        return new PublicationResult(
            publicationId: $publicationId,
            curriculumVersionId: $curriculumVersionId,
            manifestHash: $this->requiredString(
                $publication,
                'manifest_hash',
            ),
            headVersion: $expectedHeadVersion + 1,
            replayed: true,
        );
    }

    private function assertCommandInput(
        int $expectedHeadVersion,
        string $idempotencyKey,
        ?string $correlationId,
    ): void {
        if (
            $expectedHeadVersion < 0
            || preg_match(
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

    private function requiredString(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    private function optionalString(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || $value === '') {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    private function requiredInteger(stdClass $row, string $field): int
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
}

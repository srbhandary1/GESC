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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class CurriculumRollbackService
{
    private const string OPERATION = 'curriculum.rollback';

    public function __construct(
        private OrganizationContextManager $contexts,
        private OrganizationAuthorization $authorization,
        private PublicationEvidenceRevalidator $evidence,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function rollback(
        User $actor,
        string $programId,
        string $targetPublicationId,
        int $expectedHeadVersion,
        string $idempotencyKey,
        string $reasonKey,
        ?string $correlationId = null,
    ): PublicationResult {
        $this->assertInput(
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
            || $context->actorUserId !== (string) $actor->getKey()
        ) {
            throw new CurriculumOperationRejected;
        }

        return DB::transaction(function () use (
            $actor,
            $programId,
            $targetPublicationId,
            $expectedHeadVersion,
            $idempotencyKey,
            $reasonKey,
            $correlationId,
            $context,
            $actorMembershipId,
        ): PublicationResult {
            if (! $this->authorization->allows($actor, 'curriculum.publish')) {
                throw new CurriculumOperationRejected;
            }

            $programExists = DB::table('programs')
                ->where('organization_id', $context->organizationId)
                ->where('id', $programId)
                ->lockForUpdate()
                ->exists();

            if (! $programExists) {
                throw new CurriculumOperationRejected;
            }

            $fingerprint = CanonicalJson::sha256([
                'actor_membership_id' => $actorMembershipId,
                'actor_user_id' => (string) $actor->getKey(),
                'expected_head_version' => $expectedHeadVersion,
                'operation' => self::OPERATION,
                'program_id' => $programId,
                'reason_key' => $reasonKey,
                'target_publication_id' => $targetPublicationId,
            ]);
            $existing = DB::table('publication_commands')
                ->where('organization_id', $context->organizationId)
                ->where('operation', self::OPERATION)
                ->where('aggregate_type', 'program')
                ->where('aggregate_id', $programId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first(['request_fingerprint', 'result_id']);

            if ($existing instanceof stdClass) {
                if (
                    ! hash_equals(
                        $this->string($existing, 'request_fingerprint'),
                        $fingerprint,
                    )
                    || $this->string($existing, 'result_id') !== $targetPublicationId
                ) {
                    throw new CurriculumIdempotencyConflict;
                }

                return $this->result(
                    $context->organizationId,
                    $programId,
                    $targetPublicationId,
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

            if (! $head instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            if ($this->integer($head, 'lock_version') !== $expectedHeadVersion) {
                throw new CurriculumHeadConflict;
            }

            $previousVersionId = $this->nullableString(
                $head,
                'active_curriculum_version_id',
            );
            $previousPublicationId = $this->nullableString(
                $head,
                'active_publication_id',
            );

            if (
                ($previousVersionId === null) !==
                    ($previousPublicationId === null)
                || $previousPublicationId === $targetPublicationId
            ) {
                throw new CurriculumOperationRejected;
            }

            $target = DB::table('curriculum_publications')
                ->where('organization_id', $context->organizationId)
                ->where('program_id', $programId)
                ->where('id', $targetPublicationId)
                ->first([
                    'curriculum_version_id',
                    'manifest',
                    'manifest_hash',
                ]);

            if (! $target instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            $this->lockManifestInputs();
            $occurredAt = now();
            $priorActivation = DB::table('publication_events')
                ->where('organization_id', $context->organizationId)
                ->where('program_id', $programId)
                ->where('curriculum_publication_id', $targetPublicationId)
                ->whereIn('event_type', ['activated', 'rollback_activated'])
                ->where('effective_at', '<=', $occurredAt)
                ->lockForUpdate()
                ->first(['id']);

            if (! $priorActivation instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            $targetVersionId = $this->string(
                $target,
                'curriculum_version_id',
            );
            $storedManifest = $this->json($target, 'manifest');
            $storedHash = $this->sha256($target, 'manifest_hash');

            if (
                ! hash_equals(
                    $storedHash,
                    CanonicalJson::sha256($storedManifest),
                )
            ) {
                throw new CurriculumOperationRejected;
            }

            $verified = $this->evidence->verify(
                $context->organizationId,
                $targetPublicationId,
                $occurredAt,
                rejectSupersededRights: true,
                requireCurrentManagedMedia: true,
            );

            if (
                $verified->programId !== $programId
                || $verified->curriculumVersionId !== $targetVersionId
                || ! hash_equals($verified->manifestHash, $storedHash)
            ) {
                throw new CurriculumOperationRejected;
            }

            $eventId = (string) Str::ulid();
            $commandId = (string) Str::ulid();
            $nextHeadVersion = $expectedHeadVersion + 1;
            $eventCorrelationId = $correlationId
                ?? $context->correlationId
                ?? (string) Str::uuid();

            DB::table('publication_events')->insert([
                'id' => $eventId,
                'organization_id' => $context->organizationId,
                'program_id' => $programId,
                'curriculum_version_id' => $targetVersionId,
                'curriculum_publication_id' => $targetPublicationId,
                'event_type' => 'rollback_activated',
                'previous_curriculum_version_id' => $previousVersionId,
                'previous_publication_id' => $previousPublicationId,
                'actor_type' => 'owner_membership',
                'actor_membership_id' => $actorMembershipId,
                'platform_actor_user_id' => null,
                'reason_key' => $reasonKey,
                'correlation_id' => $eventCorrelationId,
                'effective_at' => $occurredAt,
                'created_at' => $occurredAt,
            ]);
            DB::table('publication_heads')
                ->where('organization_id', $context->organizationId)
                ->where('program_id', $programId)
                ->update([
                    'active_curriculum_version_id' => $targetVersionId,
                    'active_publication_id' => $targetPublicationId,
                    'last_event_id' => $eventId,
                    'lock_version' => $nextHeadVersion,
                    'updated_at' => $occurredAt,
                ]);
            DB::table('publication_commands')->insert([
                'id' => $commandId,
                'organization_id' => $context->organizationId,
                'operation' => self::OPERATION,
                'aggregate_type' => 'program',
                'aggregate_id' => $programId,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'result_type' => 'curriculum_publication',
                'result_id' => $targetPublicationId,
                'actor_type' => 'owner_membership',
                'actor_membership_id' => $actorMembershipId,
                'actor_user_id' => $actor->getKey(),
                'completed_at' => $occurredAt,
                'created_at' => $occurredAt,
            ]);
            $this->audit->recordTenant(
                'curriculum.publication.rollback_activated',
                'curriculum_publication',
                $targetPublicationId,
                $eventCorrelationId,
            );
            $payload = [
                'curriculum_version_id' => $targetVersionId,
                'organization_id' => $context->organizationId,
                'program_id' => $programId,
                'publication_event_id' => $eventId,
                'publication_id' => $targetPublicationId,
            ];

            if ($previousPublicationId !== null) {
                $payload['previous_publication_id'] = $previousPublicationId;
            }

            $this->outbox->recordTenant(
                'curriculum.publication.rollback_activated',
                'curriculum_publication',
                $targetPublicationId,
                $payload,
                $eventCorrelationId,
            );

            return new PublicationResult(
                publicationId: $targetPublicationId,
                curriculumVersionId: $targetVersionId,
                manifestHash: $storedHash,
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
    ): PublicationResult {
        $publication = DB::table('curriculum_publications')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('id', $publicationId)
            ->first(['curriculum_version_id', 'manifest_hash']);
        if (! $publication instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        return new PublicationResult(
            publicationId: $publicationId,
            curriculumVersionId: $this->string(
                $publication,
                'curriculum_version_id',
            ),
            manifestHash: $this->sha256($publication, 'manifest_hash'),
            headVersion: $headVersion,
            replayed: $replayed,
        );
    }

    private function lockManifestInputs(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'LOCK TABLE learning_objectives, objective_prerequisites, curriculum_modules, lessons, lesson_objectives, content_documents, content_revisions, content_revision_heads, localized_variants, learning_items, curriculum_missions, mission_items, curriculum_graph_revisions, curriculum_graph_heads, authority_appointments, authority_appointment_events, curriculum_graph_reviews, content_reviews, media_assets, media_rights_revisions, content_revision_media_assets IN SHARE MODE',
        );
    }

    private function assertInput(
        int $expectedHeadVersion,
        string $idempotencyKey,
        string $reasonKey,
        ?string $correlationId,
    ): void {
        if (
            $expectedHeadVersion < 0
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

    /**
     * @return array<string, mixed>
     */
    private function json(stdClass $row, string $field): array
    {
        $value = $row->{$field} ?? null;

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            throw new CurriculumOperationRejected;
        }

        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new CurriculumOperationRejected;
        }

        return $decoded;
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $this->nullableString($row, $field);

        if ($value === null || $value === '') {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    private function nullableString(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;

        if ($value !== null && ! is_string($value)) {
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

    private function sha256(stdClass $row, string $field): string
    {
        $value = $this->string($row, $field);

        if (preg_match('/\A[0-9a-f]{64}\z/', $value) !== 1) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }
}

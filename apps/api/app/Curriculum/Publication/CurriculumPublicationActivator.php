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
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class CurriculumPublicationActivator
{
    private const string OPERATION = 'curriculum.activate';

    public function __construct(
        private OrganizationContextManager $contexts,
        private OrganizationAuthorization $authorization,
        private PublicationEvidenceRevalidator $evidence,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function activate(
        User $actor,
        string $publicationId,
        int $expectedHeadVersion,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): PublicationActivationResult {
        $this->assertInput(
            publicationId: $publicationId,
            expectedHeadVersion: $expectedHeadVersion,
            idempotencyKey: $idempotencyKey,
            correlationId: $correlationId,
        );

        if (! $this->authorization->allows($actor, 'curriculum.publish')) {
            throw new CurriculumOperationRejected;
        }

        $context = $this->contexts->current();
        $actorMembershipId = $context->membershipId;

        if (
            $actorMembershipId === null
            || ! hash_equals(
                $context->actorUserId,
                (string) $actor->getKey(),
            )
        ) {
            throw new CurriculumOperationRejected;
        }

        return DB::transaction(function () use (
            $actor,
            $publicationId,
            $expectedHeadVersion,
            $idempotencyKey,
            $correlationId,
            $context,
            $actorMembershipId,
        ): PublicationActivationResult {
            if (! $this->authorization->allows($actor, 'curriculum.publish')) {
                throw new CurriculumOperationRejected;
            }

            $identity = DB::table('curriculum_publications')
                ->where('organization_id', $context->organizationId)
                ->where('id', $publicationId)
                ->first(['program_id']);

            if (! $identity instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            $programId = $this->string($identity, 'program_id');
            $program = DB::table('programs')
                ->where('organization_id', $context->organizationId)
                ->where('id', $programId)
                ->lockForUpdate()
                ->first(['id']);

            if (! $program instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            $publication = DB::table('curriculum_publications')
                ->where('organization_id', $context->organizationId)
                ->where('program_id', $programId)
                ->where('id', $publicationId)
                ->lockForUpdate()
                ->first(['curriculum_version_id', 'manifest_hash']);

            if (! $publication instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            $curriculumVersionId = $this->string(
                $publication,
                'curriculum_version_id',
            );
            $fingerprint = CanonicalJson::sha256([
                'actor_membership_id' => $actorMembershipId,
                'actor_user_id' => (string) $actor->getKey(),
                'expected_head_version' => $expectedHeadVersion,
                'operation' => self::OPERATION,
                'program_id' => $programId,
                'publication_id' => $publicationId,
            ]);
            $replay = $this->replay(
                organizationId: $context->organizationId,
                programId: $programId,
                publicationId: $publicationId,
                fingerprint: $fingerprint,
                idempotencyKey: $idempotencyKey,
                expectedHeadVersion: $expectedHeadVersion,
            );

            if ($replay instanceof PublicationActivationResult) {
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

            if (! $head instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            if ($this->integer($head, 'lock_version') !== $expectedHeadVersion) {
                throw new CurriculumHeadConflict;
            }

            $scheduledEvent = DB::table('publication_events')
                ->where('organization_id', $context->organizationId)
                ->where('program_id', $programId)
                ->where('curriculum_publication_id', $publicationId)
                ->whereIn('event_type', [
                    'scheduled',
                    'schedule_cancelled',
                    'activated',
                    'rollback_activated',
                    'retired',
                ])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first(['event_type', 'effective_at']);

            if (
                ! $scheduledEvent instanceof stdClass
                || $this->string($scheduledEvent, 'event_type') !== 'scheduled'
            ) {
                throw new CurriculumOperationRejected;
            }

            $this->evidence->lockMutableEvidence();
            $activatedAt = Date::now()->toImmutable()->utc();

            if (
                $this->date($scheduledEvent, 'effective_at')
                    ->greaterThan($activatedAt)
            ) {
                throw new CurriculumOperationRejected;
            }

            $verified = $this->evidence->verify(
                ownerOrganizationId: $context->organizationId,
                publicationId: $publicationId,
                evaluatedAt: $activatedAt,
                lockMutableEvidence: false,
                rejectSupersededRights: true,
                requireCurrentManagedMedia: true,
            );

            if (
                ! hash_equals($verified->programId, $programId)
                || ! hash_equals(
                    $verified->curriculumVersionId,
                    $curriculumVersionId,
                )
                || ! hash_equals(
                    $verified->manifestHash,
                    $this->string($publication, 'manifest_hash'),
                )
            ) {
                throw new CurriculumOperationRejected;
            }

            $previousVersionId = $this->optionalString(
                $head,
                'active_curriculum_version_id',
            );
            $previousPublicationId = $this->optionalString(
                $head,
                'active_publication_id',
            );
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
                'event_type' => 'activated',
                'previous_curriculum_version_id' => $previousVersionId,
                'previous_publication_id' => $previousPublicationId,
                'actor_type' => 'owner_membership',
                'actor_membership_id' => $actorMembershipId,
                'platform_actor_user_id' => null,
                'reason_key' => $previousPublicationId === null
                    ? 'scheduled_initial_publish'
                    : 'scheduled_replacement_publish',
                'correlation_id' => $eventCorrelationId,
                'effective_at' => $activatedAt,
                'created_at' => $activatedAt,
            ]);
            DB::table('publication_heads')
                ->where('organization_id', $context->organizationId)
                ->where('program_id', $programId)
                ->update([
                    'active_curriculum_version_id' => $curriculumVersionId,
                    'active_publication_id' => $publicationId,
                    'last_event_id' => $eventId,
                    'lock_version' => $nextHeadVersion,
                    'updated_at' => $activatedAt,
                ]);
            $this->recordCommand(
                organizationId: $context->organizationId,
                programId: $programId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                publicationId: $publicationId,
                actorMembershipId: $actorMembershipId,
                actorUserId: (string) $actor->getKey(),
                completedAt: $activatedAt,
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

            return new PublicationActivationResult(
                publicationId: $publicationId,
                curriculumVersionId: $curriculumVersionId,
                manifestHash: $verified->manifestHash,
                activatedAt: $activatedAt,
                headVersion: $nextHeadVersion,
                replayed: false,
            );
        }, 3);
    }

    private function replay(
        string $organizationId,
        string $programId,
        string $publicationId,
        string $fingerprint,
        string $idempotencyKey,
        int $expectedHeadVersion,
    ): ?PublicationActivationResult {
        $command = DB::table('publication_commands')
            ->where('organization_id', $organizationId)
            ->where('operation', self::OPERATION)
            ->where('aggregate_type', 'program')
            ->where('aggregate_id', $programId)
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first([
                'request_fingerprint',
                'result_id',
                'completed_at',
            ]);

        if (! $command instanceof stdClass) {
            return null;
        }

        if (
            ! hash_equals(
                $this->string($command, 'request_fingerprint'),
                $fingerprint,
            )
            || ! hash_equals(
                $this->string($command, 'result_id'),
                $publicationId,
            )
        ) {
            throw new CurriculumIdempotencyConflict;
        }

        $publication = DB::table('curriculum_publications')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('id', $publicationId)
            ->first(['curriculum_version_id', 'manifest_hash']);

        if (! $publication instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        return new PublicationActivationResult(
            publicationId: $publicationId,
            curriculumVersionId: $this->string(
                $publication,
                'curriculum_version_id',
            ),
            manifestHash: $this->string($publication, 'manifest_hash'),
            activatedAt: $this->date($command, 'completed_at'),
            headVersion: $expectedHeadVersion + 1,
            replayed: true,
        );
    }

    private function recordCommand(
        string $organizationId,
        string $programId,
        string $idempotencyKey,
        string $fingerprint,
        string $publicationId,
        string $actorMembershipId,
        string $actorUserId,
        CarbonInterface $completedAt,
    ): void {
        try {
            DB::table('publication_commands')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationId,
                'operation' => self::OPERATION,
                'aggregate_type' => 'program',
                'aggregate_id' => $programId,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'result_type' => 'curriculum_publication',
                'result_id' => $publicationId,
                'actor_type' => 'owner_membership',
                'actor_membership_id' => $actorMembershipId,
                'actor_user_id' => $actorUserId,
                'completed_at' => $completedAt,
                'created_at' => $completedAt,
            ]);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23505') {
                throw new CurriculumIdempotencyConflict;
            }

            throw $exception;
        }
    }

    private function assertInput(
        string $publicationId,
        int $expectedHeadVersion,
        string $idempotencyKey,
        ?string $correlationId,
    ): void {
        if (
            ! Str::isUlid($publicationId)
            || $expectedHeadVersion < 1
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

    private function string(stdClass $row, string $field): string
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

    private function date(stdClass $row, string $field): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string($row, $field))->utc();
    }
}

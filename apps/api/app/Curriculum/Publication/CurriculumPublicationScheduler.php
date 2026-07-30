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
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class CurriculumPublicationScheduler
{
    private const string OPERATION = 'curriculum.schedule';

    public function __construct(
        private OrganizationContextManager $contexts,
        private OrganizationAuthorization $authorization,
        private PublicationSnapshotWriter $snapshots,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function schedule(
        User $actor,
        string $curriculumVersionId,
        CarbonInterface $activationAt,
        int $expectedHeadVersion,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): ScheduledPublicationResult {
        $normalizedActivationAt = $activationAt->toImmutable()->utc();
        $this->assertInput(
            curriculumVersionId: $curriculumVersionId,
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
            $curriculumVersionId,
            $normalizedActivationAt,
            $expectedHeadVersion,
            $idempotencyKey,
            $correlationId,
            $context,
            $actorMembershipId,
        ): ScheduledPublicationResult {
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

            $programId = $this->string($version, 'program_id');
            $program = DB::table('programs')
                ->where('organization_id', $context->organizationId)
                ->where('id', $programId)
                ->lockForUpdate()
                ->first(['id']);

            if (! $program instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            $fingerprint = CanonicalJson::sha256([
                'activation_at' => $this->timestamp($normalizedActivationAt),
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

            if ($replay instanceof ScheduledPublicationResult) {
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
                ? $this->integer($head, 'lock_version')
                : 0;
            $scheduledAt = Date::now()->toImmutable()->utc();

            if (
                $headVersion !== $expectedHeadVersion
                || ! $normalizedActivationAt->greaterThan($scheduledAt)
            ) {
                throw new CurriculumOperationRejected;
            }

            $snapshot = $this->snapshots->create(
                organizationId: $context->organizationId,
                programId: $programId,
                curriculumVersionId: $curriculumVersionId,
                actorMembershipId: $actorMembershipId,
                evaluatedAt: $scheduledAt,
            );
            $eventId = (string) Str::ulid();
            $nextHeadVersion = $headVersion + 1;
            $eventCorrelationId = $correlationId
                ?? $context->correlationId
                ?? (string) Str::uuid();

            DB::table('publication_events')->insert([
                'id' => $eventId,
                'organization_id' => $context->organizationId,
                'program_id' => $programId,
                'curriculum_version_id' => $curriculumVersionId,
                'curriculum_publication_id' => $snapshot->publicationId,
                'event_type' => 'scheduled',
                'previous_curriculum_version_id' => null,
                'previous_publication_id' => null,
                'actor_type' => 'owner_membership',
                'actor_membership_id' => $actorMembershipId,
                'platform_actor_user_id' => null,
                'reason_key' => 'scheduled_publish',
                'correlation_id' => $eventCorrelationId,
                'effective_at' => $normalizedActivationAt,
                'created_at' => $scheduledAt,
            ]);

            if ($head instanceof stdClass) {
                DB::table('publication_heads')
                    ->where('organization_id', $context->organizationId)
                    ->where('program_id', $programId)
                    ->update([
                        'last_event_id' => $eventId,
                        'lock_version' => $nextHeadVersion,
                        'updated_at' => $scheduledAt,
                    ]);
            } else {
                DB::table('publication_heads')->insert([
                    'organization_id' => $context->organizationId,
                    'program_id' => $programId,
                    'active_curriculum_version_id' => null,
                    'active_publication_id' => null,
                    'last_event_id' => $eventId,
                    'is_suspended' => false,
                    'lock_version' => $nextHeadVersion,
                    'updated_at' => $scheduledAt,
                ]);
            }

            $this->recordCommand(
                organizationId: $context->organizationId,
                programId: $programId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                publicationId: $snapshot->publicationId,
                actorMembershipId: $actorMembershipId,
                actorUserId: (string) $actor->getKey(),
                completedAt: $scheduledAt,
            );
            $this->audit->recordTenant(
                'curriculum.publication.scheduled',
                'curriculum_publication',
                $snapshot->publicationId,
                $eventCorrelationId,
            );
            $this->outbox->recordTenant(
                'curriculum.publication.scheduled',
                'curriculum_publication',
                $snapshot->publicationId,
                [
                    'curriculum_version_id' => $curriculumVersionId,
                    'organization_id' => $context->organizationId,
                    'program_id' => $programId,
                    'publication_event_id' => $eventId,
                    'publication_id' => $snapshot->publicationId,
                ],
                $eventCorrelationId,
            );

            return new ScheduledPublicationResult(
                publicationId: $snapshot->publicationId,
                curriculumVersionId: $curriculumVersionId,
                manifestHash: $snapshot->manifestHash,
                activationAt: $normalizedActivationAt,
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
    ): ?ScheduledPublicationResult {
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
                $this->string($command, 'request_fingerprint'),
                $fingerprint,
            )
        ) {
            throw new CurriculumIdempotencyConflict;
        }

        $publicationId = $this->string($command, 'result_id');
        $publication = DB::table('curriculum_publications')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('id', $publicationId)
            ->first(['curriculum_version_id', 'manifest_hash']);
        $event = DB::table('publication_events')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('curriculum_publication_id', $publicationId)
            ->where('event_type', 'scheduled')
            ->first(['effective_at']);

        if (
            ! $publication instanceof stdClass
            || ! $event instanceof stdClass
            || $this->string($publication, 'curriculum_version_id')
                !== $curriculumVersionId
        ) {
            throw new CurriculumOperationRejected;
        }

        return new ScheduledPublicationResult(
            publicationId: $publicationId,
            curriculumVersionId: $curriculumVersionId,
            manifestHash: $this->string($publication, 'manifest_hash'),
            activationAt: $this->date($event, 'effective_at'),
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
        string $curriculumVersionId,
        int $expectedHeadVersion,
        string $idempotencyKey,
        ?string $correlationId,
    ): void {
        if (
            ! Str::isUlid($curriculumVersionId)
            || $expectedHeadVersion < 0
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

    private function timestamp(CarbonInterface $value): string
    {
        return $value->format('Y-m-d\TH:i:s.u\Z');
    }
}

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
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class CurriculumPublicationScheduleCancellationService
{
    private const string OPERATION = 'curriculum.schedule.cancel';

    /** @var list<string> */
    private const array REASONS = [
        'schedule_replaced',
        'release_withdrawn',
        'timing_changed',
    ];

    public function __construct(
        private OrganizationContextManager $contexts,
        private OrganizationAuthorization $authorization,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function cancel(
        User $actor,
        string $publicationId,
        int $expectedHeadVersion,
        string $reasonKey,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): PublicationScheduleCancellationResult {
        $this->assertInput(
            $publicationId,
            $expectedHeadVersion,
            $reasonKey,
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
            || ! hash_equals($context->actorUserId, (string) $actor->getKey())
        ) {
            throw new CurriculumOperationRejected;
        }

        try {
            return DB::transaction(function () use (
                $actor,
                $publicationId,
                $expectedHeadVersion,
                $reasonKey,
                $idempotencyKey,
                $correlationId,
                $context,
                $actorMembershipId,
            ): PublicationScheduleCancellationResult {
                if (! $this->authorization->allows($actor, 'curriculum.publish')) {
                    throw new CurriculumOperationRejected;
                }

                $publication = DB::table('curriculum_publications')
                    ->where('organization_id', $context->organizationId)
                    ->where('id', $publicationId)
                    ->first(['program_id', 'curriculum_version_id']);

                if (! $publication instanceof stdClass) {
                    throw new CurriculumOperationRejected;
                }

                $programId = $this->string($publication, 'program_id');
                $curriculumVersionId = $this->string(
                    $publication,
                    'curriculum_version_id',
                );
                $program = DB::table('programs')
                    ->where('organization_id', $context->organizationId)
                    ->where('id', $programId)
                    ->lockForUpdate()
                    ->first(['id']);
                $lockedPublication = DB::table('curriculum_publications')
                    ->where('organization_id', $context->organizationId)
                    ->where('program_id', $programId)
                    ->where('id', $publicationId)
                    ->lockForUpdate()
                    ->first(['id']);

                if (
                    ! $program instanceof stdClass
                    || ! $lockedPublication instanceof stdClass
                ) {
                    throw new CurriculumOperationRejected;
                }

                $fingerprint = CanonicalJson::sha256([
                    'actor_membership_id' => $actorMembershipId,
                    'actor_user_id' => (string) $actor->getKey(),
                    'expected_head_version' => $expectedHeadVersion,
                    'operation' => self::OPERATION,
                    'program_id' => $programId,
                    'publication_id' => $publicationId,
                    'reason_key' => $reasonKey,
                ]);
                $replay = DB::table('publication_commands')
                    ->where('organization_id', $context->organizationId)
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

                if ($replay instanceof stdClass) {
                    if (
                        ! hash_equals(
                            $this->string($replay, 'request_fingerprint'),
                            $fingerprint,
                        )
                        || ! hash_equals(
                            $this->string($replay, 'result_id'),
                            $publicationId,
                        )
                    ) {
                        throw new CurriculumIdempotencyConflict;
                    }

                    return new PublicationScheduleCancellationResult(
                        publicationId: $publicationId,
                        curriculumVersionId: $curriculumVersionId,
                        cancelledAt: $this->date($replay, 'completed_at'),
                        headVersion: $expectedHeadVersion + 1,
                        replayed: true,
                    );
                }

                $head = DB::table('publication_heads')
                    ->where('organization_id', $context->organizationId)
                    ->where('program_id', $programId)
                    ->lockForUpdate()
                    ->first(['lock_version']);
                $latestLifecycle = DB::table('publication_events')
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
                    ->first(['event_type']);

                if (! $head instanceof stdClass) {
                    throw new CurriculumOperationRejected;
                }

                if ($this->integer($head, 'lock_version') !== $expectedHeadVersion) {
                    throw new CurriculumHeadConflict;
                }

                if (
                    ! $latestLifecycle instanceof stdClass
                    || $this->string($latestLifecycle, 'event_type')
                        !== 'scheduled'
                ) {
                    throw new CurriculumOperationRejected;
                }

                $cancelledAt = Date::now()->toImmutable()->utc();
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
                    'event_type' => 'schedule_cancelled',
                    'previous_curriculum_version_id' => null,
                    'previous_publication_id' => null,
                    'actor_type' => 'owner_membership',
                    'actor_membership_id' => $actorMembershipId,
                    'platform_actor_user_id' => null,
                    'reason_key' => $reasonKey,
                    'correlation_id' => $eventCorrelationId,
                    'effective_at' => $cancelledAt,
                    'created_at' => $cancelledAt,
                ]);
                $updated = DB::table('publication_heads')
                    ->where('organization_id', $context->organizationId)
                    ->where('program_id', $programId)
                    ->where('lock_version', $expectedHeadVersion)
                    ->update([
                        'last_event_id' => $eventId,
                        'lock_version' => $nextHeadVersion,
                        'updated_at' => $cancelledAt,
                    ]);

                if ($updated !== 1) {
                    throw new CurriculumOperationRejected;
                }
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
                    'actor_user_id' => (string) $actor->getKey(),
                    'completed_at' => $cancelledAt,
                    'created_at' => $cancelledAt,
                ]);
                $this->audit->recordTenant(
                    'curriculum.publication.schedule_cancelled',
                    'curriculum_publication',
                    $publicationId,
                    $eventCorrelationId,
                );
                $this->outbox->recordTenant(
                    'curriculum.publication.schedule_cancelled',
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

                return new PublicationScheduleCancellationResult(
                    publicationId: $publicationId,
                    curriculumVersionId: $curriculumVersionId,
                    cancelledAt: $cancelledAt,
                    headVersion: $nextHeadVersion,
                    replayed: false,
                );
            }, 3);
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
        string $reasonKey,
        string $idempotencyKey,
        ?string $correlationId,
    ): void {
        if (
            ! Str::isUlid($publicationId)
            || $expectedHeadVersion < 1
            || ! in_array($reasonKey, self::REASONS, true)
            || preg_match(
                '/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/',
                $idempotencyKey,
            ) !== 1
            || ($correlationId !== null && ! Str::isUuid($correlationId))
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
}

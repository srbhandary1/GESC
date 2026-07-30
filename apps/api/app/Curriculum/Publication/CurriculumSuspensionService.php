<?php

declare(strict_types=1);

namespace App\Curriculum\Publication;

use App\Audit\AuditEventRecorder;
use App\Authentication\Sessions\AuthenticationSessionService;
use App\Curriculum\CanonicalJson;
use App\Curriculum\Exceptions\CurriculumIdempotencyConflict;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Models\AuthenticationSession;
use App\Models\User;
use App\Outbox\OutboxEventRecorder;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContextManager;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class CurriculumSuspensionService
{
    public function __construct(
        private OrganizationContextManager $contexts,
        private OrganizationAuthorization $authorization,
        private AuthenticationSessionService $authenticationSessions,
        private PublicationEvidenceRevalidator $evidence,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function suspend(
        User $actor,
        string $programId,
        int $expectedHeadVersion,
        string $authenticationSessionId,
        string $idempotencyKey,
        string $reasonKey,
        string $remediationReference,
        ?string $correlationId = null,
    ): PublicationSuspensionResult {
        return $this->change(
            actor: $actor,
            programId: $programId,
            suspend: true,
            expectedHeadVersion: $expectedHeadVersion,
            authenticationSessionId: $authenticationSessionId,
            idempotencyKey: $idempotencyKey,
            reasonKey: $reasonKey,
            remediationReference: $remediationReference,
            correlationId: $correlationId,
        );
    }

    public function resume(
        User $actor,
        string $programId,
        int $expectedHeadVersion,
        string $authenticationSessionId,
        string $idempotencyKey,
        string $reasonKey,
        string $remediationReference,
        ?string $correlationId = null,
    ): PublicationSuspensionResult {
        return $this->change(
            actor: $actor,
            programId: $programId,
            suspend: false,
            expectedHeadVersion: $expectedHeadVersion,
            authenticationSessionId: $authenticationSessionId,
            idempotencyKey: $idempotencyKey,
            reasonKey: $reasonKey,
            remediationReference: $remediationReference,
            correlationId: $correlationId,
        );
    }

    private function change(
        User $actor,
        string $programId,
        bool $suspend,
        int $expectedHeadVersion,
        string $authenticationSessionId,
        string $idempotencyKey,
        string $reasonKey,
        string $remediationReference,
        ?string $correlationId,
    ): PublicationSuspensionResult {
        $this->assertInput(
            $programId,
            $expectedHeadVersion,
            $authenticationSessionId,
            $idempotencyKey,
            $reasonKey,
            $remediationReference,
            $correlationId,
        );

        if (! $this->authorization->allows($actor, 'curriculum.suspend')) {
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

        $operation = $suspend
            ? 'curriculum.suspend'
            : 'curriculum.resume';

        return DB::transaction(function () use (
            $actor,
            $programId,
            $suspend,
            $expectedHeadVersion,
            $authenticationSessionId,
            $idempotencyKey,
            $reasonKey,
            $remediationReference,
            $correlationId,
            $context,
            $actorMembershipId,
            $operation,
        ): PublicationSuspensionResult {
            if (! $this->authorization->allows($actor, 'curriculum.suspend')) {
                throw new CurriculumOperationRejected;
            }

            $now = Date::now();
            $fingerprint = CanonicalJson::sha256([
                'actor_membership_id' => $actorMembershipId,
                'actor_user_id' => (string) $actor->getKey(),
                'authentication_session_id' => $authenticationSessionId,
                'expected_head_version' => $expectedHeadVersion,
                'operation' => $operation,
                'program_id' => $programId,
                'reason_key' => $reasonKey,
                'remediation_reference' => $remediationReference,
            ]);
            $replay = DB::table('publication_commands')
                ->where('organization_id', $context->organizationId)
                ->where('operation', $operation)
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

                $publicationId = $this->string($replay, 'result_id');
                $publication = DB::table('curriculum_publications')
                    ->where('organization_id', $context->organizationId)
                    ->where('program_id', $programId)
                    ->where('id', $publicationId)
                    ->first(['curriculum_version_id']);

                if (! $publication instanceof stdClass) {
                    throw new CurriculumOperationRejected;
                }

                return new PublicationSuspensionResult(
                    programId: $programId,
                    curriculumVersionId: $this->string(
                        $publication,
                        'curriculum_version_id',
                    ),
                    publicationId: $publicationId,
                    suspended: $suspend,
                    headVersion: $expectedHeadVersion + 1,
                    replayed: true,
                );
            }

            $this->assertFreshPrivilegedMfa(
                $actor,
                $authenticationSessionId,
                $now,
            );
            $head = DB::table('publication_heads')
                ->where('organization_id', $context->organizationId)
                ->where('program_id', $programId)
                ->lockForUpdate()
                ->first([
                    'active_curriculum_version_id',
                    'active_publication_id',
                    'last_event_id',
                    'is_suspended',
                    'lock_version',
                ]);

            if (! $head instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            $versionId = $this->nullableString(
                $head,
                'active_curriculum_version_id',
            );
            $publicationId = $this->nullableString(
                $head,
                'active_publication_id',
            );

            if (($versionId === null) !== ($publicationId === null)) {
                throw new CurriculumOperationRejected;
            }

            if ($publicationId === null) {
                $lastEventId = $this->string($head, 'last_event_id');
                $lastEvent = DB::table('publication_events')
                    ->where('organization_id', $context->organizationId)
                    ->where('program_id', $programId)
                    ->where('id', $lastEventId)
                    ->first([
                        'curriculum_version_id',
                        'curriculum_publication_id',
                    ]);

                if (! $lastEvent instanceof stdClass) {
                    throw new CurriculumOperationRejected;
                }

                $versionId = $this->string(
                    $lastEvent,
                    'curriculum_version_id',
                );
                $publicationId = $this->string(
                    $lastEvent,
                    'curriculum_publication_id',
                );
            }

            if ($versionId === null) {
                throw new CurriculumOperationRejected;
            }

            if (
                $this->integer($head, 'lock_version')
                    !== $expectedHeadVersion
                || $this->boolean($head, 'is_suspended') === $suspend
            ) {
                throw new CurriculumOperationRejected;
            }

            if (! $suspend) {
                $verified = $this->evidence->verify(
                    $context->organizationId,
                    $publicationId,
                    $now,
                    true,
                );

                if (
                    $verified->programId !== $programId
                    || $verified->curriculumVersionId !== $versionId
                ) {
                    throw new CurriculumOperationRejected;
                }
            }

            $eventId = (string) Str::ulid();
            $eventType = $suspend ? 'suspended' : 'resumed';
            $nextHeadVersion = $expectedHeadVersion + 1;
            $eventCorrelationId = $correlationId
                ?? $context->correlationId
                ?? (string) Str::uuid();

            DB::table('publication_events')->insert([
                'id' => $eventId,
                'organization_id' => $context->organizationId,
                'program_id' => $programId,
                'curriculum_version_id' => $versionId,
                'curriculum_publication_id' => $publicationId,
                'event_type' => $eventType,
                'previous_curriculum_version_id' => null,
                'previous_publication_id' => null,
                'actor_type' => 'owner_membership',
                'actor_membership_id' => $actorMembershipId,
                'platform_actor_user_id' => null,
                'authentication_session_id' => $authenticationSessionId,
                'platform_audit_event_id' => null,
                'reason_key' => $reasonKey,
                'remediation_reference' => $remediationReference,
                'correlation_id' => $eventCorrelationId,
                'effective_at' => $now,
                'created_at' => $now,
            ]);
            DB::table('publication_heads')
                ->where('organization_id', $context->organizationId)
                ->where('program_id', $programId)
                ->update([
                    'last_event_id' => $eventId,
                    'is_suspended' => $suspend,
                    'lock_version' => $nextHeadVersion,
                    'updated_at' => $now,
                ]);
            DB::table('publication_commands')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $context->organizationId,
                'operation' => $operation,
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
            $action = "curriculum.publication.{$eventType}";
            $this->audit->recordTenant(
                $action,
                'curriculum_publication',
                $publicationId,
                $eventCorrelationId,
            );
            $this->outbox->recordTenant(
                $action,
                'curriculum_publication',
                $publicationId,
                [
                    'curriculum_version_id' => $versionId,
                    'organization_id' => $context->organizationId,
                    'program_id' => $programId,
                    'publication_event_id' => $eventId,
                    'publication_id' => $publicationId,
                ],
                $eventCorrelationId,
            );

            return new PublicationSuspensionResult(
                programId: $programId,
                curriculumVersionId: $versionId,
                publicationId: $publicationId,
                suspended: $suspend,
                headVersion: $nextHeadVersion,
                replayed: false,
            );
        }, 3);
    }

    private function assertFreshPrivilegedMfa(
        User $actor,
        string $authenticationSessionId,
        CarbonInterface $now,
    ): void {
        $session = AuthenticationSession::query()
            ->whereKey($authenticationSessionId)
            ->where('user_id', $actor->getKey())
            ->lockForUpdate()
            ->first();

        if (
            ! $session instanceof AuthenticationSession
            || ! $session->is_privileged
            || $session->revoked_at !== null
            || $session->idle_expires_at->lessThanOrEqualTo($now)
            || $session->absolute_expires_at->lessThanOrEqualTo($now)
            || ! $this->authenticationSessions->hasFreshMfa($session)
        ) {
            throw new CurriculumOperationRejected;
        }
    }

    private function assertInput(
        string $programId,
        int $expectedHeadVersion,
        string $authenticationSessionId,
        string $idempotencyKey,
        string $reasonKey,
        string $remediationReference,
        ?string $correlationId,
    ): void {
        if (
            ! Str::isUlid($programId)
            || $expectedHeadVersion < 0
            || ! Str::isUlid($authenticationSessionId)
            || preg_match(
                '/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/',
                $idempotencyKey,
            ) !== 1
            || preg_match('/\A[a-z][a-z0-9_.-]{0,99}\z/', $reasonKey) !== 1
            || preg_match(
                '/\A[A-Za-z0-9][A-Za-z0-9._:\/-]{0,159}\z/',
                $remediationReference,
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

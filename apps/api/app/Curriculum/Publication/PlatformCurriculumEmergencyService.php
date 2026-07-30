<?php

declare(strict_types=1);

namespace App\Curriculum\Publication;

use App\Curriculum\CanonicalJson;
use App\Curriculum\Exceptions\CurriculumIdempotencyConflict;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Outbox\OutboxEventRecorder;
use App\Tenancy\Exceptions\UnauthorizedPlatformOperation;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextSource;
use App\Tenancy\PlatformCapability;
use App\Tenancy\PlatformOperationAuthorization;
use App\Tenancy\PlatformOperationExecutor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

/**
 * Executes only the named, target-bound platform emergency capability.
 *
 * The PlatformOperationExecutor records and verifies the platform audit before
 * it establishes the membership-free owner context. This service deliberately
 * exposes no content-editing or publication operation.
 */
final readonly class PlatformCurriculumEmergencyService
{
    public function __construct(
        private PlatformOperationExecutor $platformOperations,
        private OrganizationContextManager $contexts,
        private PublicationEvidenceRevalidator $evidence,
        private OutboxEventRecorder $outbox,
    ) {}

    public function suspend(
        PlatformOperationAuthorization $authorization,
        int $expectedHeadVersion,
        string $idempotencyKey,
        string $remediationReference,
    ): PublicationSuspensionResult {
        return $this->change(
            authorization: $authorization,
            suspend: true,
            expectedHeadVersion: $expectedHeadVersion,
            idempotencyKey: $idempotencyKey,
            remediationReference: $remediationReference,
        );
    }

    public function resume(
        PlatformOperationAuthorization $authorization,
        int $expectedHeadVersion,
        string $idempotencyKey,
        string $remediationReference,
    ): PublicationSuspensionResult {
        return $this->change(
            authorization: $authorization,
            suspend: false,
            expectedHeadVersion: $expectedHeadVersion,
            idempotencyKey: $idempotencyKey,
            remediationReference: $remediationReference,
        );
    }

    private function change(
        PlatformOperationAuthorization $authorization,
        bool $suspend,
        int $expectedHeadVersion,
        string $idempotencyKey,
        string $remediationReference,
    ): PublicationSuspensionResult {
        $expectedAction = $suspend
            ? 'curriculum.publication.suspended'
            : 'curriculum.publication.resumed';

        if (
            $authorization->capability
                !== PlatformCapability::ContentEmergencySuspend
            || $authorization->action !== $expectedAction
            || $authorization->subjectType !== 'program'
            || $authorization->targetOrganizationId === null
            || $authorization->actorUserId === null
            || $authorization->authenticationSessionId === null
            || $authorization->correlationId === null
            || $expectedHeadVersion < 0
            || preg_match(
                '/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/',
                $idempotencyKey,
            ) !== 1
            || preg_match(
                '/\A[A-Za-z0-9][A-Za-z0-9._:\/-]{0,159}\z/',
                $remediationReference,
            ) !== 1
        ) {
            throw new UnauthorizedPlatformOperation;
        }

        $organizationId = $this->required(
            $authorization->targetOrganizationId,
        );
        $actorUserId = $this->required($authorization->actorUserId);
        $authenticationSessionId = $this->required(
            $authorization->authenticationSessionId,
        );
        $programId = $authorization->subjectId;
        $operation = $suspend
            ? 'curriculum.platform_emergency.suspend'
            : 'curriculum.platform_emergency.resume';
        $fingerprint = CanonicalJson::sha256([
            'actor_user_id' => $actorUserId,
            'authentication_session_id' => $authenticationSessionId,
            'expected_head_version' => $expectedHeadVersion,
            'operation' => $operation,
            'program_id' => $programId,
            'reason_key' => $authorization->reasonKey,
            'remediation_reference' => $remediationReference,
            'target_organization_id' => $organizationId,
        ]);

        return $this->platformOperations->run(
            $authorization,
            fn (): PublicationSuspensionResult => $this->changeInContext(
                authorization: $authorization,
                suspend: $suspend,
                expectedHeadVersion: $expectedHeadVersion,
                idempotencyKey: $idempotencyKey,
                remediationReference: $remediationReference,
                operation: $operation,
                fingerprint: $fingerprint,
            ),
            fn (): ?PublicationSuspensionResult => $this->replay(
                organizationId: $organizationId,
                actorUserId: $actorUserId,
                programId: $programId,
                operation: $operation,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                suspend: $suspend,
                expectedHeadVersion: $expectedHeadVersion,
            ),
        );
    }

    private function changeInContext(
        PlatformOperationAuthorization $authorization,
        bool $suspend,
        int $expectedHeadVersion,
        string $idempotencyKey,
        string $remediationReference,
        string $operation,
        string $fingerprint,
    ): PublicationSuspensionResult {
        $context = $this->contexts->current();
        $organizationId = $this->required(
            $authorization->targetOrganizationId,
        );
        $actorUserId = $this->required($authorization->actorUserId);
        $authenticationSessionId = $this->required(
            $authorization->authenticationSessionId,
        );
        $correlationId = $this->required($authorization->correlationId);
        $programId = $authorization->subjectId;

        if (
            $context->source !== OrganizationContextSource::PlatformOperation
            || $context->membershipId !== null
            || $context->organizationId !== $organizationId
            || $context->actorUserId !== $actorUserId
            || $context->platformAuthorization !== $authorization
        ) {
            throw new UnauthorizedPlatformOperation;
        }

        $head = DB::table('publication_heads')
            ->where('organization_id', $organizationId)
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

        $curriculumVersionId = $this->optionalString(
            $head,
            'active_curriculum_version_id',
        );
        $publicationId = $this->optionalString(
            $head,
            'active_publication_id',
        );

        if (
            $this->integer($head, 'lock_version') !== $expectedHeadVersion
            || $this->boolean($head, 'is_suspended') === $suspend
            || (($curriculumVersionId === null) !== ($publicationId === null))
        ) {
            throw new CurriculumOperationRejected;
        }

        if ($publicationId === null || $curriculumVersionId === null) {
            $lastEventId = $this->string($head, 'last_event_id');
            $lastEvent = DB::table('publication_events')
                ->where('organization_id', $organizationId)
                ->where('program_id', $programId)
                ->where('id', $lastEventId)
                ->whereIn('event_type', [
                    'scheduled',
                    'retired',
                    'suspended',
                    'resumed',
                ])
                ->lockForUpdate()
                ->first([
                    'curriculum_version_id',
                    'curriculum_publication_id',
                ]);

            if (! $lastEvent instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            $curriculumVersionId = $this->string(
                $lastEvent,
                'curriculum_version_id',
            );
            $publicationId = $this->string(
                $lastEvent,
                'curriculum_publication_id',
            );
        }

        if (! $suspend) {
            $this->evidence->lockMutableEvidence();
        }

        $now = Date::now()->toImmutable();

        if (! $suspend) {
            $verified = $this->evidence->verify(
                $organizationId,
                $publicationId,
                $now,
            );

            if (
                $verified->programId !== $programId
                || $verified->curriculumVersionId !== $curriculumVersionId
            ) {
                throw new CurriculumOperationRejected;
            }
        }

        $eventId = (string) Str::ulid();
        $eventType = $suspend ? 'suspended' : 'resumed';
        $nextHeadVersion = $expectedHeadVersion + 1;

        DB::table('publication_events')->insert([
            'id' => $eventId,
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'curriculum_version_id' => $curriculumVersionId,
            'curriculum_publication_id' => $publicationId,
            'event_type' => $eventType,
            'previous_curriculum_version_id' => null,
            'previous_publication_id' => null,
            'actor_type' => 'platform_user',
            'actor_membership_id' => null,
            'platform_actor_user_id' => $actorUserId,
            'authentication_session_id' => $authenticationSessionId,
            'platform_audit_event_id' => $context->platformAuditEventId,
            'reason_key' => $authorization->reasonKey,
            'remediation_reference' => $remediationReference,
            'correlation_id' => $correlationId,
            'effective_at' => $now,
            'created_at' => $now,
        ]);
        $updated = DB::table('publication_heads')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('lock_version', $expectedHeadVersion)
            ->update([
                'last_event_id' => $eventId,
                'is_suspended' => $suspend,
                'lock_version' => $nextHeadVersion,
                'updated_at' => $now,
            ]);

        if ($updated !== 1) {
            throw new CurriculumOperationRejected;
        }

        DB::table('publication_commands')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'operation' => $operation,
            'aggregate_type' => 'program',
            'aggregate_id' => $programId,
            'idempotency_key' => $idempotencyKey,
            'request_fingerprint' => $fingerprint,
            'result_type' => 'curriculum_publication',
            'result_id' => $publicationId,
            'actor_type' => 'platform_user',
            'actor_membership_id' => null,
            'actor_user_id' => $actorUserId,
            'completed_at' => $now,
            'created_at' => $now,
        ]);
        $this->outbox->recordTenant(
            "curriculum.publication.{$eventType}",
            'curriculum_publication',
            $publicationId,
            [
                'curriculum_version_id' => $curriculumVersionId,
                'organization_id' => $organizationId,
                'program_id' => $programId,
                'publication_event_id' => $eventId,
                'publication_id' => $publicationId,
                'user_id' => $actorUserId,
            ],
            $correlationId,
        );

        return new PublicationSuspensionResult(
            programId: $programId,
            curriculumVersionId: $curriculumVersionId,
            publicationId: $publicationId,
            suspended: $suspend,
            headVersion: $nextHeadVersion,
            replayed: false,
        );
    }

    private function replay(
        string $organizationId,
        string $actorUserId,
        string $programId,
        string $operation,
        string $idempotencyKey,
        string $fingerprint,
        bool $suspend,
        int $expectedHeadVersion,
    ): ?PublicationSuspensionResult {
        $replay = DB::table('publication_commands')
            ->where('organization_id', $organizationId)
            ->where('operation', $operation)
            ->where('aggregate_type', 'program')
            ->where('aggregate_id', $programId)
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first([
                'request_fingerprint',
                'result_id',
                'actor_type',
                'actor_user_id',
            ]);

        if (! $replay instanceof stdClass) {
            return null;
        }

        if (
            ! hash_equals(
                $this->string($replay, 'request_fingerprint'),
                $fingerprint,
            )
            || $this->string($replay, 'actor_type') !== 'platform_user'
            || $this->string($replay, 'actor_user_id') !== $actorUserId
        ) {
            throw new CurriculumIdempotencyConflict;
        }

        $publicationId = $this->string($replay, 'result_id');
        $publication = DB::table('curriculum_publications')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('id', $publicationId)
            ->lockForUpdate()
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

    private function required(?string $value): string
    {
        if ($value === null || $value === '') {
            throw new UnauthorizedPlatformOperation;
        }

        return $value;
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

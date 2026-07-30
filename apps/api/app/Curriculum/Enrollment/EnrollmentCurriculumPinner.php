<?php

declare(strict_types=1);

namespace App\Curriculum\Enrollment;

use App\Audit\AuditEventRecorder;
use App\Curriculum\CanonicalJson;
use App\Curriculum\Exceptions\CurriculumIdempotencyConflict;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Publication\PublicationEvidenceRevalidator;
use App\Curriculum\Publication\VerifiedPublication;
use App\Curriculum\Sharing\SharedCurriculumAccessResolver;
use App\Models\User;
use App\Outbox\OutboxEventRecorder;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContextManager;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class EnrollmentCurriculumPinner
{
    public function __construct(
        private OrganizationContextManager $contexts,
        private OrganizationAuthorization $authorization,
        private PublicationEvidenceRevalidator $evidence,
        private SharedCurriculumAccessResolver $sharedAccess,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function activate(
        User $actor,
        string $enrollmentId,
        string $publicationId,
        ?string $shareGrantId,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): EnrollmentPinResult {
        $this->assertInput(
            $enrollmentId,
            $publicationId,
            $shareGrantId,
            $idempotencyKey,
            $correlationId,
        );

        if (
            ! $this->authorization->allows(
                $actor,
                'enrollments.curriculum.assign',
            )
        ) {
            throw new CurriculumOperationRejected;
        }

        $context = $this->contexts->current();
        $actorMembershipId = $this->actorMembershipId($actor);

        return DB::transaction(function () use (
            $actor,
            $enrollmentId,
            $publicationId,
            $shareGrantId,
            $idempotencyKey,
            $correlationId,
            $context,
            $actorMembershipId,
        ): EnrollmentPinResult {
            if (
                ! $this->authorization->allows(
                    $actor,
                    'enrollments.curriculum.assign',
                )
            ) {
                throw new CurriculumOperationRejected;
            }

            $enrollment = DB::table('enrollments')
                ->where('organization_id', $context->organizationId)
                ->where('id', $enrollmentId)
                ->lockForUpdate()
                ->first([
                    'status',
                    'curriculum_owner_organization_id',
                    'curriculum_version_id',
                    'curriculum_publication_id',
                    'curriculum_share_grant_id',
                ]);

            if (! $enrollment instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            $fingerprint = CanonicalJson::sha256([
                'actor_membership_id' => $actorMembershipId,
                'actor_user_id' => (string) $actor->getKey(),
                'enrollment_id' => $enrollmentId,
                'operation' => 'enrollment.curriculum.activate',
                'publication_id' => $publicationId,
                'share_grant_id' => $shareGrantId,
            ]);
            $replay = $this->replay(
                organizationId: $context->organizationId,
                enrollmentId: $enrollmentId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay) {
                return $this->result($enrollmentId, true);
            }

            if (
                $this->nullableString($enrollment, 'status')
                    !== 'pending_curriculum'
                || $this->nullableString(
                    $enrollment,
                    'curriculum_owner_organization_id',
                ) !== null
                || $this->nullableString(
                    $enrollment,
                    'curriculum_version_id',
                ) !== null
                || $this->nullableString(
                    $enrollment,
                    'curriculum_publication_id',
                ) !== null
                || $this->nullableString(
                    $enrollment,
                    'curriculum_share_grant_id',
                ) !== null
            ) {
                throw new CurriculumOperationRejected;
            }

            $resolution = $shareGrantId === null
                ? $this->resolveOwnedPublication(
                    $context->organizationId,
                    $publicationId,
                )
                : $this->resolveSharedPublication(
                    $shareGrantId,
                    $publicationId,
                );
            $publication = $resolution['publication'];
            $evaluatedAt = $resolution['evaluated_at'];
            $effectiveShareGrantId = $publication->ownerOrganizationId
                === $context->organizationId
                ? null
                : $shareGrantId;

            if (
                (
                    $publication->ownerOrganizationId
                        === $context->organizationId
                    && $shareGrantId !== null
                )
                || (
                    $publication->ownerOrganizationId
                        !== $context->organizationId
                    && $effectiveShareGrantId === null
                )
            ) {
                throw new CurriculumOperationRejected;
            }

            $updated = DB::table('enrollments')
                ->where('organization_id', $context->organizationId)
                ->where('id', $enrollmentId)
                ->where('status', 'pending_curriculum')
                ->whereNull('curriculum_publication_id')
                ->update([
                    'curriculum_owner_organization_id' => $publication
                        ->ownerOrganizationId,
                    'curriculum_version_id' => $publication
                        ->curriculumVersionId,
                    'curriculum_publication_id' => $publication->publicationId,
                    'curriculum_share_grant_id' => $effectiveShareGrantId,
                    'status' => 'active',
                    'activated_at' => $evaluatedAt,
                    'updated_at' => $evaluatedAt,
                ]);

            if ($updated !== 1) {
                throw new CurriculumOperationRejected;
            }

            $this->recordCommand(
                actor: $actor,
                actorMembershipId: $actorMembershipId,
                organizationId: $context->organizationId,
                enrollmentId: $enrollmentId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                occurredAt: $evaluatedAt,
            );
            $eventCorrelationId = $correlationId
                ?? $context->correlationId
                ?? (string) Str::uuid();
            $this->audit->recordTenant(
                'enrollment.curriculum.activated',
                'enrollment',
                $enrollmentId,
                $eventCorrelationId,
            );
            $payload = [
                'curriculum_owner_organization_id' => $publication
                    ->ownerOrganizationId,
                'curriculum_version_id' => $publication
                    ->curriculumVersionId,
                'enrollment_id' => $enrollmentId,
                'organization_id' => $context->organizationId,
                'publication_id' => $publication->publicationId,
            ];

            if ($effectiveShareGrantId !== null) {
                $payload['curriculum_share_grant_id']
                    = $effectiveShareGrantId;
            }

            $this->outbox->recordTenant(
                'enrollment.curriculum.activated',
                'enrollment',
                $enrollmentId,
                $payload,
                $eventCorrelationId,
            );

            return $this->result($enrollmentId, false);
        }, 3);
    }

    /**
     * @return array{
     *   publication: VerifiedPublication,
     *   evaluated_at: CarbonInterface
     * }
     */
    private function resolveOwnedPublication(
        string $organizationId,
        string $publicationId,
    ): array {
        $publication = DB::table('curriculum_publications')
            ->where('organization_id', $organizationId)
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
        $active = DB::table('publication_heads')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where(
                'active_curriculum_version_id',
                $versionId,
            )
            ->where('active_publication_id', $publicationId)
            ->where('is_suspended', false)
            ->lockForUpdate()
            ->first(['program_id']) instanceof stdClass;
        $this->evidence->lockMutableEvidence();
        $evaluatedAt = Date::now()->toImmutable();
        $verified = $this->evidence->verify(
            $organizationId,
            $publicationId,
            $evaluatedAt,
        );

        if (
            ! $active
            || $programId !== $verified->programId
            || $versionId !== $verified->curriculumVersionId
        ) {
            throw new CurriculumOperationRejected;
        }

        return [
            'publication' => $verified,
            'evaluated_at' => $evaluatedAt,
        ];
    }

    /**
     * @return array{
     *   publication: VerifiedPublication,
     *   evaluated_at: CarbonInterface
     * }
     */
    private function resolveSharedPublication(
        string $shareGrantId,
        string $publicationId,
    ): array {
        $access = $this->sharedAccess->resolve(
            actor: $this->currentActor(),
            shareGrantId: $shareGrantId,
            use: 'enrollment',
        );

        if ($access->publication->publicationId !== $publicationId) {
            throw new CurriculumOperationRejected;
        }

        return [
            'publication' => $access->publication,
            'evaluated_at' => $access->evaluatedAt,
        ];
    }

    private function currentActor(): User
    {
        $context = $this->contexts->current();
        $actor = User::query()->find($context->actorUserId);

        if (! $actor instanceof User) {
            throw new CurriculumOperationRejected;
        }

        return $actor;
    }

    private function assertInput(
        string $enrollmentId,
        string $publicationId,
        ?string $shareGrantId,
        string $idempotencyKey,
        ?string $correlationId,
    ): void {
        if (
            ! Str::isUlid($enrollmentId)
            || ! Str::isUlid($publicationId)
            || (
                $shareGrantId !== null
                && ! Str::isUlid($shareGrantId)
            )
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
        string $enrollmentId,
        string $idempotencyKey,
        string $fingerprint,
    ): bool {
        $command = DB::table('publication_commands')
            ->where('organization_id', $organizationId)
            ->where('operation', 'enrollment.curriculum.activate')
            ->where('aggregate_type', 'enrollment')
            ->where('aggregate_id', $enrollmentId)
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first(['request_fingerprint', 'result_id']);

        if (! $command instanceof stdClass) {
            return false;
        }

        if (
            ! hash_equals(
                $this->string($command, 'request_fingerprint'),
                $fingerprint,
            )
            || $this->string($command, 'result_id') !== $enrollmentId
        ) {
            throw new CurriculumIdempotencyConflict;
        }

        return true;
    }

    private function recordCommand(
        User $actor,
        string $actorMembershipId,
        string $organizationId,
        string $enrollmentId,
        string $idempotencyKey,
        string $fingerprint,
        CarbonInterface $occurredAt,
    ): void {
        DB::table('publication_commands')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'operation' => 'enrollment.curriculum.activate',
            'aggregate_type' => 'enrollment',
            'aggregate_id' => $enrollmentId,
            'idempotency_key' => $idempotencyKey,
            'request_fingerprint' => $fingerprint,
            'result_type' => 'enrollment',
            'result_id' => $enrollmentId,
            'actor_type' => 'owner_membership',
            'actor_membership_id' => $actorMembershipId,
            'actor_user_id' => $actor->getKey(),
            'completed_at' => $occurredAt,
            'created_at' => $occurredAt,
        ]);
    }

    private function result(
        string $enrollmentId,
        bool $replayed,
    ): EnrollmentPinResult {
        $context = $this->contexts->current();
        $enrollment = DB::table('enrollments')
            ->where('organization_id', $context->organizationId)
            ->where('id', $enrollmentId)
            ->first([
                'organization_id',
                'curriculum_owner_organization_id',
                'curriculum_version_id',
                'curriculum_publication_id',
                'curriculum_share_grant_id',
                'status',
            ]);

        if (! $enrollment instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        return new EnrollmentPinResult(
            enrollmentId: $enrollmentId,
            organizationId: $this->string($enrollment, 'organization_id'),
            curriculumOwnerOrganizationId: $this->string(
                $enrollment,
                'curriculum_owner_organization_id',
            ),
            curriculumVersionId: $this->string(
                $enrollment,
                'curriculum_version_id',
            ),
            publicationId: $this->string(
                $enrollment,
                'curriculum_publication_id',
            ),
            shareGrantId: $this->nullableString(
                $enrollment,
                'curriculum_share_grant_id',
            ),
            status: 'active',
            replayed: $replayed,
        );
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $this->nullableString($row, $field);

        if ($value === null || $value === '') {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    private function nullableString(
        stdClass $row,
        string $field,
    ): ?string {
        $value = $row->{$field} ?? null;

        if ($value !== null && ! is_string($value)) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }
}

<?php

declare(strict_types=1);

namespace App\Curriculum\Governance;

use App\Audit\AuditEventRecorder;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Models\User;
use App\Outbox\OutboxEventRecorder;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class ContentReviewCommands
{
    public function __construct(
        private OrganizationContextManager $contexts,
        private OrganizationAuthorization $authorization,
        private AuthorityEvidenceGate $authority,
        private GovernanceCommandIdempotency $idempotency,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function reviewEnglish(
        User $actor,
        string $sourceRevisionId,
        string $sourceContentHash,
        string $authorityAppointmentId,
        string $decision,
        string $idempotencyKey,
        ?string $rationaleKey = null,
    ): string {
        GovernanceInput::ulid($sourceRevisionId);
        GovernanceInput::sha256($sourceContentHash);
        GovernanceInput::ulid($authorityAppointmentId);
        GovernanceInput::idempotencyKey($idempotencyKey);
        $decision = $this->decision($decision);
        $rationaleKey = $this->rationale($rationaleKey);

        return DB::transaction(function () use (
            $actor,
            $sourceRevisionId,
            $sourceContentHash,
            $authorityAppointmentId,
            $decision,
            $idempotencyKey,
            $rationaleKey,
        ): string {
            $this->assertPermission($actor, 'curriculum.review.en');
            $source = $this->sourceRevision(
                $sourceRevisionId,
                $sourceContentHash,
            );
            $now = Date::now();
            $operation = 'curriculum.content_review.english';
            $fingerprint = $this->idempotency->fingerprint([
                'authority_appointment_id' => $authorityAppointmentId,
                'decision' => $decision,
                'operation' => $operation,
                'rationale_key' => $rationaleKey,
                'source_content_hash' => $sourceContentHash,
                'source_revision_id' => $sourceRevisionId,
            ]);
            $replay = $this->idempotency->replay(
                actor: $actor,
                operation: $operation,
                aggregateType: 'source_revision',
                aggregateId: $sourceRevisionId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay !== null) {
                return $replay;
            }

            $accepted = $this->authority->acceptedForNewEvidence(
                actor: $actor,
                appointmentId: $authorityAppointmentId,
                authorityKind: AuthorityKind::EnglishAcademicReviewer,
                programId: $this->programId(
                    $this->stringProperty(
                        $source,
                        'curriculum_version_id',
                    ),
                ),
                at: $now,
            );

            $authorMembershipId = $source->authored_by_membership_id ?? null;

            if (
                is_string($authorMembershipId)
                && $this->membershipBelongsToActor(
                    $authorMembershipId,
                    $actor,
                )
            ) {
                throw new CurriculumOperationRejected;
            }

            if (
                $decision === 'approved'
                && $this->stringProperty(
                    $source,
                    'localization_requirement',
                ) === 'english_only_pedagogical'
                && $rationaleKey === null
            ) {
                throw new CurriculumOperationRejected;
            }

            $reviewId = $this->insertReview(
                reviewKind: 'academic_en',
                source: $source,
                sourceContentHash: $sourceContentHash,
                localizedVariantId: null,
                localizedContentHash: null,
                appointment: $accepted,
                decision: $decision,
                rationaleKey: $rationaleKey,
                reviewedAt: $now,
            );
            $this->idempotency->record(
                actor: $actor,
                operation: $operation,
                aggregateType: 'source_revision',
                aggregateId: $sourceRevisionId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'content_review',
                resultId: $reviewId,
                completedAt: $now,
            );

            return $reviewId;
        }, 3);
    }

    public function reviewKannada(
        User $actor,
        string $sourceRevisionId,
        string $sourceContentHash,
        string $localizedVariantId,
        string $localizedContentHash,
        string $authorityAppointmentId,
        string $decision,
        string $idempotencyKey,
        ?string $rationaleKey = null,
    ): string {
        GovernanceInput::ulid($sourceRevisionId);
        GovernanceInput::sha256($sourceContentHash);
        GovernanceInput::ulid($localizedVariantId);
        GovernanceInput::sha256($localizedContentHash);
        GovernanceInput::ulid($authorityAppointmentId);
        GovernanceInput::idempotencyKey($idempotencyKey);
        $decision = $this->decision($decision);
        $rationaleKey = $this->rationale($rationaleKey);

        return DB::transaction(function () use (
            $actor,
            $sourceRevisionId,
            $sourceContentHash,
            $localizedVariantId,
            $localizedContentHash,
            $authorityAppointmentId,
            $decision,
            $idempotencyKey,
            $rationaleKey,
        ): string {
            $this->assertPermission($actor, 'curriculum.review.kn');
            $source = $this->sourceRevision(
                $sourceRevisionId,
                $sourceContentHash,
            );
            $variant = DB::table('localized_variants')
                ->where(
                    'organization_id',
                    $this->contexts->current()->organizationId,
                )
                ->where(
                    'curriculum_version_id',
                    $this->stringProperty(
                        $source,
                        'curriculum_version_id',
                    ),
                )
                ->where('id', $localizedVariantId)
                ->where('source_revision_id', $sourceRevisionId)
                ->where('source_content_hash', $sourceContentHash)
                ->where('content_hash', $localizedContentHash)
                ->where('locale', 'kn-IN')
                ->first([
                    'translated_by_membership_id',
                ]);

            if (! $variant instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            $now = Date::now();
            $operation = 'curriculum.content_review.kannada';
            $fingerprint = $this->idempotency->fingerprint([
                'authority_appointment_id' => $authorityAppointmentId,
                'decision' => $decision,
                'localized_content_hash' => $localizedContentHash,
                'localized_variant_id' => $localizedVariantId,
                'operation' => $operation,
                'rationale_key' => $rationaleKey,
                'source_content_hash' => $sourceContentHash,
                'source_revision_id' => $sourceRevisionId,
            ]);
            $replay = $this->idempotency->replay(
                actor: $actor,
                operation: $operation,
                aggregateType: 'source_revision',
                aggregateId: $sourceRevisionId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay !== null) {
                return $replay;
            }

            $accepted = $this->authority->acceptedForNewEvidence(
                actor: $actor,
                appointmentId: $authorityAppointmentId,
                authorityKind: AuthorityKind::KannadaReviewer,
                programId: $this->programId(
                    $this->stringProperty(
                        $source,
                        'curriculum_version_id',
                    ),
                ),
                at: $now,
            );
            $translatorMembershipId =
                $variant->translated_by_membership_id ?? null;

            if (
                is_string($translatorMembershipId)
                && $this->membershipBelongsToActor(
                    $translatorMembershipId,
                    $actor,
                )
            ) {
                throw new CurriculumOperationRejected;
            }

            $reviewId = $this->insertReview(
                reviewKind: 'kannada',
                source: $source,
                sourceContentHash: $sourceContentHash,
                localizedVariantId: $localizedVariantId,
                localizedContentHash: $localizedContentHash,
                appointment: $accepted,
                decision: $decision,
                rationaleKey: $rationaleKey,
                reviewedAt: $now,
            );
            $this->idempotency->record(
                actor: $actor,
                operation: $operation,
                aggregateType: 'source_revision',
                aggregateId: $sourceRevisionId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'content_review',
                resultId: $reviewId,
                completedAt: $now,
            );

            return $reviewId;
        }, 3);
    }

    public function withdraw(
        User $actor,
        string $contentReviewId,
        string $authorityAppointmentId,
        string $rationaleKey,
        string $idempotencyKey,
    ): string {
        GovernanceInput::ulid($contentReviewId);
        GovernanceInput::ulid($authorityAppointmentId);
        $rationaleKey = GovernanceInput::key($rationaleKey);
        GovernanceInput::idempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $actor,
            $contentReviewId,
            $authorityAppointmentId,
            $rationaleKey,
            $idempotencyKey,
        ): string {
            $context = $this->contexts->current();
            $target = DB::table('content_reviews')
                ->where('organization_id', $context->organizationId)
                ->where('id', $contentReviewId)
                ->lockForUpdate()
                ->first([
                    'curriculum_version_id',
                    'review_kind',
                    'source_revision_id',
                    'localized_variant_id',
                    'decision',
                    'source_content_hash',
                    'localized_content_hash',
                ]);

            if (
                ! $target instanceof stdClass
                || $this->stringProperty($target, 'decision') !== 'approved'
            ) {
                throw new CurriculumOperationRejected;
            }

            $reviewKind = $this->stringProperty($target, 'review_kind');
            [$permission, $authorityKind] = match ($reviewKind) {
                'academic_en' => [
                    'curriculum.review.en',
                    AuthorityKind::EnglishAcademicReviewer,
                ],
                'kannada' => [
                    'curriculum.review.kn',
                    AuthorityKind::KannadaReviewer,
                ],
                default => throw new CurriculumOperationRejected,
            };
            $this->assertPermission($actor, $permission);
            $sourceRevisionId = $this->stringProperty(
                $target,
                'source_revision_id',
            );
            $sourceContentHash = $this->stringProperty(
                $target,
                'source_content_hash',
            );
            $localizedVariantId = $this->nullableStringProperty(
                $target,
                'localized_variant_id',
            );
            $localizedContentHash = $this->nullableStringProperty(
                $target,
                'localized_content_hash',
            );
            $source = $this->sourceRevision(
                $sourceRevisionId,
                $sourceContentHash,
            );
            $operation = 'curriculum.content_review.withdraw';
            $fingerprint = $this->idempotency->fingerprint([
                'authority_appointment_id' => $authorityAppointmentId,
                'content_review_id' => $contentReviewId,
                'operation' => $operation,
                'rationale_key' => $rationaleKey,
            ]);
            $replay = $this->idempotency->replay(
                actor: $actor,
                operation: $operation,
                aggregateType: 'content_review',
                aggregateId: $contentReviewId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay !== null) {
                return $replay;
            }

            $latest = DB::table('content_reviews')
                ->where('organization_id', $context->organizationId)
                ->where('review_kind', $reviewKind)
                ->where('source_revision_id', $sourceRevisionId)
                ->where('source_content_hash', $sourceContentHash)
                ->when(
                    $localizedVariantId === null,
                    fn ($query) => $query->whereNull(
                        'localized_variant_id',
                    ),
                    fn ($query) => $query->where(
                        'localized_variant_id',
                        $localizedVariantId,
                    ),
                )
                ->when(
                    $localizedContentHash === null,
                    fn ($query) => $query->whereNull(
                        'localized_content_hash',
                    ),
                    fn ($query) => $query->where(
                        'localized_content_hash',
                        $localizedContentHash,
                    ),
                )
                ->orderByDesc('reviewed_at')
                ->orderByDesc('id')
                ->first(['id']);

            if (
                ! $latest instanceof stdClass
                || ! hash_equals(
                    $this->stringProperty($latest, 'id'),
                    $contentReviewId,
                )
            ) {
                throw new CurriculumOperationRejected;
            }

            $now = Date::now();
            $accepted = $this->authority->acceptedForNewEvidence(
                actor: $actor,
                appointmentId: $authorityAppointmentId,
                authorityKind: $authorityKind,
                programId: $this->programId(
                    $this->stringProperty(
                        $source,
                        'curriculum_version_id',
                    ),
                ),
                at: $now,
            );

            if ($reviewKind === 'academic_en') {
                $authorMembershipId =
                    $source->authored_by_membership_id ?? null;

                if (
                    is_string($authorMembershipId)
                    && $this->membershipBelongsToActor(
                        $authorMembershipId,
                        $actor,
                    )
                ) {
                    throw new CurriculumOperationRejected;
                }
            } else {
                if (
                    $localizedVariantId === null
                    || $localizedContentHash === null
                ) {
                    throw new CurriculumOperationRejected;
                }

                $variant = $this->localizedVariant(
                    source: $source,
                    sourceRevisionId: $sourceRevisionId,
                    sourceContentHash: $sourceContentHash,
                    localizedVariantId: $localizedVariantId,
                    localizedContentHash: $localizedContentHash,
                );
                $translatorMembershipId =
                    $variant->translated_by_membership_id ?? null;

                if (
                    is_string($translatorMembershipId)
                    && $this->membershipBelongsToActor(
                        $translatorMembershipId,
                        $actor,
                    )
                ) {
                    throw new CurriculumOperationRejected;
                }
            }

            $withdrawalId = $this->insertReview(
                reviewKind: $reviewKind,
                source: $source,
                sourceContentHash: $sourceContentHash,
                localizedVariantId: $localizedVariantId,
                localizedContentHash: $localizedContentHash,
                appointment: $accepted,
                decision: 'withdrawn',
                rationaleKey: $rationaleKey,
                reviewedAt: $now,
            );
            $this->idempotency->record(
                actor: $actor,
                operation: $operation,
                aggregateType: 'content_review',
                aggregateId: $contentReviewId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'content_review',
                resultId: $withdrawalId,
                completedAt: $now,
            );

            return $withdrawalId;
        }, 3);
    }

    private function assertPermission(User $actor, string $permission): void
    {
        if (! $this->authorization->allows($actor, $permission)) {
            throw new CurriculumOperationRejected;
        }
    }

    private function sourceRevision(
        string $sourceRevisionId,
        string $sourceContentHash,
    ): stdClass {
        $source = DB::table('content_revisions')
            ->where(
                'content_revisions.organization_id',
                $this->contexts->current()->organizationId,
            )
            ->where('content_revisions.id', $sourceRevisionId)
            ->where('content_revisions.content_hash', $sourceContentHash)
            ->lockForUpdate()
            ->first([
                'content_revisions.id as source_revision_id',
                'content_revisions.curriculum_version_id',
                'content_revisions.authored_by_membership_id',
                'content_revisions.localization_requirement',
            ]);

        if (! $source instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        return $source;
    }

    private function localizedVariant(
        stdClass $source,
        string $sourceRevisionId,
        string $sourceContentHash,
        string $localizedVariantId,
        string $localizedContentHash,
    ): stdClass {
        $variant = DB::table('localized_variants')
            ->where(
                'organization_id',
                $this->contexts->current()->organizationId,
            )
            ->where(
                'curriculum_version_id',
                $this->stringProperty(
                    $source,
                    'curriculum_version_id',
                ),
            )
            ->where('id', $localizedVariantId)
            ->where('source_revision_id', $sourceRevisionId)
            ->where('source_content_hash', $sourceContentHash)
            ->where('content_hash', $localizedContentHash)
            ->where('locale', 'kn-IN')
            ->first(['translated_by_membership_id']);

        if (! $variant instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        return $variant;
    }

    private function programId(string $curriculumVersionId): string
    {
        $programId = DB::table('curriculum_versions')
            ->where(
                'organization_id',
                $this->contexts->current()->organizationId,
            )
            ->where('id', $curriculumVersionId)
            ->value('program_id');

        if (! is_string($programId)) {
            throw new CurriculumOperationRejected;
        }

        return $programId;
    }

    private function membershipBelongsToActor(
        string $membershipId,
        User $actor,
    ): bool {
        return DB::table('memberships')
            ->where(
                'organization_id',
                $this->contexts->current()->organizationId,
            )
            ->where('id', $membershipId)
            ->where('user_id', $actor->getKey())
            ->exists();
    }

    private function insertReview(
        string $reviewKind,
        stdClass $source,
        string $sourceContentHash,
        ?string $localizedVariantId,
        ?string $localizedContentHash,
        AcceptedAuthorityAppointment $appointment,
        string $decision,
        ?string $rationaleKey,
        \DateTimeInterface $reviewedAt,
    ): string {
        $context = $this->contexts->current();
        $reviewId = (string) Str::ulid();

        DB::table('content_reviews')->insert([
            'id' => $reviewId,
            'organization_id' => $context->organizationId,
            'curriculum_version_id' => $this->stringProperty(
                $source,
                'curriculum_version_id',
            ),
            'review_kind' => $reviewKind,
            'source_revision_id' => $this->stringProperty(
                $source,
                'source_revision_id',
            ),
            'localized_variant_id' => $localizedVariantId,
            'authority_appointment_id' => $appointment->appointmentId,
            'authority_appointment_event_id' => $appointment->acceptanceEventId,
            'reviewer_membership_id' => $appointment->appointeeMembershipId,
            'decision' => $decision,
            'source_content_hash' => $sourceContentHash,
            'localized_content_hash' => $localizedContentHash,
            'rationale_key' => $rationaleKey,
            'reviewed_at' => $reviewedAt,
            'created_at' => $reviewedAt,
            'updated_at' => $reviewedAt,
        ]);
        $eventType = $decision === 'withdrawn'
            ? 'curriculum.content_review.withdrawn'
            : 'curriculum.content_review.recorded';
        $this->audit->recordTenant(
            $eventType,
            'content_review',
            $reviewId,
        );
        $this->outbox->recordTenant(
            $eventType,
            'content_review',
            $reviewId,
            [
                'organization_id' => $context->organizationId,
                'membership_id' => $appointment->appointeeMembershipId,
            ],
        );

        return $reviewId;
    }

    private function decision(string $decision): string
    {
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new CurriculumOperationRejected;
        }

        return $decision;
    }

    private function rationale(?string $rationaleKey): ?string
    {
        return $rationaleKey === null
            ? null
            : GovernanceInput::key($rationaleKey);
    }

    private function stringProperty(
        stdClass $record,
        string $property,
    ): string {
        $value = $record->{$property} ?? null;

        if (! is_string($value)) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    private function nullableStringProperty(
        stdClass $record,
        string $property,
    ): ?string {
        $value = $record->{$property} ?? null;

        if ($value !== null && ! is_string($value)) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }
}

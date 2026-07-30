<?php

declare(strict_types=1);

namespace App\Curriculum\Governance;

use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Tenancy\OrganizationContextManager;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class AuthorityEvidenceValidator
{
    public function __construct(
        private OrganizationContextManager $contexts,
    ) {}

    public function assertContentReviewAuthorityRemainsValid(
        string $contentReviewId,
        CarbonInterface $at,
    ): void {
        GovernanceInput::ulid($contentReviewId);
        $context = $this->contexts->current();
        $review = DB::table('content_reviews')
            ->join('curriculum_versions', function ($join): void {
                $join
                    ->on(
                        'curriculum_versions.organization_id',
                        '=',
                        'content_reviews.organization_id',
                    )
                    ->on(
                        'curriculum_versions.id',
                        '=',
                        'content_reviews.curriculum_version_id',
                    );
            })
            ->where('content_reviews.organization_id', $context->organizationId)
            ->where('content_reviews.id', $contentReviewId)
            ->first([
                'content_reviews.review_kind',
                'content_reviews.authority_appointment_id',
                'content_reviews.authority_appointment_event_id',
                'content_reviews.reviewer_membership_id',
                'content_reviews.reviewed_at',
                'curriculum_versions.program_id',
            ]);

        if (! $review instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        $reviewKind = $this->stringProperty($review, 'review_kind');
        $authorityKind = match ($reviewKind) {
            'academic_en' => AuthorityKind::EnglishAcademicReviewer,
            'kannada' => AuthorityKind::KannadaReviewer,
            default => throw new CurriculumOperationRejected,
        };

        $this->assertHistoricalAuthority(
            appointmentId: $this->stringProperty(
                $review,
                'authority_appointment_id',
            ),
            acceptanceEventId: $this->stringProperty(
                $review,
                'authority_appointment_event_id',
            ),
            appointeeMembershipId: $this->stringProperty(
                $review,
                'reviewer_membership_id',
            ),
            authorityKind: $authorityKind,
            programId: $this->stringProperty($review, 'program_id'),
            evidenceAt: $this->dateProperty($review, 'reviewed_at'),
            evaluatedAt: $at,
        );
    }

    public function assertRightsConfirmationAuthorityRemainsValid(
        string $mediaRightsRevisionId,
        CarbonInterface $at,
    ): void {
        GovernanceInput::ulid($mediaRightsRevisionId);
        $rights = DB::table('media_rights_revisions')
            ->where(
                'organization_id',
                $this->contexts->current()->organizationId,
            )
            ->where('id', $mediaRightsRevisionId)
            ->where('evidence_status', 'approved')
            ->first([
                'program_id',
                'rights_appointment_id',
                'rights_appointment_event_id',
                'confirmed_by_membership_id',
                'confirmed_at',
            ]);

        if (! $rights instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        $this->assertHistoricalAuthority(
            appointmentId: $this->stringProperty(
                $rights,
                'rights_appointment_id',
            ),
            acceptanceEventId: $this->stringProperty(
                $rights,
                'rights_appointment_event_id',
            ),
            appointeeMembershipId: $this->stringProperty(
                $rights,
                'confirmed_by_membership_id',
            ),
            authorityKind: AuthorityKind::RightsConfirmer,
            programId: $this->stringProperty($rights, 'program_id'),
            evidenceAt: $this->dateProperty($rights, 'confirmed_at'),
            evaluatedAt: $at,
        );
    }

    private function assertHistoricalAuthority(
        string $appointmentId,
        string $acceptanceEventId,
        string $appointeeMembershipId,
        AuthorityKind $authorityKind,
        ?string $programId,
        CarbonInterface $evidenceAt,
        CarbonInterface $evaluatedAt,
    ): void {
        if ($evaluatedAt->lessThan($evidenceAt)) {
            throw new CurriculumOperationRejected;
        }

        $context = $this->contexts->current();
        $appointment = DB::table('authority_appointments')
            ->where('organization_id', $context->organizationId)
            ->where('id', $appointmentId)
            ->where('authority_kind', $authorityKind->value)
            ->where('appointee_membership_id', $appointeeMembershipId)
            ->where('valid_from', '<=', $evidenceAt)
            ->where(function ($query) use ($evidenceAt): void {
                $query
                    ->whereNull('valid_until')
                    ->orWhere('valid_until', '>', $evidenceAt);
            })
            ->first(['scope_type', 'program_id']);
        $acceptanceExists = DB::table('authority_appointment_events')
            ->where('organization_id', $context->organizationId)
            ->where('authority_appointment_id', $appointmentId)
            ->where('id', $acceptanceEventId)
            ->where('event_type', 'accepted')
            ->where('effective_at', '<=', $evidenceAt)
            ->exists();
        $terminatedBeforeEvidence = DB::table(
            'authority_appointment_events',
        )
            ->where('organization_id', $context->organizationId)
            ->where('authority_appointment_id', $appointmentId)
            ->whereIn(
                'event_type',
                [
                    'surrendered',
                    'revoked',
                    'revoked_for_cause',
                    'expired',
                ],
            )
            ->where('effective_at', '<=', $evidenceAt)
            ->exists();
        $invalidated = DB::table('authority_appointment_events')
            ->where('organization_id', $context->organizationId)
            ->where('authority_appointment_id', $appointmentId)
            ->where('event_type', 'revoked_for_cause')
            ->where('invalidates_prior_approvals', true)
            ->where('effective_at', '<=', $evaluatedAt)
            ->exists();

        if (
            ! $appointment instanceof stdClass
            || ! $acceptanceExists
            || $terminatedBeforeEvidence
            || $invalidated
            || ! $this->scopeAllows(
                $this->stringProperty($appointment, 'scope_type'),
                is_string($appointment->program_id)
                    ? $appointment->program_id
                    : null,
                $programId,
            )
        ) {
            throw new CurriculumOperationRejected;
        }
    }

    private function scopeAllows(
        string $scopeType,
        ?string $appointmentProgramId,
        ?string $requiredProgramId,
    ): bool {
        if ($scopeType === 'organization') {
            return $appointmentProgramId === null;
        }

        return $scopeType === 'program'
            && $appointmentProgramId !== null
            && $requiredProgramId !== null
            && hash_equals($appointmentProgramId, $requiredProgramId);
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

    private function dateProperty(
        stdClass $record,
        string $property,
    ): CarbonImmutable {
        $value = $record->{$property} ?? null;

        if (! is_string($value)) {
            throw new CurriculumOperationRejected;
        }

        return CarbonImmutable::parse($value);
    }
}

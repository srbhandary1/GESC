<?php

declare(strict_types=1);

namespace App\Curriculum\Enrollment;

use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Publication\PublicationEvidenceRevalidator;
use App\Models\Membership;
use App\Models\User;
use App\Tenancy\OrganizationContextManager;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Resolves only the immutable publication already assigned to the current
 * learner. Ordinary share withdrawal and later publication-head changes do
 * not rewrite or disable that pin. Current rights and program suspension are
 * still fail-closed.
 */
final readonly class PinnedEnrollmentAccessResolver
{
    public function __construct(
        private OrganizationContextManager $contexts,
        private PublicationEvidenceRevalidator $evidence,
    ) {}

    public function resolve(
        User $learner,
        string $enrollmentId,
    ): PinnedEnrollmentAccess {
        return DB::transaction(function () use (
            $learner,
            $enrollmentId,
        ): PinnedEnrollmentAccess {
            /*
             * Use one trusted instant for the whole access decision and take
             * it before locking the membership, pin, publication and head.
             */
            $evaluatedAt = Date::now()->toImmutable();

            return $this->resolveLocked(
                learner: $learner,
                enrollmentId: $enrollmentId,
                evaluatedAt: $evaluatedAt,
            );
        }, 3);
    }

    private function resolveLocked(
        User $learner,
        string $enrollmentId,
        CarbonInterface $evaluatedAt,
    ): PinnedEnrollmentAccess {
        $context = $this->contexts->current();

        if (
            $context->membershipId === null
            || $context->actorUserId !== (string) $learner->getKey()
        ) {
            throw new CurriculumOperationRejected;
        }

        $enrollment = DB::table('enrollments')
            ->join('memberships', function ($join): void {
                $join
                    ->on('memberships.organization_id', '=', 'enrollments.organization_id')
                    ->on('memberships.id', '=', 'enrollments.learner_membership_id');
            })
            ->join(
                'organizations',
                'organizations.id',
                '=',
                'enrollments.organization_id',
            )
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->where('enrollments.organization_id', $context->organizationId)
            ->where('enrollments.id', $enrollmentId)
            ->where('enrollments.learner_membership_id', $context->membershipId)
            ->where('enrollments.status', 'active')
            ->where('memberships.user_id', $learner->getKey())
            ->where('memberships.status', Membership::STATUS_ACTIVE)
            ->where('users.status', User::STATUS_ACTIVE)
            ->where('organizations.status', 'active')
            ->lockForUpdate()
            ->first([
                'enrollments.learner_membership_id',
                'enrollments.curriculum_owner_organization_id',
                'enrollments.curriculum_version_id',
                'enrollments.curriculum_publication_id',
            ]);

        if (! $enrollment instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        $ownerOrganizationId = $this->string(
            $enrollment,
            'curriculum_owner_organization_id',
        );
        $curriculumVersionId = $this->string(
            $enrollment,
            'curriculum_version_id',
        );
        $publicationId = $this->string(
            $enrollment,
            'curriculum_publication_id',
        );
        $publicationIdentity = DB::table('curriculum_publications')
            ->where('organization_id', $ownerOrganizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->where('id', $publicationId)
            ->lockForUpdate()
            ->first(['program_id']);

        if (! $publicationIdentity instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        $programId = $this->string($publicationIdentity, 'program_id');
        $ownerIsActive = DB::table('organizations')
            ->where('id', $ownerOrganizationId)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first(['id']) instanceof stdClass;
        $programIsDeliverable = DB::table('publication_heads')
            ->where('organization_id', $ownerOrganizationId)
            ->where('program_id', $programId)
            ->where('is_suspended', false)
            ->lockForUpdate()
            ->first(['program_id']) instanceof stdClass;

        $this->evidence->lockMutableEvidence();
        $publication = $this->evidence->verify(
            $ownerOrganizationId,
            $publicationId,
            $evaluatedAt,
            validateReviewAuthority: false,
        );

        if (
            ! $ownerIsActive
            || ! $programIsDeliverable
            || $publication->programId !== $programId
            || $publication->curriculumVersionId !== $curriculumVersionId
        ) {
            throw new CurriculumOperationRejected;
        }

        return new PinnedEnrollmentAccess(
            enrollmentId: $enrollmentId,
            learnerMembershipId: $this->string(
                $enrollment,
                'learner_membership_id',
            ),
            publication: $publication,
        );
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }
}

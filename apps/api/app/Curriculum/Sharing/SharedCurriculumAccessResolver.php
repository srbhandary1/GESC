<?php

declare(strict_types=1);

namespace App\Curriculum\Sharing;

use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Publication\PublicationEvidenceRevalidator;
use App\Models\User;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContextManager;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class SharedCurriculumAccessResolver
{
    private const array PERMISSIONS = [
        'clone' => 'curriculum.shared.clone',
        'enrollment' => 'enrollments.curriculum.assign',
        'preview' => 'curriculum.shared.read',
    ];

    private const array USE_COLUMNS = [
        'clone' => 'allow_clone',
        'enrollment' => 'allow_enrollment',
        'preview' => 'allow_preview',
    ];

    public function __construct(
        private OrganizationContextManager $contexts,
        private OrganizationAuthorization $authorization,
        private PublicationEvidenceRevalidator $evidence,
    ) {}

    public function resolve(
        User $actor,
        string $shareGrantId,
        string $use,
    ): SharedPublicationAccess {
        $permission = self::PERMISSIONS[$use] ?? null;
        $useColumn = self::USE_COLUMNS[$use] ?? null;

        if (
            ! is_string($permission)
            || ! is_string($useColumn)
        ) {
            throw new CurriculumOperationRejected;
        }

        return DB::transaction(function () use (
            $actor,
            $shareGrantId,
            $use,
            $permission,
            $useColumn,
        ): SharedPublicationAccess {
            /*
             * One trusted instant governs grant validity, prior activation and
             * publication evidence. Capture it before any authoritative row is
             * locked so time cannot move between otherwise-consistent reads.
             */
            $effectiveEvaluation = Date::now()->toImmutable();

            /*
             * OrganizationAuthorization locks the selected authority rows
             * during a transaction. This is the authoritative recheck, not
             * the caller's earlier permission observation.
             */
            if (! $this->authorization->allows($actor, $permission)) {
                throw new CurriculumOperationRejected;
            }

            return $this->resolveLocked(
                shareGrantId: $shareGrantId,
                use: $use,
                useColumn: $useColumn,
                effectiveEvaluation: $effectiveEvaluation,
            );
        }, 3);
    }

    private function resolveLocked(
        string $shareGrantId,
        string $use,
        string $useColumn,
        CarbonInterface $effectiveEvaluation,
    ): SharedPublicationAccess {
        $context = $this->contexts->current();
        $grant = DB::table('curriculum_share_grants')
            ->join('curriculum_share_grant_heads', function ($join): void {
                $join
                    ->on('curriculum_share_grant_heads.organization_id', '=', 'curriculum_share_grants.organization_id')
                    ->on('curriculum_share_grant_heads.curriculum_share_grant_id', '=', 'curriculum_share_grants.id');
            })
            ->where(
                'curriculum_share_grants.recipient_organization_id',
                $context->organizationId,
            )
            ->where('curriculum_share_grants.id', $shareGrantId)
            ->where('curriculum_share_grant_heads.status', 'active')
            ->where("curriculum_share_grants.{$useColumn}", true)
            ->lockForUpdate()
            ->first([
                'curriculum_share_grants.organization_id',
                'curriculum_share_grants.recipient_organization_id',
                'curriculum_share_grants.curriculum_version_id',
                'curriculum_share_grants.curriculum_publication_id',
                'curriculum_share_grants.effective_at',
                'curriculum_share_grants.expires_at',
            ]);

        if (! $grant instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        $ownerOrganizationId = $this->string($grant, 'organization_id');
        $curriculumVersionId = $this->string(
            $grant,
            'curriculum_version_id',
        );
        $publicationId = $this->string(
            $grant,
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
        $headQuery = DB::table('publication_heads')
            ->where('organization_id', $ownerOrganizationId)
            ->where('program_id', $programId)
            ->where('is_suspended', false);

        if ($use === 'enrollment') {
            $headQuery
                ->where('active_curriculum_version_id', $curriculumVersionId)
                ->where('active_publication_id', $publicationId);
        }

        $headIsDeliverable = $headQuery
            ->lockForUpdate()
            ->first(['program_id'])
            instanceof stdClass;
        $wasActivated = DB::table('publication_events')
            ->where('organization_id', $ownerOrganizationId)
            ->where('program_id', $programId)
            ->where('curriculum_publication_id', $publicationId)
            ->whereIn(
                'event_type',
                ['activated', 'rollback_activated'],
            )
            ->where('effective_at', '<=', $effectiveEvaluation)
            ->lockForUpdate()
            ->first(['id']) instanceof stdClass;
        $grantEffectiveAt = $this->date($grant, 'effective_at');
        $grantExpiresAt = $this->optionalDate($grant, 'expires_at');

        if (
            $grantEffectiveAt->greaterThan($effectiveEvaluation)
            || (
                $grantExpiresAt instanceof CarbonInterface
                && $effectiveEvaluation->greaterThanOrEqualTo(
                    $grantExpiresAt,
                )
            )
        ) {
            throw new CurriculumOperationRejected;
        }

        $this->evidence->lockMutableEvidence();
        $publication = $this->evidence->verify(
            $ownerOrganizationId,
            $publicationId,
            $effectiveEvaluation,
            validateReviewAuthority: false,
        );

        if (
            ! $ownerIsActive
            || ! $headIsDeliverable
            || ! $wasActivated
            || $publication->programId !== $programId
            || $publication->curriculumVersionId !== $curriculumVersionId
            || ($use === 'clone' && ! $publication->derivativeUseAllowed)
        ) {
            throw new CurriculumOperationRejected;
        }

        return new SharedPublicationAccess(
            shareGrantId: $shareGrantId,
            recipientOrganizationId: $context->organizationId,
            use: $use,
            publication: $publication,
            evaluatedAt: $effectiveEvaluation,
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

    private function date(stdClass $row, string $field): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string($row, $field));
    }

    private function optionalDate(
        stdClass $row,
        string $field,
    ): ?CarbonImmutable {
        $value = $row->{$field} ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || $value === '') {
            throw new CurriculumOperationRejected;
        }

        return CarbonImmutable::parse($value);
    }
}

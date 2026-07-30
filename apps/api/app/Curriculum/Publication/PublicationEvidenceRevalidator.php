<?php

declare(strict_types=1);

namespace App\Curriculum\Publication;

use App\Curriculum\CanonicalJson;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use stdClass;

final class PublicationEvidenceRevalidator
{
    public function verify(
        string $ownerOrganizationId,
        string $publicationId,
        CarbonInterface $evaluatedAt,
        bool $lockMutableEvidence = false,
        bool $validateReviewAuthority = true,
        bool $rejectSupersededRights = false,
    ): VerifiedPublication {
        if ($lockMutableEvidence) {
            $this->lockMutableEvidence();
        }

        $publication = DB::table('curriculum_publications')
            ->where('organization_id', $ownerOrganizationId)
            ->where('id', $publicationId)
            ->first([
                'program_id',
                'curriculum_version_id',
                'manifest',
                'manifest_hash',
            ]);

        if (! $publication instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        $programId = $this->string($publication, 'program_id');
        $manifest = $this->json($publication, 'manifest');
        $manifestHash = $this->sha256($publication, 'manifest_hash');

        if (! hash_equals($manifestHash, CanonicalJson::sha256($manifest))) {
            throw new CurriculumOperationRejected;
        }

        $graphRevision = $this->array($manifest, 'graph_revision');
        $graphReview = $this->array($graphRevision, 'review');
        $graphRevisionId = $this->arrayString($graphRevision, 'id');
        $graphHash = $this->arraySha256($graphRevision, 'graph_hash');
        $graph = $graphRevision['graph'] ?? null;

        if (! is_array($graph) || array_is_list($graph)) {
            throw new CurriculumOperationRejected;
        }

        $graphComponent = DB::table('publication_graph_components')
            ->where('organization_id', $ownerOrganizationId)
            ->where('curriculum_publication_id', $publicationId)
            ->first();
        $storedGraphRevision = DB::table('curriculum_graph_revisions')
            ->where('organization_id', $ownerOrganizationId)
            ->where(
                'curriculum_version_id',
                $this->string($publication, 'curriculum_version_id'),
            )
            ->where('id', $graphRevisionId)
            ->where('graph_hash', $graphHash)
            ->first(['graph']);

        if (
            ! $graphComponent instanceof stdClass
            || DB::table('publication_graph_components')
                ->where('organization_id', $ownerOrganizationId)
                ->where('curriculum_publication_id', $publicationId)
                ->count() !== 1
            || $this->string(
                $graphComponent,
                'curriculum_graph_revision_id',
            ) !== $graphRevisionId
            || ! hash_equals(
                $this->sha256($graphComponent, 'graph_hash'),
                $graphHash,
            )
            || $this->string(
                $graphComponent,
                'curriculum_graph_review_id',
            ) !== $this->arrayString($graphReview, 'id')
            || $this->string(
                $graphComponent,
                'academic_appointment_id',
            ) !== $this->arrayString($graphReview, 'appointment_id')
            || $this->string(
                $graphComponent,
                'academic_appointment_event_id',
            ) !== $this->arrayString(
                $graphReview,
                'appointment_event_id',
            )
            || $this->string(
                $graphComponent,
                'reviewer_membership_id',
            ) !== $this->arrayString($graphReview, 'reviewer_membership_id')
            || ! $storedGraphRevision instanceof stdClass
            || ! hash_equals(
                CanonicalJson::encode($this->json($storedGraphRevision, 'graph')),
                CanonicalJson::encode($graph),
            )
        ) {
            throw new CurriculumOperationRejected;
        }

        if ($validateReviewAuthority) {
            $this->assertGraphReviewStillValid(
                organizationId: $ownerOrganizationId,
                reviewId: $this->string(
                    $graphComponent,
                    'curriculum_graph_review_id',
                ),
                curriculumVersionId: $this->string(
                    $publication,
                    'curriculum_version_id',
                ),
                graphRevisionId: $graphRevisionId,
                graphHash: $graphHash,
                appointmentId: $this->string(
                    $graphComponent,
                    'academic_appointment_id',
                ),
                evaluatedAt: $evaluatedAt,
            );
        }

        $content = $manifest['content'] ?? null;

        if (! is_array($content) || ! array_is_list($content)) {
            throw new CurriculumOperationRejected;
        }

        $componentCount = DB::table('publication_content_components')
            ->where('organization_id', $ownerOrganizationId)
            ->where('curriculum_publication_id', $publicationId)
            ->count();

        if ($componentCount !== count($content)) {
            throw new CurriculumOperationRejected;
        }

        $sharingAllowed = true;
        $derivativeUseAllowed = true;

        foreach ($content as $entry) {
            if (! is_array($entry)) {
                throw new CurriculumOperationRejected;
            }

            $document = $this->array($entry, 'document');
            $source = $this->array($entry, 'source_revision');
            $academic = $this->array($entry, 'academic_review');
            $documentId = $this->arrayString($document, 'id');
            $sourceRevisionId = $this->arrayString($source, 'id');
            $component = DB::table('publication_content_components')
                ->where('organization_id', $ownerOrganizationId)
                ->where('curriculum_publication_id', $publicationId)
                ->where('content_document_id', $documentId)
                ->first();

            if (
                ! $component instanceof stdClass
                || $this->string($component, 'source_revision_id') !== $sourceRevisionId
                || ! hash_equals(
                    $this->sha256($component, 'source_content_hash'),
                    $this->arraySha256($source, 'content_hash'),
                )
                || $this->string($component, 'academic_review_id')
                    !== $this->arrayString($academic, 'id')
                || $this->string($component, 'academic_appointment_id')
                    !== $this->arrayString($academic, 'appointment_id')
                || $this->string(
                    $component,
                    'academic_appointment_event_id',
                ) !== $this->arrayString($academic, 'appointment_event_id')
            ) {
                throw new CurriculumOperationRejected;
            }

            if ($validateReviewAuthority) {
                $this->assertReviewStillValid(
                    organizationId: $ownerOrganizationId,
                    reviewId: $this->string($component, 'academic_review_id'),
                    reviewKind: 'academic_en',
                    sourceRevisionId: $sourceRevisionId,
                    localizedVariantId: null,
                    appointmentId: $this->string(
                        $component,
                        'academic_appointment_id',
                    ),
                    evaluatedAt: $evaluatedAt,
                );
            }

            $localized = $entry['localized_variant'] ?? null;

            if ($localized === null) {
                if (
                    $this->optionalString(
                        $component,
                        'localized_variant_id',
                    ) !== null
                ) {
                    throw new CurriculumOperationRejected;
                }
            } else {
                if (! is_array($localized)) {
                    throw new CurriculumOperationRejected;
                }

                $kannadaReview = $this->array($localized, 'review');
                $variantId = $this->arrayString($localized, 'id');
                $appointmentId = $this->arrayString(
                    $kannadaReview,
                    'appointment_id',
                );

                if (
                    $this->string($component, 'localized_variant_id') !== $variantId
                    || ! hash_equals(
                        $this->sha256($component, 'localized_content_hash'),
                        $this->arraySha256($localized, 'content_hash'),
                    )
                    || $this->string($component, 'kannada_review_id')
                        !== $this->arrayString($kannadaReview, 'id')
                    || $this->string($component, 'kannada_appointment_id')
                        !== $appointmentId
                    || $this->string(
                        $component,
                        'kannada_appointment_event_id',
                    ) !== $this->arrayString(
                        $kannadaReview,
                        'appointment_event_id',
                    )
                ) {
                    throw new CurriculumOperationRejected;
                }

                if ($validateReviewAuthority) {
                    $this->assertReviewStillValid(
                        organizationId: $ownerOrganizationId,
                        reviewId: $this->string(
                            $component,
                            'kannada_review_id',
                        ),
                        reviewKind: 'kannada',
                        sourceRevisionId: $sourceRevisionId,
                        localizedVariantId: $variantId,
                        appointmentId: $appointmentId,
                        evaluatedAt: $evaluatedAt,
                    );
                }
            }

            $media = $entry['media'] ?? null;

            if (! is_array($media) || ! array_is_list($media)) {
                throw new CurriculumOperationRejected;
            }

            $storedMediaCount = DB::table('publication_media_components')
                ->where('organization_id', $ownerOrganizationId)
                ->where('curriculum_publication_id', $publicationId)
                ->where('content_document_id', $documentId)
                ->count();

            if ($storedMediaCount !== count($media)) {
                throw new CurriculumOperationRejected;
            }

            foreach ($media as $mediaEntry) {
                if (! is_array($mediaEntry)) {
                    throw new CurriculumOperationRejected;
                }

                $assetId = $this->arrayString($mediaEntry, 'asset_id');
                $usageKey = $this->arrayString($mediaEntry, 'usage_key');
                $storedMedia = DB::table('publication_media_components')
                    ->where('organization_id', $ownerOrganizationId)
                    ->where('curriculum_publication_id', $publicationId)
                    ->where('content_document_id', $documentId)
                    ->where('source_revision_id', $sourceRevisionId)
                    ->where('media_asset_id', $assetId)
                    ->where('usage_key', $usageKey)
                    ->first();

                if (
                    ! $storedMedia instanceof stdClass
                    || ! hash_equals(
                        $this->sha256($storedMedia, 'asset_hash'),
                        $this->arraySha256($mediaEntry, 'asset_hash'),
                    )
                    || $this->string(
                        $storedMedia,
                        'media_rights_revision_id',
                    ) !== $this->arrayString(
                        $mediaEntry,
                        'rights_revision_id',
                    )
                    || ! hash_equals(
                        $this->sha256(
                            $storedMedia,
                            'rights_evidence_hash',
                        ),
                        $this->arraySha256($mediaEntry, 'evidence_hash'),
                    )
                ) {
                    throw new CurriculumOperationRejected;
                }

                [$mediaSharing, $mediaDerivative] = $this->assertRightsStillValid(
                    organizationId: $ownerOrganizationId,
                    programId: $programId,
                    mediaAssetId: $assetId,
                    rightsRevisionId: $this->string(
                        $storedMedia,
                        'media_rights_revision_id',
                    ),
                    evaluatedAt: $evaluatedAt,
                    rejectSupersededRights: $rejectSupersededRights,
                );
                $sharingAllowed = $sharingAllowed && $mediaSharing;
                $derivativeUseAllowed = $derivativeUseAllowed && $mediaDerivative;
            }
        }

        return new VerifiedPublication(
            ownerOrganizationId: $ownerOrganizationId,
            programId: $programId,
            curriculumVersionId: $this->string(
                $publication,
                'curriculum_version_id',
            ),
            publicationId: $publicationId,
            manifestHash: $manifestHash,
            sharingAllowed: $sharingAllowed,
            derivativeUseAllowed: $derivativeUseAllowed,
        );
    }

    public function lockMutableEvidence(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'LOCK TABLE authority_appointment_events, curriculum_graph_reviews, content_reviews, media_rights_revisions IN SHARE MODE',
        );
    }

    private function assertGraphReviewStillValid(
        string $organizationId,
        string $reviewId,
        string $curriculumVersionId,
        string $graphRevisionId,
        string $graphHash,
        string $appointmentId,
        CarbonInterface $evaluatedAt,
    ): void {
        $review = DB::table('curriculum_graph_reviews')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->where('id', $reviewId)
            ->where('curriculum_graph_revision_id', $graphRevisionId)
            ->where('graph_hash', $graphHash)
            ->where('decision', 'approved')
            ->first(['reviewed_at']);

        if (
            ! $review instanceof stdClass
            || $this->date($review, 'reviewed_at')->greaterThan($evaluatedAt)
        ) {
            throw new CurriculumOperationRejected;
        }

        $reviewedAt = $this->date($review, 'reviewed_at');
        $invalidated = DB::table('curriculum_graph_reviews')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->where('curriculum_graph_revision_id', $graphRevisionId)
            ->where('graph_hash', $graphHash)
            ->whereIn('decision', ['rejected', 'withdrawn'])
            ->where('reviewed_at', '<=', $evaluatedAt)
            ->where(function ($query) use ($reviewedAt, $reviewId): void {
                $query
                    ->where('reviewed_at', '>', $reviewedAt)
                    ->orWhere(function ($query) use (
                        $reviewedAt,
                        $reviewId,
                    ): void {
                        $query
                            ->where('reviewed_at', '=', $reviewedAt)
                            ->where('id', '>', $reviewId);
                    });
            })
            ->exists();
        $authorityInvalidated = DB::table('authority_appointment_events')
            ->where('organization_id', $organizationId)
            ->where('authority_appointment_id', $appointmentId)
            ->where('event_type', 'revoked_for_cause')
            ->where('invalidates_prior_approvals', true)
            ->where('effective_at', '<=', $evaluatedAt)
            ->exists();

        if ($invalidated || $authorityInvalidated) {
            throw new CurriculumOperationRejected;
        }
    }

    private function assertReviewStillValid(
        string $organizationId,
        string $reviewId,
        string $reviewKind,
        string $sourceRevisionId,
        ?string $localizedVariantId,
        string $appointmentId,
        CarbonInterface $evaluatedAt,
    ): void {
        $review = DB::table('content_reviews')
            ->where('organization_id', $organizationId)
            ->where('id', $reviewId)
            ->where('review_kind', $reviewKind)
            ->where('source_revision_id', $sourceRevisionId)
            ->where('decision', 'approved')
            ->first(['reviewed_at']);

        if (
            ! $review instanceof stdClass
            || $this->date($review, 'reviewed_at')->greaterThan($evaluatedAt)
        ) {
            throw new CurriculumOperationRejected;
        }

        $reviewedAt = $this->date($review, 'reviewed_at');
        $laterDecision = DB::table('content_reviews')
            ->where('organization_id', $organizationId)
            ->where('review_kind', $reviewKind)
            ->where('source_revision_id', $sourceRevisionId)
            ->whereIn('decision', ['rejected', 'withdrawn'])
            ->where('reviewed_at', '<=', $evaluatedAt)
            ->where(function ($query) use ($reviewedAt, $reviewId): void {
                $query
                    ->where('reviewed_at', '>', $reviewedAt)
                    ->orWhere(function ($query) use (
                        $reviewedAt,
                        $reviewId,
                    ): void {
                        $query
                            ->where('reviewed_at', '=', $reviewedAt)
                            ->where('id', '>', $reviewId);
                    });
            });

        if ($localizedVariantId === null) {
            $laterDecision->whereNull('localized_variant_id');
        } else {
            $laterDecision->where('localized_variant_id', $localizedVariantId);
        }

        if (
            $laterDecision->exists()
            || DB::table('authority_appointment_events')
                ->where('organization_id', $organizationId)
                ->where('authority_appointment_id', $appointmentId)
                ->where('event_type', 'revoked_for_cause')
                ->where('invalidates_prior_approvals', true)
                ->where('effective_at', '<=', $evaluatedAt)
                ->exists()
        ) {
            throw new CurriculumOperationRejected;
        }
    }

    /**
     * @return array{bool, bool}
     */
    private function assertRightsStillValid(
        string $organizationId,
        string $programId,
        string $mediaAssetId,
        string $rightsRevisionId,
        CarbonInterface $evaluatedAt,
        bool $rejectSupersededRights,
    ): array {
        $rights = DB::table('media_rights_revisions')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('media_asset_id', $mediaAssetId)
            ->where('id', $rightsRevisionId)
            ->where('evidence_status', 'approved')
            ->first([
                'revision_number',
                'effective_at',
                'expires_at',
                'learner_delivery_allowed',
                'sharing_allowed',
                'derivative_use_allowed',
                'rights_appointment_id',
            ]);

        if (! $rights instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        $effectiveAt = $this->date($rights, 'effective_at');
        $expiresAt = $this->optionalDate($rights, 'expires_at');

        if (
            $effectiveAt->greaterThan($evaluatedAt)
            || ! $this->boolean($rights, 'learner_delivery_allowed')
            || (
                $expiresAt instanceof CarbonInterface
                && $evaluatedAt->greaterThanOrEqualTo($expiresAt)
            )
            || DB::table('media_rights_revisions')
                ->where('organization_id', $organizationId)
                ->where('program_id', $programId)
                ->where('media_asset_id', $mediaAssetId)
                ->where(
                    'revision_number',
                    '>',
                    $this->integer($rights, 'revision_number'),
                )
                ->where('evidence_status', 'withdrawn')
                ->where('effective_at', '<=', $evaluatedAt)
                ->exists()
            || (
                $rejectSupersededRights
                && DB::table('media_rights_revisions')
                    ->where('organization_id', $organizationId)
                    ->where('program_id', $programId)
                    ->where('media_asset_id', $mediaAssetId)
                    ->where(
                        'revision_number',
                        '>',
                        $this->integer($rights, 'revision_number'),
                    )
                    ->where('effective_at', '<=', $evaluatedAt)
                    ->exists()
            )
            || DB::table('authority_appointment_events')
                ->where('organization_id', $organizationId)
                ->where(
                    'authority_appointment_id',
                    $this->string($rights, 'rights_appointment_id'),
                )
                ->where('event_type', 'revoked_for_cause')
                ->where('invalidates_prior_approvals', true)
                ->where('effective_at', '<=', $evaluatedAt)
                ->exists()
        ) {
            throw new CurriculumOperationRejected;
        }

        return [
            $this->boolean($rights, 'sharing_allowed'),
            $this->boolean($rights, 'derivative_use_allowed'),
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function array(array $value, string $field): array
    {
        $nested = $value[$field] ?? null;

        if (! is_array($nested) || array_is_list($nested)) {
            throw new CurriculumOperationRejected;
        }

        return $nested;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function arrayString(array $value, string $field): string
    {
        $item = $value[$field] ?? null;

        if (! is_string($item) || $item === '') {
            throw new CurriculumOperationRejected;
        }

        return $item;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function arraySha256(array $value, string $field): string
    {
        $item = $this->arrayString($value, $field);

        if (preg_match('/\A[0-9a-f]{64}\z/', $item) !== 1) {
            throw new CurriculumOperationRejected;
        }

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(stdClass $row, string $field): array
    {
        $value = $row->{$field} ?? null;

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            throw new CurriculumOperationRejected;
        }

        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new CurriculumOperationRejected;
        }

        return $decoded;
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

    private function sha256(stdClass $row, string $field): string
    {
        $value = $this->string($row, $field);

        if (preg_match('/\A[0-9a-f]{64}\z/', $value) !== 1) {
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

    private function date(stdClass $row, string $field): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string($row, $field));
    }

    private function optionalDate(
        stdClass $row,
        string $field,
    ): ?CarbonImmutable {
        $value = $this->optionalString($row, $field);

        return $value === null ? null : CarbonImmutable::parse($value);
    }
}

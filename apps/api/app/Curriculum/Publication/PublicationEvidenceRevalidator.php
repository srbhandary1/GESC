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
    public function __construct(
        private readonly ManagedMediaGovernanceEvidence $managedMedia,
    ) {}

    public function verify(
        string $ownerOrganizationId,
        string $publicationId,
        CarbonInterface $evaluatedAt,
        bool $lockMutableEvidence = false,
        bool $validateReviewAuthority = true,
        bool $rejectSupersededRights = false,
        bool $requireCurrentManagedMedia = false,
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
        $managedPublication = ($manifest['schema'] ?? null)
            === 'global-english.curriculum-publication.v2';

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

        $graphComponents = DB::table('publication_graph_components')
            ->where('organization_id', $ownerOrganizationId)
            ->where('curriculum_publication_id', $publicationId)
            ->get();
        $graphComponent = $graphComponents->count() === 1
            ? $graphComponents->first()
            : null;
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

        $storedContentComponents = DB::table('publication_content_components')
            ->where('organization_id', $ownerOrganizationId)
            ->where('curriculum_publication_id', $publicationId)
            ->get();

        if ($storedContentComponents->count() !== count($content)) {
            throw new CurriculumOperationRejected;
        }

        $contentComponents = [];

        foreach ($storedContentComponents as $storedContentComponent) {
            $documentId = $this->string(
                $storedContentComponent,
                'content_document_id',
            );

            if (array_key_exists($documentId, $contentComponents)) {
                throw new CurriculumOperationRejected;
            }

            $contentComponents[$documentId] = $storedContentComponent;
        }

        $storedMediaComponents = DB::table('publication_media_components')
            ->where('organization_id', $ownerOrganizationId)
            ->where('curriculum_publication_id', $publicationId)
            ->get();
        $mediaComponents = [];

        foreach ($storedMediaComponents as $storedMediaComponent) {
            $key = $this->mediaComponentKey(
                $this->string($storedMediaComponent, 'content_document_id'),
                $this->string($storedMediaComponent, 'source_revision_id'),
                $this->string($storedMediaComponent, 'media_asset_id'),
                $this->string($storedMediaComponent, 'usage_key'),
            );

            if (array_key_exists($key, $mediaComponents)) {
                throw new CurriculumOperationRejected;
            }

            $mediaComponents[$key] = $storedMediaComponent;
        }

        $storedGovernanceComponents = DB::table(
            'publication_media_governance_components',
        )
            ->where('organization_id', $ownerOrganizationId)
            ->where('curriculum_publication_id', $publicationId)
            ->get();
        $governanceComponents = [];

        foreach ($storedGovernanceComponents as $storedGovernance) {
            $key = $this->mediaGovernanceComponentKey(
                $this->string($storedGovernance, 'source_revision_id'),
                $this->string($storedGovernance, 'media_asset_id'),
                $this->string($storedGovernance, 'usage_key'),
            );

            if (array_key_exists($key, $governanceComponents)) {
                throw new CurriculumOperationRejected;
            }

            $governanceComponents[$key] = $storedGovernance;
        }

        $usedMediaComponents = [];
        $usedGovernanceComponents = [];
        $rightsRequirements = [];

        foreach ($content as $entry) {
            if (! is_array($entry)) {
                throw new CurriculumOperationRejected;
            }

            $document = $this->array($entry, 'document');
            $source = $this->array($entry, 'source_revision');
            $academic = $this->array($entry, 'academic_review');
            $documentId = $this->arrayString($document, 'id');
            $sourceRevisionId = $this->arrayString($source, 'id');
            $component = $contentComponents[$documentId] ?? null;

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

            foreach ($media as $mediaEntry) {
                if (! is_array($mediaEntry)) {
                    throw new CurriculumOperationRejected;
                }

                $assetId = $this->arrayString($mediaEntry, 'asset_id');
                $usageKey = $this->arrayString($mediaEntry, 'usage_key');
                $key = $this->mediaComponentKey(
                    $documentId,
                    $sourceRevisionId,
                    $assetId,
                    $usageKey,
                );
                $governanceKey = $this->mediaGovernanceComponentKey(
                    $sourceRevisionId,
                    $assetId,
                    $usageKey,
                );
                $storedMedia = $mediaComponents[$key] ?? null;

                if (
                    ! $storedMedia instanceof stdClass
                    || array_key_exists($key, $usedMediaComponents)
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

                $governance = $mediaEntry['governance'] ?? null;
                $storedGovernance = $governanceComponents[$governanceKey]
                    ?? null;

                if (! $managedPublication) {
                    if ($governance !== null || $storedGovernance !== null) {
                        throw new CurriculumOperationRejected;
                    }
                } else {
                    if (
                        ! is_array($governance)
                        || array_is_list($governance)
                        || ! $storedGovernance instanceof stdClass
                        || array_key_exists(
                            $governanceKey,
                            $usedGovernanceComponents,
                        )
                    ) {
                        throw new CurriculumOperationRejected;
                    }

                    $selectedScan = $this->array(
                        $governance,
                        'selected_scan',
                    );
                    $metadata = $this->array($governance, 'metadata');
                    $englishReview = $this->array(
                        $metadata,
                        'english_review',
                    );
                    $kannadaVariant = $this->array(
                        $metadata,
                        'kannada_variant',
                    );
                    $kannadaReview = $this->array(
                        $kannadaVariant,
                        'review',
                    );
                    $governanceRights = $this->array(
                        $governance,
                        'rights',
                    );
                    $rightsRevisionId = $this->arrayString(
                        $mediaEntry,
                        'rights_revision_id',
                    );
                    $rightsEvidenceHash = $this->arraySha256(
                        $mediaEntry,
                        'evidence_hash',
                    );

                    if (
                        $this->string($storedGovernance, 'selected_scan_id')
                            !== $this->arrayString($selectedScan, 'id')
                        || ! hash_equals(
                            $this->sha256($storedGovernance, 'object_hash'),
                            $this->arraySha256(
                                $selectedScan,
                                'object_hash',
                            ),
                        )
                        || $this->string(
                            $storedGovernance,
                            'inspector_policy_version',
                        ) !== $this->arrayString(
                            $selectedScan,
                            'inspector_policy_version',
                        )
                        || $this->string(
                            $storedGovernance,
                            'scanner_policy_version',
                        ) !== $this->arrayString(
                            $selectedScan,
                            'scanner_policy_version',
                        )
                        || $this->string(
                            $storedGovernance,
                            'metadata_revision_id',
                        ) !== $this->arrayString($metadata, 'revision_id')
                        || ! hash_equals(
                            $this->sha256(
                                $storedGovernance,
                                'metadata_content_hash',
                            ),
                            $this->arraySha256($metadata, 'content_hash'),
                        )
                        || $this->string(
                            $storedGovernance,
                            'english_review_id',
                        ) !== $this->arrayString($englishReview, 'id')
                        || $this->string(
                            $storedGovernance,
                            'metadata_variant_id',
                        ) !== $this->arrayString($kannadaVariant, 'id')
                        || ! hash_equals(
                            $this->sha256(
                                $storedGovernance,
                                'variant_content_hash',
                            ),
                            $this->arraySha256(
                                $kannadaVariant,
                                'content_hash',
                            ),
                        )
                        || $this->string(
                            $storedGovernance,
                            'kannada_review_id',
                        ) !== $this->arrayString($kannadaReview, 'id')
                        || $this->string(
                            $storedGovernance,
                            'media_rights_revision_id',
                        ) !== $rightsRevisionId
                        || $this->string(
                            $storedGovernance,
                            'rights_evidence_status',
                        ) !== 'approved'
                        || $this->arrayString(
                            $governanceRights,
                            'revision_id',
                        ) !== $rightsRevisionId
                        || ! hash_equals(
                            $this->sha256(
                                $storedGovernance,
                                'rights_evidence_hash',
                            ),
                            $rightsEvidenceHash,
                        )
                        || ! hash_equals(
                            $this->arraySha256(
                                $governanceRights,
                                'evidence_hash',
                            ),
                            $rightsEvidenceHash,
                        )
                    ) {
                        throw new CurriculumOperationRejected;
                    }

                    $this->managedMedia->assertSnapshot(
                        organizationId: $ownerOrganizationId,
                        programId: $programId,
                        curriculumVersionId: $this->string(
                            $publication,
                            'curriculum_version_id',
                        ),
                        sourceRevisionId: $sourceRevisionId,
                        mediaAssetId: $assetId,
                        assetHash: $this->arraySha256(
                            $mediaEntry,
                            'asset_hash',
                        ),
                        usageKey: $usageKey,
                        rightsRevisionId: $rightsRevisionId,
                        rightsEvidenceHash: $rightsEvidenceHash,
                        expected: $governance,
                        evaluatedAt: $evaluatedAt,
                        requireCurrentSelection: $requireCurrentManagedMedia,
                        validateCurrentAuthority: $validateReviewAuthority,
                    );
                    $usedGovernanceComponents[$governanceKey] = true;
                }

                $usedMediaComponents[$key] = true;
                $rightsRequirements[] = [
                    'media_asset_id' => $assetId,
                    'rights_revision_id' => $this->string(
                        $storedMedia,
                        'media_rights_revision_id',
                    ),
                ];
            }
        }

        if (count($usedMediaComponents) !== $storedMediaComponents->count()) {
            throw new CurriculumOperationRejected;
        }

        if (
            count($usedGovernanceComponents)
                !== $storedGovernanceComponents->count()
            || ($managedPublication
                && count($usedGovernanceComponents)
                    !== count($usedMediaComponents))
        ) {
            throw new CurriculumOperationRejected;
        }

        [$sharingAllowed, $derivativeUseAllowed]
            = $this->assertRightsSetStillValid(
                organizationId: $ownerOrganizationId,
                programId: $programId,
                requirements: $rightsRequirements,
                evaluatedAt: $evaluatedAt,
                rejectSupersededRights: $rejectSupersededRights,
            );

        return new VerifiedPublication(
            ownerOrganizationId: $ownerOrganizationId,
            programId: $programId,
            curriculumVersionId: $this->string(
                $publication,
                'curriculum_version_id',
            ),
            publicationId: $publicationId,
            manifestHash: $manifestHash,
            manifest: $manifest,
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
            'LOCK TABLE authority_appointment_events, curriculum_graph_reviews, content_reviews, media_rights_revisions, media_security_scans, media_asset_lifecycles, media_asset_metadata_revisions, media_asset_metadata_heads, media_asset_metadata_variants, media_asset_metadata_variant_heads, media_asset_metadata_reviews, media_attachment_events, publication_media_governance_components IN SHARE MODE',
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
     * @param  list<array{media_asset_id: string, rights_revision_id: string}>  $requirements
     * @return array{bool, bool}
     */
    private function assertRightsSetStillValid(
        string $organizationId,
        string $programId,
        array $requirements,
        CarbonInterface $evaluatedAt,
        bool $rejectSupersededRights,
    ): array {
        if ($requirements === []) {
            return [true, true];
        }

        $requiredAssetsByRevision = [];

        foreach ($requirements as $requirement) {
            $mediaAssetId = $requirement['media_asset_id'];
            $rightsRevisionId = $requirement['rights_revision_id'];
            $existingAssetId = $requiredAssetsByRevision[$rightsRevisionId]
                ?? null;

            if (
                $existingAssetId !== null
                && $existingAssetId !== $mediaAssetId
            ) {
                throw new CurriculumOperationRejected;
            }

            $requiredAssetsByRevision[$rightsRevisionId] = $mediaAssetId;
        }

        $rightsRows = DB::table('media_rights_revisions')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->whereIn('id', array_keys($requiredAssetsByRevision))
            ->where('evidence_status', 'approved')
            ->get([
                'id',
                'media_asset_id',
                'revision_number',
                'effective_at',
                'expires_at',
                'learner_delivery_allowed',
                'sharing_allowed',
                'derivative_use_allowed',
                'rights_appointment_id',
            ]);

        if ($rightsRows->count() !== count($requiredAssetsByRevision)) {
            throw new CurriculumOperationRejected;
        }

        $seenRights = [];
        $selectedRightsByAsset = [];
        $appointmentIds = [];
        $sharingAllowed = true;
        $derivativeUseAllowed = true;

        foreach ($rightsRows as $rights) {
            $rightsRevisionId = $this->string($rights, 'id');
            $mediaAssetId = $this->string($rights, 'media_asset_id');
            $effectiveAt = $this->date($rights, 'effective_at');
            $expiresAt = $this->optionalDate($rights, 'expires_at');

            if (
                array_key_exists($rightsRevisionId, $seenRights)
                || ($requiredAssetsByRevision[$rightsRevisionId] ?? null)
                    !== $mediaAssetId
                || $effectiveAt->greaterThan($evaluatedAt)
                || ! $this->boolean($rights, 'learner_delivery_allowed')
                || (
                    $expiresAt instanceof CarbonInterface
                    && $evaluatedAt->greaterThanOrEqualTo($expiresAt)
                )
            ) {
                throw new CurriculumOperationRejected;
            }

            $seenRights[$rightsRevisionId] = true;
            $selectedRightsByAsset[$mediaAssetId][] = $this->integer(
                $rights,
                'revision_number',
            );
            $appointmentIds[$this->string(
                $rights,
                'rights_appointment_id',
            )] = true;
            $sharingAllowed = $sharingAllowed
                && $this->boolean($rights, 'sharing_allowed');
            $derivativeUseAllowed = $derivativeUseAllowed
                && $this->boolean($rights, 'derivative_use_allowed');
        }

        if (count($seenRights) !== count($requiredAssetsByRevision)) {
            throw new CurriculumOperationRejected;
        }

        $laterRights = DB::table('media_rights_revisions')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->whereIn('media_asset_id', array_keys($selectedRightsByAsset))
            ->where('effective_at', '<=', $evaluatedAt)
            ->when(
                ! $rejectSupersededRights,
                static fn ($query) => $query->where(
                    'evidence_status',
                    'withdrawn',
                ),
            )
            ->get([
                'media_asset_id',
                'revision_number',
            ]);

        foreach ($laterRights as $laterRight) {
            $mediaAssetId = $this->string($laterRight, 'media_asset_id');
            $revisionNumber = $this->integer(
                $laterRight,
                'revision_number',
            );

            foreach ($selectedRightsByAsset[$mediaAssetId] ?? [] as $selected) {
                if ($revisionNumber > $selected) {
                    throw new CurriculumOperationRejected;
                }
            }
        }

        if (
            DB::table('authority_appointment_events')
                ->where('organization_id', $organizationId)
                ->whereIn(
                    'authority_appointment_id',
                    array_keys($appointmentIds),
                )
                ->where('event_type', 'revoked_for_cause')
                ->where('invalidates_prior_approvals', true)
                ->where('effective_at', '<=', $evaluatedAt)
                ->exists()
        ) {
            throw new CurriculumOperationRejected;
        }

        return [$sharingAllowed, $derivativeUseAllowed];
    }

    private function mediaComponentKey(
        string $documentId,
        string $sourceRevisionId,
        string $assetId,
        string $usageKey,
    ): string {
        return implode("\0", [
            $documentId,
            $sourceRevisionId,
            $assetId,
            $usageKey,
        ]);
    }

    private function mediaGovernanceComponentKey(
        string $sourceRevisionId,
        string $assetId,
        string $usageKey,
    ): string {
        return implode("\0", [
            $sourceRevisionId,
            $assetId,
            $usageKey,
        ]);
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

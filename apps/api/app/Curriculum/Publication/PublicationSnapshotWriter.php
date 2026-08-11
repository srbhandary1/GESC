<?php

declare(strict_types=1);

namespace App\Curriculum\Publication;

use App\Curriculum\CanonicalJson;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class PublicationSnapshotWriter
{
    public function __construct(
        private PublicationManifestBuilder $manifests,
    ) {}

    public function create(
        string $organizationId,
        string $programId,
        string $curriculumVersionId,
        string $actorMembershipId,
        CarbonInterface $evaluatedAt,
    ): PublicationSnapshot {
        $this->lockManifestInputs();
        $manifest = $this->manifests->build(
            $curriculumVersionId,
            $evaluatedAt,
        );
        $manifestHash = CanonicalJson::sha256($manifest);

        if (
            DB::table('curriculum_publications')
                ->where('organization_id', $organizationId)
                ->where('program_id', $programId)
                ->where('manifest_hash', $manifestHash)
                ->exists()
        ) {
            throw new CurriculumOperationRejected;
        }

        $publicationId = (string) Str::ulid();
        $publicationNumber = (int) DB::table('curriculum_publications')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->max('publication_number') + 1;

        DB::table('curriculum_publications')->insert([
            'id' => $publicationId,
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'curriculum_version_id' => $curriculumVersionId,
            'publication_number' => $publicationNumber,
            'manifest_schema_version' => ($manifest['schema'] ?? null)
                === 'global-english.curriculum-publication.v2'
                ? 2
                : 1,
            'manifest' => CanonicalJson::encode($manifest),
            'manifest_hash' => $manifestHash,
            'created_by_membership_id' => $actorMembershipId,
            'created_at' => $evaluatedAt,
        ]);
        $this->recordManifestComponents(
            organizationId: $organizationId,
            programId: $programId,
            curriculumVersionId: $curriculumVersionId,
            publicationId: $publicationId,
            manifest: $manifest,
            createdAt: $evaluatedAt,
        );
        $this->sealWorkspace(
            organizationId: $organizationId,
            programId: $programId,
            curriculumVersionId: $curriculumVersionId,
            publicationId: $publicationId,
            actorMembershipId: $actorMembershipId,
            sealedAt: $evaluatedAt,
        );

        return new PublicationSnapshot(
            publicationId: $publicationId,
            manifestHash: $manifestHash,
        );
    }

    private function lockManifestInputs(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'LOCK TABLE curriculum_workspaces, curriculum_levels, learning_objectives, objective_prerequisites, curriculum_modules, lessons, lesson_objectives, content_documents, content_revisions, content_revision_heads, localized_variants, learning_items, curriculum_missions, mission_items, curriculum_graph_revisions, curriculum_graph_heads, curriculum_graph_reviews, authority_appointments, authority_appointment_events, content_reviews, media_assets, media_rights_revisions, content_revision_media_assets, media_security_scans, media_asset_lifecycles, media_asset_metadata_revisions, media_asset_metadata_heads, media_asset_metadata_variants, media_asset_metadata_variant_heads, media_asset_metadata_reviews, media_attachment_events IN SHARE MODE',
        );
    }

    private function sealWorkspace(
        string $organizationId,
        string $programId,
        string $curriculumVersionId,
        string $publicationId,
        string $actorMembershipId,
        CarbonInterface $sealedAt,
    ): void {
        $workspace = DB::table('curriculum_workspaces')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->lockForUpdate()
            ->first([
                'state',
                'version',
                'current_draft_revision_id',
                'submitted_graph_revision_id',
            ]);

        if (! $workspace instanceof \stdClass) {
            return;
        }

        $state = $workspace->state ?? null;
        $version = $workspace->version ?? null;

        if (
            $state !== 'in_review'
            || (! is_int($version) && ! (is_string($version) && ctype_digit($version)))
        ) {
            throw new CurriculumOperationRejected;
        }

        $nextVersion = (int) $version + 1;
        DB::table('curriculum_workspaces')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->where('version', (int) $version)
            ->update([
                'state' => 'sealed',
                'version' => $nextVersion,
                'sealed_at' => $sealedAt,
                'updated_at' => $sealedAt,
            ]);
        DB::table('curriculum_workspace_events')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'curriculum_version_id' => $curriculumVersionId,
            'event_type' => 'workspace.sealed',
            'workspace_version' => $nextVersion,
            'draft_revision_id' => $workspace->current_draft_revision_id,
            'graph_revision_id' => $workspace->submitted_graph_revision_id,
            'actor_membership_id' => $actorMembershipId,
            'command_id' => null,
            'reason_key' => 'publication_snapshot_created',
            'occurred_at' => $sealedAt,
            'created_at' => $sealedAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function recordManifestComponents(
        string $organizationId,
        string $programId,
        string $curriculumVersionId,
        string $publicationId,
        array $manifest,
        CarbonInterface $createdAt,
    ): void {
        $graphRevision = $this->array($manifest, 'graph_revision');
        $graphReview = $this->array($graphRevision, 'review');

        DB::table('publication_graph_components')->insert([
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'curriculum_version_id' => $curriculumVersionId,
            'curriculum_publication_id' => $publicationId,
            'curriculum_graph_revision_id' => $this->arrayString(
                $graphRevision,
                'id',
            ),
            'graph_hash' => $this->arraySha256(
                $graphRevision,
                'graph_hash',
            ),
            'curriculum_graph_review_id' => $this->arrayString(
                $graphReview,
                'id',
            ),
            'graph_review_decision' => 'approved',
            'academic_appointment_id' => $this->arrayString(
                $graphReview,
                'appointment_id',
            ),
            'academic_appointment_event_id' => $this->arrayString(
                $graphReview,
                'appointment_event_id',
            ),
            'reviewer_membership_id' => $this->arrayString(
                $graphReview,
                'reviewer_membership_id',
            ),
            'created_at' => $createdAt,
        ]);

        $contentEntries = $manifest['content'] ?? null;
        $managedPublication = ($manifest['schema'] ?? null)
            === 'global-english.curriculum-publication.v2';

        if (! is_array($contentEntries) || ! array_is_list($contentEntries)) {
            throw new CurriculumOperationRejected;
        }

        foreach ($contentEntries as $entry) {
            if (! is_array($entry)) {
                throw new CurriculumOperationRejected;
            }

            $document = $this->array($entry, 'document');
            $source = $this->array($entry, 'source_revision');
            $academicReview = $this->array($entry, 'academic_review');
            $localized = $entry['localized_variant'] ?? null;
            $localizedVariantId = null;
            $localizedContentHash = null;
            $kannadaReviewId = null;
            $kannadaAppointmentId = null;
            $kannadaAppointmentEventId = null;

            if ($localized !== null) {
                if (! is_array($localized)) {
                    throw new CurriculumOperationRejected;
                }

                $kannadaReview = $this->array($localized, 'review');
                $localizedVariantId = $this->arrayString($localized, 'id');
                $localizedContentHash = $this->arraySha256(
                    $localized,
                    'content_hash',
                );
                $kannadaReviewId = $this->arrayString($kannadaReview, 'id');
                $kannadaAppointmentId = $this->arrayString(
                    $kannadaReview,
                    'appointment_id',
                );
                $kannadaAppointmentEventId = $this->arrayString(
                    $kannadaReview,
                    'appointment_event_id',
                );
            }

            $contentDocumentId = $this->arrayString($document, 'id');
            $sourceRevisionId = $this->arrayString($source, 'id');

            DB::table('publication_content_components')->insert([
                'organization_id' => $organizationId,
                'program_id' => $programId,
                'curriculum_version_id' => $curriculumVersionId,
                'curriculum_publication_id' => $publicationId,
                'content_document_id' => $contentDocumentId,
                'source_revision_id' => $sourceRevisionId,
                'source_content_hash' => $this->arraySha256(
                    $source,
                    'content_hash',
                ),
                'academic_review_id' => $this->arrayString(
                    $academicReview,
                    'id',
                ),
                'academic_review_kind' => 'academic_en',
                'academic_review_decision' => 'approved',
                'academic_appointment_id' => $this->arrayString(
                    $academicReview,
                    'appointment_id',
                ),
                'academic_appointment_event_id' => $this->arrayString(
                    $academicReview,
                    'appointment_event_id',
                ),
                'localized_variant_id' => $localizedVariantId,
                'localized_content_hash' => $localizedContentHash,
                'kannada_review_id' => $kannadaReviewId,
                'kannada_review_kind' => $localized === null
                    ? null
                    : 'kannada',
                'kannada_review_decision' => $localized === null
                    ? null
                    : 'approved',
                'kannada_appointment_id' => $kannadaAppointmentId,
                'kannada_appointment_event_id' => $kannadaAppointmentEventId,
                'created_at' => $createdAt,
            ]);

            $mediaEntries = $entry['media'] ?? null;

            if (! is_array($mediaEntries) || ! array_is_list($mediaEntries)) {
                throw new CurriculumOperationRejected;
            }

            foreach ($mediaEntries as $media) {
                if (! is_array($media)) {
                    throw new CurriculumOperationRejected;
                }

                DB::table('publication_media_components')->insert([
                    'organization_id' => $organizationId,
                    'program_id' => $programId,
                    'curriculum_version_id' => $curriculumVersionId,
                    'curriculum_publication_id' => $publicationId,
                    'content_document_id' => $contentDocumentId,
                    'source_revision_id' => $sourceRevisionId,
                    'media_asset_id' => $this->arrayString(
                        $media,
                        'asset_id',
                    ),
                    'asset_hash' => $this->arraySha256(
                        $media,
                        'asset_hash',
                    ),
                    'media_rights_revision_id' => $this->arrayString(
                        $media,
                        'rights_revision_id',
                    ),
                    'rights_evidence_status' => 'approved',
                    'rights_evidence_hash' => $this->arraySha256(
                        $media,
                        'evidence_hash',
                    ),
                    'usage_key' => $this->arrayString($media, 'usage_key'),
                    'sequence' => $this->arrayInteger($media, 'sequence'),
                    'created_at' => $createdAt,
                ]);

                $governance = $media['governance'] ?? null;

                if ($governance === null) {
                    if ($managedPublication) {
                        throw new CurriculumOperationRejected;
                    }

                    continue;
                }

                if (! is_array($governance) || array_is_list($governance)) {
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
                $rights = $this->array($governance, 'rights');
                $assetId = $this->arrayString($media, 'asset_id');
                $assetHash = $this->arraySha256($media, 'asset_hash');
                $rightsRevisionId = $this->arrayString(
                    $media,
                    'rights_revision_id',
                );
                $rightsEvidenceHash = $this->arraySha256(
                    $media,
                    'evidence_hash',
                );

                if (
                    $this->arrayString($rights, 'revision_id')
                        !== $rightsRevisionId
                    || ! hash_equals(
                        $this->arraySha256($rights, 'evidence_hash'),
                        $rightsEvidenceHash,
                    )
                    || ! hash_equals(
                        $this->arraySha256($selectedScan, 'object_hash'),
                        $assetHash,
                    )
                ) {
                    throw new CurriculumOperationRejected;
                }

                DB::table('publication_media_governance_components')->insert([
                    'organization_id' => $organizationId,
                    'program_id' => $programId,
                    'curriculum_version_id' => $curriculumVersionId,
                    'curriculum_publication_id' => $publicationId,
                    'source_revision_id' => $sourceRevisionId,
                    'media_asset_id' => $assetId,
                    'usage_key' => $this->arrayString($media, 'usage_key'),
                    'selected_scan_id' => $this->arrayString(
                        $selectedScan,
                        'id',
                    ),
                    'object_hash' => $assetHash,
                    'inspector_policy_version' => $this->arrayString(
                        $selectedScan,
                        'inspector_policy_version',
                    ),
                    'scanner_policy_version' => $this->arrayString(
                        $selectedScan,
                        'scanner_policy_version',
                    ),
                    'metadata_revision_id' => $this->arrayString(
                        $metadata,
                        'revision_id',
                    ),
                    'metadata_content_hash' => $this->arraySha256(
                        $metadata,
                        'content_hash',
                    ),
                    'english_review_id' => $this->arrayString(
                        $englishReview,
                        'id',
                    ),
                    'metadata_variant_id' => $this->arrayString(
                        $kannadaVariant,
                        'id',
                    ),
                    'variant_content_hash' => $this->arraySha256(
                        $kannadaVariant,
                        'content_hash',
                    ),
                    'kannada_review_id' => $this->arrayString(
                        $kannadaReview,
                        'id',
                    ),
                    'media_rights_revision_id' => $rightsRevisionId,
                    'rights_evidence_status' => 'approved',
                    'rights_evidence_hash' => $rightsEvidenceHash,
                    'created_at' => $createdAt,
                ]);
            }
        }
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
     * @param  array<string, mixed>  $value
     */
    private function arrayInteger(array $value, string $field): int
    {
        $item = $value[$field] ?? null;

        if (! is_int($item) || $item < 0) {
            throw new CurriculumOperationRejected;
        }

        return $item;
    }
}

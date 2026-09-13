<?php

declare(strict_types=1);

namespace App\Curriculum\Publication;

use App\Curriculum\CanonicalJson;
use App\Curriculum\CurriculumGraphProjector;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Tenancy\OrganizationContextManager;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class PublicationManifestBuilder
{
    /**
     * Release channels and territories are project-owned policy, not caller
     * input. Expanding them requires a reviewed code/config change.
     */
    private const array RELEASE_CHANNELS = ['android', 'web'];

    private const array RELEASE_TERRITORIES = ['IN'];

    public function __construct(
        private OrganizationContextManager $contexts,
        private CurriculumGraphProjector $graphProjector,
        private ManagedMediaGovernanceEvidence $managedMedia,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(
        string $curriculumVersionId,
        CarbonInterface $evaluatedAt,
    ): array {
        $organizationId = $this->contexts->current()->organizationId;
        $version = DB::table('curriculum_versions')
            ->join('programs', function ($join): void {
                $join
                    ->on('programs.organization_id', '=', 'curriculum_versions.organization_id')
                    ->on('programs.id', '=', 'curriculum_versions.program_id');
            })
            ->where('curriculum_versions.organization_id', $organizationId)
            ->where('curriculum_versions.id', $curriculumVersionId)
            ->first([
                'curriculum_versions.id',
                'curriculum_versions.key',
                'curriculum_versions.program_id',
                'programs.key as program_key',
            ]);

        if (! $version instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        $programId = $this->string($version, 'program_id');
        $workspaceIsV2 = DB::table('curriculum_workspaces')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->where('graph_schema_version', 2)
            ->exists();
        $graphRevision = $this->approvedGraphManifest(
            organizationId: $organizationId,
            curriculumVersionId: $curriculumVersionId,
            programId: $programId,
            evaluatedAt: $evaluatedAt,
        );
        $levels = $workspaceIsV2
            ? $this->rows(
                DB::table('curriculum_levels')
                    ->where('organization_id', $organizationId)
                    ->where('curriculum_version_id', $curriculumVersionId)
                    ->whereNull('retired_at')
                    ->orderBy('sequence')
                    ->orderBy('key')
                    ->orderBy('id')
                    ->get(['id', 'key', 'sequence']),
            )
            : [];
        $objectives = $this->rows(
            DB::table('learning_objectives')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->whereNull('retired_at')
                ->orderBy('sequence')
                ->orderBy('key')
                ->orderBy('id')
                ->get(['id', 'key', 'outcome_code', 'sequence']),
        );
        $objectiveIds = array_map(
            fn (stdClass $row): string => $this->string($row, 'id'),
            $objectives,
        );
        $prerequisites = $this->rows(
            DB::table('objective_prerequisites')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->orderBy('objective_id')
                ->orderBy('prerequisite_objective_id')
                ->get(['objective_id', 'prerequisite_objective_id']),
        );
        $this->assertAcyclicPrerequisites($objectiveIds, $prerequisites);

        $modules = $this->rows(
            DB::table('curriculum_modules')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->whereNull('retired_at')
                ->when(
                    $workspaceIsV2,
                    fn ($query) => $query->orderBy('curriculum_level_id'),
                )
                ->orderBy('sequence')
                ->orderBy('key')
                ->orderBy('id')
                ->get([
                    'id',
                    'curriculum_level_id',
                    'key',
                    'sequence',
                    'estimated_minutes',
                ]),
        );
        $lessons = $this->rows(
            DB::table('lessons')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->whereNull('retired_at')
                ->orderBy('curriculum_module_id')
                ->orderBy('sequence')
                ->orderBy('key')
                ->orderBy('id')
                ->get([
                    'id',
                    'curriculum_module_id',
                    'key',
                    'sequence',
                ]),
        );
        $lessonObjectives = $this->rows(
            DB::table('lesson_objectives')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->orderBy('lesson_id')
                ->orderBy('objective_id')
                ->get(['lesson_id', 'objective_id']),
        );
        $items = $this->rows(
            DB::table('learning_items')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->whereNull('retired_at')
                ->orderBy('lesson_id')
                ->orderBy('sequence')
                ->orderBy('key')
                ->orderBy('id')
                ->get([
                    'id',
                    'lesson_id',
                    'content_document_id',
                    'key',
                    'item_type',
                    'sequence',
                ]),
        );
        $missions = $this->rows(
            DB::table('curriculum_missions')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->whereNull('retired_at')
                ->orderBy('sequence')
                ->orderBy('key')
                ->orderBy('id')
                ->get([
                    'id',
                    'instruction_content_document_id',
                    'key',
                    'sequence',
                ]),
        );
        $missionItems = $this->rows(
            DB::table('mission_items')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->orderBy('mission_id')
                ->orderBy('sequence')
                ->orderBy('learning_item_id')
                ->get(['mission_id', 'learning_item_id', 'sequence']),
        );

        $documentIds = [];

        foreach ($items as $item) {
            $documentIds[] = $this->string($item, 'content_document_id');
        }

        foreach ($missions as $mission) {
            $documentIds[] = $this->string(
                $mission,
                'instruction_content_document_id',
            );
        }

        $documentIds = array_values(array_unique($documentIds));
        sort($documentIds, SORT_STRING);

        if ($missions === [] || $missionItems === [] || $documentIds === []) {
            throw new CurriculumOperationRejected;
        }

        $documents = $this->contentDocuments(
            $organizationId,
            $curriculumVersionId,
            $documentIds,
        );
        $content = [];

        foreach ($documents as $document) {
            $content[] = $this->contentManifestEntry(
                organizationId: $organizationId,
                curriculumVersionId: $curriculumVersionId,
                programId: $programId,
                document: $document,
                evaluatedAt: $evaluatedAt,
                workspaceIsV2: $workspaceIsV2,
            );
        }

        $manifest = [
            'schema' => $workspaceIsV2
                ? 'global-english.curriculum-publication.v2'
                : 'global-english.curriculum-publication.v1',
            'organization_id' => $organizationId,
            'program' => [
                'id' => $programId,
                'key' => $this->string($version, 'program_key'),
            ],
            'curriculum_version' => [
                'id' => $this->string($version, 'id'),
                'key' => $this->string($version, 'key'),
            ],
            'graph_revision' => $graphRevision,
            'objectives' => array_map(fn (stdClass $row): array => [
                'id' => $this->string($row, 'id'),
                'key' => $this->string($row, 'key'),
                'outcome_code' => $this->string($row, 'outcome_code'),
                'sequence' => $this->integer($row, 'sequence'),
            ], $objectives),
            'objective_prerequisites' => array_map(
                fn (stdClass $row): array => [
                    'objective_id' => $this->string($row, 'objective_id'),
                    'prerequisite_objective_id' => $this->string(
                        $row,
                        'prerequisite_objective_id',
                    ),
                ],
                $prerequisites,
            ),
            'modules' => array_map(
                fn (stdClass $row): array => array_filter([
                    'id' => $this->string($row, 'id'),
                    'level_id' => $workspaceIsV2
                        ? $this->string($row, 'curriculum_level_id')
                        : null,
                    'key' => $this->string($row, 'key'),
                    'sequence' => $this->integer($row, 'sequence'),
                    'estimated_minutes' => $this->nullableInteger(
                        $row,
                        'estimated_minutes',
                    ),
                ], static fn (mixed $value, string $key): bool => (
                    $key !== 'level_id' || $value !== null
                ), ARRAY_FILTER_USE_BOTH),
                $modules,
            ),
            'lessons' => array_map(fn (stdClass $row): array => [
                'id' => $this->string($row, 'id'),
                'module_id' => $this->string($row, 'curriculum_module_id'),
                'key' => $this->string($row, 'key'),
                'sequence' => $this->integer($row, 'sequence'),
            ], $lessons),
            'lesson_objectives' => array_map(
                fn (stdClass $row): array => [
                    'lesson_id' => $this->string($row, 'lesson_id'),
                    'objective_id' => $this->string($row, 'objective_id'),
                ],
                $lessonObjectives,
            ),
            'learning_items' => array_map(fn (stdClass $row): array => [
                'id' => $this->string($row, 'id'),
                'lesson_id' => $this->string($row, 'lesson_id'),
                'content_document_id' => $this->string(
                    $row,
                    'content_document_id',
                ),
                'key' => $this->string($row, 'key'),
                'item_type' => $this->string($row, 'item_type'),
                'sequence' => $this->integer($row, 'sequence'),
            ], $items),
            'missions' => array_map(fn (stdClass $row): array => [
                'id' => $this->string($row, 'id'),
                'instruction_content_document_id' => $this->string(
                    $row,
                    'instruction_content_document_id',
                ),
                'key' => $this->string($row, 'key'),
                'sequence' => $this->integer($row, 'sequence'),
            ], $missions),
            'mission_items' => array_map(fn (stdClass $row): array => [
                'mission_id' => $this->string($row, 'mission_id'),
                'learning_item_id' => $this->string(
                    $row,
                    'learning_item_id',
                ),
                'sequence' => $this->integer($row, 'sequence'),
            ], $missionItems),
            'content' => $content,
        ];

        if ($workspaceIsV2) {
            if ($levels === []) {
                throw new CurriculumOperationRejected;
            }

            $manifest['levels'] = array_map(fn (stdClass $row): array => [
                'id' => $this->string($row, 'id'),
                'key' => $this->string($row, 'key'),
                'sequence' => $this->integer($row, 'sequence'),
            ], $levels);
        }

        return $manifest;
    }

    /**
     * @return array<string, mixed>
     */
    private function approvedGraphManifest(
        string $organizationId,
        string $curriculumVersionId,
        string $programId,
        CarbonInterface $evaluatedAt,
    ): array {
        $projected = $this->graphProjector->project(
            $organizationId,
            $curriculumVersionId,
        );
        $head = DB::table('curriculum_graph_heads')
            ->join('curriculum_graph_revisions', function ($join): void {
                $join
                    ->on(
                        'curriculum_graph_revisions.organization_id',
                        '=',
                        'curriculum_graph_heads.organization_id',
                    )
                    ->on(
                        'curriculum_graph_revisions.curriculum_version_id',
                        '=',
                        'curriculum_graph_heads.curriculum_version_id',
                    )
                    ->on(
                        'curriculum_graph_revisions.id',
                        '=',
                        'curriculum_graph_heads.curriculum_graph_revision_id',
                    );
            })
            ->where(
                'curriculum_graph_heads.organization_id',
                $organizationId,
            )
            ->where(
                'curriculum_graph_heads.curriculum_version_id',
                $curriculumVersionId,
            )
            ->first([
                'curriculum_graph_revisions.id',
                'curriculum_graph_revisions.revision_number',
                'curriculum_graph_revisions.graph',
                'curriculum_graph_revisions.graph_hash',
                'curriculum_graph_revisions.authored_by_membership_id',
                'curriculum_graph_revisions.authored_at',
            ]);

        if (! $head instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        $graphRevisionId = $this->string($head, 'id');
        $graphHash = $this->sha256($head, 'graph_hash');
        $storedGraph = $this->jsonArray($head, 'graph');
        $projectedGraph = $projected['graph'];
        $projectedHash = $projected['graph_hash'];

        if (
            ! hash_equals($graphHash, $projectedHash)
            || ! hash_equals(
                CanonicalJson::encode($storedGraph),
                CanonicalJson::encode($projectedGraph),
            )
            || $this->date($head, 'authored_at')->greaterThan($evaluatedAt)
        ) {
            throw new CurriculumOperationRejected;
        }

        $review = DB::table('curriculum_graph_reviews')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->where('curriculum_graph_revision_id', $graphRevisionId)
            ->where('graph_hash', $graphHash)
            ->where('reviewed_at', '<=', $evaluatedAt)
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->first([
                'id',
                'decision',
                'authority_appointment_id',
                'authority_appointment_event_id',
                'reviewer_membership_id',
                'rationale_key',
                'reviewed_at',
            ]);

        if (
            ! $review instanceof stdClass
            || $this->string($review, 'decision') !== 'approved'
        ) {
            throw new CurriculumOperationRejected;
        }

        $reviewedAt = $this->date($review, 'reviewed_at');
        $appointmentId = $this->string(
            $review,
            'authority_appointment_id',
        );
        $appointmentEventId = $this->string(
            $review,
            'authority_appointment_event_id',
        );
        $reviewerMembershipId = $this->string(
            $review,
            'reviewer_membership_id',
        );
        $authorMembershipId = $this->nullableString(
            $head,
            'authored_by_membership_id',
        );

        $this->assertAppointmentEvidence(
            organizationId: $organizationId,
            programId: $programId,
            appointmentId: $appointmentId,
            appointmentEventId: $appointmentEventId,
            appointeeMembershipId: $reviewerMembershipId,
            authorityKind: 'english_academic_reviewer',
            evidenceTime: $reviewedAt,
            evaluatedAt: $evaluatedAt,
        );
        $this->assertDistinctHumans(
            $organizationId,
            $authorMembershipId,
            $reviewerMembershipId,
        );

        return [
            'id' => $graphRevisionId,
            'revision_number' => $this->integer($head, 'revision_number'),
            'graph_hash' => $graphHash,
            'author_membership_id' => $authorMembershipId,
            'graph' => $projectedGraph,
            'review' => [
                'id' => $this->string($review, 'id'),
                'decision' => 'approved',
                'appointment_id' => $appointmentId,
                'appointment_event_id' => $appointmentEventId,
                'reviewer_membership_id' => $reviewerMembershipId,
                'rationale_key' => $this->nullableString(
                    $review,
                    'rationale_key',
                ),
            ],
        ];
    }

    /**
     * @param  list<string>  $documentIds
     * @return list<stdClass>
     */
    private function contentDocuments(
        string $organizationId,
        string $curriculumVersionId,
        array $documentIds,
    ): array {
        $documents = $this->rows(
            DB::table('content_documents')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->whereIn('id', $documentIds)
                ->orderBy('key')
                ->orderBy('id')
                ->get([
                    'id',
                    'key',
                ]),
        );

        if (count($documents) !== count($documentIds)) {
            throw new CurriculumOperationRejected;
        }

        return $documents;
    }

    /**
     * @return array<string, mixed>
     */
    private function contentManifestEntry(
        string $organizationId,
        string $curriculumVersionId,
        string $programId,
        stdClass $document,
        CarbonInterface $evaluatedAt,
        bool $workspaceIsV2,
    ): array {
        $documentId = $this->string($document, 'id');
        $head = DB::table('content_revision_heads')
            ->join('content_revisions', function ($join): void {
                $join
                    ->on('content_revisions.organization_id', '=', 'content_revision_heads.organization_id')
                    ->on('content_revisions.curriculum_version_id', '=', 'content_revision_heads.curriculum_version_id')
                    ->on('content_revisions.content_document_id', '=', 'content_revision_heads.content_document_id')
                    ->on('content_revisions.id', '=', 'content_revision_heads.content_revision_id');
            })
            ->where('content_revision_heads.organization_id', $organizationId)
            ->where(
                'content_revision_heads.curriculum_version_id',
                $curriculumVersionId,
            )
            ->where('content_revision_heads.content_document_id', $documentId)
            ->first([
                'content_revisions.id',
                'content_revisions.content_document_id',
                'content_revisions.payload',
                'content_revisions.content_hash',
                'content_revisions.authored_by_membership_id',
                'content_revisions.authored_at',
                'content_revisions.revision_number',
                'content_revisions.content_type',
                'content_revisions.learner_visible',
                'content_revisions.localization_requirement',
                'content_revisions.pedagogically_authoritative',
            ]);

        if (
            ! $head instanceof stdClass
            || $this->date($head, 'authored_at')->greaterThan($evaluatedAt)
            || DB::table('content_revisions')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->where('content_document_id', $documentId)
                ->where(
                    'revision_number',
                    '>',
                    $this->integer($head, 'revision_number'),
                )
                ->exists()
        ) {
            throw new CurriculumOperationRejected;
        }

        $payload = $this->jsonArray($head, 'payload');
        $contentHash = $this->sha256($head, 'content_hash');

        if (! hash_equals($contentHash, CanonicalJson::sha256($payload))) {
            throw new CurriculumOperationRejected;
        }

        $pedagogical = $this->boolean(
            $head,
            'pedagogically_authoritative',
        );

        if (! $pedagogical) {
            // PR-005 defines no caller-selectable non-pedagogical content
            // category. A later reviewed code policy may add one.
            throw new CurriculumOperationRejected;
        }

        $academicReview = $this->approvedReview(
            organizationId: $organizationId,
            programId: $programId,
            sourceRevisionId: $this->string($head, 'id'),
            sourceContentHash: $contentHash,
            reviewKind: 'academic_en',
            localizedVariantId: null,
            localizedContentHash: null,
            creatorMembershipId: $this->nullableString(
                $head,
                'authored_by_membership_id',
            ),
            requiredAuthorityKind: 'english_academic_reviewer',
            evaluatedAt: $evaluatedAt,
        );
        $localizationRequirement = $this->string(
            $head,
            'localization_requirement',
        );
        $localized = null;

        if ($localizationRequirement === 'kn_required') {
            $localized = $this->approvedKannadaVariant(
                organizationId: $organizationId,
                curriculumVersionId: $curriculumVersionId,
                programId: $programId,
                sourceRevisionId: $this->string($head, 'id'),
                sourceContentHash: $contentHash,
                evaluatedAt: $evaluatedAt,
            );
        } elseif ($localizationRequirement === 'english_only_pedagogical') {
            $contentType = $this->string($head, 'content_type');

            if (
                in_array(
                    $contentType,
                    ['instruction', 'explanation', 'feedback', 'learner_action'],
                    true,
                )
                || $this->nullableString($academicReview, 'rationale_key') === null
            ) {
                throw new CurriculumOperationRejected;
            }
        } else {
            throw new CurriculumOperationRejected;
        }

        $media = $this->mediaEntries(
            organizationId: $organizationId,
            curriculumVersionId: $curriculumVersionId,
            programId: $programId,
            sourceRevisionId: $this->string($head, 'id'),
            evaluatedAt: $evaluatedAt,
            workspaceIsV2: $workspaceIsV2,
        );

        if (
            $workspaceIsV2
            && ($payload['schema'] ?? null)
                === 'global-english.daily-speaking-mission.v1'
        ) {
            $modelAudio = array_values(array_filter(
                $media,
                static fn (array $entry): bool => (
                    ($entry['usage_key'] ?? null) === 'model_audio'
                ),
            ));

            if (count($modelAudio) !== 1) {
                throw new CurriculumOperationRejected;
            }
        }

        return [
            'document' => [
                'id' => $documentId,
                'key' => $this->string($document, 'key'),
                'content_type' => $this->string($head, 'content_type'),
                'learner_visible' => $this->boolean(
                    $head,
                    'learner_visible',
                ),
                'localization_requirement' => $localizationRequirement,
                'pedagogically_authoritative' => true,
            ],
            'source_revision' => [
                'id' => $this->string($head, 'id'),
                'content_hash' => $contentHash,
            ],
            'academic_review' => $this->reviewReference($academicReview),
            'localized_variant' => $localized,
            'media' => $media,
        ];
    }

    private function approvedReview(
        string $organizationId,
        string $programId,
        string $sourceRevisionId,
        string $sourceContentHash,
        string $reviewKind,
        ?string $localizedVariantId,
        ?string $localizedContentHash,
        ?string $creatorMembershipId,
        string $requiredAuthorityKind,
        CarbonInterface $evaluatedAt,
    ): stdClass {
        $query = DB::table('content_reviews')
            ->where('organization_id', $organizationId)
            ->where('source_revision_id', $sourceRevisionId)
            ->where('review_kind', $reviewKind);

        if ($localizedVariantId === null) {
            $query->whereNull('localized_variant_id');
        } else {
            $query->where('localized_variant_id', $localizedVariantId);
        }

        $review = $query
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->first();

        if (
            ! $review instanceof stdClass
            || $this->string($review, 'decision') !== 'approved'
            || ! hash_equals(
                $sourceContentHash,
                $this->sha256($review, 'source_content_hash'),
            )
        ) {
            throw new CurriculumOperationRejected;
        }

        if ($localizedContentHash === null) {
            if ($this->nullableString($review, 'localized_content_hash') !== null) {
                throw new CurriculumOperationRejected;
            }
        } elseif (
            ! hash_equals(
                $localizedContentHash,
                $this->sha256($review, 'localized_content_hash'),
            )
        ) {
            throw new CurriculumOperationRejected;
        }

        $reviewedAt = $this->date($review, 'reviewed_at');

        if ($reviewedAt->greaterThan($evaluatedAt)) {
            throw new CurriculumOperationRejected;
        }

        $reviewerMembershipId = $this->string(
            $review,
            'reviewer_membership_id',
        );
        $this->assertDistinctHumans(
            $organizationId,
            $creatorMembershipId,
            $reviewerMembershipId,
        );
        $this->assertAppointmentEvidence(
            organizationId: $organizationId,
            programId: $programId,
            appointmentId: $this->string(
                $review,
                'authority_appointment_id',
            ),
            appointmentEventId: $this->string(
                $review,
                'authority_appointment_event_id',
            ),
            appointeeMembershipId: $reviewerMembershipId,
            authorityKind: $requiredAuthorityKind,
            evidenceTime: $reviewedAt,
            evaluatedAt: $evaluatedAt,
        );

        return $review;
    }

    /**
     * @return array<string, mixed>
     */
    private function approvedKannadaVariant(
        string $organizationId,
        string $curriculumVersionId,
        string $programId,
        string $sourceRevisionId,
        string $sourceContentHash,
        CarbonInterface $evaluatedAt,
    ): array {
        $variants = $this->rows(
            DB::table('localized_variants')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->where('source_revision_id', $sourceRevisionId)
                ->where('locale', 'kn-IN')
                ->orderByDesc('revision_number')
                ->orderByDesc('id')
                ->get([
                    'id',
                    'payload',
                    'content_hash',
                    'source_content_hash',
                    'translated_by_membership_id',
                    'translated_at',
                ]),
        );

        if ($variants === []) {
            throw new CurriculumOperationRejected;
        }

        $variant = $variants[0];
        $variantSourceHash = $this->sha256(
            $variant,
            'source_content_hash',
        );
        $variantHash = $this->sha256($variant, 'content_hash');

        if (
            ! hash_equals($sourceContentHash, $variantSourceHash)
            || $this->date($variant, 'translated_at')->greaterThan($evaluatedAt)
            || ! hash_equals(
                $variantHash,
                CanonicalJson::sha256($this->jsonArray($variant, 'payload')),
            )
        ) {
            throw new CurriculumOperationRejected;
        }

        $review = $this->approvedReview(
            organizationId: $organizationId,
            programId: $programId,
            sourceRevisionId: $sourceRevisionId,
            sourceContentHash: $sourceContentHash,
            reviewKind: 'kannada',
            localizedVariantId: $this->string($variant, 'id'),
            localizedContentHash: $variantHash,
            creatorMembershipId: $this->string(
                $variant,
                'translated_by_membership_id',
            ),
            requiredAuthorityKind: 'kannada_reviewer',
            evaluatedAt: $evaluatedAt,
        );

        return [
            'id' => $this->string($variant, 'id'),
            'locale' => 'kn-IN',
            'source_content_hash' => $variantSourceHash,
            'content_hash' => $variantHash,
            'review' => $this->reviewReference($review),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mediaEntries(
        string $organizationId,
        string $curriculumVersionId,
        string $programId,
        string $sourceRevisionId,
        CarbonInterface $evaluatedAt,
        bool $workspaceIsV2,
    ): array {
        $rows = $this->rows(
            DB::table('content_revision_media_assets')
                ->join('media_assets', function ($join): void {
                    $join
                        ->on('media_assets.organization_id', '=', 'content_revision_media_assets.organization_id')
                        ->on('media_assets.id', '=', 'content_revision_media_assets.media_asset_id');
                })
                ->where(
                    'content_revision_media_assets.organization_id',
                    $organizationId,
                )
                ->where(
                    'content_revision_media_assets.curriculum_version_id',
                    $curriculumVersionId,
                )
                ->where(
                    'content_revision_media_assets.content_revision_id',
                    $sourceRevisionId,
                )
                ->orderBy('content_revision_media_assets.sequence')
                ->orderBy('content_revision_media_assets.media_asset_id')
                ->orderBy('content_revision_media_assets.usage_key')
                ->get([
                    'content_revision_media_assets.media_asset_id',
                    'content_revision_media_assets.usage_key',
                    'content_revision_media_assets.sequence',
                    'media_assets.asset_hash',
                    'media_assets.uploaded_at',
                ]),
        );
        $result = [];

        foreach ($rows as $attachment) {
            $assetId = $this->string($attachment, 'media_asset_id');
            $managed = DB::table('media_asset_lifecycles')
                ->where('organization_id', $organizationId)
                ->where('media_asset_id', $assetId)
                ->exists();

            if ($managed !== $workspaceIsV2) {
                throw new CurriculumOperationRejected;
            }

            $rights = DB::table('media_rights_revisions')
                ->where('organization_id', $organizationId)
                ->where('program_id', $programId)
                ->where('media_asset_id', $assetId)
                ->where('effective_at', '<=', $evaluatedAt)
                ->orderByDesc('revision_number')
                ->orderByDesc('id')
                ->first([
                    'id as media_rights_revision_id',
                    'program_id',
                    'evidence_status',
                    'evidence_hash',
                    'selected_asset_hash',
                    'permitted_release_channels',
                    'permitted_territories',
                    'learner_delivery_allowed',
                    'effective_at',
                    'expires_at',
                    'confirmed_by_membership_id',
                    'rights_appointment_id',
                    'rights_appointment_event_id',
                    'confirmed_at',
                    'sharing_allowed',
                    'derivative_use_allowed',
                ]);

            if (! $rights instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            $rights->media_asset_id = $assetId;
            $rights->asset_hash = $attachment->asset_hash ?? null;
            $rights->uploaded_at = $attachment->uploaded_at ?? null;

            $this->assertRightsEvidence(
                $organizationId,
                $programId,
                $rights,
                $evaluatedAt,
            );
            $entry = [
                'asset_id' => $assetId,
                'asset_hash' => $this->sha256($rights, 'asset_hash'),
                'rights_revision_id' => $this->string(
                    $rights,
                    'media_rights_revision_id',
                ),
                'evidence_hash' => $this->sha256($rights, 'evidence_hash'),
                'usage_key' => $this->string($attachment, 'usage_key'),
                'sequence' => $this->integer($attachment, 'sequence'),
                'sharing_allowed' => $this->boolean(
                    $rights,
                    'sharing_allowed',
                ),
                'derivative_use_allowed' => $this->boolean(
                    $rights,
                    'derivative_use_allowed',
                ),
            ];

            if ($workspaceIsV2) {
                $entry['governance'] = $this->managedMedia->selectCurrent(
                    organizationId: $organizationId,
                    programId: $programId,
                    curriculumVersionId: $curriculumVersionId,
                    sourceRevisionId: $sourceRevisionId,
                    mediaAssetId: $assetId,
                    assetHash: $this->sha256($rights, 'asset_hash'),
                    usageKey: $this->string($attachment, 'usage_key'),
                    rightsRevisionId: $this->string(
                        $rights,
                        'media_rights_revision_id',
                    ),
                    rightsEvidenceHash: $this->sha256(
                        $rights,
                        'evidence_hash',
                    ),
                    evaluatedAt: $evaluatedAt,
                );
            }

            $result[] = $entry;
        }

        return $result;
    }

    private function assertRightsEvidence(
        string $organizationId,
        string $programId,
        stdClass $rights,
        CarbonInterface $evaluatedAt,
    ): void {
        $effectiveAt = $this->date($rights, 'effective_at');
        $expiresAt = $this->nullableDate($rights, 'expires_at');
        $channels = $this->jsonArray(
            $rights,
            'permitted_release_channels',
        );
        $territories = $this->jsonArray(
            $rights,
            'permitted_territories',
        );

        if (
            $this->string($rights, 'evidence_status') !== 'approved'
            || ! hash_equals(
                $this->string($rights, 'program_id'),
                $programId,
            )
            || ! hash_equals(
                $this->sha256($rights, 'asset_hash'),
                $this->sha256($rights, 'selected_asset_hash'),
            )
            || $this->date($rights, 'uploaded_at')->greaterThan($evaluatedAt)
            || $effectiveAt->greaterThan($evaluatedAt)
            || (
                $expiresAt instanceof CarbonInterface
                && $evaluatedAt->greaterThanOrEqualTo($expiresAt)
            )
            || ! $this->boolean($rights, 'learner_delivery_allowed')
            || array_diff(self::RELEASE_CHANNELS, $channels) !== []
            || array_diff(self::RELEASE_TERRITORIES, $territories) !== []
        ) {
            throw new CurriculumOperationRejected;
        }

        $confirmedAt = $this->nullableDate($rights, 'confirmed_at');
        $membershipId = $this->nullableString(
            $rights,
            'confirmed_by_membership_id',
        );
        $appointmentId = $this->nullableString(
            $rights,
            'rights_appointment_id',
        );
        $appointmentEventId = $this->nullableString(
            $rights,
            'rights_appointment_event_id',
        );

        if (
            ! $confirmedAt instanceof CarbonInterface
            || $confirmedAt->greaterThan($evaluatedAt)
            || $membershipId === null
            || $appointmentId === null
            || $appointmentEventId === null
        ) {
            throw new CurriculumOperationRejected;
        }

        $this->assertAppointmentEvidence(
            organizationId: $organizationId,
            programId: $programId,
            appointmentId: $appointmentId,
            appointmentEventId: $appointmentEventId,
            appointeeMembershipId: $membershipId,
            authorityKind: 'rights_confirmer',
            evidenceTime: $confirmedAt,
            evaluatedAt: $evaluatedAt,
        );
    }

    private function assertAppointmentEvidence(
        string $organizationId,
        string $programId,
        string $appointmentId,
        string $appointmentEventId,
        string $appointeeMembershipId,
        string $authorityKind,
        CarbonInterface $evidenceTime,
        CarbonInterface $evaluatedAt,
    ): void {
        $appointment = DB::table('authority_appointments')
            ->where('organization_id', $organizationId)
            ->where('id', $appointmentId)
            ->where('appointee_membership_id', $appointeeMembershipId)
            ->where('authority_kind', $authorityKind)
            ->first([
                'id',
                'scope_type',
                'program_id',
                'valid_from',
                'valid_until',
            ]);

        if (! $appointment instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        $validFrom = $this->date($appointment, 'valid_from');
        $validUntil = $this->nullableDate($appointment, 'valid_until');
        $scopeType = $this->string($appointment, 'scope_type');
        $scopeValid = $scopeType === 'organization'
            || (
                $scopeType === 'program'
                && $this->nullableString($appointment, 'program_id') === $programId
            );

        if (
            ! $scopeValid
            || $evidenceTime->lessThan($validFrom)
            || (
                $validUntil instanceof CarbonInterface
                && $evidenceTime->greaterThanOrEqualTo($validUntil)
            )
        ) {
            throw new CurriculumOperationRejected;
        }

        $accepted = DB::table('authority_appointment_events')
            ->where('organization_id', $organizationId)
            ->where('authority_appointment_id', $appointmentId)
            ->where('id', $appointmentEventId)
            ->where('event_type', 'accepted')
            ->first(['effective_at']);

        if (
            ! $accepted instanceof stdClass
            || $this->date($accepted, 'effective_at')->greaterThan($evidenceTime)
        ) {
            throw new CurriculumOperationRejected;
        }

        $invalidAtEvidence = DB::table('authority_appointment_events')
            ->where('organization_id', $organizationId)
            ->where('authority_appointment_id', $appointmentId)
            ->whereIn(
                'event_type',
                ['surrendered', 'revoked', 'expired', 'revoked_for_cause'],
            )
            ->where('effective_at', '<=', $evidenceTime)
            ->exists();
        $invalidatedForCause = DB::table('authority_appointment_events')
            ->where('organization_id', $organizationId)
            ->where('authority_appointment_id', $appointmentId)
            ->where('event_type', 'revoked_for_cause')
            ->where('invalidates_prior_approvals', true)
            ->where('effective_at', '<=', $evaluatedAt)
            ->exists();

        if ($invalidAtEvidence || $invalidatedForCause) {
            throw new CurriculumOperationRejected;
        }
    }

    private function assertDistinctHumans(
        string $organizationId,
        ?string $creatorMembershipId,
        string $reviewerMembershipId,
    ): void {
        if ($creatorMembershipId === null) {
            return;
        }

        $userIds = DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->whereIn('id', [$creatorMembershipId, $reviewerMembershipId])
            ->pluck('user_id', 'id');

        $creatorUserId = $userIds->get($creatorMembershipId);
        $reviewerUserId = $userIds->get($reviewerMembershipId);

        if (
            ! is_string($creatorUserId)
            || ! is_string($reviewerUserId)
            || hash_equals($creatorUserId, $reviewerUserId)
        ) {
            throw new CurriculumOperationRejected;
        }
    }

    /**
     * @param  list<string>  $objectiveIds
     * @param  list<stdClass>  $prerequisites
     */
    private function assertAcyclicPrerequisites(
        array $objectiveIds,
        array $prerequisites,
    ): void {
        $known = array_fill_keys($objectiveIds, true);
        $edges = [];

        foreach ($objectiveIds as $objectiveId) {
            $edges[$objectiveId] = [];
        }

        foreach ($prerequisites as $row) {
            $objectiveId = $this->string($row, 'objective_id');
            $requiredId = $this->string($row, 'prerequisite_objective_id');

            if (! isset($known[$objectiveId], $known[$requiredId])) {
                throw new CurriculumOperationRejected;
            }

            $edges[$objectiveId][] = $requiredId;
        }

        $visiting = [];
        $visited = [];

        $visit = function (string $objectiveId) use (
            &$visit,
            &$visiting,
            &$visited,
            $edges,
        ): void {
            if (isset($visiting[$objectiveId])) {
                throw new CurriculumOperationRejected;
            }

            if (isset($visited[$objectiveId])) {
                return;
            }

            $visiting[$objectiveId] = true;

            foreach ($edges[$objectiveId] as $requiredId) {
                $visit($requiredId);
            }

            unset($visiting[$objectiveId]);
            $visited[$objectiveId] = true;
        };

        foreach ($objectiveIds as $objectiveId) {
            $visit($objectiveId);
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function reviewReference(stdClass $review): array
    {
        return [
            'id' => $this->string($review, 'id'),
            'appointment_id' => $this->string(
                $review,
                'authority_appointment_id',
            ),
            'appointment_event_id' => $this->string(
                $review,
                'authority_appointment_event_id',
            ),
            'rationale_key' => $this->nullableString(
                $review,
                'rationale_key',
            ),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $collection
     * @return list<stdClass>
     */
    private function rows(Collection $collection): array
    {
        $rows = [];

        foreach ($collection as $row) {
            if (! $row instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    private function nullableString(stdClass $row, string $field): ?string
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

    private function nullableInteger(stdClass $row, string $field): ?int
    {
        if (($row->{$field} ?? null) === null) {
            return null;
        }

        return $this->integer($row, $field);
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

    private function sha256(stdClass $row, string $field): string
    {
        $value = $this->string($row, $field);

        if (preg_match('/\A[0-9a-f]{64}\z/', $value) !== 1) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    /**
     * @return array<mixed>
     */
    private function jsonArray(stdClass $row, string $field): array
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

    private function date(stdClass $row, string $field): CarbonImmutable
    {
        $value = $this->string($row, $field);

        return CarbonImmutable::parse($value);
    }

    private function nullableDate(
        stdClass $row,
        string $field,
    ): ?CarbonImmutable {
        $value = $this->nullableString($row, $field);

        return $value === null ? null : CarbonImmutable::parse($value);
    }
}

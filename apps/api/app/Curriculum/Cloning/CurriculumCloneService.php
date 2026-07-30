<?php

declare(strict_types=1);

namespace App\Curriculum\Cloning;

use App\Audit\AuditEventRecorder;
use App\Curriculum\CanonicalJson;
use App\Curriculum\CurriculumGraphProjector;
use App\Curriculum\Exceptions\CurriculumIdempotencyConflict;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Sharing\SharedCurriculumAccessResolver;
use App\Models\User;
use App\Outbox\OutboxEventRecorder;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContextManager;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use stdClass;

final readonly class CurriculumCloneService
{
    private const string OPERATION = 'curriculum.shared.clone';

    private const string CLONE_ATTRIBUTION = 'recipient_publication_clone';

    public function __construct(
        private OrganizationContextManager $contexts,
        private OrganizationAuthorization $authorization,
        private SharedCurriculumAccessResolver $sharedAccess,
        private CurriculumGraphProjector $graphProjector,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function clone(
        User $actor,
        string $shareGrantId,
        string $programKey,
        string $curriculumVersionKey,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): CurriculumCloneResult {
        $this->assertInput(
            shareGrantId: $shareGrantId,
            programKey: $programKey,
            curriculumVersionKey: $curriculumVersionKey,
            idempotencyKey: $idempotencyKey,
            correlationId: $correlationId,
        );

        if (
            ! $this->authorization->allows(
                $actor,
                'curriculum.shared.clone',
            )
        ) {
            throw new CurriculumOperationRejected;
        }

        $context = $this->contexts->current();
        $actorMembershipId = $context->membershipId;

        if (
            $actorMembershipId === null
            || $context->actorUserId !== (string) $actor->getKey()
        ) {
            throw new CurriculumOperationRejected;
        }

        try {
            return DB::transaction(function () use (
                $actor,
                $shareGrantId,
                $programKey,
                $curriculumVersionKey,
                $idempotencyKey,
                $correlationId,
                $context,
                $actorMembershipId,
            ): CurriculumCloneResult {
                $this->lockRecipientAuthority(
                    organizationId: $context->organizationId,
                    actorUserId: (string) $actor->getKey(),
                    actorMembershipId: $actorMembershipId,
                );

                if (
                    ! $this->authorization->allows(
                        $actor,
                        'curriculum.shared.clone',
                    )
                ) {
                    throw new CurriculumOperationRejected;
                }

                $this->lockRecipientGrant(
                    organizationId: $context->organizationId,
                    shareGrantId: $shareGrantId,
                );
                $fingerprint = CanonicalJson::sha256([
                    'actor_membership_id' => $actorMembershipId,
                    'actor_user_id' => (string) $actor->getKey(),
                    'curriculum_version_key' => $curriculumVersionKey,
                    'operation' => self::OPERATION,
                    'program_key' => $programKey,
                    'share_grant_id' => $shareGrantId,
                ]);
                $replay = $this->replay(
                    organizationId: $context->organizationId,
                    shareGrantId: $shareGrantId,
                    idempotencyKey: $idempotencyKey,
                    fingerprint: $fingerprint,
                );

                if ($replay instanceof CurriculumCloneResult) {
                    return $replay;
                }

                $access = $this->sharedAccess->resolve(
                    actor: $actor,
                    shareGrantId: $shareGrantId,
                    use: 'clone',
                );

                if (
                    $access->recipientOrganizationId
                        !== $context->organizationId
                    || $access->shareGrantId !== $shareGrantId
                ) {
                    throw new CurriculumOperationRejected;
                }

                $source = $access->publication;

                // A database/object copy is not proof that the recipient has a
                // separately governed media object. Until project-owned object
                // copy semantics exist, media-bearing releases fail closed.
                if (
                    DB::table('publication_media_components')
                        ->where(
                            'organization_id',
                            $source->ownerOrganizationId,
                        )
                        ->where(
                            'curriculum_publication_id',
                            $source->publicationId,
                        )
                        ->exists()
                ) {
                    throw new CurriculumOperationRejected;
                }

                $sourcePublication = DB::table('curriculum_publications')
                    ->join('programs', function ($join): void {
                        $join
                            ->on(
                                'programs.organization_id',
                                '=',
                                'curriculum_publications.organization_id',
                            )
                            ->on(
                                'programs.id',
                                '=',
                                'curriculum_publications.program_id',
                            );
                    })
                    ->where(
                        'curriculum_publications.organization_id',
                        $source->ownerOrganizationId,
                    )
                    ->where(
                        'curriculum_publications.id',
                        $source->publicationId,
                    )
                    ->where(
                        'curriculum_publications.curriculum_version_id',
                        $source->curriculumVersionId,
                    )
                    ->first([
                        'curriculum_publications.manifest',
                        'programs.internal_name',
                    ]);

                if (! $sourcePublication instanceof stdClass) {
                    throw new CurriculumOperationRejected;
                }

                $manifest = $this->jsonObject(
                    $sourcePublication,
                    'manifest',
                );
                $graphRevision = $this->arrayObject(
                    $manifest,
                    'graph_revision',
                );
                $sourceGraph = $this->arrayObject(
                    $graphRevision,
                    'graph',
                );
                $content = $this->arrayList($manifest, 'content');

                if (
                    $this->arrayString(
                        $sourceGraph,
                        'organization_id',
                    ) !== $source->ownerOrganizationId
                    || $this->arrayString(
                        $sourceGraph,
                        'curriculum_version_id',
                    ) !== $source->curriculumVersionId
                    || $content === []
                ) {
                    throw new CurriculumOperationRejected;
                }

                $occurredAt = $access->evaluatedAt;
                $cloneId = (string) Str::ulid();
                $programId = (string) Str::ulid();
                $curriculumVersionId = (string) Str::ulid();
                $eventCorrelationId = $correlationId
                    ?? $context->correlationId
                    ?? (string) Str::uuid();

                DB::table('programs')->insert([
                    'id' => $programId,
                    'organization_id' => $context->organizationId,
                    'key' => $programKey,
                    'internal_name' => $this->string(
                        $sourcePublication,
                        'internal_name',
                    ),
                    'created_by_membership_id' => $actorMembershipId,
                    'created_by_attribution' => self::CLONE_ATTRIBUTION,
                    'created_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ]);
                DB::table('curriculum_versions')->insert([
                    'id' => $curriculumVersionId,
                    'organization_id' => $context->organizationId,
                    'program_id' => $programId,
                    'key' => $curriculumVersionKey,
                    'created_by_membership_id' => $actorMembershipId,
                    'created_by_attribution' => self::CLONE_ATTRIBUTION,
                    'created_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ]);

                $documentIds = $this->cloneContent(
                    content: $content,
                    sourceOwnerOrganizationId: $source->ownerOrganizationId,
                    sourceCurriculumVersionId: $source->curriculumVersionId,
                    recipientOrganizationId: $context->organizationId,
                    curriculumVersionId: $curriculumVersionId,
                    actorMembershipId: $actorMembershipId,
                    occurredAt: $occurredAt,
                );
                $this->cloneGraph(
                    sourceGraph: $sourceGraph,
                    documentIds: $documentIds,
                    recipientOrganizationId: $context->organizationId,
                    curriculumVersionId: $curriculumVersionId,
                    occurredAt: $occurredAt,
                );
                $projection = $this->graphProjector->project(
                    $context->organizationId,
                    $curriculumVersionId,
                );
                $graphRevisionId = (string) Str::ulid();

                DB::table('curriculum_graph_revisions')->insert([
                    'id' => $graphRevisionId,
                    'organization_id' => $context->organizationId,
                    'curriculum_version_id' => $curriculumVersionId,
                    'parent_revision_id' => null,
                    'parent_revision_number' => null,
                    'revision_number' => 1,
                    'graph' => CanonicalJson::encode($projection['graph']),
                    'graph_hash' => $projection['graph_hash'],
                    'authored_by_membership_id' => $actorMembershipId,
                    'author_attribution' => self::CLONE_ATTRIBUTION,
                    'authored_at' => $occurredAt,
                    'created_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ]);
                DB::table('curriculum_graph_heads')->insert([
                    'organization_id' => $context->organizationId,
                    'curriculum_version_id' => $curriculumVersionId,
                    'curriculum_graph_revision_id' => $graphRevisionId,
                    'graph_hash' => $projection['graph_hash'],
                    'created_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ]);
                DB::table('curriculum_clones')->insert([
                    'id' => $cloneId,
                    'organization_id' => $context->organizationId,
                    'source_owner_organization_id' => $source
                        ->ownerOrganizationId,
                    'source_curriculum_version_id' => $source
                        ->curriculumVersionId,
                    'source_curriculum_publication_id' => $source
                        ->publicationId,
                    'source_share_grant_id' => $shareGrantId,
                    'cloned_curriculum_version_id' => $curriculumVersionId,
                    'cloned_by_membership_id' => $actorMembershipId,
                    'cloned_at' => $occurredAt,
                    'created_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ]);
                $this->recordCommand(
                    actor: $actor,
                    actorMembershipId: $actorMembershipId,
                    organizationId: $context->organizationId,
                    shareGrantId: $shareGrantId,
                    idempotencyKey: $idempotencyKey,
                    fingerprint: $fingerprint,
                    cloneId: $cloneId,
                    occurredAt: $occurredAt,
                );
                $this->audit->recordTenant(
                    'curriculum.clone.created',
                    'curriculum_clone',
                    $cloneId,
                    $eventCorrelationId,
                );
                $this->outbox->recordTenant(
                    'curriculum.clone.created',
                    'curriculum_clone',
                    $cloneId,
                    [
                        'curriculum_clone_id' => $cloneId,
                        'curriculum_owner_organization_id' => $source
                            ->ownerOrganizationId,
                        'curriculum_version_id' => $curriculumVersionId,
                        'organization_id' => $context->organizationId,
                        'program_id' => $programId,
                        'source_publication_id' => $source->publicationId,
                    ],
                    $eventCorrelationId,
                );

                return new CurriculumCloneResult(
                    curriculumCloneId: $cloneId,
                    recipientOrganizationId: $context->organizationId,
                    sourceOwnerOrganizationId: $source
                        ->ownerOrganizationId,
                    sourcePublicationId: $source->publicationId,
                    sourceShareGrantId: $shareGrantId,
                    programId: $programId,
                    curriculumVersionId: $curriculumVersionId,
                    replayed: false,
                );
            }, 3);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23505') {
                throw new CurriculumOperationRejected;
            }

            throw $exception;
        }
    }

    /**
     * @param  list<mixed>  $content
     * @return array<string, string>
     */
    private function cloneContent(
        array $content,
        string $sourceOwnerOrganizationId,
        string $sourceCurriculumVersionId,
        string $recipientOrganizationId,
        string $curriculumVersionId,
        string $actorMembershipId,
        CarbonInterface $occurredAt,
    ): array {
        $documentIds = [];

        foreach ($content as $entry) {
            if (! is_array($entry) || array_is_list($entry)) {
                throw new CurriculumOperationRejected;
            }

            $document = $this->arrayObject($entry, 'document');
            $sourceReference = $this->arrayObject(
                $entry,
                'source_revision',
            );
            $media = $this->arrayList($entry, 'media');

            if ($media !== []) {
                throw new CurriculumOperationRejected;
            }

            $sourceDocumentId = $this->arrayUlid($document, 'id');
            $sourceRevisionId = $this->arrayUlid(
                $sourceReference,
                'id',
            );

            if (isset($documentIds[$sourceDocumentId])) {
                throw new CurriculumOperationRejected;
            }

            $sourceDocument = DB::table('content_documents')
                ->where('organization_id', $sourceOwnerOrganizationId)
                ->where(
                    'curriculum_version_id',
                    $sourceCurriculumVersionId,
                )
                ->where('id', $sourceDocumentId)
                ->first(['key']);
            $sourceRevision = DB::table('content_revisions')
                ->where('organization_id', $sourceOwnerOrganizationId)
                ->where(
                    'curriculum_version_id',
                    $sourceCurriculumVersionId,
                )
                ->where('content_document_id', $sourceDocumentId)
                ->where('id', $sourceRevisionId)
                ->where(
                    'content_hash',
                    $this->arraySha256(
                        $sourceReference,
                        'content_hash',
                    ),
                )
                ->first([
                    'source_locale',
                    'content_type',
                    'learner_visible',
                    'localization_requirement',
                    'pedagogically_authoritative',
                    'payload',
                    'content_hash',
                ]);

            if (
                ! $sourceDocument instanceof stdClass
                || ! $sourceRevision instanceof stdClass
                || $this->string($sourceDocument, 'key')
                    !== $this->arrayString($document, 'key')
                || $this->string($sourceRevision, 'content_type')
                    !== $this->arrayString($document, 'content_type')
                || $this->boolean($sourceRevision, 'learner_visible')
                    !== $this->arrayBoolean($document, 'learner_visible')
                || $this->string(
                    $sourceRevision,
                    'localization_requirement',
                ) !== $this->arrayString(
                    $document,
                    'localization_requirement',
                )
                || $this->boolean(
                    $sourceRevision,
                    'pedagogically_authoritative',
                ) !== $this->arrayBoolean(
                    $document,
                    'pedagogically_authoritative',
                )
            ) {
                throw new CurriculumOperationRejected;
            }

            $sourcePayload = $this->jsonObject(
                $sourceRevision,
                'payload',
            );
            $sourceHash = $this->sha256(
                $sourceRevision,
                'content_hash',
            );

            if (! hash_equals($sourceHash, CanonicalJson::sha256($sourcePayload))) {
                throw new CurriculumOperationRejected;
            }

            $newDocumentId = (string) Str::ulid();
            $newSourceRevisionId = (string) Str::ulid();
            $documentIds[$sourceDocumentId] = $newDocumentId;

            DB::table('content_documents')->insert([
                'id' => $newDocumentId,
                'organization_id' => $recipientOrganizationId,
                'curriculum_version_id' => $curriculumVersionId,
                'key' => $this->string($sourceDocument, 'key'),
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);
            DB::table('content_revisions')->insert([
                'id' => $newSourceRevisionId,
                'organization_id' => $recipientOrganizationId,
                'curriculum_version_id' => $curriculumVersionId,
                'content_document_id' => $newDocumentId,
                'parent_revision_id' => null,
                'revision_number' => 1,
                'source_locale' => $this->string(
                    $sourceRevision,
                    'source_locale',
                ),
                'content_type' => $this->string(
                    $sourceRevision,
                    'content_type',
                ),
                'learner_visible' => $this->boolean(
                    $sourceRevision,
                    'learner_visible',
                ),
                'localization_requirement' => $this->string(
                    $sourceRevision,
                    'localization_requirement',
                ),
                'pedagogically_authoritative' => $this->boolean(
                    $sourceRevision,
                    'pedagogically_authoritative',
                ),
                'payload' => CanonicalJson::encode($sourcePayload),
                'content_hash' => $sourceHash,
                'authored_by_membership_id' => $actorMembershipId,
                'author_attribution' => self::CLONE_ATTRIBUTION,
                'authored_at' => $occurredAt,
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);
            DB::table('content_revision_heads')->insert([
                'organization_id' => $recipientOrganizationId,
                'curriculum_version_id' => $curriculumVersionId,
                'content_document_id' => $newDocumentId,
                'content_revision_id' => $newSourceRevisionId,
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);

            $localized = $entry['localized_variant'] ?? null;

            if ($localized !== null) {
                if (! is_array($localized) || array_is_list($localized)) {
                    throw new CurriculumOperationRejected;
                }

                $sourceVariantId = $this->arrayUlid($localized, 'id');
                $sourceVariant = DB::table('localized_variants')
                    ->where('organization_id', $sourceOwnerOrganizationId)
                    ->where(
                        'curriculum_version_id',
                        $sourceCurriculumVersionId,
                    )
                    ->where('source_revision_id', $sourceRevisionId)
                    ->where('id', $sourceVariantId)
                    ->where('source_content_hash', $sourceHash)
                    ->where(
                        'content_hash',
                        $this->arraySha256(
                            $localized,
                            'content_hash',
                        ),
                    )
                    ->first([
                        'locale',
                        'payload',
                        'content_hash',
                    ]);

                if (! $sourceVariant instanceof stdClass) {
                    throw new CurriculumOperationRejected;
                }

                $localizedPayload = $this->jsonObject(
                    $sourceVariant,
                    'payload',
                );
                $localizedHash = $this->sha256(
                    $sourceVariant,
                    'content_hash',
                );

                if (
                    ! hash_equals(
                        $localizedHash,
                        CanonicalJson::sha256($localizedPayload),
                    )
                ) {
                    throw new CurriculumOperationRejected;
                }

                DB::table('localized_variants')->insert([
                    'id' => (string) Str::ulid(),
                    'organization_id' => $recipientOrganizationId,
                    'curriculum_version_id' => $curriculumVersionId,
                    'source_revision_id' => $newSourceRevisionId,
                    'parent_variant_id' => null,
                    'locale' => $this->string($sourceVariant, 'locale'),
                    'revision_number' => 1,
                    'source_content_hash' => $sourceHash,
                    'payload' => CanonicalJson::encode($localizedPayload),
                    'content_hash' => $localizedHash,
                    'translated_by_membership_id' => $actorMembershipId,
                    'translator_attribution' => self::CLONE_ATTRIBUTION,
                    'translated_at' => $occurredAt,
                    'created_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ]);
            }
        }

        return $documentIds;
    }

    /**
     * @param  array<string, mixed>  $sourceGraph
     * @param  array<string, string>  $documentIds
     */
    private function cloneGraph(
        array $sourceGraph,
        array $documentIds,
        string $recipientOrganizationId,
        string $curriculumVersionId,
        CarbonInterface $occurredAt,
    ): void {
        $objectives = $this->arrayList($sourceGraph, 'objectives');
        $prerequisites = $this->arrayList(
            $sourceGraph,
            'objective_prerequisites',
        );
        $modules = $this->arrayList($sourceGraph, 'modules');
        $lessons = $this->arrayList($sourceGraph, 'lessons');
        $lessonObjectives = $this->arrayList(
            $sourceGraph,
            'lesson_objectives',
        );
        $items = $this->arrayList($sourceGraph, 'learning_items');
        $missions = $this->arrayList($sourceGraph, 'missions');
        $missionItems = $this->arrayList($sourceGraph, 'mission_items');
        $objectiveIds = $this->newIdMap($objectives, 'id');
        $moduleIds = $this->newIdMap($modules, 'id');
        $lessonIds = $this->newIdMap($lessons, 'id');
        $itemIds = $this->newIdMap($items, 'id');
        $missionIds = $this->newIdMap($missions, 'id');

        foreach ($objectives as $objective) {
            $row = $this->record($objective);
            $sourceId = $this->arrayUlid($row, 'id');
            DB::table('learning_objectives')->insert([
                'id' => $this->mapped($objectiveIds, $sourceId),
                'organization_id' => $recipientOrganizationId,
                'curriculum_version_id' => $curriculumVersionId,
                'key' => $this->arrayString($row, 'key'),
                'outcome_code' => $this->arrayString(
                    $row,
                    'outcome_code',
                ),
                'sequence' => $this->arrayInteger($row, 'sequence'),
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);
        }

        foreach ($modules as $module) {
            $row = $this->record($module);
            $sourceId = $this->arrayUlid($row, 'id');
            DB::table('curriculum_modules')->insert([
                'id' => $this->mapped($moduleIds, $sourceId),
                'organization_id' => $recipientOrganizationId,
                'curriculum_version_id' => $curriculumVersionId,
                'key' => $this->arrayString($row, 'key'),
                'sequence' => $this->arrayInteger($row, 'sequence'),
                'estimated_minutes' => $this->arrayNullableInteger(
                    $row,
                    'estimated_minutes',
                ),
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);
        }

        foreach ($lessons as $lesson) {
            $row = $this->record($lesson);
            $sourceId = $this->arrayUlid($row, 'id');
            DB::table('lessons')->insert([
                'id' => $this->mapped($lessonIds, $sourceId),
                'organization_id' => $recipientOrganizationId,
                'curriculum_version_id' => $curriculumVersionId,
                'curriculum_module_id' => $this->mapped(
                    $moduleIds,
                    $this->arrayUlid($row, 'module_id'),
                ),
                'key' => $this->arrayString($row, 'key'),
                'sequence' => $this->arrayInteger($row, 'sequence'),
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);
        }

        foreach ($prerequisites as $prerequisite) {
            $row = $this->record($prerequisite);
            DB::table('objective_prerequisites')->insert([
                'organization_id' => $recipientOrganizationId,
                'curriculum_version_id' => $curriculumVersionId,
                'objective_id' => $this->mapped(
                    $objectiveIds,
                    $this->arrayUlid($row, 'objective_id'),
                ),
                'prerequisite_objective_id' => $this->mapped(
                    $objectiveIds,
                    $this->arrayUlid(
                        $row,
                        'prerequisite_objective_id',
                    ),
                ),
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);
        }

        foreach ($lessonObjectives as $lessonObjective) {
            $row = $this->record($lessonObjective);
            DB::table('lesson_objectives')->insert([
                'organization_id' => $recipientOrganizationId,
                'curriculum_version_id' => $curriculumVersionId,
                'lesson_id' => $this->mapped(
                    $lessonIds,
                    $this->arrayUlid($row, 'lesson_id'),
                ),
                'objective_id' => $this->mapped(
                    $objectiveIds,
                    $this->arrayUlid($row, 'objective_id'),
                ),
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);
        }

        foreach ($items as $item) {
            $row = $this->record($item);
            $sourceId = $this->arrayUlid($row, 'id');
            DB::table('learning_items')->insert([
                'id' => $this->mapped($itemIds, $sourceId),
                'organization_id' => $recipientOrganizationId,
                'curriculum_version_id' => $curriculumVersionId,
                'lesson_id' => $this->mapped(
                    $lessonIds,
                    $this->arrayUlid($row, 'lesson_id'),
                ),
                'content_document_id' => $this->mapped(
                    $documentIds,
                    $this->arrayUlid($row, 'content_document_id'),
                ),
                'key' => $this->arrayString($row, 'key'),
                'item_type' => $this->arrayString($row, 'item_type'),
                'sequence' => $this->arrayInteger($row, 'sequence'),
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);
        }

        foreach ($missions as $mission) {
            $row = $this->record($mission);
            $sourceId = $this->arrayUlid($row, 'id');
            DB::table('curriculum_missions')->insert([
                'id' => $this->mapped($missionIds, $sourceId),
                'organization_id' => $recipientOrganizationId,
                'curriculum_version_id' => $curriculumVersionId,
                'instruction_content_document_id' => $this->mapped(
                    $documentIds,
                    $this->arrayUlid(
                        $row,
                        'instruction_content_document_id',
                    ),
                ),
                'key' => $this->arrayString($row, 'key'),
                'sequence' => $this->arrayInteger($row, 'sequence'),
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);
        }

        foreach ($missionItems as $missionItem) {
            $row = $this->record($missionItem);
            DB::table('mission_items')->insert([
                'organization_id' => $recipientOrganizationId,
                'curriculum_version_id' => $curriculumVersionId,
                'mission_id' => $this->mapped(
                    $missionIds,
                    $this->arrayUlid($row, 'mission_id'),
                ),
                'learning_item_id' => $this->mapped(
                    $itemIds,
                    $this->arrayUlid($row, 'learning_item_id'),
                ),
                'sequence' => $this->arrayInteger($row, 'sequence'),
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);
        }
    }

    private function lockRecipientAuthority(
        string $organizationId,
        string $actorUserId,
        string $actorMembershipId,
    ): void {
        $membership = DB::table('memberships')
            ->join(
                'organizations',
                'organizations.id',
                '=',
                'memberships.organization_id',
            )
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->where('memberships.organization_id', $organizationId)
            ->where('memberships.id', $actorMembershipId)
            ->where('memberships.user_id', $actorUserId)
            ->where('memberships.status', 'active')
            ->where('organizations.status', 'active')
            ->where('users.status', 'active')
            ->lockForUpdate()
            ->first(['memberships.id']);

        if (! $membership instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }
    }

    private function lockRecipientGrant(
        string $organizationId,
        string $shareGrantId,
    ): void {
        $grant = DB::table('curriculum_share_grants')
            ->where('recipient_organization_id', $organizationId)
            ->where('id', $shareGrantId)
            ->lockForUpdate()
            ->first(['id']);

        if (! $grant instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }
    }

    private function replay(
        string $organizationId,
        string $shareGrantId,
        string $idempotencyKey,
        string $fingerprint,
    ): ?CurriculumCloneResult {
        $command = DB::table('publication_commands')
            ->where('organization_id', $organizationId)
            ->where('operation', self::OPERATION)
            ->where('aggregate_type', 'curriculum_share_grant')
            ->where('aggregate_id', $shareGrantId)
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first(['request_fingerprint', 'result_id']);

        if (! $command instanceof stdClass) {
            return null;
        }

        if (
            ! hash_equals(
                $this->string($command, 'request_fingerprint'),
                $fingerprint,
            )
        ) {
            throw new CurriculumIdempotencyConflict;
        }

        return $this->result(
            organizationId: $organizationId,
            cloneId: $this->string($command, 'result_id'),
            replayed: true,
        );
    }

    private function recordCommand(
        User $actor,
        string $actorMembershipId,
        string $organizationId,
        string $shareGrantId,
        string $idempotencyKey,
        string $fingerprint,
        string $cloneId,
        CarbonInterface $occurredAt,
    ): void {
        DB::table('publication_commands')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'operation' => self::OPERATION,
            'aggregate_type' => 'curriculum_share_grant',
            'aggregate_id' => $shareGrantId,
            'idempotency_key' => $idempotencyKey,
            'request_fingerprint' => $fingerprint,
            'result_type' => 'curriculum_clone',
            'result_id' => $cloneId,
            'actor_type' => 'owner_membership',
            'actor_membership_id' => $actorMembershipId,
            'actor_user_id' => $actor->getKey(),
            'completed_at' => $occurredAt,
            'created_at' => $occurredAt,
        ]);
    }

    private function result(
        string $organizationId,
        string $cloneId,
        bool $replayed,
    ): CurriculumCloneResult {
        $clone = DB::table('curriculum_clones')
            ->join('curriculum_versions', function ($join): void {
                $join
                    ->on(
                        'curriculum_versions.organization_id',
                        '=',
                        'curriculum_clones.organization_id',
                    )
                    ->on(
                        'curriculum_versions.id',
                        '=',
                        'curriculum_clones.cloned_curriculum_version_id',
                    );
            })
            ->where('curriculum_clones.organization_id', $organizationId)
            ->where('curriculum_clones.id', $cloneId)
            ->first([
                'curriculum_clones.organization_id',
                'curriculum_clones.source_owner_organization_id',
                'curriculum_clones.source_curriculum_publication_id',
                'curriculum_clones.source_share_grant_id',
                'curriculum_clones.cloned_curriculum_version_id',
                'curriculum_versions.program_id',
            ]);

        if (! $clone instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        return new CurriculumCloneResult(
            curriculumCloneId: $cloneId,
            recipientOrganizationId: $this->string(
                $clone,
                'organization_id',
            ),
            sourceOwnerOrganizationId: $this->string(
                $clone,
                'source_owner_organization_id',
            ),
            sourcePublicationId: $this->string(
                $clone,
                'source_curriculum_publication_id',
            ),
            sourceShareGrantId: $this->string(
                $clone,
                'source_share_grant_id',
            ),
            programId: $this->string($clone, 'program_id'),
            curriculumVersionId: $this->string(
                $clone,
                'cloned_curriculum_version_id',
            ),
            replayed: $replayed,
        );
    }

    private function assertInput(
        string $shareGrantId,
        string $programKey,
        string $curriculumVersionKey,
        string $idempotencyKey,
        ?string $correlationId,
    ): void {
        if (
            ! Str::isUlid($shareGrantId)
            || preg_match(
                '/\A[a-z0-9][a-z0-9._-]{0,99}\z/',
                $programKey,
            ) !== 1
            || preg_match(
                '/\A[a-z0-9][a-z0-9._-]{0,99}\z/',
                $curriculumVersionKey,
            ) !== 1
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

    /**
     * @param  list<mixed>  $records
     * @return array<string, string>
     */
    private function newIdMap(array $records, string $idField): array
    {
        $ids = [];

        foreach ($records as $record) {
            $row = $this->record($record);
            $sourceId = $this->arrayUlid($row, $idField);

            if (isset($ids[$sourceId])) {
                throw new CurriculumOperationRejected;
            }

            $ids[$sourceId] = (string) Str::ulid();
        }

        if ($ids === []) {
            throw new CurriculumOperationRejected;
        }

        return $ids;
    }

    /**
     * @param  array<string, string>  $ids
     */
    private function mapped(array $ids, string $sourceId): string
    {
        $mapped = $ids[$sourceId] ?? null;

        if (! is_string($mapped)) {
            throw new CurriculumOperationRejected;
        }

        return $mapped;
    }

    /**
     * @return array<string, mixed>
     */
    private function record(mixed $value): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function arrayObject(array $record, string $field): array
    {
        $value = $record[$field] ?? null;

        if (! is_array($value) || array_is_list($value)) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return list<mixed>
     */
    private function arrayList(array $record, string $field): array
    {
        $value = $record[$field] ?? null;

        if (! is_array($value) || ! array_is_list($value)) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function arrayString(array $record, string $field): string
    {
        $value = $record[$field] ?? null;

        if (! is_string($value) || $value === '') {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function arrayUlid(array $record, string $field): string
    {
        $value = $this->arrayString($record, $field);

        if (! Str::isUlid($value)) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function arraySha256(array $record, string $field): string
    {
        $value = $this->arrayString($record, $field);

        if (preg_match('/\A[a-f0-9]{64}\z/', $value) !== 1) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function arrayBoolean(array $record, string $field): bool
    {
        $value = $record[$field] ?? null;

        if (! is_bool($value)) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function arrayInteger(array $record, string $field): int
    {
        $value = $record[$field] ?? null;

        if (! is_int($value) || $value < 0) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function arrayNullableInteger(
        array $record,
        string $field,
    ): ?int {
        $value = $record[$field] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_int($value) || $value < 0) {
            throw new CurriculumOperationRejected;
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

    private function sha256(stdClass $row, string $field): string
    {
        $value = $this->string($row, $field);

        if (preg_match('/\A[a-f0-9]{64}\z/', $value) !== 1) {
            throw new CurriculumOperationRejected;
        }

        return $value;
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

    /**
     * @return array<string, mixed>
     */
    private function jsonObject(stdClass $row, string $field): array
    {
        $value = $row->{$field} ?? null;

        if (is_string($value)) {
            try {
                $value = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new CurriculumOperationRejected;
            }
        }

        if (! is_array($value) || array_is_list($value)) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }
}

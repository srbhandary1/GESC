<?php

declare(strict_types=1);

namespace App\Operator\ContentWorkspace;

use App\Audit\AuditEventRecorder;
use App\Curriculum\Authoring\ContentAuthoringCommands;
use App\Curriculum\CanonicalJson;
use App\Curriculum\CurriculumGraphProjector;
use App\Curriculum\Exceptions\CurriculumIdempotencyConflict;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Governance\CurriculumGraphGovernanceCommands;
use App\Models\User;
use App\Operator\ContentWorkspace\Exceptions\ContentWorkspaceConflict;
use App\Operator\ContentWorkspace\Exceptions\ContentWorkspaceRejected;
use App\Outbox\OutboxEventRecorder;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;

final readonly class ContentWorkspaceCommands
{
    private const string ATTRIBUTION = 'operator_content_studio';

    /** @var list<string> */
    private const array ABANDONMENT_REASONS = [
        'work_discontinued',
        'superseded',
        'created_in_error',
    ];

    public function __construct(
        private ContentWorkspaceAuthorization $authorization,
        private ContentWorkspaceIdempotency $idempotency,
        private ContentDraftGraphService $graphs,
        private GovernedContentPayloadPolicy $payloads,
        private ContentAuthoringCommands $authoring,
        private CurriculumGraphProjector $projector,
        private CurriculumGraphGovernanceCommands $graphGovernance,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function createProgram(
        User $actor,
        string $programKey,
        string $internalName,
        string $versionKey,
        int $expectedVersion,
        string $idempotencyKey,
    ): ContentWorkspaceCommandResult {
        $programKey = $this->key($programKey, 100);
        $versionKey = $this->key($versionKey, 100);
        $internalName = $this->text($internalName, 160);

        if ($expectedVersion !== 0) {
            throw new ContentWorkspaceConflict;
        }

        $organizationId = $this->authorization->context($actor)->organizationId;

        return $this->execute(
            actor: $actor,
            permission: 'curriculum.author',
            operation: 'content_workspace.program.create',
            targetType: 'organization',
            targetId: $organizationId,
            body: [
                'expected_version' => $expectedVersion,
                'internal_name' => $internalName,
                'program_key' => $programKey,
                'version_key' => $versionKey,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                ContentWorkspaceCommandClaim $claim,
                CarbonImmutable $now,
            ) use ($programKey, $internalName, $versionKey): array {
                $programId = (string) Str::ulid();
                $curriculumVersionId = (string) Str::ulid();
                $draftRevisionId = (string) Str::ulid();
                $blankGraph = $this->blankGraph(
                    $organizationId,
                    $curriculumVersionId,
                );

                DB::table('programs')->insert([
                    'id' => $programId,
                    'organization_id' => $organizationId,
                    'key' => $programKey,
                    'internal_name' => $internalName,
                    'descriptor_version' => 1,
                    'created_by_membership_id' => $actorMembershipId,
                    'created_by_attribution' => self::ATTRIBUTION,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('curriculum_versions')->insert([
                    'id' => $curriculumVersionId,
                    'organization_id' => $organizationId,
                    'program_id' => $programId,
                    'key' => $versionKey,
                    'created_by_membership_id' => $actorMembershipId,
                    'created_by_attribution' => self::ATTRIBUTION,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('curriculum_draft_graph_revisions')->insert([
                    'id' => $draftRevisionId,
                    'organization_id' => $organizationId,
                    'program_id' => $programId,
                    'curriculum_version_id' => $curriculumVersionId,
                    'parent_revision_id' => null,
                    'revision_number' => 1,
                    'graph' => CanonicalJson::encode($blankGraph),
                    'graph_hash' => CanonicalJson::sha256($blankGraph),
                    'blocker_codes' => CanonicalJson::encode([
                        'workspace_incomplete',
                    ]),
                    'is_complete' => false,
                    'authored_by_membership_id' => $actorMembershipId,
                    'authored_at' => $now,
                    'created_at' => $now,
                ]);
                DB::table('curriculum_workspaces')->insert([
                    'organization_id' => $organizationId,
                    'program_id' => $programId,
                    'curriculum_version_id' => $curriculumVersionId,
                    'source_curriculum_version_id' => null,
                    'source_curriculum_publication_id' => null,
                    'graph_schema_version' => 2,
                    'state' => 'draft',
                    'version' => 1,
                    'current_draft_revision_id' => $draftRevisionId,
                    'submitted_graph_revision_id' => null,
                    'legacy_level_assignment_required' => false,
                    'created_by_membership_id' => $actorMembershipId,
                    'sealed_at' => null,
                    'abandoned_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->workspaceEvent(
                    organizationId: $organizationId,
                    programId: $programId,
                    curriculumVersionId: $curriculumVersionId,
                    eventType: 'workspace.created',
                    workspaceVersion: 1,
                    actorMembershipId: $actorMembershipId,
                    commandId: $claim->id,
                    occurredAt: $now,
                    draftRevisionId: $draftRevisionId,
                );
                $this->recordEvidence(
                    'content_workspace.created',
                    'curriculum_workspace',
                    $curriculumVersionId,
                    $organizationId,
                    $programId,
                    $curriculumVersionId,
                );

                return [
                    'result_type' => 'curriculum_workspace',
                    'result_id' => $curriculumVersionId,
                    'data' => [
                        'program_id' => $programId,
                        'curriculum_version_id' => $curriculumVersionId,
                        'workspace_version' => 1,
                        'draft_revision_id' => $draftRevisionId,
                    ],
                ];
            },
        );
    }

    public function correctDescriptor(
        User $actor,
        string $programId,
        string $internalName,
        int $expectedDescriptorVersion,
        string $idempotencyKey,
    ): ContentWorkspaceCommandResult {
        $internalName = $this->text($internalName, 160);

        return $this->execute(
            actor: $actor,
            permission: 'curriculum.author',
            operation: 'content_workspace.program_descriptor.correct',
            targetType: 'program',
            targetId: $programId,
            body: [
                'expected_descriptor_version' => $expectedDescriptorVersion,
                'internal_name' => $internalName,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                ContentWorkspaceCommandClaim $claim,
                CarbonImmutable $now,
            ) use ($programId, $internalName, $expectedDescriptorVersion): array {
                $program = DB::table('programs')
                    ->where('organization_id', $organizationId)
                    ->where('id', $programId)
                    ->lockForUpdate()
                    ->first(['internal_name', 'descriptor_version']);

                if (! $program instanceof stdClass) {
                    throw new ContentWorkspaceRejected;
                }

                $previousName = $this->rowString($program, 'internal_name');

                if (
                    $this->rowInteger($program, 'descriptor_version')
                        !== $expectedDescriptorVersion
                    || hash_equals($previousName, $internalName)
                ) {
                    throw new ContentWorkspaceConflict;
                }

                $successorVersion = $expectedDescriptorVersion + 1;
                DB::table('programs')
                    ->where('organization_id', $organizationId)
                    ->where('id', $programId)
                    ->where('descriptor_version', $expectedDescriptorVersion)
                    ->update([
                        'internal_name' => $internalName,
                        'descriptor_version' => $successorVersion,
                        'updated_at' => $now,
                    ]);
                DB::table('program_descriptor_events')->insert([
                    'id' => (string) Str::ulid(),
                    'organization_id' => $organizationId,
                    'program_id' => $programId,
                    'previous_version' => $expectedDescriptorVersion,
                    'successor_version' => $successorVersion,
                    'previous_internal_name' => $previousName,
                    'successor_internal_name' => $internalName,
                    'actor_membership_id' => $actorMembershipId,
                    'command_id' => $claim->id,
                    'occurred_at' => $now,
                    'created_at' => $now,
                ]);
                $this->recordEvidence(
                    'content_workspace.program_descriptor_corrected',
                    'program',
                    $programId,
                    $organizationId,
                    $programId,
                    null,
                );

                return [
                    'result_type' => 'program',
                    'result_id' => $programId,
                    'data' => [
                        'program_id' => $programId,
                        'descriptor_version' => $successorVersion,
                    ],
                ];
            },
        );
    }

    public function createSuccessor(
        User $actor,
        string $sourceCurriculumVersionId,
        string $successorVersionKey,
        int $expectedSourceVersion,
        string $idempotencyKey,
    ): ContentWorkspaceCommandResult {
        $successorVersionKey = $this->key($successorVersionKey, 100);

        return $this->execute(
            actor: $actor,
            permission: 'curriculum.author',
            operation: 'content_workspace.successor.create',
            targetType: 'curriculum_version',
            targetId: $sourceCurriculumVersionId,
            body: [
                'expected_source_version' => $expectedSourceVersion,
                'successor_version_key' => $successorVersionKey,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                ContentWorkspaceCommandClaim $claim,
                CarbonImmutable $now,
            ) use (
                $sourceCurriculumVersionId,
                $successorVersionKey,
                $expectedSourceVersion,
            ): array {
                $sourceVersion = DB::table('curriculum_versions')
                    ->where('organization_id', $organizationId)
                    ->where('id', $sourceCurriculumVersionId)
                    ->lockForUpdate()
                    ->first(['program_id']);

                if (! $sourceVersion instanceof stdClass) {
                    throw new ContentWorkspaceRejected;
                }

                $programId = $this->rowString($sourceVersion, 'program_id');
                $program = DB::table('programs')
                    ->where('organization_id', $organizationId)
                    ->where('id', $programId)
                    ->lockForUpdate()
                    ->first(['id']);

                if (! $program instanceof stdClass) {
                    throw new ContentWorkspaceRejected;
                }

                $sourceWorkspace = DB::table('curriculum_workspaces')
                    ->where('organization_id', $organizationId)
                    ->where('program_id', $programId)
                    ->where('curriculum_version_id', $sourceCurriculumVersionId)
                    ->lockForUpdate()
                    ->first();
                $actualSourceVersion = $sourceWorkspace instanceof stdClass
                    ? $this->rowInteger($sourceWorkspace, 'version')
                    : 0;

                if ($actualSourceVersion !== $expectedSourceVersion) {
                    throw new ContentWorkspaceConflict;
                }

                $sourcePublicationId = DB::table('curriculum_publications')
                    ->where('organization_id', $organizationId)
                    ->where('program_id', $programId)
                    ->where('curriculum_version_id', $sourceCurriculumVersionId)
                    ->orderByDesc('publication_number')
                    ->lockForUpdate()
                    ->value('id');
                $sourcePublicationId = is_string($sourcePublicationId)
                    ? $sourcePublicationId
                    : null;
                $successorVersionId = (string) Str::ulid();

                DB::table('curriculum_versions')->insert([
                    'id' => $successorVersionId,
                    'organization_id' => $organizationId,
                    'program_id' => $programId,
                    'key' => $successorVersionKey,
                    'created_by_membership_id' => $actorMembershipId,
                    'created_by_attribution' => self::ATTRIBUTION,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $documentKeysBySourceId = $this->copyCurrentContent(
                    organizationId: $organizationId,
                    sourceCurriculumVersionId: $sourceCurriculumVersionId,
                    successorCurriculumVersionId: $successorVersionId,
                    copiedAt: $now,
                );
                $graphInput = $sourceWorkspace instanceof stdClass
                    ? $this->workspaceGraphInput(
                        $organizationId,
                        $sourceCurriculumVersionId,
                        $sourceWorkspace,
                    )
                    : $this->legacyGraphInput(
                        $organizationId,
                        $sourceCurriculumVersionId,
                        $documentKeysBySourceId,
                    );
                $projected = $this->graphs->replace(
                    $organizationId,
                    $successorVersionId,
                    $graphInput,
                    $now,
                );
                $draftRevisionId = (string) Str::ulid();
                DB::table('curriculum_draft_graph_revisions')->insert([
                    'id' => $draftRevisionId,
                    'organization_id' => $organizationId,
                    'program_id' => $programId,
                    'curriculum_version_id' => $successorVersionId,
                    'parent_revision_id' => null,
                    'revision_number' => 1,
                    'graph' => CanonicalJson::encode($projected['graph']),
                    'graph_hash' => $projected['graph_hash'],
                    'blocker_codes' => CanonicalJson::encode(
                        $projected['blocker_codes'],
                    ),
                    'is_complete' => $projected['is_complete'],
                    'authored_by_membership_id' => $actorMembershipId,
                    'authored_at' => $now,
                    'created_at' => $now,
                ]);
                DB::table('curriculum_workspaces')->insert([
                    'organization_id' => $organizationId,
                    'program_id' => $programId,
                    'curriculum_version_id' => $successorVersionId,
                    'source_curriculum_version_id' => $sourceCurriculumVersionId,
                    'source_curriculum_publication_id' => $sourcePublicationId,
                    'graph_schema_version' => 2,
                    'state' => 'draft',
                    'version' => 1,
                    'current_draft_revision_id' => $draftRevisionId,
                    'submitted_graph_revision_id' => null,
                    'legacy_level_assignment_required' => in_array(
                        'legacy_level_assignment_required',
                        $projected['blocker_codes'],
                        true,
                    ),
                    'created_by_membership_id' => $actorMembershipId,
                    'sealed_at' => null,
                    'abandoned_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->workspaceEvent(
                    $organizationId,
                    $programId,
                    $successorVersionId,
                    'workspace.successor_created',
                    1,
                    $actorMembershipId,
                    $claim->id,
                    $now,
                    draftRevisionId: $draftRevisionId,
                    reasonKey: $sourcePublicationId === null
                        ? 'source_version'
                        : 'source_publication',
                );
                $this->recordEvidence(
                    'content_workspace.successor_created',
                    'curriculum_workspace',
                    $successorVersionId,
                    $organizationId,
                    $programId,
                    $successorVersionId,
                );

                return [
                    'result_type' => 'curriculum_workspace',
                    'result_id' => $successorVersionId,
                    'data' => [
                        'program_id' => $programId,
                        'curriculum_version_id' => $successorVersionId,
                        'source_curriculum_version_id' => $sourceCurriculumVersionId,
                        'source_curriculum_publication_id' => $sourcePublicationId,
                        'workspace_version' => 1,
                        'draft_revision_id' => $draftRevisionId,
                        'blocker_codes' => $projected['blocker_codes'],
                    ],
                ];
            },
        );
    }

    /** @param array<string, mixed> $graph */
    public function saveGraph(
        User $actor,
        string $curriculumVersionId,
        int $expectedWorkspaceVersion,
        array $graph,
        string $idempotencyKey,
    ): ContentWorkspaceCommandResult {
        return $this->execute(
            actor: $actor,
            permission: 'curriculum.author',
            operation: 'content_workspace.graph.save',
            targetType: 'curriculum_workspace',
            targetId: $curriculumVersionId,
            body: [
                'expected_workspace_version' => $expectedWorkspaceVersion,
                'graph' => $graph,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                ContentWorkspaceCommandClaim $claim,
                CarbonImmutable $now,
            ) use ($curriculumVersionId, $expectedWorkspaceVersion, $graph): array {
                $workspace = $this->lockedWorkspace(
                    $organizationId,
                    $curriculumVersionId,
                );
                $state = $this->rowString($workspace, 'state');

                if (
                    $this->rowInteger($workspace, 'version')
                        !== $expectedWorkspaceVersion
                    || ! in_array($state, ['draft', 'in_review'], true)
                ) {
                    throw new ContentWorkspaceConflict(
                        $state === 'sealed' || $state === 'abandoned'
                            ? ContentWorkspaceConflict::STATE
                            : ContentWorkspaceConflict::STALE_VERSION,
                    );
                }

                $reopened = $state === 'in_review';

                if ($reopened && ! $this->submittedGraphMayReopen(
                    $organizationId,
                    $curriculumVersionId,
                    $workspace,
                )) {
                    throw new ContentWorkspaceConflict(
                        ContentWorkspaceConflict::STATE,
                    );
                }

                $projected = $this->graphs->replace(
                    $organizationId,
                    $curriculumVersionId,
                    $graph,
                    $now,
                );
                $parentRevisionId = $this->nullableRowString(
                    $workspace,
                    'current_draft_revision_id',
                );
                $revisionNumber = $parentRevisionId === null
                    ? 1
                    : (int) DB::table('curriculum_draft_graph_revisions')
                        ->where('organization_id', $organizationId)
                        ->where('curriculum_version_id', $curriculumVersionId)
                        ->where('id', $parentRevisionId)
                        ->value('revision_number') + 1;

                if (
                    DB::table('curriculum_draft_graph_revisions')
                        ->where('organization_id', $organizationId)
                        ->where('curriculum_version_id', $curriculumVersionId)
                        ->where('graph_hash', $projected['graph_hash'])
                        ->exists()
                ) {
                    throw new ContentWorkspaceConflict(
                        ContentWorkspaceConflict::STATE,
                    );
                }

                $draftRevisionId = (string) Str::ulid();
                $nextVersion = $expectedWorkspaceVersion + 1;
                DB::table('curriculum_draft_graph_revisions')->insert([
                    'id' => $draftRevisionId,
                    'organization_id' => $organizationId,
                    'program_id' => $this->rowString($workspace, 'program_id'),
                    'curriculum_version_id' => $curriculumVersionId,
                    'parent_revision_id' => $parentRevisionId,
                    'revision_number' => $revisionNumber,
                    'graph' => CanonicalJson::encode($projected['graph']),
                    'graph_hash' => $projected['graph_hash'],
                    'blocker_codes' => CanonicalJson::encode(
                        $projected['blocker_codes'],
                    ),
                    'is_complete' => $projected['is_complete'],
                    'authored_by_membership_id' => $actorMembershipId,
                    'authored_at' => $now,
                    'created_at' => $now,
                ]);
                $updated = DB::table('curriculum_workspaces')
                    ->where('organization_id', $organizationId)
                    ->where('curriculum_version_id', $curriculumVersionId)
                    ->where('version', $expectedWorkspaceVersion)
                    ->update([
                        'state' => 'draft',
                        'version' => $nextVersion,
                        'current_draft_revision_id' => $draftRevisionId,
                        'submitted_graph_revision_id' => null,
                        'legacy_level_assignment_required' => in_array(
                            'legacy_level_assignment_required',
                            $projected['blocker_codes'],
                            true,
                        ),
                        'updated_at' => $now,
                    ]);

                if ($updated !== 1) {
                    throw new ContentWorkspaceConflict;
                }

                $programId = $this->rowString($workspace, 'program_id');

                if ($reopened) {
                    $this->workspaceEvent(
                        $organizationId,
                        $programId,
                        $curriculumVersionId,
                        'workspace.reopened',
                        $nextVersion,
                        $actorMembershipId,
                        $claim->id,
                        $now,
                        draftRevisionId: $draftRevisionId,
                        reasonKey: 'review_revision_required',
                    );
                }

                $this->workspaceEvent(
                    $organizationId,
                    $programId,
                    $curriculumVersionId,
                    'workspace.graph_saved',
                    $nextVersion,
                    $actorMembershipId,
                    $claim->id,
                    $now,
                    draftRevisionId: $draftRevisionId,
                );
                $this->recordEvidence(
                    'content_workspace.graph_saved',
                    'curriculum_draft_graph_revision',
                    $draftRevisionId,
                    $organizationId,
                    $programId,
                    $curriculumVersionId,
                );

                return [
                    'result_type' => 'curriculum_draft_graph_revision',
                    'result_id' => $draftRevisionId,
                    'data' => [
                        'curriculum_version_id' => $curriculumVersionId,
                        'workspace_version' => $nextVersion,
                        'draft_revision_id' => $draftRevisionId,
                        'draft_revision_number' => $revisionNumber,
                        'graph_hash' => $projected['graph_hash'],
                        'blocker_codes' => $projected['blocker_codes'],
                        'is_complete' => $projected['is_complete'],
                    ],
                ];
            },
        );
    }

    public function submitGraphReview(
        User $actor,
        string $curriculumVersionId,
        int $expectedWorkspaceVersion,
        string $idempotencyKey,
    ): ContentWorkspaceCommandResult {
        return $this->execute(
            actor: $actor,
            permission: 'curriculum.author',
            operation: 'content_workspace.graph.submit_review',
            targetType: 'curriculum_workspace',
            targetId: $curriculumVersionId,
            body: ['expected_workspace_version' => $expectedWorkspaceVersion],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                ContentWorkspaceCommandClaim $claim,
                CarbonImmutable $now,
            ) use ($actor, $curriculumVersionId, $expectedWorkspaceVersion, $idempotencyKey): array {
                $workspace = $this->lockedWorkspace(
                    $organizationId,
                    $curriculumVersionId,
                );
                $draftRevisionId = $this->nullableRowString(
                    $workspace,
                    'current_draft_revision_id',
                );

                if (
                    $this->rowInteger($workspace, 'version')
                        !== $expectedWorkspaceVersion
                    || $this->rowString($workspace, 'state') !== 'draft'
                    || $draftRevisionId === null
                ) {
                    throw new ContentWorkspaceConflict;
                }

                $draft = DB::table('curriculum_draft_graph_revisions')
                    ->where('organization_id', $organizationId)
                    ->where('curriculum_version_id', $curriculumVersionId)
                    ->where('id', $draftRevisionId)
                    ->lockForUpdate()
                    ->first(['is_complete', 'blocker_codes']);

                if (
                    ! $draft instanceof stdClass
                    || ! $this->rowBoolean($draft, 'is_complete')
                    || $this->jsonList($draft, 'blocker_codes') !== []
                ) {
                    throw new ContentWorkspaceConflict(
                        ContentWorkspaceConflict::STATE,
                    );
                }

                $projected = $this->projector->project(
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
                    ->lockForUpdate()
                    ->first(['curriculum_graph_revisions.revision_number']);
                $expectedGraphRevision = $head instanceof stdClass
                    ? $this->rowInteger($head, 'revision_number')
                    : 0;

                try {
                    $graphRevisionId = $this->graphGovernance->snapshot(
                        actor: $actor,
                        curriculumVersionId: $curriculumVersionId,
                        expectedRevisionNumber: $expectedGraphRevision,
                        expectedGraphHash: $projected['graph_hash'],
                        idempotencyKey: $idempotencyKey,
                        authorAttribution: self::ATTRIBUTION,
                    );
                } catch (CurriculumIdempotencyConflict $exception) {
                    throw new ContentWorkspaceConflict(
                        ContentWorkspaceConflict::IDEMPOTENCY,
                        $exception,
                    );
                } catch (CurriculumOperationRejected $exception) {
                    throw new ContentWorkspaceConflict(
                        ContentWorkspaceConflict::STATE,
                        $exception,
                    );
                }

                $nextVersion = $expectedWorkspaceVersion + 1;
                $updated = DB::table('curriculum_workspaces')
                    ->where('organization_id', $organizationId)
                    ->where('curriculum_version_id', $curriculumVersionId)
                    ->where('version', $expectedWorkspaceVersion)
                    ->where('state', 'draft')
                    ->update([
                        'state' => 'in_review',
                        'version' => $nextVersion,
                        'submitted_graph_revision_id' => $graphRevisionId,
                        'updated_at' => $now,
                    ]);

                if ($updated !== 1) {
                    throw new ContentWorkspaceConflict;
                }

                $programId = $this->rowString($workspace, 'program_id');
                $this->workspaceEvent(
                    $organizationId,
                    $programId,
                    $curriculumVersionId,
                    'workspace.review_submitted',
                    $nextVersion,
                    $actorMembershipId,
                    $claim->id,
                    $now,
                    draftRevisionId: $draftRevisionId,
                    graphRevisionId: $graphRevisionId,
                );
                $this->recordEvidence(
                    'content_workspace.review_submitted',
                    'curriculum_graph_revision',
                    $graphRevisionId,
                    $organizationId,
                    $programId,
                    $curriculumVersionId,
                );

                return [
                    'result_type' => 'curriculum_graph_revision',
                    'result_id' => $graphRevisionId,
                    'data' => [
                        'curriculum_version_id' => $curriculumVersionId,
                        'workspace_version' => $nextVersion,
                        'graph_revision_id' => $graphRevisionId,
                        'graph_hash' => $projected['graph_hash'],
                        'state' => 'in_review',
                    ],
                ];
            },
        );
    }

    public function abandon(
        User $actor,
        string $curriculumVersionId,
        int $expectedWorkspaceVersion,
        string $reasonKey,
        string $idempotencyKey,
    ): ContentWorkspaceCommandResult {
        if (! in_array($reasonKey, self::ABANDONMENT_REASONS, true)) {
            $this->invalid('reason_key', 'The abandonment reason is invalid.');
        }

        return $this->execute(
            actor: $actor,
            permission: 'curriculum.author',
            operation: 'content_workspace.abandon',
            targetType: 'curriculum_workspace',
            targetId: $curriculumVersionId,
            body: [
                'expected_workspace_version' => $expectedWorkspaceVersion,
                'reason_key' => $reasonKey,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                ContentWorkspaceCommandClaim $claim,
                CarbonImmutable $now,
            ) use ($curriculumVersionId, $expectedWorkspaceVersion, $reasonKey): array {
                $workspace = $this->lockedWorkspace(
                    $organizationId,
                    $curriculumVersionId,
                );
                $state = $this->rowString($workspace, 'state');

                if (
                    $this->rowInteger($workspace, 'version')
                        !== $expectedWorkspaceVersion
                    || ! in_array($state, ['draft', 'in_review'], true)
                ) {
                    throw new ContentWorkspaceConflict(
                        in_array($state, ['draft', 'in_review'], true)
                            ? ContentWorkspaceConflict::STALE_VERSION
                            : ContentWorkspaceConflict::STATE,
                    );
                }

                $nextVersion = $expectedWorkspaceVersion + 1;
                DB::table('curriculum_workspaces')
                    ->where('organization_id', $organizationId)
                    ->where('curriculum_version_id', $curriculumVersionId)
                    ->where('version', $expectedWorkspaceVersion)
                    ->update([
                        'state' => 'abandoned',
                        'version' => $nextVersion,
                        'abandoned_at' => $now,
                        'updated_at' => $now,
                    ]);
                $programId = $this->rowString($workspace, 'program_id');
                $this->workspaceEvent(
                    $organizationId,
                    $programId,
                    $curriculumVersionId,
                    'workspace.abandoned',
                    $nextVersion,
                    $actorMembershipId,
                    $claim->id,
                    $now,
                    draftRevisionId: $this->nullableRowString(
                        $workspace,
                        'current_draft_revision_id',
                    ),
                    graphRevisionId: $this->nullableRowString(
                        $workspace,
                        'submitted_graph_revision_id',
                    ),
                    reasonKey: $reasonKey,
                );
                $this->recordEvidence(
                    'content_workspace.abandoned',
                    'curriculum_workspace',
                    $curriculumVersionId,
                    $organizationId,
                    $programId,
                    $curriculumVersionId,
                );

                return [
                    'result_type' => 'curriculum_workspace',
                    'result_id' => $curriculumVersionId,
                    'data' => [
                        'curriculum_version_id' => $curriculumVersionId,
                        'workspace_version' => $nextVersion,
                        'state' => 'abandoned',
                    ],
                ];
            },
        );
    }

    /** @param array<string, mixed> $payload */
    public function createDocument(
        User $actor,
        string $curriculumVersionId,
        string $documentKey,
        string $contentType,
        bool $learnerVisible,
        array $payload,
        int $expectedWorkspaceVersion,
        string $idempotencyKey,
    ): ContentWorkspaceCommandResult {
        $documentKey = $this->key($documentKey, 120);
        $governed = $this->payloads->source(
            $contentType,
            $learnerVisible,
            $payload,
        );

        return $this->execute(
            actor: $actor,
            permission: 'curriculum.author',
            operation: 'content_workspace.document.create',
            targetType: 'curriculum_workspace',
            targetId: $curriculumVersionId,
            body: [
                'content_type' => $governed['content_type'],
                'document_key' => $documentKey,
                'expected_workspace_version' => $expectedWorkspaceVersion,
                'learner_visible' => $governed['learner_visible'],
                'payload' => $governed['payload'],
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                ContentWorkspaceCommandClaim $claim,
                CarbonImmutable $now,
            ) use (
                $actor,
                $curriculumVersionId,
                $documentKey,
                $governed,
                $expectedWorkspaceVersion,
                $idempotencyKey,
            ): array {
                $workspace = $this->lockedWorkspace(
                    $organizationId,
                    $curriculumVersionId,
                );
                $state = $this->rowString($workspace, 'state');

                if (
                    $this->rowInteger($workspace, 'version')
                        !== $expectedWorkspaceVersion
                    || $state !== 'draft'
                ) {
                    throw new ContentWorkspaceConflict(
                        $state === 'draft'
                            ? ContentWorkspaceConflict::STALE_VERSION
                            : ContentWorkspaceConflict::STATE,
                    );
                }

                $documentId = (string) Str::ulid();
                DB::table('content_documents')->insert([
                    'id' => $documentId,
                    'organization_id' => $organizationId,
                    'curriculum_version_id' => $curriculumVersionId,
                    'key' => $documentKey,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                try {
                    $revision = $this->authoring->appendEnglishSourceRevision(
                        actor: $actor,
                        curriculumVersionId: $curriculumVersionId,
                        contentDocumentId: $documentId,
                        expectedHeadRevisionId: null,
                        expectedRevisionNumber: 0,
                        contentType: $governed['content_type'],
                        learnerVisible: $governed['learner_visible'],
                        localizationRequirement: $governed['localization_requirement'],
                        payload: $governed['payload'],
                        idempotencyKey: $idempotencyKey,
                        authorAttribution: self::ATTRIBUTION,
                    );
                } catch (CurriculumIdempotencyConflict $exception) {
                    throw new ContentWorkspaceConflict(
                        ContentWorkspaceConflict::IDEMPOTENCY,
                        $exception,
                    );
                } catch (CurriculumOperationRejected $exception) {
                    throw new ContentWorkspaceConflict(
                        ContentWorkspaceConflict::STATE,
                        $exception,
                    );
                }

                $nextVersion = $expectedWorkspaceVersion + 1;
                DB::table('curriculum_workspaces')
                    ->where('organization_id', $organizationId)
                    ->where('curriculum_version_id', $curriculumVersionId)
                    ->where('version', $expectedWorkspaceVersion)
                    ->update([
                        'version' => $nextVersion,
                        'updated_at' => $now,
                    ]);
                $programId = $this->rowString($workspace, 'program_id');
                $this->workspaceEvent(
                    $organizationId,
                    $programId,
                    $curriculumVersionId,
                    'content.document_created',
                    $nextVersion,
                    $actorMembershipId,
                    $claim->id,
                    $now,
                    draftRevisionId: $this->nullableRowString(
                        $workspace,
                        'current_draft_revision_id',
                    ),
                );

                return [
                    'result_type' => 'content_document',
                    'result_id' => $documentId,
                    'data' => [
                        'content_document_id' => $documentId,
                        'document_key' => $documentKey,
                        'source_revision_id' => $revision->contentRevisionId,
                        'source_revision_number' => $revision->revisionNumber,
                        'source_content_hash' => $revision->contentHash,
                        'workspace_version' => $nextVersion,
                    ],
                ];
            },
        );
    }

    /** @param array<string, mixed> $payload */
    public function appendEnglish(
        User $actor,
        string $contentDocumentId,
        ?string $expectedHeadRevisionId,
        int $expectedRevisionNumber,
        string $contentType,
        bool $learnerVisible,
        array $payload,
        string $idempotencyKey,
    ): ContentWorkspaceCommandResult {
        $this->authorization->assert($actor, 'curriculum.author');
        $context = $this->authorization->context($actor);
        $document = DB::table('content_documents')
            ->join('curriculum_workspaces', function ($join): void {
                $join
                    ->on(
                        'curriculum_workspaces.organization_id',
                        '=',
                        'content_documents.organization_id',
                    )
                    ->on(
                        'curriculum_workspaces.curriculum_version_id',
                        '=',
                        'content_documents.curriculum_version_id',
                    );
            })
            ->where('content_documents.organization_id', $context->organizationId)
            ->where('content_documents.id', $contentDocumentId)
            ->first([
                'content_documents.curriculum_version_id',
                'curriculum_workspaces.state',
            ]);

        if (! $document instanceof stdClass) {
            throw new ContentWorkspaceRejected;
        }

        if ($this->rowString($document, 'state') !== 'draft') {
            throw new ContentWorkspaceConflict(ContentWorkspaceConflict::STATE);
        }

        $governed = $this->payloads->source(
            $contentType,
            $learnerVisible,
            $payload,
        );
        $curriculumVersionId = $this->rowString(
            $document,
            'curriculum_version_id',
        );

        try {
            $result = DB::transaction(function () use (
                $actor,
                $context,
                $contentDocumentId,
                $curriculumVersionId,
                $expectedHeadRevisionId,
                $expectedRevisionNumber,
                $governed,
                $idempotencyKey,
            ) {
                $workspace = $this->lockedWorkspace(
                    $context->organizationId,
                    $curriculumVersionId,
                );

                if ($this->rowString($workspace, 'state') !== 'draft') {
                    throw new ContentWorkspaceConflict(
                        ContentWorkspaceConflict::STATE,
                    );
                }

                return $this->authoring->appendEnglishSourceRevision(
                    actor: $actor,
                    curriculumVersionId: $curriculumVersionId,
                    contentDocumentId: $contentDocumentId,
                    expectedHeadRevisionId: $expectedHeadRevisionId,
                    expectedRevisionNumber: $expectedRevisionNumber,
                    contentType: $governed['content_type'],
                    learnerVisible: $governed['learner_visible'],
                    localizationRequirement: $governed['localization_requirement'],
                    payload: $governed['payload'],
                    idempotencyKey: $idempotencyKey,
                    authorAttribution: self::ATTRIBUTION,
                );
            }, 3);
        } catch (CurriculumIdempotencyConflict $exception) {
            throw new ContentWorkspaceConflict(
                ContentWorkspaceConflict::IDEMPOTENCY,
                $exception,
            );
        } catch (CurriculumOperationRejected $exception) {
            throw new ContentWorkspaceConflict(
                ContentWorkspaceConflict::HEAD,
                $exception,
            );
        }

        return new ContentWorkspaceCommandResult(
            resultType: 'content_revision',
            resultId: $result->contentRevisionId,
            replayed: $result->replayed,
            data: [
                'content_document_id' => $contentDocumentId,
                'source_revision_id' => $result->contentRevisionId,
                'source_revision_number' => $result->revisionNumber,
                'source_content_hash' => $result->contentHash,
            ],
        );
    }

    /** @param array<string, mixed> $payload */
    public function appendKannada(
        User $actor,
        string $sourceRevisionId,
        string $sourceContentHash,
        int $expectedSourceRevisionNumber,
        ?string $expectedHeadVariantId,
        int $expectedVariantRevisionNumber,
        array $payload,
        string $idempotencyKey,
    ): ContentWorkspaceCommandResult {
        $this->authorization->assert($actor, 'curriculum.translate.kn');
        $context = $this->authorization->context($actor);
        $source = DB::table('content_revisions')
            ->join('curriculum_workspaces', function ($join): void {
                $join
                    ->on(
                        'curriculum_workspaces.organization_id',
                        '=',
                        'content_revisions.organization_id',
                    )
                    ->on(
                        'curriculum_workspaces.curriculum_version_id',
                        '=',
                        'content_revisions.curriculum_version_id',
                    );
            })
            ->where('content_revisions.organization_id', $context->organizationId)
            ->where('content_revisions.id', $sourceRevisionId)
            ->where('content_revisions.content_hash', $sourceContentHash)
            ->first([
                'content_revisions.curriculum_version_id',
                'content_revisions.payload',
                'curriculum_workspaces.state',
            ]);

        if (! $source instanceof stdClass) {
            throw new ContentWorkspaceRejected;
        }

        if ($this->rowString($source, 'state') !== 'draft') {
            throw new ContentWorkspaceConflict(ContentWorkspaceConflict::STATE);
        }

        $governedPayload = $this->payloads->kannada(
            $this->jsonObject($source, 'payload'),
            $payload,
        );
        $curriculumVersionId = $this->rowString(
            $source,
            'curriculum_version_id',
        );

        try {
            $result = DB::transaction(function () use (
                $actor,
                $context,
                $curriculumVersionId,
                $sourceRevisionId,
                $sourceContentHash,
                $expectedSourceRevisionNumber,
                $expectedHeadVariantId,
                $expectedVariantRevisionNumber,
                $governedPayload,
                $idempotencyKey,
            ) {
                $workspace = $this->lockedWorkspace(
                    $context->organizationId,
                    $curriculumVersionId,
                );

                if ($this->rowString($workspace, 'state') !== 'draft') {
                    throw new ContentWorkspaceConflict(
                        ContentWorkspaceConflict::STATE,
                    );
                }

                return $this->authoring->appendKannadaVariant(
                    actor: $actor,
                    curriculumVersionId: $curriculumVersionId,
                    sourceRevisionId: $sourceRevisionId,
                    sourceContentHash: $sourceContentHash,
                    expectedSourceRevisionNumber: $expectedSourceRevisionNumber,
                    expectedHeadVariantId: $expectedHeadVariantId,
                    expectedVariantRevisionNumber: $expectedVariantRevisionNumber,
                    payload: $governedPayload,
                    idempotencyKey: $idempotencyKey,
                    translatorAttribution: self::ATTRIBUTION,
                );
            }, 3);
        } catch (CurriculumIdempotencyConflict $exception) {
            throw new ContentWorkspaceConflict(
                ContentWorkspaceConflict::IDEMPOTENCY,
                $exception,
            );
        } catch (CurriculumOperationRejected $exception) {
            throw new ContentWorkspaceConflict(
                ContentWorkspaceConflict::HEAD,
                $exception,
            );
        }

        return new ContentWorkspaceCommandResult(
            resultType: 'localized_variant',
            resultId: $result->localizedVariantId,
            replayed: $result->replayed,
            data: [
                'source_revision_id' => $sourceRevisionId,
                'source_content_hash' => $sourceContentHash,
                'localized_variant_id' => $result->localizedVariantId,
                'variant_revision_number' => $result->revisionNumber,
                'localized_content_hash' => $result->contentHash,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  Closure(string, string, ContentWorkspaceCommandClaim, CarbonImmutable): array{result_type: string, result_id: string, data: array<string, mixed>}  $command
     */
    private function execute(
        User $actor,
        string $permission,
        string $operation,
        string $targetType,
        string $targetId,
        array $body,
        string $idempotencyKey,
        Closure $command,
    ): ContentWorkspaceCommandResult {
        if (! Str::isUlid($targetId)) {
            throw new ContentWorkspaceRejected;
        }

        $this->authorization->assert($actor, $permission);
        $context = $this->authorization->context($actor);
        $fingerprint = $this->idempotency->fingerprint(
            $operation,
            $targetType,
            $targetId,
            $body,
        );

        try {
            return DB::transaction(function () use (
                $actor,
                $permission,
                $operation,
                $targetType,
                $targetId,
                $idempotencyKey,
                $fingerprint,
                $context,
                $command,
            ): ContentWorkspaceCommandResult {
                $organization = DB::table('organizations')
                    ->where('id', $context->organizationId)
                    ->lockForUpdate()
                    ->first(['id']);

                if (! $organization instanceof stdClass) {
                    throw new ContentWorkspaceRejected;
                }

                $membership = $this->authorization->actorMembership($actor);
                $this->authorization->assert($actor, $permission);
                $actorMembershipId = $this->rowString($membership, 'id');
                $claim = $this->idempotency->claim(
                    $actor,
                    $idempotencyKey,
                    $operation,
                    $targetType,
                    $targetId,
                    $fingerprint,
                );

                if ($claim->replayed) {
                    return new ContentWorkspaceCommandResult(
                        resultType: $claim->resultType
                            ?? throw new ContentWorkspaceConflict(
                                ContentWorkspaceConflict::IDEMPOTENCY,
                            ),
                        resultId: $claim->resultId
                            ?? throw new ContentWorkspaceConflict(
                                ContentWorkspaceConflict::IDEMPOTENCY,
                            ),
                        replayed: true,
                        data: $this->replayData(
                            $context->organizationId,
                            $claim->resultType,
                            $claim->resultId,
                            $claim->id,
                        ),
                    );
                }

                $now = Date::now()->toImmutable();
                $result = $command(
                    $context->organizationId,
                    $actorMembershipId,
                    $claim,
                    $now,
                );
                $this->idempotency->complete(
                    $claim,
                    $result['result_type'],
                    $result['result_id'],
                    $now,
                );

                return new ContentWorkspaceCommandResult(
                    resultType: $result['result_type'],
                    resultId: $result['result_id'],
                    replayed: false,
                    data: $result['data'],
                );
            }, 3);
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), [
                '23000',
                '23505',
                '40001',
                '40P01',
            ], true)) {
                throw new ContentWorkspaceConflict(
                    ContentWorkspaceConflict::CONCURRENT_CHANGE,
                    $exception,
                );
            }

            throw $exception;
        }
    }

    private function lockedWorkspace(
        string $organizationId,
        string $curriculumVersionId,
    ): stdClass {
        $version = DB::table('curriculum_versions')
            ->where('organization_id', $organizationId)
            ->where('id', $curriculumVersionId)
            ->lockForUpdate()
            ->first(['program_id']);

        if (! $version instanceof stdClass) {
            throw new ContentWorkspaceRejected;
        }

        $programId = $this->rowString($version, 'program_id');
        $program = DB::table('programs')
            ->where('organization_id', $organizationId)
            ->where('id', $programId)
            ->lockForUpdate()
            ->first(['id']);

        if (! $program instanceof stdClass) {
            throw new ContentWorkspaceRejected;
        }

        $workspace = DB::table('curriculum_workspaces')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->lockForUpdate()
            ->first();

        if (! $workspace instanceof stdClass) {
            throw new ContentWorkspaceRejected;
        }

        return $workspace;
    }

    private function submittedGraphMayReopen(
        string $organizationId,
        string $curriculumVersionId,
        stdClass $workspace,
    ): bool {
        $graphRevisionId = $this->nullableRowString(
            $workspace,
            'submitted_graph_revision_id',
        );

        if ($graphRevisionId === null) {
            return false;
        }

        $decision = DB::table('curriculum_graph_reviews')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->where('curriculum_graph_revision_id', $graphRevisionId)
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->value('decision');

        return is_string($decision)
            && in_array($decision, ['rejected', 'withdrawn'], true);
    }

    /** @return array<string, mixed> */
    private function replayData(
        string $organizationId,
        ?string $resultType,
        ?string $resultId,
        string $commandId,
    ): array {
        if ($resultType === null || $resultId === null) {
            throw new ContentWorkspaceConflict(
                ContentWorkspaceConflict::IDEMPOTENCY,
            );
        }

        if ($resultType === 'program') {
            $program = DB::table('programs')
                ->join('program_descriptor_events', function ($join): void {
                    $join
                        ->on(
                            'program_descriptor_events.organization_id',
                            '=',
                            'programs.organization_id',
                        )
                        ->on(
                            'program_descriptor_events.program_id',
                            '=',
                            'programs.id',
                        );
                })
                ->where('programs.organization_id', $organizationId)
                ->where('programs.id', $resultId)
                ->where('program_descriptor_events.command_id', $commandId)
                ->first(['program_descriptor_events.successor_version']);

            if (! $program instanceof stdClass) {
                throw new ContentWorkspaceConflict;
            }

            return [
                'program_id' => $resultId,
                'descriptor_version' => $this->rowInteger(
                    $program,
                    'successor_version',
                ),
            ];
        }

        if ($resultType === 'content_document') {
            $document = DB::table('content_documents')
                ->join('content_revisions', function ($join): void {
                    $join
                        ->on(
                            'content_revisions.organization_id',
                            '=',
                            'content_documents.organization_id',
                        )
                        ->on(
                            'content_revisions.curriculum_version_id',
                            '=',
                            'content_documents.curriculum_version_id',
                        )
                        ->on(
                            'content_revisions.content_document_id',
                            '=',
                            'content_documents.id',
                        );
                })
                ->join('curriculum_workspace_events', function ($join): void {
                    $join
                        ->on(
                            'curriculum_workspace_events.organization_id',
                            '=',
                            'content_documents.organization_id',
                        )
                        ->on(
                            'curriculum_workspace_events.curriculum_version_id',
                            '=',
                            'content_documents.curriculum_version_id',
                        );
                })
                ->where('content_documents.organization_id', $organizationId)
                ->where('content_documents.id', $resultId)
                ->where('content_revisions.revision_number', 1)
                ->where('curriculum_workspace_events.command_id', $commandId)
                ->first([
                    'content_documents.key',
                    'content_revisions.id as source_revision_id',
                    'content_revisions.revision_number',
                    'content_revisions.content_hash',
                    'curriculum_workspace_events.workspace_version',
                ]);

            if (! $document instanceof stdClass) {
                throw new ContentWorkspaceConflict;
            }

            return [
                'content_document_id' => $resultId,
                'document_key' => $this->rowString($document, 'key'),
                'source_revision_id' => $this->rowString(
                    $document,
                    'source_revision_id',
                ),
                'source_revision_number' => $this->rowInteger(
                    $document,
                    'revision_number',
                ),
                'source_content_hash' => $this->rowString(
                    $document,
                    'content_hash',
                ),
                'workspace_version' => $this->rowInteger(
                    $document,
                    'workspace_version',
                ),
            ];
        }

        $curriculumVersionId = match ($resultType) {
            'curriculum_workspace' => $resultId,
            'curriculum_draft_graph_revision' => DB::table(
                'curriculum_draft_graph_revisions',
            )
                ->where('organization_id', $organizationId)
                ->where('id', $resultId)
                ->value('curriculum_version_id'),
            'curriculum_graph_revision' => DB::table(
                'curriculum_graph_revisions',
            )
                ->where('organization_id', $organizationId)
                ->where('id', $resultId)
                ->value('curriculum_version_id'),
            default => null,
        };

        if (! is_string($curriculumVersionId)) {
            throw new ContentWorkspaceConflict;
        }

        $workspace = DB::table('curriculum_workspaces')
            ->join('curriculum_workspace_events', function ($join): void {
                $join
                    ->on(
                        'curriculum_workspace_events.organization_id',
                        '=',
                        'curriculum_workspaces.organization_id',
                    )
                    ->on(
                        'curriculum_workspace_events.curriculum_version_id',
                        '=',
                        'curriculum_workspaces.curriculum_version_id',
                    );
            })
            ->where('curriculum_workspaces.organization_id', $organizationId)
            ->where(
                'curriculum_workspaces.curriculum_version_id',
                $curriculumVersionId,
            )
            ->where('curriculum_workspace_events.command_id', $commandId)
            ->first([
                'curriculum_workspaces.program_id',
                'curriculum_workspaces.curriculum_version_id',
                'curriculum_workspace_events.event_type',
                'curriculum_workspace_events.workspace_version',
                'curriculum_workspace_events.draft_revision_id',
                'curriculum_workspace_events.graph_revision_id',
            ]);

        if (! $workspace instanceof stdClass) {
            throw new ContentWorkspaceConflict;
        }

        $draftRevisionId = $resultType === 'curriculum_draft_graph_revision'
            ? $resultId
            : $this->nullableRowString($workspace, 'draft_revision_id');
        $graphRevisionId = $resultType === 'curriculum_graph_revision'
            ? $resultId
            : $this->nullableRowString($workspace, 'graph_revision_id');
        $draft = $draftRevisionId === null
            ? null
            : DB::table('curriculum_draft_graph_revisions')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->where('id', $draftRevisionId)
                ->first(['graph_hash', 'blocker_codes', 'is_complete']);
        $graphHash = $graphRevisionId === null
            ? null
            : DB::table('curriculum_graph_revisions')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->where('id', $graphRevisionId)
                ->value('graph_hash');

        return [
            'program_id' => $this->rowString($workspace, 'program_id'),
            'curriculum_version_id' => $this->rowString(
                $workspace,
                'curriculum_version_id',
            ),
            'state' => match ($this->rowString($workspace, 'event_type')) {
                'workspace.review_submitted' => 'in_review',
                'workspace.sealed' => 'sealed',
                'workspace.abandoned' => 'abandoned',
                default => 'draft',
            },
            'workspace_version' => $this->rowInteger(
                $workspace,
                'workspace_version',
            ),
            'draft_revision_id' => $draftRevisionId,
            'graph_revision_id' => $graphRevisionId,
            'graph_hash' => is_string($graphHash)
                ? $graphHash
                : ($draft instanceof stdClass
                    ? $this->rowString($draft, 'graph_hash')
                    : null),
            'blocker_codes' => $draft instanceof stdClass
                ? $this->jsonList($draft, 'blocker_codes')
                : [],
            'is_complete' => $draft instanceof stdClass
                ? $this->rowBoolean($draft, 'is_complete')
                : null,
        ];
    }

    private function workspaceEvent(
        string $organizationId,
        string $programId,
        string $curriculumVersionId,
        string $eventType,
        int $workspaceVersion,
        string $actorMembershipId,
        string $commandId,
        CarbonImmutable $occurredAt,
        ?string $draftRevisionId = null,
        ?string $graphRevisionId = null,
        ?string $reasonKey = null,
    ): void {
        DB::table('curriculum_workspace_events')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'curriculum_version_id' => $curriculumVersionId,
            'event_type' => $eventType,
            'workspace_version' => $workspaceVersion,
            'draft_revision_id' => $draftRevisionId,
            'graph_revision_id' => $graphRevisionId,
            'actor_membership_id' => $actorMembershipId,
            'command_id' => $commandId,
            'reason_key' => $reasonKey,
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
        ]);
    }

    /**
     * Copy each current document head, its latest exact-source Kannada variant
     * and same-owner media links. Review/publication authority is not copied.
     *
     * @return array<string, string> source document id => immutable key
     */
    private function copyCurrentContent(
        string $organizationId,
        string $sourceCurriculumVersionId,
        string $successorCurriculumVersionId,
        CarbonImmutable $copiedAt,
    ): array {
        $documents = DB::table('content_documents')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $sourceCurriculumVersionId)
            ->orderBy('key')
            ->orderBy('id')
            ->get(['id', 'key']);
        $documentKeys = [];

        foreach ($documents as $document) {
            $sourceDocumentId = (string) $document->id;
            $documentKey = (string) $document->key;
            $head = DB::table('content_revision_heads')
                ->join('content_revisions', function ($join): void {
                    $join
                        ->on(
                            'content_revisions.organization_id',
                            '=',
                            'content_revision_heads.organization_id',
                        )
                        ->on(
                            'content_revisions.curriculum_version_id',
                            '=',
                            'content_revision_heads.curriculum_version_id',
                        )
                        ->on(
                            'content_revisions.content_document_id',
                            '=',
                            'content_revision_heads.content_document_id',
                        )
                        ->on(
                            'content_revisions.id',
                            '=',
                            'content_revision_heads.content_revision_id',
                        );
                })
                ->where('content_revision_heads.organization_id', $organizationId)
                ->where(
                    'content_revision_heads.curriculum_version_id',
                    $sourceCurriculumVersionId,
                )
                ->where('content_revision_heads.content_document_id', $sourceDocumentId)
                ->first(['content_revisions.*']);

            if (! $head instanceof stdClass) {
                continue;
            }

            $successorDocumentId = (string) Str::ulid();
            $successorRevisionId = (string) Str::ulid();
            DB::table('content_documents')->insert([
                'id' => $successorDocumentId,
                'organization_id' => $organizationId,
                'curriculum_version_id' => $successorCurriculumVersionId,
                'key' => $documentKey,
                'created_at' => $copiedAt,
                'updated_at' => $copiedAt,
            ]);
            DB::table('content_revisions')->insert([
                'id' => $successorRevisionId,
                'organization_id' => $organizationId,
                'curriculum_version_id' => $successorCurriculumVersionId,
                'content_document_id' => $successorDocumentId,
                'parent_revision_id' => null,
                'revision_number' => 1,
                'source_locale' => $this->rowString($head, 'source_locale'),
                'content_type' => $this->rowString($head, 'content_type'),
                'learner_visible' => $this->rowBoolean($head, 'learner_visible'),
                'localization_requirement' => $this->rowString(
                    $head,
                    'localization_requirement',
                ),
                'pedagogically_authoritative' => $this->rowBoolean(
                    $head,
                    'pedagogically_authoritative',
                ),
                'payload' => CanonicalJson::encode(
                    $this->jsonObject($head, 'payload'),
                ),
                'content_hash' => $this->rowString($head, 'content_hash'),
                'authored_by_membership_id' => $this->nullableRowString(
                    $head,
                    'authored_by_membership_id',
                ),
                'author_attribution' => $this->rowString(
                    $head,
                    'author_attribution',
                ),
                'authored_at' => $head->authored_at,
                'created_at' => $copiedAt,
                'updated_at' => $copiedAt,
            ]);
            DB::table('content_revision_heads')->insert([
                'organization_id' => $organizationId,
                'curriculum_version_id' => $successorCurriculumVersionId,
                'content_document_id' => $successorDocumentId,
                'content_revision_id' => $successorRevisionId,
                'created_at' => $copiedAt,
                'updated_at' => $copiedAt,
            ]);

            $variant = DB::table('localized_variants')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $sourceCurriculumVersionId)
                ->where('source_revision_id', $this->rowString($head, 'id'))
                ->where('locale', 'kn-IN')
                ->orderByDesc('revision_number')
                ->orderByDesc('id')
                ->first();

            if ($variant instanceof stdClass) {
                DB::table('localized_variants')->insert([
                    'id' => (string) Str::ulid(),
                    'organization_id' => $organizationId,
                    'curriculum_version_id' => $successorCurriculumVersionId,
                    'source_revision_id' => $successorRevisionId,
                    'parent_variant_id' => null,
                    'locale' => 'kn-IN',
                    'revision_number' => 1,
                    'source_content_hash' => $this->rowString(
                        $head,
                        'content_hash',
                    ),
                    'payload' => CanonicalJson::encode(
                        $this->jsonObject($variant, 'payload'),
                    ),
                    'content_hash' => $this->rowString($variant, 'content_hash'),
                    'translated_by_membership_id' => $this->rowString(
                        $variant,
                        'translated_by_membership_id',
                    ),
                    'translator_attribution' => $this->rowString(
                        $variant,
                        'translator_attribution',
                    ),
                    'translated_at' => $variant->translated_at,
                    'created_at' => $copiedAt,
                    'updated_at' => $copiedAt,
                ]);
            }

            $mediaLinks = DB::table('content_revision_media_assets')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $sourceCurriculumVersionId)
                ->where('content_revision_id', $this->rowString($head, 'id'))
                ->orderBy('usage_key')
                ->orderBy('sequence')
                ->get(['media_asset_id', 'usage_key', 'sequence']);

            foreach ($mediaLinks as $mediaLink) {
                DB::table('content_revision_media_assets')->insert([
                    'organization_id' => $organizationId,
                    'curriculum_version_id' => $successorCurriculumVersionId,
                    'content_revision_id' => $successorRevisionId,
                    'media_asset_id' => (string) $mediaLink->media_asset_id,
                    'usage_key' => (string) $mediaLink->usage_key,
                    'sequence' => (int) $mediaLink->sequence,
                    'created_at' => $copiedAt,
                    'updated_at' => $copiedAt,
                ]);
            }

            $documentKeys[$sourceDocumentId] = $documentKey;
        }

        return $documentKeys;
    }

    /** @return array<string, mixed> */
    private function workspaceGraphInput(
        string $organizationId,
        string $curriculumVersionId,
        stdClass $workspace,
    ): array {
        $draftRevisionId = $this->nullableRowString(
            $workspace,
            'current_draft_revision_id',
        );

        if ($draftRevisionId === null) {
            return [
                'schema' => ContentDraftGraphService::DRAFT_SCHEMA,
                'levels' => [],
                'objectives' => [],
                'modules' => [],
                'lessons' => [],
                'items' => [],
                'delivery_bundles' => [],
            ];
        }

        $row = DB::table('curriculum_draft_graph_revisions')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->where('id', $draftRevisionId)
            ->first(['graph']);

        if (! $row instanceof stdClass) {
            throw new ContentWorkspaceRejected;
        }

        $graph = $this->jsonObject($row, 'graph');

        return [
            'schema' => ContentDraftGraphService::DRAFT_SCHEMA,
            'levels' => array_map(
                static fn (array $item): array => [
                    'key' => $item['key'],
                    'sequence' => $item['sequence'],
                ],
                $this->arrayList($graph, 'levels'),
            ),
            'objectives' => array_map(
                static fn (array $item): array => [
                    'key' => $item['key'],
                    'outcome_code' => $item['outcome_code'],
                    'sequence' => $item['sequence'],
                    'prerequisite_keys' => $item['prerequisite_keys'],
                ],
                $this->arrayList($graph, 'objectives'),
            ),
            'modules' => array_map(
                static fn (array $item): array => [
                    'key' => $item['key'],
                    'level_key' => $item['level_key'],
                    'sequence' => $item['sequence'],
                    'estimated_minutes' => $item['estimated_minutes'],
                ],
                $this->arrayList($graph, 'modules'),
            ),
            'lessons' => array_map(
                static fn (array $item): array => [
                    'key' => $item['key'],
                    'module_key' => $item['module_key'],
                    'sequence' => $item['sequence'],
                    'objective_keys' => $item['objective_keys'],
                ],
                $this->arrayList($graph, 'lessons'),
            ),
            'items' => array_map(
                static fn (array $item): array => [
                    'key' => $item['key'],
                    'lesson_key' => $item['lesson_key'],
                    'content_document_key' => $item['content_document_key'],
                    'item_type' => $item['item_type'],
                    'sequence' => $item['sequence'],
                ],
                $this->arrayList($graph, 'items'),
            ),
            'delivery_bundles' => array_map(
                static fn (array $item): array => [
                    'key' => $item['key'],
                    'instruction_document_key' => $item['instruction_document_key'],
                    'sequence' => $item['sequence'],
                    'item_keys' => $item['item_keys'],
                ],
                $this->arrayList($graph, 'delivery_bundles'),
            ),
        ];
    }

    /**
     * Convert a graph-v1 relational projection into a graph-v2 draft with one
     * explicit non-publishable legacy assignment level.
     *
     * @param  array<string, string>  $documentKeysBySourceId
     * @return array<string, mixed>
     */
    private function legacyGraphInput(
        string $organizationId,
        string $curriculumVersionId,
        array $documentKeysBySourceId,
    ): array {
        $objectives = DB::table('learning_objectives')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->whereNull('retired_at')
            ->orderBy('sequence')
            ->orderBy('key')
            ->get(['id', 'key', 'outcome_code', 'sequence']);
        $modules = DB::table('curriculum_modules')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->whereNull('retired_at')
            ->orderBy('sequence')
            ->orderBy('key')
            ->get(['id', 'key', 'sequence', 'estimated_minutes']);
        $lessons = DB::table('lessons')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->whereNull('retired_at')
            ->orderBy('curriculum_module_id')
            ->orderBy('sequence')
            ->get(['id', 'curriculum_module_id', 'key', 'sequence']);
        $items = DB::table('learning_items')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->whereNull('retired_at')
            ->orderBy('lesson_id')
            ->orderBy('sequence')
            ->get([
                'id',
                'lesson_id',
                'content_document_id',
                'key',
                'item_type',
                'sequence',
            ]);
        $missions = DB::table('curriculum_missions')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->whereNull('retired_at')
            ->orderBy('sequence')
            ->get([
                'id',
                'instruction_content_document_id',
                'key',
                'sequence',
            ]);
        $objectiveKeyById = $this->keyMap($objectives);
        $moduleKeyById = $this->keyMap($modules);
        $lessonKeyById = $this->keyMap($lessons);
        $itemKeyById = $this->keyMap($items);
        $prerequisites = DB::table('objective_prerequisites')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->get(['objective_id', 'prerequisite_objective_id'])
            ->groupBy('objective_id');
        $lessonObjectives = DB::table('lesson_objectives')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->get(['lesson_id', 'objective_id'])
            ->groupBy('lesson_id');
        $missionItems = DB::table('mission_items')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->orderBy('sequence')
            ->get(['mission_id', 'learning_item_id'])
            ->groupBy('mission_id');

        return [
            'schema' => ContentDraftGraphService::DRAFT_SCHEMA,
            'levels' => $modules->isEmpty() ? [] : [[
                'key' => 'legacy-unassigned',
                'sequence' => 1,
            ]],
            'objectives' => array_values($objectives->map(
                fn (object $row): array => [
                    'key' => (string) $row->key,
                    'outcome_code' => (string) $row->outcome_code,
                    'sequence' => (int) $row->sequence,
                    'prerequisite_keys' => array_values(
                        ($prerequisites[(string) $row->id] ?? collect())
                            ->map(fn (object $edge): string => $objectiveKeyById[
                                (string) $edge->prerequisite_objective_id
                            ])
                            ->all(),
                    ),
                ],
            )->all()),
            'modules' => array_values($modules->map(
                static fn (object $row): array => [
                    'key' => (string) $row->key,
                    'level_key' => 'legacy-unassigned',
                    'sequence' => (int) $row->sequence,
                    'estimated_minutes' => $row->estimated_minutes === null
                        ? null
                        : (int) $row->estimated_minutes,
                ],
            )->all()),
            'lessons' => array_values($lessons->map(
                fn (object $row): array => [
                    'key' => (string) $row->key,
                    'module_key' => $moduleKeyById[
                        (string) $row->curriculum_module_id
                    ],
                    'sequence' => (int) $row->sequence,
                    'objective_keys' => array_values(
                        ($lessonObjectives[(string) $row->id] ?? collect())
                            ->map(fn (object $edge): string => $objectiveKeyById[
                                (string) $edge->objective_id
                            ])
                            ->all(),
                    ),
                ],
            )->all()),
            'items' => array_values($items->map(
                fn (object $row): array => [
                    'key' => (string) $row->key,
                    'lesson_key' => $lessonKeyById[(string) $row->lesson_id],
                    'content_document_key' => $documentKeysBySourceId[
                        (string) $row->content_document_id
                    ] ?? throw new ContentWorkspaceRejected,
                    'item_type' => (string) $row->item_type,
                    'sequence' => (int) $row->sequence,
                ],
            )->all()),
            'delivery_bundles' => array_values($missions->map(
                fn (object $row): array => [
                    'key' => (string) $row->key,
                    'instruction_document_key' => $documentKeysBySourceId[
                        (string) $row->instruction_content_document_id
                    ] ?? throw new ContentWorkspaceRejected,
                    'sequence' => (int) $row->sequence,
                    'item_keys' => array_values(
                        ($missionItems[(string) $row->id] ?? collect())
                            ->map(fn (object $edge): string => $itemKeyById[
                                (string) $edge->learning_item_id
                            ])
                            ->all(),
                    ),
                ],
            )->all()),
        ];
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return array<string, string>
     */
    private function keyMap(Collection $rows): array
    {
        $map = [];

        foreach ($rows as $row) {
            $map[(string) $row->id] = (string) $row->key;
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $object
     * @return list<array<string, mixed>>
     */
    private function arrayList(array $object, string $field): array
    {
        $value = $object[$field] ?? null;

        if (! is_array($value) || ! array_is_list($value)) {
            throw new ContentWorkspaceRejected;
        }

        foreach ($value as $item) {
            if (! is_array($item) || array_is_list($item)) {
                throw new ContentWorkspaceRejected;
            }
        }

        return $value;
    }

    private function recordEvidence(
        string $eventType,
        string $aggregateType,
        string $aggregateId,
        string $organizationId,
        string $programId,
        ?string $curriculumVersionId,
    ): void {
        $this->audit->recordTenant($eventType, $aggregateType, $aggregateId);
        $this->outbox->recordTenant(
            $eventType,
            $aggregateType,
            $aggregateId,
            array_filter([
                'organization_id' => $organizationId,
                'program_id' => $programId,
                'curriculum_version_id' => $curriculumVersionId,
            ], static fn (mixed $value): bool => is_string($value)),
        );
    }

    /** @return array<string, mixed> */
    private function blankGraph(
        string $organizationId,
        string $curriculumVersionId,
    ): array {
        return [
            'schema' => ContentDraftGraphService::DRAFT_SCHEMA,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $curriculumVersionId,
            'levels' => [],
            'objectives' => [],
            'modules' => [],
            'lessons' => [],
            'items' => [],
            'delivery_bundles' => [],
        ];
    }

    private function key(string $value, int $maximum): string
    {
        if (
            strlen($value) > $maximum
            || preg_match('/\A[a-z0-9][a-z0-9._-]*\z/', $value) !== 1
        ) {
            $this->invalid('key', 'The key format is invalid.');
        }

        return $value;
    }

    private function text(string $value, int $maximum): string
    {
        if (
            $value === ''
            || mb_strlen($value) > $maximum
            || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1
        ) {
            $this->invalid('internal_name', 'The internal descriptor is invalid.');
        }

        return $value;
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }

    private function rowString(stdClass $row, string $field): string
    {
        return $this->nullableRowString($row, $field)
            ?? throw new ContentWorkspaceRejected;
    }

    private function nullableRowString(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function rowInteger(stdClass $row, string $field): int
    {
        $value = $row->{$field} ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new ContentWorkspaceRejected;
    }

    private function rowBoolean(stdClass $row, string $field): bool
    {
        return $this->nullableRowBoolean($row, $field)
            ?? throw new ContentWorkspaceRejected;
    }

    private function nullableRowBoolean(stdClass $row, string $field): ?bool
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

        return null;
    }

    /** @return list<string> */
    private function jsonList(stdClass $row, string $field): array
    {
        $value = $row->{$field} ?? null;

        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw new ContentWorkspaceRejected;
        }

        $result = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw new ContentWorkspaceRejected;
            }

            $result[] = $item;
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function jsonObject(stdClass $row, string $field): array
    {
        $value = $row->{$field} ?? null;

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value) || array_is_list($value)) {
            throw new ContentWorkspaceRejected;
        }

        return $value;
    }
}

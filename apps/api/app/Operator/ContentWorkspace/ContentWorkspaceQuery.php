<?php

declare(strict_types=1);

namespace App\Operator\ContentWorkspace;

use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Publication\PublicationManifestBuilder;
use App\Models\User;
use App\Operator\ContentWorkspace\Exceptions\ContentWorkspaceRejected;
use App\Operator\Exceptions\InvalidOperatorCursor;
use App\Operator\OperatorCursorCodec;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class ContentWorkspaceQuery
{
    private const int MAX_PAGE_SIZE = 25;

    public function __construct(
        private ContentWorkspaceAuthorization $authorization,
        private OperatorCursorCodec $cursors,
        private PublicationManifestBuilder $manifests,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null, allowed_actions: list<string>}
     */
    public function workspaces(
        User $actor,
        int $limit,
        ?string $cursor,
    ): array {
        $this->authorization->assertRead($actor);
        $organizationId = $this->authorization->context($actor)->organizationId;
        $limit = $this->limit($limit);
        $query = DB::table('curriculum_workspaces')
            ->join('curriculum_versions', function ($join): void {
                $join
                    ->on(
                        'curriculum_versions.organization_id',
                        '=',
                        'curriculum_workspaces.organization_id',
                    )
                    ->on(
                        'curriculum_versions.id',
                        '=',
                        'curriculum_workspaces.curriculum_version_id',
                    );
            })
            ->join('programs', function ($join): void {
                $join
                    ->on(
                        'programs.organization_id',
                        '=',
                        'curriculum_workspaces.organization_id',
                    )
                    ->on(
                        'programs.id',
                        '=',
                        'curriculum_workspaces.program_id',
                    );
            })
            ->leftJoin('curriculum_draft_graph_revisions', function ($join): void {
                $join
                    ->on(
                        'curriculum_draft_graph_revisions.organization_id',
                        '=',
                        'curriculum_workspaces.organization_id',
                    )
                    ->on(
                        'curriculum_draft_graph_revisions.id',
                        '=',
                        'curriculum_workspaces.current_draft_revision_id',
                    );
            })
            ->leftJoin('publication_heads', function ($join): void {
                $join
                    ->on(
                        'publication_heads.organization_id',
                        '=',
                        'curriculum_workspaces.organization_id',
                    )
                    ->on(
                        'publication_heads.program_id',
                        '=',
                        'curriculum_workspaces.program_id',
                    );
            })
            ->where('curriculum_workspaces.organization_id', $organizationId);
        $this->applyTimestampCursor(
            $query,
            $cursor,
            'content-workspaces',
            $organizationId,
            'curriculum_workspaces.updated_at',
            'curriculum_workspaces.curriculum_version_id',
        );
        $rows = $query
            ->orderByDesc('curriculum_workspaces.updated_at')
            ->orderByDesc('curriculum_workspaces.curriculum_version_id')
            ->limit($limit + 1)
            ->get([
                'curriculum_workspaces.program_id',
                'curriculum_workspaces.curriculum_version_id',
                'curriculum_workspaces.source_curriculum_version_id',
                'curriculum_workspaces.source_curriculum_publication_id',
                'curriculum_workspaces.state',
                'curriculum_workspaces.version as workspace_version',
                'curriculum_workspaces.current_draft_revision_id',
                'curriculum_workspaces.submitted_graph_revision_id',
                'curriculum_workspaces.updated_at',
                'curriculum_versions.key as version_key',
                'programs.key as program_key',
                'programs.internal_name as program_internal_name',
                'programs.descriptor_version',
                'curriculum_draft_graph_revisions.graph_hash',
                'curriculum_draft_graph_revisions.blocker_codes',
                'curriculum_draft_graph_revisions.is_complete',
                DB::raw(
                    'COALESCE(publication_heads.lock_version, 0) as publication_head_version',
                ),
                'publication_heads.active_publication_id',
                DB::raw(
                    'COALESCE(publication_heads.is_suspended, false) as publication_suspended',
                ),
            ]);
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit)->values();
        $data = [];

        foreach ($rows as $row) {
            $data[] = $this->workspaceSummary($actor, $row);
        }

        $last = $rows->last();

        return [
            'data' => $data,
            'next_cursor' => $hasMore && $last instanceof stdClass
                ? $this->cursors->encode(
                    'content-workspaces',
                    $organizationId,
                    [
                        'updated_at' => $this->timestamp(
                            $last->updated_at ?? null,
                        ),
                        'id' => $this->string(
                            $last,
                            'curriculum_version_id',
                        ),
                    ],
                )
                : null,
            'allowed_actions' => $this->authorization->allows(
                $actor,
                'curriculum.author',
            ) ? ['create_program'] : [],
        ];
    }

    /** @return array{data: array<string, mixed>} */
    public function workspace(User $actor, string $curriculumVersionId): array
    {
        $this->authorization->assertRead($actor);
        $organizationId = $this->authorization->context($actor)->organizationId;
        $row = $this->workspaceRow($organizationId, $curriculumVersionId);
        $draftRevisionId = $this->nullableString(
            $row,
            'current_draft_revision_id',
        );
        $graph = null;
        $blockers = [];

        if ($draftRevisionId !== null) {
            $draft = DB::table('curriculum_draft_graph_revisions')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->where('id', $draftRevisionId)
                ->first([
                    'revision_number',
                    'graph',
                    'graph_hash',
                    'blocker_codes',
                    'is_complete',
                    'authored_by_membership_id',
                    'authored_at',
                ]);

            if (! $draft instanceof stdClass) {
                throw new ContentWorkspaceRejected;
            }

            $graph = [
                'revision_id' => $draftRevisionId,
                'revision_number' => $this->integer($draft, 'revision_number'),
                'graph_hash' => $this->string($draft, 'graph_hash'),
                'is_complete' => $this->boolean($draft, 'is_complete'),
                'blocker_codes' => $this->jsonList($draft, 'blocker_codes'),
                'authored_by_membership_id' => $this->string(
                    $draft,
                    'authored_by_membership_id',
                ),
                'authored_at' => $this->timestamp($draft->authored_at ?? null),
                'value' => $this->jsonObject($draft, 'graph'),
            ];
            $blockers = $graph['blocker_codes'];
        }

        $readiness = $this->readiness(
            $organizationId,
            $curriculumVersionId,
            $row,
        );
        $documents = $this->documentSummaries(
            $organizationId,
            $curriculumVersionId,
        );
        $actions = $this->workspaceActions(
            $actor,
            $row,
            $blockers,
            $readiness,
        );

        return ['data' => [
            ...$this->workspaceIdentity($row),
            'graph' => $graph,
            'documents' => $documents['data'],
            'document_count' => $documents['count'],
            'documents_truncated' => $documents['count'] > count($documents['data']),
            'readiness' => [
                'blocker_codes' => $readiness,
                'ready' => $readiness === [],
            ],
            'appointment_choices' => [
                'english' => $this->appointmentChoices(
                    $actor,
                    $this->string($row, 'program_id'),
                    'english_academic_reviewer',
                ),
                'kannada' => $this->appointmentChoices(
                    $actor,
                    $this->string($row, 'program_id'),
                    'kannada_reviewer',
                ),
            ],
            'allowed_actions' => $actions,
        ]];
    }

    /** @return array{data: array<string, mixed>} */
    public function preview(
        User $actor,
        string $curriculumVersionId,
        string $documentKey,
    ): array {
        $this->authorization->assertRead($actor);
        $organizationId = $this->authorization->context($actor)->organizationId;
        $this->workspaceRow($organizationId, $curriculumVersionId);
        $head = DB::table('content_documents')
            ->join('content_revision_heads', function ($join): void {
                $join
                    ->on(
                        'content_revision_heads.organization_id',
                        '=',
                        'content_documents.organization_id',
                    )
                    ->on(
                        'content_revision_heads.curriculum_version_id',
                        '=',
                        'content_documents.curriculum_version_id',
                    )
                    ->on(
                        'content_revision_heads.content_document_id',
                        '=',
                        'content_documents.id',
                    );
            })
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
            ->where('content_documents.organization_id', $organizationId)
            ->where('content_documents.curriculum_version_id', $curriculumVersionId)
            ->where('content_documents.key', $documentKey)
            ->first([
                'content_documents.id as content_document_id',
                'content_documents.key as document_key',
                'content_revisions.id as source_revision_id',
                'content_revisions.revision_number',
                'content_revisions.content_hash',
                'content_revisions.content_type',
                'content_revisions.localization_requirement',
                'content_revisions.payload',
            ]);

        if (! $head instanceof stdClass) {
            throw new ContentWorkspaceRejected;
        }

        $sourceRevisionId = $this->string($head, 'source_revision_id');
        $sourceHash = $this->string($head, 'content_hash');
        $variant = DB::table('localized_variants')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->where('source_revision_id', $sourceRevisionId)
            ->where('source_content_hash', $sourceHash)
            ->where('locale', 'kn-IN')
            ->orderByDesc('revision_number')
            ->orderByDesc('id')
            ->first([
                'id',
                'revision_number',
                'content_hash',
                'payload',
            ]);
        $media = DB::table('content_revision_media_assets')
            ->join('media_assets', function ($join): void {
                $join
                    ->on(
                        'media_assets.organization_id',
                        '=',
                        'content_revision_media_assets.organization_id',
                    )
                    ->on(
                        'media_assets.id',
                        '=',
                        'content_revision_media_assets.media_asset_id',
                    );
            })
            ->where('content_revision_media_assets.organization_id', $organizationId)
            ->where(
                'content_revision_media_assets.curriculum_version_id',
                $curriculumVersionId,
            )
            ->where(
                'content_revision_media_assets.content_revision_id',
                $sourceRevisionId,
            )
            ->orderBy('content_revision_media_assets.usage_key')
            ->orderBy('content_revision_media_assets.sequence')
            ->get([
                'media_assets.id',
                'media_assets.key',
                'media_assets.asset_kind',
                'content_revision_media_assets.usage_key',
                'content_revision_media_assets.sequence',
            ])
            ->map(fn (object $row): array => [
                'media_asset_id' => (string) $row->id,
                'key' => (string) $row->key,
                'asset_kind' => (string) $row->asset_kind,
                'usage_key' => (string) $row->usage_key,
                'sequence' => (int) $row->sequence,
                'readiness' => $this->mediaReadiness(
                    $organizationId,
                    $this->programId($organizationId, $curriculumVersionId),
                    (string) $row->id,
                ),
            ])->values()->all();
        $sourcePayload = $this->jsonObject($head, 'payload');
        $knownSchema = in_array($sourcePayload['schema'] ?? null, [
            GovernedContentPayloadPolicy::GOVERNED_TEXT,
            GovernedContentPayloadPolicy::DAILY_MISSION,
        ], true);

        return ['data' => [
            'content_document_id' => $this->string(
                $head,
                'content_document_id',
            ),
            'document_key' => $this->string($head, 'document_key'),
            'content_type' => $this->string($head, 'content_type'),
            'localization_requirement' => $this->string(
                $head,
                'localization_requirement',
            ),
            'read_only' => ! $knownSchema,
            'english' => [
                'locale' => 'en-IN',
                'revision_id' => $sourceRevisionId,
                'revision_number' => $this->integer($head, 'revision_number'),
                'content_hash' => $sourceHash,
                'payload' => $sourcePayload,
            ],
            'kannada' => $variant instanceof stdClass ? [
                'locale' => 'kn-IN',
                'variant_id' => $this->string($variant, 'id'),
                'revision_number' => $this->integer(
                    $variant,
                    'revision_number',
                ),
                'content_hash' => $this->string($variant, 'content_hash'),
                'payload' => $this->jsonObject($variant, 'payload'),
            ] : null,
            'media' => $media,
        ]];
    }

    /**
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null, allowed_actions: list<string>}
     */
    public function reviewQueue(
        User $actor,
        string $queue,
        int $limit,
        ?string $cursor,
    ): array {
        $this->authorization->assertRead($actor);
        $organizationId = $this->authorization->context($actor)->organizationId;
        $limit = $this->limit($limit);

        if (! in_array($queue, ['structure', 'english', 'kannada', 'readiness'], true)) {
            throw new InvalidOperatorCursor;
        }

        $after = null;

        if ($cursor !== null) {
            $position = $this->cursors->decode(
                $cursor,
                "content-review-queue-{$queue}",
                $organizationId,
            );
            $after = $position['id'] ?? null;

            if (! is_string($after) || $after === '') {
                throw new InvalidOperatorCursor;
            }
        }

        $rows = match ($queue) {
            'structure' => $this->structureQueueRows(
                $organizationId,
                $after,
                $limit + 1,
            ),
            'english' => $this->contentQueueRows(
                $organizationId,
                $after,
                $limit + 1,
                false,
            ),
            'kannada' => $this->contentQueueRows(
                $organizationId,
                $after,
                $limit + 1,
                true,
            ),
            'readiness' => $this->readinessQueueRows(
                $organizationId,
                $after,
                $limit + 1,
            ),
        };
        $hasMore = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $data = array_map(
            fn (stdClass $row): array => $this->queueProjection(
                $actor,
                $queue,
                $row,
            ),
            $rows,
        );
        $last = $rows === [] ? null : $rows[array_key_last($rows)];

        return [
            'data' => $data,
            'next_cursor' => $hasMore && $last instanceof stdClass
                ? $this->cursors->encode(
                    "content-review-queue-{$queue}",
                    $organizationId,
                    ['id' => $this->queueRowId($queue, $last)],
                )
                : null,
            'allowed_actions' => [],
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null}
     */
    public function history(
        User $actor,
        string $curriculumVersionId,
        int $limit,
        ?string $cursor,
    ): array {
        $this->authorization->assertRead($actor);
        $organizationId = $this->authorization->context($actor)->organizationId;
        $this->workspaceRow($organizationId, $curriculumVersionId);
        $limit = $this->limit($limit);
        $beforeTime = null;
        $beforeId = null;

        if ($cursor !== null) {
            $position = $this->cursors->decode(
                $cursor,
                "content-history-{$curriculumVersionId}",
                $organizationId,
            );
            $beforeTime = $position['occurred_at'] ?? null;
            $beforeId = $position['id'] ?? null;

            if (! is_string($beforeTime) || ! is_string($beforeId)) {
                throw new InvalidOperatorCursor;
            }
        }

        $events = [];
        $sources = [
            ['curriculum_workspace_events', 'event_type', 'occurred_at', 'actor_membership_id'],
            ['curriculum_graph_reviews', 'decision', 'reviewed_at', 'reviewer_membership_id'],
            ['content_reviews', 'decision', 'reviewed_at', 'reviewer_membership_id'],
            ['publication_events', 'event_type', 'created_at', 'actor_membership_id'],
        ];

        foreach ($sources as [$table, $kindColumn, $timeColumn, $actorColumn]) {
            $query = DB::table($table)
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId);

            if ($cursor !== null) {
                $query->where(function (Builder $position) use (
                    $timeColumn,
                    $beforeTime,
                    $beforeId,
                ): void {
                    $position
                        ->where($timeColumn, '<', $beforeTime)
                        ->orWhere(function (Builder $same) use (
                            $timeColumn,
                            $beforeTime,
                            $beforeId,
                        ): void {
                            $same
                                ->where($timeColumn, $beforeTime)
                                ->where('id', '<', $beforeId);
                        });
                });
            }

            foreach ($query
                ->orderByDesc($timeColumn)
                ->orderByDesc('id')
                ->limit($limit + 1)
                ->get(['id', $kindColumn, $timeColumn, $actorColumn]) as $row) {
                $events[] = [
                    'id' => (string) $row->id,
                    'event_kind' => $table.'.'.(string) $row->{$kindColumn},
                    'actor_membership_id' => $row->{$actorColumn} === null
                        ? null
                        : (string) $row->{$actorColumn},
                    'occurred_at' => $this->timestamp($row->{$timeColumn}),
                ];
            }
        }

        usort($events, static fn (array $left, array $right): int => (
            [$right['occurred_at'], $right['id']]
            <=> [$left['occurred_at'], $left['id']]
        ));
        $hasMore = count($events) > $limit;
        $events = array_slice($events, 0, $limit);
        $last = $events === [] ? null : $events[array_key_last($events)];

        return [
            'data' => $events,
            'next_cursor' => $hasMore && is_array($last)
                ? $this->cursors->encode(
                    "content-history-{$curriculumVersionId}",
                    $organizationId,
                    [
                        'occurred_at' => $last['occurred_at'],
                        'id' => $last['id'],
                    ],
                )
                : null,
        ];
    }

    /** @return list<string> */
    private function readiness(
        string $organizationId,
        string $curriculumVersionId,
        stdClass $workspace,
    ): array {
        $state = $this->string($workspace, 'state');

        if ($state === 'abandoned') {
            return ['workspace_abandoned'];
        }

        if ($state === 'sealed') {
            return ['workspace_sealed'];
        }

        $blockers = [];
        $draftRevisionId = $this->nullableString(
            $workspace,
            'current_draft_revision_id',
        );

        if ($draftRevisionId === null) {
            $blockers[] = 'workspace_incomplete';
        } else {
            $draft = DB::table('curriculum_draft_graph_revisions')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->where('id', $draftRevisionId)
                ->first(['blocker_codes']);

            if (! $draft instanceof stdClass) {
                $blockers[] = 'workspace_incomplete';
            } else {
                $blockers = [...$blockers, ...$this->jsonList(
                    $draft,
                    'blocker_codes',
                )];
            }
        }

        $graphRevisionId = $this->nullableString(
            $workspace,
            'submitted_graph_revision_id',
        );

        if ($graphRevisionId === null) {
            $blockers[] = 'graph_missing';
        } else {
            $graphDecision = DB::table('curriculum_graph_reviews')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->where('curriculum_graph_revision_id', $graphRevisionId)
                ->orderByDesc('reviewed_at')
                ->orderByDesc('id')
                ->value('decision');

            if ($graphDecision === 'rejected' || $graphDecision === 'withdrawn') {
                $blockers[] = 'graph_review_rejected';
            } elseif ($graphDecision !== 'approved') {
                $blockers[] = 'graph_review_missing';
            }
        }

        $programId = $this->string($workspace, 'program_id');

        foreach ($this->referencedContentHeads(
            $organizationId,
            $curriculumVersionId,
        ) as $head) {
            $sourceRevisionId = $this->string($head, 'source_revision_id');
            $sourceHash = $this->string($head, 'content_hash');
            $englishDecision = DB::table('content_reviews')
                ->where('organization_id', $organizationId)
                ->where('source_revision_id', $sourceRevisionId)
                ->where('source_content_hash', $sourceHash)
                ->where('review_kind', 'academic_en')
                ->orderByDesc('reviewed_at')
                ->orderByDesc('id')
                ->value('decision');

            if ($englishDecision !== 'approved') {
                $blockers[] = 'english_review_missing';
            }

            if ($this->string($head, 'localization_requirement') === 'kn_required') {
                $variant = DB::table('localized_variants')
                    ->where('organization_id', $organizationId)
                    ->where('curriculum_version_id', $curriculumVersionId)
                    ->where('source_revision_id', $sourceRevisionId)
                    ->where('source_content_hash', $sourceHash)
                    ->where('locale', 'kn-IN')
                    ->orderByDesc('revision_number')
                    ->orderByDesc('id')
                    ->first(['id', 'content_hash']);

                if (! $variant instanceof stdClass) {
                    $blockers[] = 'kannada_variant_missing';
                } else {
                    $kannadaDecision = DB::table('content_reviews')
                        ->where('organization_id', $organizationId)
                        ->where('source_revision_id', $sourceRevisionId)
                        ->where('source_content_hash', $sourceHash)
                        ->where('localized_variant_id', $this->string($variant, 'id'))
                        ->where(
                            'localized_content_hash',
                            $this->string($variant, 'content_hash'),
                        )
                        ->where('review_kind', 'kannada')
                        ->orderByDesc('reviewed_at')
                        ->orderByDesc('id')
                        ->value('decision');

                    if ($kannadaDecision !== 'approved') {
                        $blockers[] = 'kannada_review_missing';
                    }
                }
            }

            $payload = $this->jsonObject($head, 'payload');

            if (($payload['schema'] ?? null) === GovernedContentPayloadPolicy::DAILY_MISSION) {
                $links = DB::table('content_revision_media_assets')
                    ->where('organization_id', $organizationId)
                    ->where('curriculum_version_id', $curriculumVersionId)
                    ->where('content_revision_id', $sourceRevisionId)
                    ->where('usage_key', 'model_audio')
                    ->get(['media_asset_id']);

                if ($links->count() !== 1) {
                    $blockers[] = 'media_required_missing';
                } else {
                    $link = $links->first();

                    if (! $link instanceof stdClass) {
                        throw new ContentWorkspaceRejected;
                    }

                    $mediaAssetId = $this->string($link, 'media_asset_id');

                    if ($this->mediaReadiness(
                        $organizationId,
                        $programId,
                        $mediaAssetId,
                    ) !== 'ready') {
                        $blockers[] = 'media_rights_missing';
                    }
                }
            }
        }

        $head = DB::table('publication_heads')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->first(['is_suspended']);

        if ($head instanceof stdClass && $this->boolean($head, 'is_suspended')) {
            $blockers[] = 'publication_suspended';
        }

        if ($this->pendingScheduleExists($organizationId, $programId)) {
            $blockers[] = 'schedule_pending';
        }

        $blockers = array_values(array_unique($blockers));
        sort($blockers, SORT_STRING);

        if ($blockers === [] && $state === 'in_review') {
            try {
                $this->manifests->build($curriculumVersionId, now());
            } catch (CurriculumOperationRejected) {
                $blockers[] = 'evidence_invalid';
            }
        }

        return $blockers;
    }

    /** @return array<string, mixed> */
    private function workspaceSummary(User $actor, stdClass $row): array
    {
        $blockers = $this->jsonList($row, 'blocker_codes');

        return [
            ...$this->workspaceIdentity($row),
            'graph_hash' => $this->nullableString($row, 'graph_hash'),
            'is_complete' => $this->nullableBoolean($row, 'is_complete') ?? false,
            'blocker_codes' => $blockers,
            'allowed_actions' => $this->workspaceActions(
                $actor,
                $row,
                $blockers,
                null,
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function workspaceIdentity(stdClass $row): array
    {
        return [
            'program_id' => $this->string($row, 'program_id'),
            'program_key' => $this->string($row, 'program_key'),
            'program_internal_name' => $this->string(
                $row,
                'program_internal_name',
            ),
            'descriptor_version' => $this->integer(
                $row,
                'descriptor_version',
            ),
            'curriculum_version_id' => $this->string(
                $row,
                'curriculum_version_id',
            ),
            'version_key' => $this->string($row, 'version_key'),
            'source_curriculum_version_id' => $this->nullableString(
                $row,
                'source_curriculum_version_id',
            ),
            'source_curriculum_publication_id' => $this->nullableString(
                $row,
                'source_curriculum_publication_id',
            ),
            'state' => $this->string($row, 'state'),
            'workspace_version' => $this->integer($row, 'workspace_version'),
            'current_draft_revision_id' => $this->nullableString(
                $row,
                'current_draft_revision_id',
            ),
            'submitted_graph_revision_id' => $this->nullableString(
                $row,
                'submitted_graph_revision_id',
            ),
            'publication_head_version' => $this->integer(
                $row,
                'publication_head_version',
            ),
            'active_publication_id' => $this->nullableString(
                $row,
                'active_publication_id',
            ),
            'publication_suspended' => $this->nullableBoolean(
                $row,
                'publication_suspended',
            ) ?? false,
        ];
    }

    /**
     * @param  list<string>  $draftBlockers
     * @param  list<string>|null  $readiness
     * @return list<string>
     */
    private function workspaceActions(
        User $actor,
        stdClass $workspace,
        array $draftBlockers,
        ?array $readiness,
    ): array {
        $state = $this->string($workspace, 'state');
        $actions = [];

        if ($this->authorization->allows($actor, 'curriculum.author')) {
            $actions[] = 'correct_descriptor';

            if ($state === 'draft') {
                array_push($actions, 'edit_graph', 'create_document', 'abandon');

                if ($draftBlockers === []) {
                    $actions[] = 'submit_structure';
                }
            } elseif ($state === 'in_review') {
                $actions[] = 'abandon';
            } else {
                $actions[] = 'create_successor';
            }
        }

        if (
            $state === 'draft'
            && $this->authorization->allows($actor, 'curriculum.translate.kn')
        ) {
            $actions[] = 'translate';
        }

        if (
            $state === 'in_review'
            && $readiness === []
            && $this->authorization->allows($actor, 'curriculum.publish')
        ) {
            $actions[] = 'publish_now';
            $actions[] = 'schedule';
        }

        return array_values(array_unique($actions));
    }

    /** @return array{data: list<array<string, mixed>>, count: int} */
    private function documentSummaries(
        string $organizationId,
        string $curriculumVersionId,
    ): array {
        $base = DB::table('content_documents')
            ->join('content_revision_heads', function ($join): void {
                $join
                    ->on(
                        'content_revision_heads.organization_id',
                        '=',
                        'content_documents.organization_id',
                    )
                    ->on(
                        'content_revision_heads.curriculum_version_id',
                        '=',
                        'content_documents.curriculum_version_id',
                    )
                    ->on(
                        'content_revision_heads.content_document_id',
                        '=',
                        'content_documents.id',
                    );
            })
            ->join('content_revisions', function ($join): void {
                $join
                    ->on(
                        'content_revisions.organization_id',
                        '=',
                        'content_revision_heads.organization_id',
                    )
                    ->on(
                        'content_revisions.id',
                        '=',
                        'content_revision_heads.content_revision_id',
                    );
            })
            ->where('content_documents.organization_id', $organizationId)
            ->where('content_documents.curriculum_version_id', $curriculumVersionId);
        $count = (clone $base)->count();
        $rows = $base
            ->orderBy('content_documents.key')
            ->orderBy('content_documents.id')
            ->limit(self::MAX_PAGE_SIZE)
            ->get([
                'content_documents.id as content_document_id',
                'content_documents.key as document_key',
                'content_revisions.id as source_revision_id',
                'content_revisions.revision_number',
                'content_revisions.content_hash',
                'content_revisions.content_type',
                'content_revisions.learner_visible',
                'content_revisions.localization_requirement',
                'content_revisions.payload',
            ]);
        $data = [];

        foreach ($rows as $row) {
            $payload = $this->jsonObject($row, 'payload');
            $data[] = [
                'content_document_id' => $this->string(
                    $row,
                    'content_document_id',
                ),
                'document_key' => $this->string($row, 'document_key'),
                'content_type' => $this->string($row, 'content_type'),
                'learner_visible' => $this->boolean($row, 'learner_visible'),
                'localization_requirement' => $this->string(
                    $row,
                    'localization_requirement',
                ),
                'payload_schema' => is_string($payload['schema'] ?? null)
                    ? $payload['schema']
                    : 'legacy-unknown',
                'source_revision_id' => $this->string(
                    $row,
                    'source_revision_id',
                ),
                'source_revision_number' => $this->integer(
                    $row,
                    'revision_number',
                ),
                'source_content_hash' => $this->string($row, 'content_hash'),
            ];
        }

        return ['data' => $data, 'count' => $count];
    }

    /** @return list<stdClass> */
    private function referencedContentHeads(
        string $organizationId,
        string $curriculumVersionId,
    ): array {
        $documentIds = DB::table('learning_items')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->whereNull('retired_at')
            ->pluck('content_document_id')
            ->merge(
                DB::table('curriculum_missions')
                    ->where('organization_id', $organizationId)
                    ->where('curriculum_version_id', $curriculumVersionId)
                    ->whereNull('retired_at')
                    ->pluck('instruction_content_document_id'),
            )
            ->unique()
            ->values()
            ->all();

        if ($documentIds === []) {
            return [];
        }

        return $this->stdRows(DB::table('content_revision_heads')
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
                $curriculumVersionId,
            )
            ->whereIn('content_revision_heads.content_document_id', $documentIds)
            ->get([
                'content_revisions.id as source_revision_id',
                'content_revisions.content_hash',
                'content_revisions.localization_requirement',
                'content_revisions.payload',
            ]));
    }

    /** @return list<array{id: string, authority_kind: string}> */
    private function appointmentChoices(
        User $actor,
        string $programId,
        string $authorityKind,
    ): array {
        $permission = $authorityKind === 'kannada_reviewer'
            ? 'curriculum.review.kn'
            : 'curriculum.review.en';

        if (! $this->authorization->allows($actor, $permission)) {
            return [];
        }

        $context = $this->authorization->context($actor);
        $appointments = DB::table('authority_appointments')
            ->where('organization_id', $context->organizationId)
            ->where('appointee_user_id', (string) $actor->getKey())
            ->where('appointee_membership_id', $context->membershipId)
            ->where('authority_kind', $authorityKind)
            ->where('valid_from', '<=', now())
            ->where(function (Builder $validity): void {
                $validity->whereNull('valid_until')->orWhere('valid_until', '>', now());
            })
            ->where(function (Builder $scope) use ($programId): void {
                $scope
                    ->where(function (Builder $organization): void {
                        $organization
                            ->where('scope_type', 'organization')
                            ->whereNull('program_id');
                    })
                    ->orWhere(function (Builder $program) use ($programId): void {
                        $program
                            ->where('scope_type', 'program')
                            ->where('program_id', $programId);
                    });
            })
            ->orderBy('id')
            ->limit(50)
            ->get(['id']);
        $choices = [];

        foreach ($appointments as $appointment) {
            $id = (string) $appointment->id;
            $event = DB::table('authority_appointment_events')
                ->where('organization_id', $context->organizationId)
                ->where('authority_appointment_id', $id)
                ->where('effective_at', '<=', now())
                ->orderByDesc('effective_at')
                ->orderByDesc('id')
                ->value('event_type');

            if ($event === 'accepted') {
                $choices[] = ['id' => $id, 'authority_kind' => $authorityKind];
            }
        }

        return $choices;
    }

    private function mediaReadiness(
        string $organizationId,
        string $programId,
        string $mediaAssetId,
    ): string {
        $rights = DB::table('media_rights_revisions')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('media_asset_id', $mediaAssetId)
            ->orderByDesc('revision_number')
            ->orderByDesc('id')
            ->first([
                'evidence_status',
                'learner_delivery_allowed',
                'effective_at',
                'expires_at',
            ]);

        if (! $rights instanceof stdClass) {
            return 'missing_rights';
        }

        $effectiveAt = CarbonImmutable::parse(
            $this->string($rights, 'effective_at'),
        );
        $expiresAt = $this->nullableString($rights, 'expires_at');

        return $this->string($rights, 'evidence_status') === 'approved'
            && $this->boolean($rights, 'learner_delivery_allowed')
            && $effectiveAt->lessThanOrEqualTo(now())
            && ($expiresAt === null || CarbonImmutable::parse($expiresAt)->isFuture())
            ? 'ready'
            : 'rights_blocked';
    }

    private function pendingScheduleExists(
        string $organizationId,
        string $programId,
    ): bool {
        return DB::table('publication_events as schedule_events')
            ->where('schedule_events.organization_id', $organizationId)
            ->where('schedule_events.program_id', $programId)
            ->where('schedule_events.event_type', 'scheduled')
            ->whereNotExists(function (Builder $terminal): void {
                $terminal
                    ->selectRaw('1')
                    ->from('publication_events as terminal_events')
                    ->whereColumn(
                        'terminal_events.organization_id',
                        'schedule_events.organization_id',
                    )
                    ->whereColumn(
                        'terminal_events.program_id',
                        'schedule_events.program_id',
                    )
                    ->whereColumn(
                        'terminal_events.curriculum_publication_id',
                        'schedule_events.curriculum_publication_id',
                    )
                    ->whereIn('terminal_events.event_type', [
                        'schedule_cancelled',
                        'activated',
                        'rollback_activated',
                        'retired',
                    ]);
            })
            ->exists();
    }

    /** @return list<stdClass> */
    private function structureQueueRows(
        string $organizationId,
        ?string $after,
        int $limit,
    ): array {
        $query = DB::table('curriculum_workspaces')
            ->join('programs', function ($join): void {
                $join
                    ->on(
                        'programs.organization_id',
                        '=',
                        'curriculum_workspaces.organization_id',
                    )
                    ->on(
                        'programs.id',
                        '=',
                        'curriculum_workspaces.program_id',
                    );
            })
            ->join('curriculum_versions', function ($join): void {
                $join
                    ->on(
                        'curriculum_versions.organization_id',
                        '=',
                        'curriculum_workspaces.organization_id',
                    )
                    ->on(
                        'curriculum_versions.id',
                        '=',
                        'curriculum_workspaces.curriculum_version_id',
                    );
            })
            ->leftJoin('publication_heads', function ($join): void {
                $join
                    ->on(
                        'publication_heads.organization_id',
                        '=',
                        'curriculum_workspaces.organization_id',
                    )
                    ->on(
                        'publication_heads.program_id',
                        '=',
                        'curriculum_workspaces.program_id',
                    );
            })
            ->join('curriculum_graph_revisions', function ($join): void {
                $join
                    ->on(
                        'curriculum_graph_revisions.organization_id',
                        '=',
                        'curriculum_workspaces.organization_id',
                    )
                    ->on(
                        'curriculum_graph_revisions.id',
                        '=',
                        'curriculum_workspaces.submitted_graph_revision_id',
                    );
            })
            ->where('curriculum_workspaces.organization_id', $organizationId)
            ->where('curriculum_workspaces.state', 'in_review');

        if ($after !== null) {
            $query->where('curriculum_workspaces.curriculum_version_id', '>', $after);
        }

        return $this->stdRows($query
            ->orderBy('curriculum_workspaces.curriculum_version_id')
            ->limit($limit)
            ->get([
                'curriculum_workspaces.program_id',
                'curriculum_workspaces.curriculum_version_id',
                'curriculum_workspaces.version as workspace_version',
                'programs.key as program_key',
                'curriculum_versions.key as version_key',
                'curriculum_graph_revisions.id as graph_revision_id',
                'curriculum_graph_revisions.graph_hash',
                'curriculum_graph_revisions.revision_number',
            ]));
    }

    /** @return list<stdClass> */
    private function contentQueueRows(
        string $organizationId,
        ?string $after,
        int $limit,
        bool $kannada,
    ): array {
        $query = DB::table('content_revision_heads')
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
            ->join('content_documents', function ($join): void {
                $join
                    ->on(
                        'content_documents.organization_id',
                        '=',
                        'content_revision_heads.organization_id',
                    )
                    ->on(
                        'content_documents.id',
                        '=',
                        'content_revision_heads.content_document_id',
                    );
            })
            ->join('curriculum_workspaces', function ($join): void {
                $join
                    ->on(
                        'curriculum_workspaces.organization_id',
                        '=',
                        'content_revision_heads.organization_id',
                    )
                    ->on(
                        'curriculum_workspaces.curriculum_version_id',
                        '=',
                        'content_revision_heads.curriculum_version_id',
                    );
            })
            ->join('programs', function ($join): void {
                $join
                    ->on(
                        'programs.organization_id',
                        '=',
                        'curriculum_workspaces.organization_id',
                    )
                    ->on(
                        'programs.id',
                        '=',
                        'curriculum_workspaces.program_id',
                    );
            })
            ->where('content_revision_heads.organization_id', $organizationId)
            ->whereIn('curriculum_workspaces.state', ['draft', 'in_review']);

        if ($kannada) {
            $query->where('content_revisions.localization_requirement', 'kn_required');
        }

        if ($after !== null) {
            $query->where('content_revisions.id', '>', $after);
        }

        return $this->stdRows($query
            ->orderBy('content_revisions.id')
            ->limit($limit)
            ->get([
                'curriculum_workspaces.program_id',
                'curriculum_workspaces.curriculum_version_id',
                'programs.key as program_key',
                'content_documents.id as content_document_id',
                'content_documents.key as document_key',
                'content_revisions.id as source_revision_id',
                'content_revisions.revision_number',
                'content_revisions.content_hash',
                'content_revisions.localization_requirement',
            ]));
    }

    /** @return list<stdClass> */
    private function readinessQueueRows(
        string $organizationId,
        ?string $after,
        int $limit,
    ): array {
        $query = DB::table('curriculum_workspaces')
            ->join('programs', function ($join): void {
                $join
                    ->on(
                        'programs.organization_id',
                        '=',
                        'curriculum_workspaces.organization_id',
                    )
                    ->on(
                        'programs.id',
                        '=',
                        'curriculum_workspaces.program_id',
                    );
            })
            ->join('curriculum_versions', function ($join): void {
                $join
                    ->on(
                        'curriculum_versions.organization_id',
                        '=',
                        'curriculum_workspaces.organization_id',
                    )
                    ->on(
                        'curriculum_versions.id',
                        '=',
                        'curriculum_workspaces.curriculum_version_id',
                    );
            })
            ->leftJoin('publication_heads', function ($join): void {
                $join
                    ->on(
                        'publication_heads.organization_id',
                        '=',
                        'curriculum_workspaces.organization_id',
                    )
                    ->on(
                        'publication_heads.program_id',
                        '=',
                        'curriculum_workspaces.program_id',
                    );
            })
            ->where('curriculum_workspaces.organization_id', $organizationId)
            ->whereIn('curriculum_workspaces.state', ['draft', 'in_review']);

        if ($after !== null) {
            $query->where('curriculum_workspaces.curriculum_version_id', '>', $after);
        }

        return $this->stdRows($query
            ->orderBy('curriculum_workspaces.curriculum_version_id')
            ->limit($limit)
            ->get([
                'curriculum_workspaces.*',
                'programs.key as program_key',
                'programs.internal_name as program_internal_name',
                'programs.descriptor_version',
                'curriculum_versions.key as version_key',
                'curriculum_workspaces.version as workspace_version',
                DB::raw(
                    'COALESCE(publication_heads.lock_version, 0) as publication_head_version',
                ),
                'publication_heads.active_publication_id',
                DB::raw(
                    'COALESCE(publication_heads.is_suspended, false) as publication_suspended',
                ),
            ]));
    }

    /** @return array<string, mixed> */
    private function queueProjection(
        User $actor,
        string $queue,
        stdClass $row,
    ): array {
        $programId = $this->string($row, 'program_id');

        if ($queue === 'structure') {
            $decision = DB::table('curriculum_graph_reviews')
                ->where('organization_id', $this->authorization->context($actor)->organizationId)
                ->where('curriculum_graph_revision_id', $this->string($row, 'graph_revision_id'))
                ->orderByDesc('reviewed_at')
                ->orderByDesc('id')
                ->value('decision');

            return [
                'program_id' => $programId,
                'program_key' => $this->string($row, 'program_key'),
                'curriculum_version_id' => $this->string(
                    $row,
                    'curriculum_version_id',
                ),
                'version_key' => $this->string($row, 'version_key'),
                'graph_revision_id' => $this->string($row, 'graph_revision_id'),
                'graph_revision_number' => $this->integer(
                    $row,
                    'revision_number',
                ),
                'graph_hash' => $this->string($row, 'graph_hash'),
                'latest_decision' => is_string($decision) ? $decision : null,
                'appointment_choices' => $this->appointmentChoices(
                    $actor,
                    $programId,
                    'english_academic_reviewer',
                ),
                'allowed_actions' => $decision !== 'approved'
                    && $this->authorization->allows($actor, 'curriculum.review.en')
                    && $this->appointmentChoices(
                        $actor,
                        $programId,
                        'english_academic_reviewer',
                    ) !== []
                        ? ['review_structure']
                        : [],
            ];
        }

        if ($queue === 'readiness') {
            $readiness = $this->readiness(
                $this->authorization->context($actor)->organizationId,
                $this->string($row, 'curriculum_version_id'),
                $row,
            );

            return [
                ...$this->workspaceIdentity($row),
                'blocker_codes' => $readiness,
                'ready' => $readiness === [],
                'allowed_actions' => $this->workspaceActions(
                    $actor,
                    $row,
                    [],
                    $readiness,
                ),
            ];
        }

        $organizationId = $this->authorization->context($actor)->organizationId;
        $sourceRevisionId = $this->string($row, 'source_revision_id');
        $sourceHash = $this->string($row, 'content_hash');
        $variant = $queue === 'kannada'
            ? DB::table('localized_variants')
                ->where('organization_id', $organizationId)
                ->where('source_revision_id', $sourceRevisionId)
                ->where('source_content_hash', $sourceHash)
                ->where('locale', 'kn-IN')
                ->orderByDesc('revision_number')
                ->orderByDesc('id')
                ->first(['id', 'revision_number', 'content_hash'])
            : null;
        $reviewQuery = DB::table('content_reviews')
            ->where('organization_id', $organizationId)
            ->where('source_revision_id', $sourceRevisionId)
            ->where('source_content_hash', $sourceHash)
            ->where('review_kind', $queue === 'kannada' ? 'kannada' : 'academic_en');

        if ($variant instanceof stdClass) {
            $reviewQuery
                ->where('localized_variant_id', $this->string($variant, 'id'))
                ->where(
                    'localized_content_hash',
                    $this->string($variant, 'content_hash'),
                );
        }

        $decision = $reviewQuery
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->value('decision');
        $authorityKind = $queue === 'kannada'
            ? 'kannada_reviewer'
            : 'english_academic_reviewer';
        $appointmentChoices = $this->appointmentChoices(
            $actor,
            $programId,
            $authorityKind,
        );
        $permission = $queue === 'kannada'
            ? 'curriculum.review.kn'
            : 'curriculum.review.en';
        $actions = [];

        if ($queue === 'kannada' && ! $variant instanceof stdClass) {
            if ($this->authorization->allows($actor, 'curriculum.translate.kn')) {
                $actions[] = 'translate';
            }
        } elseif (
            $decision !== 'approved'
            && $this->authorization->allows($actor, $permission)
            && $appointmentChoices !== []
        ) {
            $actions[] = $queue === 'kannada'
                ? 'review_kannada'
                : 'review_english';
        }

        return [
            'program_id' => $programId,
            'program_key' => $this->string($row, 'program_key'),
            'curriculum_version_id' => $this->string(
                $row,
                'curriculum_version_id',
            ),
            'content_document_id' => $this->string(
                $row,
                'content_document_id',
            ),
            'document_key' => $this->string($row, 'document_key'),
            'source_revision_id' => $sourceRevisionId,
            'source_revision_number' => $this->integer($row, 'revision_number'),
            'source_content_hash' => $sourceHash,
            'localized_variant_id' => $variant instanceof stdClass
                ? $this->string($variant, 'id')
                : null,
            'localized_variant_revision_number' => $variant instanceof stdClass
                ? $this->integer($variant, 'revision_number')
                : null,
            'localized_content_hash' => $variant instanceof stdClass
                ? $this->string($variant, 'content_hash')
                : null,
            'latest_decision' => is_string($decision) ? $decision : null,
            'appointment_choices' => $appointmentChoices,
            'allowed_actions' => $actions,
        ];
    }

    private function queueRowId(string $queue, stdClass $row): string
    {
        return in_array($queue, ['structure', 'readiness'], true)
            ? $this->string($row, 'curriculum_version_id')
            : $this->string($row, 'source_revision_id');
    }

    private function workspaceRow(
        string $organizationId,
        string $curriculumVersionId,
    ): stdClass {
        $row = DB::table('curriculum_workspaces')
            ->join('curriculum_versions', function ($join): void {
                $join
                    ->on(
                        'curriculum_versions.organization_id',
                        '=',
                        'curriculum_workspaces.organization_id',
                    )
                    ->on(
                        'curriculum_versions.id',
                        '=',
                        'curriculum_workspaces.curriculum_version_id',
                    );
            })
            ->join('programs', function ($join): void {
                $join
                    ->on(
                        'programs.organization_id',
                        '=',
                        'curriculum_workspaces.organization_id',
                    )
                    ->on(
                        'programs.id',
                        '=',
                        'curriculum_workspaces.program_id',
                    );
            })
            ->leftJoin('publication_heads', function ($join): void {
                $join
                    ->on(
                        'publication_heads.organization_id',
                        '=',
                        'curriculum_workspaces.organization_id',
                    )
                    ->on(
                        'publication_heads.program_id',
                        '=',
                        'curriculum_workspaces.program_id',
                    );
            })
            ->where('curriculum_workspaces.organization_id', $organizationId)
            ->where('curriculum_workspaces.curriculum_version_id', $curriculumVersionId)
            ->first([
                'curriculum_workspaces.*',
                'curriculum_workspaces.version as workspace_version',
                'curriculum_versions.key as version_key',
                'programs.key as program_key',
                'programs.internal_name as program_internal_name',
                'programs.descriptor_version',
                DB::raw(
                    'COALESCE(publication_heads.lock_version, 0) as publication_head_version',
                ),
                'publication_heads.active_publication_id',
                DB::raw(
                    'COALESCE(publication_heads.is_suspended, false) as publication_suspended',
                ),
            ]);

        if (! $row instanceof stdClass) {
            throw new ContentWorkspaceRejected;
        }

        return $row;
    }

    private function programId(
        string $organizationId,
        string $curriculumVersionId,
    ): string {
        $programId = DB::table('curriculum_versions')
            ->where('organization_id', $organizationId)
            ->where('id', $curriculumVersionId)
            ->value('program_id');

        if (! is_string($programId)) {
            throw new ContentWorkspaceRejected;
        }

        return $programId;
    }

    private function applyTimestampCursor(
        Builder $query,
        ?string $cursor,
        string $purpose,
        string $organizationId,
        string $timeColumn,
        string $idColumn,
    ): void {
        if ($cursor === null) {
            return;
        }

        $position = $this->cursors->decode($cursor, $purpose, $organizationId);
        $time = $position['updated_at'] ?? null;
        $id = $position['id'] ?? null;

        if (! is_string($time) || ! is_string($id)) {
            throw new InvalidOperatorCursor;
        }

        $query->where(function (Builder $cursorQuery) use (
            $timeColumn,
            $idColumn,
            $time,
            $id,
        ): void {
            $cursorQuery
                ->where($timeColumn, '<', $time)
                ->orWhere(function (Builder $sameTime) use (
                    $timeColumn,
                    $idColumn,
                    $time,
                    $id,
                ): void {
                    $sameTime
                        ->where($timeColumn, $time)
                        ->where($idColumn, '<', $id);
                });
        });
    }

    private function limit(int $limit): int
    {
        return max(1, min(self::MAX_PAGE_SIZE, $limit));
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return list<stdClass>
     */
    private function stdRows(Collection $rows): array
    {
        return array_values($rows->all());
    }

    private function string(stdClass $row, string $field): string
    {
        return $this->nullableString($row, $field)
            ?? throw new ContentWorkspaceRejected;
    }

    private function nullableString(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;

        return is_string($value) && $value !== '' ? $value : null;
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

        throw new ContentWorkspaceRejected;
    }

    private function boolean(stdClass $row, string $field): bool
    {
        return $this->nullableBoolean($row, $field)
            ?? throw new ContentWorkspaceRejected;
    }

    private function nullableBoolean(stdClass $row, string $field): ?bool
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

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw new ContentWorkspaceRejected;
            }
        }

        return $value;
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

    private function timestamp(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s.uP');
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        throw new ContentWorkspaceRejected;
    }
}

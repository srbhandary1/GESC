<?php

declare(strict_types=1);

namespace App\Operator\MediaLibrary;

use App\Audit\AuditEventRecorder;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Governance\AuthorityEvidenceValidator;
use App\Models\User;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryConflict;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryRejected;
use App\Outbox\OutboxEventRecorder;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class MediaLifecycleCommands
{
    public function __construct(
        private MediaLibraryAuthorization $authorization,
        private MediaLibraryIdempotency $idempotency,
        private AuthorityEvidenceValidator $authorityEvidence,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function replace(
        User $actor,
        string $predecessorAssetId,
        string $successorAssetId,
        int $expectedPredecessorLifecycleVersion,
        int $expectedSuccessorLifecycleVersion,
        string $idempotencyKey,
    ): MediaLibraryCommandResult {
        return DB::transaction(function () use (
            $actor,
            $predecessorAssetId,
            $successorAssetId,
            $expectedPredecessorLifecycleVersion,
            $expectedSuccessorLifecycleVersion,
            $idempotencyKey,
        ): MediaLibraryCommandResult {
            $this->authorization->assert($actor, 'media.replace');
            $context = $this->authorization->context($actor);
            $membership = $this->authorization->actorMembership($actor);

            if (hash_equals($predecessorAssetId, $successorAssetId)) {
                throw new MediaLibraryRejected;
            }

            $operation = 'media.asset.replace';
            $fingerprint = $this->idempotency->fingerprint(
                $operation,
                'media_asset',
                $predecessorAssetId,
                [
                    'expected_predecessor_lifecycle_version' => $expectedPredecessorLifecycleVersion,
                    'expected_successor_lifecycle_version' => $expectedSuccessorLifecycleVersion,
                    'successor_media_asset_id' => $successorAssetId,
                ],
            );
            $claim = $this->idempotency->claim(
                $actor,
                $idempotencyKey,
                $operation,
                'media_asset',
                $predecessorAssetId,
                $fingerprint,
            );

            if ($claim->replayed) {
                return new MediaLibraryCommandResult(
                    'media_asset_replacement',
                    $successorAssetId,
                    true,
                    [
                        'predecessor_media_asset_id' => $predecessorAssetId,
                        'successor_media_asset_id' => $successorAssetId,
                    ],
                );
            }

            $rows = DB::table('media_asset_lifecycles')
                ->where('organization_id', $context->organizationId)
                ->whereIn('media_asset_id', [
                    $predecessorAssetId,
                    $successorAssetId,
                ])
                ->orderBy('media_asset_id')
                ->lockForUpdate()
                ->get([
                    'media_asset_id',
                    'asset_kind',
                    'state',
                    'version',
                    'selected_scan_id',
                    'predecessor_media_asset_id',
                ]);
            $lifecycles = [];

            foreach ($rows as $row) {
                $lifecycles[$this->string($row, 'media_asset_id')] = $row;
            }

            $predecessor = $lifecycles[$predecessorAssetId] ?? null;
            $successor = $lifecycles[$successorAssetId] ?? null;

            if (
                ! $predecessor instanceof stdClass
                || ! $successor instanceof stdClass
                || $this->string($predecessor, 'state') !== 'active'
                || $this->string($successor, 'state') !== 'active'
                || $this->integer($predecessor, 'version')
                    !== $expectedPredecessorLifecycleVersion
                || $this->integer($successor, 'version')
                    !== $expectedSuccessorLifecycleVersion
                || ! hash_equals(
                    $this->string($predecessor, 'asset_kind'),
                    $this->string($successor, 'asset_kind'),
                )
                || $this->nullableString(
                    $successor,
                    'predecessor_media_asset_id',
                ) !== null
            ) {
                throw new MediaLibraryConflict(
                    MediaLibraryConflict::STALE_LIFECYCLE,
                );
            }

            $this->assertCleanSelectedScan(
                $context->organizationId,
                $predecessorAssetId,
                $this->string($predecessor, 'selected_scan_id'),
            );
            $this->assertCleanSelectedScan(
                $context->organizationId,
                $successorAssetId,
                $this->string($successor, 'selected_scan_id'),
            );

            if (
                DB::table('media_asset_replacements')
                    ->where('organization_id', $context->organizationId)
                    ->where(function ($query) use (
                        $predecessorAssetId,
                        $successorAssetId,
                    ): void {
                        $query
                            ->where(
                                'successor_media_asset_id',
                                $successorAssetId,
                            )
                            ->orWhere(function ($query) use (
                                $predecessorAssetId,
                                $successorAssetId,
                            ): void {
                                $query
                                    ->where(
                                        'predecessor_media_asset_id',
                                        $predecessorAssetId,
                                    )
                                    ->where(
                                        'successor_media_asset_id',
                                        $successorAssetId,
                                    );
                            });
                    })
                    ->exists()
                || $this->wouldCreateReplacementCycle(
                    $context->organizationId,
                    $predecessorAssetId,
                    $successorAssetId,
                )
            ) {
                throw new MediaLibraryConflict(
                    MediaLibraryConflict::CONCURRENT_CHANGE,
                );
            }

            $actorMembershipId = $this->string($membership, 'id');
            $now = CarbonImmutable::now();
            $successorVersion = $expectedSuccessorLifecycleVersion + 1;
            DB::table('media_asset_replacements')->insert([
                'organization_id' => $context->organizationId,
                'predecessor_media_asset_id' => $predecessorAssetId,
                'successor_media_asset_id' => $successorAssetId,
                'recorded_by_membership_id' => $actorMembershipId,
                'recorded_at' => $now,
                'created_at' => $now,
            ]);
            $updated = DB::table('media_asset_lifecycles')
                ->where('organization_id', $context->organizationId)
                ->where('media_asset_id', $successorAssetId)
                ->where('version', $expectedSuccessorLifecycleVersion)
                ->whereNull('predecessor_media_asset_id')
                ->update([
                    'version' => $successorVersion,
                    'predecessor_media_asset_id' => $predecessorAssetId,
                    'updated_at' => $now,
                ]);

            if ($updated !== 1) {
                throw new MediaLibraryConflict(
                    MediaLibraryConflict::STALE_LIFECYCLE,
                );
            }

            $this->assetEvent(
                $context->organizationId,
                $successorAssetId,
                'asset.replacement_recorded',
                $successorVersion,
                $actorMembershipId,
                $now,
                $this->string($successor, 'selected_scan_id'),
                $predecessorAssetId,
            );
            $this->idempotency->complete(
                $claim,
                'media_asset_replacement',
                $successorAssetId,
                $now,
            );
            $this->recordEvidence(
                'media.asset.replacement_recorded',
                'media_asset',
                $successorAssetId,
                ['media_asset_id' => $successorAssetId],
            );

            return new MediaLibraryCommandResult(
                'media_asset_replacement',
                $successorAssetId,
                false,
                [
                    'predecessor_media_asset_id' => $predecessorAssetId,
                    'successor_media_asset_id' => $successorAssetId,
                    'successor_lifecycle_version' => $successorVersion,
                    'authority_inherited' => false,
                ],
            );
        }, 3);
    }

    public function retire(
        User $actor,
        string $assetId,
        int $expectedLifecycleVersion,
        string $reasonKey,
        string $idempotencyKey,
    ): MediaLibraryCommandResult {
        return DB::transaction(function () use (
            $actor,
            $assetId,
            $expectedLifecycleVersion,
            $reasonKey,
            $idempotencyKey,
        ): MediaLibraryCommandResult {
            $this->authorization->assert($actor, 'media.retire');
            $context = $this->authorization->context($actor);
            $membership = $this->authorization->actorMembership($actor);
            $reasonKey = $this->safeKey($reasonKey);
            $operation = 'media.asset.retire';
            $fingerprint = $this->idempotency->fingerprint(
                $operation,
                'media_asset',
                $assetId,
                [
                    'expected_lifecycle_version' => $expectedLifecycleVersion,
                    'reason_key' => $reasonKey,
                ],
            );
            $claim = $this->idempotency->claim(
                $actor,
                $idempotencyKey,
                $operation,
                'media_asset',
                $assetId,
                $fingerprint,
            );

            if ($claim->replayed) {
                return new MediaLibraryCommandResult(
                    'media_asset_lifecycle',
                    $assetId,
                    true,
                    [
                        'media_asset_id' => $assetId,
                        'state' => 'retired',
                        'lifecycle_version' => $expectedLifecycleVersion + 1,
                    ],
                );
            }

            $lifecycle = DB::table('media_asset_lifecycles')
                ->where('organization_id', $context->organizationId)
                ->where('media_asset_id', $assetId)
                ->lockForUpdate()
                ->first(['state', 'version', 'selected_scan_id']);

            if (
                ! $lifecycle instanceof stdClass
                || $this->string($lifecycle, 'state') !== 'active'
                || $this->integer($lifecycle, 'version')
                    !== $expectedLifecycleVersion
            ) {
                throw new MediaLibraryConflict(
                    MediaLibraryConflict::STALE_LIFECYCLE,
                );
            }

            $actorMembershipId = $this->string($membership, 'id');
            $now = CarbonImmutable::now();
            $nextVersion = $expectedLifecycleVersion + 1;
            $updated = DB::table('media_asset_lifecycles')
                ->where('organization_id', $context->organizationId)
                ->where('media_asset_id', $assetId)
                ->where('state', 'active')
                ->where('version', $expectedLifecycleVersion)
                ->update([
                    'state' => 'retired',
                    'version' => $nextVersion,
                    'retired_by_membership_id' => $actorMembershipId,
                    'retirement_reason_key' => $reasonKey,
                    'retired_at' => $now,
                    'updated_at' => $now,
                ]);

            if ($updated !== 1) {
                throw new MediaLibraryConflict(
                    MediaLibraryConflict::STALE_LIFECYCLE,
                );
            }

            $this->assetEvent(
                $context->organizationId,
                $assetId,
                'asset.retired',
                $nextVersion,
                $actorMembershipId,
                $now,
                $this->string($lifecycle, 'selected_scan_id'),
                reasonKey: $reasonKey,
            );
            $this->idempotency->complete(
                $claim,
                'media_asset_lifecycle',
                $assetId,
                $now,
            );
            $this->recordEvidence(
                'media.asset.retired',
                'media_asset',
                $assetId,
                ['media_asset_id' => $assetId],
            );

            return new MediaLibraryCommandResult(
                'media_asset_lifecycle',
                $assetId,
                false,
                [
                    'media_asset_id' => $assetId,
                    'state' => 'retired',
                    'lifecycle_version' => $nextVersion,
                    'existing_publications_changed' => false,
                ],
            );
        }, 3);
    }

    public function attach(
        User $actor,
        string $assetId,
        string $programId,
        string $curriculumVersionId,
        string $sourceRevisionId,
        string $usageKey,
        int $expectedWorkspaceVersion,
        int $expectedLifecycleVersion,
        string $metadataRevisionId,
        string $metadataContentHash,
        string $englishReviewId,
        string $metadataVariantId,
        string $variantContentHash,
        string $kannadaReviewId,
        string $mediaRightsRevisionId,
        string $idempotencyKey,
    ): MediaLibraryCommandResult {
        try {
            return DB::transaction(function () use (
                $actor,
                $assetId,
                $programId,
                $curriculumVersionId,
                $sourceRevisionId,
                $usageKey,
                $expectedWorkspaceVersion,
                $expectedLifecycleVersion,
                $metadataRevisionId,
                $metadataContentHash,
                $englishReviewId,
                $metadataVariantId,
                $variantContentHash,
                $kannadaReviewId,
                $mediaRightsRevisionId,
                $idempotencyKey,
            ): MediaLibraryCommandResult {
                $this->authorization->assert($actor, 'media.metadata.manage');
                $this->authorization->assert($actor, 'curriculum.author');
                $context = $this->authorization->context($actor);
                $membership = $this->authorization->actorMembership($actor);
                $operation = 'media.asset.attach';
                $fingerprint = $this->idempotency->fingerprint(
                    $operation,
                    'media_asset',
                    $assetId,
                    [
                        'curriculum_version_id' => $curriculumVersionId,
                        'english_review_id' => $englishReviewId,
                        'expected_lifecycle_version' => $expectedLifecycleVersion,
                        'expected_workspace_version' => $expectedWorkspaceVersion,
                        'kannada_review_id' => $kannadaReviewId,
                        'media_rights_revision_id' => $mediaRightsRevisionId,
                        'metadata_content_hash' => $metadataContentHash,
                        'metadata_revision_id' => $metadataRevisionId,
                        'metadata_variant_id' => $metadataVariantId,
                        'program_id' => $programId,
                        'source_revision_id' => $sourceRevisionId,
                        'usage_key' => $usageKey,
                        'variant_content_hash' => $variantContentHash,
                    ],
                );
                $claim = $this->idempotency->claim(
                    $actor,
                    $idempotencyKey,
                    $operation,
                    'media_asset',
                    $assetId,
                    $fingerprint,
                );

                if ($claim->replayed) {
                    $eventId = $claim->resultId
                        ?? throw new MediaLibraryConflict(
                            MediaLibraryConflict::IDEMPOTENCY,
                        );

                    return new MediaLibraryCommandResult(
                        'media_attachment_event',
                        $eventId,
                        true,
                        [
                            'media_attachment_event_id' => $eventId,
                            'media_asset_id' => $assetId,
                            'workspace_version' => $expectedWorkspaceVersion + 1,
                        ],
                    );
                }

                $workspace = DB::table('curriculum_workspaces')
                    ->where('organization_id', $context->organizationId)
                    ->where('program_id', $programId)
                    ->where('curriculum_version_id', $curriculumVersionId)
                    ->lockForUpdate()
                    ->first(['state', 'version']);

                if (
                    ! $workspace instanceof stdClass
                    || $this->string($workspace, 'state') !== 'draft'
                    || $this->integer($workspace, 'version')
                        !== $expectedWorkspaceVersion
                ) {
                    throw new MediaLibraryConflict(
                        MediaLibraryConflict::STALE_WORKSPACE,
                    );
                }

                $source = DB::table('content_revisions')
                    ->join('content_revision_heads', function ($join): void {
                        $join
                            ->on(
                                'content_revision_heads.organization_id',
                                '=',
                                'content_revisions.organization_id',
                            )
                            ->on(
                                'content_revision_heads.curriculum_version_id',
                                '=',
                                'content_revisions.curriculum_version_id',
                            )
                            ->on(
                                'content_revision_heads.content_document_id',
                                '=',
                                'content_revisions.content_document_id',
                            )
                            ->on(
                                'content_revision_heads.content_revision_id',
                                '=',
                                'content_revisions.id',
                            );
                    })
                    ->where('content_revisions.organization_id', $context->organizationId)
                    ->where('content_revisions.curriculum_version_id', $curriculumVersionId)
                    ->where('content_revisions.id', $sourceRevisionId)
                    ->lockForUpdate()
                    ->first([
                        'content_revisions.content_document_id',
                        'content_revisions.payload',
                    ]);

                if (
                    ! $source instanceof stdClass
                    || DB::table('content_reviews')
                        ->where('organization_id', $context->organizationId)
                        ->where('source_revision_id', $sourceRevisionId)
                        ->exists()
                    || DB::table('content_revision_media_assets')
                        ->where('organization_id', $context->organizationId)
                        ->where('curriculum_version_id', $curriculumVersionId)
                        ->where('content_revision_id', $sourceRevisionId)
                        ->where('usage_key', $usageKey)
                        ->exists()
                ) {
                    throw new MediaLibraryConflict(
                        MediaLibraryConflict::DUPLICATE_USE,
                    );
                }

                $lifecycle = DB::table('media_asset_lifecycles')
                    ->join('media_assets', function ($join): void {
                        $join
                            ->on(
                                'media_assets.organization_id',
                                '=',
                                'media_asset_lifecycles.organization_id',
                            )
                            ->on(
                                'media_assets.id',
                                '=',
                                'media_asset_lifecycles.media_asset_id',
                            );
                    })
                    ->where('media_asset_lifecycles.organization_id', $context->organizationId)
                    ->where('media_asset_lifecycles.media_asset_id', $assetId)
                    ->lockForUpdate()
                    ->first([
                        'media_asset_lifecycles.state',
                        'media_asset_lifecycles.version',
                        'media_asset_lifecycles.selected_scan_id',
                        'media_assets.asset_kind',
                        'media_assets.asset_hash',
                    ]);

                if (
                    ! $lifecycle instanceof stdClass
                    || $this->string($lifecycle, 'state') !== 'active'
                    || $this->integer($lifecycle, 'version')
                        !== $expectedLifecycleVersion
                    || $this->string($lifecycle, 'asset_kind') !== $usageKey
                    || ! $this->sourceAllowsUsage(
                        $source->payload ?? null,
                        $usageKey,
                    )
                ) {
                    throw new MediaLibraryConflict(
                        MediaLibraryConflict::STALE_LIFECYCLE,
                    );
                }

                $scanId = $this->string($lifecycle, 'selected_scan_id');
                $this->assertCleanSelectedScan(
                    $context->organizationId,
                    $assetId,
                    $scanId,
                    $this->string($lifecycle, 'asset_hash'),
                );
                $metadata = DB::table('media_asset_metadata_heads')
                    ->join('media_asset_metadata_revisions', function ($join): void {
                        $join
                            ->on(
                                'media_asset_metadata_revisions.organization_id',
                                '=',
                                'media_asset_metadata_heads.organization_id',
                            )
                            ->on(
                                'media_asset_metadata_revisions.media_asset_id',
                                '=',
                                'media_asset_metadata_heads.media_asset_id',
                            )
                            ->on(
                                'media_asset_metadata_revisions.id',
                                '=',
                                'media_asset_metadata_heads.metadata_revision_id',
                            );
                    })
                    ->where('media_asset_metadata_heads.organization_id', $context->organizationId)
                    ->where('media_asset_metadata_heads.media_asset_id', $assetId)
                    ->where('media_asset_metadata_heads.metadata_revision_id', $metadataRevisionId)
                    ->where('media_asset_metadata_revisions.content_hash', $metadataContentHash)
                    ->lockForUpdate()
                    ->first(['media_asset_metadata_revisions.id']);
                $variant = DB::table('media_asset_metadata_variant_heads')
                    ->join('media_asset_metadata_variants', function ($join): void {
                        $join
                            ->on(
                                'media_asset_metadata_variants.organization_id',
                                '=',
                                'media_asset_metadata_variant_heads.organization_id',
                            )
                            ->on(
                                'media_asset_metadata_variants.metadata_revision_id',
                                '=',
                                'media_asset_metadata_variant_heads.metadata_revision_id',
                            )
                            ->on(
                                'media_asset_metadata_variants.id',
                                '=',
                                'media_asset_metadata_variant_heads.metadata_variant_id',
                            );
                    })
                    ->where('media_asset_metadata_variant_heads.organization_id', $context->organizationId)
                    ->where('media_asset_metadata_variant_heads.metadata_revision_id', $metadataRevisionId)
                    ->where('media_asset_metadata_variant_heads.metadata_variant_id', $metadataVariantId)
                    ->where('media_asset_metadata_variants.content_hash', $variantContentHash)
                    ->where('media_asset_metadata_variants.source_content_hash', $metadataContentHash)
                    ->lockForUpdate()
                    ->first(['media_asset_metadata_variants.id']);

                if (! $metadata instanceof stdClass || ! $variant instanceof stdClass) {
                    throw new MediaLibraryConflict(
                        MediaLibraryConflict::STALE_METADATA,
                    );
                }

                $now = CarbonImmutable::now();
                $this->assertCurrentApprovedReview(
                    $context->organizationId,
                    $programId,
                    $metadataRevisionId,
                    $metadataContentHash,
                    'academic_en',
                    null,
                    null,
                    $englishReviewId,
                    $now,
                );
                $this->assertCurrentApprovedReview(
                    $context->organizationId,
                    $programId,
                    $metadataRevisionId,
                    $metadataContentHash,
                    'kannada',
                    $metadataVariantId,
                    $variantContentHash,
                    $kannadaReviewId,
                    $now,
                );
                $this->assertCurrentRights(
                    $context->organizationId,
                    $programId,
                    $assetId,
                    $this->string($lifecycle, 'asset_hash'),
                    $mediaRightsRevisionId,
                    $now,
                );
                $this->authorityEvidence
                    ->assertRightsConfirmationAuthorityRemainsValid(
                        $mediaRightsRevisionId,
                        $now,
                    );
                $actorMembershipId = $this->string($membership, 'id');
                $eventId = (string) Str::ulid();
                $successorWorkspaceVersion = $expectedWorkspaceVersion + 1;
                DB::table('content_revision_media_assets')->insert([
                    'organization_id' => $context->organizationId,
                    'curriculum_version_id' => $curriculumVersionId,
                    'content_revision_id' => $sourceRevisionId,
                    'media_asset_id' => $assetId,
                    'usage_key' => $usageKey,
                    'sequence' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $updated = DB::table('curriculum_workspaces')
                    ->where('organization_id', $context->organizationId)
                    ->where('program_id', $programId)
                    ->where('curriculum_version_id', $curriculumVersionId)
                    ->where('state', 'draft')
                    ->where('version', $expectedWorkspaceVersion)
                    ->update([
                        'version' => $successorWorkspaceVersion,
                        'updated_at' => $now,
                    ]);

                if ($updated !== 1) {
                    throw new MediaLibraryConflict(
                        MediaLibraryConflict::STALE_WORKSPACE,
                    );
                }

                DB::table('media_attachment_events')->insert([
                    'id' => $eventId,
                    'organization_id' => $context->organizationId,
                    'program_id' => $programId,
                    'curriculum_version_id' => $curriculumVersionId,
                    'source_revision_id' => $sourceRevisionId,
                    'media_asset_id' => $assetId,
                    'usage_key' => $usageKey,
                    'previous_workspace_version' => $expectedWorkspaceVersion,
                    'successor_workspace_version' => $successorWorkspaceVersion,
                    'selected_scan_id' => $scanId,
                    'metadata_revision_id' => $metadataRevisionId,
                    'english_review_id' => $englishReviewId,
                    'metadata_variant_id' => $metadataVariantId,
                    'kannada_review_id' => $kannadaReviewId,
                    'media_rights_revision_id' => $mediaRightsRevisionId,
                    'attached_by_membership_id' => $actorMembershipId,
                    'attached_at' => $now,
                    'created_at' => $now,
                ]);
                $this->idempotency->complete(
                    $claim,
                    'media_attachment_event',
                    $eventId,
                    $now,
                );
                $this->recordEvidence(
                    'media.asset.attached',
                    'media_attachment_event',
                    $eventId,
                    [
                        'media_asset_id' => $assetId,
                        'media_asset_metadata_revision_id' => $metadataRevisionId,
                        'media_rights_revision_id' => $mediaRightsRevisionId,
                        'source_revision_id' => $sourceRevisionId,
                    ],
                );

                return new MediaLibraryCommandResult(
                    'media_attachment_event',
                    $eventId,
                    false,
                    [
                        'media_attachment_event_id' => $eventId,
                        'media_asset_id' => $assetId,
                        'source_revision_id' => $sourceRevisionId,
                        'usage_key' => $usageKey,
                        'workspace_version' => $successorWorkspaceVersion,
                    ],
                );
            }, 3);
        } catch (CurriculumOperationRejected $exception) {
            throw new MediaLibraryRejected($exception);
        }
    }

    private function assertCleanSelectedScan(
        string $organizationId,
        string $assetId,
        string $scanId,
        ?string $expectedHash = null,
    ): void {
        $query = DB::table('media_security_scans')
            ->where('organization_id', $organizationId)
            ->where('id', $scanId)
            ->where('inspection_outcome', 'passed')
            ->where('malware_outcome', 'clean')
            ->where(function ($query) use ($assetId): void {
                $query
                    ->where('media_asset_id', $assetId)
                    ->orWhereExists(function ($query) use ($assetId): void {
                        $query
                            ->select(DB::raw('1'))
                            ->from('media_uploads')
                            ->whereColumn(
                                'media_uploads.organization_id',
                                'media_security_scans.organization_id',
                            )
                            ->whereColumn(
                                'media_uploads.id',
                                'media_security_scans.media_upload_id',
                            )
                            ->where('media_uploads.accepted_asset_id', $assetId);
                    });
            });

        if ($expectedHash !== null) {
            $query->where('object_hash', $expectedHash);
        }

        if (! $query->exists()) {
            throw new MediaLibraryRejected;
        }
    }

    private function assertCurrentApprovedReview(
        string $organizationId,
        string $programId,
        string $metadataRevisionId,
        string $sourceHash,
        string $reviewKind,
        ?string $variantId,
        ?string $variantHash,
        string $expectedReviewId,
        CarbonInterface $at,
    ): void {
        $query = DB::table('media_asset_metadata_reviews')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('metadata_revision_id', $metadataRevisionId)
            ->where('source_content_hash', $sourceHash)
            ->where('review_kind', $reviewKind);

        if ($variantId === null) {
            $query->whereNull('metadata_variant_id')
                ->whereNull('variant_content_hash');
        } else {
            $query->where('metadata_variant_id', $variantId)
                ->where('variant_content_hash', $variantHash);
        }

        $review = $query
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->first([
                'id',
                'decision',
                'authority_appointment_id',
                'authority_appointment_event_id',
                'reviewer_membership_id',
                'reviewed_at',
            ]);

        if (
            ! $review instanceof stdClass
            || ! hash_equals($this->string($review, 'id'), $expectedReviewId)
            || $this->string($review, 'decision') !== 'approved'
        ) {
            throw new MediaLibraryRejected;
        }

        $this->assertReviewAuthority(
            $organizationId,
            $programId,
            $reviewKind,
            $review,
            $at,
        );
    }

    private function assertReviewAuthority(
        string $organizationId,
        string $programId,
        string $reviewKind,
        stdClass $review,
        CarbonInterface $evaluatedAt,
    ): void {
        $appointmentId = $this->string(
            $review,
            'authority_appointment_id',
        );
        $reviewerMembershipId = $this->string(
            $review,
            'reviewer_membership_id',
        );
        $reviewedAt = $this->date($review, 'reviewed_at');
        $appointment = DB::table('authority_appointments')
            ->where('organization_id', $organizationId)
            ->where('id', $appointmentId)
            ->where(
                'authority_kind',
                $reviewKind === 'academic_en'
                    ? 'english_academic_reviewer'
                    : 'kannada_reviewer',
            )
            ->where('appointee_membership_id', $reviewerMembershipId)
            ->where('valid_from', '<=', $reviewedAt)
            ->where(function ($query) use ($reviewedAt): void {
                $query
                    ->whereNull('valid_until')
                    ->orWhere('valid_until', '>', $reviewedAt);
            })
            ->first(['scope_type', 'program_id']);
        $accepted = DB::table('authority_appointment_events')
            ->where('organization_id', $organizationId)
            ->where('authority_appointment_id', $appointmentId)
            ->where(
                'id',
                $this->string($review, 'authority_appointment_event_id'),
            )
            ->where('event_type', 'accepted')
            ->where('effective_at', '<=', $reviewedAt)
            ->exists();
        $invalidated = DB::table('authority_appointment_events')
            ->where('organization_id', $organizationId)
            ->where('authority_appointment_id', $appointmentId)
            ->where('event_type', 'revoked_for_cause')
            ->where('invalidates_prior_approvals', true)
            ->where('effective_at', '<=', $evaluatedAt)
            ->exists();
        $terminatedAtEvidence = DB::table('authority_appointment_events')
            ->where('organization_id', $organizationId)
            ->where('authority_appointment_id', $appointmentId)
            ->whereIn(
                'event_type',
                ['surrendered', 'revoked', 'expired', 'revoked_for_cause'],
            )
            ->where('effective_at', '<=', $reviewedAt)
            ->exists();

        if (
            ! $appointment instanceof stdClass
            || ! $accepted
            || $invalidated
            || $terminatedAtEvidence
            || ! $this->appointmentScopeAllows($appointment, $programId)
        ) {
            throw new MediaLibraryRejected;
        }
    }

    private function appointmentScopeAllows(
        stdClass $appointment,
        string $programId,
    ): bool {
        $scope = $this->string($appointment, 'scope_type');
        $appointmentProgram = $this->nullableString($appointment, 'program_id');

        return ($scope === 'organization' && $appointmentProgram === null)
            || ($scope === 'program'
                && $appointmentProgram !== null
                && hash_equals($appointmentProgram, $programId));
    }

    private function assertCurrentRights(
        string $organizationId,
        string $programId,
        string $assetId,
        string $assetHash,
        string $expectedRightsRevisionId,
        CarbonInterface $at,
    ): void {
        $rights = DB::table('media_rights_revisions')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('media_asset_id', $assetId)
            ->orderByDesc('revision_number')
            ->orderByDesc('id')
            ->first([
                'id',
                'evidence_status',
                'selected_asset_hash',
                'permitted_release_channels',
                'permitted_territories',
                'learner_delivery_allowed',
                'effective_at',
                'expires_at',
            ]);

        if (
            ! $rights instanceof stdClass
            || ! hash_equals(
                $this->string($rights, 'id'),
                $expectedRightsRevisionId,
            )
            || $this->string($rights, 'evidence_status') !== 'approved'
            || ! hash_equals(
                $this->string($rights, 'selected_asset_hash'),
                $assetHash,
            )
            || ! $this->boolean($rights, 'learner_delivery_allowed')
            || $this->date($rights, 'effective_at')->greaterThan($at)
        ) {
            throw new MediaLibraryRejected;
        }

        $expiresAt = $this->nullableString($rights, 'expires_at');

        if (
            $expiresAt !== null
            && CarbonImmutable::parse($expiresAt)->lessThanOrEqualTo($at)
        ) {
            throw new MediaLibraryRejected;
        }

        $channels = $this->jsonList(
            $rights,
            'permitted_release_channels',
        );
        $territories = $this->jsonList(
            $rights,
            'permitted_territories',
        );

        if (
            array_diff(['android', 'web'], $channels) !== []
            || ! in_array('IN', $territories, true)
        ) {
            throw new MediaLibraryRejected;
        }
    }

    private function sourceAllowsUsage(mixed $payload, string $usageKey): bool
    {
        $decoded = is_string($payload)
            ? json_decode($payload, true)
            : $payload;

        if (! is_array($decoded) || array_is_list($decoded)) {
            return false;
        }

        return match ($usageKey) {
            'model_audio' => ($decoded['schema'] ?? null)
                === 'global-english.daily-speaking-mission.v1',
            'lesson_video' => ($decoded['schema'] ?? null)
                === 'global-english.level-video.v1',
            default => false,
        };
    }

    private function wouldCreateReplacementCycle(
        string $organizationId,
        string $predecessorAssetId,
        string $successorAssetId,
    ): bool {
        $rows = DB::table('media_asset_replacements')
            ->where('organization_id', $organizationId)
            ->get([
                'predecessor_media_asset_id',
                'successor_media_asset_id',
            ]);
        $successors = [];

        foreach ($rows as $row) {
            $successors[$this->string(
                $row,
                'predecessor_media_asset_id',
            )][] = $this->string($row, 'successor_media_asset_id');
        }

        $pending = [$successorAssetId];
        $seen = [];

        while ($pending !== []) {
            $current = array_pop($pending);

            if (isset($seen[$current])) {
                continue;
            }

            $seen[$current] = true;

            if (hash_equals($current, $predecessorAssetId)) {
                return true;
            }

            foreach ($successors[$current] ?? [] as $next) {
                $pending[] = $next;
            }
        }

        return false;
    }

    private function assetEvent(
        string $organizationId,
        string $assetId,
        string $eventType,
        int $lifecycleVersion,
        string $actorMembershipId,
        CarbonInterface $at,
        string $scanId,
        ?string $relatedAssetId = null,
        ?string $reasonKey = null,
    ): void {
        DB::table('media_asset_events')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'media_asset_id' => $assetId,
            'event_type' => $eventType,
            'lifecycle_version' => $lifecycleVersion,
            'selected_scan_id' => $scanId,
            'related_media_asset_id' => $relatedAssetId,
            'media_upload_id' => null,
            'actor_membership_id' => $actorMembershipId,
            'reason_key' => $reasonKey,
            'occurred_at' => $at,
            'created_at' => $at,
        ]);
    }

    /** @param array<string, string> $payload */
    private function recordEvidence(
        string $eventType,
        string $subjectType,
        string $subjectId,
        array $payload,
    ): void {
        $this->audit->recordTenant($eventType, $subjectType, $subjectId);
        $this->outbox->recordTenant(
            $eventType,
            $subjectType,
            $subjectId,
            $payload,
        );
    }

    /** @return list<string> */
    private function jsonList(stdClass $row, string $property): array
    {
        $value = $row->{$property} ?? null;
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new MediaLibraryRejected;
        }

        $result = [];

        foreach ($decoded as $item) {
            if (! is_string($item)) {
                throw new MediaLibraryRejected;
            }

            $result[] = $item;
        }

        return $result;
    }

    private function safeKey(string $value): string
    {
        if (preg_match('/\A[a-z][a-z0-9_.-]{0,99}\z/', $value) !== 1) {
            throw new MediaLibraryRejected;
        }

        return $value;
    }

    private function string(stdClass $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        if (! is_string($value) || $value === '') {
            throw new MediaLibraryRejected;
        }

        return $value;
    }

    private function nullableString(stdClass $row, string $property): ?string
    {
        $value = $row->{$property} ?? null;

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function integer(stdClass $row, string $property): int
    {
        $value = $row->{$property} ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new MediaLibraryRejected;
    }

    private function boolean(stdClass $row, string $property): bool
    {
        $value = $row->{$property} ?? null;

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1') {
            return true;
        }

        if ($value === 0 || $value === '0') {
            return false;
        }

        throw new MediaLibraryRejected;
    }

    private function date(stdClass $row, string $property): CarbonImmutable
    {
        $value = $row->{$property} ?? null;

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value) && $value !== '') {
            return CarbonImmutable::parse($value);
        }

        throw new MediaLibraryRejected;
    }
}

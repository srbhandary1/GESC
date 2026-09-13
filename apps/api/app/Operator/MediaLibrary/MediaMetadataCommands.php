<?php

declare(strict_types=1);

namespace App\Operator\MediaLibrary;

use App\Audit\AuditEventRecorder;
use App\Curriculum\CanonicalJson;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Governance\AuthorityEvidenceGate;
use App\Curriculum\Governance\AuthorityKind;
use App\Media\MediaKindPolicy;
use App\Models\User;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryConflict;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryRejected;
use App\Outbox\OutboxEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Normalizer;
use stdClass;

final readonly class MediaMetadataCommands
{
    public function __construct(
        private MediaLibraryAuthorization $authorization,
        private MediaLibraryIdempotency $idempotency,
        private AuthorityEvidenceGate $authority,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function appendEnglish(
        User $actor,
        string $assetId,
        ?string $expectedHeadRevisionId,
        int $expectedMetadataVersion,
        string $operatorLabel,
        string $transcript,
        ?string $textAlternative,
        string $idempotencyKey,
    ): MediaLibraryCommandResult {
        return DB::transaction(function () use (
            $actor,
            $assetId,
            $expectedHeadRevisionId,
            $expectedMetadataVersion,
            $operatorLabel,
            $transcript,
            $textAlternative,
            $idempotencyKey,
        ): MediaLibraryCommandResult {
            $this->authorization->assert($actor, 'media.metadata.manage');
            $context = $this->authorization->context($actor);
            $membership = $this->authorization->actorMembership($actor);
            $asset = $this->activeAsset($context->organizationId, $assetId);
            $assetKind = $this->string($asset, 'asset_kind');
            $operatorLabel = $this->text($operatorLabel, 160, 640);
            $transcript = $this->text($transcript, 32_000, 65_536);
            $textAlternative = $this->accessibilityTextAlternative(
                $assetKind,
                $textAlternative,
            );
            $operation = 'media.metadata.english.append';
            $fingerprint = $this->idempotency->fingerprint(
                $operation,
                'media_asset',
                $assetId,
                [
                    'expected_head_revision_id' => $expectedHeadRevisionId,
                    'expected_metadata_version' => $expectedMetadataVersion,
                    'operator_label' => $operatorLabel,
                    'text_alternative' => $textAlternative,
                    'transcript' => $transcript,
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
                $revisionId = $claim->resultId
                    ?? throw new MediaLibraryConflict(
                        MediaLibraryConflict::IDEMPOTENCY,
                    );

                return new MediaLibraryCommandResult(
                    'media_asset_metadata_revision',
                    $revisionId,
                    true,
                    $this->metadataSummary(
                        $context->organizationId,
                        $assetId,
                        $revisionId,
                    ),
                );
            }

            $head = DB::table('media_asset_metadata_heads')
                ->where('organization_id', $context->organizationId)
                ->where('media_asset_id', $assetId)
                ->lockForUpdate()
                ->first(['metadata_revision_id', 'version', 'created_at']);

            if ($head instanceof stdClass) {
                if (
                    $expectedHeadRevisionId === null
                    || ! hash_equals(
                        $this->string($head, 'metadata_revision_id'),
                        $expectedHeadRevisionId,
                    )
                    || $this->integer($head, 'version')
                        !== $expectedMetadataVersion
                ) {
                    throw new MediaLibraryConflict(
                        MediaLibraryConflict::STALE_METADATA,
                    );
                }
            } elseif (
                $expectedHeadRevisionId !== null
                || $expectedMetadataVersion !== 0
            ) {
                throw new MediaLibraryConflict(
                    MediaLibraryConflict::STALE_METADATA,
                );
            }

            $revisionNumber = $expectedMetadataVersion + 1;
            $revisionId = (string) Str::ulid();
            $contentHash = CanonicalJson::sha256([
                'asset_kind' => $assetKind,
                'locale' => 'en-IN',
                'operator_label' => $operatorLabel,
                'text_alternative' => $textAlternative,
                'transcript' => $transcript,
            ]);
            $now = CarbonImmutable::now();
            $actorMembershipId = $this->string($membership, 'id');
            DB::table('media_asset_metadata_revisions')->insert([
                'id' => $revisionId,
                'organization_id' => $context->organizationId,
                'media_asset_id' => $assetId,
                'asset_kind' => $assetKind,
                'parent_revision_id' => $expectedHeadRevisionId,
                'revision_number' => $revisionNumber,
                'operator_label' => $operatorLabel,
                'transcript' => $transcript,
                'text_alternative' => $textAlternative,
                'content_hash' => $contentHash,
                'authored_by_membership_id' => $actorMembershipId,
                'authored_at' => $now,
                'created_at' => $now,
            ]);

            if ($head instanceof stdClass) {
                $updated = DB::table('media_asset_metadata_heads')
                    ->where('organization_id', $context->organizationId)
                    ->where('media_asset_id', $assetId)
                    ->where('version', $expectedMetadataVersion)
                    ->where('metadata_revision_id', $expectedHeadRevisionId)
                    ->update([
                        'metadata_revision_id' => $revisionId,
                        'version' => $revisionNumber,
                        'updated_by_membership_id' => $actorMembershipId,
                        'updated_at' => $now,
                    ]);

                if ($updated !== 1) {
                    throw new MediaLibraryConflict(
                        MediaLibraryConflict::STALE_METADATA,
                    );
                }
            } else {
                DB::table('media_asset_metadata_heads')->insert([
                    'organization_id' => $context->organizationId,
                    'media_asset_id' => $assetId,
                    'metadata_revision_id' => $revisionId,
                    'version' => 1,
                    'updated_by_membership_id' => $actorMembershipId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->idempotency->complete(
                $claim,
                'media_asset_metadata_revision',
                $revisionId,
                $now,
            );
            $this->recordEvidence(
                'media.metadata.english_appended',
                'media_asset_metadata_revision',
                $revisionId,
                [
                    'media_asset_id' => $assetId,
                    'media_asset_metadata_revision_id' => $revisionId,
                ],
            );

            return new MediaLibraryCommandResult(
                'media_asset_metadata_revision',
                $revisionId,
                false,
                [
                    'media_asset_id' => $assetId,
                    'metadata_revision_id' => $revisionId,
                    'metadata_version' => $revisionNumber,
                    'content_hash' => $contentHash,
                ],
            );
        }, 3);
    }

    public function appendKannada(
        User $actor,
        string $metadataRevisionId,
        string $sourceContentHash,
        ?string $expectedHeadVariantId,
        int $expectedVariantVersion,
        string $transcript,
        ?string $textAlternative,
        string $idempotencyKey,
    ): MediaLibraryCommandResult {
        return DB::transaction(function () use (
            $actor,
            $metadataRevisionId,
            $sourceContentHash,
            $expectedHeadVariantId,
            $expectedVariantVersion,
            $transcript,
            $textAlternative,
            $idempotencyKey,
        ): MediaLibraryCommandResult {
            $this->authorization->assert($actor, 'media.metadata.manage');
            $this->authorization->assert($actor, 'curriculum.translate.kn');
            $context = $this->authorization->context($actor);
            $membership = $this->authorization->actorMembership($actor);
            $source = DB::table('media_asset_metadata_revisions')
                ->join('media_asset_metadata_heads', function ($join): void {
                    $join
                        ->on(
                            'media_asset_metadata_heads.organization_id',
                            '=',
                            'media_asset_metadata_revisions.organization_id',
                        )
                        ->on(
                            'media_asset_metadata_heads.media_asset_id',
                            '=',
                            'media_asset_metadata_revisions.media_asset_id',
                        )
                        ->on(
                            'media_asset_metadata_heads.metadata_revision_id',
                            '=',
                            'media_asset_metadata_revisions.id',
                        );
                })
                ->where(
                    'media_asset_metadata_revisions.organization_id',
                    $context->organizationId,
                )
                ->where('media_asset_metadata_revisions.id', $metadataRevisionId)
                ->where(
                    'media_asset_metadata_revisions.content_hash',
                    $sourceContentHash,
                )
                ->lockForUpdate()
                ->first([
                    'media_asset_metadata_revisions.media_asset_id',
                    'media_asset_metadata_revisions.asset_kind',
                    'media_asset_metadata_revisions.content_hash',
                    'media_asset_metadata_revisions.authored_by_membership_id',
                ]);

            if (! $source instanceof stdClass) {
                throw new MediaLibraryRejected;
            }

            $assetId = $this->string($source, 'media_asset_id');
            $this->activeAsset($context->organizationId, $assetId);
            $this->assertDifferentHuman(
                $context->organizationId,
                $this->string($source, 'authored_by_membership_id'),
                $this->string($membership, 'id'),
            );
            $assetKind = $this->string($source, 'asset_kind');
            $transcript = $this->text($transcript, 32_000, 65_536);
            $textAlternative = $this->accessibilityTextAlternative(
                $assetKind,
                $textAlternative,
            );
            $operation = 'media.metadata.kannada.append';
            $fingerprint = $this->idempotency->fingerprint(
                $operation,
                'media_asset_metadata_revision',
                $metadataRevisionId,
                [
                    'expected_head_variant_id' => $expectedHeadVariantId,
                    'expected_variant_version' => $expectedVariantVersion,
                    'source_content_hash' => $sourceContentHash,
                    'text_alternative' => $textAlternative,
                    'transcript' => $transcript,
                ],
            );
            $claim = $this->idempotency->claim(
                $actor,
                $idempotencyKey,
                $operation,
                'media_asset_metadata_revision',
                $metadataRevisionId,
                $fingerprint,
            );

            if ($claim->replayed) {
                $variantId = $claim->resultId
                    ?? throw new MediaLibraryConflict(
                        MediaLibraryConflict::IDEMPOTENCY,
                    );

                return new MediaLibraryCommandResult(
                    'media_asset_metadata_variant',
                    $variantId,
                    true,
                    $this->variantSummary(
                        $context->organizationId,
                        $metadataRevisionId,
                        $variantId,
                    ),
                );
            }

            $head = DB::table('media_asset_metadata_variant_heads')
                ->where('organization_id', $context->organizationId)
                ->where('metadata_revision_id', $metadataRevisionId)
                ->lockForUpdate()
                ->first(['metadata_variant_id', 'version']);

            if ($head instanceof stdClass) {
                if (
                    $expectedHeadVariantId === null
                    || ! hash_equals(
                        $this->string($head, 'metadata_variant_id'),
                        $expectedHeadVariantId,
                    )
                    || $this->integer($head, 'version')
                        !== $expectedVariantVersion
                ) {
                    throw new MediaLibraryConflict(
                        MediaLibraryConflict::STALE_METADATA,
                    );
                }
            } elseif (
                $expectedHeadVariantId !== null
                || $expectedVariantVersion !== 0
            ) {
                throw new MediaLibraryConflict(
                    MediaLibraryConflict::STALE_METADATA,
                );
            }

            $variantId = (string) Str::ulid();
            $revisionNumber = $expectedVariantVersion + 1;
            $contentHash = CanonicalJson::sha256([
                'asset_kind' => $assetKind,
                'locale' => 'kn-IN',
                'source_content_hash' => $sourceContentHash,
                'text_alternative' => $textAlternative,
                'transcript' => $transcript,
            ]);
            $now = CarbonImmutable::now();
            $actorMembershipId = $this->string($membership, 'id');
            DB::table('media_asset_metadata_variants')->insert([
                'id' => $variantId,
                'organization_id' => $context->organizationId,
                'media_asset_id' => $assetId,
                'metadata_revision_id' => $metadataRevisionId,
                'asset_kind' => $assetKind,
                'locale' => 'kn-IN',
                'parent_variant_id' => $expectedHeadVariantId,
                'revision_number' => $revisionNumber,
                'source_content_hash' => $sourceContentHash,
                'transcript' => $transcript,
                'text_alternative' => $textAlternative,
                'content_hash' => $contentHash,
                'translated_by_membership_id' => $actorMembershipId,
                'translated_at' => $now,
                'created_at' => $now,
            ]);

            if ($head instanceof stdClass) {
                $updated = DB::table('media_asset_metadata_variant_heads')
                    ->where('organization_id', $context->organizationId)
                    ->where('metadata_revision_id', $metadataRevisionId)
                    ->where('version', $expectedVariantVersion)
                    ->where('metadata_variant_id', $expectedHeadVariantId)
                    ->update([
                        'metadata_variant_id' => $variantId,
                        'version' => $revisionNumber,
                        'updated_by_membership_id' => $actorMembershipId,
                        'updated_at' => $now,
                    ]);

                if ($updated !== 1) {
                    throw new MediaLibraryConflict(
                        MediaLibraryConflict::STALE_METADATA,
                    );
                }
            } else {
                DB::table('media_asset_metadata_variant_heads')->insert([
                    'organization_id' => $context->organizationId,
                    'metadata_revision_id' => $metadataRevisionId,
                    'metadata_variant_id' => $variantId,
                    'version' => 1,
                    'updated_by_membership_id' => $actorMembershipId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->idempotency->complete(
                $claim,
                'media_asset_metadata_variant',
                $variantId,
                $now,
            );
            $this->recordEvidence(
                'media.metadata.kannada_appended',
                'media_asset_metadata_variant',
                $variantId,
                [
                    'media_asset_id' => $assetId,
                    'media_asset_metadata_revision_id' => $metadataRevisionId,
                    'media_asset_metadata_variant_id' => $variantId,
                ],
            );

            return new MediaLibraryCommandResult(
                'media_asset_metadata_variant',
                $variantId,
                false,
                [
                    'media_asset_id' => $assetId,
                    'metadata_revision_id' => $metadataRevisionId,
                    'metadata_variant_id' => $variantId,
                    'variant_version' => $revisionNumber,
                    'content_hash' => $contentHash,
                ],
            );
        }, 3);
    }

    public function review(
        User $actor,
        string $programId,
        string $metadataRevisionId,
        string $sourceContentHash,
        string $reviewKind,
        ?string $metadataVariantId,
        ?string $variantContentHash,
        string $decision,
        ?string $rationaleKey,
        string $authorityAppointmentId,
        string $idempotencyKey,
    ): MediaLibraryCommandResult {
        try {
            return DB::transaction(function () use (
                $actor,
                $programId,
                $metadataRevisionId,
                $sourceContentHash,
                $reviewKind,
                $metadataVariantId,
                $variantContentHash,
                $decision,
                $rationaleKey,
                $authorityAppointmentId,
                $idempotencyKey,
            ): MediaLibraryCommandResult {
                $permission = match ($reviewKind) {
                    'academic_en' => 'curriculum.review.en',
                    'kannada' => 'curriculum.review.kn',
                    default => throw new MediaLibraryRejected,
                };
                $authorityKind = $reviewKind === 'academic_en'
                    ? AuthorityKind::EnglishAcademicReviewer
                    : AuthorityKind::KannadaReviewer;
                $this->authorization->assert($actor, $permission);
                $context = $this->authorization->context($actor);
                $membership = $this->authorization->actorMembership($actor);
                $decision = $this->decision($decision);
                $rationaleKey = $rationaleKey === null
                    ? null
                    : $this->safeKey($rationaleKey);

                if (
                    in_array($decision, ['rejected', 'withdrawn'], true)
                    && $rationaleKey === null
                ) {
                    throw new MediaLibraryRejected;
                }

                $source = DB::table('media_asset_metadata_revisions')
                    ->join('media_asset_metadata_heads', function ($join): void {
                        $join
                            ->on(
                                'media_asset_metadata_heads.organization_id',
                                '=',
                                'media_asset_metadata_revisions.organization_id',
                            )
                            ->on(
                                'media_asset_metadata_heads.media_asset_id',
                                '=',
                                'media_asset_metadata_revisions.media_asset_id',
                            )
                            ->on(
                                'media_asset_metadata_heads.metadata_revision_id',
                                '=',
                                'media_asset_metadata_revisions.id',
                            );
                    })
                    ->where(
                        'media_asset_metadata_revisions.organization_id',
                        $context->organizationId,
                    )
                    ->where('media_asset_metadata_revisions.id', $metadataRevisionId)
                    ->where(
                        'media_asset_metadata_revisions.content_hash',
                        $sourceContentHash,
                    )
                    ->lockForUpdate()
                    ->first([
                        'media_asset_metadata_revisions.media_asset_id',
                        'media_asset_metadata_revisions.authored_by_membership_id',
                        'media_asset_metadata_revisions.transcript',
                    ]);

                if (
                    ! $source instanceof stdClass
                    || ! DB::table('programs')
                        ->where('organization_id', $context->organizationId)
                        ->where('id', $programId)
                        ->exists()
                ) {
                    throw new MediaLibraryRejected;
                }

                $assetId = $this->string($source, 'media_asset_id');
                $this->activeAsset($context->organizationId, $assetId);
                $creatorMembershipId = $this->string(
                    $source,
                    'authored_by_membership_id',
                );

                if ($reviewKind === 'academic_en') {
                    if ($metadataVariantId !== null || $variantContentHash !== null) {
                        throw new MediaLibraryRejected;
                    }
                    if (
                        $decision === 'approved'
                        && $this->containsUnresolvedTranscriptMarker(
                            $this->string($source, 'transcript'),
                        )
                    ) {
                        throw new MediaLibraryRejected;
                    }
                } else {
                    $variant = DB::table('media_asset_metadata_variants')
                        ->join(
                            'media_asset_metadata_variant_heads',
                            function ($join): void {
                                $join
                                    ->on(
                                        'media_asset_metadata_variant_heads.organization_id',
                                        '=',
                                        'media_asset_metadata_variants.organization_id',
                                    )
                                    ->on(
                                        'media_asset_metadata_variant_heads.metadata_revision_id',
                                        '=',
                                        'media_asset_metadata_variants.metadata_revision_id',
                                    )
                                    ->on(
                                        'media_asset_metadata_variant_heads.metadata_variant_id',
                                        '=',
                                        'media_asset_metadata_variants.id',
                                    );
                            },
                        )
                        ->where(
                            'media_asset_metadata_variants.organization_id',
                            $context->organizationId,
                        )
                        ->where(
                            'media_asset_metadata_variants.metadata_revision_id',
                            $metadataRevisionId,
                        )
                        ->where('media_asset_metadata_variants.id', $metadataVariantId)
                        ->where(
                            'media_asset_metadata_variants.content_hash',
                            $variantContentHash,
                        )
                        ->lockForUpdate()
                        ->first([
                            'media_asset_metadata_variants.translated_by_membership_id',
                        ]);

                    if (! $variant instanceof stdClass) {
                        throw new MediaLibraryRejected;
                    }

                    $creatorMembershipId = $this->string(
                        $variant,
                        'translated_by_membership_id',
                    );
                }

                $this->assertDifferentHuman(
                    $context->organizationId,
                    $creatorMembershipId,
                    $this->string($membership, 'id'),
                );
                $operation = 'media.metadata.review.'.$reviewKind;
                $fingerprint = $this->idempotency->fingerprint(
                    $operation,
                    'media_asset_metadata_revision',
                    $metadataRevisionId,
                    [
                        'authority_appointment_id' => $authorityAppointmentId,
                        'decision' => $decision,
                        'metadata_variant_id' => $metadataVariantId,
                        'program_id' => $programId,
                        'rationale_key' => $rationaleKey,
                        'review_kind' => $reviewKind,
                        'source_content_hash' => $sourceContentHash,
                        'variant_content_hash' => $variantContentHash,
                    ],
                );
                $claim = $this->idempotency->claim(
                    $actor,
                    $idempotencyKey,
                    $operation,
                    'media_asset_metadata_revision',
                    $metadataRevisionId,
                    $fingerprint,
                );

                if ($claim->replayed) {
                    $reviewId = $claim->resultId
                        ?? throw new MediaLibraryConflict(
                            MediaLibraryConflict::IDEMPOTENCY,
                        );

                    return new MediaLibraryCommandResult(
                        'media_asset_metadata_review',
                        $reviewId,
                        true,
                        ['media_asset_metadata_review_id' => $reviewId],
                    );
                }

                $latest = $this->latestReview(
                    $context->organizationId,
                    $programId,
                    $metadataRevisionId,
                    $reviewKind,
                    $metadataVariantId,
                );

                if (
                    ($decision === 'withdrawn'
                        && (! $latest instanceof stdClass
                            || $this->string($latest, 'decision') !== 'approved'))
                    || ($latest instanceof stdClass
                        && $this->string($latest, 'decision') === $decision)
                ) {
                    throw new MediaLibraryConflict(
                        MediaLibraryConflict::STALE_METADATA,
                    );
                }

                $now = CarbonImmutable::now();
                $accepted = $this->authority->acceptedForNewEvidence(
                    actor: $actor,
                    appointmentId: $authorityAppointmentId,
                    authorityKind: $authorityKind,
                    programId: $programId,
                    at: $now,
                );
                $reviewId = (string) Str::ulid();
                DB::table('media_asset_metadata_reviews')->insert([
                    'id' => $reviewId,
                    'organization_id' => $context->organizationId,
                    'media_asset_id' => $assetId,
                    'program_id' => $programId,
                    'metadata_revision_id' => $metadataRevisionId,
                    'source_content_hash' => $sourceContentHash,
                    'review_kind' => $reviewKind,
                    'metadata_variant_id' => $metadataVariantId,
                    'variant_content_hash' => $variantContentHash,
                    'decision' => $decision,
                    'rationale_key' => $rationaleKey,
                    'authority_appointment_id' => $accepted->appointmentId,
                    'authority_appointment_event_id' => $accepted->acceptanceEventId,
                    'reviewer_membership_id' => $accepted->appointeeMembershipId,
                    'reviewed_at' => $now,
                    'created_at' => $now,
                ]);
                $this->idempotency->complete(
                    $claim,
                    'media_asset_metadata_review',
                    $reviewId,
                    $now,
                );
                $this->recordEvidence(
                    'media.metadata.reviewed',
                    'media_asset_metadata_review',
                    $reviewId,
                    [
                        'media_asset_id' => $assetId,
                        'media_asset_metadata_review_id' => $reviewId,
                        'media_asset_metadata_revision_id' => $metadataRevisionId,
                    ],
                );

                return new MediaLibraryCommandResult(
                    'media_asset_metadata_review',
                    $reviewId,
                    false,
                    [
                        'media_asset_id' => $assetId,
                        'media_asset_metadata_review_id' => $reviewId,
                        'program_id' => $programId,
                        'review_kind' => $reviewKind,
                        'decision' => $decision,
                    ],
                );
            }, 3);
        } catch (CurriculumOperationRejected $exception) {
            throw new MediaLibraryRejected($exception);
        }
    }

    private function activeAsset(string $organizationId, string $assetId): stdClass
    {
        $asset = DB::table('media_assets')
            ->join('media_asset_lifecycles', function ($join): void {
                $join
                    ->on(
                        'media_asset_lifecycles.organization_id',
                        '=',
                        'media_assets.organization_id',
                    )
                    ->on(
                        'media_asset_lifecycles.media_asset_id',
                        '=',
                        'media_assets.id',
                    );
            })
            ->where('media_assets.organization_id', $organizationId)
            ->where('media_assets.id', $assetId)
            ->where('media_asset_lifecycles.state', 'active')
            ->lockForUpdate()
            ->first([
                'media_assets.asset_kind',
                'media_assets.asset_hash',
                'media_asset_lifecycles.selected_scan_id',
                'media_asset_lifecycles.version',
            ]);

        if (! $asset instanceof stdClass) {
            throw new MediaLibraryRejected;
        }

        return $asset;
    }

    private function containsUnresolvedTranscriptMarker(string $transcript): bool
    {
        return preg_match('/\[inaudible(?:\s[^\]\r\n]*)?\]/iu', $transcript) === 1;
    }

    private function latestReview(
        string $organizationId,
        string $programId,
        string $metadataRevisionId,
        string $reviewKind,
        ?string $metadataVariantId,
    ): ?stdClass {
        $query = DB::table('media_asset_metadata_reviews')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('metadata_revision_id', $metadataRevisionId)
            ->where('review_kind', $reviewKind);

        if ($metadataVariantId === null) {
            $query->whereNull('metadata_variant_id');
        } else {
            $query->where('metadata_variant_id', $metadataVariantId);
        }

        return $query
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first(['id', 'decision']);
    }

    private function assertDifferentHuman(
        string $organizationId,
        string $creatorMembershipId,
        string $reviewerMembershipId,
    ): void {
        $memberships = DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->whereIn('id', [$creatorMembershipId, $reviewerMembershipId])
            ->pluck('user_id', 'id');
        $creator = $memberships->get($creatorMembershipId);
        $reviewer = $memberships->get($reviewerMembershipId);

        if (
            ! is_string($creator)
            || ! is_string($reviewer)
            || hash_equals($creator, $reviewer)
        ) {
            throw new MediaLibraryRejected;
        }
    }

    private function accessibilityTextAlternative(
        string $assetKind,
        ?string $value,
    ): ?string {
        if (MediaKindPolicy::isAudio($assetKind)) {
            if ($value !== null && trim($value) !== '') {
                throw new MediaLibraryRejected;
            }

            return null;
        }

        if ($assetKind !== MediaKindPolicy::LESSON_VIDEO || $value === null) {
            throw new MediaLibraryRejected;
        }

        return $this->text($value, 2_000, 8_000);
    }

    private function text(
        string $value,
        int $maximumScalars,
        int $maximumBytes,
    ): string {
        $normalized = Normalizer::normalize(trim($value), Normalizer::FORM_C);

        if (
            ! is_string($normalized)
            || $normalized === ''
            || mb_strlen($normalized) > $maximumScalars
            || strlen($normalized) > $maximumBytes
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $normalized) === 1
            || preg_match('/<[^>]+>/u', $normalized) === 1
            || preg_match('/(?:javascript|data|https?):/iu', $normalized) === 1
        ) {
            throw new MediaLibraryRejected;
        }

        return $normalized;
    }

    private function decision(string $decision): string
    {
        if (! in_array($decision, ['approved', 'rejected', 'withdrawn'], true)) {
            throw new MediaLibraryRejected;
        }

        return $decision;
    }

    private function safeKey(string $value): string
    {
        if (preg_match('/\A[a-z][a-z0-9_.-]{0,99}\z/', $value) !== 1) {
            throw new MediaLibraryRejected;
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function metadataSummary(
        string $organizationId,
        string $assetId,
        string $revisionId,
    ): array {
        $row = DB::table('media_asset_metadata_revisions')
            ->where('organization_id', $organizationId)
            ->where('media_asset_id', $assetId)
            ->where('id', $revisionId)
            ->first([
                'media_asset_id',
                'id as metadata_revision_id',
                'revision_number as metadata_version',
                'content_hash',
            ]);

        if (! $row instanceof stdClass) {
            throw new MediaLibraryRejected;
        }

        return (array) $row;
    }

    /** @return array<string, mixed> */
    private function variantSummary(
        string $organizationId,
        string $metadataRevisionId,
        string $variantId,
    ): array {
        $row = DB::table('media_asset_metadata_variants')
            ->where('organization_id', $organizationId)
            ->where('metadata_revision_id', $metadataRevisionId)
            ->where('id', $variantId)
            ->first([
                'media_asset_id',
                'metadata_revision_id',
                'id as metadata_variant_id',
                'revision_number as variant_version',
                'content_hash',
            ]);

        if (! $row instanceof stdClass) {
            throw new MediaLibraryRejected;
        }

        return (array) $row;
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

    private function string(stdClass $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        if (! is_string($value) || $value === '') {
            throw new MediaLibraryRejected;
        }

        return $value;
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
}

<?php

declare(strict_types=1);

namespace App\Curriculum\LessonPractice;

use App\Curriculum\CanonicalJson;
use App\Curriculum\LessonPractice\Exceptions\LessonPracticeIdempotencyConflict;
use App\Curriculum\LessonPractice\Exceptions\LessonPracticeOperationRejected;
use App\Models\LessonPracticeBlueprint;
use App\Models\LessonPracticeMedia;
use App\Models\LessonPracticeReview;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

final class LessonPracticeAttachmentService
{
    public function __construct(
        private readonly LessonPracticeBlueprintValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $canonical
     * @return array{blueprint: LessonPracticeBlueprint, created: bool}
     */
    public function createBlueprint(
        OrganizationContext $context,
        string $programId,
        string $curriculumVersionId,
        string $conceptKey,
        array $canonical,
        string $idempotencyKey,
    ): array {
        $errors = $this->validator->validate($canonical);
        if ($errors !== []) {
            throw new LessonPracticeOperationRejected(
                'blueprint_invalid:'.implode(',', $errors),
            );
        }

        try {
            $contentHash = CanonicalJson::sha256($canonical);
        } catch (JsonException) {
            throw new LessonPracticeOperationRejected('blueprint_encode');
        }

        return DB::transaction(function () use (
            $context,
            $programId,
            $curriculumVersionId,
            $conceptKey,
            $canonical,
            $contentHash,
            $idempotencyKey,
        ): array {
            $existingEvent = DB::table('lesson_practice_attachment_events')
                ->where('organization_id', $context->organizationId)
                ->where('idempotency_key', 'create:'.$idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingEvent !== null) {
                $blueprint = LessonPracticeBlueprint::query()
                    ->where('organization_id', $context->organizationId)
                    ->whereKey($existingEvent->blueprint_id)
                    ->first();
                if ($blueprint === null) {
                    throw new LessonPracticeOperationRejected('idempotency_orphan');
                }
                if ($blueprint->content_hash !== $contentHash) {
                    throw new LessonPracticeIdempotencyConflict('create_hash_mismatch');
                }

                return ['blueprint' => $blueprint, 'created' => false];
            }

            $revision = (int) LessonPracticeBlueprint::query()
                ->where('organization_id', $context->organizationId)
                ->where('program_id', $programId)
                ->where('concept_key', $conceptKey)
                ->max('revision_number');

            $blueprint = LessonPracticeBlueprint::query()->create([
                'id' => (string) Str::ulid(),
                'organization_id' => $context->organizationId,
                'program_id' => $programId,
                'curriculum_version_id' => $curriculumVersionId,
                'revision_number' => $revision + 1,
                'concept_key' => $conceptKey,
                'state' => 'draft',
                'canonical_json' => $canonical,
                'content_hash' => $contentHash,
            ]);

            DB::table('lesson_practice_attachment_events')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $context->organizationId,
                'blueprint_id' => $blueprint->getKey(),
                'actor_membership_id' => $context->membershipId,
                'idempotency_key' => 'create:'.$idempotencyKey,
                'request_hash' => hash('sha256', $contentHash.'|create'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['blueprint' => $blueprint, 'created' => true];
        });
    }

    /**
     * @return array{review: LessonPracticeReview, created: bool}
     */
    public function recordReview(
        OrganizationContext $context,
        string $blueprintId,
        string $reviewKind,
        string $decision,
        string $expectedContentHash,
        string $idempotencyKey,
    ): array {
        if (! in_array($reviewKind, ['english_academic', 'kannada'], true)) {
            throw new LessonPracticeOperationRejected('review_kind');
        }
        if ($decision !== 'approved') {
            throw new LessonPracticeOperationRejected('review_decision');
        }

        return DB::transaction(function () use (
            $context,
            $blueprintId,
            $reviewKind,
            $decision,
            $expectedContentHash,
            $idempotencyKey,
        ): array {
            $blueprint = LessonPracticeBlueprint::query()
                ->where('organization_id', $context->organizationId)
                ->whereKey($blueprintId)
                ->lockForUpdate()
                ->first();
            if ($blueprint === null || $blueprint->state !== 'draft') {
                throw new LessonPracticeOperationRejected('blueprint_unavailable');
            }
            if ($blueprint->content_hash !== $expectedContentHash) {
                throw new LessonPracticeOperationRejected('hash_mismatch');
            }

            $existing = LessonPracticeReview::query()
                ->where('organization_id', $context->organizationId)
                ->where('blueprint_id', $blueprintId)
                ->where('review_kind', $reviewKind)
                ->where('blueprint_content_hash', $expectedContentHash)
                ->first();
            if ($existing !== null) {
                return ['review' => $existing, 'created' => false];
            }

            $review = LessonPracticeReview::query()->create([
                'id' => (string) Str::ulid(),
                'organization_id' => $context->organizationId,
                'blueprint_id' => $blueprintId,
                'review_kind' => $reviewKind,
                'blueprint_content_hash' => $expectedContentHash,
                'decision' => $decision,
                'reviewer_membership_id' => $context->membershipId,
            ]);

            DB::table('lesson_practice_attachment_events')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $context->organizationId,
                'blueprint_id' => $blueprintId,
                'actor_membership_id' => $context->membershipId,
                'idempotency_key' => 'review:'.$reviewKind.':'.$idempotencyKey,
                'request_hash' => hash('sha256', $expectedContentHash.'|'.$reviewKind),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['review' => $review, 'created' => true];
        });
    }

    /**
     * @param  list<array{
     *   order: int,
     *   part_id: string,
     *   asset_id: string,
     *   metadata_revision_id: string,
     *   metadata_content_hash: string,
     *   rights_revision_id: string
     * }>  $parts
     * @return array{media: list<LessonPracticeMedia>, created: bool}
     */
    public function attachParts(
        OrganizationContext $context,
        string $blueprintId,
        string $expectedContentHash,
        array $parts,
        string $idempotencyKey,
    ): array {
        $expectedOrder = ['introduction', 'practice', 'conversation', 'interaction'];
        $ordered = collect($parts)->sortBy('order')->values()->all();
        if (count($ordered) !== 4) {
            throw new LessonPracticeOperationRejected('parts_count');
        }
        foreach ($ordered as $index => $part) {
            if ((int) $part['order'] !== $index + 1
                || $part['part_id'] !== $expectedOrder[$index]
            ) {
                throw new LessonPracticeOperationRejected('parts_order');
            }
        }

        return DB::transaction(function () use (
            $context,
            $blueprintId,
            $expectedContentHash,
            $ordered,
            $idempotencyKey,
        ): array {
            $eventKey = 'attach:'.$idempotencyKey;
            $existingEvent = DB::table('lesson_practice_attachment_events')
                ->where('organization_id', $context->organizationId)
                ->where('idempotency_key', $eventKey)
                ->lockForUpdate()
                ->first();

            if ($existingEvent !== null) {
                if ((string) $existingEvent->blueprint_id !== $blueprintId) {
                    throw new LessonPracticeIdempotencyConflict('attach_blueprint_mismatch');
                }
                $media = array_values(
                    LessonPracticeMedia::query()
                        ->where('organization_id', $context->organizationId)
                        ->where('blueprint_id', $blueprintId)
                        ->orderBy('part_order')
                        ->get()
                        ->all(),
                );

                return ['media' => $media, 'created' => false];
            }

            $blueprint = LessonPracticeBlueprint::query()
                ->where('organization_id', $context->organizationId)
                ->whereKey($blueprintId)
                ->lockForUpdate()
                ->first();
            if ($blueprint === null || $blueprint->state !== 'draft') {
                throw new LessonPracticeOperationRejected('blueprint_unavailable');
            }
            if ($blueprint->content_hash !== $expectedContentHash) {
                throw new LessonPracticeOperationRejected('hash_mismatch');
            }

            foreach (['english_academic', 'kannada'] as $kind) {
                $approved = LessonPracticeReview::query()
                    ->where('organization_id', $context->organizationId)
                    ->where('blueprint_id', $blueprintId)
                    ->where('review_kind', $kind)
                    ->where('blueprint_content_hash', $expectedContentHash)
                    ->where('decision', 'approved')
                    ->exists();
                if (! $approved) {
                    throw new LessonPracticeOperationRejected('reviews_incomplete');
                }
            }

            if (LessonPracticeMedia::query()
                ->where('organization_id', $context->organizationId)
                ->where('blueprint_id', $blueprintId)
                ->exists()
            ) {
                throw new LessonPracticeOperationRejected('already_attached');
            }

            $media = [];
            foreach ($ordered as $part) {
                $asset = DB::table('media_assets')
                    ->where('organization_id', $context->organizationId)
                    ->where('id', $part['asset_id'])
                    ->where('asset_kind', 'lesson_audio')
                    ->first();
                if ($asset === null) {
                    throw new LessonPracticeOperationRejected('asset_missing');
                }

                $lifecycle = DB::table('media_asset_lifecycles')
                    ->where('organization_id', $context->organizationId)
                    ->where('media_asset_id', $part['asset_id'])
                    ->where('state', 'active')
                    ->first();
                if ($lifecycle === null) {
                    throw new LessonPracticeOperationRejected('asset_inactive');
                }

                $rights = DB::table('media_rights_revisions')
                    ->where('organization_id', $context->organizationId)
                    ->where('id', $part['rights_revision_id'])
                    ->where('media_asset_id', $part['asset_id'])
                    ->where('evidence_status', 'approved')
                    ->first();
                if ($rights === null) {
                    throw new LessonPracticeOperationRejected('rights_missing');
                }

                $row = LessonPracticeMedia::query()->create([
                    'id' => (string) Str::ulid(),
                    'organization_id' => $context->organizationId,
                    'blueprint_id' => $blueprintId,
                    'part_order' => (int) $part['order'],
                    'part_id' => (string) $part['part_id'],
                    'media_asset_id' => (string) $part['asset_id'],
                    'metadata_revision_id' => (string) $part['metadata_revision_id'],
                    'metadata_content_hash' => (string) $part['metadata_content_hash'],
                    'media_rights_revision_id' => (string) $part['rights_revision_id'],
                ]);
                $media[] = $row;
            }

            DB::table('lesson_practice_attachment_events')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $context->organizationId,
                'blueprint_id' => $blueprintId,
                'actor_membership_id' => $context->membershipId,
                'idempotency_key' => $eventKey,
                'request_hash' => hash('sha256', $expectedContentHash.'|attach'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['media' => $media, 'created' => true];
        });
    }

    /**
     * @return array{blueprint: LessonPracticeBlueprint, created: bool}
     */
    public function publishBlueprint(
        OrganizationContext $context,
        string $blueprintId,
        string $expectedContentHash,
        string $idempotencyKey,
    ): array {
        return DB::transaction(function () use (
            $context,
            $blueprintId,
            $expectedContentHash,
            $idempotencyKey,
        ): array {
            $eventKey = 'publish:'.$idempotencyKey;
            $existingEvent = DB::table('lesson_practice_attachment_events')
                ->where('organization_id', $context->organizationId)
                ->where('idempotency_key', $eventKey)
                ->lockForUpdate()
                ->first();

            if ($existingEvent !== null) {
                $blueprint = LessonPracticeBlueprint::query()
                    ->where('organization_id', $context->organizationId)
                    ->whereKey($existingEvent->blueprint_id)
                    ->first();
                if ($blueprint === null) {
                    throw new LessonPracticeOperationRejected('idempotency_orphan');
                }
                if ($blueprint->content_hash !== $expectedContentHash) {
                    throw new LessonPracticeIdempotencyConflict('publish_hash_mismatch');
                }

                return ['blueprint' => $blueprint, 'created' => false];
            }

            $blueprint = LessonPracticeBlueprint::query()
                ->where('organization_id', $context->organizationId)
                ->whereKey($blueprintId)
                ->lockForUpdate()
                ->first();
            if ($blueprint === null) {
                throw new LessonPracticeOperationRejected('blueprint_unavailable');
            }
            if ($blueprint->content_hash !== $expectedContentHash) {
                throw new LessonPracticeOperationRejected('hash_mismatch');
            }
            if ($blueprint->state === 'published') {
                return ['blueprint' => $blueprint, 'created' => false];
            }
            if ($blueprint->state !== 'draft') {
                throw new LessonPracticeOperationRejected('blueprint_unavailable');
            }

            $mediaCount = LessonPracticeMedia::query()
                ->where('organization_id', $context->organizationId)
                ->where('blueprint_id', $blueprintId)
                ->count();
            if ($mediaCount !== 4) {
                throw new LessonPracticeOperationRejected('parts_missing');
            }

            foreach (['english_academic', 'kannada'] as $kind) {
                $approved = LessonPracticeReview::query()
                    ->where('organization_id', $context->organizationId)
                    ->where('blueprint_id', $blueprintId)
                    ->where('review_kind', $kind)
                    ->where('blueprint_content_hash', $expectedContentHash)
                    ->where('decision', 'approved')
                    ->exists();
                if (! $approved) {
                    throw new LessonPracticeOperationRejected('reviews_incomplete');
                }
            }

            $publication = $blueprint->canonical_json['publication'] ?? null;
            if (
                ! is_array($publication)
                || ($publication['eligible'] ?? false) !== true
                || (($publication['blockers'] ?? null) !== [])
            ) {
                throw new LessonPracticeOperationRejected('publication_ineligible');
            }

            $blueprint->state = 'published';
            $blueprint->save();

            DB::table('lesson_practice_attachment_events')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $context->organizationId,
                'blueprint_id' => $blueprintId,
                'actor_membership_id' => $context->membershipId,
                'idempotency_key' => $eventKey,
                'request_hash' => hash('sha256', $expectedContentHash.'|publish'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['blueprint' => $blueprint->refresh(), 'created' => true];
        });
    }

    public function getBlueprint(
        OrganizationContext $context,
        string $blueprintId,
    ): LessonPracticeBlueprint {
        $blueprint = LessonPracticeBlueprint::query()
            ->where('organization_id', $context->organizationId)
            ->whereKey($blueprintId)
            ->first();
        if ($blueprint === null) {
            throw new LessonPracticeOperationRejected('blueprint_unavailable');
        }

        return $blueprint;
    }
}

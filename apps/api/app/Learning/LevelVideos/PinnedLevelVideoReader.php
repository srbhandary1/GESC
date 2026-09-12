<?php

declare(strict_types=1);

namespace App\Learning\LevelVideos;

use App\Curriculum\CanonicalJson;
use App\Curriculum\Enrollment\PinnedEnrollmentAccess;
use App\Curriculum\Enrollment\PinnedEnrollmentAccessResolver;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\LearnerAuthority;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use stdClass;

final readonly class PinnedLevelVideoReader
{
    public const string SCHEMA = 'global-english.level-video.v1';

    public function __construct(
        private LearnerAuthority $authority,
        private PinnedEnrollmentAccessResolver $enrollments,
    ) {}

    /**
     * @return list<LevelVideoDefinition>
     */
    public function list(User $learner, string $enrollmentId): array
    {
        if (! Str::isUlid($enrollmentId)) {
            throw new LearningOperationRejected;
        }

        $videos = $this->read(
            learner: $learner,
            enrollmentId: $enrollmentId,
            lessonId: null,
            assetId: null,
        );

        usort(
            $videos,
            static function (
                LevelVideoDefinition $left,
                LevelVideoDefinition $right,
            ): int {
                $leftNumber = preg_match(
                    '/(\d+)/',
                    $left->levelLabel,
                    $leftMatch,
                ) === 1
                    ? (int) $leftMatch[1]
                    : PHP_INT_MAX;
                $rightNumber = preg_match(
                    '/(\d+)/',
                    $right->levelLabel,
                    $rightMatch,
                ) === 1
                    ? (int) $rightMatch[1]
                    : PHP_INT_MAX;

                return [$leftNumber, $left->levelLabel, $left->lessonId]
                    <=> [
                        $rightNumber,
                        $right->levelLabel,
                        $right->lessonId,
                    ];
            },
        );

        return $videos;
    }

    public function resolve(
        User $learner,
        string $enrollmentId,
        string $lessonId,
        string $assetId,
    ): LevelVideoDefinition {
        if (
            ! Str::isUlid($enrollmentId)
            || ! Str::isUlid($lessonId)
            || ! Str::isUlid($assetId)
        ) {
            throw new LearningOperationRejected;
        }

        $matches = $this->read(
            learner: $learner,
            enrollmentId: $enrollmentId,
            lessonId: $lessonId,
            assetId: $assetId,
        );

        if (count($matches) !== 1) {
            throw new LearningOperationRejected;
        }

        return $matches[0];
    }

    /**
     * @return list<LevelVideoDefinition>
     */
    private function read(
        User $learner,
        string $enrollmentId,
        ?string $lessonId,
        ?string $assetId,
    ): array {
        return DB::transaction(function () use (
            $learner,
            $enrollmentId,
            $lessonId,
            $assetId,
        ): array {
            $this->authority->require($learner);
            $access = $this->enrollments->resolve($learner, $enrollmentId);
            $manifest = $access->publication->manifest;
            $contentEntries = $this->listEntries($manifest, 'content');
            $contentByDocument = [];

            foreach ($contentEntries as $content) {
                $documentId = $this->string(
                    $this->map($content, 'document'),
                    'id',
                );

                if (array_key_exists($documentId, $contentByDocument)) {
                    throw new LearningOperationRejected;
                }

                $contentByDocument[$documentId] = $content;
            }

            $candidates = [];

            foreach ($this->listEntries($manifest, 'learning_items') as $item) {
                $itemLessonId = $this->string($item, 'lesson_id');
                $documentId = $this->string($item, 'content_document_id');
                $content = $contentByDocument[$documentId] ?? null;

                if (! is_array($content)) {
                    throw new LearningOperationRejected;
                }

                $media = array_values(array_filter(
                    $this->listEntries($content, 'media'),
                    fn (array $entry): bool => $this->string(
                        $entry,
                        'usage_key',
                    ) === (string) config(
                        'learning.lesson_video_usage_key',
                        'lesson_video',
                    ),
                ));

                if ($media === []) {
                    continue;
                }

                if (count($media) !== 1) {
                    throw new LearningOperationRejected;
                }

                $candidateAssetId = $this->string($media[0], 'asset_id');

                if (
                    ($lessonId !== null && $itemLessonId !== $lessonId)
                    || ($assetId !== null && $candidateAssetId !== $assetId)
                ) {
                    continue;
                }

                $source = $this->map($content, 'source_revision');
                $candidates[] = [
                    'lesson_id' => $itemLessonId,
                    'source_revision_id' => $this->string($source, 'id'),
                    'source_hash' => $this->sha256($source, 'content_hash'),
                    'asset_id' => $candidateAssetId,
                    'asset_hash' => $this->sha256($media[0], 'asset_hash'),
                ];
            }

            return $this->definitions($access, $candidates);
        }, 3);
    }

    /**
     * @param  list<array{lesson_id: string, source_revision_id: string, source_hash: string, asset_id: string, asset_hash: string}>  $candidates
     * @return list<LevelVideoDefinition>
     */
    private function definitions(
        PinnedEnrollmentAccess $access,
        array $candidates,
    ): array {
        if ($candidates === []) {
            return [];
        }

        $sourceRevisionIds = array_values(array_unique(array_column(
            $candidates,
            'source_revision_id',
        )));
        $revisionRows = DB::table('content_revisions')
            ->where(
                'organization_id',
                $access->publication->ownerOrganizationId,
            )
            ->where(
                'curriculum_version_id',
                $access->publication->curriculumVersionId,
            )
            ->whereIn('id', $sourceRevisionIds)
            ->get(['id', 'content_hash', 'payload']);
        $revisions = [];

        foreach ($revisionRows as $revision) {
            $revisionId = $this->objectString($revision, 'id');

            if (array_key_exists($revisionId, $revisions)) {
                throw new LearningOperationRejected;
            }

            $revisions[$revisionId] = $revision;
        }

        if (count($revisions) !== count($sourceRevisionIds)) {
            throw new LearningOperationRejected;
        }

        $assetIds = array_values(array_unique(array_column(
            $candidates,
            'asset_id',
        )));
        $assetRows = DB::table('media_assets')
            ->where(
                'organization_id',
                $access->publication->ownerOrganizationId,
            )
            ->whereIn('id', $assetIds)
            ->get([
                'id',
                'asset_hash',
                'storage_object_key',
                'mime_type',
                'byte_size',
            ]);
        $assets = [];

        foreach ($assetRows as $asset) {
            $rowAssetId = $this->objectString($asset, 'id');

            if (array_key_exists($rowAssetId, $assets)) {
                throw new LearningOperationRejected;
            }

            $assets[$rowAssetId] = $asset;
        }

        if (count($assets) !== count($assetIds)) {
            throw new LearningOperationRejected;
        }

        $allowedMimeTypes = config('learning.lesson_video_mime_types');
        $maximumBytes = config('learning.lesson_video_maximum_bytes');

        if (
            ! is_array($allowedMimeTypes)
            || ! is_int($maximumBytes)
            || $maximumBytes < 1
        ) {
            throw new LearningOperationRejected;
        }

        $videos = [];

        foreach ($candidates as $candidate) {
            $revision = $revisions[$candidate['source_revision_id']] ?? null;
            $asset = $assets[$candidate['asset_id']] ?? null;

            if (
                ! $revision instanceof stdClass
                || ! $asset instanceof stdClass
                || ! hash_equals(
                    $candidate['source_hash'],
                    $this->objectSha256($revision, 'content_hash'),
                )
                || ! hash_equals(
                    $candidate['asset_hash'],
                    $this->objectSha256($asset, 'asset_hash'),
                )
            ) {
                throw new LearningOperationRejected;
            }

            $payload = $this->json($revision, 'payload');

            if (
                $this->string($payload, 'schema') !== self::SCHEMA
                || ! hash_equals(
                    $candidate['source_hash'],
                    CanonicalJson::sha256($payload),
                )
            ) {
                throw new LearningOperationRejected;
            }

            $mimeType = $this->objectString($asset, 'mime_type');
            $byteSize = $this->objectInteger($asset, 'byte_size');

            if (
                ! in_array($mimeType, $allowedMimeTypes, true)
                || $byteSize < 1
                || $byteSize > $maximumBytes
            ) {
                throw new LearningOperationRejected;
            }

            $videos[] = new LevelVideoDefinition(
                lessonId: $candidate['lesson_id'],
                enrollmentId: $access->enrollmentId,
                publicationId: $access->publication->publicationId,
                ownerOrganizationId: $access->publication->ownerOrganizationId,
                curriculumVersionId: $access->publication->curriculumVersionId,
                levelLabel: $this->string($payload, 'level_label'),
                title: $this->string($payload, 'title'),
                assetId: $candidate['asset_id'],
                mimeType: $mimeType,
                contentHash: $candidate['asset_hash'],
                byteSize: $byteSize,
                storageKey: $this->objectString(
                    $asset,
                    'storage_object_key',
                ),
            );
        }

        return $videos;
    }

    /**
     * @param  array<string, mixed>  $container
     * @return list<array<string, mixed>>
     */
    private function listEntries(array $container, string $key): array
    {
        $value = $container[$key] ?? null;

        if (! is_array($value)) {
            throw new LearningOperationRejected;
        }

        $entries = [];

        foreach ($value as $entry) {
            if (! is_array($entry)) {
                throw new LearningOperationRejected;
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $container
     * @return array<string, mixed>
     */
    private function map(array $container, string $key): array
    {
        $value = $container[$key] ?? null;

        if (! is_array($value)) {
            throw new LearningOperationRejected;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $container
     */
    private function string(array $container, string $key): string
    {
        $value = $container[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new LearningOperationRejected;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $container
     */
    private function sha256(array $container, string $key): string
    {
        $value = $this->string($container, $key);

        if (preg_match('/\A[0-9a-f]{64}\z/', $value) !== 1) {
            throw new LearningOperationRejected;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(stdClass $row, string $field): array
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new LearningOperationRejected;
        }

        try {
            $decoded = json_decode(
                $value,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            throw new LearningOperationRejected;
        }

        if (! is_array($decoded)) {
            throw new LearningOperationRejected;
        }

        return $decoded;
    }

    private function objectInteger(stdClass $row, string $field): int
    {
        $value = $row->{$field} ?? null;

        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new LearningOperationRejected;
        }

        return (int) $value;
    }

    private function objectSha256(stdClass $row, string $field): string
    {
        $value = $this->objectString($row, $field);

        if (preg_match('/\A[0-9a-f]{64}\z/', $value) !== 1) {
            throw new LearningOperationRejected;
        }

        return $value;
    }

    private function objectString(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new LearningOperationRejected;
        }

        return $value;
    }
}

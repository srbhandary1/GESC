<?php

declare(strict_types=1);

namespace App\Learning\LevelVideos;

use App\Audit\AuditEventRecorder;
use App\Curriculum\CanonicalJson;
use App\Curriculum\CurriculumGraphProjector;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Governance\GovernanceCommandIdempotency;
use App\Curriculum\Governance\GovernanceInput;
use App\Models\User;
use App\Outbox\OutboxEventRecorder;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * Imports inventoried legacy level videos as an unpublished curriculum draft.
 *
 * This command deliberately records no authority appointments, review
 * decisions, approved rights evidence, publication, or enrollment pin. Those
 * authoritative records must be created later by the appointed humans through
 * the normal governance commands (ADR-0004 and ADR-0013).
 */
final readonly class LegacyLevelVideoCommissioner
{
    private const string ATTRIBUTION = 'legacy-level-video-import';

    private const string IMPORT_OPERATION =
        'curriculum.legacy_level_videos.import_draft';

    private const string RIGHTS_BASIS_KEY = 'owner_asserted_legacy_import';

    private const string LEVEL_VIDEO_SCHEMA =
        'global-english.level-video.v1';

    private const string DAILY_MISSION_SCHEMA =
        'global-english.daily-speaking-mission.v1';

    private const string SCAFFOLD_AUDIO =
        'legacy-import-scaffold-model-audio';

    public function __construct(
        private OrganizationContextManager $contexts,
        private OrganizationAuthorization $authorization,
        private CurriculumGraphProjector $graphProjector,
        private GovernanceCommandIdempotency $idempotency,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    /**
     * @param  array<array-key, mixed>  $videos
     * @return array{
     *   workflow_state: 'draft_pending_independent_review',
     *   replayed: bool,
     *   publication_id: null,
     *   program_id: string,
     *   version_id: string,
     *   enrollment_id: null,
     *   publication_blockers: list<string>,
     *   videos: list<array{
     *     lesson_id: string,
     *     asset_id: string,
     *     rights_revision_id: string,
     *     level_label: string
     *   }>
     * }
     */
    public function commission(
        User $actor,
        array $videos,
        string $idempotencyKey,
    ): array {
        GovernanceInput::idempotencyKey($idempotencyKey);

        $context = $this->contexts->current();
        $actorMembershipId = $context->membershipId;

        if (
            $actorMembershipId === null
            || $context->actorUserId !== (string) $actor->getKey()
            || ! $this->authorization->allows($actor, 'curriculum.author')
            || ! $this->authorization->allows(
                $actor,
                'curriculum.translate.kn',
            )
        ) {
            throw new CurriculumOperationRejected;
        }

        $normalizedVideos = $this->normalizeVideos($videos);
        $organizationId = $context->organizationId;
        $fingerprint = $this->idempotency->fingerprint([
            'operation' => self::IMPORT_OPERATION,
            'organization_id' => $organizationId,
            'scaffold_audio_sha256' => hash(
                'sha256',
                self::SCAFFOLD_AUDIO,
            ),
            'videos' => array_map(
                static fn (array $video): array => [
                    'bytes' => $video['bytes'],
                    'level_label' => $video['level_label'],
                    'sha256' => $video['sha256'],
                ],
                $normalizedVideos,
            ),
        ]);

        $replayedVersionId = DB::transaction(
            fn (): ?string => $this->idempotency->replay(
                actor: $actor,
                operation: self::IMPORT_OPERATION,
                aggregateType: 'organization',
                aggregateId: $organizationId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            ),
            3,
        );

        if ($replayedVersionId !== null) {
            return $this->resultForVersion(
                $organizationId,
                $replayedVersionId,
                true,
            );
        }

        $prepared = $this->prepareUploads(
            $organizationId,
            $normalizedVideos,
        );
        $uploadedObjects = [];
        try {
            $this->uploadPrepared($prepared, $uploadedObjects);

            $transactionResult = DB::transaction(function () use (
                $actor,
                $actorMembershipId,
                $fingerprint,
                $idempotencyKey,
                $organizationId,
                $prepared,
            ): array {
                $replay = $this->idempotency->replay(
                    actor: $actor,
                    operation: self::IMPORT_OPERATION,
                    aggregateType: 'organization',
                    aggregateId: $organizationId,
                    idempotencyKey: $idempotencyKey,
                    fingerprint: $fingerprint,
                );

                if ($replay !== null) {
                    return [
                        'replayed' => true,
                        'version_id' => $replay,
                    ];
                }

                $programId = $this->insertProgram(
                    organizationId: $organizationId,
                    createdByMembershipId: $actorMembershipId,
                );
                $versionId = (string) Str::ulid();
                $now = now();

                DB::table('curriculum_versions')->insert([
                    'id' => $versionId,
                    'organization_id' => $organizationId,
                    'program_id' => $programId,
                    'key' => 'legacy-level-videos-v1',
                    'created_by_membership_id' => $actorMembershipId,
                    'created_by_attribution' => self::ATTRIBUTION,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $objectiveId = (string) Str::ulid();
                DB::table('learning_objectives')->insert([
                    'id' => $objectiveId,
                    'organization_id' => $organizationId,
                    'curriculum_version_id' => $versionId,
                    'key' => 'legacy-level-video-orientation',
                    'outcome_code' => 'legacy.level_video.orientation',
                    'sequence' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $missionId = $this->insertDailyMissionScaffold(
                    organizationId: $organizationId,
                    programId: $programId,
                    versionId: $versionId,
                    objectiveId: $objectiveId,
                    actorMembershipId: $actorMembershipId,
                    audio: $prepared['audio'],
                );

                $sequence = 2;

                foreach ($prepared['videos'] as $video) {
                    $this->insertLevelVideoLesson(
                        organizationId: $organizationId,
                        programId: $programId,
                        versionId: $versionId,
                        objectiveId: $objectiveId,
                        missionId: $missionId,
                        sequence: $sequence,
                        video: $video,
                        actorMembershipId: $actorMembershipId,
                    );
                    $sequence++;
                }

                $graph = $this->graphProjector->project(
                    $organizationId,
                    $versionId,
                );
                $graphRevisionId = (string) Str::ulid();
                DB::table('curriculum_graph_revisions')->insert([
                    'id' => $graphRevisionId,
                    'organization_id' => $organizationId,
                    'curriculum_version_id' => $versionId,
                    'parent_revision_id' => null,
                    'parent_revision_number' => null,
                    'revision_number' => 1,
                    'graph' => CanonicalJson::encode($graph['graph']),
                    'graph_hash' => $graph['graph_hash'],
                    'authored_by_membership_id' => $actorMembershipId,
                    'author_attribution' => self::ATTRIBUTION,
                    'authored_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('curriculum_graph_heads')->insert([
                    'organization_id' => $organizationId,
                    'curriculum_version_id' => $versionId,
                    'curriculum_graph_revision_id' => $graphRevisionId,
                    'graph_hash' => $graph['graph_hash'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $this->idempotency->record(
                    actor: $actor,
                    operation: self::IMPORT_OPERATION,
                    aggregateType: 'organization',
                    aggregateId: $organizationId,
                    idempotencyKey: $idempotencyKey,
                    fingerprint: $fingerprint,
                    resultType: 'curriculum_version',
                    resultId: $versionId,
                    completedAt: $now,
                );
                $this->audit->recordTenant(
                    'curriculum.legacy_level_videos.draft_imported',
                    'curriculum_version',
                    $versionId,
                );
                $this->outbox->recordTenant(
                    'curriculum.legacy_level_videos.draft_imported',
                    'curriculum_version',
                    $versionId,
                    [
                        'curriculum_version_id' => $versionId,
                        'membership_id' => $actorMembershipId,
                        'organization_id' => $organizationId,
                        'program_id' => $programId,
                    ],
                );

                return [
                    'replayed' => false,
                    'version_id' => $versionId,
                ];
            }, 3);

            if ($transactionResult['replayed'] === true) {
                if (! $this->cleanupUploads($uploadedObjects)) {
                    throw new RuntimeException(
                        'Concurrent replay succeeded, but duplicate object cleanup was incomplete.',
                    );
                }

                $uploadedObjects = [];
            }
        } catch (Throwable $exception) {
            if (
                $uploadedObjects !== []
                && ! $this->cleanupUploads($uploadedObjects)
            ) {
                throw new RuntimeException(
                    'Legacy level video import failed and uploaded object cleanup was incomplete.',
                    0,
                    $exception,
                );
            }

            $concurrentReplay = DB::transaction(
                fn (): ?string => $this->idempotency->replay(
                    actor: $actor,
                    operation: self::IMPORT_OPERATION,
                    aggregateType: 'organization',
                    aggregateId: $organizationId,
                    idempotencyKey: $idempotencyKey,
                    fingerprint: $fingerprint,
                ),
                3,
            );

            if ($concurrentReplay !== null) {
                return $this->resultForVersion(
                    $organizationId,
                    $concurrentReplay,
                    true,
                );
            }

            throw $exception;
        }

        return $this->resultForVersion(
            $organizationId,
            $transactionResult['version_id'],
            $transactionResult['replayed'],
        );
    }

    /**
     * @param  array<array-key, mixed>  $videos
     * @return list<array{
     *   level_label: string,
     *   absolute_path: string,
     *   sha256: string,
     *   bytes: int
     * }>
     */
    private function normalizeVideos(array $videos): array
    {
        if ($videos === []) {
            throw new InvalidArgumentException(
                'At least one downloaded legacy level video is required.',
            );
        }

        $maximumBytes = config('learning.lesson_video_maximum_bytes');
        $mimeTypes = config('learning.lesson_video_mime_types');

        if (
            ! is_int($maximumBytes)
            || $maximumBytes < 1
            || ! is_array($mimeTypes)
            || ! in_array('video/mp4', $mimeTypes, true)
        ) {
            throw new CurriculumOperationRejected;
        }

        $normalized = [];
        $labels = [];

        foreach ($videos as $index => $video) {
            if (! is_array($video)) {
                throw new InvalidArgumentException(sprintf(
                    'Video entry at index %d must be an object.',
                    $index,
                ));
            }

            $levelLabel = $video['level_label'] ?? null;
            $absolutePath = $video['absolute_path'] ?? null;
            $sha256 = $video['sha256'] ?? null;
            $bytes = $video['bytes'] ?? null;

            if (
                ! is_string($levelLabel)
                || trim($levelLabel) === ''
                || ! is_string($absolutePath)
                || $absolutePath === ''
                || ! is_string($sha256)
                || preg_match('/\A[a-f0-9]{64}\z/', $sha256) !== 1
                || ! is_int($bytes)
                || $bytes < 1
                || $bytes > $maximumBytes
            ) {
                throw new InvalidArgumentException(sprintf(
                    'Video entry at index %d is missing valid level_label, absolute_path, sha256, or bounded bytes.',
                    $index,
                ));
            }

            $levelLabel = trim($levelLabel);
            $labelKey = mb_strtolower($levelLabel);

            if (isset($labels[$labelKey])) {
                throw new InvalidArgumentException(sprintf(
                    'Duplicate legacy level label: %s.',
                    $levelLabel,
                ));
            }

            if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
                throw new InvalidArgumentException(sprintf(
                    'Legacy level video file is missing or unreadable: %s',
                    $absolutePath,
                ));
            }

            $size = filesize($absolutePath);

            if ($size === false || (int) $size !== $bytes) {
                throw new InvalidArgumentException(sprintf(
                    'Legacy level video byte size mismatch for %s.',
                    $absolutePath,
                ));
            }

            $actualHash = hash_file('sha256', $absolutePath);

            if (
                ! is_string($actualHash)
                || ! hash_equals($sha256, $actualHash)
            ) {
                throw new InvalidArgumentException(sprintf(
                    'Legacy level video SHA-256 mismatch for %s.',
                    $absolutePath,
                ));
            }

            $labels[$labelKey] = true;
            $normalized[] = [
                'level_label' => $levelLabel,
                'absolute_path' => $absolutePath,
                'sha256' => $sha256,
                'bytes' => $bytes,
            ];
        }

        usort(
            $normalized,
            static function (array $left, array $right): int {
                $leftNumber = preg_match(
                    '/(\d+)/',
                    $left['level_label'],
                    $leftMatch,
                ) === 1 ? (int) $leftMatch[1] : PHP_INT_MAX;
                $rightNumber = preg_match(
                    '/(\d+)/',
                    $right['level_label'],
                    $rightMatch,
                ) === 1 ? (int) $rightMatch[1] : PHP_INT_MAX;

                return [$leftNumber, $left['level_label'], $left['sha256']]
                    <=> [$rightNumber, $right['level_label'], $right['sha256']];
            },
        );

        return $normalized;
    }

    /**
     * @param  list<array{
     *   level_label: string,
     *   absolute_path: string,
     *   sha256: string,
     *   bytes: int
     * }>  $videos
     * @return array{
     *   audio: array{
     *     asset_id: string,
     *     asset_hash: string,
     *     bytes: string,
     *     byte_size: int,
     *     disk: string,
     *     storage_key: string
     *   },
     *   videos: list<array{
     *     level_label: string,
     *     absolute_path: string,
     *     sha256: string,
     *     bytes: int,
     *     asset_id: string,
     *     disk: string,
     *     storage_key: string
     *   }>
     * }
     */
    private function prepareUploads(
        string $organizationId,
        array $videos,
    ): array {
        $audioDisk = config('learning.model_audio_disk');
        $videoDisk = config('learning.lesson_video_disk');

        if (
            ! is_string($audioDisk)
            || $audioDisk === ''
            || ! is_string($videoDisk)
            || $videoDisk === ''
        ) {
            throw new CurriculumOperationRejected;
        }

        $audioAssetId = (string) Str::ulid();
        $preparedVideos = [];

        foreach ($videos as $video) {
            $assetId = (string) Str::ulid();
            $video['asset_id'] = $assetId;
            $video['disk'] = $videoDisk;
            $video['storage_key'] = sprintf(
                'legacy-level-videos/%s/%s.mp4',
                strtolower($organizationId),
                strtolower($assetId),
            );
            $preparedVideos[] = $video;
        }

        return [
            'audio' => [
                'asset_id' => $audioAssetId,
                'asset_hash' => hash('sha256', self::SCAFFOLD_AUDIO),
                'bytes' => self::SCAFFOLD_AUDIO,
                'byte_size' => strlen(self::SCAFFOLD_AUDIO),
                'disk' => $audioDisk,
                'storage_key' => sprintf(
                    'legacy-level-videos/%s/mission-model-audio-%s.ogg',
                    strtolower($organizationId),
                    strtolower($audioAssetId),
                ),
            ],
            'videos' => $preparedVideos,
        ];
    }

    /**
     * @param  array{
     *   audio: array{
     *     asset_id: string,
     *     asset_hash: string,
     *     bytes: string,
     *     byte_size: int,
     *     disk: string,
     *     storage_key: string
     *   },
     *   videos: list<array{
     *     level_label: string,
     *     absolute_path: string,
     *     sha256: string,
     *     bytes: int,
     *     asset_id: string,
     *     disk: string,
     *     storage_key: string
     *   }>
     * }  $prepared
     * @param  list<array{disk: string, storage_key: string}>  $uploadedObjects
     */
    private function uploadPrepared(
        array $prepared,
        array &$uploadedObjects,
    ): void {
        $audio = $prepared['audio'];
        $uploadedObjects[] = [
            'disk' => $audio['disk'],
            'storage_key' => $audio['storage_key'],
        ];

        $audioDisk = Storage::disk($audio['disk']);

        if (! $audioDisk->put($audio['storage_key'], $audio['bytes'])) {
            throw new RuntimeException(
                'Unable to store the draft scaffold model audio.',
            );
        }

        $this->assertStoredObject(
            disk: $audioDisk,
            storageKey: $audio['storage_key'],
            expectedBytes: $audio['byte_size'],
            expectedHash: $audio['asset_hash'],
        );

        foreach ($prepared['videos'] as $video) {
            $uploadedObjects[] = [
                'disk' => $video['disk'],
                'storage_key' => $video['storage_key'],
            ];
            $stream = fopen($video['absolute_path'], 'rb');

            if ($stream === false) {
                throw new InvalidArgumentException(sprintf(
                    'Unable to open legacy level video for copy: %s',
                    $video['absolute_path'],
                ));
            }

            $videoDisk = Storage::disk($video['disk']);

            try {
                $stored = $videoDisk->put(
                    $video['storage_key'],
                    $stream,
                );
            } finally {
                fclose($stream);
            }

            if (! $stored) {
                throw new RuntimeException(sprintf(
                    'Unable to store legacy level video: %s.',
                    $video['level_label'],
                ));
            }

            $this->assertStoredObject(
                disk: $videoDisk,
                storageKey: $video['storage_key'],
                expectedBytes: $video['bytes'],
                expectedHash: $video['sha256'],
            );
        }
    }

    private function assertStoredObject(
        Filesystem $disk,
        string $storageKey,
        int $expectedBytes,
        string $expectedHash,
    ): void {
        $stream = $disk->readStream($storageKey);

        if (! is_resource($stream)) {
            throw new RuntimeException(
                'Unable to verify an imported private media object.',
            );
        }

        $hash = hash_init('sha256');
        $actualBytes = 0;

        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 1_048_576);

                if ($chunk === false) {
                    throw new RuntimeException(
                        'Unable to verify an imported private media object.',
                    );
                }

                $actualBytes += strlen($chunk);
                hash_update($hash, $chunk);
            }
        } finally {
            fclose($stream);
        }

        $actualHash = hash_final($hash);

        if (
            $actualBytes !== $expectedBytes
            || ! hash_equals($expectedHash, $actualHash)
        ) {
            throw new RuntimeException(
                'Imported private media object verification failed.',
            );
        }
    }

    /**
     * @param  list<array{disk: string, storage_key: string}>  $objects
     */
    private function cleanupUploads(array $objects): bool
    {
        $complete = true;

        foreach (array_reverse($objects) as $object) {
            try {
                $disk = Storage::disk($object['disk']);

                if (
                    $disk->exists($object['storage_key'])
                    && ! $disk->delete($object['storage_key'])
                ) {
                    $complete = false;
                }
            } catch (Throwable) {
                $complete = false;
            }
        }

        return $complete;
    }

    private function insertProgram(
        string $organizationId,
        string $createdByMembershipId,
    ): string {
        $programId = (string) Str::ulid();
        $now = now();

        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organizationId,
            'key' => 'legacy-level-videos-'.strtolower(
                (string) Str::ulid(),
            ),
            'internal_name' => 'Legacy level video curriculum draft',
            'created_by_membership_id' => $createdByMembershipId,
            'created_by_attribution' => self::ATTRIBUTION,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $programId;
    }

    /**
     * @param  array{
     *   asset_id: string,
     *   asset_hash: string,
     *   bytes: string,
     *   byte_size: int,
     *   disk: string,
     *   storage_key: string
     * }  $audio
     */
    private function insertDailyMissionScaffold(
        string $organizationId,
        string $programId,
        string $versionId,
        string $objectiveId,
        string $actorMembershipId,
        array $audio,
    ): string {
        $moduleId = (string) Str::ulid();
        $lessonId = (string) Str::ulid();
        $documentId = (string) Str::ulid();
        $sourceRevisionId = (string) Str::ulid();
        $variantId = (string) Str::ulid();
        $itemId = (string) Str::ulid();
        $missionId = (string) Str::ulid();
        $now = now();
        $sourcePayload = [
            'schema' => self::DAILY_MISSION_SCHEMA,
            'goal_key' => 'everyday-conversation',
            'estimated_minutes' => 5,
            'title' => 'Legacy import scaffold mission',
            'model_text' => 'Hello. How are you today?',
            'transcript' => 'Hello. How are you today?',
            'can_do' => 'I can greet someone politely.',
            'model_audio_duration_ms' => 500,
            'check' => [
                'id' => 'legacy-import-scaffold-check',
                'prompt' => 'Which option is a polite greeting?',
                'options' => [
                    [
                        'id' => 'polite-greeting',
                        'label' => 'Hello. How are you today?',
                    ],
                    [
                        'id' => 'not-a-greeting',
                        'label' => 'Close the door.',
                    ],
                ],
                'correct_option_id' => 'polite-greeting',
            ],
        ];
        $variantPayload = [
            'schema' => self::DAILY_MISSION_SCHEMA,
            'guidance' => 'ವಿನಯವಾಗಿ ಶುಭಾಶಯ ಹೇಳಿ.',
        ];
        $sourceHash = CanonicalJson::sha256($sourcePayload);
        $variantHash = CanonicalJson::sha256($variantPayload);

        DB::table('curriculum_modules')->insert([
            'id' => $moduleId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'key' => 'legacy-import-scaffold-module',
            'sequence' => 1,
            'estimated_minutes' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('lessons')->insert([
            'id' => $lessonId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'curriculum_module_id' => $moduleId,
            'key' => 'legacy-import-scaffold-lesson',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('lesson_objectives')->insert([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'lesson_id' => $lessonId,
            'objective_id' => $objectiveId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_documents')->insert([
            'id' => $documentId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'key' => 'legacy-import-scaffold-mission',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_revisions')->insert([
            'id' => $sourceRevisionId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'content_document_id' => $documentId,
            'parent_revision_id' => null,
            'revision_number' => 1,
            'source_locale' => 'en-IN',
            'content_type' => 'instruction',
            'learner_visible' => true,
            'localization_requirement' => 'kn_required',
            'pedagogically_authoritative' => true,
            'payload' => CanonicalJson::encode($sourcePayload),
            'content_hash' => $sourceHash,
            'authored_by_membership_id' => $actorMembershipId,
            'author_attribution' => self::ATTRIBUTION,
            'authored_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_revision_heads')->insert([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'content_document_id' => $documentId,
            'content_revision_id' => $sourceRevisionId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('localized_variants')->insert([
            'id' => $variantId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'source_revision_id' => $sourceRevisionId,
            'parent_variant_id' => null,
            'locale' => 'kn-IN',
            'revision_number' => 1,
            'source_content_hash' => $sourceHash,
            'payload' => CanonicalJson::encode($variantPayload),
            'content_hash' => $variantHash,
            'translated_by_membership_id' => $actorMembershipId,
            'translator_attribution' => self::ATTRIBUTION,
            'translated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('learning_items')->insert([
            'id' => $itemId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'lesson_id' => $lessonId,
            'content_document_id' => $documentId,
            'key' => 'legacy-import-scaffold-item',
            'item_type' => 'speaking_prompt',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_missions')->insert([
            'id' => $missionId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'instruction_content_document_id' => $documentId,
            'key' => 'legacy-import-scaffold-mission',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('mission_items')->insert([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'mission_id' => $missionId,
            'learning_item_id' => $itemId,
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insertPendingMedia(
            organizationId: $organizationId,
            programId: $programId,
            versionId: $versionId,
            sourceRevisionId: $sourceRevisionId,
            mediaAssetId: $audio['asset_id'],
            assetKind: 'instruction_audio',
            mimeType: 'audio/ogg',
            byteSize: $audio['byte_size'],
            assetHash: $audio['asset_hash'],
            storageObjectKey: $audio['storage_key'],
            usageKey: 'model_audio',
            actorMembershipId: $actorMembershipId,
            provenanceReference: 'provenance:generated-import-scaffold',
            learnerDeliveryRequested: false,
        );

        return $missionId;
    }

    /**
     * @param  array{
     *   level_label: string,
     *   absolute_path: string,
     *   sha256: string,
     *   bytes: int,
     *   asset_id: string,
     *   disk: string,
     *   storage_key: string
     * }  $video
     * @return array{
     *   lesson_id: string,
     *   asset_id: string,
     *   rights_revision_id: string,
     *   level_label: string
     * }
     */
    private function insertLevelVideoLesson(
        string $organizationId,
        string $programId,
        string $versionId,
        string $objectiveId,
        string $missionId,
        int $sequence,
        array $video,
        string $actorMembershipId,
    ): array {
        $keyFragment = $this->keyFragment($video['level_label'], $sequence);
        $moduleId = (string) Str::ulid();
        $lessonId = (string) Str::ulid();
        $documentId = (string) Str::ulid();
        $sourceRevisionId = (string) Str::ulid();
        $variantId = (string) Str::ulid();
        $itemId = (string) Str::ulid();
        $now = now();
        $sourcePayload = [
            'schema' => self::LEVEL_VIDEO_SCHEMA,
            'level_label' => $video['level_label'],
            'title' => $video['level_label'],
        ];
        $variantPayload = [
            'schema' => self::LEVEL_VIDEO_SCHEMA,
            'guidance' => sprintf(
                '%s ವೀಡಿಯೊ ಪಾಠ.',
                $video['level_label'],
            ),
        ];
        $sourceHash = CanonicalJson::sha256($sourcePayload);
        $variantHash = CanonicalJson::sha256($variantPayload);

        DB::table('curriculum_modules')->insert([
            'id' => $moduleId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'key' => 'module-'.$keyFragment,
            'sequence' => $sequence,
            'estimated_minutes' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('lessons')->insert([
            'id' => $lessonId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'curriculum_module_id' => $moduleId,
            'key' => 'lesson-'.$keyFragment,
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('lesson_objectives')->insert([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'lesson_id' => $lessonId,
            'objective_id' => $objectiveId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_documents')->insert([
            'id' => $documentId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'key' => 'level-video-'.$keyFragment,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_revisions')->insert([
            'id' => $sourceRevisionId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'content_document_id' => $documentId,
            'parent_revision_id' => null,
            'revision_number' => 1,
            'source_locale' => 'en-IN',
            'content_type' => 'instruction',
            'learner_visible' => true,
            'localization_requirement' => 'kn_required',
            'pedagogically_authoritative' => true,
            'payload' => CanonicalJson::encode($sourcePayload),
            'content_hash' => $sourceHash,
            'authored_by_membership_id' => $actorMembershipId,
            'author_attribution' => self::ATTRIBUTION,
            'authored_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_revision_heads')->insert([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'content_document_id' => $documentId,
            'content_revision_id' => $sourceRevisionId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('localized_variants')->insert([
            'id' => $variantId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'source_revision_id' => $sourceRevisionId,
            'parent_variant_id' => null,
            'locale' => 'kn-IN',
            'revision_number' => 1,
            'source_content_hash' => $sourceHash,
            'payload' => CanonicalJson::encode($variantPayload),
            'content_hash' => $variantHash,
            'translated_by_membership_id' => $actorMembershipId,
            'translator_attribution' => self::ATTRIBUTION,
            'translated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('learning_items')->insert([
            'id' => $itemId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'lesson_id' => $lessonId,
            'content_document_id' => $documentId,
            'key' => 'level-video-'.$keyFragment,
            'item_type' => 'lesson_video',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('mission_items')->insert([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'mission_id' => $missionId,
            'learning_item_id' => $itemId,
            'sequence' => $sequence,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $usageKey = config(
            'learning.lesson_video_usage_key',
            'lesson_video',
        );

        if (! is_string($usageKey) || $usageKey === '') {
            throw new CurriculumOperationRejected;
        }

        $rightsRevisionId = $this->insertPendingMedia(
            organizationId: $organizationId,
            programId: $programId,
            versionId: $versionId,
            sourceRevisionId: $sourceRevisionId,
            mediaAssetId: $video['asset_id'],
            assetKind: 'lesson_video',
            mimeType: 'video/mp4',
            byteSize: $video['bytes'],
            assetHash: $video['sha256'],
            storageObjectKey: $video['storage_key'],
            usageKey: $usageKey,
            actorMembershipId: $actorMembershipId,
            provenanceReference: 'provenance:legacy-gse-content',
            learnerDeliveryRequested: true,
        );

        return [
            'lesson_id' => $lessonId,
            'asset_id' => $video['asset_id'],
            'rights_revision_id' => $rightsRevisionId,
            'level_label' => $video['level_label'],
        ];
    }

    private function insertPendingMedia(
        string $organizationId,
        string $programId,
        string $versionId,
        string $sourceRevisionId,
        string $mediaAssetId,
        string $assetKind,
        string $mimeType,
        int $byteSize,
        string $assetHash,
        string $storageObjectKey,
        string $usageKey,
        string $actorMembershipId,
        string $provenanceReference,
        bool $learnerDeliveryRequested,
    ): string {
        $rightsRevisionId = (string) Str::ulid();
        $evidenceHash = CanonicalJson::sha256([
            'asset_hash' => $assetHash,
            'asset_id' => $mediaAssetId,
            'learner_delivery_requested' => $learnerDeliveryRequested,
            'provenance_reference' => $provenanceReference,
            'rights_basis_key' => self::RIGHTS_BASIS_KEY,
            'status' => 'pending',
        ]);
        $now = now();

        DB::table('media_assets')->insert([
            'id' => $mediaAssetId,
            'organization_id' => $organizationId,
            'key' => strtolower($assetKind).'-'.strtolower(
                (string) Str::ulid(),
            ),
            'asset_kind' => $assetKind,
            'storage_object_key' => $storageObjectKey,
            'mime_type' => $mimeType,
            'byte_size' => $byteSize,
            'asset_hash' => $assetHash,
            'uploaded_by_membership_id' => $actorMembershipId,
            'uploader_attribution' => self::ATTRIBUTION,
            'uploaded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('media_rights_revisions')->insert([
            'id' => $rightsRevisionId,
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'media_asset_id' => $mediaAssetId,
            'parent_revision_id' => null,
            'revision_number' => 1,
            'evidence_status' => 'pending',
            'evidence_hash' => $evidenceHash,
            'selected_asset_hash' => $assetHash,
            'rights_holder_reference' => 'party:GLOBAL-ENGLISH-DIRECT-OWNER',
            'provenance_reference' => $provenanceReference,
            'rights_basis_key' => self::RIGHTS_BASIS_KEY,
            'license_or_contract_reference' => 'ADR-0013-OWNER-ASSERTED',
            'permitted_release_channels' => CanonicalJson::encode([
                'android',
                'web',
            ]),
            'permitted_territories' => CanonicalJson::encode(['IN']),
            'learner_delivery_allowed' => $learnerDeliveryRequested,
            'sharing_allowed' => false,
            'derivative_use_allowed' => false,
            'effective_at' => $now,
            'expires_at' => null,
            'retention_policy_key' => 'legacy_level_video_retention',
            'deletion_constraint_key' => 'legacy_level_video_preserve_history',
            'confirmed_by_membership_id' => null,
            'rights_appointment_id' => null,
            'rights_appointment_event_id' => null,
            'confirmed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_revision_media_assets')->insert([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'content_revision_id' => $sourceRevisionId,
            'media_asset_id' => $mediaAssetId,
            'usage_key' => $usageKey,
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $rightsRevisionId;
    }

    /**
     * @return array{
     *   workflow_state: 'draft_pending_independent_review',
     *   replayed: bool,
     *   publication_id: null,
     *   program_id: string,
     *   version_id: string,
     *   enrollment_id: null,
     *   publication_blockers: list<string>,
     *   videos: list<array{
     *     lesson_id: string,
     *     asset_id: string,
     *     rights_revision_id: string,
     *     level_label: string
     *   }>
     * }
     */
    private function resultForVersion(
        string $organizationId,
        string $versionId,
        bool $replayed,
    ): array {
        $version = DB::table('curriculum_versions')
            ->where('organization_id', $organizationId)
            ->where('id', $versionId)
            ->first(['program_id']);

        if (! $version instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        $programId = $this->stringProperty($version, 'program_id');
        $usageKey = config(
            'learning.lesson_video_usage_key',
            'lesson_video',
        );

        if (! is_string($usageKey) || $usageKey === '') {
            throw new CurriculumOperationRejected;
        }

        $rows = DB::table('content_revision_media_assets as links')
            ->join('content_revisions as revisions', function ($join): void {
                $join
                    ->on(
                        'revisions.organization_id',
                        '=',
                        'links.organization_id',
                    )
                    ->on(
                        'revisions.curriculum_version_id',
                        '=',
                        'links.curriculum_version_id',
                    )
                    ->on('revisions.id', '=', 'links.content_revision_id');
            })
            ->join('learning_items as items', function ($join): void {
                $join
                    ->on(
                        'items.organization_id',
                        '=',
                        'revisions.organization_id',
                    )
                    ->on(
                        'items.curriculum_version_id',
                        '=',
                        'revisions.curriculum_version_id',
                    )
                    ->on(
                        'items.content_document_id',
                        '=',
                        'revisions.content_document_id',
                    );
            })
            ->join('lessons', function ($join): void {
                $join
                    ->on(
                        'lessons.organization_id',
                        '=',
                        'items.organization_id',
                    )
                    ->on(
                        'lessons.curriculum_version_id',
                        '=',
                        'items.curriculum_version_id',
                    )
                    ->on('lessons.id', '=', 'items.lesson_id');
            })
            ->join('curriculum_modules as modules', function ($join): void {
                $join
                    ->on(
                        'modules.organization_id',
                        '=',
                        'lessons.organization_id',
                    )
                    ->on(
                        'modules.curriculum_version_id',
                        '=',
                        'lessons.curriculum_version_id',
                    )
                    ->on('modules.id', '=', 'lessons.curriculum_module_id');
            })
            ->join('media_assets as assets', function ($join): void {
                $join
                    ->on(
                        'assets.organization_id',
                        '=',
                        'links.organization_id',
                    )
                    ->on('assets.id', '=', 'links.media_asset_id');
            })
            ->join('media_rights_revisions as rights', function ($join) use (
                $programId,
            ): void {
                $join
                    ->on(
                        'rights.organization_id',
                        '=',
                        'assets.organization_id',
                    )
                    ->on('rights.media_asset_id', '=', 'assets.id')
                    ->where('rights.program_id', '=', $programId)
                    ->where('rights.revision_number', '=', 1);
            })
            ->where('links.organization_id', $organizationId)
            ->where('links.curriculum_version_id', $versionId)
            ->where('links.usage_key', $usageKey)
            ->where('items.item_type', 'lesson_video')
            ->where('assets.uploader_attribution', self::ATTRIBUTION)
            ->orderBy('modules.sequence')
            ->orderBy('lessons.sequence')
            ->get([
                'lessons.id as lesson_id',
                'assets.id as asset_id',
                'rights.id as rights_revision_id',
                'revisions.payload',
            ]);
        $videos = [];

        foreach ($rows as $row) {
            $payloadValue = $row->payload ?? null;

            if (! is_string($payloadValue) || $payloadValue === '') {
                throw new CurriculumOperationRejected;
            }

            $payload = json_decode($payloadValue, true);

            if (
                ! is_array($payload)
                || ($payload['schema'] ?? null) !== self::LEVEL_VIDEO_SCHEMA
                || ! is_string($payload['level_label'] ?? null)
                || $payload['level_label'] === ''
            ) {
                throw new CurriculumOperationRejected;
            }

            $videos[] = [
                'lesson_id' => $this->stringProperty($row, 'lesson_id'),
                'asset_id' => $this->stringProperty($row, 'asset_id'),
                'rights_revision_id' => $this->stringProperty(
                    $row,
                    'rights_revision_id',
                ),
                'level_label' => $payload['level_label'],
            ];
        }

        if ($videos === []) {
            throw new CurriculumOperationRejected;
        }

        return [
            'workflow_state' => 'draft_pending_independent_review',
            'replayed' => $replayed,
            'publication_id' => null,
            'program_id' => $programId,
            'version_id' => $versionId,
            'enrollment_id' => null,
            'publication_blockers' => [
                'graph_review',
                'academic_en_review',
                'kannada_review',
                'media_rights_confirmation',
            ],
            'videos' => $videos,
        ];
    }

    private function stringProperty(
        stdClass $record,
        string $property,
    ): string {
        $value = $record->{$property} ?? null;

        if (! is_string($value) || $value === '') {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    private function keyFragment(string $levelLabel, int $sequence): string
    {
        $slug = strtolower(
            (string) preg_replace('/[^a-zA-Z0-9]+/', '-', $levelLabel),
        );
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = 'level';
        }

        return sprintf('%s-%d', $slug, $sequence);
    }
}

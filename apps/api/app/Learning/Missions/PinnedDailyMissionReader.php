<?php

declare(strict_types=1);

namespace App\Learning\Missions;

use App\Curriculum\CanonicalJson;
use App\Curriculum\Enrollment\PinnedEnrollmentAccess;
use App\Curriculum\Enrollment\PinnedEnrollmentAccessResolver;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\LearnerAuthority;
use App\Models\User;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use stdClass;

final readonly class PinnedDailyMissionReader implements DailyMissionReader
{
    public const string SCHEMA = 'global-english.daily-speaking-mission.v1';

    public function __construct(
        private LearnerAuthority $authority,
        private PinnedEnrollmentAccessResolver $enrollments,
        private OrganizationContextManager $contexts,
    ) {}

    public function resolve(
        User $learner,
        string $enrollmentId,
        string $missionId,
    ): DailyMissionDefinition {
        if (! Str::isUlid($enrollmentId) || ! Str::isUlid($missionId)) {
            throw new LearningOperationRejected;
        }

        return DB::transaction(function () use (
            $learner,
            $enrollmentId,
            $missionId,
        ): DailyMissionDefinition {
            $this->authority->require($learner);
            $goalKey = $this->requiredGoal();
            $access = $this->enrollments->resolve(
                $learner,
                $enrollmentId,
            );
            $definition = $this->definition($access, $missionId);

            if ($definition->goalKey !== $goalKey) {
                throw new LearningOperationRejected;
            }

            return $definition;
        }, 3);
    }

    public function first(
        User $learner,
        string $enrollmentId,
    ): ?DailyMissionDefinition {
        if (! Str::isUlid($enrollmentId)) {
            throw new LearningOperationRejected;
        }

        return DB::transaction(function () use (
            $learner,
            $enrollmentId,
        ): ?DailyMissionDefinition {
            $this->authority->require($learner);
            $goalKey = $this->requiredGoal();
            $access = $this->enrollments->resolve(
                $learner,
                $enrollmentId,
            );
            $manifest = $this->manifest($access);
            $missions = $this->list($manifest, 'missions');

            if (count($missions) !== 1) {
                throw new LearningOperationRejected;
            }

            $definition = $this->definition(
                $access,
                $this->string($missions[0], 'id'),
            );

            return $definition->goalKey === $goalKey
                ? $definition
                : null;
        }, 3);
    }

    private function requiredGoal(): string
    {
        $context = $this->contexts->current();

        if ($context->membershipId === null) {
            throw new LearningOperationRejected;
        }

        $goal = DB::table('learner_profiles')
            ->join('learner_goals', function ($join): void {
                $join
                    ->on(
                        'learner_goals.organization_id',
                        '=',
                        'learner_profiles.organization_id',
                    )
                    ->on(
                        'learner_goals.learner_profile_id',
                        '=',
                        'learner_profiles.id',
                    )
                    ->on(
                        'learner_goals.learner_membership_id',
                        '=',
                        'learner_profiles.learner_membership_id',
                    );
            })
            ->where(
                'learner_profiles.organization_id',
                $context->organizationId,
            )
            ->where(
                'learner_profiles.learner_membership_id',
                $context->membershipId,
            )
            ->whereNotNull('learner_profiles.adult_confirmed_at')
            ->whereNotNull('learner_profiles.onboarding_completed_at')
            ->lockForUpdate()
            ->value('learner_goals.goal_key');

        if (! is_string($goal) || $goal === '') {
            throw new LearningOperationRejected;
        }

        return $goal;
    }

    private function definition(
        PinnedEnrollmentAccess $access,
        string $missionId,
    ): DailyMissionDefinition {
        $manifest = $this->manifest($access);
        $missions = $this->list($manifest, 'missions');

        if (
            count($missions) !== 1
            || $this->string($missions[0], 'id') !== $missionId
        ) {
            throw new LearningOperationRejected;
        }

        $mission = $missions[0];
        $missionItems = array_values(array_filter(
            $this->list($manifest, 'mission_items'),
            fn (array $edge): bool => $this->string(
                $edge,
                'mission_id',
            ) === $missionId,
        ));

        if (count($missionItems) !== 1) {
            throw new LearningOperationRejected;
        }

        $learningItemId = $this->string(
            $missionItems[0],
            'learning_item_id',
        );
        $item = $this->findById(
            $this->list($manifest, 'learning_items'),
            $learningItemId,
        );
        $lessonId = $this->string($item, 'lesson_id');
        $objectiveEdges = array_values(array_filter(
            $this->list($manifest, 'lesson_objectives'),
            fn (array $edge): bool => $this->string(
                $edge,
                'lesson_id',
            ) === $lessonId,
        ));

        if (count($objectiveEdges) !== 1) {
            throw new LearningOperationRejected;
        }

        $objectiveId = $this->string(
            $objectiveEdges[0],
            'objective_id',
        );
        $documentId = $this->string(
            $mission,
            'instruction_content_document_id',
        );

        if ($this->string($item, 'content_document_id') !== $documentId) {
            throw new LearningOperationRejected;
        }

        $content = $this->findContent(
            $this->list($manifest, 'content'),
            $documentId,
        );
        $sourceReference = $this->map($content, 'source_revision');
        $localizedReference = $this->map(
            $content,
            'localized_variant',
        );
        $sourceRevisionId = $this->string($sourceReference, 'id');
        $sourceHash = $this->sha256($sourceReference, 'content_hash');
        $variantId = $this->string($localizedReference, 'id');
        $variantHash = $this->sha256(
            $localizedReference,
            'content_hash',
        );
        $source = DB::table('content_revisions')
            ->where(
                'organization_id',
                $access->publication->ownerOrganizationId,
            )
            ->where(
                'curriculum_version_id',
                $access->publication->curriculumVersionId,
            )
            ->where('content_document_id', $documentId)
            ->where('id', $sourceRevisionId)
            ->where('content_hash', $sourceHash)
            ->first(['payload']);
        $localized = DB::table('localized_variants')
            ->where(
                'organization_id',
                $access->publication->ownerOrganizationId,
            )
            ->where(
                'curriculum_version_id',
                $access->publication->curriculumVersionId,
            )
            ->where('source_revision_id', $sourceRevisionId)
            ->where('id', $variantId)
            ->where('content_hash', $variantHash)
            ->where('locale', 'kn-IN')
            ->first(['payload']);

        if (
            ! $source instanceof stdClass
            || ! $localized instanceof stdClass
        ) {
            throw new LearningOperationRejected;
        }

        $sourcePayload = $this->json($source, 'payload');
        $localizedPayload = $this->json($localized, 'payload');

        if (
            ! hash_equals($sourceHash, CanonicalJson::sha256($sourcePayload))
            || ! hash_equals(
                $variantHash,
                CanonicalJson::sha256($localizedPayload),
            )
            || $this->string($sourcePayload, 'schema') !== self::SCHEMA
            || $this->string($localizedPayload, 'schema') !== self::SCHEMA
        ) {
            throw new LearningOperationRejected;
        }

        $goalKey = $this->string($sourcePayload, 'goal_key');

        if (
            ! in_array(
                $goalKey,
                config('learning.goal_keys', []),
                true,
            )
        ) {
            throw new LearningOperationRejected;
        }

        $check = $this->map($sourcePayload, 'check');
        $checkId = $this->identifier($check, 'id');
        $options = $this->options($check);
        $correctOptionId = $this->identifier(
            $check,
            'correct_option_id',
        );

        if (
            ! in_array(
                $correctOptionId,
                array_column($options, 'id'),
                true,
            )
        ) {
            throw new LearningOperationRejected;
        }

        $media = array_values(array_filter(
            $this->list($content, 'media'),
            fn (array $entry): bool => $this->string(
                $entry,
                'usage_key',
            ) === 'model_audio',
        ));

        if (count($media) !== 1) {
            throw new LearningOperationRejected;
        }

        $assetId = $this->string($media[0], 'asset_id');
        $assetHash = $this->sha256($media[0], 'asset_hash');
        $asset = DB::table('media_assets')
            ->where(
                'organization_id',
                $access->publication->ownerOrganizationId,
            )
            ->where('id', $assetId)
            ->where('asset_hash', $assetHash)
            ->first([
                'storage_object_key',
                'mime_type',
                'byte_size',
            ]);

        $allowedAudioMimeTypes = config(
            'learning.model_audio_mime_types',
        );

        if (
            ! $asset instanceof stdClass
            || ! is_string($asset->mime_type)
            || ! is_array($allowedAudioMimeTypes)
            || ! in_array(
                $asset->mime_type,
                $allowedAudioMimeTypes,
                true,
            )
        ) {
            throw new LearningOperationRejected;
        }

        $assetByteSize = $this->objectInteger($asset, 'byte_size');
        $maximumAudioBytes = config(
            'learning.model_audio_maximum_bytes',
        );

        if (
            ! is_int($maximumAudioBytes)
            || $maximumAudioBytes < 1
            || $assetByteSize < 1
            || $assetByteSize > $maximumAudioBytes
        ) {
            throw new LearningOperationRejected;
        }

        $estimatedMinutes = $this->integer(
            $sourcePayload,
            'estimated_minutes',
        );
        $audioDurationMs = $this->integer(
            $sourcePayload,
            'model_audio_duration_ms',
        );

        if (
            $estimatedMinutes < 1
            || $estimatedMinutes > 60
            || $audioDurationMs < 250
            || $audioDurationMs > 300_000
        ) {
            throw new LearningOperationRejected;
        }

        $definition = [
            'schema' => self::SCHEMA,
            'mission_id' => $missionId,
            'objective_id' => $objectiveId,
            'learning_item_id' => $learningItemId,
            'source_revision_id' => $sourceRevisionId,
            'source_content_hash' => $sourceHash,
            'localized_variant_id' => $variantId,
            'localized_content_hash' => $variantHash,
            'media_asset_id' => $assetId,
            'media_asset_hash' => $assetHash,
            'check_id' => $checkId,
            'correct_option_id' => $correctOptionId,
        ];

        return new DailyMissionDefinition(
            missionId: $missionId,
            enrollmentId: $access->enrollmentId,
            publicationId: $access->publication->publicationId,
            ownerOrganizationId: $access->publication->ownerOrganizationId,
            curriculumVersionId: $access->publication->curriculumVersionId,
            objectiveId: $objectiveId,
            learningItemId: $learningItemId,
            manifestHash: $access->publication->manifestHash,
            definitionHash: CanonicalJson::sha256($definition),
            sourceRevisionId: $sourceRevisionId,
            sourceContentHash: $sourceHash,
            kannadaVariantId: $variantId,
            kannadaContentHash: $variantHash,
            goalKey: $goalKey,
            estimatedMinutes: $estimatedMinutes,
            title: $this->string($sourcePayload, 'title'),
            kannadaGuidance: $this->string(
                $localizedPayload,
                'guidance',
            ),
            modelText: $this->string($sourcePayload, 'model_text'),
            transcript: $this->string($sourcePayload, 'transcript'),
            canDo: $this->string($sourcePayload, 'can_do'),
            checkId: $checkId,
            checkPrompt: $this->string($check, 'prompt'),
            checkOptions: $options,
            correctOptionId: $correctOptionId,
            modelAudioAssetId: $assetId,
            modelAudioMimeType: $asset->mime_type,
            modelAudioContentHash: $assetHash,
            modelAudioByteSize: $assetByteSize,
            modelAudioDurationMs: $audioDurationMs,
            modelAudioStorageKey: $this->objectString(
                $asset,
                'storage_object_key',
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(PinnedEnrollmentAccess $access): array
    {
        $row = DB::table('curriculum_publications')
            ->where(
                'organization_id',
                $access->publication->ownerOrganizationId,
            )
            ->where('id', $access->publication->publicationId)
            ->where('manifest_hash', $access->publication->manifestHash)
            ->first(['manifest']);

        if (! $row instanceof stdClass) {
            throw new LearningOperationRejected;
        }

        $manifest = $this->json($row, 'manifest');

        if (
            ! hash_equals(
                $access->publication->manifestHash,
                CanonicalJson::sha256($manifest),
            )
        ) {
            throw new LearningOperationRejected;
        }

        return $manifest;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    private function findById(array $entries, string $id): array
    {
        $matches = array_values(array_filter(
            $entries,
            fn (array $entry): bool => $this->string($entry, 'id') === $id,
        ));

        if (count($matches) !== 1) {
            throw new LearningOperationRejected;
        }

        return $matches[0];
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    private function findContent(array $entries, string $documentId): array
    {
        $matches = array_values(array_filter(
            $entries,
            fn (array $entry): bool => $this->string(
                $this->map($entry, 'document'),
                'id',
            ) === $documentId,
        ));

        if (count($matches) !== 1) {
            throw new LearningOperationRejected;
        }

        return $matches[0];
    }

    /**
     * @param  array<string, mixed>  $check
     * @return list<array{id: string, label: string}>
     */
    private function options(array $check): array
    {
        $options = $this->list($check, 'options');

        if (count($options) < 2 || count($options) > 8) {
            throw new LearningOperationRejected;
        }

        $result = [];
        $ids = [];

        foreach ($options as $option) {
            $id = $this->identifier($option, 'id');

            if (isset($ids[$id])) {
                throw new LearningOperationRejected;
            }

            $ids[$id] = true;
            $result[] = [
                'id' => $id,
                'label' => $this->string($option, 'label'),
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function map(array $value, string $field): array
    {
        $item = $value[$field] ?? null;

        if (! is_array($item) || array_is_list($item)) {
            throw new LearningOperationRejected;
        }

        return $item;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function identifier(array $value, string $field): string
    {
        $identifier = $this->string($value, $field);

        if (
            preg_match(
                '/\A[a-z0-9][a-z0-9._-]{0,99}\z/',
                $identifier,
            ) !== 1
        ) {
            throw new LearningOperationRejected;
        }

        return $identifier;
    }

    /**
     * @param  array<string, mixed>  $value
     * @return list<array<string, mixed>>
     */
    private function list(array $value, string $field): array
    {
        $items = $value[$field] ?? null;

        if (! is_array($items) || ! array_is_list($items)) {
            throw new LearningOperationRejected;
        }

        foreach ($items as $item) {
            if (! is_array($item) || array_is_list($item)) {
                throw new LearningOperationRejected;
            }
        }

        /** @var list<array<string, mixed>> $items */
        return $items;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function string(array $value, string $field): string
    {
        $item = $value[$field] ?? null;

        if (! is_string($item) || trim($item) === '') {
            throw new LearningOperationRejected;
        }

        return $item;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function sha256(array $value, string $field): string
    {
        $hash = $this->string($value, $field);

        if (preg_match('/\A[0-9a-f]{64}\z/', $hash) !== 1) {
            throw new LearningOperationRejected;
        }

        return $hash;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function integer(array $value, string $field): int
    {
        $item = $value[$field] ?? null;

        if (! is_int($item)) {
            throw new LearningOperationRejected;
        }

        return $item;
    }

    private function objectString(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new LearningOperationRejected;
        }

        return $value;
    }

    private function objectInteger(stdClass $row, string $field): int
    {
        $value = $row->{$field} ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new LearningOperationRejected;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(stdClass $row, string $field): array
    {
        $value = $row->{$field} ?? null;

        if (is_array($value) && ! array_is_list($value)) {
            return $value;
        }

        if (! is_string($value)) {
            throw new LearningOperationRejected;
        }

        try {
            $decoded = json_decode(
                $value,
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            throw new LearningOperationRejected;
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new LearningOperationRejected;
        }

        return $decoded;
    }
}

<?php

declare(strict_types=1);

namespace App\Learning\Missions;

final readonly class DailyMissionDefinition
{
    /**
     * @param  list<array{id: string, label: string}>  $checkOptions
     */
    public function __construct(
        public string $missionId,
        public string $enrollmentId,
        public string $publicationId,
        public string $ownerOrganizationId,
        public string $curriculumVersionId,
        public string $objectiveId,
        public string $learningItemId,
        public string $manifestHash,
        public string $definitionHash,
        public string $sourceRevisionId,
        public string $sourceContentHash,
        public string $kannadaVariantId,
        public string $kannadaContentHash,
        public string $goalKey,
        public int $estimatedMinutes,
        public string $title,
        public string $kannadaGuidance,
        public string $modelText,
        public string $transcript,
        public string $canDo,
        public string $checkId,
        public string $checkPrompt,
        public array $checkOptions,
        public string $correctOptionId,
        public string $modelAudioAssetId,
        public string $modelAudioMimeType,
        public string $modelAudioContentHash,
        public int $modelAudioByteSize,
        public int $modelAudioDurationMs,
        public string $modelAudioStorageKey,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        $english = fn (string $text): array => [
            'locale' => 'en-IN',
            'text' => $text,
            'revision_id' => $this->sourceRevisionId,
            'content_hash' => $this->sourceContentHash,
        ];
        $kannada = fn (string $text): array => [
            'locale' => 'kn-IN',
            'text' => $text,
            'revision_id' => $this->kannadaVariantId,
            'content_hash' => $this->kannadaContentHash,
        ];

        return [
            'id' => $this->missionId,
            'enrollment_id' => $this->enrollmentId,
            'publication_id' => $this->publicationId,
            'objective_id' => $this->objectiveId,
            'title' => $english($this->title),
            'kannada_guidance' => $kannada($this->kannadaGuidance),
            'model_text' => $english($this->modelText),
            'transcript' => $english($this->transcript),
            'model_audio' => [
                'asset_id' => $this->modelAudioAssetId,
                'stream_url' => sprintf(
                    '/api/v1/media-assets/%s/content?enrollment_id=%s&mission_id=%s',
                    $this->modelAudioAssetId,
                    $this->enrollmentId,
                    $this->missionId,
                ),
                'mime_type' => $this->modelAudioMimeType,
                'duration_ms' => $this->modelAudioDurationMs,
            ],
            'check' => [
                'id' => $this->checkId,
                'prompt' => $english($this->checkPrompt),
                'options' => array_map(
                    fn (array $option): array => [
                        'id' => $option['id'],
                        'label' => $english($option['label']),
                    ],
                    $this->checkOptions,
                ),
            ],
        ];
    }

    /**
     * @return array{locale: string, text: string, revision_id: string, content_hash: string}
     */
    public function canDoPayload(): array
    {
        return [
            'locale' => 'en-IN',
            'text' => $this->canDo,
            'revision_id' => $this->sourceRevisionId,
            'content_hash' => $this->sourceContentHash,
        ];
    }
}

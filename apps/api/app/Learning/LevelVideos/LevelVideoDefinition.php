<?php

declare(strict_types=1);

namespace App\Learning\LevelVideos;

final readonly class LevelVideoDefinition
{
    public function __construct(
        public string $lessonId,
        public string $enrollmentId,
        public string $publicationId,
        public string $ownerOrganizationId,
        public string $curriculumVersionId,
        public string $levelLabel,
        public string $title,
        public string $assetId,
        public string $mimeType,
        public string $contentHash,
        public int $byteSize,
        public string $storageKey,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        return [
            'lesson_id' => $this->lessonId,
            'enrollment_id' => $this->enrollmentId,
            'publication_id' => $this->publicationId,
            'level_label' => $this->levelLabel,
            'title' => [
                'locale' => 'en-IN',
                'text' => $this->title,
            ],
            'video' => [
                'asset_id' => $this->assetId,
                'stream_url' => sprintf(
                    '/api/v1/media-assets/%s/lesson-video?enrollment_id=%s&lesson_id=%s',
                    $this->assetId,
                    $this->enrollmentId,
                    $this->lessonId,
                ),
                'mime_type' => $this->mimeType,
                'byte_size' => $this->byteSize,
            ],
        ];
    }
}

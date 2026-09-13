<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning\MediaTranscription;

use App\Media\MediaObjectStore;
use Throwable;

final readonly class LaravelCurriculumMediaSourceReader implements CurriculumMediaSourceReader
{
    public function __construct(private MediaObjectStore $objects) {}

    public function readExact(
        string $assetKind,
        string $storageObjectKey,
        int $expectedBytes,
        string $expectedSha256,
    ): string {
        try {
            $stream = $this->objects->openAccepted($assetKind, $storageObjectKey);
            try {
                $bytes = stream_get_contents($stream);
            } finally {
                fclose($stream);
            }
        } catch (Throwable $exception) {
            throw new MediaTranscriptionRejected(
                'source_object_invalid',
                $exception,
            );
        }

        if (
            ! is_string($bytes)
            || strlen($bytes) !== $expectedBytes
            || ! hash_equals($expectedSha256, hash('sha256', $bytes))
        ) {
            throw new MediaTranscriptionRejected('source_object_invalid');
        }

        return $bytes;
    }
}

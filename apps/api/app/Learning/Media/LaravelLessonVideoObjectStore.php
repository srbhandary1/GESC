<?php

declare(strict_types=1);

namespace App\Learning\Media;

use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\LevelVideos\LevelVideoDefinition;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use League\Flysystem\PathPrefixer;
use League\Flysystem\WhitespacePathNormalizer;
use Psr\Http\Message\StreamInterface;
use Throwable;

final readonly class LaravelLessonVideoObjectStore implements LessonVideoObjectStore
{
    private const int VERIFICATION_CHUNK_BYTES = 65_536;

    public function __construct(private FilesystemManager $filesystems) {}

    public function openVerifiedRange(
        LevelVideoDefinition $video,
        VideoByteRange $range,
    ) {
        $diskName = $this->validatedDiskName($video, $range);

        try {
            $disk = $this->filesystems->disk($diskName);

            if ($disk instanceof AwsS3V3Adapter) {
                return $this->openS3Range($disk, $video, $range);
            }

            if (! $disk instanceof FilesystemAdapter) {
                throw new LearningOperationRejected;
            }

            return $this->openSeekableRange($disk, $video, $range);
        } catch (Throwable $exception) {
            if ($exception instanceof LearningOperationRejected) {
                throw $exception;
            }

            throw new LearningOperationRejected;
        }
    }

    private function validatedDiskName(
        LevelVideoDefinition $video,
        VideoByteRange $range,
    ): string {
        $maximumBytes = config('learning.lesson_video_maximum_bytes');
        $diskName = config('learning.lesson_video_disk');
        $allowedMimeTypes = config('learning.lesson_video_mime_types');

        if (
            ! is_int($maximumBytes)
            || $maximumBytes < 1
            || ! is_string($diskName)
            || $diskName === ''
            || ! is_array($allowedMimeTypes)
            || ! in_array($video->mimeType, $allowedMimeTypes, true)
            || preg_match('/\A[0-9a-f]{64}\z/', $video->contentHash) !== 1
            || $video->storageKey === ''
            || $video->byteSize < 1
            || $video->byteSize > $maximumBytes
            || $range->totalBytes !== $video->byteSize
            || $range->start < 0
            || $range->end < $range->start
            || $range->end >= $video->byteSize
        ) {
            throw new LearningOperationRejected;
        }

        return $diskName;
    }

    /**
     * @return resource
     */
    private function openSeekableRange(
        FilesystemAdapter $disk,
        LevelVideoDefinition $video,
        VideoByteRange $range,
    ) {
        $source = $disk->readStream($video->storageKey);

        if (! is_resource($source)) {
            throw new LearningOperationRejected;
        }

        try {
            $metadata = stream_get_meta_data($source);

            if ($metadata['seekable'] !== true) {
                throw new LearningOperationRejected;
            }

            $this->verifyResource($source, $video);

            if (fseek($source, $range->start, SEEK_SET) !== 0) {
                throw new LearningOperationRejected;
            }

            return $source;
        } catch (Throwable $exception) {
            fclose($source);

            if ($exception instanceof LearningOperationRejected) {
                throw $exception;
            }

            throw new LearningOperationRejected;
        }
    }

    /**
     * S3 objects are verified in one bounded sequential read. The response
     * range is then reopened with If-Match against that exact object version,
     * avoiding both a full-file buffer and an unverified time-of-check/use gap.
     *
     * @return resource
     */
    private function openS3Range(
        AwsS3V3Adapter $disk,
        LevelVideoDefinition $video,
        VideoByteRange $range,
    ) {
        $config = $disk->getConfig();
        $bucket = $config['bucket'] ?? null;
        $root = $config['root'] ?? '';

        if (
            ! is_string($bucket)
            || $bucket === ''
            || ! is_string($root)
        ) {
            throw new LearningOperationRejected;
        }

        $path = (new WhitespacePathNormalizer)->normalizePath(
            $video->storageKey,
        );
        $key = (new PathPrefixer($root))->prefixPath($path);
        $client = $disk->getClient();
        $verifiedBody = null;
        $rangeBody = null;

        try {
            $verifiedObject = $client->getObject([
                'Bucket' => $bucket,
                'Key' => $key,
            ]);
            $verifiedBody = $verifiedObject->get('Body');
            $etag = $verifiedObject->get('ETag');

            if (
                ! $verifiedBody instanceof StreamInterface
                || ! is_string($etag)
                || $etag === ''
                || $this->integer($verifiedObject->get('ContentLength'))
                    !== $video->byteSize
            ) {
                throw new LearningOperationRejected;
            }

            $this->verifyPsrStream($verifiedBody, $video);
            $verifiedBody->close();
            $verifiedBody = null;

            $rangeObject = $client->getObject([
                'Bucket' => $bucket,
                'Key' => $key,
                'IfMatch' => $etag,
                'Range' => sprintf(
                    'bytes=%d-%d',
                    $range->start,
                    $range->end,
                ),
            ]);
            $rangeBody = $rangeObject->get('Body');

            if (
                ! $rangeBody instanceof StreamInterface
                || $rangeObject->get('ETag') !== $etag
                || $this->integer($rangeObject->get('ContentLength'))
                    !== $range->length()
                || $rangeObject->get('ContentRange') !== sprintf(
                    'bytes %d-%d/%d',
                    $range->start,
                    $range->end,
                    $video->byteSize,
                )
            ) {
                throw new LearningOperationRejected;
            }

            $resource = $rangeBody->detach();
            $rangeBody = null;

            if (! is_resource($resource)) {
                throw new LearningOperationRejected;
            }

            return $resource;
        } finally {
            if ($verifiedBody instanceof StreamInterface) {
                $verifiedBody->close();
            }

            if ($rangeBody instanceof StreamInterface) {
                $rangeBody->close();
            }
        }
    }

    /**
     * @param  resource  $source
     */
    private function verifyResource(
        $source,
        LevelVideoDefinition $video,
    ): void {
        $hash = hash_init('sha256');
        $bytes = 0;

        while (! feof($source)) {
            $chunk = fread($source, self::VERIFICATION_CHUNK_BYTES);

            if ($chunk === false) {
                throw new LearningOperationRejected;
            }

            if ($chunk === '') {
                if (feof($source)) {
                    break;
                }

                throw new LearningOperationRejected;
            }

            $bytes += strlen($chunk);

            if ($bytes > $video->byteSize) {
                throw new LearningOperationRejected;
            }

            hash_update($hash, $chunk);
        }

        if (
            $bytes !== $video->byteSize
            || ! hash_equals($video->contentHash, hash_final($hash))
        ) {
            throw new LearningOperationRejected;
        }
    }

    private function verifyPsrStream(
        StreamInterface $source,
        LevelVideoDefinition $video,
    ): void {
        $hash = hash_init('sha256');
        $bytes = 0;

        while (true) {
            $chunk = $source->read(self::VERIFICATION_CHUNK_BYTES);
            $atEnd = $source->eof();

            if ($chunk === '') {
                if ($atEnd) {
                    break;
                }

                throw new LearningOperationRejected;
            }

            $bytes += strlen($chunk);

            if ($bytes > $video->byteSize) {
                throw new LearningOperationRejected;
            }

            hash_update($hash, $chunk);

            if ($atEnd) {
                break;
            }
        }

        if (
            $bytes !== $video->byteSize
            || ! hash_equals($video->contentHash, hash_final($hash))
        ) {
            throw new LearningOperationRejected;
        }
    }

    private function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new LearningOperationRejected;
    }
}

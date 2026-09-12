<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Learning\LevelVideos\LevelVideoDefinition;
use App\Learning\Media\LessonVideoObjectStore;
use App\Learning\Media\VideoByteRange;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Filesystem\AwsS3V3Adapter as LaravelS3Adapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter as FlysystemS3Adapter;
use League\Flysystem\Filesystem;
use Tests\TestCase;

final class LessonVideoObjectStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('learning.lesson_video_maximum_bytes', 20_000_000);
        config()->set('learning.lesson_video_mime_types', ['video/mp4']);
    }

    public function test_local_range_reuses_the_verified_seekable_object_without_a_spool_file(): void
    {
        Storage::fake('lesson-video-local-test');
        config()->set(
            'learning.lesson_video_disk',
            'lesson-video-local-test',
        );
        $content = str_repeat('0123456789abcdef', 8_192);
        $video = $this->video($content, 'large/lesson.mp4');
        Storage::disk('lesson-video-local-test')->put(
            $video->storageKey,
            $content,
        );
        $range = VideoByteRange::fromRequest(
            rangeHeader: 'bytes=65530-65550',
            ifRangeHeader: null,
            totalBytes: $video->byteSize,
            etag: '"'.$video->contentHash.'"',
        );

        $stream = $this->app
            ->make(LessonVideoObjectStore::class)
            ->openVerifiedRange($video, $range);
        $metadata = stream_get_meta_data($stream);
        $served = stream_get_contents($stream, $range->length());
        fclose($stream);

        self::assertSame(
            substr($content, $range->start, $range->length()),
            $served,
        );
        self::assertIsString($metadata['uri'] ?? null);
        self::assertStringEndsWith(
            '/large/lesson.mp4',
            str_replace('\\', '/', (string) $metadata['uri']),
        );
    }

    public function test_s3_verifies_once_then_reopens_only_the_range_under_if_match(): void
    {
        $content = str_repeat('verified-video-', 10_000);
        $video = $this->video($content, 'published/lesson.mp4');
        $range = VideoByteRange::fromRequest(
            rangeHeader: 'bytes=101-232',
            ifRangeHeader: null,
            totalBytes: $video->byteSize,
            etag: '"'.$video->contentHash.'"',
        );
        $servedRange = substr($content, $range->start, $range->length());
        $etag = '"storage-version-1"';
        $commands = [];
        $handler = new MockHandler([
            function ($command) use (&$commands, $content, $etag): Result {
                $commands[] = $command->toArray();

                return new Result([
                    'Body' => Utils::streamFor($content),
                    'ContentLength' => strlen($content),
                    'ETag' => $etag,
                    '@metadata' => [],
                ]);
            },
            function ($command) use (
                &$commands,
                $servedRange,
                $range,
                $video,
                $etag,
            ): Result {
                $commands[] = $command->toArray();

                return new Result([
                    'Body' => Utils::streamFor($servedRange),
                    'ContentLength' => strlen($servedRange),
                    'ContentRange' => sprintf(
                        'bytes %d-%d/%d',
                        $range->start,
                        $range->end,
                        $video->byteSize,
                    ),
                    'ETag' => $etag,
                    '@metadata' => ['statusCode' => 206],
                ]);
            },
        ]);
        $client = new S3Client([
            'credentials' => ['key' => 'test', 'secret' => 'test'],
            'handler' => $handler,
            'region' => 'ap-south-1',
            'version' => 'latest',
        ]);
        $flysystemAdapter = new FlysystemS3Adapter(
            client: $client,
            bucket: 'private-media',
            prefix: 'lesson-videos',
            streamReads: true,
        );
        $disk = new LaravelS3Adapter(
            new Filesystem($flysystemAdapter),
            $flysystemAdapter,
            [
                'bucket' => 'private-media',
                'root' => 'lesson-videos',
            ],
            $client,
        );
        $this->app->make(FilesystemManager::class)->set(
            'lesson-video-s3-test',
            $disk,
        );
        config()->set(
            'learning.lesson_video_disk',
            'lesson-video-s3-test',
        );

        $stream = $this->app
            ->make(LessonVideoObjectStore::class)
            ->openVerifiedRange($video, $range);
        $served = stream_get_contents($stream);
        fclose($stream);

        self::assertSame($servedRange, $served);
        self::assertCount(2, $commands);
        self::assertSame('private-media', $commands[0]['Bucket']);
        self::assertSame(
            'lesson-videos/published/lesson.mp4',
            $commands[0]['Key'],
        );
        self::assertArrayNotHasKey('Range', $commands[0]);
        self::assertSame(
            sprintf('bytes=%d-%d', $range->start, $range->end),
            $commands[1]['Range'],
        );
        self::assertSame($etag, $commands[1]['IfMatch']);
        self::assertCount(0, $handler);
    }

    private function video(
        string $content,
        string $storageKey,
    ): LevelVideoDefinition {
        return new LevelVideoDefinition(
            lessonId: '01K00000000000000000000001',
            enrollmentId: '01K00000000000000000000002',
            publicationId: '01K00000000000000000000003',
            ownerOrganizationId: '01K00000000000000000000004',
            curriculumVersionId: '01K00000000000000000000005',
            levelLabel: 'Level 1',
            title: 'Level 1 video',
            assetId: '01K00000000000000000000006',
            mimeType: 'video/mp4',
            contentHash: hash('sha256', $content),
            byteSize: strlen($content),
            storageKey: $storageKey,
        );
    }
}

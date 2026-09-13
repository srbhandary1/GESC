<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Media\Exceptions\MediaObjectStoreFailure;
use App\Media\MediaObjectStore;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MediaObjectStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media-object-quarantine-test');
        Storage::fake('media-object-accepted-test');
        config()->set(
            'media.quarantine_disk',
            'media-object-quarantine-test',
        );
        config()->set(
            'media.accepted_disks.model_audio',
            'media-object-accepted-test',
        );
        config()->set(
            'media.accepted_disks.lesson_audio',
            'media-object-accepted-test',
        );
        config()->set(
            'media.accepted_disks.lesson_video',
            'media-object-accepted-test',
        );
    }

    public function test_parts_are_assembled_hashed_promoted_and_reopened_exactly(): void
    {
        $store = $this->app->make(MediaObjectStore::class);
        $first = 'private-part-one';
        $second = '-private-part-two';
        $complete = $first.$second;
        $store->putQuarantineBytes('uploads/01/parts/1', $first);
        $store->putQuarantineBytes('uploads/01/parts/2', $second);

        $digest = $store->assembleQuarantine(
            ['uploads/01/parts/1', 'uploads/01/parts/2'],
            'uploads/01/assembled',
        );

        self::assertSame(strlen($complete), $digest->byteSize);
        self::assertSame(hash('sha256', $complete), $digest->sha256);
        self::assertSame(
            $complete,
            Storage::disk('media-object-quarantine-test')->get(
                'uploads/01/assembled',
            ),
        );

        $store->promote(
            assetKind: 'model_audio',
            quarantineKey: 'uploads/01/assembled',
            acceptedKey: 'assets/01/'.hash('sha256', $complete),
            expectedBytes: strlen($complete),
            expectedSha256: hash('sha256', $complete),
        );
        $stream = $store->openAccepted(
            'model_audio',
            'assets/01/'.hash('sha256', $complete),
        );
        $reopened = stream_get_contents($stream);
        fclose($stream);

        self::assertSame($complete, $reopened);
    }

    public function test_unsafe_keys_and_changed_promotion_digests_fail_closed(): void
    {
        $store = $this->app->make(MediaObjectStore::class);

        try {
            $store->putQuarantineBytes('../outside', 'private');
            self::fail('Traversal-like object keys must be rejected.');
        } catch (MediaObjectStoreFailure) {
            self::assertSame(
                [],
                Storage::disk('media-object-quarantine-test')->allFiles(),
            );
        }

        $store->putQuarantineBytes('uploads/02/assembled', 'exact-bytes');

        try {
            $store->promote(
                assetKind: 'model_audio',
                quarantineKey: 'uploads/02/assembled',
                acceptedKey: 'assets/02/candidate',
                expectedBytes: strlen('exact-bytes'),
                expectedSha256: str_repeat('0', 64),
            );
            self::fail('Changed promotion hashes must be rejected.');
        } catch (MediaObjectStoreFailure) {
            self::assertFalse(
                Storage::disk('media-object-accepted-test')->exists(
                    'assets/02/candidate',
                ),
            );
        }
    }

    public function test_lesson_audio_uses_its_private_accepted_disk_mapping(): void
    {
        $store = $this->app->make(MediaObjectStore::class);
        $bytes = 'ID3-long-form-lesson-audio';
        $hash = hash('sha256', $bytes);
        $store->putQuarantineBytes('uploads/03/assembled', $bytes);

        $store->promote(
            assetKind: 'lesson_audio',
            quarantineKey: 'uploads/03/assembled',
            acceptedKey: 'assets/03/'.$hash,
            expectedBytes: strlen($bytes),
            expectedSha256: $hash,
        );
        $stream = $store->openAccepted('lesson_audio', 'assets/03/'.$hash);

        try {
            self::assertSame($bytes, stream_get_contents($stream));
        } finally {
            fclose($stream);
        }
    }
}

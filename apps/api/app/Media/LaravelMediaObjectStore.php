<?php

declare(strict_types=1);

namespace App\Media;

use App\Media\Exceptions\MediaObjectStoreFailure;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Throwable;

final readonly class LaravelMediaObjectStore implements MediaObjectStore
{
    private const int STREAM_CHUNK_BYTES = 65_536;

    public function __construct(private FilesystemManager $filesystems) {}

    public function putQuarantineBytes(string $key, string $bytes): void
    {
        $this->assertSafeKey($key);

        try {
            if (! $this->quarantine()->put($key, $bytes, ['visibility' => 'private'])) {
                throw new MediaObjectStoreFailure;
            }
        } catch (Throwable $exception) {
            $this->fail($exception);
        }
    }

    public function assembleQuarantine(
        array $partKeys,
        string $targetKey,
    ): MediaObjectDigest {
        $this->assertSafeKey($targetKey);

        if ($partKeys === []) {
            throw new MediaObjectStoreFailure;
        }

        $target = fopen('php://temp/maxmemory:2097152', 'w+b');

        if (! is_resource($target)) {
            throw new MediaObjectStoreFailure;
        }

        $hash = hash_init('sha256');
        $bytes = 0;

        try {
            foreach ($partKeys as $partKey) {
                $this->assertSafeKey($partKey);
                $source = $this->openFromDisk($this->quarantine(), $partKey);

                try {
                    while (! feof($source)) {
                        $chunk = fread($source, self::STREAM_CHUNK_BYTES);

                        if ($chunk === false) {
                            throw new MediaObjectStoreFailure;
                        }

                        if ($chunk === '') {
                            if (feof($source)) {
                                break;
                            }

                            throw new MediaObjectStoreFailure;
                        }

                        $written = fwrite($target, $chunk);

                        if ($written !== strlen($chunk)) {
                            throw new MediaObjectStoreFailure;
                        }

                        hash_update($hash, $chunk);
                        $bytes += $written;
                    }
                } finally {
                    fclose($source);
                }
            }

            if (rewind($target) !== true) {
                throw new MediaObjectStoreFailure;
            }

            if (! $this->quarantine()->put(
                $targetKey,
                $target,
                ['visibility' => 'private'],
            )) {
                throw new MediaObjectStoreFailure;
            }

            $digest = new MediaObjectDigest(
                byteSize: $bytes,
                sha256: hash_final($hash),
            );
            $stored = $this->digest($this->openQuarantine($targetKey));

            if (
                $stored->byteSize !== $digest->byteSize
                || ! hash_equals($stored->sha256, $digest->sha256)
            ) {
                throw new MediaObjectStoreFailure;
            }

            return $digest;
        } catch (Throwable $exception) {
            $this->fail($exception);
        } finally {
            fclose($target);
        }
    }

    public function openQuarantine(string $key)
    {
        $this->assertSafeKey($key);

        try {
            return $this->openFromDisk($this->quarantine(), $key);
        } catch (Throwable $exception) {
            $this->fail($exception);
        }
    }

    public function promote(
        string $assetKind,
        string $quarantineKey,
        string $acceptedKey,
        int $expectedBytes,
        string $expectedSha256,
    ): void {
        $this->assertSafeKey($quarantineKey);
        $this->assertSafeKey($acceptedKey);
        $source = $this->openQuarantine($quarantineKey);
        $target = $this->accepted($assetKind);

        try {
            $sourceDigest = $this->digest($source, rewindAfter: true);
            $this->assertDigest($sourceDigest, $expectedBytes, $expectedSha256);

            if (! $target->put(
                $acceptedKey,
                $source,
                ['visibility' => 'private'],
            )) {
                throw new MediaObjectStoreFailure;
            }

            $accepted = $this->digest($this->openAccepted($assetKind, $acceptedKey));
            $this->assertDigest($accepted, $expectedBytes, $expectedSha256);
        } catch (Throwable $exception) {
            try {
                $target->delete($acceptedKey);
            } catch (Throwable) {
                // The reconciliation receipt remains authoritative.
            }

            $this->fail($exception);
        } finally {
            fclose($source);
        }
    }

    public function openAccepted(string $assetKind, string $key)
    {
        $this->assertSafeKey($key);

        try {
            return $this->openFromDisk($this->accepted($assetKind), $key);
        } catch (Throwable $exception) {
            $this->fail($exception);
        }
    }

    public function deleteQuarantine(string $key): void
    {
        $this->assertSafeKey($key);

        try {
            $disk = $this->quarantine();

            if ($disk->exists($key) && ! $disk->delete($key)) {
                throw new MediaObjectStoreFailure;
            }
        } catch (Throwable $exception) {
            $this->fail($exception);
        }
    }

    private function quarantine(): FilesystemAdapter
    {
        $disk = config('media.quarantine_disk');

        if (! is_string($disk) || $disk === '') {
            throw new MediaObjectStoreFailure;
        }

        return $this->disk($disk);
    }

    private function accepted(string $assetKind): FilesystemAdapter
    {
        $disk = config('media.accepted_disks.'.$assetKind);

        if (! is_string($disk) || $disk === '') {
            throw new MediaObjectStoreFailure;
        }

        return $this->disk($disk);
    }

    private function disk(string $name): FilesystemAdapter
    {
        $disk = $this->filesystems->disk($name);

        if (! $disk instanceof FilesystemAdapter) {
            throw new MediaObjectStoreFailure;
        }

        return $disk;
    }

    /** @return resource */
    private function openFromDisk(FilesystemAdapter $disk, string $key)
    {
        $stream = $disk->readStream($key);

        if (! is_resource($stream)) {
            throw new MediaObjectStoreFailure;
        }

        return $stream;
    }

    /** @param resource $stream */
    private function digest($stream, bool $rewindAfter = false): MediaObjectDigest
    {
        $hash = hash_init('sha256');
        $bytes = 0;

        try {
            while (! feof($stream)) {
                $chunk = fread($stream, self::STREAM_CHUNK_BYTES);

                if ($chunk === false) {
                    throw new MediaObjectStoreFailure;
                }

                if ($chunk === '') {
                    if (feof($stream)) {
                        break;
                    }

                    throw new MediaObjectStoreFailure;
                }

                hash_update($hash, $chunk);
                $bytes += strlen($chunk);
            }

            if ($rewindAfter && rewind($stream) !== true) {
                throw new MediaObjectStoreFailure;
            }

            return new MediaObjectDigest($bytes, hash_final($hash));
        } finally {
            if (! $rewindAfter) {
                fclose($stream);
            }
        }
    }

    private function assertDigest(
        MediaObjectDigest $digest,
        int $expectedBytes,
        string $expectedSha256,
    ): void {
        if (
            $digest->byteSize !== $expectedBytes
            || preg_match('/\A[0-9a-f]{64}\z/', $expectedSha256) !== 1
            || ! hash_equals($digest->sha256, $expectedSha256)
        ) {
            throw new MediaObjectStoreFailure;
        }
    }

    private function assertSafeKey(string $key): void
    {
        if (
            $key === ''
            || strlen($key) > 500
            || str_starts_with($key, '/')
            || str_contains($key, '..')
            || str_contains($key, '\\')
            || preg_match('/[\x00-\x20\x7f]/', $key) === 1
        ) {
            throw new MediaObjectStoreFailure;
        }
    }

    private function fail(Throwable $exception): never
    {
        if ($exception instanceof MediaObjectStoreFailure) {
            throw $exception;
        }

        throw new MediaObjectStoreFailure(previous: $exception);
    }
}

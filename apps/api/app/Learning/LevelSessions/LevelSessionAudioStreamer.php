<?php

declare(strict_types=1);

namespace App\Learning\LevelSessions;

use App\Learning\Exceptions\LearningOperationRejected;
use App\Models\LessonPracticeMedia;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class LevelSessionAudioStreamer
{
    public function __construct(
        private readonly LevelSessionDeliveryService $sessions,
    ) {}

    public function stream(
        OrganizationContext $context,
        User $learner,
        int $levelNumber,
        string $partId,
    ): StreamedResponse {
        $detail = $this->sessions->sessionDetail(
            $context,
            $learner,
            $levelNumber,
            $partId,
        );
        $assetId = (string) ($detail['media_asset_id'] ?? '');
        if ($assetId === '') {
            throw new LearningOperationRejected('audio_missing');
        }

        $attachment = LessonPracticeMedia::query()
            ->where('organization_id', $context->organizationId)
            ->where('media_asset_id', $assetId)
            ->where('part_id', $partId)
            ->first();
        if ($attachment === null) {
            throw new LearningOperationRejected('audio_unattached');
        }

        $asset = DB::table('media_assets')
            ->where('organization_id', $context->organizationId)
            ->where('id', $assetId)
            ->where('asset_kind', 'lesson_audio')
            ->first();
        if ($asset === null) {
            throw new LearningOperationRejected('asset_missing');
        }

        $active = DB::table('media_asset_lifecycles')
            ->where('organization_id', $context->organizationId)
            ->where('media_asset_id', $assetId)
            ->where('state', 'active')
            ->exists();
        if (! $active) {
            throw new LearningOperationRejected('asset_inactive');
        }

        $maximumBytes = (int) config(
            'media.kinds.lesson_audio.maximum_bytes',
            20_000_000,
        );
        $allowedMimeTypes = config(
            'media.kinds.lesson_audio.mime_types',
            ['audio/mpeg'],
        );
        $disk = (string) config(
            'media.accepted_disks.lesson_audio',
            config('filesystems.default', 'local'),
        );
        $mime = (string) $asset->mime_type;
        $bytes = (int) $asset->byte_size;
        $hash = (string) $asset->asset_hash;
        $key = (string) $asset->storage_object_key;

        if (
            $key === ''
            || $bytes < 1
            || $bytes > $maximumBytes
            || ! is_array($allowedMimeTypes)
            || ! in_array($mime, $allowedMimeTypes, true)
            || preg_match('/\A[0-9a-f]{64}\z/', $hash) !== 1
        ) {
            throw new LearningOperationRejected('audio_metadata');
        }

        try {
            $source = Storage::disk($disk)->readStream($key);
        } catch (Throwable) {
            throw new LearningOperationRejected('audio_unavailable');
        }
        if (! is_resource($source)) {
            throw new LearningOperationRejected('audio_unavailable');
        }

        $verified = tmpfile();
        if (! is_resource($verified)) {
            fclose($source);
            throw new LearningOperationRejected('audio_unavailable');
        }

        $hasher = hash_init('sha256');
        $read = 0;
        try {
            while (! feof($source)) {
                $chunk = fread($source, 8192);
                if ($chunk === false) {
                    throw new LearningOperationRejected('audio_corrupt');
                }
                if ($chunk === '') {
                    continue;
                }
                $read += strlen($chunk);
                if ($read > $bytes) {
                    throw new LearningOperationRejected('audio_corrupt');
                }
                hash_update($hasher, $chunk);
                $this->writeAll($verified, $chunk);
            }
        } catch (Throwable $exception) {
            fclose($verified);
            if ($exception instanceof LearningOperationRejected) {
                throw $exception;
            }
            throw new LearningOperationRejected('audio_corrupt');
        } finally {
            fclose($source);
        }

        if (
            $read !== $bytes
            || ! hash_equals($hash, hash_final($hasher))
            || ! rewind($verified)
        ) {
            fclose($verified);
            throw new LearningOperationRejected('audio_corrupt');
        }

        return response()->stream(
            static function () use ($verified): void {
                try {
                    fpassthru($verified);
                } finally {
                    fclose($verified);
                }
            },
            200,
            [
                'Cache-Control' => 'private, no-store',
                'Content-Disposition' => 'inline',
                'Content-Length' => (string) $bytes,
                'Content-Type' => $mime,
                'X-Content-SHA256' => $hash,
            ],
        );
    }

    /**
     * @param  resource  $stream
     */
    private function writeAll($stream, string $content): void
    {
        $offset = 0;
        $length = strlen($content);
        while ($offset < $length) {
            $written = fwrite($stream, substr($content, $offset));
            if ($written === false || $written === 0) {
                throw new LearningOperationRejected('audio_corrupt');
            }
            $offset += $written;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Learning\Media;

use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\Missions\DailyMissionDefinition;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class VerifiedPrivateAudioResponder
{
    public function respond(
        DailyMissionDefinition $mission,
    ): StreamedResponse {
        $verified = $this->verifiedContent($mission);

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
                'Content-Length' => (string) $mission
                    ->modelAudioByteSize,
                'Content-Type' => $mission->modelAudioMimeType,
                'X-Content-SHA256' => $mission
                    ->modelAudioContentHash,
            ],
        );
    }

    /**
     * @return resource
     */
    private function verifiedContent(DailyMissionDefinition $mission)
    {
        $maximumBytes = config('learning.model_audio_maximum_bytes');
        $disk = config('learning.model_audio_disk');
        $allowedMimeTypes = config('learning.model_audio_mime_types');

        if (
            ! is_int($maximumBytes)
            || $maximumBytes < 1
            || ! is_string($disk)
            || $disk === ''
            || ! is_array($allowedMimeTypes)
            || ! in_array(
                $mission->modelAudioMimeType,
                $allowedMimeTypes,
                true,
            )
            || $mission->modelAudioByteSize < 1
            || $mission->modelAudioByteSize > $maximumBytes
        ) {
            throw new LearningOperationRejected;
        }

        try {
            $source = Storage::disk($disk)->readStream(
                $mission->modelAudioStorageKey,
            );
        } catch (Throwable) {
            throw new LearningOperationRejected;
        }

        if (! is_resource($source)) {
            throw new LearningOperationRejected;
        }

        $verified = tmpfile();

        if (! is_resource($verified)) {
            fclose($source);

            throw new LearningOperationRejected;
        }

        $hash = hash_init('sha256');
        $bytes = 0;

        try {
            while (! feof($source)) {
                $chunk = fread($source, 8192);

                if ($chunk === false) {
                    throw new LearningOperationRejected;
                }

                if ($chunk === '') {
                    continue;
                }

                $bytes += strlen($chunk);

                if ($bytes > $mission->modelAudioByteSize) {
                    throw new LearningOperationRejected;
                }

                hash_update($hash, $chunk);
                $this->writeAll($verified, $chunk);
            }
        } catch (Throwable $exception) {
            fclose($verified);

            if ($exception instanceof LearningOperationRejected) {
                throw $exception;
            }

            throw new LearningOperationRejected;
        } finally {
            fclose($source);
        }

        if (
            $bytes !== $mission->modelAudioByteSize
            || ! hash_equals(
                $mission->modelAudioContentHash,
                hash_final($hash),
            )
            || ! rewind($verified)
        ) {
            fclose($verified);

            throw new LearningOperationRejected;
        }

        return $verified;
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
                throw new LearningOperationRejected;
            }

            $offset += $written;
        }
    }
}

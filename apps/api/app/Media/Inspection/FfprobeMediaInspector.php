<?php

declare(strict_types=1);

namespace App\Media\Inspection;

use App\Media\MediaInspection;
use App\Media\MediaInspector;
use App\Media\MediaKindPolicy;
use JsonException;
use Throwable;

final class FfprobeMediaInspector implements MediaInspector
{
    private const int COPY_CHUNK_BYTES = 65_536;

    private const int MAXIMUM_OUTPUT_BYTES = 1_000_000;

    public function inspect(
        $stream,
        string $assetKind,
        int $expectedBytes,
        string $expectedSha256,
    ): MediaInspection {
        $policy = $this->policyVersion();
        $binary = config('media.ffprobe_binary');

        if (
            ! is_string($binary)
            || ! str_starts_with($binary, '/')
            || ! is_file($binary)
            || ! is_executable($binary)
        ) {
            return $this->result('unavailable', null, null, $policy);
        }

        $path = tempnam(sys_get_temp_dir(), 'gse-media-inspect-');

        if ($path === false) {
            return $this->result('unavailable', null, null, $policy);
        }

        try {
            if (! $this->copyAndVerify(
                $stream,
                $path,
                $expectedBytes,
                $expectedSha256,
            )) {
                return $this->result('failed', null, null, $policy);
            }

            $document = $this->probe($binary, $path);

            if ($document === null) {
                return $this->result('unavailable', null, null, $policy);
            }

            $evidence = $this->validatedEvidence($document, $assetKind);

            if ($evidence === null) {
                return $this->result('failed', null, null, $policy);
            }

            return $this->result(
                'passed',
                $evidence['mime_type'],
                $evidence['duration_milliseconds'],
                $policy,
            );
        } catch (Throwable) {
            return $this->result('unavailable', null, null, $policy);
        } finally {
            @unlink($path);
        }
    }

    private function copyAndVerify(
        mixed $source,
        string $path,
        int $expectedBytes,
        string $expectedSha256,
    ): bool {
        $target = fopen($path, 'wb');

        if (! is_resource($source) || ! is_resource($target)) {
            if (is_resource($target)) {
                fclose($target);
            }

            return false;
        }

        $hash = hash_init('sha256');
        $bytes = 0;

        try {
            while (! feof($source)) {
                $chunk = fread($source, self::COPY_CHUNK_BYTES);

                if ($chunk === false || ($chunk === '' && ! feof($source))) {
                    return false;
                }

                if ($chunk === '') {
                    break;
                }

                $written = fwrite($target, $chunk);

                if ($written !== strlen($chunk)) {
                    return false;
                }

                $bytes += $written;
                hash_update($hash, $chunk);

                if ($bytes > $expectedBytes) {
                    return false;
                }
            }

            return $bytes === $expectedBytes
                && preg_match('/\A[0-9a-f]{64}\z/', $expectedSha256) === 1
                && hash_equals(hash_final($hash), $expectedSha256);
        } finally {
            fclose($target);
        }
    }

    /** @return array<string, mixed>|null */
    private function probe(string $binary, string $path): ?array
    {
        $process = proc_open(
            [
                $binary,
                '-v',
                'error',
                '-count_frames',
                '-show_entries',
                'format=format_name,duration:stream=codec_type,codec_name,nb_read_frames',
                '-of',
                'json',
                $path,
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            null,
            null,
            ['bypass_shell' => true],
        );

        if (! is_resource($process)) {
            return null;
        }

        try {
            if (count($pipes) !== 3) {
                return null;
            }

            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1], self::MAXIMUM_OUTPUT_BYTES);
            $error = stream_get_contents($pipes[2], 16_384);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $status = proc_close($process);
            $process = null;

            if (
                $status !== 0
                || ! is_string($output)
                || ! is_string($error)
                || trim($error) !== ''
            ) {
                return null;
            }

            try {
                $decoded = json_decode($output, true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return null;
            }

            return is_array($decoded) && ! array_is_list($decoded)
                ? $decoded
                : null;
        } finally {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array{mime_type: string, duration_milliseconds: int}|null
     */
    private function validatedEvidence(
        array $document,
        string $assetKind,
    ): ?array {
        $format = $document['format'] ?? null;
        $streams = $document['streams'] ?? null;

        if (
            ! is_array($format)
            || array_is_list($format)
            || ! is_array($streams)
            || ! array_is_list($streams)
            || $streams === []
            || count($streams) > 8
        ) {
            return null;
        }

        $formatName = $format['format_name'] ?? null;
        $duration = $format['duration'] ?? null;

        if (
            ! is_string($formatName)
            || (! is_string($duration) && ! is_int($duration) && ! is_float($duration))
            || ! is_numeric($duration)
        ) {
            return null;
        }

        $durationMilliseconds = (int) round((float) $duration * 1_000);
        $hasAudio = false;
        $hasVideo = false;

        foreach ($streams as $stream) {
            if (! is_array($stream) || array_is_list($stream)) {
                return null;
            }

            $type = $stream['codec_type'] ?? null;
            $frames = $stream['nb_read_frames'] ?? null;

            if (
                ! is_string($type)
                || ! in_array($type, ['audio', 'video'], true)
                || (! is_string($frames) && ! is_int($frames))
                || ! ctype_digit((string) $frames)
                || (int) $frames < 1
            ) {
                return null;
            }

            $hasAudio = $hasAudio || $type === 'audio';
            $hasVideo = $hasVideo || $type === 'video';
        }

        $mimeType = $this->mimeType(
            $assetKind,
            $formatName,
            $hasAudio,
            $hasVideo,
        );
        $minimum = config('media.kinds.'.$assetKind.'.minimum_duration_milliseconds');
        $maximum = config('media.kinds.'.$assetKind.'.maximum_duration_milliseconds');

        if (
            $mimeType === null
            || ! is_int($minimum)
            || ! is_int($maximum)
            || $durationMilliseconds < $minimum
            || $durationMilliseconds > $maximum
        ) {
            return null;
        }

        return [
            'mime_type' => $mimeType,
            'duration_milliseconds' => $durationMilliseconds,
        ];
    }

    private function mimeType(
        string $assetKind,
        string $formatName,
        bool $hasAudio,
        bool $hasVideo,
    ): ?string {
        $formats = explode(',', $formatName);

        if ($assetKind === MediaKindPolicy::LESSON_VIDEO) {
            return $hasVideo
                && array_intersect($formats, ['mov', 'mp4', 'm4a', '3gp', '3g2', 'mj2']) !== []
                    ? 'video/mp4'
                    : null;
        }

        if (! MediaKindPolicy::isAudio($assetKind) || ! $hasAudio || $hasVideo) {
            return null;
        }

        return match (true) {
            in_array('mp3', $formats, true) => 'audio/mpeg',
            in_array('wav', $formats, true) => 'audio/wav',
            in_array('ogg', $formats, true) => 'audio/ogg',
            in_array('webm', $formats, true) => 'audio/webm',
            array_intersect($formats, ['mov', 'mp4', 'm4a']) !== [] => 'audio/mp4',
            default => null,
        };
    }

    private function result(
        string $outcome,
        ?string $mimeType,
        ?int $durationMilliseconds,
        string $policyVersion,
    ): MediaInspection {
        return new MediaInspection(
            outcome: $outcome,
            mimeType: $mimeType,
            durationMilliseconds: $durationMilliseconds,
            inspectorName: 'ffprobe',
            policyVersion: $policyVersion,
        );
    }

    private function policyVersion(): string
    {
        $version = config('media.inspector_policy_version');

        return is_string($version) && $version !== ''
            ? $version
            : 'unavailable';
    }
}

<?php

declare(strict_types=1);

namespace App\Media\Inspection;

use App\Media\MediaInspection;
use App\Media\MediaInspector;
use App\Media\MediaKindPolicy;

final class DeterministicTestingMediaInspector implements MediaInspector
{
    private const int MAXIMUM_TEST_BYTES = 20_000_000;

    public function inspect(
        $stream,
        string $assetKind,
        int $expectedBytes,
        string $expectedSha256,
    ): MediaInspection {
        $bytes = stream_get_contents($stream, self::MAXIMUM_TEST_BYTES + 1);
        $mimeType = is_string($bytes)
            ? $this->detectMimeType($bytes, $assetKind)
            : null;
        $valid = is_string($bytes)
            && strlen($bytes) === $expectedBytes
            && hash_equals(hash('sha256', $bytes), $expectedSha256)
            && $mimeType !== null;

        return new MediaInspection(
            outcome: $valid ? 'passed' : 'failed',
            mimeType: $valid ? $mimeType : null,
            durationMilliseconds: $valid ? 1_000 : null,
            inspectorName: 'deterministic-testing-inspector',
            policyVersion: 'admin-media-inspection-test-v1',
        );
    }

    private function detectMimeType(string $bytes, string $assetKind): ?string
    {
        if ($assetKind === MediaKindPolicy::LESSON_VIDEO) {
            return strlen($bytes) >= 24
                && substr($bytes, 4, 4) === 'ftyp'
                && str_contains($bytes, 'moov')
                && str_contains($bytes, 'mdat')
                    ? 'video/mp4'
                    : null;
        }

        if (! MediaKindPolicy::isAudio($assetKind)) {
            return null;
        }

        if (
            strlen($bytes) >= 12
            && substr($bytes, 0, 4) === 'RIFF'
            && substr($bytes, 8, 4) === 'WAVE'
        ) {
            return 'audio/wav';
        }

        if (str_starts_with($bytes, 'ID3')) {
            return 'audio/mpeg';
        }

        if (strlen($bytes) >= 12 && substr($bytes, 4, 4) === 'ftyp') {
            return 'audio/mp4';
        }

        if (str_starts_with($bytes, 'OggS')) {
            return 'audio/ogg';
        }

        if (str_starts_with($bytes, "\x1aE\xdf\xa3")) {
            return 'audio/webm';
        }

        return null;
    }
}

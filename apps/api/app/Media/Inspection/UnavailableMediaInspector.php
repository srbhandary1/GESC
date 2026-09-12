<?php

declare(strict_types=1);

namespace App\Media\Inspection;

use App\Media\MediaInspection;
use App\Media\MediaInspector;

final class UnavailableMediaInspector implements MediaInspector
{
    public function inspect(
        $stream,
        string $assetKind,
        int $expectedBytes,
        string $expectedSha256,
    ): MediaInspection {
        return new MediaInspection(
            outcome: 'unavailable',
            mimeType: null,
            durationMilliseconds: null,
            inspectorName: 'unavailable',
            policyVersion: $this->policyVersion(),
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

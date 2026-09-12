<?php

declare(strict_types=1);

namespace App\Media;

interface MediaObjectStore
{
    public function putQuarantineBytes(string $key, string $bytes): void;

    /** @param list<string> $partKeys */
    public function assembleQuarantine(
        array $partKeys,
        string $targetKey,
    ): MediaObjectDigest;

    /** @return resource */
    public function openQuarantine(string $key);

    public function promote(
        string $assetKind,
        string $quarantineKey,
        string $acceptedKey,
        int $expectedBytes,
        string $expectedSha256,
    ): void;

    /** @return resource */
    public function openAccepted(string $assetKind, string $key);

    public function deleteQuarantine(string $key): void;
}

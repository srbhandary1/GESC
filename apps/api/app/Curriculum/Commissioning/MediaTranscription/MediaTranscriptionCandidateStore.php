<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning\MediaTranscription;

interface MediaTranscriptionCandidateStore
{
    public function put(
        string $organizationId,
        string $campaignId,
        string $campaignAssetId,
        string $providerAttemptId,
        MediaTranscriptionCandidate $candidate,
    ): string;

    public function read(
        string $organizationId,
        string $campaignId,
        string $campaignAssetId,
        string $providerAttemptId,
        string $expectedSha256,
    ): MediaTranscriptionCandidate;

    public function digest(
        string $organizationId,
        string $campaignId,
        string $campaignAssetId,
        string $providerAttemptId,
    ): ?string;

    public function delete(
        string $organizationId,
        string $campaignId,
        string $campaignAssetId,
        string $providerAttemptId,
    ): void;

    public function exists(
        string $organizationId,
        string $campaignId,
        string $campaignAssetId,
        string $providerAttemptId,
    ): bool;
}

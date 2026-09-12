<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning\MediaTranscription;

use App\Models\User;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryRejected;
use App\Operator\MediaLibrary\MediaLibraryAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class MediaTranscriptionCandidateQuery
{
    public function __construct(
        private MediaLibraryAuthorization $authorization,
        private MediaTranscriptionCandidateStore $candidates,
    ) {}

    /** @return array<string, mixed> */
    public function get(User $actor, string $campaignId, string $campaignAssetId): array
    {
        $this->authorization->assert($actor, 'media.metadata.manage');
        $context = $this->authorization->context($actor);
        $asset = DB::table('media_transcription_campaign_assets')
            ->where('organization_id', $context->organizationId)
            ->where('campaign_id', $campaignId)
            ->where('id', $campaignAssetId)
            ->where('state', 'candidate_ready')
            ->where('candidate_expires_at', '>', CarbonImmutable::now())
            ->first([
                'id', 'campaign_id', 'media_asset_id', 'part_key', 'sequence',
                'state', 'version', 'candidate_provider_attempt_id',
                'candidate_sha256', 'candidate_expires_at', 'source_sha256',
                'source_duration_milliseconds',
            ]);
        if (! $asset instanceof stdClass) {
            throw new MediaLibraryRejected;
        }
        $candidateHash = (string) $asset->candidate_sha256;
        try {
            $candidate = $this->candidates->read(
                $context->organizationId,
                $campaignId,
                $campaignAssetId,
                (string) $asset->candidate_provider_attempt_id,
                $candidateHash,
            );
        } catch (MediaTranscriptionRejected $exception) {
            throw new MediaLibraryRejected($exception);
        }

        return [
            'campaign_id' => $campaignId,
            'campaign_asset_id' => $campaignAssetId,
            'media_asset_id' => (string) $asset->media_asset_id,
            'part_key' => (string) $asset->part_key,
            'sequence' => (int) $asset->sequence,
            'state' => (string) $asset->state,
            'version' => (int) $asset->version,
            'candidate_sha256' => $candidateHash,
            'candidate_expires_at' => CarbonImmutable::parse(
                (string) $asset->candidate_expires_at,
            )->toIso8601String(),
            'source_sha256' => (string) $asset->source_sha256,
            'source_duration_milliseconds' => (int) $asset->source_duration_milliseconds,
            'locale' => $candidate->locale,
            'transcript' => $candidate->transcript,
            'segments' => array_map(
                static fn (MediaTranscriptionSegment $segment): array => $segment->toArray(),
                $candidate->segments,
            ),
        ];
    }
}

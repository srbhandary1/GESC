<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning\MediaTranscription;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

final readonly class MediaTranscriptionRetentionService
{
    public function __construct(private MediaTranscriptionCandidateStore $candidates) {}

    public function schedule(
        string $organizationId,
        string $campaignId,
        string $campaignAssetId,
        string $providerAttemptId,
        string $candidateSha256,
        CarbonImmutable $at,
    ): void {
        $created = DB::table('media_transcription_candidate_deletions')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'campaign_id' => $campaignId,
            'campaign_asset_id' => $campaignAssetId,
            'provider_attempt_id' => $providerAttemptId,
            'candidate_sha256' => $candidateSha256,
            'state' => 'pending',
            'attempt_count' => 0,
            'next_attempt_at' => $at,
            'hard_deadline_at' => $at->addMinutes(60),
            'last_error_code' => null,
            'completed_at' => null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        if ($created !== 1) {
            return;
        }

        $asset = DB::table('media_transcription_campaign_assets')
            ->where('organization_id', $organizationId)
            ->where('campaign_id', $campaignId)
            ->where('id', $campaignAssetId)
            ->first(['version']);
        $campaign = DB::table('media_transcription_campaigns')
            ->where('organization_id', $organizationId)
            ->where('id', $campaignId)
            ->first(['version']);
        if ($asset instanceof stdClass && $campaign instanceof stdClass) {
            $this->event(
                $organizationId,
                $campaignId,
                $campaignAssetId,
                'candidate.deletion_requested',
                (int) $campaign->version,
                (int) $asset->version,
                null,
                $at,
            );
        }
    }

    /** @return array<string, int> */
    public function run(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $expired = $this->expireCandidates($limit);
        $processed = 0;
        $deleted = 0;
        $failed = 0;
        $deadLettered = 0;

        $ids = DB::table('media_transcription_candidate_deletions')
            ->whereIn('state', ['pending', 'retrying'])
            ->where('next_attempt_at', '<=', CarbonImmutable::now())
            ->orderBy('next_attempt_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            if (! is_string($id)) {
                continue;
            }
            $processed++;
            $outcome = $this->processOne($id);
            $deleted += $outcome === 'deleted' ? 1 : 0;
            $failed += $outcome === 'failed' ? 1 : 0;
            $deadLettered += $outcome === 'dead_letter' ? 1 : 0;
        }

        $this->completeEligibleCampaigns();
        $purgedProvenance = $this->purgeExpiredProvenance($limit);
        $overdue = DB::table('media_transcription_candidate_deletions')
            ->whereIn('state', ['pending', 'retrying'])
            ->where('hard_deadline_at', '<=', CarbonImmutable::now())
            ->count();
        $deadLetterBacklog = DB::table(
            'media_transcription_candidate_deletions',
        )->where('state', 'dead_letter')->count();

        return [
            'expired' => $expired,
            'processed' => $processed,
            'deleted' => $deleted,
            'failed' => $failed,
            'dead_lettered' => $deadLettered,
            'purged_provenance' => $purgedProvenance,
            'overdue_backlog' => $overdue,
            'dead_letter_backlog' => $deadLetterBacklog,
        ];
    }

    private function purgeExpiredProvenance(int $limit): int
    {
        $retentionDays = config(
            'media_transcription.provenance_retention_days',
        );
        if (! is_int($retentionDays) || $retentionDays !== 365) {
            throw new MediaTranscriptionRejected('retention_policy_invalid');
        }
        $cutoff = CarbonImmutable::now()->subDays($retentionDays);
        $ids = DB::table('media_transcription_campaigns')
            ->whereIn('state', ['completed', 'failed'])
            ->where('terminal_at', '<=', $cutoff)
            ->orderBy('terminal_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');
        $purged = 0;

        foreach ($ids as $id) {
            if (! is_string($id)) {
                continue;
            }
            $deleted = DB::transaction(function () use ($id, $cutoff): bool {
                $campaign = DB::table('media_transcription_campaigns')
                    ->where('id', $id)
                    ->whereIn('state', ['completed', 'failed'])
                    ->where('terminal_at', '<=', $cutoff)
                    ->lockForUpdate()
                    ->first(['id', 'organization_id']);
                if (! $campaign instanceof stdClass) {
                    return false;
                }
                $organizationId = (string) $campaign->organization_id;
                $referenced = DB::table('media_transcription_reconciliations')
                    ->where('organization_id', $organizationId)
                    ->where('campaign_id', $id)
                    ->whereNotNull('metadata_revision_id')
                    ->exists();
                $incompleteDeletion = DB::table(
                    'media_transcription_candidate_deletions',
                )
                    ->where('organization_id', $organizationId)
                    ->where('campaign_id', $id)
                    ->where('state', '!=', 'completed')
                    ->exists();
                $candidateWithoutReceipt = DB::table(
                    'media_transcription_campaign_assets as assets',
                )
                    ->where('assets.organization_id', $organizationId)
                    ->where('assets.campaign_id', $id)
                    ->whereNotNull('assets.candidate_sha256')
                    ->whereNotExists(function (Builder $query): void {
                        $query->select(DB::raw('1'))
                            ->from('media_transcription_candidate_deletions as deletions')
                            ->whereColumn(
                                'deletions.organization_id',
                                'assets.organization_id',
                            )
                            ->whereColumn(
                                'deletions.campaign_id',
                                'assets.campaign_id',
                            )
                            ->whereColumn(
                                'deletions.campaign_asset_id',
                                'assets.id',
                            )
                            ->whereColumn(
                                'deletions.candidate_sha256',
                                'assets.candidate_sha256',
                            )
                            ->where('deletions.state', 'completed');
                    })
                    ->exists();
                if ($referenced || $incompleteDeletion || $candidateWithoutReceipt) {
                    return false;
                }

                if (DB::connection()->getDriverName() === 'pgsql') {
                    DB::statement(
                        "SELECT set_config('app.media_transcription_retention_purge', 'on', true)",
                    );
                }

                return DB::table('media_transcription_campaigns')
                    ->where('organization_id', $organizationId)
                    ->where('id', $id)
                    ->whereIn('state', ['completed', 'failed'])
                    ->where('terminal_at', '<=', $cutoff)
                    ->delete() === 1;
            }, 3);
            $purged += $deleted ? 1 : 0;
        }

        return $purged;
    }

    private function expireCandidates(int $limit): int
    {
        $ids = DB::table('media_transcription_campaign_assets')
            ->where('state', 'candidate_ready')
            ->where('candidate_expires_at', '<=', CarbonImmutable::now())
            ->orderBy('candidate_expires_at')
            ->limit($limit)
            ->pluck('id');
        $expired = 0;

        foreach ($ids as $id) {
            if (! is_string($id)) {
                continue;
            }
            DB::transaction(function () use ($id, &$expired): void {
                $asset = DB::table('media_transcription_campaign_assets')
                    ->where('id', $id)
                    ->where('state', 'candidate_ready')
                    ->where('candidate_expires_at', '<=', CarbonImmutable::now())
                    ->lockForUpdate()
                    ->first();
                if (! $asset instanceof stdClass) {
                    return;
                }
                $now = CarbonImmutable::now();
                $version = (int) $asset->version + 1;
                $updated = DB::table('media_transcription_campaign_assets')
                    ->where('organization_id', (string) $asset->organization_id)
                    ->where('campaign_id', (string) $asset->campaign_id)
                    ->where('id', $id)
                    ->where('version', (int) $asset->version)
                    ->where('state', 'candidate_ready')
                    ->update([
                        'state' => 'expired',
                        'version' => $version,
                        'updated_at' => $now,
                    ]);
                if ($updated !== 1) {
                    throw new MediaTranscriptionRejected(
                        'campaign_state_invalid',
                    );
                }
                $campaign = DB::table('media_transcription_campaigns')
                    ->where('organization_id', (string) $asset->organization_id)
                    ->where('id', (string) $asset->campaign_id)
                    ->first(['version', 'state']);
                if (! $campaign instanceof stdClass) {
                    throw new MediaTranscriptionRejected('campaign_scope_invalid');
                }
                $this->event(
                    (string) $asset->organization_id,
                    (string) $asset->campaign_id,
                    $id,
                    'candidate.expired',
                    (int) $campaign->version,
                    $version,
                    null,
                    $now,
                );
                $this->schedule(
                    (string) $asset->organization_id,
                    (string) $asset->campaign_id,
                    $id,
                    (string) $asset->candidate_provider_attempt_id,
                    (string) $asset->candidate_sha256,
                    $now,
                );
                if (in_array((string) $campaign->state, ['running', 'provider_complete'], true)) {
                    $campaignVersion = (int) $campaign->version + 1;
                    $updated = DB::table('media_transcription_campaigns')
                        ->where('organization_id', (string) $asset->organization_id)
                        ->where('id', (string) $asset->campaign_id)
                        ->where('version', (int) $campaign->version)
                        ->whereIn('state', ['running', 'provider_complete'])
                        ->update([
                            'state' => 'failed',
                            'version' => $campaignVersion,
                            'terminal_at' => $now,
                            'updated_at' => $now,
                        ]);
                    if ($updated !== 1) {
                        throw new MediaTranscriptionRejected('campaign_state_invalid');
                    }
                    $this->event(
                        (string) $asset->organization_id,
                        (string) $asset->campaign_id,
                        null,
                        'campaign.failed',
                        $campaignVersion,
                        null,
                        'candidate_expired_unreconciled',
                        $now,
                    );
                }
                $expired++;
            }, 3);
        }

        return $expired;
    }

    private function processOne(string $id): string
    {
        return DB::transaction(function () use ($id): string {
            $deletion = DB::table('media_transcription_candidate_deletions')
                ->where('id', $id)
                ->whereIn('state', ['pending', 'retrying'])
                ->where('next_attempt_at', '<=', CarbonImmutable::now())
                ->lockForUpdate()
                ->first();
            if (! $deletion instanceof stdClass) {
                return 'skipped';
            }
            $now = CarbonImmutable::now();
            $attempts = (int) $deletion->attempt_count + 1;
            $organizationId = (string) $deletion->organization_id;
            $campaignId = (string) $deletion->campaign_id;
            $assetId = (string) $deletion->campaign_asset_id;
            $providerAttemptId = (string) $deletion->provider_attempt_id;

            try {
                $this->candidates->delete(
                    $organizationId,
                    $campaignId,
                    $assetId,
                    $providerAttemptId,
                );
                DB::table('media_transcription_candidate_deletions')
                    ->where('id', $id)
                    ->update([
                        'state' => 'completed',
                        'attempt_count' => $attempts,
                        'last_error_code' => null,
                        'completed_at' => $now,
                        'updated_at' => $now,
                    ]);
                $this->deletionEvent(
                    $deletion,
                    'candidate.deleted',
                    null,
                    $now,
                );

                return 'deleted';
            } catch (Throwable) {
                $dead = $attempts >= (int) config(
                    'media_transcription.candidate.deletion_max_attempts',
                    5,
                );
                DB::table('media_transcription_candidate_deletions')
                    ->where('id', $id)
                    ->update([
                        'state' => $dead ? 'dead_letter' : 'retrying',
                        'attempt_count' => $attempts,
                        'next_attempt_at' => $now->addSeconds((int) config(
                            'media_transcription.candidate.deletion_retry_seconds',
                            60,
                        )),
                        'last_error_code' => 'candidate_deletion_failed',
                        'updated_at' => $now,
                    ]);
                $this->deletionEvent(
                    $deletion,
                    $dead
                        ? 'candidate.deletion_dead_letter'
                        : 'candidate.deletion_failed',
                    'candidate_deletion_failed',
                    $now,
                );

                return $dead ? 'dead_letter' : 'failed';
            }
        }, 3);
    }

    private function deletionEvent(
        stdClass $deletion,
        string $type,
        ?string $code,
        CarbonImmutable $at,
    ): void {
        $campaign = DB::table('media_transcription_campaigns')
            ->where('organization_id', (string) $deletion->organization_id)
            ->where('id', (string) $deletion->campaign_id)
            ->first(['version']);
        $asset = DB::table('media_transcription_campaign_assets')
            ->where('organization_id', (string) $deletion->organization_id)
            ->where('id', (string) $deletion->campaign_asset_id)
            ->first(['version']);
        if ($campaign instanceof stdClass && $asset instanceof stdClass) {
            $this->event(
                (string) $deletion->organization_id,
                (string) $deletion->campaign_id,
                (string) $deletion->campaign_asset_id,
                $type,
                (int) $campaign->version,
                (int) $asset->version,
                $code,
                $at,
            );
        }
    }

    private function completeEligibleCampaigns(): void
    {
        $ids = DB::table('media_transcription_campaigns')
            ->where('state', 'provider_complete')
            ->pluck('id');
        foreach ($ids as $id) {
            if (! is_string($id)) {
                continue;
            }
            DB::transaction(function () use ($id): void {
                $campaign = DB::table('media_transcription_campaigns')
                    ->where('id', $id)
                    ->where('state', 'provider_complete')
                    ->lockForUpdate()
                    ->first();
                if (! $campaign instanceof stdClass) {
                    return;
                }
                $organizationId = (string) $campaign->organization_id;
                $assetCount = DB::table('media_transcription_campaign_assets')
                    ->where('organization_id', $organizationId)
                    ->where('campaign_id', $id)
                    ->count();
                $unreconciled = DB::table('media_transcription_campaign_assets')
                    ->where('organization_id', $organizationId)
                    ->where('campaign_id', $id)
                    ->whereNotIn('state', [
                        'confirmed', 'corrected', 'discarded', 'needs_attention',
                    ])
                    ->exists();
                $reconciliationCount = DB::table('media_transcription_reconciliations')
                    ->where('organization_id', $organizationId)
                    ->where('campaign_id', $id)
                    ->count();
                $incompleteDeletion = DB::table('media_transcription_candidate_deletions')
                    ->where('organization_id', $organizationId)
                    ->where('campaign_id', $id)
                    ->where('state', '!=', 'completed')
                    ->exists();
                if (
                    $assetCount !== 4
                    || $reconciliationCount !== 4
                    || $unreconciled
                    || $incompleteDeletion
                ) {
                    return;
                }
                $now = CarbonImmutable::now();
                $version = (int) $campaign->version + 1;
                $updated = DB::table('media_transcription_campaigns')
                    ->where('organization_id', $organizationId)
                    ->where('id', $id)
                    ->where('version', $version - 1)
                    ->where('state', 'provider_complete')
                    ->update([
                        'state' => 'completed',
                        'version' => $version,
                        'terminal_at' => $now,
                        'updated_at' => $now,
                    ]);
                if ($updated !== 1) {
                    throw new MediaTranscriptionRejected(
                        'campaign_state_invalid',
                    );
                }
                $this->event(
                    $organizationId,
                    $id,
                    null,
                    'campaign.completed',
                    $version,
                    null,
                    null,
                    $now,
                );
            }, 3);
        }
    }

    private function event(
        string $organizationId,
        string $campaignId,
        ?string $assetId,
        string $type,
        int $campaignVersion,
        ?int $assetVersion,
        ?string $code,
        CarbonImmutable $at,
    ): void {
        DB::table('media_transcription_events')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'campaign_id' => $campaignId,
            'campaign_asset_id' => $assetId,
            'provider_attempt_id' => null,
            'attempt_number' => null,
            'reserved_uploaded_bytes' => null,
            'reserved_billable_milliseconds' => null,
            'reserved_estimated_cost_microunits' => null,
            'event_type' => $type,
            'campaign_version' => $campaignVersion,
            'asset_version' => $assetVersion,
            'actor_membership_id' => null,
            'safe_failure_code' => $code,
            'occurred_at' => $at,
            'created_at' => $at,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning\MediaTranscription;

use App\Audit\AuditEventRecorder;
use App\Models\User;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryConflict;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryRejected;
use App\Operator\MediaLibrary\MediaLibraryAuthorization;
use App\Operator\MediaLibrary\MediaLibraryCommandResult;
use App\Operator\MediaLibrary\MediaLibraryIdempotency;
use App\Operator\MediaLibrary\MediaMetadataCommands;
use App\Outbox\OutboxEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Normalizer;
use stdClass;

final readonly class MediaTranscriptionReconciliationCommands
{
    public function __construct(
        private MediaLibraryAuthorization $authorization,
        private MediaLibraryIdempotency $idempotency,
        private MediaMetadataCommands $metadata,
        private MediaTranscriptionCandidateStore $candidates,
        private MediaTranscriptionRetentionService $retention,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function reconcile(
        User $actor,
        string $campaignId,
        string $campaignAssetId,
        int $expectedAssetVersion,
        string $candidateSha256,
        string $outcome,
        ?string $operatorLabel,
        ?string $correctedTranscript,
        ?string $expectedHeadRevisionId,
        int $expectedMetadataVersion,
        string $idempotencyKey,
    ): MediaLibraryCommandResult {
        $outcome = $this->outcome($outcome);
        $operatorLabel = $this->conditionalLabel($operatorLabel, $outcome);
        $correctedTranscript = $this->conditionalCorrection(
            $correctedTranscript,
            $outcome,
        );

        $result = DB::transaction(function () use (
            $actor,
            $campaignId,
            $campaignAssetId,
            $expectedAssetVersion,
            $candidateSha256,
            $outcome,
            $operatorLabel,
            $correctedTranscript,
            $expectedHeadRevisionId,
            $expectedMetadataVersion,
            $idempotencyKey,
        ): MediaLibraryCommandResult {
            $this->authorization->assert($actor, 'media.metadata.manage');
            $context = $this->authorization->context($actor);
            $membership = $this->authorization->actorMembership($actor);
            $operation = 'media.transcription.reconcile';
            $fingerprint = $this->idempotency->fingerprint(
                $operation,
                'media_transcription_campaign_asset',
                $campaignAssetId,
                [
                    'campaign_id' => $campaignId,
                    'candidate_sha256' => $candidateSha256,
                    'corrected_transcript' => $correctedTranscript,
                    'expected_asset_version' => $expectedAssetVersion,
                    'expected_head_revision_id' => $expectedHeadRevisionId,
                    'expected_metadata_version' => $expectedMetadataVersion,
                    'operator_label' => $operatorLabel,
                    'outcome' => $outcome,
                ],
            );
            $claim = $this->idempotency->claim(
                $actor,
                $idempotencyKey,
                $operation,
                'media_transcription_campaign_asset',
                $campaignAssetId,
                $fingerprint,
            );
            if ($claim->replayed) {
                $reconciliationId = $claim->resultId
                    ?? throw new MediaLibraryConflict(MediaLibraryConflict::IDEMPOTENCY);

                return new MediaLibraryCommandResult(
                    'media_transcription_reconciliation',
                    $reconciliationId,
                    true,
                    $this->summary(
                        $context->organizationId,
                        $campaignId,
                        $campaignAssetId,
                    ),
                );
            }

            $asset = DB::table('media_transcription_campaign_assets')
                ->where('organization_id', $context->organizationId)
                ->where('campaign_id', $campaignId)
                ->where('id', $campaignAssetId)
                ->lockForUpdate()
                ->first();
            if (! $asset instanceof stdClass) {
                throw new MediaLibraryRejected;
            }
            if (
                (string) $asset->state !== 'candidate_ready'
                || (int) $asset->version !== $expectedAssetVersion
                || ! is_string($asset->candidate_provider_attempt_id)
                || ! is_string($asset->candidate_sha256)
                || ! hash_equals((string) $asset->candidate_sha256, $candidateSha256)
            ) {
                throw new MediaLibraryConflict(MediaLibraryConflict::CONCURRENT_CHANGE);
            }
            if (
                ! is_string($asset->candidate_expires_at)
                || CarbonImmutable::parse($asset->candidate_expires_at)->isPast()
            ) {
                throw new MediaLibraryRejected;
            }

            try {
                $candidate = $this->candidates->read(
                    $context->organizationId,
                    $campaignId,
                    $campaignAssetId,
                    (string) $asset->candidate_provider_attempt_id,
                    $candidateSha256,
                );
            } catch (MediaTranscriptionRejected $exception) {
                throw new MediaLibraryRejected($exception);
            }

            $metadataRevisionId = null;
            $metadataContentHash = null;
            if (in_array($outcome, ['confirmed', 'corrected'], true)) {
                $transcript = $outcome === 'confirmed'
                    ? $candidate->transcript
                    : ($correctedTranscript ?? throw new MediaLibraryRejected);
                $metadataResult = $this->metadata->appendEnglish(
                    actor: $actor,
                    assetId: (string) $asset->media_asset_id,
                    expectedHeadRevisionId: $expectedHeadRevisionId,
                    expectedMetadataVersion: $expectedMetadataVersion,
                    operatorLabel: $operatorLabel ?? throw new MediaLibraryRejected,
                    transcript: $transcript,
                    textAlternative: null,
                    idempotencyKey: $idempotencyKey.'.metadata',
                );
                $metadataRevisionId = $metadataResult->resultId;
                $hash = $metadataResult->data['content_hash'] ?? null;
                if (! is_string($hash)) {
                    throw new MediaLibraryRejected;
                }
                $metadataContentHash = $hash;
            }

            $now = CarbonImmutable::now();
            $reconciliationId = (string) Str::ulid();
            $membershipId = (string) $membership->id;
            DB::table('media_transcription_reconciliations')->insert([
                'id' => $reconciliationId,
                'organization_id' => $context->organizationId,
                'campaign_id' => $campaignId,
                'campaign_asset_id' => $campaignAssetId,
                'media_asset_id' => (string) $asset->media_asset_id,
                'candidate_sha256' => $candidateSha256,
                'outcome' => $outcome,
                'metadata_revision_id' => $metadataRevisionId,
                'metadata_content_hash' => $metadataContentHash,
                'reconciled_by_membership_id' => $membershipId,
                'reconciled_at' => $now,
                'created_at' => $now,
            ]);
            $assetVersion = (int) $asset->version + 1;
            $updated = DB::table('media_transcription_campaign_assets')
                ->where('organization_id', $context->organizationId)
                ->where('campaign_id', $campaignId)
                ->where('id', $campaignAssetId)
                ->where('version', $expectedAssetVersion)
                ->update([
                    'state' => $outcome,
                    'version' => $assetVersion,
                    'reconciled_at' => $now,
                    'updated_at' => $now,
                ]);
            if ($updated !== 1) {
                throw new MediaLibraryConflict(
                    MediaLibraryConflict::CONCURRENT_CHANGE,
                );
            }
            $campaign = DB::table('media_transcription_campaigns')
                ->where('organization_id', $context->organizationId)
                ->where('id', $campaignId)
                ->first(['version']);
            if (! $campaign instanceof stdClass) {
                throw new MediaLibraryRejected;
            }
            DB::table('media_transcription_events')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $context->organizationId,
                'campaign_id' => $campaignId,
                'campaign_asset_id' => $campaignAssetId,
                'provider_attempt_id' => null,
                'attempt_number' => null,
                'reserved_uploaded_bytes' => null,
                'reserved_billable_milliseconds' => null,
                'reserved_estimated_cost_microunits' => null,
                'event_type' => 'candidate.reconciled',
                'campaign_version' => (int) $campaign->version,
                'asset_version' => $assetVersion,
                'actor_membership_id' => $membershipId,
                'safe_failure_code' => null,
                'occurred_at' => $now,
                'created_at' => $now,
            ]);
            $this->retention->schedule(
                $context->organizationId,
                $campaignId,
                $campaignAssetId,
                (string) $asset->candidate_provider_attempt_id,
                $candidateSha256,
                $now,
            );
            $this->idempotency->complete(
                $claim,
                'media_transcription_reconciliation',
                $reconciliationId,
                $now,
            );
            $this->audit->recordTenant(
                'media.transcription.reconciled',
                'media_transcription_reconciliation',
                $reconciliationId,
            );
            $payload = [
                'media_asset_id' => (string) $asset->media_asset_id,
                'media_transcription_campaign_id' => $campaignId,
                'media_transcription_campaign_asset_id' => $campaignAssetId,
                'media_transcription_reconciliation_id' => $reconciliationId,
            ];
            if ($metadataRevisionId !== null) {
                $payload['media_asset_metadata_revision_id'] = $metadataRevisionId;
            }
            $this->outbox->recordTenant(
                'media.transcription.reconciled',
                'media_transcription_reconciliation',
                $reconciliationId,
                $payload,
            );

            return new MediaLibraryCommandResult(
                'media_transcription_reconciliation',
                $reconciliationId,
                false,
                [
                    'campaign_id' => $campaignId,
                    'campaign_asset_id' => $campaignAssetId,
                    'reconciliation_id' => $reconciliationId,
                    'outcome' => $outcome,
                    'asset_version' => $assetVersion,
                    'metadata_revision_id' => $metadataRevisionId,
                    'metadata_content_hash' => $metadataContentHash,
                ],
            );
        }, 3);

        return $result;
    }

    private function outcome(string $value): string
    {
        if (! in_array($value, [
            'confirmed', 'corrected', 'discarded', 'needs_attention',
        ], true)) {
            throw new MediaLibraryRejected;
        }

        return $value;
    }

    private function conditionalLabel(?string $value, string $outcome): ?string
    {
        if (in_array($outcome, ['discarded', 'needs_attention'], true)) {
            if ($value !== null) {
                throw new MediaLibraryRejected;
            }

            return null;
        }
        if ($value === null) {
            throw new MediaLibraryRejected;
        }
        $normalized = Normalizer::normalize(trim($value), Normalizer::FORM_C);
        if (! is_string($normalized) || $normalized === '' || mb_strlen($normalized) > 160) {
            throw new MediaLibraryRejected;
        }

        return $normalized;
    }

    private function conditionalCorrection(?string $value, string $outcome): ?string
    {
        if ($outcome !== 'corrected') {
            if ($value !== null) {
                throw new MediaLibraryRejected;
            }

            return null;
        }
        if ($value === null) {
            throw new MediaLibraryRejected;
        }
        $normalized = Normalizer::normalize(trim($value), Normalizer::FORM_C);
        if (
            ! is_string($normalized)
            || $normalized === ''
            || mb_strlen($normalized) > 32_000
            || strlen($normalized) > 65_536
        ) {
            throw new MediaLibraryRejected;
        }

        return $normalized;
    }

    /** @return array<string, mixed> */
    private function summary(
        string $organizationId,
        string $campaignId,
        string $campaignAssetId,
    ): array {
        $row = DB::table('media_transcription_reconciliations')
            ->where('organization_id', $organizationId)
            ->where('campaign_id', $campaignId)
            ->where('campaign_asset_id', $campaignAssetId)
            ->first();
        if (! $row instanceof stdClass) {
            throw new MediaLibraryRejected;
        }

        return [
            'campaign_id' => $campaignId,
            'campaign_asset_id' => $campaignAssetId,
            'reconciliation_id' => (string) $row->id,
            'outcome' => (string) $row->outcome,
            'metadata_revision_id' => $row->metadata_revision_id,
            'metadata_content_hash' => $row->metadata_content_hash,
        ];
    }
}

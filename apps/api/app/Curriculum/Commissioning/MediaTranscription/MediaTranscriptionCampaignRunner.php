<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning\MediaTranscription;

use App\Curriculum\CanonicalJson;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class MediaTranscriptionCampaignRunner
{
    public function __construct(
        private MediaTranscriptionConfiguration $configuration,
        private CurriculumMediaSourceReader $sources,
        private MediaTranscriptionCandidateStore $candidates,
        private MediaTranscriptionRetentionService $retention,
        private HttpFactory $http,
    ) {}

    /**
     * @param  callable(array<string, mixed>, HttpFactory): mixed  $providerFactory
     * @return array<string, int|string>
     */
    public function run(
        string $campaignId,
        string $membershipId,
        callable $providerFactory,
    ): array {
        if (! $this->localCampaignGuard($campaignId, true)) {
            throw new MediaTranscriptionRejected('campaign_state_invalid');
        }

        $databaseLockAcquired = false;
        try {
            $databaseLockAcquired = $this->acquireDatabaseCampaignLock(
                $campaignId,
            );
            if (! $databaseLockAcquired) {
                throw new MediaTranscriptionRejected(
                    'campaign_state_invalid',
                );
            }

            return $this->runLocked($campaignId, $membershipId, $providerFactory);
        } finally {
            if ($databaseLockAcquired) {
                $this->releaseDatabaseCampaignLock($campaignId);
            }
            $this->localCampaignGuard($campaignId, false);
        }
    }

    /**
     * @param  callable(array<string, mixed>, HttpFactory): mixed  $providerFactory
     * @return array<string, int|string>
     */
    private function runLocked(
        string $campaignId,
        string $membershipId,
        callable $providerFactory,
    ): array {
        $authorization = $this->configuration->authorize($campaignId);
        $this->prepare($campaignId, $membershipId, $authorization);
        $this->reconcileInterruptedReservations($campaignId);

        foreach ($this->manifest($authorization) as $manifestAsset) {
            while (true) {
                $asset = $this->campaignAsset($campaignId, $manifestAsset);
                $state = $this->string($asset, 'state');
                if ($state === 'candidate_ready' || $this->isReconciled($state)) {
                    break;
                }
                if ($state === 'failed') {
                    $this->failCampaign($campaignId, 'asset_failed');
                    break 2;
                }
                if (! in_array($state, ['pending', 'retry_pending'], true)) {
                    throw new MediaTranscriptionRejected('campaign_state_invalid');
                }

                $source = null;
                try {
                    $authorization = $this->configuration->authorize($campaignId);
                    $this->assertCampaignConfiguration($campaignId, $authorization);
                    $source = $this->readValidatedSource($manifestAsset);
                    $reservation = $this->reserve(
                        $campaignId,
                        $this->string($asset, 'id'),
                        $manifestAsset,
                        $authorization,
                    );
                } catch (MediaTranscriptionRejected $exception) {
                    unset($source);
                    if ($this->isTerminalPreDispatchFailure($exception->safeCode)) {
                        $this->failCampaign($campaignId, $exception->safeCode);
                        break 2;
                    }

                    throw $exception;
                }

                $candidateHash = null;
                $responseMetadataHash = null;
                try {
                    $authorization = $this->configuration->authorize($campaignId);
                    $this->assertCampaignConfiguration($campaignId, $authorization);
                    $provider = $providerFactory($authorization, $this->http);
                    if (! $provider instanceof CurriculumMediaTranscriptionProvider) {
                        throw new MediaTranscriptionRejected(
                            'provider_not_authorized',
                        );
                    }
                    $result = $provider->transcribe(new MediaTranscriptionRequest(
                        audioBytes: $source,
                        sourceSha256: $this->manifestString($manifestAsset, 'source_sha256'),
                        sourceDurationMilliseconds: $this->manifestInteger(
                            $manifestAsset,
                            'source_duration_milliseconds',
                        ),
                    ));
                    $responseMetadataHash = $result->responseMetadataSha256;
                    $candidate = new MediaTranscriptionCandidate(
                        transcript: $result->transcript,
                        locale: 'en-IN',
                        segments: $result->segments,
                    );
                    $candidateHash = CanonicalJson::sha256(
                        $candidate->toArray(),
                    );
                    $storedCandidateHash = $this->candidates->put(
                        $this->authorizationString($authorization, 'organization_id'),
                        $campaignId,
                        $this->string($asset, 'id'),
                        (string) $reservation['attempt_id'],
                        $candidate,
                    );
                    if (! hash_equals($candidateHash, $storedCandidateHash)) {
                        throw new MediaTranscriptionRejected(
                            'candidate_storage_failed',
                        );
                    }
                    $this->succeedAttempt(
                        $campaignId,
                        $this->string($asset, 'id'),
                        $reservation,
                        $result,
                        $candidateHash,
                    );
                    break;
                } catch (MediaTranscriptionProviderException $exception) {
                    $retry = $this->failAttempt(
                        $campaignId,
                        $this->string($asset, 'id'),
                        $reservation,
                        $exception->safeCode,
                        $exception->retryable,
                        $exception->latencyMilliseconds,
                    );
                    if (! $retry) {
                        break 2;
                    }
                } catch (MediaTranscriptionRejected $exception) {
                    $this->failAttempt(
                        $campaignId,
                        $this->string($asset, 'id'),
                        $reservation,
                        $exception->safeCode,
                        false,
                        0,
                        $candidateHash,
                        $responseMetadataHash,
                    );
                    if (isset($candidateHash)) {
                        DB::transaction(function () use (
                            $authorization,
                            $campaignId,
                            $asset,
                            $candidateHash,
                            $reservation,
                        ): void {
                            $this->retention->schedule(
                                $this->authorizationString(
                                    $authorization,
                                    'organization_id',
                                ),
                                $campaignId,
                                $this->string($asset, 'id'),
                                (string) $reservation['attempt_id'],
                                $candidateHash,
                                CarbonImmutable::now(),
                            );
                        }, 3);
                    }
                    break 2;
                } finally {
                    unset($source);
                }
            }
        }

        $this->markProviderComplete($campaignId);

        return $this->report($campaignId);
    }

    private function localCampaignGuard(string $campaignId, bool $acquire): bool
    {
        /** @var array<string, true> $activeCampaigns */
        static $activeCampaigns = [];

        if (! $acquire) {
            unset($activeCampaigns[$campaignId]);

            return true;
        }
        if (isset($activeCampaigns[$campaignId])) {
            return false;
        }

        $activeCampaigns[$campaignId] = true;

        return true;
    }

    private function acquireDatabaseCampaignLock(string $campaignId): bool
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return true;
        }

        [$firstKey, $secondKey] = $this->campaignLockKeys($campaignId);
        $row = DB::selectOne(
            'SELECT pg_try_advisory_lock(?, ?) AS acquired',
            [$firstKey, $secondKey],
        );

        return $row instanceof stdClass
            && $this->databaseBoolean($row->acquired ?? false);
    }

    private function releaseDatabaseCampaignLock(string $campaignId): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        [$firstKey, $secondKey] = $this->campaignLockKeys($campaignId);
        $row = DB::selectOne(
            'SELECT pg_advisory_unlock(?, ?) AS released',
            [$firstKey, $secondKey],
        );
        if (! $row instanceof stdClass
            || ! $this->databaseBoolean($row->released ?? false)) {
            throw new MediaTranscriptionRejected('campaign_state_invalid');
        }
    }

    /** @return array{int, int} */
    private function campaignLockKeys(string $campaignId): array
    {
        $parts = unpack('Nfirst/Nsecond', hash('sha256', $campaignId, true));
        if (! is_array($parts)
            || ! isset($parts['first'], $parts['second'])) {
            throw new MediaTranscriptionRejected('campaign_state_invalid');
        }

        return [
            $this->signedInteger32((int) $parts['first']),
            $this->signedInteger32((int) $parts['second']),
        ];
    }

    private function signedInteger32(int $value): int
    {
        return $value > 2_147_483_647 ? $value - 4_294_967_296 : $value;
    }

    private function databaseBoolean(mixed $value): bool
    {
        return $value === true
            || $value === 1
            || $value === '1'
            || $value === 't'
            || $value === 'true';
    }

    /** @param array<string, mixed> $authorization */
    private function prepare(
        string $campaignId,
        string $membershipId,
        array $authorization,
    ): void {
        if (! Str::isUlid($membershipId)) {
            throw new MediaTranscriptionRejected('campaign_authority_invalid');
        }

        DB::transaction(function () use (
            $campaignId,
            $membershipId,
            $authorization,
        ): void {
            $organizationId = $this->authorizationString(
                $authorization,
                'organization_id',
            );
            $programId = $this->authorizationString($authorization, 'program_id');
            $this->assertCommissioner($organizationId, $membershipId);
            if (! DB::table('programs')
                ->where('organization_id', $organizationId)
                ->where('id', $programId)
                ->lockForUpdate()
                ->exists()) {
                throw new MediaTranscriptionRejected('campaign_scope_invalid');
            }

            $now = CarbonImmutable::now();
            $caps = $this->authorizationArray($authorization, 'caps');
            $configuration = $this->authorizationArray(
                $authorization,
                'configuration',
            );
            $created = DB::table('media_transcription_campaigns')->insertOrIgnore([
                'id' => $campaignId,
                'organization_id' => $organizationId,
                'program_id' => $programId,
                'version' => 1,
                'state' => 'running',
                'approval_reference' => 'APP-0052',
                'approved_request_sha256' => $this->authorizationString(
                    $authorization,
                    'approved_request_sha256',
                ),
                'manifest_sha256' => $this->authorizationString(
                    $authorization,
                    'manifest_sha256',
                ),
                'provider_key' => 'azure-speech',
                'provider_model' => $this->arrayString($configuration, 'model'),
                'provider_region' => 'centralindia',
                'provider_api_version' => '2025-10-15',
                'locale' => 'en-IN',
                'configuration_sha256' => $this->authorizationString(
                    $authorization,
                    'configuration_sha256',
                ),
                'vendor_evidence_sha256' => $this->authorizationString(
                    $authorization,
                    'vendor_evidence_sha256',
                ),
                'price_evidence_sha256' => $this->authorizationString(
                    $authorization,
                    'price_evidence_sha256',
                ),
                'credential_version' => $this->authorizationString(
                    $authorization,
                    'credential_version',
                ),
                'logical_asset_cap' => $this->arrayInteger($caps, 'logical_assets'),
                'http_attempt_cap' => $this->arrayInteger($caps, 'http_attempts'),
                'uploaded_byte_cap' => $this->arrayInteger($caps, 'uploaded_bytes'),
                'billable_millisecond_cap' => $this->arrayInteger(
                    $caps,
                    'billable_milliseconds',
                ),
                'estimated_cost_microunit_cap' => $this->arrayInteger(
                    $caps,
                    'estimated_cost_microunits',
                ),
                'reserved_http_attempts' => 0,
                'reserved_uploaded_bytes' => 0,
                'reserved_billable_milliseconds' => 0,
                'reserved_estimated_cost_microunits' => 0,
                'initiated_by_membership_id' => $membershipId,
                'started_at' => $now,
                'terminal_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]) === 1;

            $campaign = DB::table('media_transcription_campaigns')
                ->where('organization_id', $organizationId)
                ->where('id', $campaignId)
                ->lockForUpdate()
                ->first();
            if (! $campaign instanceof stdClass) {
                throw new MediaTranscriptionRejected('campaign_already_exists');
            }
            $this->assertCampaignRow($campaign, $authorization);
            if (! hash_equals(
                $this->string($campaign, 'initiated_by_membership_id'),
                $membershipId,
            )) {
                throw new MediaTranscriptionRejected(
                    'campaign_authority_invalid',
                );
            }

            foreach ($this->manifest($authorization) as $entry) {
                $validated = $this->validatedAsset($organizationId, $programId, $entry, true);
                DB::table('media_transcription_campaign_assets')->insertOrIgnore([
                    'id' => (string) Str::ulid(),
                    'organization_id' => $organizationId,
                    'campaign_id' => $campaignId,
                    'program_id' => $programId,
                    'sequence' => $this->manifestInteger($entry, 'sequence'),
                    'part_key' => $this->manifestString($entry, 'part_key'),
                    'media_asset_id' => $this->manifestString($entry, 'media_asset_id'),
                    'selected_scan_id' => $this->string($validated, 'selected_scan_id'),
                    'media_rights_revision_id' => $this->manifestString(
                        $entry,
                        'media_rights_revision_id',
                    ),
                    'rights_evidence_status' => 'approved',
                    'rights_evidence_hash' => $this->string($validated, 'evidence_hash'),
                    'source_sha256' => $this->manifestString($entry, 'source_sha256'),
                    'source_bytes' => $this->manifestInteger($entry, 'source_bytes'),
                    'source_duration_milliseconds' => $this->manifestInteger(
                        $entry,
                        'source_duration_milliseconds',
                    ),
                    'locale' => 'en-IN',
                    'state' => 'pending',
                    'version' => 1,
                    'candidate_provider_attempt_id' => null,
                    'candidate_sha256' => null,
                    'response_metadata_sha256' => null,
                    'candidate_stored_at' => null,
                    'candidate_expires_at' => null,
                    'reconciled_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if (
                DB::table('media_transcription_campaign_assets')
                    ->where('organization_id', $organizationId)
                    ->where('campaign_id', $campaignId)
                    ->count() !== 4
            ) {
                throw new MediaTranscriptionRejected('campaign_manifest_invalid');
            }

            if ($created) {
                $this->event(
                    $organizationId,
                    $campaignId,
                    null,
                    null,
                    'campaign.started',
                    1,
                    null,
                    $membershipId,
                    null,
                    $now,
                );
            }
        }, 3);
    }

    /** @param array<string, mixed> $entry */
    private function validatedAsset(
        string $organizationId,
        string $programId,
        array $entry,
        bool $lock,
    ): stdClass {
        $query = DB::table('media_assets')
            ->join('media_asset_lifecycles', function ($join): void {
                $join->on('media_asset_lifecycles.organization_id', '=', 'media_assets.organization_id')
                    ->on('media_asset_lifecycles.media_asset_id', '=', 'media_assets.id');
            })
            ->join('media_security_scans', function ($join): void {
                $join->on('media_security_scans.organization_id', '=', 'media_asset_lifecycles.organization_id')
                    ->on('media_security_scans.id', '=', 'media_asset_lifecycles.selected_scan_id');
            })
            ->join('media_rights_revisions', function ($join) use ($programId): void {
                $join->on('media_rights_revisions.organization_id', '=', 'media_assets.organization_id')
                    ->on('media_rights_revisions.media_asset_id', '=', 'media_assets.id')
                    ->where('media_rights_revisions.program_id', $programId);
            })
            ->where('media_assets.organization_id', $organizationId)
            ->where('media_assets.id', $this->manifestString($entry, 'media_asset_id'))
            ->where('media_assets.asset_kind', 'lesson_audio')
            ->where('media_assets.mime_type', 'audio/mpeg')
            ->where('media_assets.asset_hash', $this->manifestString($entry, 'source_sha256'))
            ->where('media_assets.byte_size', $this->manifestInteger($entry, 'source_bytes'))
            ->where('media_asset_lifecycles.state', 'active')
            ->where('media_security_scans.inspection_outcome', 'passed')
            ->where('media_security_scans.malware_outcome', 'clean')
            ->where('media_security_scans.object_hash', $this->manifestString($entry, 'source_sha256'))
            ->where('media_security_scans.byte_size', $this->manifestInteger($entry, 'source_bytes'))
            ->where('media_security_scans.duration_milliseconds', $this->manifestInteger(
                $entry,
                'source_duration_milliseconds',
            ))
            ->where('media_security_scans.detected_mime_type', 'audio/mpeg')
            ->where('media_rights_revisions.id', $this->manifestString(
                $entry,
                'media_rights_revision_id',
            ))
            ->where('media_rights_revisions.evidence_status', 'approved')
            ->where('media_rights_revisions.selected_asset_hash', $this->manifestString(
                $entry,
                'source_sha256',
            ))
            ->where('media_rights_revisions.derivative_use_allowed', true)
            ->where('media_rights_revisions.effective_at', '<=', CarbonImmutable::now())
            ->where(function (Builder $query): void {
                $query->whereNull('media_rights_revisions.expires_at')
                    ->orWhere('media_rights_revisions.expires_at', '>', CarbonImmutable::now());
            })
            ->whereRaw(
                'media_rights_revisions.revision_number = (SELECT MAX(latest.revision_number) FROM media_rights_revisions latest WHERE latest.organization_id = media_rights_revisions.organization_id AND latest.program_id = media_rights_revisions.program_id AND latest.media_asset_id = media_rights_revisions.media_asset_id)',
            )
            ->select([
                'media_assets.storage_object_key',
                'media_asset_lifecycles.selected_scan_id',
                'media_rights_revisions.evidence_hash',
                'media_rights_revisions.rights_appointment_id',
                'media_rights_revisions.rights_appointment_event_id',
                'media_rights_revisions.confirmed_by_membership_id',
                'media_rights_revisions.confirmed_at',
            ]);

        if ($lock) {
            $query->lockForUpdate();
        }
        $row = $query->first();
        if (! $row instanceof stdClass) {
            throw new MediaTranscriptionRejected('source_authority_invalid');
        }
        $this->assertRightsAuthority($organizationId, $programId, $row, $lock);

        return $row;
    }

    private function assertRightsAuthority(
        string $organizationId,
        string $programId,
        stdClass $rights,
        bool $lock,
    ): void {
        $appointmentId = $this->string($rights, 'rights_appointment_id');
        $acceptanceEventId = $this->string(
            $rights,
            'rights_appointment_event_id',
        );
        $membershipId = $this->string(
            $rights,
            'confirmed_by_membership_id',
        );
        $confirmedAt = CarbonImmutable::parse(
            $this->string($rights, 'confirmed_at'),
        );
        $appointmentQuery = DB::table('authority_appointments')
            ->where('organization_id', $organizationId)
            ->where('id', $appointmentId)
            ->where('authority_kind', 'rights_confirmer')
            ->where('appointee_membership_id', $membershipId)
            ->where('scope_type', 'program')
            ->where('program_id', $programId)
            ->where('valid_from', '<=', $confirmedAt)
            ->where(function (Builder $query) use ($confirmedAt): void {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>', $confirmedAt);
            });
        if ($lock) {
            $appointmentQuery->lockForUpdate();
        }
        $appointment = $appointmentQuery->first(['id']);
        if (! $appointment instanceof stdClass) {
            throw new MediaTranscriptionRejected('source_authority_invalid');
        }

        $accepted = DB::table('authority_appointment_events')
            ->where('organization_id', $organizationId)
            ->where('authority_appointment_id', $appointmentId)
            ->where('id', $acceptanceEventId)
            ->where('event_type', 'accepted')
            ->where('effective_at', '<=', $confirmedAt)
            ->exists();
        $terminatedAtConfirmation = DB::table('authority_appointment_events')
            ->where('organization_id', $organizationId)
            ->where('authority_appointment_id', $appointmentId)
            ->whereIn('event_type', [
                'surrendered', 'revoked', 'revoked_for_cause', 'expired',
            ])
            ->where('effective_at', '<=', $confirmedAt)
            ->exists();
        $invalidated = DB::table('authority_appointment_events')
            ->where('organization_id', $organizationId)
            ->where('authority_appointment_id', $appointmentId)
            ->where('event_type', 'revoked_for_cause')
            ->where('invalidates_prior_approvals', true)
            ->where('effective_at', '<=', CarbonImmutable::now())
            ->exists();
        if (! $accepted || $terminatedAtConfirmation || $invalidated) {
            throw new MediaTranscriptionRejected('source_authority_invalid');
        }
    }

    /** @param array<string, mixed> $entry */
    private function readValidatedSource(array $entry): string
    {
        $organizationId = $this->configString('organization_id');
        $programId = $this->configString('program_id');
        $row = $this->validatedAsset($organizationId, $programId, $entry, false);

        return $this->sources->readExact(
            'lesson_audio',
            $this->string($row, 'storage_object_key'),
            $this->manifestInteger($entry, 'source_bytes'),
            $this->manifestString($entry, 'source_sha256'),
        );
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $authorization
     * @return array<string, int|string>
     */
    private function reserve(
        string $campaignId,
        string $campaignAssetId,
        array $entry,
        array $authorization,
    ): array {
        return DB::transaction(function () use (
            $campaignId,
            $campaignAssetId,
            $entry,
            $authorization,
        ): array {
            $organizationId = $this->authorizationString($authorization, 'organization_id');
            $campaign = $this->lockedCampaign($organizationId, $campaignId);
            $this->assertCampaignRow($campaign, $authorization);
            if ($this->string($campaign, 'state') !== 'running') {
                throw new MediaTranscriptionRejected('campaign_state_invalid');
            }
            $asset = $this->lockedCampaignAsset($organizationId, $campaignId, $campaignAssetId);
            if (! in_array($this->string($asset, 'state'), ['pending', 'retry_pending'], true)) {
                throw new MediaTranscriptionRejected('campaign_state_invalid');
            }
            $this->validatedAsset(
                $organizationId,
                $this->authorizationString($authorization, 'program_id'),
                $entry,
                true,
            );

            $attemptNumber = DB::table('media_transcription_events')
                ->where('organization_id', $organizationId)
                ->where('campaign_asset_id', $campaignAssetId)
                ->where('event_type', 'provider_attempt.reserved')
                ->count() + 1;
            if ($attemptNumber > 2) {
                throw new MediaTranscriptionRejected('attempt_cap_reached');
            }

            $bytes = $this->manifestInteger($entry, 'source_bytes');
            $duration = $this->manifestInteger($entry, 'source_duration_milliseconds');
            $rate = $this->authorizationInteger(
                $authorization,
                'rate_microunits_per_second',
            );
            $cost = (int) ceil($duration / 1000) * $rate;
            $nextAttempts = $this->integer($campaign, 'reserved_http_attempts') + 1;
            $nextBytes = $this->integer($campaign, 'reserved_uploaded_bytes') + $bytes;
            $nextDuration = $this->integer(
                $campaign,
                'reserved_billable_milliseconds',
            ) + $duration;
            $nextCost = $this->integer(
                $campaign,
                'reserved_estimated_cost_microunits',
            ) + $cost;
            if (
                $nextAttempts > $this->integer($campaign, 'http_attempt_cap')
                || $nextBytes > $this->integer($campaign, 'uploaded_byte_cap')
                || $nextDuration > $this->integer($campaign, 'billable_millisecond_cap')
                || $nextCost > $this->integer($campaign, 'estimated_cost_microunit_cap')
            ) {
                throw new MediaTranscriptionRejected('campaign_cap_reached');
            }

            $campaignVersion = $this->integer($campaign, 'version') + 1;
            $assetVersion = $this->integer($asset, 'version') + 1;
            $attemptId = (string) Str::ulid();
            $now = CarbonImmutable::now();
            $campaignUpdated = DB::table('media_transcription_campaigns')
                ->where('organization_id', $organizationId)
                ->where('id', $campaignId)
                ->where('version', $campaignVersion - 1)
                ->update([
                    'version' => $campaignVersion,
                    'reserved_http_attempts' => $nextAttempts,
                    'reserved_uploaded_bytes' => $nextBytes,
                    'reserved_billable_milliseconds' => $nextDuration,
                    'reserved_estimated_cost_microunits' => $nextCost,
                    'updated_at' => $now,
                ]);
            $assetUpdated = DB::table('media_transcription_campaign_assets')
                ->where('organization_id', $organizationId)
                ->where('id', $campaignAssetId)
                ->where('version', $assetVersion - 1)
                ->update([
                    'state' => 'processing',
                    'version' => $assetVersion,
                    'updated_at' => $now,
                ]);
            if ($campaignUpdated !== 1 || $assetUpdated !== 1) {
                throw new MediaTranscriptionRejected('campaign_state_invalid');
            }
            $reservationEventId = $this->event(
                $organizationId,
                $campaignId,
                $campaignAssetId,
                $attemptId,
                'provider_attempt.reserved',
                $campaignVersion,
                $assetVersion,
                null,
                null,
                $now,
                $attemptNumber,
                $bytes,
                $duration,
                $cost,
            );

            return [
                'attempt_id' => $attemptId,
                'attempt_number' => $attemptNumber,
                'cost' => $cost,
                'duration' => $duration,
                'reservation_event_id' => $reservationEventId,
                'started_at' => $now->format('Y-m-d H:i:s.uP'),
            ];
        }, 3);
    }

    /** @param array<string, int|string> $reservation */
    private function succeedAttempt(
        string $campaignId,
        string $campaignAssetId,
        array $reservation,
        MediaTranscriptionResult $result,
        string $candidateHash,
    ): void {
        DB::transaction(function () use (
            $campaignId,
            $campaignAssetId,
            $reservation,
            $result,
            $candidateHash,
        ): void {
            $organizationId = $this->configString('organization_id');
            $campaign = $this->lockedCampaign($organizationId, $campaignId);
            $asset = $this->lockedCampaignAsset($organizationId, $campaignId, $campaignAssetId);
            if ($this->string($asset, 'state') !== 'processing') {
                throw new MediaTranscriptionRejected('campaign_state_invalid');
            }
            $now = CarbonImmutable::now();
            $assetVersion = $this->integer($asset, 'version') + 1;
            $this->insertAttempt(
                $campaign,
                $asset,
                $reservation,
                'succeeded',
                null,
                false,
                $result->latencyMilliseconds,
                $result->usageMilliseconds,
                $candidateHash,
                $result->responseMetadataSha256,
                $now,
            );
            $updated = DB::table('media_transcription_campaign_assets')
                ->where('organization_id', $organizationId)
                ->where('id', $campaignAssetId)
                ->where('version', $assetVersion - 1)
                ->update([
                    'state' => 'candidate_ready',
                    'version' => $assetVersion,
                    'candidate_provider_attempt_id' => (string) $reservation['attempt_id'],
                    'candidate_sha256' => $candidateHash,
                    'response_metadata_sha256' => $result->responseMetadataSha256,
                    'candidate_stored_at' => $now,
                    'candidate_expires_at' => $now->addDays(7),
                    'updated_at' => $now,
                ]);
            if ($updated !== 1) {
                throw new MediaTranscriptionRejected('campaign_state_invalid');
            }
            $this->event(
                $organizationId,
                $campaignId,
                $campaignAssetId,
                (string) $reservation['attempt_id'],
                'provider_attempt.succeeded',
                $this->integer($campaign, 'version'),
                $assetVersion,
                null,
                null,
                $now,
            );
        }, 3);
    }

    /** @param array<string, int|string> $reservation */
    private function failAttempt(
        string $campaignId,
        string $campaignAssetId,
        array $reservation,
        string $safeCode,
        bool $retryable,
        int $latency,
        ?string $cleanupCandidateHash = null,
        ?string $responseMetadataHash = null,
    ): bool {
        return DB::transaction(function () use (
            $campaignId,
            $campaignAssetId,
            $reservation,
            $safeCode,
            $retryable,
            $latency,
            $cleanupCandidateHash,
            $responseMetadataHash,
        ): bool {
            $organizationId = $this->configString('organization_id');
            $campaign = $this->lockedCampaign($organizationId, $campaignId);
            $asset = $this->lockedCampaignAsset($organizationId, $campaignId, $campaignAssetId);
            $attemptNumber = (int) $reservation['attempt_number'];
            $willRetry = $retryable && $attemptNumber < 2;
            $now = CarbonImmutable::now();
            $assetVersion = $this->integer($asset, 'version') + 1;
            $this->insertAttempt(
                $campaign,
                $asset,
                $reservation,
                $safeCode === 'provider_connection_failure' ? 'failed_uncertain' : 'failed',
                $safeCode,
                $willRetry,
                $latency,
                (int) $reservation['duration'],
                $cleanupCandidateHash,
                $responseMetadataHash,
                $now,
            );
            $updated = DB::table('media_transcription_campaign_assets')
                ->where('organization_id', $organizationId)
                ->where('id', $campaignAssetId)
                ->where('version', $assetVersion - 1)
                ->update([
                    'state' => $willRetry ? 'retry_pending' : 'failed',
                    'version' => $assetVersion,
                    'updated_at' => $now,
                ]);
            if ($updated !== 1) {
                throw new MediaTranscriptionRejected('campaign_state_invalid');
            }
            $this->event(
                $organizationId,
                $campaignId,
                $campaignAssetId,
                (string) $reservation['attempt_id'],
                'provider_attempt.failed',
                $this->integer($campaign, 'version'),
                $assetVersion,
                null,
                $safeCode,
                $now,
            );
            if (! $willRetry) {
                $this->transitionCampaignToFailed($campaign, $safeCode, $now);
            }

            return $willRetry;
        }, 3);
    }

    private function reconcileInterruptedReservations(string $campaignId): void
    {
        $organizationId = $this->configString('organization_id');
        $cleanup = [];
        $events = DB::table('media_transcription_events as events')
            ->leftJoin('media_transcription_provider_attempts as attempts', function ($join): void {
                $join->on('attempts.organization_id', '=', 'events.organization_id')
                    ->on('attempts.id', '=', 'events.provider_attempt_id');
            })
            ->where('events.organization_id', $organizationId)
            ->where('events.campaign_id', $campaignId)
            ->where('events.event_type', 'provider_attempt.reserved')
            ->whereNull('attempts.id')
            ->orderBy('events.occurred_at')
            ->get(['events.*']);

        foreach ($events as $event) {
            $assetId = $this->string($event, 'campaign_asset_id');
            $attemptId = $this->string($event, 'provider_attempt_id');
            $candidateHash = $this->candidates->digest(
                $organizationId,
                $campaignId,
                $assetId,
                $attemptId,
            );
            $scheduled = DB::transaction(function () use (
                $event,
                $organizationId,
                $campaignId,
                $assetId,
                $attemptId,
                $candidateHash,
            ): bool {
                $campaign = $this->lockedCampaign($organizationId, $campaignId);
                $asset = $this->lockedCampaignAsset($organizationId, $campaignId, $assetId);
                if (DB::table('media_transcription_provider_attempts')
                    ->where('organization_id', $organizationId)
                    ->where('campaign_id', $campaignId)
                    ->where('campaign_asset_id', $assetId)
                    ->where('id', $attemptId)
                    ->exists()) {
                    return false;
                }
                $now = CarbonImmutable::now();
                $reservation = [
                    'attempt_id' => $attemptId,
                    'attempt_number' => $this->integer($event, 'attempt_number'),
                    'cost' => $this->integer($event, 'reserved_estimated_cost_microunits'),
                    'duration' => $this->integer($event, 'reserved_billable_milliseconds'),
                    'reservation_event_id' => $this->string($event, 'id'),
                    'started_at' => $this->string($event, 'occurred_at'),
                ];
                $retry = (int) $reservation['attempt_number'] < 2;
                $this->insertAttempt(
                    $campaign,
                    $asset,
                    $reservation,
                    'failed_uncertain',
                    'provider_attempt_interrupted',
                    $retry,
                    0,
                    (int) $reservation['duration'],
                    $candidateHash,
                    null,
                    $now,
                );
                $assetVersion = $this->integer($asset, 'version') + 1;
                DB::table('media_transcription_campaign_assets')
                    ->where('organization_id', $organizationId)
                    ->where('id', $assetId)
                    ->update([
                        'state' => $retry ? 'retry_pending' : 'failed',
                        'version' => $assetVersion,
                        'updated_at' => $now,
                    ]);
                $this->event(
                    $organizationId,
                    $campaignId,
                    $assetId,
                    $attemptId,
                    'provider_attempt.interrupted',
                    $this->integer($campaign, 'version'),
                    $assetVersion,
                    null,
                    'provider_attempt_interrupted',
                    $now,
                );
                if ($candidateHash !== null) {
                    $this->retention->schedule(
                        $organizationId,
                        $campaignId,
                        $assetId,
                        $attemptId,
                        $candidateHash,
                        $now,
                    );
                }
                if (! $retry) {
                    $this->transitionCampaignToFailed(
                        $campaign,
                        'provider_attempt_interrupted',
                        $now,
                    );
                }

                return $candidateHash !== null;
            }, 3);
            if ($scheduled) {
                $cleanup[] = [$assetId, $attemptId];
            }
        }

        if ($cleanup === []) {
            return;
        }
        $this->retention->run(500);
        foreach ($cleanup as [$assetId, $attemptId]) {
            if ($this->candidates->exists(
                $organizationId,
                $campaignId,
                $assetId,
                $attemptId,
            )) {
                throw new MediaTranscriptionRejected('candidate_cleanup_pending');
            }
        }
    }

    private function markProviderComplete(string $campaignId): void
    {
        $organizationId = $this->configString('organization_id');
        DB::transaction(function () use ($campaignId, $organizationId): void {
            $campaign = $this->lockedCampaign($organizationId, $campaignId);
            if ($this->string($campaign, 'state') !== 'running') {
                return;
            }
            $unfinished = DB::table('media_transcription_campaign_assets')
                ->where('organization_id', $organizationId)
                ->where('campaign_id', $campaignId)
                ->whereNotIn('state', [
                    'candidate_ready', 'confirmed', 'corrected', 'discarded',
                    'needs_attention', 'expired',
                ])->exists();
            if ($unfinished) {
                return;
            }
            $now = CarbonImmutable::now();
            $version = $this->integer($campaign, 'version') + 1;
            $updated = DB::table('media_transcription_campaigns')
                ->where('organization_id', $organizationId)
                ->where('id', $campaignId)
                ->where('version', $version - 1)
                ->where('state', 'running')
                ->update([
                    'state' => 'provider_complete',
                    'version' => $version,
                    'updated_at' => $now,
                ]);
            if ($updated !== 1) {
                throw new MediaTranscriptionRejected('campaign_state_invalid');
            }
            $this->event(
                $organizationId,
                $campaignId,
                null,
                null,
                'campaign.provider_complete',
                $version,
                null,
                null,
                null,
                $now,
            );
        }, 3);
    }

    private function failCampaign(string $campaignId, string $code): void
    {
        DB::transaction(function () use ($campaignId, $code): void {
            $campaign = $this->lockedCampaign($this->configString('organization_id'), $campaignId);
            if ($this->string($campaign, 'state') === 'running') {
                $this->transitionCampaignToFailed($campaign, $code, CarbonImmutable::now());
            }
        }, 3);
    }

    private function transitionCampaignToFailed(
        stdClass $campaign,
        string $code,
        CarbonImmutable $now,
    ): void {
        $organizationId = $this->string($campaign, 'organization_id');
        $campaignId = $this->string($campaign, 'id');
        $version = $this->integer($campaign, 'version') + 1;
        $updated = DB::table('media_transcription_campaigns')
            ->where('organization_id', $organizationId)
            ->where('id', $campaignId)
            ->where('version', $version - 1)
            ->where('state', 'running')
            ->update([
                'state' => 'failed',
                'version' => $version,
                'terminal_at' => $now,
                'updated_at' => $now,
            ]);
        if ($updated !== 1) {
            throw new MediaTranscriptionRejected('campaign_state_invalid');
        }
        $this->event(
            $organizationId,
            $campaignId,
            null,
            null,
            'campaign.failed',
            $version,
            null,
            null,
            $code,
            $now,
        );
    }

    /**
     * @param  array<string, int|string>  $reservation
     */
    private function insertAttempt(
        stdClass $campaign,
        stdClass $asset,
        array $reservation,
        string $status,
        ?string $safeCode,
        bool $retryable,
        int $latency,
        int $usage,
        ?string $candidateHash,
        ?string $responseHash,
        CarbonImmutable $completedAt,
    ): void {
        DB::table('media_transcription_provider_attempts')->insert([
            'id' => (string) $reservation['attempt_id'],
            'organization_id' => $this->string($campaign, 'organization_id'),
            'campaign_id' => $this->string($campaign, 'id'),
            'campaign_asset_id' => $this->string($asset, 'id'),
            'reservation_event_id' => (string) $reservation['reservation_event_id'],
            'attempt_number' => (int) $reservation['attempt_number'],
            'provider_key' => $this->string($campaign, 'provider_key'),
            'provider_model' => $this->string($campaign, 'provider_model'),
            'provider_region' => $this->string($campaign, 'provider_region'),
            'provider_api_version' => $this->string($campaign, 'provider_api_version'),
            'configuration_sha256' => $this->string($campaign, 'configuration_sha256'),
            'input_sha256' => $this->string($asset, 'source_sha256'),
            'input_bytes' => $this->integer($asset, 'source_bytes'),
            'input_duration_milliseconds' => $this->integer(
                $asset,
                'source_duration_milliseconds',
            ),
            'status' => $status,
            'safe_failure_code' => $safeCode,
            'retryable' => $retryable,
            'latency_milliseconds' => max(0, $latency),
            'usage_milliseconds' => max(0, $usage),
            'estimated_cost_microunits' => (int) $reservation['cost'],
            'candidate_sha256' => $candidateHash,
            'response_metadata_sha256' => $responseHash,
            'started_at' => (string) $reservation['started_at'],
            'completed_at' => $completedAt,
            'created_at' => $completedAt,
        ]);
    }

    /** @param array<string, mixed> $authorization */
    private function assertCampaignConfiguration(
        string $campaignId,
        array $authorization,
    ): void {
        $campaign = DB::table('media_transcription_campaigns')
            ->where('organization_id', $this->authorizationString($authorization, 'organization_id'))
            ->where('id', $campaignId)
            ->first();
        if (! $campaign instanceof stdClass) {
            throw new MediaTranscriptionRejected('campaign_scope_invalid');
        }
        $this->assertCampaignRow($campaign, $authorization);
    }

    /** @param array<string, mixed> $authorization */
    private function assertCampaignRow(stdClass $campaign, array $authorization): void
    {
        foreach ([
            'organization_id', 'program_id', 'approved_request_sha256',
            'manifest_sha256', 'configuration_sha256',
            'vendor_evidence_sha256', 'price_evidence_sha256',
            'credential_version',
        ] as $key) {
            if (! hash_equals(
                $this->string($campaign, $key),
                $this->authorizationString($authorization, $key),
            )) {
                throw new MediaTranscriptionRejected('campaign_evidence_changed');
            }
        }
        if (
            $this->string($campaign, 'approval_reference') !== 'APP-0052'
            || $this->string($campaign, 'provider_key') !== 'azure-speech'
            || $this->string($campaign, 'provider_region') !== 'centralindia'
            || $this->string($campaign, 'provider_api_version') !== '2025-10-15'
            || $this->string($campaign, 'locale') !== 'en-IN'
        ) {
            throw new MediaTranscriptionRejected('campaign_evidence_changed');
        }
    }

    private function assertCommissioner(string $organizationId, string $membershipId): void
    {
        $allowed = DB::table('memberships')
            ->join('membership_roles', function ($join): void {
                $join->on('membership_roles.organization_id', '=', 'memberships.organization_id')
                    ->on('membership_roles.membership_id', '=', 'memberships.id');
            })
            ->join('role_permissions', function ($join): void {
                $join->on('role_permissions.organization_id', '=', 'membership_roles.organization_id')
                    ->on('role_permissions.role_id', '=', 'membership_roles.role_id');
            })
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('memberships.organization_id', $organizationId)
            ->where('memberships.id', $membershipId)
            ->where('memberships.status', 'active')
            ->where('permissions.key', 'media.metadata.manage')
            ->lockForUpdate()
            ->exists();
        if (! $allowed) {
            throw new MediaTranscriptionRejected('campaign_authority_invalid');
        }
    }

    /** @param array<string, mixed> $entry */
    private function campaignAsset(string $campaignId, array $entry): stdClass
    {
        $asset = DB::table('media_transcription_campaign_assets')
            ->where('organization_id', $this->configString('organization_id'))
            ->where('campaign_id', $campaignId)
            ->where('sequence', $this->manifestInteger($entry, 'sequence'))
            ->where('media_asset_id', $this->manifestString($entry, 'media_asset_id'))
            ->first();
        if (! $asset instanceof stdClass) {
            throw new MediaTranscriptionRejected('campaign_manifest_invalid');
        }

        return $asset;
    }

    private function lockedCampaign(string $organizationId, string $campaignId): stdClass
    {
        $row = DB::table('media_transcription_campaigns')
            ->where('organization_id', $organizationId)
            ->where('id', $campaignId)
            ->lockForUpdate()
            ->first();
        if (! $row instanceof stdClass) {
            throw new MediaTranscriptionRejected('campaign_scope_invalid');
        }

        return $row;
    }

    private function lockedCampaignAsset(
        string $organizationId,
        string $campaignId,
        string $campaignAssetId,
    ): stdClass {
        $row = DB::table('media_transcription_campaign_assets')
            ->where('organization_id', $organizationId)
            ->where('campaign_id', $campaignId)
            ->where('id', $campaignAssetId)
            ->lockForUpdate()
            ->first();
        if (! $row instanceof stdClass) {
            throw new MediaTranscriptionRejected('campaign_scope_invalid');
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $authorization
     * @return list<array<string, mixed>>
     */
    private function manifest(array $authorization): array
    {
        $manifest = $authorization['manifest'] ?? null;
        if (! is_array($manifest) || ! array_is_list($manifest)) {
            throw new MediaTranscriptionRejected('campaign_manifest_invalid');
        }

        return $manifest;
    }

    /** @return array<string, int|string> */
    public function report(string $campaignId): array
    {
        if (! Str::isUlid($campaignId)) {
            throw new MediaTranscriptionRejected('campaign_scope_invalid');
        }
        $organizationId = $this->configString('organization_id');
        $campaign = $this->lockedCampaign($organizationId, $campaignId);
        $attempts = DB::table('media_transcription_provider_attempts')
            ->where('organization_id', $organizationId)
            ->where('campaign_id', $campaignId);
        $assets = DB::table('media_transcription_campaign_assets')
            ->where('organization_id', $organizationId)
            ->where('campaign_id', $campaignId);

        return [
            'campaign_id' => $campaignId,
            'state' => $this->string($campaign, 'state'),
            'manifest_sha256' => $this->string($campaign, 'manifest_sha256'),
            'configuration_sha256' => $this->string(
                $campaign,
                'configuration_sha256',
            ),
            'vendor_evidence_sha256' => $this->string(
                $campaign,
                'vendor_evidence_sha256',
            ),
            'price_evidence_sha256' => $this->string(
                $campaign,
                'price_evidence_sha256',
            ),
            'logical_assets' => (clone $assets)->count(),
            'logical_asset_cap' => $this->integer($campaign, 'logical_asset_cap'),
            'reserved_http_attempts' => $this->integer($campaign, 'reserved_http_attempts'),
            'http_attempt_cap' => $this->integer($campaign, 'http_attempt_cap'),
            'reserved_uploaded_bytes' => $this->integer($campaign, 'reserved_uploaded_bytes'),
            'uploaded_byte_cap' => $this->integer($campaign, 'uploaded_byte_cap'),
            'reserved_billable_milliseconds' => $this->integer(
                $campaign,
                'reserved_billable_milliseconds',
            ),
            'billable_millisecond_cap' => $this->integer(
                $campaign,
                'billable_millisecond_cap',
            ),
            'reserved_estimated_cost_microunits' => $this->integer(
                $campaign,
                'reserved_estimated_cost_microunits',
            ),
            'estimated_cost_microunits_cap' => $this->integer(
                $campaign,
                'estimated_cost_microunit_cap',
            ),
            'provider_attempts' => (clone $attempts)->count(),
            'provider_successes' => (clone $attempts)
                ->where('status', 'succeeded')->count(),
            'provider_failures' => (clone $attempts)
                ->whereIn('status', ['failed', 'failed_uncertain'])->count(),
            'human_reconciliations' => DB::table('media_transcription_reconciliations')
                ->where('organization_id', $organizationId)
                ->where('campaign_id', $campaignId)
                ->count(),
            'candidate_deletions_completed' => DB::table(
                'media_transcription_candidate_deletions',
            )
                ->where('organization_id', $organizationId)
                ->where('campaign_id', $campaignId)
                ->where('state', 'completed')
                ->count(),
            'candidate_deletion_failures' => DB::table(
                'media_transcription_candidate_deletions',
            )
                ->where('organization_id', $organizationId)
                ->where('campaign_id', $campaignId)
                ->whereIn('state', ['retrying', 'dead_letter'])
                ->count(),
        ];
    }

    private function isReconciled(string $state): bool
    {
        return in_array($state, [
            'confirmed', 'corrected', 'discarded', 'needs_attention', 'expired',
        ], true);
    }

    private function isTerminalPreDispatchFailure(string $safeCode): bool
    {
        return in_array($safeCode, [
            'attempt_cap_reached',
            'campaign_cap_reached',
            'campaign_manifest_invalid',
            'campaign_scope_invalid',
            'source_authority_invalid',
            'source_object_invalid',
        ], true);
    }

    private function event(
        string $organizationId,
        string $campaignId,
        ?string $campaignAssetId,
        ?string $attemptId,
        string $type,
        int $campaignVersion,
        ?int $assetVersion,
        ?string $actorMembershipId,
        ?string $safeCode,
        CarbonImmutable $at,
        ?int $attemptNumber = null,
        ?int $bytes = null,
        ?int $duration = null,
        ?int $cost = null,
    ): string {
        $eventId = (string) Str::ulid();
        DB::table('media_transcription_events')->insert([
            'id' => $eventId,
            'organization_id' => $organizationId,
            'campaign_id' => $campaignId,
            'campaign_asset_id' => $campaignAssetId,
            'provider_attempt_id' => $attemptId,
            'attempt_number' => $attemptNumber,
            'reserved_uploaded_bytes' => $bytes,
            'reserved_billable_milliseconds' => $duration,
            'reserved_estimated_cost_microunits' => $cost,
            'event_type' => $type,
            'campaign_version' => $campaignVersion,
            'asset_version' => $assetVersion,
            'actor_membership_id' => $actorMembershipId,
            'safe_failure_code' => $safeCode,
            'occurred_at' => $at,
            'created_at' => $at,
        ]);

        return $eventId;
    }

    private function configString(string $key): string
    {
        $value = config('media_transcription.'.$key);
        if (! is_string($value) || $value === '') {
            throw new MediaTranscriptionRejected('campaign_scope_invalid');
        }

        return $value;
    }

    /** @param array<string, mixed> $entry */
    private function manifestString(array $entry, string $key): string
    {
        return $this->arrayString($entry, $key);
    }

    /** @param array<string, mixed> $entry */
    private function manifestInteger(array $entry, string $key): int
    {
        return $this->arrayInteger($entry, $key);
    }

    /** @param array<string, mixed> $values */
    private function arrayString(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new MediaTranscriptionRejected('campaign_evidence_invalid');
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private function arrayInteger(array $values, string $key): int
    {
        $value = $values[$key] ?? null;
        if (! is_int($value) || $value < 0) {
            throw new MediaTranscriptionRejected('campaign_evidence_invalid');
        }

        return $value;
    }

    /** @param array<string, mixed> $authorization */
    private function authorizationString(array $authorization, string $key): string
    {
        return $this->arrayString($authorization, $key);
    }

    /** @param array<string, mixed> $authorization */
    private function authorizationInteger(array $authorization, string $key): int
    {
        return $this->arrayInteger($authorization, $key);
    }

    /**
     * @param  array<string, mixed>  $authorization
     * @return array<string, mixed>
     */
    private function authorizationArray(array $authorization, string $key): array
    {
        $value = $authorization[$key] ?? null;
        if (! is_array($value)) {
            throw new MediaTranscriptionRejected('campaign_evidence_invalid');
        }

        return $value;
    }

    private function string(stdClass $row, string $key): string
    {
        $value = $row->{$key} ?? null;
        if (! is_string($value) || $value === '') {
            throw new MediaTranscriptionRejected('campaign_evidence_invalid');
        }

        return $value;
    }

    private function integer(stdClass $row, string $key): int
    {
        $value = $row->{$key} ?? null;
        if (! is_int($value) && ! is_numeric($value)) {
            throw new MediaTranscriptionRejected('campaign_evidence_invalid');
        }

        return (int) $value;
    }
}

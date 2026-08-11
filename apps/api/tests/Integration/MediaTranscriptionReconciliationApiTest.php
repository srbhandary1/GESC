<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionCandidate;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionCandidateStore;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionRetentionService;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionSegment;
use App\Models\AuthenticationSession;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class MediaTranscriptionReconciliationApiTest extends TenancyIntegrationTestCase
{
    private const string OTP = '830417';

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(
            OtpCodeGenerator::class,
            new MediaTranscriptionOtpCodeGenerator(self::OTP),
        );
        $this->app->instance(
            OtpDeliveryAdapter::class,
            new MediaTranscriptionNullOtpDeliveryAdapter,
        );
        Storage::fake('media_transcription_candidates_test');
        config()->set(
            'media_transcription.candidate.disk',
            'media_transcription_candidates_test',
        );
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_candidate_read_and_confirmed_reconciliation_are_private_atomic_and_replay_safe(): void
    {
        CarbonImmutable::setTestNow('2026-08-10T08:00:00+00:00');
        $actor = $this->signInWeb('+919310000081');
        $organization = $this->createOrganization('Transcription Reconciliation');
        [$membership, $context] = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $membership,
            $context,
            ['media.metadata.manage'],
            'transcription-reconciler',
        );
        $this->selectMembership($membership);
        [$campaignId, $campaignAssetId, $mediaAssetId, $candidateHash, $attemptId] =
            $this->candidateFixture($organization, $membership);
        $candidateUri = "/api/v1/operator/media/transcription-campaigns/{$campaignId}/assets/{$campaignAssetId}/candidate";
        $reconcileUri = "/api/v1/operator/media/transcription-campaigns/{$campaignId}/assets/{$campaignAssetId}/reconciliations";
        $body = [
            'expected_asset_version' => 2,
            'candidate_sha256' => $candidateHash,
            'outcome' => 'confirmed',
            'operator_label' => 'Level 1 — Can — Introduction',
            'expected_head_revision_id' => null,
            'expected_metadata_version' => 0,
        ];

        $this->getJson($candidateUri)
            ->assertForbidden()
            ->assertJsonPath('message_key', 'problem.fresh_mfa_required');

        $this->freshenCurrentWebSession($actor);
        $candidate = $this->getJson($candidateUri)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('campaign_id', $campaignId)
            ->assertJsonPath('campaign_asset_id', $campaignAssetId)
            ->assertJsonPath('candidate_sha256', $candidateHash)
            ->assertJsonPath('locale', 'en-IN')
            ->assertJsonPath('transcript', 'I can speak in English.')
            ->assertJsonPath('segments.0.confidence_bps', 9700);
        $this->assertArrayNotHasKey('candidate_object_key', $candidate->json());

        $foreign = $this->createOrganization('Foreign Transcription Tenant');
        [$foreignMembership, $foreignContext] = $this->createMembership(
            $foreign,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $foreignMembership,
            $foreignContext,
            ['media.metadata.manage'],
            'foreign-transcription-reconciler',
        );
        $this->selectMembership($foreignMembership);
        $this->freshenCurrentWebSession($actor);
        $this->getJson($candidateUri)
            ->assertNotFound()
            ->assertJsonMissing(['I can speak in English.', $candidateHash]);
        $this->withHeader('Idempotency-Key', (string) Str::ulid())
            ->withHeader('Origin', 'https://localhost')
            ->postJson($reconcileUri, $body)
            ->assertNotFound()
            ->assertJsonMissing(['I can speak in English.', $candidateHash]);
        $this->selectMembership($membership);
        $this->freshenCurrentWebSession($actor);

        $this->withHeader('Idempotency-Key', (string) Str::ulid())
            ->withHeader('Origin', 'https://localhost')
            ->postJson($reconcileUri, [
                ...$body,
                'expected_asset_version' => 99,
            ])
            ->assertConflict()
            ->assertJsonPath(
                'message_key',
                'problem.media_library.concurrent_change',
            );
        $this->withHeader('Idempotency-Key', (string) Str::ulid())
            ->withHeader('Origin', 'https://localhost')
            ->postJson($reconcileUri, [
                ...$body,
                'candidate_sha256' => str_repeat('f', 64),
            ])
            ->assertConflict()
            ->assertJsonPath(
                'message_key',
                'problem.media_library.concurrent_change',
            );
        $idempotencyKey = (string) Str::ulid();
        $created = $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->withHeader('Origin', 'https://localhost')
            ->postJson($reconcileUri, $body)
            ->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('result_type', 'media_transcription_reconciliation')
            ->assertJsonPath('replayed', false)
            ->assertJsonPath('outcome', 'confirmed');
        $reconciliationId = $created->json('reconciliation_id');
        $metadataRevisionId = $created->json('metadata_revision_id');
        self::assertIsString($reconciliationId);
        self::assertIsString($metadataRevisionId);

        $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->withHeader('Origin', 'https://localhost')
            ->postJson($reconcileUri, $body)
            ->assertOk()
            ->assertJsonPath('replayed', true)
            ->assertJsonPath('reconciliation_id', $reconciliationId)
            ->assertJsonPath('metadata_revision_id', $metadataRevisionId);
        $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->withHeader('Origin', 'https://localhost')
            ->postJson($reconcileUri, [
                ...$body,
                'operator_label' => 'Changed replay payload',
            ])
            ->assertConflict()
            ->assertJsonPath(
                'message_key',
                'problem.media_library.idempotency_conflict',
            );
        $this->app->make(MediaTranscriptionRetentionService::class)->run();

        $this->assertDatabaseHas('media_asset_metadata_revisions', [
            'id' => $metadataRevisionId,
            'organization_id' => $organization->getKey(),
            'media_asset_id' => $mediaAssetId,
            'transcript' => 'I can speak in English.',
            'authored_by_membership_id' => $membership->getKey(),
        ]);
        $this->assertDatabaseCount('media_transcription_reconciliations', 1);
        $this->assertDatabaseHas('media_transcription_candidate_deletions', [
            'organization_id' => $organization->getKey(),
            'campaign_asset_id' => $campaignAssetId,
            'state' => 'completed',
        ]);
        $this->assertFalse($this->app->make(
            MediaTranscriptionCandidateStore::class,
        )->exists(
            (string) $organization->getKey(),
            $campaignId,
            $campaignAssetId,
            $attemptId,
        ));
        $this->getJson($candidateUri)->assertNotFound();

        DB::table('media_transcription_campaigns')
            ->where('organization_id', $organization->getKey())
            ->where('id', $campaignId)
            ->update([
                'state' => 'failed',
                'version' => 2,
                'terminal_at' => CarbonImmutable::now(),
                'updated_at' => CarbonImmutable::now(),
            ]);
        CarbonImmutable::setTestNow('2027-08-11T08:00:00+00:00');
        $retained = $this->app->make(
            MediaTranscriptionRetentionService::class,
        )->run();
        $this->assertSame(0, $retained['purged_provenance']);
        $this->assertDatabaseHas('media_transcription_campaigns', [
            'organization_id' => $organization->getKey(),
            'id' => $campaignId,
            'state' => 'failed',
        ]);

    }

    public function test_unreconciled_candidate_expiry_fails_campaign_and_deletes_content(): void
    {
        CarbonImmutable::setTestNow('2026-08-10T08:00:00+00:00');
        $actor = $this->signInWeb('+919310000082');
        $organization = $this->createOrganization('Expired Transcription Candidate');
        [$membership, $context] = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $membership,
            $context,
            ['media.metadata.manage'],
            'expired-transcription-reconciler',
        );
        [$campaignId, $campaignAssetId, , $candidateHash, $attemptId] =
            $this->candidateFixture($organization, $membership);

        CarbonImmutable::setTestNow('2026-08-18T08:00:00+00:00');
        $result = $this->app->make(MediaTranscriptionRetentionService::class)->run();

        $this->assertSame(1, $result['expired']);
        $this->assertSame(1, $result['deleted']);
        $this->assertDatabaseHas('media_transcription_campaign_assets', [
            'organization_id' => $organization->getKey(),
            'id' => $campaignAssetId,
            'state' => 'expired',
        ]);
        $this->assertDatabaseHas('media_transcription_campaigns', [
            'organization_id' => $organization->getKey(),
            'id' => $campaignId,
            'state' => 'failed',
        ]);
        $this->assertDatabaseHas('media_transcription_events', [
            'organization_id' => $organization->getKey(),
            'campaign_id' => $campaignId,
            'event_type' => 'campaign.failed',
            'safe_failure_code' => 'candidate_expired_unreconciled',
        ]);
        $this->assertDatabaseCount('media_transcription_reconciliations', 0);
        $this->assertFalse($this->app->make(
            MediaTranscriptionCandidateStore::class,
        )->exists(
            (string) $organization->getKey(),
            $campaignId,
            $campaignAssetId,
            $attemptId,
        ));
        $this->assertSame(64, strlen($candidateHash));

        CarbonImmutable::setTestNow('2027-08-19T08:00:00+00:00');
        $purged = $this->app->make(
            MediaTranscriptionRetentionService::class,
        )->run();
        $this->assertSame(1, $purged['purged_provenance']);
        $this->assertDatabaseMissing('media_transcription_campaigns', [
            'organization_id' => $organization->getKey(),
            'id' => $campaignId,
        ]);
        $this->assertDatabaseMissing('media_transcription_campaign_assets', [
            'organization_id' => $organization->getKey(),
            'id' => $campaignAssetId,
        ]);
        $this->assertDatabaseMissing('media_transcription_provider_attempts', [
            'organization_id' => $organization->getKey(),
            'id' => $attemptId,
        ]);
    }

    public function test_corrected_discarded_and_needs_attention_outcomes_are_explicit_and_private(): void
    {
        CarbonImmutable::setTestNow('2026-08-10T08:00:00+00:00');
        $actor = $this->signInWeb('+919310000083');
        $organization = $this->createOrganization('Transcription Outcomes');
        [$membership, $context] = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $membership,
            $context,
            ['media.metadata.manage'],
            'transcription-outcome-reconciler',
        );
        $this->selectMembership($membership);
        $this->freshenCurrentWebSession($actor);

        $fixtures = [
            'corrected' => $this->candidateFixture($organization, $membership),
            'discarded' => $this->candidateFixture($organization, $membership),
            'needs_attention' => $this->candidateFixture($organization, $membership),
        ];
        foreach ($fixtures as $outcome => $fixture) {
            [$campaignId, $assetId, $mediaAssetId, $candidateHash] = $fixture;
            $body = [
                'expected_asset_version' => 2,
                'candidate_sha256' => $candidateHash,
                'outcome' => $outcome,
                'expected_head_revision_id' => null,
                'expected_metadata_version' => 0,
            ];
            if ($outcome === 'corrected') {
                $body['operator_label'] = 'Level 1 corrected transcript';
                $body['corrected_transcript'] = 'I can speak English clearly.';
            }

            $response = $this->withHeader(
                'Idempotency-Key',
                (string) Str::ulid(),
            )
                ->withHeader('Origin', 'https://localhost')
                ->postJson(
                    "/api/v1/operator/media/transcription-campaigns/{$campaignId}/assets/{$assetId}/reconciliations",
                    $body,
                )
                ->assertCreated()
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertJsonPath('outcome', $outcome);

            if ($outcome === 'corrected') {
                $metadataRevisionId = $response->json('metadata_revision_id');
                self::assertIsString($metadataRevisionId);
                $this->assertDatabaseHas('media_asset_metadata_revisions', [
                    'id' => $metadataRevisionId,
                    'organization_id' => $organization->getKey(),
                    'media_asset_id' => $mediaAssetId,
                    'transcript' => 'I can speak English clearly.',
                ]);
            } else {
                $response->assertJsonPath('metadata_revision_id', null);
                $this->assertDatabaseMissing('media_asset_metadata_revisions', [
                    'organization_id' => $organization->getKey(),
                    'media_asset_id' => $mediaAssetId,
                ]);
            }
        }

        $result = $this->app->make(
            MediaTranscriptionRetentionService::class,
        )->run();
        $this->assertSame(3, $result['deleted']);
        $this->assertDatabaseCount('media_transcription_reconciliations', 3);
        $this->assertSame(
            3,
            DB::table('media_transcription_candidate_deletions')
                ->where('organization_id', $organization->getKey())
                ->where('state', 'completed')
                ->count(),
        );
    }

    public function test_candidate_deletion_retries_to_content_free_dead_letter(): void
    {
        CarbonImmutable::setTestNow('2026-08-10T08:00:00+00:00');
        $actor = $this->signInWeb('+919310000084');
        $organization = $this->createOrganization('Transcription Dead Letter');
        [$membership] = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        [$campaignId, $campaignAssetId, , $candidateHash, $attemptId] =
            $this->candidateFixture($organization, $membership);
        $delegate = $this->app->make(MediaTranscriptionCandidateStore::class);
        $this->app->instance(
            MediaTranscriptionCandidateStore::class,
            new DeleteFailingMediaTranscriptionCandidateStore($delegate),
        );
        $retention = $this->app->make(
            MediaTranscriptionRetentionService::class,
        );
        $retention->schedule(
            (string) $organization->getKey(),
            $campaignId,
            $campaignAssetId,
            $attemptId,
            $candidateHash,
            CarbonImmutable::now(),
        );

        $result = [];
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $result = $retention->run();
            if ($attempt < 5) {
                CarbonImmutable::setTestNow(
                    CarbonImmutable::now()->addSeconds(61),
                );
            }
        }

        $this->assertSame(1, $result['dead_lettered']);
        $this->assertSame(1, $result['dead_letter_backlog']);
        $this->assertDatabaseHas('media_transcription_candidate_deletions', [
            'organization_id' => $organization->getKey(),
            'campaign_asset_id' => $campaignAssetId,
            'candidate_sha256' => $candidateHash,
            'state' => 'dead_letter',
            'attempt_count' => 5,
            'last_error_code' => 'candidate_deletion_failed',
        ]);
        $this->assertDatabaseHas('media_transcription_events', [
            'organization_id' => $organization->getKey(),
            'campaign_id' => $campaignId,
            'campaign_asset_id' => $campaignAssetId,
            'event_type' => 'candidate.deletion_dead_letter',
            'safe_failure_code' => 'candidate_deletion_failed',
        ]);
        $this->assertTrue($delegate->exists(
            (string) $organization->getKey(),
            $campaignId,
            $campaignAssetId,
            $attemptId,
        ));
    }

    /** @return array{string, string, string, string, string} */
    private function candidateFixture(
        Organization $organization,
        Membership $membership,
    ): array {
        $organizationId = (string) $organization->getKey();
        $membershipId = (string) $membership->getKey();
        $programId = (string) Str::ulid();
        $mediaAssetId = (string) Str::ulid();
        $scanId = (string) Str::ulid();
        $rightsId = (string) Str::ulid();
        $campaignId = (string) Str::ulid();
        $campaignAssetId = (string) Str::ulid();
        $fixtureKey = Str::lower((string) Str::ulid());
        $sourceHash = hash('sha256', 'synthetic-governed-audio');
        $now = CarbonImmutable::now();
        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organizationId,
            'key' => 'transcription-test-program-'.$fixtureKey,
            'internal_name' => 'Transcription test program',
            'created_by_membership_id' => $membershipId,
            'created_by_attribution' => 'test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        [$appointmentId, $appointmentEventId] = $this->insertRightsAppointment(
            $organizationId,
            $programId,
            (string) $membership->user_id,
            $membershipId,
        );
        DB::table('media_assets')->insert([
            'id' => $mediaAssetId,
            'organization_id' => $organizationId,
            'key' => 'transcription-test-audio-'.$fixtureKey,
            'asset_kind' => 'lesson_audio',
            'storage_object_key' => 'private/test-audio.mp3',
            'mime_type' => 'audio/mpeg',
            'byte_size' => 26,
            'asset_hash' => $sourceHash,
            'uploaded_by_membership_id' => $membershipId,
            'uploader_attribution' => 'test',
            'uploaded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('media_security_scans')->insert([
            'id' => $scanId,
            'organization_id' => $organizationId,
            'media_upload_id' => null,
            'media_asset_id' => $mediaAssetId,
            'asset_kind' => 'lesson_audio',
            'object_hash' => $sourceHash,
            'byte_size' => 26,
            'inspection_outcome' => 'passed',
            'malware_outcome' => 'clean',
            'detected_mime_type' => 'audio/mpeg',
            'duration_milliseconds' => 2000,
            'inspector_name' => 'test',
            'inspector_policy_version' => 'test-v1',
            'scanner_name' => 'test',
            'scanner_engine_version' => 'test',
            'scanner_policy_version' => 'test-v1',
            'technical_metadata_hash' => hash('sha256', 'technical'),
            'inspected_at' => $now,
            'scanned_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('media_asset_lifecycles')->insert([
            'organization_id' => $organizationId,
            'media_asset_id' => $mediaAssetId,
            'asset_kind' => 'lesson_audio',
            'state' => 'active',
            'version' => 1,
            'selected_scan_id' => $scanId,
            'predecessor_media_asset_id' => null,
            'managed_by_membership_id' => $membershipId,
            'retired_by_membership_id' => null,
            'retirement_reason_key' => null,
            'managed_at' => $now,
            'retired_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('media_rights_revisions')->insert([
            'id' => $rightsId,
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'media_asset_id' => $mediaAssetId,
            'parent_revision_id' => null,
            'revision_number' => 1,
            'evidence_status' => 'approved',
            'evidence_hash' => hash('sha256', 'rights'),
            'selected_asset_hash' => $sourceHash,
            'rights_holder_reference' => 'test-owner',
            'provenance_reference' => 'test-provenance',
            'rights_basis_key' => 'owner_authorization',
            'license_or_contract_reference' => 'test-contract',
            'permitted_release_channels' => json_encode(['web', 'android'], JSON_THROW_ON_ERROR),
            'permitted_territories' => json_encode(['IN'], JSON_THROW_ON_ERROR),
            'learner_delivery_allowed' => true,
            'sharing_allowed' => false,
            'derivative_use_allowed' => true,
            'effective_at' => $now->subDay(),
            'expires_at' => null,
            'retention_policy_key' => 'preserve_while_referenced',
            'deletion_constraint_key' => 'rights_review_required',
            'confirmed_by_membership_id' => $membershipId,
            'rights_appointment_id' => $appointmentId,
            'rights_appointment_event_id' => $appointmentEventId,
            'confirmed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('media_transcription_campaigns')->insert([
            'id' => $campaignId,
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'version' => 1,
            'state' => 'provider_complete',
            'approval_reference' => 'APP-0052',
            'approved_request_sha256' => hash(
                'sha256',
                'request-'.$fixtureKey,
            ),
            'manifest_sha256' => str_repeat('b', 64),
            'provider_key' => 'azure-speech',
            'provider_model' => 'test-provider-managed',
            'provider_region' => 'centralindia',
            'provider_api_version' => '2025-10-15',
            'locale' => 'en-IN',
            'configuration_sha256' => str_repeat('c', 64),
            'vendor_evidence_sha256' => str_repeat('d', 64),
            'price_evidence_sha256' => str_repeat('e', 64),
            'credential_version' => 'test-v1',
            'logical_asset_cap' => 4,
            'http_attempt_cap' => 8,
            'uploaded_byte_cap' => 37_520_380,
            'billable_millisecond_cap' => 2_345_026,
            'estimated_cost_microunit_cap' => 100_000_000,
            'reserved_http_attempts' => 1,
            'reserved_uploaded_bytes' => 26,
            'reserved_billable_milliseconds' => 2000,
            'reserved_estimated_cost_microunits' => 1000,
            'initiated_by_membership_id' => $membershipId,
            'started_at' => $now,
            'terminal_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $attemptId = (string) Str::ulid();
        $reservationEventId = (string) Str::ulid();
        DB::table('media_transcription_campaign_assets')->insert([
            'id' => $campaignAssetId,
            'organization_id' => $organizationId,
            'campaign_id' => $campaignId,
            'program_id' => $programId,
            'sequence' => 1,
            'part_key' => 'introduction',
            'media_asset_id' => $mediaAssetId,
            'selected_scan_id' => $scanId,
            'media_rights_revision_id' => $rightsId,
            'rights_evidence_status' => 'approved',
            'rights_evidence_hash' => hash('sha256', 'rights'),
            'source_sha256' => $sourceHash,
            'source_bytes' => 26,
            'source_duration_milliseconds' => 2000,
            'locale' => 'en-IN',
            'state' => 'processing',
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
        $candidate = new MediaTranscriptionCandidate(
            'I can speak in English.',
            'en-IN',
            [new MediaTranscriptionSegment(1, 'I can speak in English.', 0, 1400, 9700)],
        );
        $candidateHash = $this->app->make(
            MediaTranscriptionCandidateStore::class,
        )->put(
            $organizationId,
            $campaignId,
            $campaignAssetId,
            $attemptId,
            $candidate,
        );
        DB::table('media_transcription_events')->insert([
            'id' => $reservationEventId,
            'organization_id' => $organizationId,
            'campaign_id' => $campaignId,
            'campaign_asset_id' => $campaignAssetId,
            'provider_attempt_id' => $attemptId,
            'attempt_number' => 1,
            'reserved_uploaded_bytes' => 26,
            'reserved_billable_milliseconds' => 2000,
            'reserved_estimated_cost_microunits' => 1000,
            'event_type' => 'provider_attempt.reserved',
            'campaign_version' => 1,
            'asset_version' => 1,
            'actor_membership_id' => null,
            'safe_failure_code' => null,
            'occurred_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('media_transcription_provider_attempts')->insert([
            'id' => $attemptId,
            'organization_id' => $organizationId,
            'campaign_id' => $campaignId,
            'campaign_asset_id' => $campaignAssetId,
            'reservation_event_id' => $reservationEventId,
            'attempt_number' => 1,
            'provider_key' => 'azure-speech',
            'provider_model' => 'test-provider-managed',
            'provider_region' => 'centralindia',
            'provider_api_version' => '2025-10-15',
            'configuration_sha256' => str_repeat('c', 64),
            'input_sha256' => $sourceHash,
            'input_bytes' => 26,
            'input_duration_milliseconds' => 2000,
            'status' => 'succeeded',
            'safe_failure_code' => null,
            'retryable' => false,
            'latency_milliseconds' => 10,
            'usage_milliseconds' => 2000,
            'estimated_cost_microunits' => 1000,
            'candidate_sha256' => $candidateHash,
            'response_metadata_sha256' => hash('sha256', 'response'),
            'started_at' => $now,
            'completed_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('media_transcription_campaign_assets')
            ->where('organization_id', $organizationId)
            ->where('id', $campaignAssetId)
            ->update([
                'state' => 'candidate_ready',
                'version' => 2,
                'candidate_provider_attempt_id' => $attemptId,
                'candidate_sha256' => $candidateHash,
                'response_metadata_sha256' => hash('sha256', 'response'),
                'candidate_stored_at' => $now,
                'candidate_expires_at' => $now->addDays(7),
                'updated_at' => $now,
            ]);

        return [
            $campaignId,
            $campaignAssetId,
            $mediaAssetId,
            $candidateHash,
            $attemptId,
        ];
    }

    /** @return array{string, string} */
    private function insertRightsAppointment(
        string $organizationId,
        string $programId,
        string $userId,
        string $membershipId,
    ): array {
        $appointmentId = (string) Str::ulid();
        $appointedEventId = (string) Str::ulid();
        $acceptedEventId = (string) Str::ulid();
        $now = CarbonImmutable::now();
        DB::table('authority_appointments')->insert([
            'id' => $appointmentId,
            'organization_id' => $organizationId,
            'appointee_user_id' => $userId,
            'appointee_membership_id' => $membershipId,
            'authority_kind' => 'rights_confirmer',
            'scope_type' => 'program',
            'program_id' => $programId,
            'valid_from' => $now->subDay(),
            'valid_until' => $now->addMonth(),
            'qualification_basis' => 'Synthetic reconciliation rights fixture',
            'approval_evidence_key' => 'APP-0052',
            'appointed_by_membership_id' => $membershipId,
            'appointed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('authority_appointment_events')->insert([
            [
                'id' => $appointedEventId,
                'organization_id' => $organizationId,
                'authority_appointment_id' => $appointmentId,
                'event_type' => 'appointed',
                'actor_membership_id' => $membershipId,
                'reason_key' => null,
                'invalidates_prior_approvals' => false,
                'effective_at' => $now->subMinute(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $acceptedEventId,
                'organization_id' => $organizationId,
                'authority_appointment_id' => $appointmentId,
                'event_type' => 'accepted',
                'actor_membership_id' => $membershipId,
                'reason_key' => null,
                'invalidates_prior_approvals' => false,
                'effective_at' => $now->subMinute(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return [$appointmentId, $acceptedEventId];
    }

    private function signInWeb(string $mobileNumber): User
    {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        Auth::forgetGuards();
        $issued = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', ['mobile_number' => $mobileNumber])
            ->assertAccepted();
        $challengeId = $issued->json('challenge_id');
        self::assertIsString($challengeId);
        $verified = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => $mobileNumber,
                'code' => self::OTP,
            ])->assertOk();
        $cookie = $verified->getCookie((string) config('session.cookie'));
        self::assertNotNull($cookie);
        $this->withCookie($cookie->getName(), (string) $cookie->getValue());
        $userId = $verified->json('user.id');
        self::assertIsString($userId);

        return User::query()->whereKey($userId)->sole();
    }

    private function selectMembership(Membership $membership): void
    {
        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => $membership->getKey(),
            ])->assertOk();
    }

    private function freshenCurrentWebSession(User $user): void
    {
        $session = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->latest('started_at')
            ->firstOrFail();
        $this->markTestAuthenticationSessionPrivileged(
            (string) $session->getKey(),
            $user,
            now(),
        );
    }
}

final readonly class MediaTranscriptionOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class MediaTranscriptionNullOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    public function send(string $mobileNumber, string $code): void {}
}

final readonly class DeleteFailingMediaTranscriptionCandidateStore implements MediaTranscriptionCandidateStore
{
    public function __construct(
        private MediaTranscriptionCandidateStore $delegate,
    ) {}

    public function put(
        string $organizationId,
        string $campaignId,
        string $campaignAssetId,
        string $providerAttemptId,
        MediaTranscriptionCandidate $candidate,
    ): string {
        return $this->delegate->put(
            $organizationId,
            $campaignId,
            $campaignAssetId,
            $providerAttemptId,
            $candidate,
        );
    }

    public function read(
        string $organizationId,
        string $campaignId,
        string $campaignAssetId,
        string $providerAttemptId,
        string $expectedSha256,
    ): MediaTranscriptionCandidate {
        return $this->delegate->read(
            $organizationId,
            $campaignId,
            $campaignAssetId,
            $providerAttemptId,
            $expectedSha256,
        );
    }

    public function digest(
        string $organizationId,
        string $campaignId,
        string $campaignAssetId,
        string $providerAttemptId,
    ): ?string {
        return $this->delegate->digest(
            $organizationId,
            $campaignId,
            $campaignAssetId,
            $providerAttemptId,
        );
    }

    public function delete(
        string $organizationId,
        string $campaignId,
        string $campaignAssetId,
        string $providerAttemptId,
    ): void {
        throw new \RuntimeException('Synthetic candidate deletion failure.');
    }

    public function exists(
        string $organizationId,
        string $campaignId,
        string $campaignAssetId,
        string $providerAttemptId,
    ): bool {
        return $this->delegate->exists(
            $organizationId,
            $campaignId,
            $campaignAssetId,
            $providerAttemptId,
        );
    }
}

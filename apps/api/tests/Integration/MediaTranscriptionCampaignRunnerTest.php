<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Curriculum\Commissioning\MediaTranscription\CurriculumMediaSourceReader;
use App\Curriculum\Commissioning\MediaTranscription\CurriculumMediaTranscriptionProvider;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionCampaignRunner;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionCandidate;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionCandidateStore;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionProviderException;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionRejected;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionRequest;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionResult;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionRetentionService;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionSegment;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\FoundationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MediaTranscriptionCampaignRunnerTest extends TestCase
{
    use RefreshDatabase;

    private const string ORGANIZATION_ID = '01KYS97G7MX374P303XJHDXT72';

    private const string PROGRAM_ID = '01KZNK3BWD8A8NJ5A5JW0ZVZMT';

    private const string USER_ID = '01KYS97G7MX374P303XJHDXT73';

    private const string MEMBERSHIP_ID = '01KYS97G7MX374P303XJHDXT74';

    /** @var list<string> */
    private array $temporaryFiles = [];

    /** @var list<string> */
    private array $temporaryDirectories = [];

    private string $originalEnvironment;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-08-10T08:30:00+00:00');
        $this->originalEnvironment = $this->app->environment();
        $this->app['env'] = 'lpl-media-transcription';
        DB::table('organizations')->insert([
            'id' => self::ORGANIZATION_ID,
            'system_key' => Organization::DIRECT_SYSTEM_KEY,
            'name' => 'Global English Direct',
            'kind' => Organization::KIND_PLATFORM_DIRECT,
            'status' => Organization::STATUS_ACTIVE,
            'timezone' => 'Asia/Kolkata',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seed(FoundationSeeder::class);
        $this->insertAuthorityAndAssets();
        Storage::fake('transcription-runner-candidates');
        config()->set(
            'media_transcription.candidate.disk',
            'transcription-runner-candidates',
        );
        $this->app->instance(
            CurriculumMediaSourceReader::class,
            new SyntheticCurriculumMediaSourceReader,
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        foreach (array_reverse($this->temporaryDirectories) as $path) {
            if (is_dir($path)) {
                rmdir($path);
            }
        }
        $this->app['env'] = $this->originalEnvironment;
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_exact_campaign_retries_once_reserves_caps_and_resumes_without_resending_successes(): void
    {
        $campaignId = (string) Str::ulid();
        $this->configureAuthorizationEvidence($campaignId);
        $provider = new DeterministicCurriculumMediaTranscriptionProvider(
            failFirstAttempt: true,
        );
        $factory = static fn (array $authorization, Factory $http): CurriculumMediaTranscriptionProvider => $provider;
        $runner = $this->app->make(MediaTranscriptionCampaignRunner::class);

        $report = $runner->run($campaignId, self::MEMBERSHIP_ID, $factory);

        $this->assertSame('provider_complete', $report['state']);
        $this->assertSame(4, $report['logical_assets']);
        $this->assertSame(5, $report['reserved_http_attempts']);
        $this->assertSame(23_728_598, $report['reserved_uploaded_bytes']);
        $this->assertSame(1_483_039, $report['reserved_billable_milliseconds']);
        $this->assertSame(5, $provider->calls);
        $this->assertSame(8, $report['http_attempt_cap']);
        $this->assertSame(37_520_380, $report['uploaded_byte_cap']);
        $this->assertSame(5, $report['provider_attempts']);
        $this->assertSame(4, $report['provider_successes']);
        $this->assertSame(1, $report['provider_failures']);
        $this->assertDatabaseCount('media_transcription_campaigns', 1);
        $this->assertDatabaseCount('media_transcription_campaign_assets', 4);
        $this->assertDatabaseCount('media_transcription_provider_attempts', 5);
        $this->assertSame(
            ['failed', 'succeeded', 'succeeded', 'succeeded', 'succeeded'],
            DB::table('media_transcription_provider_attempts')
                ->orderBy('created_at')->orderBy('id')->pluck('status')->all(),
        );
        $this->assertSame(
            ['candidate_ready'],
            DB::table('media_transcription_campaign_assets')
                ->distinct()->pluck('state')->all(),
        );
        $this->assertSame(5, DB::table('media_transcription_provider_attempts as attempts')
            ->join('media_transcription_events as reservations', function ($join): void {
                $join->on('reservations.organization_id', '=', 'attempts.organization_id')
                    ->on('reservations.campaign_id', '=', 'attempts.campaign_id')
                    ->on('reservations.campaign_asset_id', '=', 'attempts.campaign_asset_id')
                    ->on('reservations.id', '=', 'attempts.reservation_event_id')
                    ->on('reservations.provider_attempt_id', '=', 'attempts.id');
            })
            ->where('reservations.event_type', 'provider_attempt.reserved')
            ->count());
        foreach (['media_transcription_provider_attempts', 'media_transcription_events'] as $table) {
            $this->assertFalse(
                DB::table($table)->whereRaw(
                    "CAST(COALESCE(safe_failure_code, '') AS TEXT) LIKE ?",
                    ['%I can speak%'],
                )->exists(),
            );
        }
        if (DB::connection()->getDriverName() === 'pgsql') {
            $attemptId = DB::table('media_transcription_provider_attempts')->value('id');
            $eventId = DB::table('media_transcription_events')->value('id');
            self::assertIsString($attemptId);
            self::assertIsString($eventId);
            $this->assertPostgresRejects(static fn () => DB::table(
                'media_transcription_provider_attempts',
            )->where('id', $attemptId)->update(['latency_milliseconds' => 999]));
            $this->assertPostgresRejects(static fn () => DB::table(
                'media_transcription_events',
            )->where('id', $eventId)->delete());
            $this->assertPostgresRejects(static fn () => DB::table(
                'media_transcription_campaigns',
            )->where('id', $campaignId)->update([
                'version' => 8,
                'manifest_sha256' => str_repeat('f', 64),
                'updated_at' => now(),
            ]));
            $succeeded = DB::table('media_transcription_provider_attempts')
                ->where('campaign_id', $campaignId)
                ->where('status', 'succeeded')
                ->first([
                    'id', 'organization_id', 'campaign_id',
                    'campaign_asset_id', 'candidate_sha256',
                ]);
            self::assertNotNull($succeeded);
            $this->assertPostgresRejects(static fn () => DB::table(
                'media_transcription_candidate_deletions',
            )->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => (string) $succeeded->organization_id,
                'campaign_id' => (string) $succeeded->campaign_id,
                'campaign_asset_id' => (string) $succeeded->campaign_asset_id,
                'provider_attempt_id' => (string) $succeeded->id,
                'candidate_sha256' => str_repeat('f', 64),
                'state' => 'pending',
                'attempt_count' => 0,
                'next_attempt_at' => now(),
                'hard_deadline_at' => now()->addHour(),
                'last_error_code' => null,
                'completed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $otherMediaAssetId = DB::table(
                'media_transcription_campaign_assets',
            )
                ->where('campaign_id', $campaignId)
                ->where('id', '!=', (string) $succeeded->campaign_asset_id)
                ->value('media_asset_id');
            self::assertIsString($otherMediaAssetId);
            $this->assertPostgresRejects(static fn () => DB::table(
                'media_transcription_reconciliations',
            )->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => (string) $succeeded->organization_id,
                'campaign_id' => (string) $succeeded->campaign_id,
                'campaign_asset_id' => (string) $succeeded->campaign_asset_id,
                'media_asset_id' => $otherMediaAssetId,
                'candidate_sha256' => (string) $succeeded->candidate_sha256,
                'outcome' => 'discarded',
                'metadata_revision_id' => null,
                'metadata_content_hash' => null,
                'reconciled_by_membership_id' => self::MEMBERSHIP_ID,
                'reconciled_at' => now(),
                'created_at' => now(),
            ]));
        }

        $replayed = $runner->run(
            $campaignId,
            self::MEMBERSHIP_ID,
            $factory,
        );
        $this->assertSame($report, $replayed);
        $this->assertSame(5, $provider->calls);
        $this->assertDatabaseCount('media_transcription_provider_attempts', 5);

        config()->set('media_transcription.live_requests_enabled', false);
        try {
            $runner->run($campaignId, self::MEMBERSHIP_ID, $factory);
            self::fail('Expected the live kill switch to stop resume.');
        } catch (MediaTranscriptionRejected $exception) {
            $this->assertSame('provider_not_authorized', $exception->safeCode);
        }
        $this->assertSame(5, $provider->calls);
    }

    public function test_campaign_rechecks_rights_authority_before_creating_or_dispatching(): void
    {
        $campaignId = (string) Str::ulid();
        $this->configureAuthorizationEvidence($campaignId);
        $appointmentId = DB::table('media_rights_revisions')
            ->where('organization_id', self::ORGANIZATION_ID)
            ->value('rights_appointment_id');
        self::assertIsString($appointmentId);
        DB::table('authority_appointment_events')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => self::ORGANIZATION_ID,
            'authority_appointment_id' => $appointmentId,
            'event_type' => 'revoked_for_cause',
            'actor_membership_id' => self::MEMBERSHIP_ID,
            'reason_key' => 'commissioning_authority_invalidated',
            'invalidates_prior_approvals' => true,
            'effective_at' => CarbonImmutable::now(),
            'created_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);
        $provider = new DeterministicCurriculumMediaTranscriptionProvider(false);
        $factory = static fn (array $authorization, Factory $http): CurriculumMediaTranscriptionProvider => $provider;

        try {
            $this->app->make(MediaTranscriptionCampaignRunner::class)->run(
                $campaignId,
                self::MEMBERSHIP_ID,
                $factory,
            );
            self::fail('Expected invalidated rights authority to block dispatch.');
        } catch (MediaTranscriptionRejected $exception) {
            $this->assertSame('source_authority_invalid', $exception->safeCode);
        }

        $this->assertSame(0, $provider->calls);
        $this->assertDatabaseCount('media_transcription_campaigns', 0);
        $this->assertDatabaseCount('media_transcription_provider_attempts', 0);
    }

    public function test_price_evidence_must_cover_per_attempt_billing_rounding(): void
    {
        $campaignId = (string) Str::ulid();
        $this->configureAuthorizationEvidence($campaignId, 42_600);
        $provider = new DeterministicCurriculumMediaTranscriptionProvider(false);
        $factory = static fn (array $authorization, Factory $http): CurriculumMediaTranscriptionProvider => $provider;

        try {
            $this->app->make(MediaTranscriptionCampaignRunner::class)->run(
                $campaignId,
                self::MEMBERSHIP_ID,
                $factory,
            );
            self::fail('Expected an underfunded per-attempt price snapshot to fail closed.');
        } catch (MediaTranscriptionRejected $exception) {
            $this->assertSame('provider_not_authorized', $exception->safeCode);
        }

        $this->assertSame(0, $provider->calls);
        $this->assertDatabaseCount('media_transcription_campaigns', 0);
    }

    public function test_interrupted_candidate_commit_is_cleaned_before_retry(): void
    {
        $campaignId = (string) Str::ulid();
        $this->configureAuthorizationEvidence($campaignId);
        $delegate = $this->app->make(MediaTranscriptionCandidateStore::class);
        $this->app->instance(
            MediaTranscriptionCandidateStore::class,
            new CrashAfterCandidateWriteStore($delegate),
        );
        $firstProvider = new DeterministicCurriculumMediaTranscriptionProvider(false);
        $firstFactory = static fn (
            array $authorization,
            Factory $http,
        ): CurriculumMediaTranscriptionProvider => $firstProvider;

        try {
            $this->app->make(MediaTranscriptionCampaignRunner::class)->run(
                $campaignId,
                self::MEMBERSHIP_ID,
                $firstFactory,
            );
            self::fail('Expected the synthetic post-write interruption.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('synthetic_candidate_commit_interruption', $exception->getMessage());
        }

        $reservation = DB::table('media_transcription_events')
            ->where('campaign_id', $campaignId)
            ->where('event_type', 'provider_attempt.reserved')
            ->first(['campaign_asset_id', 'provider_attempt_id']);
        self::assertNotNull($reservation);
        $assetId = (string) $reservation->campaign_asset_id;
        $interruptedAttemptId = (string) $reservation->provider_attempt_id;
        $this->assertTrue($delegate->exists(
            self::ORGANIZATION_ID,
            $campaignId,
            $assetId,
            $interruptedAttemptId,
        ));
        $this->assertDatabaseCount('media_transcription_provider_attempts', 0);

        $this->app->instance(MediaTranscriptionCandidateStore::class, $delegate);
        $retryProvider = new DeterministicCurriculumMediaTranscriptionProvider(false);
        $retryFactory = static fn (
            array $authorization,
            Factory $http,
        ): CurriculumMediaTranscriptionProvider => $retryProvider;
        $report = $this->app->make(MediaTranscriptionCampaignRunner::class)->run(
            $campaignId,
            self::MEMBERSHIP_ID,
            $retryFactory,
        );

        $this->assertSame('provider_complete', $report['state']);
        $this->assertSame(4, $retryProvider->calls);
        $this->assertFalse($delegate->exists(
            self::ORGANIZATION_ID,
            $campaignId,
            $assetId,
            $interruptedAttemptId,
        ));
        $this->assertDatabaseHas('media_transcription_provider_attempts', [
            'organization_id' => self::ORGANIZATION_ID,
            'campaign_id' => $campaignId,
            'campaign_asset_id' => $assetId,
            'id' => $interruptedAttemptId,
            'status' => 'failed_uncertain',
            'safe_failure_code' => 'provider_attempt_interrupted',
        ]);
        $this->assertDatabaseHas('media_transcription_candidate_deletions', [
            'organization_id' => self::ORGANIZATION_ID,
            'campaign_id' => $campaignId,
            'campaign_asset_id' => $assetId,
            'provider_attempt_id' => $interruptedAttemptId,
            'state' => 'completed',
        ]);
        $this->assertDatabaseCount('media_transcription_provider_attempts', 5);
    }

    public function test_connection_timeout_is_reserved_as_uncertain_and_retried_only_once(): void
    {
        $campaignId = (string) Str::ulid();
        $this->configureAuthorizationEvidence($campaignId);
        $provider = new DeterministicCurriculumMediaTranscriptionProvider(
            failFirstAttempt: true,
            failureCode: 'provider_connection_failure',
        );
        $factory = static fn (
            array $authorization,
            Factory $http,
        ): CurriculumMediaTranscriptionProvider => $provider;

        $report = $this->app->make(MediaTranscriptionCampaignRunner::class)
            ->run($campaignId, self::MEMBERSHIP_ID, $factory);

        $this->assertSame('provider_complete', $report['state']);
        $this->assertSame(5, $report['reserved_http_attempts']);
        $this->assertDatabaseHas('media_transcription_provider_attempts', [
            'organization_id' => self::ORGANIZATION_ID,
            'campaign_id' => $campaignId,
            'attempt_number' => 1,
            'status' => 'failed_uncertain',
            'safe_failure_code' => 'provider_connection_failure',
            'retryable' => true,
        ]);
    }

    public function test_insecure_permit_fails_before_campaign_or_provider_work(): void
    {
        $campaignId = (string) Str::ulid();
        $this->configureAuthorizationEvidence($campaignId);
        $permit = config('media_transcription.evidence.permit_file');
        self::assertIsString($permit);
        chmod($permit, 0644);
        $provider = new DeterministicCurriculumMediaTranscriptionProvider(false);
        $factory = static fn (
            array $authorization,
            Factory $http,
        ): CurriculumMediaTranscriptionProvider => $provider;

        try {
            $this->app->make(MediaTranscriptionCampaignRunner::class)->run(
                $campaignId,
                self::MEMBERSHIP_ID,
                $factory,
            );
            self::fail('Expected the insecure permit to fail closed.');
        } catch (MediaTranscriptionRejected $exception) {
            $this->assertSame('provider_not_authorized', $exception->safeCode);
            $this->assertStringNotContainsString(
                'synthetic-secret',
                $exception->getMessage(),
            );
        }

        $this->assertSame(0, $provider->calls);
        $this->assertDatabaseCount('media_transcription_campaigns', 0);
    }

    public function test_changed_vendor_evidence_stops_before_the_next_dispatch(): void
    {
        $campaignId = (string) Str::ulid();
        $this->configureAuthorizationEvidence($campaignId);
        $vendorPath = config('media_transcription.evidence.vendor_file');
        self::assertIsString($vendorPath);
        $provider = new EvidenceMutatingCurriculumMediaTranscriptionProvider(
            $vendorPath,
        );
        $factory = static fn (
            array $authorization,
            Factory $http,
        ): CurriculumMediaTranscriptionProvider => $provider;

        try {
            $this->app->make(MediaTranscriptionCampaignRunner::class)->run(
                $campaignId,
                self::MEMBERSHIP_ID,
                $factory,
            );
            self::fail('Expected changed vendor evidence to stop the campaign.');
        } catch (MediaTranscriptionRejected $exception) {
            $this->assertSame('provider_not_authorized', $exception->safeCode);
        }

        $this->assertSame(1, $provider->calls);
        $this->assertDatabaseHas('media_transcription_campaigns', [
            'organization_id' => self::ORGANIZATION_ID,
            'id' => $campaignId,
            'state' => 'running',
            'reserved_http_attempts' => 1,
        ]);
        $this->assertDatabaseCount('media_transcription_provider_attempts', 1);
    }

    public function test_source_drift_is_a_terminal_content_free_failure_without_dispatch(): void
    {
        $campaignId = (string) Str::ulid();
        $this->configureAuthorizationEvidence($campaignId);
        $this->app->instance(
            CurriculumMediaSourceReader::class,
            new RejectingCurriculumMediaSourceReader,
        );
        $provider = new DeterministicCurriculumMediaTranscriptionProvider(false);
        $factory = static fn (
            array $authorization,
            Factory $http,
        ): CurriculumMediaTranscriptionProvider => $provider;

        $report = $this->app->make(MediaTranscriptionCampaignRunner::class)
            ->run($campaignId, self::MEMBERSHIP_ID, $factory);

        $this->assertSame('failed', $report['state']);
        $this->assertSame(0, $report['reserved_http_attempts']);
        $this->assertSame(0, $provider->calls);
        $this->assertDatabaseHas('media_transcription_events', [
            'organization_id' => self::ORGANIZATION_ID,
            'campaign_id' => $campaignId,
            'event_type' => 'campaign.failed',
            'safe_failure_code' => 'source_object_invalid',
        ]);
    }

    public function test_overlapping_runner_is_rejected_without_duplicate_reservation_or_dispatch(): void
    {
        $campaignId = (string) Str::ulid();
        $this->configureAuthorizationEvidence($campaignId);
        $runner = $this->app->make(MediaTranscriptionCampaignRunner::class);
        $provider = new ReentrantCurriculumMediaTranscriptionProvider(
            $runner,
            $campaignId,
            self::MEMBERSHIP_ID,
        );
        $factory = static fn (
            array $authorization,
            Factory $http,
        ): CurriculumMediaTranscriptionProvider => $provider;
        $provider->factory = $factory;

        $report = $runner->run($campaignId, self::MEMBERSHIP_ID, $factory);

        $this->assertSame('provider_complete', $report['state']);
        $this->assertSame('campaign_state_invalid', $provider->overlapFailure);
        $this->assertSame(4, $provider->calls);
        $this->assertSame(4, $report['reserved_http_attempts']);
        $this->assertDatabaseCount('media_transcription_provider_attempts', 4);
    }

    public function test_exact_campaign_completes_only_after_four_reconciliations_and_candidate_cleanup(): void
    {
        $campaignId = (string) Str::ulid();
        $this->configureAuthorizationEvidence($campaignId);
        $provider = new DeterministicCurriculumMediaTranscriptionProvider(false);
        $factory = static fn (
            array $authorization,
            Factory $http,
        ): CurriculumMediaTranscriptionProvider => $provider;
        $report = $this->app->make(MediaTranscriptionCampaignRunner::class)
            ->run($campaignId, self::MEMBERSHIP_ID, $factory);
        $this->assertSame('provider_complete', $report['state']);

        $retention = $this->app->make(
            MediaTranscriptionRetentionService::class,
        );
        $before = $retention->run();
        $this->assertSame(0, $before['deleted']);
        $this->assertDatabaseHas('media_transcription_campaigns', [
            'organization_id' => self::ORGANIZATION_ID,
            'id' => $campaignId,
            'state' => 'provider_complete',
        ]);

        $store = $this->app->make(MediaTranscriptionCandidateStore::class);
        $assets = DB::table('media_transcription_campaign_assets')
            ->where('organization_id', self::ORGANIZATION_ID)
            ->where('campaign_id', $campaignId)
            ->orderBy('sequence')
            ->get();
        $this->assertCount(4, $assets);
        foreach ($assets as $asset) {
            self::assertIsString($asset->candidate_provider_attempt_id);
            self::assertIsString($asset->candidate_sha256);
            $now = CarbonImmutable::now();
            $store->delete(
                self::ORGANIZATION_ID,
                $campaignId,
                (string) $asset->id,
                $asset->candidate_provider_attempt_id,
            );
            DB::transaction(static function () use (
                $asset,
                $campaignId,
                $now,
            ): void {
                DB::table('media_transcription_campaign_assets')
                    ->where('organization_id', self::ORGANIZATION_ID)
                    ->where('campaign_id', $campaignId)
                    ->where('id', (string) $asset->id)
                    ->where('version', (int) $asset->version)
                    ->update([
                        'state' => 'discarded',
                        'version' => (int) $asset->version + 1,
                        'reconciled_at' => $now,
                        'updated_at' => $now,
                    ]);
                DB::table('media_transcription_reconciliations')->insert([
                    'id' => (string) Str::ulid(),
                    'organization_id' => self::ORGANIZATION_ID,
                    'campaign_id' => $campaignId,
                    'campaign_asset_id' => (string) $asset->id,
                    'media_asset_id' => (string) $asset->media_asset_id,
                    'candidate_sha256' => $asset->candidate_sha256,
                    'outcome' => 'discarded',
                    'metadata_revision_id' => null,
                    'metadata_content_hash' => null,
                    'reconciled_by_membership_id' => self::MEMBERSHIP_ID,
                    'reconciled_at' => $now,
                    'created_at' => $now,
                ]);
                DB::table('media_transcription_candidate_deletions')->insert([
                    'id' => (string) Str::ulid(),
                    'organization_id' => self::ORGANIZATION_ID,
                    'campaign_id' => $campaignId,
                    'campaign_asset_id' => (string) $asset->id,
                    'provider_attempt_id' => $asset->candidate_provider_attempt_id,
                    'candidate_sha256' => $asset->candidate_sha256,
                    'state' => 'completed',
                    'attempt_count' => 1,
                    'next_attempt_at' => $now,
                    'hard_deadline_at' => $now->addHour(),
                    'last_error_code' => null,
                    'completed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }, 3);
        }

        $after = $retention->run();
        $this->assertSame(0, $after['dead_letter_backlog']);
        $this->assertDatabaseHas('media_transcription_campaigns', [
            'organization_id' => self::ORGANIZATION_ID,
            'id' => $campaignId,
            'state' => 'completed',
        ]);
        $this->assertDatabaseHas('media_transcription_events', [
            'organization_id' => self::ORGANIZATION_ID,
            'campaign_id' => $campaignId,
            'event_type' => 'campaign.completed',
        ]);
        $this->assertSame(4, DB::table('media_transcription_reconciliations')
            ->where('campaign_id', $campaignId)->count());
        $this->assertSame(4, DB::table('media_transcription_candidate_deletions')
            ->where('campaign_id', $campaignId)
            ->where('state', 'completed')->count());
    }

    /** @param callable(): mixed $operation */
    private function assertPostgresRejects(callable $operation): void
    {
        try {
            DB::transaction(static fn (): mixed => $operation());
            self::fail('Expected PostgreSQL protection to reject the mutation.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    private function insertAuthorityAndAssets(): void
    {
        $now = CarbonImmutable::now();
        DB::table('users')->insert([
            'id' => self::USER_ID,
            'display_name' => null,
            'status' => User::STATUS_ACTIVE,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('memberships')->insert([
            'id' => self::MEMBERSHIP_ID,
            'organization_id' => self::ORGANIZATION_ID,
            'user_id' => self::USER_ID,
            'membership_type' => Membership::TYPE_ORGANIZATION_ADMIN,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => $now,
            'ended_at' => null,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $roleId = DB::table('roles')
            ->where('organization_id', self::ORGANIZATION_ID)
            ->where('key', Role::DIRECT_ORGANIZATION_ADMIN_KEY)
            ->value('id');
        self::assertIsString($roleId);
        DB::table('membership_roles')->insert([
            'organization_id' => self::ORGANIZATION_ID,
            'membership_id' => self::MEMBERSHIP_ID,
            'role_id' => $roleId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('programs')->insert([
            'id' => self::PROGRAM_ID,
            'organization_id' => self::ORGANIZATION_ID,
            'key' => 'lpl01-level1-pilot',
            'internal_name' => 'LPL-01 Level 1 Pilot',
            'created_by_membership_id' => self::MEMBERSHIP_ID,
            'created_by_attribution' => 'test',
            'descriptor_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        [$appointmentId, $appointmentEventId] = $this->insertRightsAppointment(
            self::ORGANIZATION_ID,
            self::PROGRAM_ID,
            self::USER_ID,
            self::MEMBERSHIP_ID,
        );
        $assets = config('media_transcription.manifest');
        self::assertIsArray($assets);
        foreach ($assets as $index => $entry) {
            self::assertIsArray($entry);
            $assetId = (string) $entry['media_asset_id'];
            $hash = (string) $entry['source_sha256'];
            $bytes = (int) $entry['source_bytes'];
            $duration = (int) $entry['source_duration_milliseconds'];
            $rightsId = (string) $entry['media_rights_revision_id'];
            $scanId = (string) Str::ulid();
            DB::table('media_assets')->insert([
                'id' => $assetId,
                'organization_id' => self::ORGANIZATION_ID,
                'key' => 'runner-audio-'.($index + 1),
                'asset_kind' => 'lesson_audio',
                'storage_object_key' => 'private/runner-audio-'.($index + 1),
                'mime_type' => 'audio/mpeg',
                'byte_size' => $bytes,
                'asset_hash' => $hash,
                'uploaded_by_membership_id' => self::MEMBERSHIP_ID,
                'uploader_attribution' => 'test',
                'uploaded_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('media_security_scans')->insert([
                'id' => $scanId,
                'organization_id' => self::ORGANIZATION_ID,
                'media_upload_id' => null,
                'media_asset_id' => $assetId,
                'asset_kind' => 'lesson_audio',
                'object_hash' => $hash,
                'byte_size' => $bytes,
                'inspection_outcome' => 'passed',
                'malware_outcome' => 'clean',
                'detected_mime_type' => 'audio/mpeg',
                'duration_milliseconds' => $duration,
                'inspector_name' => 'ffprobe',
                'inspector_policy_version' => 'admin-media-inspection-v1',
                'scanner_name' => 'clamav-instream',
                'scanner_engine_version' => 'test',
                'scanner_policy_version' => 'admin-media-malware-v1',
                'technical_metadata_hash' => hash('sha256', 'scan-'.$assetId),
                'inspected_at' => $now,
                'scanned_at' => $now,
                'created_at' => $now,
            ]);
            DB::table('media_asset_lifecycles')->insert([
                'organization_id' => self::ORGANIZATION_ID,
                'media_asset_id' => $assetId,
                'asset_kind' => 'lesson_audio',
                'state' => 'active',
                'version' => 1,
                'selected_scan_id' => $scanId,
                'predecessor_media_asset_id' => null,
                'managed_by_membership_id' => self::MEMBERSHIP_ID,
                'retired_by_membership_id' => null,
                'retirement_reason_key' => null,
                'managed_at' => $now,
                'retired_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('media_rights_revisions')->insert([
                'id' => $rightsId,
                'organization_id' => self::ORGANIZATION_ID,
                'program_id' => self::PROGRAM_ID,
                'media_asset_id' => $assetId,
                'parent_revision_id' => null,
                'revision_number' => 1,
                'evidence_status' => 'approved',
                'evidence_hash' => hash('sha256', 'rights-'.$assetId),
                'selected_asset_hash' => $hash,
                'rights_holder_reference' => 'APP-0047',
                'provenance_reference' => 'LPL-01',
                'rights_basis_key' => 'owner_authorization',
                'license_or_contract_reference' => 'APP-0047',
                'permitted_release_channels' => json_encode(['web', 'android'], JSON_THROW_ON_ERROR),
                'permitted_territories' => json_encode(['IN'], JSON_THROW_ON_ERROR),
                'learner_delivery_allowed' => true,
                'sharing_allowed' => false,
                'derivative_use_allowed' => true,
                'effective_at' => $now->subDay(),
                'expires_at' => null,
                'retention_policy_key' => 'preserve_while_referenced',
                'deletion_constraint_key' => 'rights_review_required',
                'confirmed_by_membership_id' => self::MEMBERSHIP_ID,
                'rights_appointment_id' => $appointmentId,
                'rights_appointment_event_id' => $appointmentEventId,
                'confirmed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
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
            'qualification_basis' => 'Synthetic DR-0025 rights fixture',
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

    private function configureAuthorizationEvidence(
        string $campaignId,
        int $rateMicrounitsPerSecond = 100,
    ): void {
        $directory = '/tmp/gse-dr0025-test-'.Str::lower((string) Str::ulid());
        mkdir($directory, 0700);
        $this->temporaryDirectories[] = $directory;
        $endpoint = 'https://speech-test.cognitiveservices.azure.com';
        $vendor = json_encode([
            'schema_version' => 1,
            'provider' => 'azure-speech',
            'service' => 'fast-transcription',
            'region' => 'centralindia',
            'api_version' => '2025-10-15',
            'resource_host' => 'speech-test.cognitiveservices.azure.com',
            'credential_version' => 'synthetic-v1',
            'data_logging' => 'disabled',
            'provider_storage' => 'none',
            'training' => false,
            'model_improvement' => false,
            'human_quality_review' => false,
            'endpoint_logging' => false,
            'candidate_store_application_encryption' => true,
            'candidate_store_public_access' => false,
            'candidate_backup_max_days' => 30,
            'provenance_retention_days' => 365,
            'dpa_effective_date' => '2026-05-01',
            'subscription_id_sha256' => hash('sha256', 'subscription'),
            'resource_id_sha256' => hash('sha256', 'resource'),
            'agreement_sha256' => hash('sha256', 'agreement'),
            'dpa_sha256' => hash('sha256', 'dpa'),
            'product_terms_sha256' => hash('sha256', 'product-terms'),
            'subprocessor_snapshot_sha256' => hash('sha256', 'subprocessors'),
            'contracting_entity' => 'Synthetic test entity',
            'privacy_review_reference' => 'PRIVACY-TEST-2026-08-10',
            'legal_review_reference' => 'LEGAL-TEST-2026-08-10',
            'valid_until' => '2026-09-01T00:00:00Z',
        ], JSON_THROW_ON_ERROR);
        $price = json_encode([
            'approval_reference' => 'APP-0052',
            'manifest_sha256' => (string) config(
                'media_transcription.expected_manifest_sha256',
            ),
            'currency' => 'INR',
            'conversion_source' => 'synthetic-test-fixture',
            'official_price_source' => 'https://azure.microsoft.com/en-in/pricing/details/speech/',
            'conversion_evidence_sha256' => hash('sha256', 'conversion'),
            'captured_on' => '2026-08-10T00:00:00Z',
            'microunits_per_billable_second' => $rateMicrounitsPerSecond,
        ], JSON_THROW_ON_ERROR);
        $paths = [
            'secret' => $directory.'/secret',
            'vendor' => $directory.'/vendor.json',
            'price' => $directory.'/price.json',
            'permit' => $directory.'/permit',
            'report' => $directory.'/report.json',
        ];
        foreach ([
            $paths['secret'] => 'synthetic-secret',
            $paths['vendor'] => $vendor,
            $paths['price'] => $price,
            $paths['permit'] => $campaignId,
        ] as $path => $content) {
            file_put_contents($path, $content);
            chmod($path, 0600);
            $this->temporaryFiles[] = $path;
        }
        config()->set([
            'media_transcription.enabled' => true,
            'media_transcription.live_requests_enabled' => true,
            'media_transcription.provider.endpoint' => $endpoint,
            'media_transcription.provider.allowed_host' => 'speech-test.cognitiveservices.azure.com',
            'media_transcription.provider.credential_kind' => 'subscription-key',
            'media_transcription.provider.secret_file' => $paths['secret'],
            'media_transcription.evidence.vendor_file' => $paths['vendor'],
            'media_transcription.evidence.vendor_sha256' => hash('sha256', $vendor),
            'media_transcription.evidence.price_file' => $paths['price'],
            'media_transcription.evidence.price_sha256' => hash('sha256', $price),
            'media_transcription.evidence.permit_file' => $paths['permit'],
            'media_transcription.evidence.safe_report_file' => $paths['report'],
        ]);
    }
}

final class SyntheticCurriculumMediaSourceReader implements CurriculumMediaSourceReader
{
    public function readExact(
        string $assetKind,
        string $storageObjectKey,
        int $expectedBytes,
        string $expectedSha256,
    ): string {
        return 'synthetic-test-audio-'.$storageObjectKey;
    }
}

final class DeterministicCurriculumMediaTranscriptionProvider implements CurriculumMediaTranscriptionProvider
{
    public int $calls = 0;

    public function __construct(
        private readonly bool $failFirstAttempt,
        private readonly string $failureCode = 'provider_transient_failure',
    ) {}

    public function transcribe(MediaTranscriptionRequest $request): MediaTranscriptionResult
    {
        $this->calls++;
        if ($this->failFirstAttempt && $this->calls === 1) {
            throw new MediaTranscriptionProviderException(
                $this->failureCode,
                true,
                25,
            );
        }
        $text = 'I can speak in English. Candidate '.$this->calls.'.';

        return new MediaTranscriptionResult(
            transcript: $text,
            segments: [new MediaTranscriptionSegment(
                1,
                $text,
                0,
                min(1000, $request->sourceDurationMilliseconds),
                9500,
            )],
            responseMetadataSha256: hash('sha256', 'response-'.$this->calls),
            latencyMilliseconds: 20,
            usageMilliseconds: $request->sourceDurationMilliseconds,
        );
    }
}

final readonly class RejectingCurriculumMediaSourceReader implements CurriculumMediaSourceReader
{
    public function readExact(
        string $assetKind,
        string $storageObjectKey,
        int $expectedBytes,
        string $expectedSha256,
    ): string {
        throw new MediaTranscriptionRejected('source_object_invalid');
    }
}

final class EvidenceMutatingCurriculumMediaTranscriptionProvider implements CurriculumMediaTranscriptionProvider
{
    public int $calls = 0;

    public function __construct(private readonly string $vendorPath) {}

    public function transcribe(MediaTranscriptionRequest $request): MediaTranscriptionResult
    {
        $this->calls++;
        $vendor = file_get_contents($this->vendorPath);
        if (! is_string($vendor) || file_put_contents(
            $this->vendorPath,
            $vendor."\n",
        ) === false) {
            throw new \RuntimeException('Synthetic evidence mutation failed.');
        }
        chmod($this->vendorPath, 0600);
        $text = 'I can speak in English.';

        return new MediaTranscriptionResult(
            $text,
            [new MediaTranscriptionSegment(1, $text, 0, 1000, 9500)],
            hash('sha256', 'evidence-mutation-response'),
            20,
            $request->sourceDurationMilliseconds,
        );
    }
}

final class ReentrantCurriculumMediaTranscriptionProvider implements CurriculumMediaTranscriptionProvider
{
    public int $calls = 0;

    public ?string $overlapFailure = null;

    /** @var null|callable(array<string, mixed>, Factory): CurriculumMediaTranscriptionProvider */
    public $factory = null;

    public function __construct(
        private readonly MediaTranscriptionCampaignRunner $runner,
        private readonly string $campaignId,
        private readonly string $membershipId,
    ) {}

    public function transcribe(MediaTranscriptionRequest $request): MediaTranscriptionResult
    {
        $this->calls++;
        if ($this->calls === 1) {
            try {
                $factory = $this->factory;
                if (! is_callable($factory)) {
                    throw new \RuntimeException('Synthetic overlap factory missing.');
                }
                $this->runner->run(
                    $this->campaignId,
                    $this->membershipId,
                    $factory,
                );
            } catch (MediaTranscriptionRejected $exception) {
                $this->overlapFailure = $exception->safeCode;
            }
        }
        $text = 'I can speak in English. Concurrent candidate '.$this->calls.'.';

        return new MediaTranscriptionResult(
            $text,
            [new MediaTranscriptionSegment(1, $text, 0, 1000, 9500)],
            hash('sha256', 'overlap-response-'.$this->calls),
            20,
            $request->sourceDurationMilliseconds,
        );
    }
}

final readonly class CrashAfterCandidateWriteStore implements MediaTranscriptionCandidateStore
{
    public function __construct(private MediaTranscriptionCandidateStore $delegate) {}

    public function put(
        string $organizationId,
        string $campaignId,
        string $campaignAssetId,
        string $providerAttemptId,
        MediaTranscriptionCandidate $candidate,
    ): string {
        $this->delegate->put(
            $organizationId,
            $campaignId,
            $campaignAssetId,
            $providerAttemptId,
            $candidate,
        );

        throw new \RuntimeException('synthetic_candidate_commit_interruption');
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
        $this->delegate->delete(
            $organizationId,
            $campaignId,
            $campaignAssetId,
            $providerAttemptId,
        );
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

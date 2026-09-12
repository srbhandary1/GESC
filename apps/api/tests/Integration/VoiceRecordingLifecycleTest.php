<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Conversation\ConversationSessionService;
use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Voice\Providers\RecordedSpeechToTextProvider;
use App\Conversation\Voice\Providers\RecordedSpeechToTextRequest;
use App\Conversation\Voice\Providers\RecordedSpeechToTextResult;
use App\Conversation\Voice\VoiceConsentService;
use App\Conversation\Voice\VoiceDeletionOutboxMonitor;
use App\Conversation\Voice\VoiceDeletionService;
use App\Conversation\Voice\VoiceOperationRejected;
use App\Conversation\Voice\VoiceRecordingService;
use App\Conversation\Voice\VoiceRetentionService;
use App\Conversation\Voice\VoiceSourceObjectStore;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class VoiceRecordingLifecycleTest extends TenancyIntegrationTestCase
{
    public function test_upload_requires_both_distinct_active_consent_purposes(): void
    {
        Storage::fake('local');
        [$learner, , $context] = $this->learner();
        $session = $this->startConversationSession($learner, $context);
        $this->contexts()->run(
            $context,
            fn (): array => $this->app->make(VoiceConsentService::class)
                ->grant(
                    learner: $learner,
                    purpose: VoiceConsentService::UPLOAD,
                    requestedLocale: 'en-IN',
                    idempotencyKey: (string) Str::uuid(),
                ),
        );

        try {
            $this->contexts()->run(
                $context,
                fn (): array => $this->app->make(VoiceRecordingService::class)
                    ->create(
                        learner: $learner,
                        sessionId: (string) $session['id'],
                        audioBytes: self::wav(),
                        declaredMimeType: 'audio/wav',
                        idempotencyKey: (string) Str::uuid(),
                    ),
            );
            self::fail('Expected missing provider consent to reject upload.');
        } catch (VoiceOperationRejected) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, DB::table('voice_recordings')->count());
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_secure_recording_correction_and_deletion_are_tenant_scoped(): void
    {
        Storage::fake('local');
        [$learner, $membership, $context] = $this->learner();
        $session = $this->startConversationSession($learner, $context);
        $this->grantRequiredConsents($learner, $context);
        $learningBefore = $this->learningCounts();
        $recordings = $this->app->make(VoiceRecordingService::class);
        $command = (string) Str::uuid();
        $wav = self::wav();

        $recording = $this->contexts()->run(
            $context,
            fn (): array => $recordings->create(
                learner: $learner,
                sessionId: (string) $session['id'],
                audioBytes: $wav,
                declaredMimeType: 'audio/wav',
                idempotencyKey: $command,
            ),
        );
        $replay = $this->contexts()->run(
            $context,
            fn (): array => $recordings->create(
                learner: $learner,
                sessionId: (string) $session['id'],
                audioBytes: $wav,
                declaredMimeType: 'audio/wav',
                idempotencyKey: $command,
            ),
        );

        $this->assertSame('awaiting_learner_confirmation', $recording['status']);
        $this->assertSame('I practise English every day.', $recording['transcript']);
        $this->assertSame(0.87, $recording['transcript_confidence']);
        $this->assertTrue($recording['source_deleted']);
        $this->assertTrue($replay['replayed']);
        $this->assertSame($recording['id'], $replay['id']);
        Storage::disk('local')->assertDirectoryEmpty('/');

        $databaseSnapshot = json_encode([
            'recording' => DB::table('voice_recordings')->get()->all(),
            'provider_runs' => DB::table('voice_provider_runs')->get()->all(),
            'commands' => DB::table('conversation_commands')->get()->all(),
        ], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString(
            'I practise English every day.',
            $databaseSnapshot,
        );
        $this->assertStringNotContainsString(base64_encode($wav), $databaseSnapshot);
        $this->assertSame(
            hash('sha256', 'I practise English every day.'),
            DB::table('voice_recordings')->value(
                'transcript_candidate_sha256',
            ),
        );

        $corrected = $this->contexts()->run(
            $context,
            fn (): array => $recordings->review(
                learner: $learner,
                recordingId: (string) $recording['id'],
                action: 'correct',
                correctedTranscript: 'I practise speaking English every day.',
                idempotencyKey: (string) Str::uuid(),
            ),
        );
        $this->assertSame('corrected', $corrected['status']);
        $this->assertSame(
            'I practise speaking English every day.',
            $corrected['transcript'],
        );
        $this->assertNull(
            DB::table('voice_recordings')->value('transcript_ciphertext'),
        );
        $this->assertSame($learningBefore, $this->learningCounts());

        [$otherLearner, , $otherContext] = $this->learner(
            $this->createOrganization('Other voice tenant'),
        );
        try {
            $this->contexts()->run(
                $otherContext,
                fn (): array => $recordings->show(
                    $otherLearner,
                    (string) $recording['id'],
                ),
            );
            self::fail('Expected cross-tenant recording access to fail.');
        } catch (VoiceOperationRejected) {
            $this->addToAssertionCount(1);
        }
        try {
            $this->contexts()->run(
                $otherContext,
                fn (): array => $recordings->delete(
                    $otherLearner,
                    (string) $recording['id'],
                ),
            );
            self::fail('Expected cross-tenant recording deletion to fail.');
        } catch (VoiceOperationRejected) {
            $this->addToAssertionCount(1);
        }

        $deletion = $this->contexts()->run(
            $context,
            fn (): array => $recordings->delete(
                $learner,
                (string) $recording['id'],
            ),
        );
        $deletionStatus = $this->contexts()->run(
            $context,
            fn (): array => $recordings->deletion(
                $learner,
                (string) $recording['id'],
            ),
        );
        $this->assertSame('completed', $deletion['status']);
        $this->assertTrue($deletion['source_deleted']);
        $this->assertTrue($deletion['transcript_deleted']);
        $this->assertSame($deletion['id'], $deletionStatus['id']);
        $this->assertSame(
            $membership->getKey(),
            DB::table('voice_deletion_requests')->value(
                'learner_membership_id',
            ),
        );
    }

    public function test_provider_outage_retries_then_retains_only_encrypted_recoverable_source(): void
    {
        Storage::fake('local');
        $this->app->instance(
            RecordedSpeechToTextProvider::class,
            new AlwaysFailRecordedSpeechProvider,
        );
        [$learner, , $context] = $this->learner();
        $session = $this->startConversationSession($learner, $context);
        $this->grantRequiredConsents($learner, $context);
        $recordings = $this->app->make(VoiceRecordingService::class);
        $wav = self::wav();

        $recording = $this->contexts()->run(
            $context,
            fn (): array => $recordings->create(
                learner: $learner,
                sessionId: (string) $session['id'],
                audioBytes: $wav,
                declaredMimeType: 'audio/wav',
                idempotencyKey: (string) Str::uuid(),
            ),
        );

        $this->assertSame('provider_failed_recoverable', $recording['status']);
        $this->assertFalse($recording['source_deleted']);
        $this->assertNull($recording['transcript']);
        $this->assertSame(
            ['failed', 'failed'],
            DB::table('voice_provider_runs')
                ->orderBy('provider_attempt')
                ->pluck('result_status')
                ->all(),
        );
        $ciphertext = Storage::disk('local')->get(
            (string) DB::table('voice_recordings')->value('source_object_key'),
        );
        $this->assertIsString($ciphertext);
        $this->assertStringNotContainsString(base64_encode($wav), $ciphertext);

        DB::table('voice_recordings')
            ->where('id', $recording['id'])
            ->update(['source_delete_by' => now()->subSecond()]);
        $purged = $this->app->make(VoiceRetentionService::class)
            ->purgeDue();
        $this->assertSame(
            [
                'recordings_examined' => 1,
                'recordings_purged' => 1,
                'deletion_attempts' => 1,
                'deletion_failures' => 0,
            ],
            $purged,
        );
        Storage::disk('local')->assertDirectoryEmpty('/');
        $this->assertSame(
            'expired_deleted',
            DB::table('voice_recordings')->value('status'),
        );
        $this->assertSame(
            'completed',
            DB::table('voice_deletion_requests')->value('status'),
        );
    }

    public function test_failed_retention_deletion_is_retryable_and_reentrant_workers_do_not_double_claim(): void
    {
        Storage::fake('local');
        $this->app->instance(
            RecordedSpeechToTextProvider::class,
            new AlwaysFailRecordedSpeechProvider,
        );
        [$learner, , $context] = $this->learner();
        $session = $this->startConversationSession($learner, $context);
        $this->grantRequiredConsents($learner, $context);
        $wav = self::wav();
        $recording = $this->contexts()->run(
            $context,
            fn (): array => $this->app->make(VoiceRecordingService::class)
                ->create(
                    learner: $learner,
                    sessionId: (string) $session['id'],
                    audioBytes: $wav,
                    declaredMimeType: 'audio/wav',
                    idempotencyKey: (string) Str::uuid(),
                ),
        );
        $objectKey = DB::table('voice_recordings')
            ->where('id', $recording['id'])
            ->value('source_object_key');
        self::assertIsString($objectKey);
        DB::table('voice_recordings')
            ->where('id', $recording['id'])
            ->update(['source_delete_by' => now()->subSecond()]);

        $store = new ReentrantVoiceSourceObjectStore;
        $deletions = new VoiceDeletionService($store);
        $retention = new VoiceRetentionService($deletions);
        $ambientTransactionLevel = DB::transactionLevel();
        $nestedRuns = [];
        $store->duringDelete = static function () use (
            $deletions,
            &$nestedRuns,
        ): void {
            $nestedRuns[] = $deletions->processDue();
        };

        $first = $retention->purgeDue();

        $this->assertSame([
            'recordings_examined' => 1,
            'recordings_purged' => 0,
            'deletion_attempts' => 1,
            'deletion_failures' => 1,
        ], $first);
        $this->assertSame([
            ['attempted' => 0, 'succeeded' => 0, 'failed' => 0],
        ], $nestedRuns);
        $this->assertSame(1, $store->attempts);
        $this->assertSame(
            [$ambientTransactionLevel],
            $store->transactionLevels,
        );
        $this->assertDatabaseHas('voice_recordings', [
            'id' => $recording['id'],
            'status' => 'provider_failed_recoverable',
            'source_object_key' => $objectKey,
            'source_deleted_at' => null,
        ]);
        $deletion = DB::table('voice_deletion_requests')->sole();
        $this->assertSame('partially_completed', $deletion->status);
        $this->assertFalse((bool) $deletion->source_deleted);
        $this->assertNull($deletion->completed_at);
        $outbox = DB::table('outbox_events')
            ->where('aggregate_type', 'voice_deletion_request')
            ->sole();
        $this->assertSame(1, (int) $outbox->attempts);
        $this->assertSame('voice_source_delete_failed', $outbox->last_error_code);
        $this->assertNull($outbox->published_at);
        $this->assertSame(
            [],
            json_decode(
                (string) $outbox->payload,
                true,
                512,
                JSON_THROW_ON_ERROR,
            ),
        );
        $safeEvidence = json_encode([
            'payload' => $outbox->payload,
            'last_error_code' => $outbox->last_error_code,
        ], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($objectKey, $safeEvidence);
        $this->assertStringNotContainsString(base64_encode($wav), $safeEvidence);

        $this->assertFalse($deletions->processRequest((string) $deletion->id));
        $this->assertSame(1, $store->attempts);

        $store->fail = false;
        $staleRaceRuns = [];
        $store->duringDelete = static function () use (
            $store,
            $deletions,
            $outbox,
            &$staleRaceRuns,
        ): void {
            $store->duringDelete = null;
            DB::table('outbox_events')
                ->where('id', $outbox->id)
                ->update(['available_at' => now()->subSecond()]);
            $staleRaceRuns[] = $deletions->processDue();
        };
        DB::table('outbox_events')
            ->where('id', $outbox->id)
            ->update(['available_at' => now()->subSecond()]);
        $retry = $deletions->processDue();

        $this->assertSame(
            ['attempted' => 1, 'succeeded' => 0, 'failed' => 1],
            $retry,
        );
        $this->assertSame([
            ['attempted' => 1, 'succeeded' => 1, 'failed' => 0],
        ], $staleRaceRuns);
        $this->assertSame(3, $store->attempts);
        $this->assertSame(
            [
                $ambientTransactionLevel,
                $ambientTransactionLevel,
                $ambientTransactionLevel,
            ],
            $store->transactionLevels,
        );
        Storage::disk('local')->assertMissing($objectKey);
        $this->assertDatabaseHas('voice_recordings', [
            'id' => $recording['id'],
            'status' => 'expired_deleted',
            'source_object_key' => null,
        ]);
        $this->assertDatabaseHas('voice_deletion_requests', [
            'id' => $deletion->id,
            'status' => 'completed',
            'source_deleted' => true,
        ]);
        $this->assertSame(
            3,
            (int) DB::table('outbox_events')
                ->where('id', $outbox->id)
                ->value('attempts'),
        );
        $this->assertNotNull(
            DB::table('outbox_events')
                ->where('id', $outbox->id)
                ->value('published_at'),
        );
        $this->assertSame(
            ['attempted' => 0, 'succeeded' => 0, 'failed' => 0],
            $deletions->processDue(),
        );
    }

    public function test_terminal_deletion_failures_are_observable_and_require_explicit_requeue(): void
    {
        Storage::fake('local');
        config()->set('voice.deletion_max_attempts', 2);
        $this->app->instance(
            RecordedSpeechToTextProvider::class,
            new AlwaysFailRecordedSpeechProvider,
        );
        [$learner, , $context] = $this->learner();
        $session = $this->startConversationSession($learner, $context);
        $this->grantRequiredConsents($learner, $context);
        $recording = $this->contexts()->run(
            $context,
            fn (): array => $this->app->make(VoiceRecordingService::class)
                ->create(
                    learner: $learner,
                    sessionId: (string) $session['id'],
                    audioBytes: self::wav(),
                    declaredMimeType: 'audio/wav',
                    idempotencyKey: (string) Str::uuid(),
                ),
        );
        $recordingRow = DB::table('voice_recordings')
            ->where('id', $recording['id'])
            ->sole(['organization_id', 'source_object_key']);
        self::assertIsString($recordingRow->source_object_key);
        DB::table('voice_recordings')
            ->where('id', $recording['id'])
            ->update(['source_delete_by' => now()->subSecond()]);

        $store = new ReentrantVoiceSourceObjectStore;
        $deletions = new VoiceDeletionService($store);
        $retention = new VoiceRetentionService($deletions);
        $retention->purgeDue();
        $outbox = DB::table('outbox_events')
            ->where('aggregate_type', 'voice_deletion_request')
            ->sole();
        DB::table('outbox_events')
            ->where('id', $outbox->id)
            ->update(['available_at' => now()->subSecond()]);

        $this->assertSame(
            ['attempted' => 1, 'succeeded' => 0, 'failed' => 1],
            $deletions->processDue(),
        );
        $deadLetter = DB::table('outbox_events')
            ->where('id', $outbox->id)
            ->sole();
        $this->assertSame(2, (int) $deadLetter->attempts);
        $this->assertNull($deadLetter->published_at);
        $this->assertNotNull($deadLetter->dead_lettered_at);
        $this->assertSame(
            'voice_source_delete_failed',
            $deadLetter->dead_letter_reason_code,
        );
        $this->assertDatabaseHas('voice_deletion_requests', [
            'id' => $outbox->aggregate_id,
            'status' => 'partially_completed',
            'source_deleted' => false,
        ]);

        $monitor = new VoiceDeletionOutboxMonitor;
        $health = $monitor->snapshot();
        $this->assertSame('unhealthy', $health['status']);
        $this->assertSame(0, $health['active']);
        $this->assertSame(1, $health['dead_lettered']);
        $safeHealth = json_encode($health, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString(
            (string) $recordingRow->source_object_key,
            $safeHealth,
        );
        $this->assertStringNotContainsString(
            (string) $recordingRow->organization_id,
            $safeHealth,
        );
        $this->assertStringNotContainsString((string) $recording['id'], $safeHealth);
        $this->assertSame(1, Artisan::call('voice:deletion-status'));
        $this->assertStringNotContainsString(
            (string) $recordingRow->source_object_key,
            Artisan::output(),
        );
        try {
            DB::transaction(static function () use ($outbox): void {
                DB::table('outbox_events')
                    ->where('id', $outbox->id)
                    ->update(['published_at' => now()]);
            });
            self::fail('A dead letter must not also be marked published.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', (string) $exception->getCode());
        }
        $this->assertSame(
            ['attempted' => 0, 'succeeded' => 0, 'failed' => 0],
            $deletions->processDue(),
        );
        $this->assertSame(2, $store->attempts);

        $this->assertSame(0, Artisan::call('voice:deletion-requeue', [
            'event' => (string) $outbox->id,
        ]));
        $this->assertSame(
            ['status' => 'requeued'],
            json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR),
        );
        $this->assertDatabaseHas('outbox_events', [
            'id' => $outbox->id,
            'attempts' => 0,
            'last_error_code' => null,
            'dead_lettered_at' => null,
            'dead_letter_reason_code' => null,
        ]);

        $store->fail = false;
        $this->assertSame(
            ['attempted' => 1, 'succeeded' => 1, 'failed' => 0],
            $deletions->processDue(),
        );
        Storage::disk('local')->assertMissing(
            (string) $recordingRow->source_object_key,
        );
        $this->assertSame('healthy', $monitor->snapshot()['status']);
        $this->assertDatabaseHas('voice_deletion_requests', [
            'id' => $outbox->aggregate_id,
            'status' => 'completed',
            'source_deleted' => true,
        ]);
    }

    public function test_stale_active_deletion_work_fails_the_health_signal(): void
    {
        config()->set('voice.deletion_outbox_stale_seconds', 60);
        $organizationId = (string) Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->value('id');
        $now = now();

        DB::table('outbox_events')->insert([
            'id' => (string) Str::ulid(),
            'scope_kind' => 'organization',
            'organization_id' => $organizationId,
            'event_type' => 'voice.source-deletion.requested',
            'aggregate_type' => 'voice_deletion_request',
            'aggregate_id' => (string) Str::ulid(),
            'payload' => '{}',
            'correlation_id' => null,
            'occurred_at' => $now->copy()->subSeconds(61),
            'available_at' => $now->copy()->subSecond(),
            'published_at' => null,
            'attempts' => 0,
            'last_error_code' => null,
            'dead_lettered_at' => null,
            'dead_letter_reason_code' => null,
        ]);

        $health = (new VoiceDeletionOutboxMonitor)->snapshot();

        $this->assertSame('unhealthy', $health['status']);
        $this->assertSame(1, $health['active']);
        $this->assertSame(1, $health['due']);
        $this->assertSame(1, $health['stale']);
        $this->assertGreaterThanOrEqual(
            60,
            $health['oldest_active_age_seconds'],
        );
        $this->assertSame(1, Artisan::call('voice:deletion-status'));
    }

    public function test_malformed_provider_response_is_fail_closed(): void
    {
        Storage::fake('local');
        $this->app->instance(
            RecordedSpeechToTextProvider::class,
            new MalformedRecordedSpeechProvider,
        );
        [$learner, , $context] = $this->learner();
        $session = $this->startConversationSession($learner, $context);
        $this->grantRequiredConsents($learner, $context);
        $recordings = $this->app->make(VoiceRecordingService::class);

        $recording = $this->contexts()->run(
            $context,
            fn (): array => $recordings->create(
                learner: $learner,
                sessionId: (string) $session['id'],
                audioBytes: self::wav(),
                declaredMimeType: 'audio/wav',
                idempotencyKey: (string) Str::uuid(),
            ),
        );

        $this->assertSame('provider_failed_recoverable', $recording['status']);
        $this->assertSame(
            ['malformed_response'],
            DB::table('voice_provider_runs')
                ->orderBy('provider_attempt')
                ->pluck('safe_failure_code')
                ->all(),
        );
    }

    /**
     * @return array{User, Membership, OrganizationContext}
     */
    private function learner(?Organization $organization = null): array
    {
        $organization ??= Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->firstOrFail();
        $user = $this->createUser();
        [$membership, $context] = $this->createMembership($organization, $user);
        $role = $this->createRole(
            $context,
            'voice-'.strtolower((string) Str::ulid()),
        );
        $permission = Permission::query()
            ->where('key', 'learning.participate')
            ->firstOrFail();
        $now = now();

        DB::table('membership_roles')->insert([
            'organization_id' => $organization->getKey(),
            'membership_id' => $membership->getKey(),
            'role_id' => $role->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('role_permissions')->insert([
            'organization_id' => $organization->getKey(),
            'role_id' => $role->getKey(),
            'permission_id' => $permission->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('learner_profiles')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->getKey(),
            'learner_membership_id' => $membership->getKey(),
            'adult_confirmed_at' => $now,
            'onboarding_completed_at' => $now,
            'created_at' => $now,
        ]);

        return [$user, $membership, $context];
    }

    /**
     * @return array<string, mixed>
     */
    private function startConversationSession(
        User $learner,
        OrganizationContext $context,
    ): array {
        return $this->contexts()->run(
            $context,
            fn (): array => $this->app->make(ConversationSessionService::class)
                ->start(
                    learner: $learner,
                    personaKey: 'patient-friend-prototype',
                    scenarioKey: 'everyday-friend-prototype',
                    coachingMode: 'gentle',
                    uiLocale: 'kn-IN',
                    idempotencyKey: (string) Str::uuid(),
                ),
        );
    }

    private function grantRequiredConsents(
        User $learner,
        OrganizationContext $context,
    ): void {
        $consents = $this->app->make(VoiceConsentService::class);

        foreach (
            [VoiceConsentService::UPLOAD, VoiceConsentService::PROVIDER] as $purpose
        ) {
            $this->contexts()->run(
                $context,
                fn (): array => $consents->grant(
                    learner: $learner,
                    purpose: $purpose,
                    requestedLocale: 'en-IN',
                    idempotencyKey: (string) Str::uuid(),
                ),
            );
        }
    }

    /**
     * @return array<string, int>
     */
    private function learningCounts(): array
    {
        return collect([
            'learner_attempts',
            'item_responses',
            'attempt_submissions',
            'mastery_states',
            'review_schedules',
            'learning_commands',
        ])->mapWithKeys(
            static fn (string $table): array => [
                $table => DB::table($table)->count(),
            ],
        )->all();
    }

    private static function wav(): string
    {
        $sampleRate = 16_000;
        $dataSize = 3_200;
        $format = 'fmt '
            .pack('VvvVVvv', 16, 1, 1, $sampleRate, $sampleRate * 2, 2, 16);
        $data = 'data'.pack('V', $dataSize).str_repeat("\0", $dataSize);
        $body = 'WAVE'.$format.$data;

        return 'RIFF'.pack('V', strlen($body)).$body;
    }
}

final class AlwaysFailRecordedSpeechProvider implements RecordedSpeechToTextProvider
{
    public function transcribe(
        RecordedSpeechToTextRequest $request,
    ): RecordedSpeechToTextResult {
        throw new ConversationProviderException(
            safeCode: 'synthetic_outage',
            providerKey: 'deterministic-failure',
            modelVersion: 'failure-v1',
            latencyMilliseconds: 3,
            inputUnits: strlen($request->audioBytes),
            retryable: true,
        );
    }
}

final class MalformedRecordedSpeechProvider implements RecordedSpeechToTextProvider
{
    public function transcribe(
        RecordedSpeechToTextRequest $request,
    ): RecordedSpeechToTextResult {
        return new RecordedSpeechToTextResult(
            transcript: '',
            confidenceBps: 10_001,
            usage: new ProviderUsage(
                providerKey: 'deterministic-malformed',
                modelVersion: 'malformed-v1',
                latencyMilliseconds: 2,
                inputUnits: strlen($request->audioBytes),
                outputUnits: 0,
                estimatedCostMicrounits: 0,
            ),
        );
    }
}

final class ReentrantVoiceSourceObjectStore implements VoiceSourceObjectStore
{
    public int $attempts = 0;

    public bool $fail = true;

    /** @var list<int> */
    public array $transactionLevels = [];

    public ?\Closure $duringDelete = null;

    public function delete(string $objectKey): bool
    {
        $this->attempts++;
        $this->transactionLevels[] = DB::transactionLevel();

        if ($this->duringDelete instanceof \Closure) {
            ($this->duringDelete)();
        }

        return ! $this->fail && Storage::disk('local')->delete($objectKey);
    }
}

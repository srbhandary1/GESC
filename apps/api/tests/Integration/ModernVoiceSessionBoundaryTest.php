<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Conversation\ConversationSessionService;
use App\Conversation\Dialogue\ConversationDialogueService;
use App\Conversation\Dialogue\ConversationRecoveryStore;
use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Voice\Providers\RecordedSpeechToTextProvider;
use App\Conversation\Voice\Providers\RecordedSpeechToTextRequest;
use App\Conversation\Voice\Providers\RecordedSpeechToTextResult;
use App\Conversation\Voice\VoiceConsentService;
use App\Conversation\Voice\VoiceOperationRejected;
use App\Conversation\Voice\VoiceRecordingService;
use App\Entitlements\DirectPlanDefaults;
use App\Entitlements\EntitlementAccess;
use App\Entitlements\LearnerSubscriptionService;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Closure;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;

final class ModernVoiceSessionBoundaryTest extends TenancyIntegrationTestCase
{
    private BoundaryCountingSpeechProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set(['voice.upload_enabled' => true, 'voice.allowed_organization_ids' => [], 'voice.storage_disk' => 'local']);
        Storage::fake('local');
        Storage::fake(ConversationRecoveryStore::DISK);
        $this->provider = new BoundaryCountingSpeechProvider;
        $this->app->instance(RecordedSpeechToTextProvider::class, $this->provider);
    }

    /** @return array<string, array{string}> */
    public static function unavailableBoundaries(): array
    {
        return ['explicit exit' => ['exit'], 'exact expiry' => ['expiry'], 'processing withdrawal' => ['withdrawal']];
    }

    #[DataProvider('unavailableBoundaries')]
    public function test_new_recording_after_boundary_has_no_provider_recording_or_charge(string $boundary): void
    {
        [$user, $context, $session] = $this->fixture();
        $this->end($user, $context, $session, $boundary);
        $charges = DB::table('live_ai_meter_charges')->count();
        try {
            $this->record($user, $context, $session, (string) Str::uuid());
            self::fail('An unavailable modern session cannot admit a recording.');
        } catch (VoiceOperationRejected) {
            self::assertSame(0, $this->provider->calls);
            self::assertSame(0, DB::table('voice_recordings')->count());
            self::assertSame(0, DB::table('voice_provider_runs')->count());
            self::assertSame($charges, DB::table('live_ai_meter_charges')->count());
            Storage::disk('local')->assertDirectoryEmpty('/');
        }
    }

    #[DataProvider('unavailableBoundaries')]
    public function test_same_key_recording_replay_after_boundary_cannot_return_candidate(string $boundary): void
    {
        [$user, $context, $session] = $this->fixture();
        $key = (string) Str::uuid();
        $first = $this->record($user, $context, $session, $key);
        self::assertSame(BoundaryCountingSpeechProvider::TRANSCRIPT, $first['transcript_original']);
        self::assertSame(1, $this->provider->calls);
        $charges = DB::table('live_ai_meter_charges')->count();
        $this->end($user, $context, $session, $boundary);
        try {
            $this->record($user, $context, $session, $key);
            self::fail('An old command must not bypass the closed-session boundary.');
        } catch (VoiceOperationRejected) {
            self::assertSame(1, $this->provider->calls);
            self::assertSame(1, DB::table('voice_recordings')->count());
            self::assertSame(1, DB::table('voice_provider_runs')->count());
            self::assertSame($charges, DB::table('live_ai_meter_charges')->count());
        }
    }

    #[DataProvider('unavailableBoundaries')]
    public function test_late_processing_after_admission_is_blocked_before_provider_dispatch(string $boundary): void
    {
        [$user, $context, $session] = $this->fixture();
        $recordingId = $this->seedProcessingRecording($context, $session);
        $source = DB::table('voice_recordings')->where('id', $recordingId)->value('source_object_key');
        self::assertIsString($source);
        $this->end($user, $context, $session, $boundary);
        $charges = DB::table('live_ai_meter_charges')->count();
        // Simulates a committed upload whose next processing step was delayed
        // until after exit/expiry/withdrawal; no provider has seen the source.
        $process = new ReflectionMethod(VoiceRecordingService::class, 'process');
        try {
            $this->contexts()->run($context, fn (): mixed => $process->invoke($this->app->make(VoiceRecordingService::class), $user, $recordingId, self::wav()));
            self::fail('A late processing step must recheck the modern boundary.');
        } catch (VoiceOperationRejected) {
            self::assertSame(0, $this->provider->calls);
            self::assertSame(0, DB::table('voice_provider_runs')->count());
            self::assertSame($charges, DB::table('live_ai_meter_charges')->count());
            self::assertNull(DB::table('voice_recordings')->where('id', $recordingId)->value('transcript_ciphertext'));
            self::assertSame('rejected_deleted', DB::table('voice_recordings')->where('id', $recordingId)->value('status'));
            Storage::disk('local')->assertMissing($source);
        }
    }

    #[DataProvider('unavailableBoundaries')]
    public function test_old_candidate_cannot_be_shown_or_confirmed_after_boundary(string $boundary): void
    {
        [$user, $context, $session] = $this->fixture();
        $recording = $this->record($user, $context, $session, (string) Str::uuid());
        self::assertIsString($recording['id']);
        $recordingId = $recording['id'];
        $this->end($user, $context, $session, $boundary);
        foreach (['show', 'review'] as $operation) {
            try {
                $this->contexts()->run($context, fn (): array => $operation === 'show'
                    ? $this->app->make(VoiceRecordingService::class)->show($user, $recordingId)
                    : $this->app->make(VoiceRecordingService::class)->review($user, $recordingId, 'accept', null, (string) Str::uuid()));
                self::fail('An old candidate cannot be exposed or confirmed after its modern session becomes unavailable.');
            } catch (VoiceOperationRejected) {
                self::assertSame(1, $this->provider->calls);
                self::assertSame(0, DB::table('conversation_commands')->where('operation', 'voice.transcript.review')->count());
            }
        }
    }

    public function test_expiry_during_stt_keeps_known_usage_but_discards_candidate_and_source(): void
    {
        [$user, $context, $session] = $this->fixture();
        $charges = DB::table('live_ai_meter_charges')->count();
        $this->provider->afterDispatch = static function (): void {
            Date::setTestNow(Date::now()->addMinutes(16));
        };
        try {
            $this->record($user, $context, $session, (string) Str::uuid());
            self::fail('A late STT result cannot re-open expired context.');
        } catch (VoiceOperationRejected) {
            self::assertSame(1, $this->provider->calls);
            self::assertSame(1, DB::table('voice_provider_runs')->count());
            self::assertSame('succeeded', DB::table('voice_provider_runs')->sole()->result_status);
            self::assertSame($charges + 1, DB::table('live_ai_meter_charges')->count());
            $row = DB::table('voice_recordings')->sole();
            self::assertSame('rejected_deleted', $row->status);
            self::assertNull($row->transcript_ciphertext);
            self::assertNull($row->source_object_key);
            Storage::disk('local')->assertDirectoryEmpty('/');
        }
    }

    public function test_retryable_provider_failure_after_expiry_is_not_retried_or_retained_as_recoverable(): void
    {
        [$user, $context, $session] = $this->fixture();
        $this->provider->afterDispatch = static function (): void {
            Date::setTestNow(Date::now()->addMinutes(16));
            throw new ConversationProviderException('synthetic_timeout', 'deterministic-test', 'deterministic-stt-v1', 3, 3244, 0, true);
        };
        try {
            $this->record($user, $context, $session, (string) Str::uuid());
            self::fail('A failed late STT call must not become a retry after expiry.');
        } catch (VoiceOperationRejected) {
            self::assertSame(1, $this->provider->calls);
            self::assertSame(1, DB::table('voice_provider_runs')->count());
            self::assertSame('failed', DB::table('voice_provider_runs')->sole()->result_status);
            $row = DB::table('voice_recordings')->sole();
            self::assertSame('rejected_deleted', $row->status);
            self::assertNull($row->transcript_ciphertext);
            self::assertNull($row->source_object_key);
            Storage::disk('local')->assertDirectoryEmpty('/');
        }
    }

    public function test_other_learner_cannot_record_into_modern_session_or_spend_allowance(): void
    {
        [, , $session] = $this->fixture();
        [$other, $otherContext] = $this->fixture();
        $charges = DB::table('live_ai_meter_charges')->count();
        try {
            $this->record($other, $otherContext, $session, (string) Str::uuid());
            self::fail('Another learner cannot use a modern session.');
        } catch (VoiceOperationRejected) {
            self::assertSame(0, $this->provider->calls);
            self::assertSame(0, DB::table('voice_recordings')->count());
            self::assertSame($charges, DB::table('live_ai_meter_charges')->count());
        }
    }

    private function end(User $user, OrganizationContext $context, string $session, string $boundary): void
    {
        if ($boundary === 'exit') {
            $this->contexts()->run($context, fn (): array => $this->app->make(ConversationSessionService::class)->exit($user, $session, (string) Str::uuid()));
        } elseif ($boundary === 'expiry') {
            // Exact metadata deadline, not an imprecise time-travel boundary.
            DB::table('conversation_dialogue_states')->where('organization_id', $context->organizationId)
                ->where('conversation_session_id', $session)->update(['expires_at' => now()]);
        } else {
            $this->contexts()->run($context, fn (): array => $this->app->make(VoiceConsentService::class)->withdraw($user, VoiceConsentService::PROVIDER, 'en-IN', (string) Str::uuid()));
        }
    }

    /** @return array<string, mixed> */
    private function record(User $user, OrganizationContext $context, string $session, string $key): array
    {
        return $this->contexts()->run($context, fn (): array => $this->app->make(VoiceRecordingService::class)
            ->create($user, $session, self::wav(), 'audio/wav', $key));
    }

    private function seedProcessingRecording(OrganizationContext $context, string $session): string
    {
        $id = (string) Str::ulid();
        $source = 'voice/'.Str::random(64).'/'.Str::random(64).'.enc';
        Storage::disk('local')->put($source, Crypt::encryptString(base64_encode(self::wav())));
        $consents = $this->app->make(VoiceConsentService::class);
        $upload = $consents->activeEvent($context->organizationId, (string) $context->membershipId, VoiceConsentService::UPLOAD);
        $provider = $consents->activeEvent($context->organizationId, (string) $context->membershipId, VoiceConsentService::PROVIDER);
        DB::table('voice_recordings')->insert([
            'id' => $id, 'organization_id' => $context->organizationId, 'conversation_session_id' => $session,
            'learner_membership_id' => $context->membershipId, 'upload_consent_event_id' => $upload->id, 'provider_consent_event_id' => $provider->id,
            'status' => 'processing', 'source_object_key' => $source, 'source_sha256' => hash('sha256', self::wav()),
            'byte_size' => strlen(self::wav()), 'duration_ms' => 100, 'mime_type' => 'audio/wav', 'codec' => 'pcm_s16le',
            'sample_rate_hz' => 16000, 'channels' => 1, 'source_delete_by' => now()->addHour(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** @return array{User, OrganizationContext, string} */
    private function fixture(): array
    {
        $organization = Organization::query()->where('system_key', Organization::DIRECT_SYSTEM_KEY)->firstOrFail();
        $user = $this->createUser();
        [$membership, $context] = $this->createMembership($organization, $user);
        $role = $this->createRole($context, 'voice-boundary-'.strtolower((string) Str::ulid()));
        $permission = Permission::query()->where('key', 'learning.participate')->firstOrFail();
        DB::table('membership_roles')->insert(['organization_id' => $organization->id, 'membership_id' => $membership->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_permissions')->insert(['organization_id' => $organization->id, 'role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('learner_profiles')->insert(['id' => (string) Str::ulid(), 'organization_id' => $organization->id, 'learner_membership_id' => $membership->id, 'adult_confirmed_at' => now(), 'onboarding_completed_at' => now(), 'created_at' => now()]);
        $this->contexts()->run($context, function () use ($user, $organization): void {
            $this->app->make(EntitlementAccess::class)->snapshot($user);
            $plan = DB::table('entitlement_plans')->where('organization_id', $organization->id)->where('code', DirectPlanDefaults::MONTHLY_ROLEPLAY)->value('id');
            self::assertIsString($plan);
            $this->app->make(LearnerSubscriptionService::class)->checkout($user, $plan, (string) Str::ulid());
            foreach ([VoiceConsentService::UPLOAD, VoiceConsentService::PROVIDER] as $purpose) {
                $this->app->make(VoiceConsentService::class)->grant($user, $purpose, 'en-IN', (string) Str::uuid());
            }
        });
        $session = $this->contexts()->run($context, fn (): array => $this->app->make(ConversationSessionService::class)->start(
            $user, 'clinic-reception-roleplay-prototype', 'clinic-reception-roleplay-scenario', 'gentle', 'kn-IN', (string) Str::uuid(),
            interactionFlow: 'roleplay_dialogue', recoveryNoticeVersion: ConversationDialogueService::NOTICE, recoveryNoticeAccepted: true,
        ));
        self::assertIsString($session['id']);

        return [$user, $context, $session['id']];
    }

    private static function wav(): string
    {
        $format = 'fmt '.pack('VvvVVvv', 16, 1, 1, 16000, 32000, 2, 16);
        $data = 'data'.pack('V', 3200).str_repeat("\0", 3200);
        $body = 'WAVE'.$format.$data;

        return 'RIFF'.pack('V', strlen($body)).$body;
    }
}

final class BoundaryCountingSpeechProvider implements RecordedSpeechToTextProvider
{
    public const string TRANSCRIPT = 'I have a synthetic appointment.';

    public int $calls = 0;

    public ?Closure $afterDispatch = null;

    public function transcribe(RecordedSpeechToTextRequest $request): RecordedSpeechToTextResult
    {
        Assert::assertSame('en-IN', $request->recognitionLanguage);
        $this->calls++;
        if ($this->afterDispatch !== null) {
            ($this->afterDispatch)();
        }

        return new RecordedSpeechToTextResult(self::TRANSCRIPT, 9300, new ProviderUsage(
            providerKey: 'deterministic-test', modelVersion: 'deterministic-stt-v1', latencyMilliseconds: 1,
            inputUnits: strlen($request->audioBytes), outputUnits: strlen(self::TRANSCRIPT), estimatedCostMicrounits: 0,
            inputUnitKind: 'audio_bytes', outputUnitKind: 'utf8_bytes', dispatchKind: 'local_execution',
        ), transcriptSource: 'original_speech', transcriptLocale: 'en-IN');
    }
}

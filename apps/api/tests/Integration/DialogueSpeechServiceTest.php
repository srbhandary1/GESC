<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Conversation\Dialogue\ConversationRecoveryStore;
use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\GuidanceSpeechService;
use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Providers\TextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechRequest;
use App\Conversation\Providers\TextToSpeechResult;
use App\Conversation\Voice\VoiceConsentService;
use App\Conversation\Voice\VoiceOperationRejected;
use App\Entitlements\DirectPlanDefaults;
use App\Entitlements\EntitlementAccess;
use App\Entitlements\LearnerSubscriptionService;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;

final class DialogueSpeechServiceTest extends TenancyIntegrationTestCase
{
    private CountingDialogueSpeechProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set(['voice.allowed_organization_ids' => [], 'voice.upload_enabled' => true]);
        Storage::fake(ConversationRecoveryStore::DISK);
        $this->provider = new CountingDialogueSpeechProvider;
        $this->app->instance(TextToSpeechProvider::class, $this->provider);
    }

    public function test_opener_is_once_en_in_encrypted_and_metered_without_fake_learner_turn(): void
    {
        [$user, $member, $context, $session] = $this->fixture();
        $first = $this->speak($user, $context, $session, 'Welcome. How may I help?');
        $replay = $this->speak($user, $context, $session, 'Welcome. How may I help?');
        self::assertSame($first, $replay);
        self::assertTrue($first['playback_available']);
        self::assertSame(1, $this->provider->calls);
        $request = $this->provider->lastRequest;
        self::assertInstanceOf(TextToSpeechRequest::class, $request);
        self::assertSame('en-IN', $request->uiLocale);
        self::assertSame('en-IN-NeerjaNeural', $request->voice);
        self::assertSame(0, DB::table('conversation_turns')->count());
        self::assertSame(0, DB::table('live_ai_meter_charges')->count()); // Generation alone is not listening.
        $ledger = DB::table('conversation_session_speech_calls')->sole();
        self::assertSame('succeeded', $ledger->status);
        self::assertSame('deterministic-fake', $ledger->provider_key);
        self::assertSame('characters', $ledger->input_unit_kind);
        self::assertSame(28, (int) $ledger->input_units);
        self::assertSame(0, (int) $ledger->estimated_cost_microunits);
        self::assertSame((string) $member->id, $ledger->learner_membership_id);
        $metadata = json_encode($ledger, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('Welcome.', $metadata);
        self::assertStringNotContainsString(base64_encode('synthetic-opener-audio'), $metadata);
        foreach (Storage::disk(ConversationRecoveryStore::DISK)->files() as $file) {
            self::assertStringNotContainsString('Welcome.', (string) Storage::disk(ConversationRecoveryStore::DISK)->get($file));
        }
    }

    public function test_arbitrary_opener_text_and_local_language_purpose_never_dispatch(): void
    {
        [$user, , $context, $session] = $this->fixture();
        foreach ([['Read this arbitrary private text', 'model_en'], ['Welcome. How may I help?', 'guidance_l1']] as [$text, $purpose]) {
            try {
                $this->speak($user, $context, $session, $text, $purpose);
                self::fail('Untrusted narration must be rejected.');
            } catch (ConversationOperationRejected) {
                self::assertSame(0, $this->provider->calls);
            }
        }
        self::assertSame(0, DB::table('conversation_session_speech_calls')->count());
    }

    public function test_recovery_notice_does_not_replace_provider_processing_consent(): void
    {
        [$user, , $context, $session] = $this->fixture(false);
        try {
            $this->speak($user, $context, $session, 'Welcome. How may I help?');
            self::fail('Provider consent is independently required.');
        } catch (VoiceOperationRejected) {
            self::assertSame(0, $this->provider->calls);
        }
        $this->contexts()->run($context, fn (): array => $this->app->make(VoiceConsentService::class)->grant($user, VoiceConsentService::PROVIDER, 'en-IN', (string) Str::uuid()));
        $this->speak($user, $context, $session, 'Welcome. How may I help?');
        self::assertSame(1, $this->provider->calls);
        $this->contexts()->run($context, fn (): array => $this->app->make(VoiceConsentService::class)->withdraw($user, VoiceConsentService::PROVIDER, 'en-IN', (string) Str::uuid()));
        $this->expectException(VoiceOperationRejected::class);
        $this->speak($user, $context, $session, 'Welcome. How may I help?');
    }

    public function test_provider_failure_and_unknown_interrupted_reservation_do_not_redispatch_or_claim_free(): void
    {
        [$user, , $context, $session] = $this->fixture();
        $this->provider->fail = true;
        $first = $this->speak($user, $context, $session, 'Welcome. How may I help?');
        self::assertFalse($first['playback_available']);
        self::assertSame($first, $this->speak($user, $context, $session, 'Welcome. How may I help?'));
        self::assertSame(1, $this->provider->calls);
        self::assertNull(DB::table('conversation_session_speech_calls')->sole()->estimated_cost_microunits);
        self::assertSame(0, DB::table('live_ai_meter_charges')->count());
        DB::table('conversation_session_speech_calls')->where('conversation_session_id', $session)->update(['status' => 'dispatching', 'completed_at' => null]);
        DB::table('conversation_dialogue_states')->where('conversation_session_id', $session)->update(['opener_speech_artifact_id' => null]);
        self::assertFalse($this->speak($user, $context, $session, 'Welcome. How may I help?')['playback_available']);
        self::assertSame(1, $this->provider->calls);
    }

    public function test_latest_main_and_guidance_are_saved_only_with_no_hidden_other_language_audio(): void
    {
        [$user, $member, $context, $session] = $this->fixture();
        $this->saveTurn($member, $context, $session, 'That is helpful.', 'en-IN', 'reply', [
            'correction_en' => 'I went to the market.', 'explanation_l1' => 'ನಿನ್ನೆ ನಡೆದ ಕ್ರಿಯೆಗೆ went ಬಳಸಿ.', 'explanation_locale' => 'kn-IN',
        ]);
        $main = $this->speak($user, $context, $session, 'That is helpful.');
        self::assertSame(base64_encode('saved-main'), $main['model_audio_base64']);
        self::assertNull($main['guidance_audio_base64']);
        $guidance = $this->speak($user, $context, $session, 'ನಿನ್ನೆ ನಡೆದ ಕ್ರಿಯೆಗೆ went ಬಳಸಿ.', 'guidance_l1');
        self::assertSame(base64_encode('saved-guidance'), $guidance['guidance_audio_base64']);
        self::assertNull($guidance['model_audio_base64']);
        self::assertFalse($this->speak($user, $context, $session, 'I went to the market.')['playback_available']);
        self::assertSame(0, $this->provider->calls);
        self::assertSame(0, DB::table('conversation_session_speech_calls')->count());
        self::assertSame(0, DB::table('live_ai_meter_charges')->count());
    }

    public function test_question_help_local_answer_requires_recorded_acceptance_and_replays_actual_main_audio(): void
    {
        [$user, $member, $context, $session] = $this->fixture();
        DB::table('conversation_dialogue_states')->where('conversation_session_id', $session)->update(['flow' => 'question_help', 'state' => 'awaiting_question']);
        $this->saveTurn($member, $context, $session, 'ಇದು ಸರಳ ವಿವರಣೆ.', 'kn-IN', 'question');
        try {
            $this->speak($user, $context, $session, 'ಇದು ಸರಳ ವಿವರಣೆ.');
            self::fail('Question text is not explanation permission.');
        } catch (ConversationOperationRejected) {
            self::assertSame(0, $this->provider->calls);
        }
        DB::table('conversation_dialogue_outcomes')->where('conversation_session_id', $session)->update(['action' => 'accept_explanation']);
        $speech = $this->speak($user, $context, $session, 'ಇದು ಸರಳ ವಿವರಣೆ.');
        self::assertSame(base64_encode('saved-main'), $speech['model_audio_base64']);
        self::assertNull($speech['guidance_audio_base64']);
        self::assertSame(0, $this->provider->calls);
    }

    public function test_missing_saved_audio_is_text_first_and_never_synthesized_again(): void
    {
        [$user, $member, $context, $session] = $this->fixture();
        $this->saveTurn($member, $context, $session, 'A saved answer.', 'en-IN', 'reply', null, false);
        self::assertFalse($this->speak($user, $context, $session, 'A saved answer.')['playback_available']);
        self::assertSame(0, $this->provider->calls);
    }

    public function test_expired_closed_and_other_learner_sessions_cannot_trigger_speech(): void
    {
        [$user, $member, $context, $session] = $this->fixture();
        [$other, , $otherContext] = $this->fixture();
        try {
            $this->speak($other, $otherContext, $session, 'Welcome. How may I help?');
            self::fail('Other learner must not access the session.');
        } catch (ConversationOperationRejected) {
            self::assertSame(0, $this->provider->calls);
        }
        $this->app->make(ConversationRecoveryStore::class)->deleteMembership($context->organizationId, (string) $member->id);
        try {
            $this->speak($user, $context, $session, 'Welcome. How may I help?');
            self::fail('Closed session must not speak.');
        } catch (ConversationOperationRejected) {
            self::assertSame(0, $this->provider->calls);
        }
        [$fresh, , $freshContext, $freshSession] = $this->fixture();
        Date::setTestNow(Date::now()->addMinutes(15));
        $this->expectException(ConversationOperationRejected::class);
        $this->speak($fresh, $freshContext, $freshSession, 'Welcome. How may I help?');
    }

    /** @return array<string, mixed> */
    private function speak(User $user, OrganizationContext $context, string $session, string $text, string $purpose = 'model_en'): array
    {
        return $this->contexts()->run($context, fn (): array => $this->app->make(GuidanceSpeechService::class)->synthesize($user, $session, $text, $purpose));
    }

    /** @param array<string, mixed>|null $correction */
    private function saveTurn(Membership $member, OrganizationContext $context, string $session, string $reply, string $locale, string $action, ?array $correction = null, bool $audio = true): void
    {
        $turn = (string) Str::ulid();
        DB::table('conversation_turns')->insert([
            'id' => $turn, 'organization_id' => $context->organizationId, 'learner_membership_id' => $member->id,
            'conversation_session_id' => $session, 'turn_number' => 1, 'status' => 'completed', 'utterance_digest' => hash('sha256', 'synthetic'),
            'transcript_digest' => hash('sha256', 'synthetic'), 'assistant_reply_digest' => hash('sha256', $reply),
            'accepted_at' => now(), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $artifact = $this->app->make(ConversationRecoveryStore::class)->persist($context->organizationId, (string) $member->id, $session, $turn, [
            'result' => ['status' => 'completed', 'reply' => ['text' => $reply, 'locale' => $locale], 'priority_correction' => $correction,
                'speech' => ['playback_available' => $audio, 'rendering_mode' => 'recorded-model-speech', 'model_audio_base64' => $audio ? base64_encode('saved-main') : null,
                    'model_audio_mime_type' => 'audio/wav', 'guidance_audio_base64' => $audio ? base64_encode('saved-guidance') : null, 'guidance_audio_mime_type' => 'audio/wav']],
        ]);
        DB::table('conversation_dialogue_outcomes')->insert([
            'organization_id' => $context->organizationId, 'learner_membership_id' => $member->id, 'conversation_session_id' => $session,
            'conversation_turn_id' => $turn, 'artifact_id' => $artifact, 'revision' => 1, 'opportunity' => 1,
            'assessment' => 'unassessed', 'action' => $action, 'objective_evidence' => '[]', 'created_at' => now(),
        ]);
        DB::table('conversation_dialogue_states')->where('conversation_session_id', $session)->update([
            'revision' => 1, 'latest_turn_id' => $turn, 'latest_artifact_id' => $artifact,
        ]);
    }

    /** @return array{User, Membership, OrganizationContext, string} */
    private function fixture(bool $grantConsent = true): array
    {
        $organization = Organization::query()->where('system_key', Organization::DIRECT_SYSTEM_KEY)->firstOrFail();
        $user = $this->createUser();
        [$member, $context] = $this->createMembership($organization, $user);
        $role = $this->createRole($context, 'dialogue-speech-'.strtolower((string) Str::ulid()));
        $permission = Permission::query()->where('key', 'learning.participate')->firstOrFail();
        DB::table('membership_roles')->insert(['organization_id' => $context->organizationId, 'membership_id' => $member->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_permissions')->insert(['organization_id' => $context->organizationId, 'role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('learner_profiles')->insert([
            'id' => (string) Str::ulid(), 'organization_id' => $context->organizationId, 'learner_membership_id' => $member->id,
            'adult_confirmed_at' => now(), 'onboarding_completed_at' => now(), 'created_at' => now(),
        ]);
        if ($grantConsent) {
            $this->contexts()->run($context, fn (): array => $this->app->make(VoiceConsentService::class)->grant($user, VoiceConsentService::PROVIDER, 'en-IN', (string) Str::uuid()));
        }
        $this->contexts()->run($context, function () use ($user, $context): void {
            $this->app->make(EntitlementAccess::class)->snapshot($user);
            $plan = DB::table('entitlement_plans')->where('organization_id', $context->organizationId)->where('code', DirectPlanDefaults::MONTHLY_ROLEPLAY)->value('id');
            self::assertIsString($plan);
            $this->app->make(LearnerSubscriptionService::class)->checkout($user, $plan, (string) Str::ulid());
        });
        $persona = DB::table('persona_versions')->where('organization_id', $context->organizationId)->first();
        $scenario = DB::table('scenario_versions')->where('organization_id', $context->organizationId)->first();
        self::assertInstanceOf(stdClass::class, $persona);
        self::assertInstanceOf(stdClass::class, $scenario);
        $session = (string) Str::ulid();
        DB::table('conversation_sessions')->insert([
            'id' => $session, 'organization_id' => $context->organizationId, 'learner_membership_id' => $member->id,
            'persona_id' => $persona->persona_id, 'persona_version_id' => $persona->id, 'persona_definition_hash' => $persona->definition_hash,
            'scenario_id' => $scenario->scenario_id, 'scenario_version_id' => $scenario->id, 'scenario_definition_hash' => $scenario->definition_hash,
            'mode' => 'roleplay', 'coaching_mode' => 'gentle', 'status' => 'active', 'ui_locale' => 'kn-IN', 'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $artifact = $this->app->make(ConversationRecoveryStore::class)->persist($context->organizationId, (string) $member->id, $session, null, ['opening_reply' => ['text' => 'Welcome. How may I help?', 'locale' => 'en-IN']]);
        DB::table('conversation_dialogue_states')->insert([
            'organization_id' => $context->organizationId, 'learner_membership_id' => $member->id, 'conversation_session_id' => $session,
            'flow' => 'roleplay_dialogue', 'state' => 'awaiting_reply', 'start_artifact_id' => $artifact,
            'notice_version' => 'synthetic-notice', 'notice_accepted_at' => now(), 'expires_at' => now()->addMinutes(15), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$user, $member, $context, $session];
    }
}

final class CountingDialogueSpeechProvider implements TextToSpeechProvider
{
    public int $calls = 0;

    public bool $fail = false;

    public ?TextToSpeechRequest $lastRequest = null;

    public function synthesize(TextToSpeechRequest $request): TextToSpeechResult
    {
        $this->calls++;
        $this->lastRequest = $request;
        if ($this->fail) {
            throw new ConversationProviderException('fake_tts_unavailable', 'deterministic-fake', 'fake-tts-v1', 12, 28, 0, true);
        }

        return new TextToSpeechResult(true, 'recorded-model-speech', new ProviderUsage(
            providerKey: 'deterministic-fake', modelVersion: 'fake-tts-v1', latencyMilliseconds: 12,
            inputUnits: 28, outputUnits: 21, estimatedCostMicrounits: 0,
            inputUnitKind: 'characters', outputUnitKind: 'audio_bytes', dispatchKind: 'local_execution',
            voiceKey: $request->voice, locale: $request->uiLocale, configurationSha256: hash('sha256', 'synthetic'),
        ), 'synthetic-opener-audio', 'audio/wav');
    }
}

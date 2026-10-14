<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Conversation\Capacity\ConversationCapacityUnavailable;
use App\Conversation\ConversationSessionService;
use App\Conversation\ConversationTurnService;
use App\Conversation\Dialogue\ConversationDialogueService;
use App\Conversation\Dialogue\ConversationRecoveryStore;
use App\Conversation\Dialogue\DialogueSpeechService;
use App\Conversation\Dialogue\DialogueTurnOutcomeUnknown;
use App\Conversation\Dialogue\FakeDialogueResponder;
use App\Conversation\Exceptions\ConversationIdempotencyConflict;
use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Memory\LearnerMemoryService;
use App\Conversation\Metering\LearnerProviderUsageQuery;
use App\Conversation\Orchestration\PriorityCorrection;
use App\Conversation\Providers\ConversationModelProvider;
use App\Conversation\Providers\ConversationModelRequest;
use App\Conversation\Providers\ConversationModelResult;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Voice\VoiceConsentService;
use App\Conversation\Voice\VoiceOperationRejected;
use App\Entitlements\DirectPlanDefaults;
use App\Entitlements\EntitlementAccess;
use App\Entitlements\Exceptions\EntitlementDenied;
use App\Entitlements\LearnerSubscriptionService;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class InteractiveConversationTest extends TenancyIntegrationTestCase
{
    public function test_exact_tutor_greeting_uses_no_model_tokens_and_preserves_normal_tts(): void
    {
        [$user, $context] = $this->learner();
        $session = $this->start($user, $context, 'question_help');
        app()->instance(ConversationModelProvider::class, new class implements ConversationModelProvider
        {
            public function respond(ConversationModelRequest $request): ConversationModelResult
            {
                throw new \RuntimeException('A plain greeting must not dispatch a model request.');
            }
        });
        $turn = $this->turn($user, $context, $session, 'Good Morning swathi', 'question', 0);
        self::assertSame('ok', $turn['safety_state']);
        self::assertSame('Good morning! What would you like to practise?', $turn['reply']['text']);
        self::assertSame('unassessed', $turn['assessment']);
        $ledger = DB::table('ai_cost_ledger')->where('conversation_turn_id', $turn['id'])->where('provider_stage', 'model')->firstOrFail();
        self::assertSame('local-dialogue-control', $ledger->provider_key);
        self::assertSame(0, (int) $ledger->input_units + (int) $ledger->output_units);
        self::assertSame(1, DB::table('ai_cost_ledger')->where('conversation_turn_id', $turn['id'])->where('provider_stage', 'tts')->count());
    }

    public function test_rejected_dialogue_never_dispatches_or_charges_tts(): void
    {
        [$user, $context] = $this->learner();
        $session = $this->start($user, $context, 'question_help');
        app()->instance(ConversationModelProvider::class, new class implements ConversationModelProvider
        {
            public function respond(ConversationModelRequest $request): ConversationModelResult
            {
                return new ConversationModelResult('answer', str_repeat('verbose ', 40),
                    new ProviderUsage('deterministic-fake', 'fake-rejection-fixture', 0, 10, 40, 0),
                    dialogue: ['assessment' => 'unassessed', 'complete' => false, 'objective_evidence' => []]);
            }
        });
        $turn = $this->turn($user, $context, $session, 'Please explain.', 'question', 0);
        self::assertSame('fallback', $turn['safety_state']);
        self::assertFalse($turn['speech']['playback_available']);
        self::assertSame(0, DB::table('ai_cost_ledger')->where('conversation_turn_id', $turn['id'])->where('provider_stage', 'tts')->count());
        self::assertSame(0, DB::table('live_ai_meter_charges')->count());
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(ConversationRecoveryStore::DISK);
        config()->set('voice.upload_enabled', true);
        config()->set('voice.allowed_organization_ids', []);
    }

    public function test_free_speech_explanation_is_bound_text_only_replayable_and_does_not_advance(): void
    {
        $provider = new class implements ConversationModelProvider
        {
            /** @var list<ConversationModelRequest> */
            public array $requests = [];

            public function respond(ConversationModelRequest $request): ConversationModelResult
            {
                $this->requests[] = $request;

                return (new FakeDialogueResponder)->respond($request);
            }
        };
        $this->app->instance(ConversationModelProvider::class, $provider);
        [$user, $context] = $this->learner();
        $session = $this->start($user, $context, 'free_speech');
        $first = $this->turn($user, $context, $session, 'I enjoy gardening.', 'reply', 0);
        $key = (string) Str::uuid();
        $input = ['action' => 'accept_explanation', 'expected_revision' => 1, 'explanation_target' => $first['id']];
        $explain = fn () => $this->contexts()->run($context, fn () => app(ConversationTurnService::class)->create($user, $session['id'], 'Read in my language', $key, $input));
        $result = $explain();
        self::assertSame('completed', $result['status']);
        self::assertSame('ok', $result['safety_state']);
        self::assertSame('kn-IN', $result['reply']['locale']);
        self::assertSame($first['id'], $result['explanation_target']);
        self::assertSame($first['reply'], $result['explanation_source']);
        self::assertSame($first['reply'], $provider->requests[1]->dialogue['explanation_target']['answer']);
        self::assertSame($first['interaction']['opportunity'], $result['interaction']['opportunity']);
        self::assertSame($first['id'], DB::table('conversation_dialogue_states')->where('conversation_session_id', $session['id'])->value('partner_turn_id'));
        self::assertSame(0, DB::table('ai_cost_ledger')->where('conversation_turn_id', $result['id'])->where('provider_stage', 'tts')->count());
        self::assertSame(1, DB::table('ai_cost_ledger')->where('conversation_turn_id', $result['id'])->where('provider_stage', 'model')->count());
        self::assertSame(0, DB::table('live_ai_meter_charges')->count());
        $audio = $this->contexts()->run($context, fn () => app(DialogueSpeechService::class)->synthesizeIfModern($user, $context->organizationId, (string) $context->membershipId, $session['id'], $first['reply']['text'], 'model_en'));
        self::assertIsArray($audio);
        self::assertTrue($explain()['replayed']);
        self::assertCount(2, $provider->requests);
        $next = $this->turn($user, $context, $session, 'I grow tomatoes.', 'reply', 2);
        self::assertSame('en-IN', $next['reply']['locale']);
        self::assertCount(1, $provider->requests[2]->dialogue['history']);
        self::assertSame($first['reply']['text'], $provider->requests[2]->dialogue['current_partner_line']);
        $opening = $this->contexts()->run($context, fn () => app(ConversationTurnService::class)->create($user, $session['id'], 'Read opening', (string) Str::uuid(), ['action' => 'accept_explanation', 'expected_revision' => 3, 'explanation_target' => 'opening']));
        self::assertSame('ok', $opening['safety_state']);
        self::assertSame($session['interaction']['opening_reply'], $opening['explanation_source']);
        $audio = $this->contexts()->run($context, fn () => app(DialogueSpeechService::class)->synthesizeIfModern($user, $context->organizationId, (string) $context->membershipId, $session['id'], $session['interaction']['opening_reply']['text'], 'model_en'));
        self::assertIsArray($audio);
        self::assertSame(0, DB::table('conversation_session_speech_calls')->count());

    }

    public function test_free_speech_explanation_rejects_other_learner_turns_and_expired_content_before_dispatch(): void
    {
        [$user, $context] = $this->learner();
        $session = $this->start($user, $context, 'free_speech');
        [$other, $otherContext] = $this->learner();
        $otherSession = $this->start($other, $otherContext, 'free_speech');
        $otherTurn = $this->turn($other, $otherContext, $otherSession, 'I enjoy walking.', 'reply', 0);
        $count = DB::table('ai_cost_ledger')->count();
        foreach ([$otherTurn['id'], (string) Str::ulid(), null] as $target) {
            try {
                $this->contexts()->run($context, fn () => app(ConversationTurnService::class)->create($user, $session['id'], 'Read', (string) Str::uuid(), ['action' => 'accept_explanation', 'expected_revision' => 0, 'explanation_target' => $target]));
                self::fail('Unowned or missing source must not dispatch.');
            } catch (ConversationOperationRejected) {
                self::assertSame($count, DB::table('ai_cost_ledger')->count());
            }
        }
        $this->travel(16)->minutes();
        try {
            $this->contexts()->run($context, fn () => app(ConversationTurnService::class)->create($user, $session['id'], 'Read', (string) Str::uuid(), ['action' => 'accept_explanation', 'expected_revision' => 0, 'explanation_target' => 'opening']));
            self::fail('Expired opening must not dispatch.');
        } catch (ConversationOperationRejected) {
            self::assertSame($count, DB::table('ai_cost_ledger')->count());
        }
    }

    public function test_provider_token_totals_are_scoped_and_do_not_become_audio_charges(): void
    {
        [$user, $context] = $this->learner();
        $session = $this->start($user, $context);
        $turn = $this->turn($user, $context, $session, 'I have an appointment.', 'reply', 0);
        $fixture = (array) DB::table('ai_cost_ledger')->where('conversation_turn_id', $turn['id'])->where('provider_stage', 'model')->firstOrFail();
        DB::table('ai_cost_ledger')->insert([...$fixture, 'id' => (string) Str::ulid(),
            'provider_key' => 'azure-openai', 'model_version' => 'synthetic-token-meter', 'provider_attempt' => 2,
            'input_units' => 1234, 'output_units' => 56, 'input_unit_kind' => 'provider_input_tokens', 'output_unit_kind' => 'provider_output_tokens']);
        $query = app(LearnerProviderUsageQuery::class);
        $usage = $query->read($context->organizationId, (string) $context->membershipId);
        self::assertSame(1234, $usage['input_tokens']);
        self::assertSame(56, $usage['output_tokens']);
        self::assertSame(0, $usage['unknown_usage_attempts']);
        self::assertSame(0, $query->read((string) Str::ulid(), (string) $context->membershipId)['input_tokens']);
        self::assertSame(0, $query->read($context->organizationId, (string) Str::ulid())['input_tokens']);
        self::assertSame(0, DB::table('live_ai_meter_charges')->count());
    }

    public function test_ai_first_wrong_retry_and_valid_alternative_are_evidence_linked_not_marks(): void
    {
        [$user, $context] = $this->learner();
        $before = DB::table('mastery_states')->count();
        $session = $this->start($user, $context);
        $this->assertSame('perform', $session['stage']);
        $this->assertSame('awaiting_reply', $session['interaction']['state']);
        $this->assertSame('Good morning. How can I help you?', $session['interaction']['opening_reply']['text']);
        $this->assertSame(0, DB::table('conversation_turns')->count());
        $this->assertSame(['unassessed', 'unassessed'], array_column($session['feedback'], 'completion_state'));
        $wrong = $this->turn($user, $context, $session, 'I has an appointment.', 'reply', 0);
        $this->assertSame('retry_required', $wrong['assessment']);
        $this->assertSame(1, $wrong['interaction']['opportunity']);
        $this->assertSame($wrong['priority_correction']['correction_en'], $wrong['reply']['text']);
        $this->assertSame('kn-IN', $wrong['priority_correction']['explanation_locale']);
        $this->assertStringContainsString('ಮತ್ತೆ', $wrong['priority_correction']['explanation_l1']);
        $valid = $this->turn($user, $context, $session, 'Good morning. My appointment is at ten.', 'retry', 1);
        $this->assertSame('accepted', $valid['assessment']);
        $this->assertSame('awaiting_reply', $valid['interaction']['state']);
        $this->assertSame(2, $valid['interaction']['opportunity']);
        $this->assertNull($valid['priority_correction']);
        $reflection = $this->contexts()->run($context, fn () => app(ConversationSessionService::class)->exit($user, $session['id'], (string) Str::uuid()));
        $this->assertSame('closed', $reflection['interaction']['state']);
        $this->assertSame('reflect', $reflection['stage']);
        $this->assertSame(['met', 'met'], array_column($reflection['feedback'], 'completion_state'));
        $this->assertSame([$valid['id'], $valid['id']], array_column($reflection['feedback'], 'source_turn_id'));
        $this->assertSame(0, DB::table('roleplay_feedback_evidence')->count());
        $this->assertSame($before, DB::table('mastery_states')->count());
    }

    public function test_free_speech_keeps_seven_turn_context_private_and_unassessed_and_deletes_it_on_exit(): void
    {
        $provider = new class implements ConversationModelProvider
        {
            /** @var list<ConversationModelRequest> */
            public array $requests = [];

            public function respond(ConversationModelRequest $request): ConversationModelResult
            {
                $this->requests[] = $request;

                return (new FakeDialogueResponder)->respond($request);
            }
        };
        $this->app->instance(ConversationModelProvider::class, $provider);
        [$user, $context] = $this->learner();
        $session = $this->start($user, $context, 'free_speech');
        $this->contexts()->run($context, fn () => app(LearnerMemoryService::class)->create($user, 'I enjoy gardening.', (string) Str::uuid()));
        $this->assertSame('awaiting_reply', $session['interaction']['state']);
        $before = DB::table('mastery_states')->count();
        for ($index = 0; $index < 7; $index++) {
            $turn = $this->turn($user, $context, $session, 'Synthetic garden detail '.$index, 'reply', $index);
            $this->assertSame('completed', $turn['status']);
            $this->assertSame('unassessed', $turn['assessment']);
            $this->assertSame('awaiting_reply', $turn['interaction']['state']);
            $this->assertCount($index, $provider->requests[$index]->dialogue['history']);
        }
        $this->assertSame(['I enjoy gardening.'], $provider->requests[0]->memoryFacts);
        $this->assertSame('Synthetic garden detail 0', $provider->requests[6]->dialogue['history'][0]['learner']);
        $this->assertIsString($context->membershipId);
        $this->assertNull(app(ConversationDialogueService::class)->state((string) Str::ulid(), $context->membershipId, $session['id']));

        $this->assertSame($before, DB::table('mastery_states')->count());
        [$other, $otherContext] = $this->learner();
        try {
            $this->contexts()->run($otherContext, fn () => app(ConversationSessionService::class)->show($other, $session['id']));
            $this->fail('Another learner read free-speech context.');
        } catch (ConversationOperationRejected) {
            $this->assertCount(7, $provider->requests);
        }
        $this->contexts()->run($context, fn () => app(ConversationSessionService::class)->exit($user, $session['id'], (string) Str::uuid()));
        $this->assertSame(0, DB::table('conversation_recovery_artifacts')->where('conversation_session_id', $session['id'])->whereNull('deleted_at')->count());
    }

    public function test_free_speech_does_not_repeat_provider_correction_after_learner_fixes_it(): void
    {
        $this->app->instance(ConversationModelProvider::class, new class implements ConversationModelProvider
        {
            public function respond(ConversationModelRequest $request): ConversationModelResult
            {
                $fake = (new FakeDialogueResponder)->respond($request);

                return new ConversationModelResult('gardening', 'You can say, "I grow tomatoes." How are your tomatoes growing?', $fake->usage(),
                    priorityCorrection: new PriorityCorrection('grammar.agreement', 'I grow tomatoes.', 'I ನಂತರ grow ಬಳಸಿ.', 'kn-IN', false, learnerSpan: 'I grows tomatoes.'),
                    dialogue: ['assessment' => 'unassessed', 'complete' => false, 'objective_evidence' => []]);
            }
        });
        [$user, $context] = $this->learner();
        $session = $this->start($user, $context, 'free_speech');
        $wrong = $this->turn($user, $context, $session, 'I grows tomatoes.', 'reply', 0);
        $this->assertSame('I grow tomatoes.', $wrong['priority_correction']['correction_en']);
        $this->assertSame('How are your tomatoes growing?', $wrong['reply']['text']);
        $fixed = $this->turn($user, $context, $session, 'I grow tomatoes.', 'reply', 1);
        $this->assertSame('completed', $fixed['status']);
        $this->assertNull($fixed['priority_correction']);
        $this->assertSame('unassessed', $fixed['assessment']);
        $this->assertSame('How are your tomatoes growing?', $fixed['reply']['text']);
    }

    public function test_question_help_is_english_first_permission_only_then_yes_local_and_next_question_english(): void
    {
        [$user, $context] = $this->learner();
        $session = $this->start($user, $context, 'question_help');
        $question = $this->turn($user, $context, $session, 'ಭೂತಕಾಲ ಎಂದರೇನು?', 'question', 0);
        $this->assertSame('en-IN', $question['reply']['locale']);
        $this->assertNull($question['priority_correction']);
        $confused = $this->turn($user, $context, $session, 'I did not understand', 'question', 1);
        $this->assertSame('awaiting_explanation_permission', $confused['interaction']['state']);
        $this->assertSame('en-IN', $confused['reply']['locale']);
        $this->assertDoesNotMatchRegularExpression('/[\x{0C80}-\x{0CFF}]/u', $confused['reply']['text']);
        $local = $this->turn($user, $context, $session, 'ಹೌದು', 'question', 2);
        $this->assertSame('kn-IN', $local['reply']['locale']);
        $this->assertNull($local['assistant_reply']);
        $this->assertMatchesRegularExpression('/[\x{0C80}-\x{0CFF}]/u', $local['reply']['text']);
        $next = $this->turn($user, $context, $session, 'What is a verb?', 'question', 3);
        $this->assertSame('en-IN', $next['reply']['locale']);
        $this->assertSame('awaiting_question', $next['interaction']['state']);

        $enSession = $this->contexts()->run($context, fn () => app(ConversationSessionService::class)->start(
            $user, 'patient-friend-prototype', 'everyday-friend-prototype', 'gentle', 'en-IN', (string) Str::uuid(),
            interactionFlow: 'question_help', recoveryNoticeVersion: 'conversation-recovery-2026-09-25', recoveryNoticeAccepted: true,
        ));
        $this->turn($user, $context, $enSession, 'Can I go to sleep yesterday?', 'question', 0);
        $enLocal = $this->turn($user, $context, $enSession, 'Explain that in Kannada', 'question', 1);
        $this->assertSame('kn-IN', $enLocal['reply']['locale']);
        $this->assertMatchesRegularExpression('/[\x{0C80}-\x{0CFF}]/u', $enLocal['reply']['text']);
    }

    public function test_local_explanation_targets_the_latest_answer_not_the_first_question(): void
    {
        $provider = new class implements ConversationModelProvider
        {
            public ?ConversationModelRequest $lastRequest = null;

            public function respond(ConversationModelRequest $request): ConversationModelResult
            {
                $this->lastRequest = $request;

                return (new FakeDialogueResponder)->respond($request);
            }
        };
        $this->app->instance(ConversationModelProvider::class, $provider);
        [$user, $context] = $this->learner();
        $session = $this->start($user, $context, 'question_help');
        $this->turn($user, $context, $session, 'What does can mean?', 'question', 0);
        $latest = $this->turn($user, $context, $session, 'Make the door request shorter.', 'question', 1);
        $this->turn($user, $context, $session, 'I do not understand', 'confused', 2);
        $this->turn($user, $context, $session, 'Yes', 'accept_explanation', 3);
        $this->assertNotNull($provider->lastRequest);
        $this->assertSame('Make the door request shorter.', $provider->lastRequest->dialogue['explanation_target']['question']);
        $this->assertSame($latest['reply'], $provider->lastRequest->dialogue['explanation_target']['answer']);
        $this->turn($user, $context, $session, 'What is a verb?', 'question', 4);
        $this->assertNull($provider->lastRequest->dialogue['explanation_target']);
    }

    public function test_decline_does_not_explain_and_yes_without_offer_does_not_authorize_locale(): void
    {
        [$user, $context] = $this->learner();
        $session = $this->start($user, $context, 'question_help');
        $yes = $this->turn($user, $context, $session, 'yes', 'question', 0);
        $this->assertSame('en-IN', $yes['reply']['locale']);
        $this->turn($user, $context, $session, 'I do not understand', 'confused', 1);
        $no = $this->turn($user, $context, $session, 'No', 'decline_explanation', 2);
        $this->assertSame('en-IN', $no['reply']['locale']);
        $this->assertSame('awaiting_question', $no['interaction']['state']);
        $this->expectException(ConversationOperationRejected::class);
        $this->turn($user, $context, $session, 'Yes', 'accept_explanation', 3);
    }

    public function test_failed_explanation_preserves_permission_state_and_invalid_question_does_not_advance(): void
    {
        $this->app->instance(ConversationModelProvider::class, new class implements ConversationModelProvider
        {
            public function respond(ConversationModelRequest $request): ConversationModelResult
            {
                $result = (new FakeDialogueResponder)->respond($request);
                if (($request->dialogue['action'] ?? '') === 'accept_explanation' || $request->transcript === 'unsafe-fixture') {
                    return new ConversationModelResult('question_answer', '<script>unsafe</script>', $result->usage(), replyLocale: $result->replyLocale, dialogue: $result->dialogue);
                }

                return $result;
            }
        });
        [$user, $context] = $this->learner();
        $session = $this->start($user, $context, 'question_help');
        $invalid = $this->turn($user, $context, $session, 'unsafe-fixture', 'question', 0);
        $this->assertSame('awaiting_question', $invalid['interaction']['state']);
        $this->assertSame('unassessed', $invalid['assessment']);
        $this->assertNull($invalid['original_turn_id']);
        $this->turn($user, $context, $session, 'What is past tense?', 'question', 1);
        $offer = $this->turn($user, $context, $session, 'I did not understand', 'confused', 2);
        $failed = $this->turn($user, $context, $session, 'Yes', 'accept_explanation', 3);
        $this->assertSame('awaiting_explanation_permission', $failed['interaction']['state']);
        $this->assertSame('unassessed', $failed['assessment']);
        $this->assertSame($offer['original_turn_id'], $failed['original_turn_id']);
        $this->assertSame($offer['interaction']['opportunity'], $failed['interaction']['opportunity']);
        $this->assertSame('en-IN', $failed['reply']['locale']);
    }

    public function test_exact_replay_does_not_dispatch_charge_advance_or_refresh_expiry_and_changed_payload_conflicts(): void
    {
        [$user, $context] = $this->learner();
        $session = $this->start($user, $context);
        $key = (string) Str::uuid();
        $first = $this->turn($user, $context, $session, 'I has an appointment.', 'reply', 0, $key);
        $calls = DB::table('ai_cost_ledger')->count();
        $charges = DB::table('live_ai_meter_charges')->count();
        $this->travel(5)->minutes();
        $again = $this->turn($user, $context, $session, 'I has an appointment.', 'reply', 0, $key);
        $this->assertTrue($again['replayed']);
        unset($first['replayed'], $again['replayed']);
        $this->assertSame($first, $again);
        $this->assertSame($calls, DB::table('ai_cost_ledger')->count());
        $this->assertSame($charges, DB::table('live_ai_meter_charges')->count());
        $this->expectException(ConversationIdempotencyConflict::class);
        $this->turn($user, $context, $session, 'Changed', 'reply', 0, $key);
    }

    public function test_interrupted_provider_transaction_cannot_redispatch_or_double_charge(): void
    {
        $provider = new class implements ConversationModelProvider
        {
            public int $calls = 0;

            public function respond(ConversationModelRequest $request): ConversationModelResult
            {
                $this->calls++;
                throw new \RuntimeException('Synthetic interruption after dispatch.');
            }
        };
        $this->app->instance(ConversationModelProvider::class, $provider);
        [$user, $context] = $this->learner();
        $session = $this->start($user, $context);
        $key = (string) Str::uuid();
        $chargesBefore = DB::table('live_ai_meter_charges')->count();
        try {
            $this->turn($user, $context, $session, 'Hello.', 'reply', 0, $key);
            $this->fail('The simulated interrupted dispatch must not complete.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic interruption after dispatch.', $exception->getMessage());
        }
        $this->assertSame(0, DB::table('conversation_turns')->count());
        $this->assertSame($chargesBefore, DB::table('live_ai_meter_charges')->count());
        foreach ([$key, (string) Str::uuid()] as $retryKey) {
            try {
                $this->turn($user, $context, $session, 'Hello.', 'reply', 0, $retryKey);
                $this->fail('An unknown reserved opportunity must fail closed.');
            } catch (DialogueTurnOutcomeUnknown) {
                $this->assertSame(1, $provider->calls);
            }
        }
        $this->assertSame($chargesBefore, DB::table('live_ai_meter_charges')->count());
        $this->assertSame(0, DB::table('ai_cost_ledger')->count());
    }

    public function test_capacity_rejection_preserves_the_turn_and_exact_replay_uses_no_new_slot(): void
    {
        config()->set('conversation.capacity', ['enabled' => true, 'store' => 'array', 'model_rpm' => 3,
            'model_tpm' => 3000, 'reserved_tokens_per_turn' => 2000, 'learner_rpm' => 6]);
        Cache::store('array')->flush();
        [$user, $context] = $this->learner();
        $session = $this->start($user, $context);
        $key = (string) Str::uuid();
        $first = $this->turn($user, $context, $session, 'I have an appointment.', 'reply', 0, $key);
        self::assertSame($first['id'], $this->turn($user, $context, $session, 'I have an appointment.', 'reply', 0, $key)['id']);
        $reservations = DB::table('conversation_dialogue_turn_reservations')->count();
        try {
            $this->turn($user, $context, $session, 'My name is Alex.', 'reply', 1);
            self::fail('The turn exceeded deployment capacity.');
        } catch (ConversationCapacityUnavailable $exception) {
            self::assertSame(429, $exception->getStatusCode());
            self::assertSame($reservations, DB::table('conversation_dialogue_turn_reservations')->count());
            self::assertSame(0, DB::table('live_ai_meter_charges')->count());
        }
    }

    public function test_exhausted_quota_rejects_before_durable_turn_reservation(): void
    {
        [$user, $context] = $this->learner();
        $session = $this->start($user, $context);
        $snapshot = $this->contexts()->run($context, fn () => app(EntitlementAccess::class)->snapshot($user));
        $this->assertIsInt($snapshot['live_ai_cap_seconds']);
        DB::table('learner_entitlements')->where('id', $snapshot['entitlement_id'])->update(['live_ai_seconds_used' => $snapshot['live_ai_cap_seconds']]);
        try {
            $this->turn($user, $context, $session, 'Hello.', 'reply', 0);
            $this->fail('Exhausted quota must deny admission.');
        } catch (EntitlementDenied) {
            $this->assertSame(0, DB::table('conversation_dialogue_turn_reservations')->count());
            $this->assertSame(0, DB::table('conversation_turns')->count());
        }
    }

    public function test_expired_replay_never_dispatches_and_closed_session_cannot_create_new_artifact(): void
    {
        [$user, $context] = $this->learner();
        $session = $this->start($user, $context);
        $key = (string) Str::uuid();
        $this->turn($user, $context, $session, 'Hello.', 'reply', 0, $key);
        $calls = DB::table('ai_cost_ledger')->count();
        $this->travel(16)->minutes();
        $read = $this->contexts()->run($context, fn () => app(ConversationSessionService::class)->show($user, $session['id']));
        $this->assertSame('expired', $read['interaction']['state']);
        $this->assertNull($read['latest_turn']);
        try {
            $this->turn($user, $context, $session, 'Hello.', 'reply', 0, $key);
            $this->fail('Expired body must not be regenerated.');
        } catch (ConversationOperationRejected) {
            $this->assertSame($calls, DB::table('ai_cost_ledger')->count());
        }
        $this->contexts()->run($context, fn () => app(ConversationSessionService::class)->exit($user, $session['id'], (string) Str::uuid()));
        $this->expectException(ConversationOperationRejected::class);
        $this->turn($user, $context, $session, 'Hello.', 'reply', 1);
    }

    public function test_stale_turn_and_missing_notice_are_rejected_without_provider_dispatch(): void
    {
        [$user, $context] = $this->learner();
        $session = $this->start($user, $context);
        $this->turn($user, $context, $session, 'Hello.', 'reply', 0);
        $calls = DB::table('ai_cost_ledger')->count();
        try {
            $this->turn($user, $context, $session, 'Hello again.', 'reply', 0);
            $this->fail('A concurrent stale command must fail.');
        } catch (ConversationIdempotencyConflict) {
            $this->assertSame($calls, DB::table('ai_cost_ledger')->count());
        }
        $this->expectException(ConversationOperationRejected::class);
        $this->contexts()->run($context, fn () => app(ConversationSessionService::class)->start($user, 'patient-friend-prototype', 'everyday-friend-prototype', 'gentle', 'kn-IN', (string) Str::uuid(), interactionFlow: 'question_help'));
    }

    public function test_other_learner_cannot_read_exit_or_submit_owner_dialogue(): void
    {
        [$owner, $context] = $this->learner();
        [$other, $otherContext] = $this->learner();
        $session = $this->start($owner, $context);
        foreach (['show', 'exit', 'turn'] as $operation) {
            try {
                $this->contexts()->run($otherContext, fn () => match ($operation) {
                    'show' => app(ConversationSessionService::class)->show($other, $session['id']),
                    'exit' => app(ConversationSessionService::class)->exit($other, $session['id'], (string) Str::uuid()),
                    default => app(ConversationTurnService::class)->create($other, $session['id'], 'Hello', (string) Str::uuid(), ['action' => 'reply', 'expected_revision' => 0]),
                });
                $this->fail('Other learner was allowed.');
            } catch (ConversationOperationRejected) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_database_rejects_outcome_pointing_at_another_learners_turn(): void
    {
        [$owner, $context] = $this->learner();
        [$other, $otherContext] = $this->learner();
        $session = $this->start($owner, $context);
        $turn = $this->turn($owner, $context, $session, 'Hello.', 'reply', 0);
        $this->expectException(QueryException::class);
        DB::table('conversation_dialogue_outcomes')->where('conversation_turn_id', $turn['id'])->update(['learner_membership_id' => $otherContext->membershipId]);
    }

    public function test_recovery_notice_never_replaces_provider_consent(): void
    {
        [$user, $context] = $this->learner(false);
        $session = $this->start($user, $context);
        $this->expectException(VoiceOperationRejected::class);
        try {
            $this->turn($user, $context, $session, 'Hello.', 'reply', 0);
        } finally {
            $this->assertSame(0, DB::table('ai_cost_ledger')->count());
        }
    }

    public function test_withdrawal_closes_dialogue_and_cannot_replay_private_body(): void
    {
        [$user, $context] = $this->learner();
        $session = $this->start($user, $context);
        $key = (string) Str::uuid();
        $this->turn($user, $context, $session, 'Hello.', 'reply', 0, $key);
        $this->contexts()->run($context, fn () => app(VoiceConsentService::class)->withdraw($user, VoiceConsentService::PROVIDER, 'en-IN', (string) Str::uuid()));
        $this->expectException(VoiceOperationRejected::class);
        $this->turn($user, $context, $session, 'Hello.', 'reply', 0, $key);
    }

    /** @return array{User, OrganizationContext} */
    private function learner(bool $grantProvider = true): array
    {
        $organization = Organization::query()->where('system_key', Organization::DIRECT_SYSTEM_KEY)->firstOrFail();
        $user = $this->createUser();
        [$membership, $context] = $this->createMembership($organization, $user);
        $role = $this->createRole($context, 'dialogue-'.strtolower((string) Str::ulid()));
        $permission = Permission::query()->where('key', 'learning.participate')->firstOrFail();
        DB::table('membership_roles')->insert(['organization_id' => $organization->id, 'membership_id' => $membership->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_permissions')->insert(['organization_id' => $organization->id, 'role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('learner_profiles')->insert(['id' => (string) Str::ulid(), 'organization_id' => $organization->id, 'learner_membership_id' => $membership->id, 'adult_confirmed_at' => now(), 'onboarding_completed_at' => now(), 'created_at' => now()]);
        $this->contexts()->run($context, function () use ($user, $organization): void {
            app(EntitlementAccess::class)->snapshot($user);
            $plan = DB::table('entitlement_plans')->where('organization_id', $organization->id)->where('code', DirectPlanDefaults::MONTHLY_ROLEPLAY)->value('id');
            $this->assertIsString($plan);
            app(LearnerSubscriptionService::class)->checkout($user, $plan, (string) Str::ulid());
        });
        if ($grantProvider) {
            $this->contexts()->run($context, fn () => app(VoiceConsentService::class)->grant($user, VoiceConsentService::PROVIDER, 'en-IN', (string) Str::uuid()));
        }

        return [$user, $context];
    }

    /** @return array<string, mixed> */
    private function start(User $user, OrganizationContext $context, string $flow = 'roleplay_dialogue'): array
    {
        return $this->contexts()->run($context, fn () => app(ConversationSessionService::class)->start($user,
            $flow === 'roleplay_dialogue' ? 'clinic-reception-roleplay-prototype' : 'patient-friend-prototype',
            $flow === 'roleplay_dialogue' ? 'clinic-reception-roleplay-scenario' : 'everyday-friend-prototype',
            'gentle', 'kn-IN', (string) Str::uuid(), interactionFlow: $flow, recoveryNoticeVersion: ConversationDialogueService::NOTICE, recoveryNoticeAccepted: true));
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    private function turn(User $user, OrganizationContext $context, array $session, string $text, string $action, int $revision, ?string $key = null): array
    {
        return $this->contexts()->run($context, fn () => app(ConversationTurnService::class)->create($user, $session['id'], $text, $key ?? (string) Str::uuid(), ['action' => $action, 'expected_revision' => $revision]));
    }
}

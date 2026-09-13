<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Conversation\ConversationEventService;
use App\Conversation\ConversationSessionService;
use App\Conversation\ConversationTurnService;
use App\Conversation\Exceptions\ConversationIdempotencyConflict;
use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\GuidanceSpeechService;
use App\Conversation\Providers\ConversationModelProvider;
use App\Conversation\Providers\ConversationModelRequest;
use App\Conversation\Providers\ConversationModelResult;
use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\Fake\DeterministicFakeSpeechToTextProvider;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Providers\SpeechToTextProvider;
use App\Conversation\Providers\SpeechToTextRequest;
use App\Conversation\Providers\SpeechToTextResult;
use App\Conversation\Providers\TextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechResult;
use App\Conversation\Speech\ConversationSpeechArtifactStore;
use App\Conversation\Speech\ConversationSpeechStreamer;
use App\Entitlements\DirectPlanDefaults;
use App\Entitlements\EntitlementAccess;
use App\Entitlements\LearnerSubscriptionService;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ConversationFakeProviderTest extends TenancyIntegrationTestCase
{
    public function test_skill_focus_and_review_use_distinct_friend_scenario_snapshots(): void
    {
        [$learner, , $context] = $this->learner();
        $sessions = $this->app->make(ConversationSessionService::class);

        $skillFocus = $this->contexts()->run(
            $context,
            fn (): array => $sessions->start(
                learner: $learner,
                personaKey: 'patient-friend-prototype',
                scenarioKey: 'past-tense-skill-focus-prototype',
                coachingMode: 'tutor',
                uiLocale: 'en-IN',
                idempotencyKey: (string) Str::uuid(),
            ),
        );
        $review = $this->contexts()->run(
            $context,
            fn (): array => $sessions->start(
                learner: $learner,
                personaKey: 'patient-friend-prototype',
                scenarioKey: 'carry-sentence-review-prototype',
                coachingMode: 'gentle',
                uiLocale: 'en-IN',
                idempotencyKey: (string) Str::uuid(),
            ),
        );

        $this->assertSame('friend', $skillFocus['mode']);
        $this->assertSame('tutor', $skillFocus['coaching_mode']);
        $this->assertSame(
            'past-tense-skill-focus-prototype',
            $skillFocus['scenario']['key'],
        );
        $this->assertSame(
            'grammar-past-simple',
            $skillFocus['scenario']['topic_code'],
        );
        $this->assertSame('friend', $review['mode']);
        $this->assertSame('gentle', $review['coaching_mode']);
        $this->assertSame(
            'carry-sentence-review-prototype',
            $review['scenario']['key'],
        );
        $this->assertSame(
            'carry-sentence-review',
            $review['scenario']['topic_code'],
        );
        $this->assertNotSame(
            $skillFocus['scenario']['definition_hash'],
            $review['scenario']['definition_hash'],
        );
    }

    public function test_soft_launch_composes_confirmed_text_live_model_and_text_first_playback(): void
    {
        try {
            $this->app->detectEnvironment(static fn (): string => 'soft-launch');
            config()->set('conversation.provider_driver', 'azure-openai-dev');
            config()->set('conversation.guidance_tts.live_requests_enabled', false);
            (new AppServiceProvider($this->app))->register();
            $this->app->instance(
                ConversationModelProvider::class,
                new SoftLaunchAzureModelFixtureProvider,
            );
            [$learner, , $context] = $this->learner();
            $sessions = $this->app->make(ConversationSessionService::class);
            $turns = $this->app->make(ConversationTurnService::class);
            $session = $this->contexts()->run(
                $context,
                fn (): array => $sessions->start(
                    learner: $learner,
                    personaKey: 'patient-friend-prototype',
                    scenarioKey: 'everyday-friend-prototype',
                    coachingMode: 'gentle',
                    uiLocale: 'kn-IN',
                    idempotencyKey: (string) Str::uuid(),
                ),
            );

            $turn = $this->contexts()->run(
                $context,
                fn (): array => $turns->create(
                    learner: $learner,
                    sessionId: (string) $session['id'],
                    prototypeUtterance: '  I spoke after work.  ',
                    idempotencyKey: (string) Str::uuid(),
                ),
            );

            $this->assertSame('completed', $turn['status']);
            $this->assertSame('I spoke after work.', $turn['transcript']);
            $this->assertSame('A live-model fixture reply.', $turn['assistant_reply']);
            $this->assertFalse($turn['speech']['playback_available']);
            $this->assertSame(
                'client-text-to-speech-fallback',
                $turn['speech']['rendering_mode'],
            );
            $this->assertSame(25, $turn['usage']['estimated_cost_microunits']);
            $this->assertSame(
                [
                    'project-confirmed-text',
                    'azure-openai',
                    'project-text-first',
                ],
                DB::table('ai_cost_ledger')
                    ->orderBy('recorded_at')
                    ->orderByRaw(
                        "case provider_stage when 'stt' then 1 when 'model' then 2 when 'tts' then 3 else 4 end",
                    )
                    ->pluck('provider_key')
                    ->all(),
            );
        } finally {
            $this->app->detectEnvironment(static fn (): string => 'testing');
            config()->set('conversation.provider_driver', 'fake');
            config()->set('conversation.guidance_tts.live_requests_enabled', false);
            config()->set(
                'conversation.azure_openai.live_requests_enabled',
                false,
            );
            foreach ([
                ConversationModelProvider::class,
                SpeechToTextProvider::class,
                TextToSpeechProvider::class,
            ] as $abstract) {
                $this->app->forgetInstance($abstract);
            }
            (new AppServiceProvider($this->app))->register();
        }
    }

    public function test_friend_turn_is_tenant_scoped_idempotent_and_content_minimized(): void
    {
        [$learner, $membership, $context] = $this->learner();
        $sessions = $this->app->make(ConversationSessionService::class);
        $turns = $this->app->make(ConversationTurnService::class);
        $events = $this->app->make(ConversationEventService::class);
        $sessionCommand = (string) Str::uuid();
        $turnCommand = (string) Str::uuid();
        $utterance = '  I enjoy learning English after work.  ';
        $normalizedUtterance = trim($utterance);
        $reply = 'Thank you for sharing that. What would you like to talk about next?';
        $learningBefore = $this->learningCounts();

        $session = $this->contexts()->run(
            $context,
            fn (): array => $sessions->start(
                learner: $learner,
                personaKey: 'patient-friend-prototype',
                scenarioKey: 'everyday-friend-prototype',
                coachingMode: 'gentle',
                uiLocale: 'kn-IN',
                idempotencyKey: $sessionCommand,
            ),
        );
        $sessionReplay = $this->contexts()->run(
            $context,
            fn (): array => $sessions->start(
                learner: $learner,
                personaKey: 'patient-friend-prototype',
                scenarioKey: 'everyday-friend-prototype',
                coachingMode: 'gentle',
                uiLocale: 'kn-IN',
                idempotencyKey: $sessionCommand,
            ),
        );

        $this->assertFalse($session['replayed']);
        $this->assertTrue($sessionReplay['replayed']);
        $this->assertSame($session['id'], $sessionReplay['id']);
        $this->assertSame('friend', $session['mode']);
        $this->assertSame('synthetic_unreviewed', $session['persona']['review_status']);
        $this->assertSame(
            'I am a deterministic AI prototype, not a human.',
            $session['persona']['disclosure'],
        );

        try {
            $this->contexts()->run(
                $context,
                fn (): array => $sessions->start(
                    learner: $learner,
                    personaKey: 'clinic-reception-roleplay-prototype',
                    scenarioKey: 'clinic-reception-roleplay-scenario',
                    coachingMode: 'gentle',
                    uiLocale: 'kn-IN',
                    idempotencyKey: $sessionCommand,
                ),
            );
            self::fail('Expected changed session replay to conflict.');
        } catch (ConversationIdempotencyConflict) {
            $this->addToAssertionCount(1);
        }

        $turn = $this->contexts()->run(
            $context,
            fn (): array => $turns->create(
                learner: $learner,
                sessionId: (string) $session['id'],
                prototypeUtterance: $utterance,
                idempotencyKey: $turnCommand,
            ),
        );
        $turnReplay = $this->contexts()->run(
            $context,
            fn (): array => $turns->create(
                learner: $learner,
                sessionId: (string) $session['id'],
                prototypeUtterance: $utterance,
                idempotencyKey: $turnCommand,
            ),
        );

        $this->assertSame('completed', $turn['status']);
        $this->assertSame($normalizedUtterance, $turn['transcript']);
        $this->assertSame($reply, $turn['assistant_reply']);
        $this->assertSame('prototype_utterance_received', $turn['understood_intent']);
        $this->assertSame('ok', $turn['safety_state']);
        $this->assertIsArray($turn['priority_correction']);
        $this->assertSame(
            'grammar.article',
            $turn['priority_correction']['error_category'],
        );
        $this->assertNotSame(
            $turn['assistant_reply'],
            $turn['priority_correction']['correction_en'],
        );
        $this->assertFalse($turn['speech']['playback_available']);
        $this->assertSame(
            'deterministic-fake-text-only',
            $turn['speech']['rendering_mode'],
        );
        $this->assertSame(3, $turn['usage']['provider_attempts']);
        $this->assertSame('deferred', $turn['speech']['guidance_speech_status']);
        $this->assertNull($turn['speech']['model_audio_base64']);
        $this->assertNull($turn['speech']['guidance_audio_base64']);
        $this->assertSame(0, $turn['usage']['estimated_cost_microunits']);
        $this->assertTrue($turnReplay['replayed']);
        $this->assertSame($turn['id'], $turnReplay['id']);
        $this->assertDatabaseHas('meaningful_speaking_days', [
            'organization_id' => $context->organizationId,
            'learner_membership_id' => $membership->getKey(),
            'source_turn_id' => $turn['id'],
            'timezone' => 'Asia/Kolkata',
        ]);
        $this->assertSame(
            1,
            DB::table('meaningful_speaking_days')
                ->where('organization_id', $context->organizationId)
                ->where('learner_membership_id', $membership->getKey())
                ->count(),
        );

        // ADR-0028: a speaking day is organization-scoped. Pairing this
        // organization with a membership owned by another one must be refused
        // by the composite foreign key, not merely absent by convention.
        if (DB::connection()->getDriverName() === 'pgsql') {
            $foreignOrganization = $this->createOrganization('Foreign Institution');
            [$foreignMembership] = $this->createMembership(
                $foreignOrganization,
                $this->createUser(),
            );
            $foreignMembershipId = (string) $foreignMembership->getKey();
            $crossTenantRow = [
                'organization_id' => $context->organizationId,
                'learner_membership_id' => $foreignMembershipId,
                'speaking_date' => '2026-07-31',
                'timezone' => 'Asia/Kolkata',
                'source_turn_id' => $turn['id'],
                'created_at' => now(),
            ];

            try {
                // A nested transaction is a savepoint, so the expected
                // constraint failure does not abort the test transaction.
                DB::transaction(static function () use ($crossTenantRow): void {
                    DB::table('meaningful_speaking_days')->insert([
                        'id' => (string) Str::ulid(),
                        ...$crossTenantRow,
                    ]);
                });
                self::fail(
                    'A speaking day must not pair one organization with another organization\'s membership.',
                );
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }

            $this->assertSame(
                0,
                DB::table('meaningful_speaking_days')
                    ->where('learner_membership_id', $foreignMembershipId)
                    ->count(),
            );
        }
        $this->assertSame($turn['assistant_reply'], $turnReplay['assistant_reply']);
        $this->assertSame(
            $turn['priority_correction'],
            $turnReplay['priority_correction'],
        );
        $this->assertSame(1, DB::table('conversation_sessions')->count());
        $this->assertSame(1, DB::table('conversation_turns')->count());
        $this->assertSame(5, DB::table('conversation_turn_events')->count());
        $this->assertSame(3, DB::table('ai_cost_ledger')->count());
        $this->assertSame(2, DB::table('conversation_commands')->count());
        $this->assertSame(1, DB::table('correction_suggestions')->count());
        $this->assertSame(1, DB::table('learner_error_patterns')->count());
        $this->assertSame($learningBefore, $this->learningCounts());

        $eventRows = $this->contexts()->run(
            $context,
            fn (): array => $events->forTurn(
                learner: $learner,
                turnId: (string) $turn['id'],
            ),
        );
        $this->assertSame(
            [
                'accepted',
                'transcribing',
                'generating_reply',
                'synthesizing',
                'completed',
            ],
            array_column($eventRows, 'state'),
        );

        $stored = json_encode([
            'turn' => DB::table('conversation_turns')->get()->all(),
            'events' => DB::table('conversation_turn_events')->get()->all(),
            'usage' => DB::table('ai_cost_ledger')->get()->all(),
            'commands' => DB::table('conversation_commands')->get()->all(),
            'audit' => DB::table('audit_events')->get()->all(),
            'outbox' => DB::table('outbox_events')->get()->all(),
        ], JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString(
            $normalizedUtterance,
            $stored,
        );
        self::assertStringNotContainsString($reply, $stored);
        self::assertStringNotContainsString('audio/', $stored);
        $this->assertSame(
            hash('sha256', $normalizedUtterance),
            DB::table('conversation_turns')->value('utterance_digest'),
        );

        [$otherLearner, , $otherContext] = $this->learner(
            organization: Organization::query()->findOrFail(
                $membership->organizationId(),
            ),
        );

        try {
            $this->contexts()->run(
                $otherContext,
                fn (): array => $events->forTurn(
                    learner: $otherLearner,
                    turnId: (string) $turn['id'],
                ),
            );
            self::fail('Expected same-tenant other-learner access to fail.');
        } catch (ConversationOperationRejected) {
            $this->addToAssertionCount(1);
        }

        $otherOrganization = $this->createOrganization('Other tenant');
        [$otherTenantLearner, , $otherTenantContext] = $this->learner(
            organization: $otherOrganization,
        );

        try {
            $this->contexts()->run(
                $otherTenantContext,
                fn (): array => $events->forTurn(
                    learner: $otherTenantLearner,
                    turnId: (string) $turn['id'],
                ),
            );
            self::fail('Expected cross-tenant event access to fail.');
        } catch (ConversationOperationRejected) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_fail_once_provider_retries_once_and_preserves_state_order(): void
    {
        [$learner, , $context] = $this->learner();
        $this->app->instance(
            SpeechToTextProvider::class,
            new FailOnceSpeechToTextProvider,
        );
        $sessions = $this->app->make(ConversationSessionService::class);
        $turns = $this->app->make(ConversationTurnService::class);
        $session = $this->contexts()->run(
            $context,
            fn (): array => $sessions->start(
                learner: $learner,
                personaKey: 'patient-friend-prototype',
                scenarioKey: 'everyday-friend-prototype',
                coachingMode: 'gentle',
                uiLocale: 'kn-IN',
                idempotencyKey: (string) Str::uuid(),
            ),
        );
        $turn = $this->contexts()->run(
            $context,
            fn (): array => $turns->create(
                learner: $learner,
                sessionId: (string) $session['id'],
                prototypeUtterance: 'I like morning walks.',
                idempotencyKey: (string) Str::uuid(),
            ),
        );

        $this->assertSame('completed', $turn['status']);
        $this->assertSame(4, $turn['usage']['provider_attempts']);
        $this->assertSame(
            [
                'turn.accepted',
                'turn.transcribing',
                'turn.provider_retrying',
                'turn.generating_reply',
                'turn.synthesizing',
                'turn.completed',
            ],
            DB::table('conversation_turn_events')
                ->orderBy('sequence')
                ->pluck('event_code')
                ->all(),
        );
        $this->assertSame(
            ['failed', 'succeeded'],
            DB::table('ai_cost_ledger')
                ->where('provider_stage', 'stt')
                ->orderBy('provider_attempt')
                ->pluck('result_status')
                ->all(),
        );
    }

    public function test_exhausted_provider_records_one_failed_turn_without_learning_writes(): void
    {
        [$learner, , $context] = $this->learner();
        $this->app->instance(
            SpeechToTextProvider::class,
            new AlwaysFailSpeechToTextProvider,
        );
        $sessions = $this->app->make(ConversationSessionService::class);
        $turns = $this->app->make(ConversationTurnService::class);
        $session = $this->contexts()->run(
            $context,
            fn (): array => $sessions->start(
                learner: $learner,
                personaKey: 'patient-friend-prototype',
                scenarioKey: 'everyday-friend-prototype',
                coachingMode: 'gentle',
                uiLocale: 'kn-IN',
                idempotencyKey: (string) Str::uuid(),
            ),
        );
        $learningBefore = $this->learningCounts();
        $turn = $this->contexts()->run(
            $context,
            fn (): array => $turns->create(
                learner: $learner,
                sessionId: (string) $session['id'],
                prototypeUtterance: 'This retry must fail safely.',
                idempotencyKey: (string) Str::uuid(),
            ),
        );

        $this->assertSame('failed', $turn['status']);
        $this->assertNull($turn['transcript']);
        $this->assertNull($turn['assistant_reply']);
        $this->assertNull($turn['priority_correction']);
        $this->assertNull($turn['safety_state']);
        $this->assertSame(2, $turn['usage']['provider_attempts']);
        $this->assertSame(
            ['accepted', 'transcribing', 'transcribing', 'failed'],
            DB::table('conversation_turn_events')
                ->orderBy('sequence')
                ->pluck('state')
                ->all(),
        );
        $this->assertSame('stt', DB::table('conversation_turns')->value('failed_stage'));
        $this->assertSame(
            'synthetic_failure',
            DB::table('conversation_turns')->value('failure_code'),
        );
        $this->assertSame($learningBefore, $this->learningCounts());
        $this->assertDatabaseCount('meaningful_speaking_days', 0);
    }

    public function test_postgresql_rejects_cross_tenant_session_snapshot(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL constraint verification.');
        }

        [$learner, $membership, $context] = $this->learner();
        $session = $this->contexts()->run(
            $context,
            fn (): array => $this->app
                ->make(ConversationSessionService::class)
                ->start(
                    learner: $learner,
                    personaKey: 'patient-friend-prototype',
                    scenarioKey: 'everyday-friend-prototype',
                    coachingMode: 'gentle',
                    uiLocale: 'kn-IN',
                    idempotencyKey: (string) Str::uuid(),
                ),
        );
        $other = $this->createOrganization('Constraint tenant');

        try {
            DB::table('conversation_turns')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $other->getKey(),
                'conversation_session_id' => $session['id'],
                'learner_membership_id' => $membership->getKey(),
                'turn_number' => 1,
                'status' => 'accepted',
                'utterance_digest' => str_repeat('a', 64),
                'transcript_digest' => null,
                'assistant_reply_digest' => null,
                'failed_stage' => null,
                'failure_code' => null,
                'accepted_at' => now(),
                'completed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            self::fail('Expected the cross-tenant session foreign key to reject.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_session_speech_is_org_scoped_and_text_first_without_audio(): void
    {
        [$learner, , $context] = $this->learner();
        $sessions = $this->app->make(ConversationSessionService::class);
        $speech = $this->app->make(GuidanceSpeechService::class);
        $session = $this->contexts()->run(
            $context,
            fn (): array => $sessions->start(
                learner: $learner,
                personaKey: 'patient-friend-prototype',
                scenarioKey: 'everyday-friend-prototype',
                coachingMode: 'gentle',
                uiLocale: 'kn-IN',
                idempotencyKey: (string) Str::uuid(),
            ),
        );

        $result = $this->contexts()->run(
            $context,
            fn (): array => $speech->synthesize(
                learner: $learner,
                sessionId: (string) $session['id'],
                text: 'I went to the market this morning.',
                purpose: 'model_en',
            ),
        );

        $this->assertFalse($result['playback_available']);
        $this->assertSame(
            'deterministic-fake-text-only',
            $result['rendering_mode'],
        );
        $this->assertNull($result['model_audio_base64']);
        $this->assertNull($result['guidance_audio_base64']);

        $otherOrganization = $this->createOrganization('Other speech tenant');
        [$otherLearner, , $otherContext] = $this->learner(
            organization: $otherOrganization,
        );

        try {
            $this->contexts()->run(
                $otherContext,
                fn (): array => $speech->synthesize(
                    learner: $otherLearner,
                    sessionId: (string) $session['id'],
                    text: 'I went to the market this morning.',
                    purpose: 'model_en',
                ),
            );
            self::fail('Expected cross-tenant session speech to fail.');
        } catch (ConversationOperationRejected) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_private_speech_stream_is_owner_only_and_no_store(): void
    {
        [$learner, $membership, $context] = $this->learner();
        $sessions = $this->app->make(ConversationSessionService::class);
        $turns = $this->app->make(ConversationTurnService::class);
        $store = $this->app->make(ConversationSpeechArtifactStore::class);
        $streamer = $this->app->make(ConversationSpeechStreamer::class);

        $session = $this->contexts()->run(
            $context,
            fn (): array => $sessions->start(
                learner: $learner,
                personaKey: 'patient-friend-prototype',
                scenarioKey: 'everyday-friend-prototype',
                coachingMode: 'gentle',
                uiLocale: 'kn-IN',
                idempotencyKey: (string) Str::uuid(),
            ),
        );
        $turn = $this->contexts()->run(
            $context,
            fn (): array => $turns->create(
                learner: $learner,
                sessionId: (string) $session['id'],
                prototypeUtterance: 'I practise English after work.',
                idempotencyKey: (string) Str::uuid(),
            ),
        );

        $wav = self::minimalWav();
        $store->persist(
            $context->organizationId,
            (string) $membership->getKey(),
            (string) $turn['id'],
            ConversationSpeechArtifactStore::KIND_MODEL,
            new TextToSpeechResult(
                playbackAvailable: true,
                renderingMode: 'azure-soft-launch-guidance-wav',
                providerUsage: new ProviderUsage(
                    providerKey: 'test-speech',
                    modelVersion: 'test',
                    latencyMilliseconds: 1,
                    inputUnits: 1,
                    outputUnits: 1,
                    estimatedCostMicrounits: 0,
                ),
                audioBytes: $wav,
                mimeType: 'audio/wav',
            ),
        );

        $response = $this->contexts()->run(
            $context,
            fn (): StreamedResponse => $streamer->stream(
                $learner,
                (string) $turn['id'],
                ConversationSpeechArtifactStore::KIND_MODEL,
            ),
        );
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('audio/wav', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
        $this->assertStringContainsString(
            'private',
            (string) $response->headers->get('Cache-Control'),
        );

        [$sameOrgOther, , $sameOrgOtherContext] = $this->learner(
            organization: Organization::query()->findOrFail(
                $membership->organizationId(),
            ),
        );
        try {
            $this->contexts()->run(
                $sameOrgOtherContext,
                fn (): StreamedResponse => $streamer->stream(
                    $sameOrgOther,
                    (string) $turn['id'],
                    ConversationSpeechArtifactStore::KIND_MODEL,
                ),
            );
            self::fail('Expected same-tenant other-learner speech stream to fail.');
        } catch (ConversationOperationRejected) {
            $this->addToAssertionCount(1);
        }

        $otherOrganization = $this->createOrganization('Speech stream foreign tenant');
        [$otherLearner, , $otherContext] = $this->learner(
            organization: $otherOrganization,
        );
        try {
            $this->contexts()->run(
                $otherContext,
                fn (): StreamedResponse => $streamer->stream(
                    $otherLearner,
                    (string) $turn['id'],
                    ConversationSpeechArtifactStore::KIND_MODEL,
                ),
            );
            self::fail('Expected cross-tenant speech stream to fail.');
        } catch (ConversationOperationRejected) {
            $this->addToAssertionCount(1);
        }
    }

    private static function minimalWav(): string
    {
        $sampleRate = 16_000;
        $dataSize = 320;
        $format = 'fmt '
            .pack('VvvVVvv', 16, 1, 1, $sampleRate, $sampleRate * 2, 2, 16);
        $data = 'data'.pack('V', $dataSize).str_repeat("\0", $dataSize);
        $body = 'WAVE'.$format.$data;

        return 'RIFF'.pack('V', strlen($body)).$body;
    }

    /**
     * @return array{User, Membership, OrganizationContext}
     */
    private function learner(
        ?Organization $organization = null,
    ): array {
        $organization ??= Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->firstOrFail();
        $user = $this->createUser();
        [$membership, $context] = $this->createMembership(
            $organization,
            $user,
        );
        $role = $this->createRole(
            $context,
            'conversation-'.strtolower((string) Str::ulid()),
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

        if ($organization->system_key === Organization::DIRECT_SYSTEM_KEY) {
            $this->contexts()->run($context, function () use ($user, $organization): void {
                $this->app->make(EntitlementAccess::class)->snapshot($user);
                $planId = DB::table('entitlement_plans')
                    ->where('organization_id', $organization->getKey())
                    ->where('code', DirectPlanDefaults::MONTHLY_ROLEPLAY)
                    ->value('id');
                $this->assertIsString($planId);
                $this->app->make(LearnerSubscriptionService::class)->checkout(
                    $user,
                    $planId,
                    (string) Str::ulid(),
                );
            });
        }

        return [$user, $membership, $context];
    }

    public function test_session_token_budget_ignores_tts_audio_units(): void
    {
        [$learner, $membership, $context] = $this->learner();
        $sessions = $this->app->make(ConversationSessionService::class);
        $turns = $this->app->make(ConversationTurnService::class);

        $session = $this->contexts()->run(
            $context,
            fn (): array => $sessions->start(
                learner: $learner,
                personaKey: 'patient-friend-prototype',
                scenarioKey: 'everyday-friend-prototype',
                coachingMode: 'gentle',
                uiLocale: 'kn-IN',
                idempotencyKey: (string) Str::uuid(),
            ),
        );

        $firstTurn = $this->contexts()->run(
            $context,
            fn (): array => $turns->create(
                learner: $learner,
                sessionId: (string) $session['id'],
                prototypeUtterance: 'I can drink water.',
                idempotencyKey: (string) Str::uuid(),
            ),
        );

        $this->assertSame('completed', $firstTurn['status']);

        $ttsRow = DB::table('ai_cost_ledger')
            ->where('conversation_turn_id', $firstTurn['id'])
            ->where('provider_stage', 'tts')
            ->first();
        self::assertNotNull($ttsRow);
        DB::table('ai_cost_ledger')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $ttsRow->organization_id,
            'conversation_turn_id' => $firstTurn['id'],
            'conversation_session_id' => $ttsRow->conversation_session_id,
            'learner_membership_id' => $ttsRow->learner_membership_id,
            'provider_stage' => 'tts',
            'provider_key' => 'deterministic-fake',
            'model_version' => 'fake-tts-v1',
            'provider_attempt' => 2,
            'latency_ms' => 0,
            'input_units' => 0,
            'output_units' => 200_000,
            'estimated_cost_microunits' => 0,
            'result_status' => 'succeeded',
            'recorded_at' => now(),
        ]);

        $secondTurn = $this->contexts()->run(
            $context,
            fn (): array => $turns->create(
                learner: $learner,
                sessionId: (string) $session['id'],
                prototypeUtterance: 'We cannot drink water.',
                idempotencyKey: (string) Str::uuid(),
            ),
        );

        $this->assertSame('completed', $secondTurn['status']);
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
}

final class FailOnceSpeechToTextProvider implements SpeechToTextProvider
{
    private int $calls = 0;

    public function transcribe(
        SpeechToTextRequest $request,
    ): SpeechToTextResult {
        $this->calls++;

        if ($this->calls === 1) {
            throw new ConversationProviderException(
                safeCode: 'synthetic_failure',
                providerKey: 'deterministic-fake',
                modelVersion: 'fake-stt-v1',
                latencyMilliseconds: 1,
                inputUnits: 1,
            );
        }

        return (new DeterministicFakeSpeechToTextProvider)
            ->transcribe($request);
    }
}

final class AlwaysFailSpeechToTextProvider implements SpeechToTextProvider
{
    public function transcribe(
        SpeechToTextRequest $request,
    ): SpeechToTextResult {
        throw new ConversationProviderException(
            safeCode: 'synthetic_failure',
            providerKey: 'deterministic-fake',
            modelVersion: 'fake-stt-v1',
            latencyMilliseconds: 1,
            inputUnits: 1,
        );
    }
}

final class SoftLaunchAzureModelFixtureProvider implements ConversationModelProvider
{
    public function respond(
        ConversationModelRequest $request,
    ): ConversationModelResult {
        return new ConversationModelResult(
            understoodIntent: 'confirmed_text_received',
            assistantReply: 'A live-model fixture reply.',
            providerUsage: new ProviderUsage(
                providerKey: 'azure-openai',
                modelVersion: 'soft-launch-live-fixture-v1',
                latencyMilliseconds: 12,
                inputUnits: 10,
                outputUnits: 5,
                estimatedCostMicrounits: 25,
            ),
        );
    }
}

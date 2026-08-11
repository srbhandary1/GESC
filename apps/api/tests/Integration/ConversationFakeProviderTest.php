<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Conversation\ConversationEventService;
use App\Conversation\ConversationSessionService;
use App\Conversation\ConversationTurnService;
use App\Conversation\Exceptions\ConversationIdempotencyConflict;
use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Providers\ConversationModelProvider;
use App\Conversation\Providers\ConversationModelRequest;
use App\Conversation\Providers\ConversationModelResult;
use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\Fake\DeterministicFakeSpeechToTextProvider;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Providers\SpeechToTextProvider;
use App\Conversation\Providers\SpeechToTextRequest;
use App\Conversation\Providers\SpeechToTextResult;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        $this->app->detectEnvironment(static fn (): string => 'soft-launch');
        config()->set('conversation.provider_driver', 'azure-openai-dev');
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
        $this->assertSame(0, $turn['usage']['estimated_cost_microunits']);
        $this->assertTrue($turnReplay['replayed']);
        $this->assertSame($turn['id'], $turnReplay['id']);
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

        return [$user, $membership, $context];
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

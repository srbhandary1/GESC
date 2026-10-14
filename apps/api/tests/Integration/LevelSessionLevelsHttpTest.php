<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Conversation\Dialogue\ConversationDialogueService;
use App\Conversation\Dialogue\ConversationRecoveryStore;
use App\Curriculum\CanonicalJson;
use App\Curriculum\LessonPractice\LessonPracticeAttachmentService;
use App\Entitlements\DirectPlanDefaults;
use App\Models\Membership;
use App\Models\Organization;
use App\Tenancy\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpFoundation\Response;

final class LevelSessionLevelsHttpTest extends TenancyIntegrationTestCase
{
    private const string OTP = '864200';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            OtpCodeGenerator::class,
            new LevelSessionLevelsHttpFixedOtpCodeGenerator(self::OTP),
        );
        $this->app->instance(
            OtpDeliveryAdapter::class,
            new LevelSessionLevelsHttpNullOtpDeliveryAdapter,
        );
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    #[TestWith(['monthly_modules'])]
    #[TestWith(['monthly_modules_roleplay'])]
    public function test_get_me_levels_reports_locked_and_allowed_progression(string $planCode): void
    {
        $organization = Organization::query()
            ->where('system_key', 'global-english-direct')
            ->firstOrFail();
        $author = $this->createUser();
        [$authorMembership, $authorContext] = $this->createMembership(
            $organization,
            $author,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $authorMembership,
            $authorContext,
            ['curriculum.author', 'media.metadata.manage'],
            'level-session-levels-http',
        );

        $this->commissionPublishedBlueprint(
            $authorContext,
            (string) $organization->getKey(),
            (string) $author->getKey(),
            'can',
            $this->canonicalBlueprint('can', 'Level 1', 'can-'),
        );
        $this->commissionPublishedBlueprint(
            $authorContext,
            (string) $organization->getKey(),
            (string) $author->getKey(),
            'should',
            $this->canonicalBlueprint('should', 'Level 2', 'should-'),
        );

        $this->commissionPublishedBlueprint(
            $authorContext,
            (string) $organization->getKey(),
            (string) $author->getKey(),
            'do_does',
            $this->canonicalBlueprint('do_does', 'Level 3', 'do-'),
        );

        $this->signInWeb();
        $this->activateSubscriptionPlan($planCode);

        $initial = $this->withHeader('Origin', 'https://localhost')
            ->getJson('/api/v1/me/levels')
            ->assertOk()
            ->assertJsonStructure([
                'levels' => [
                    [
                        'level_number',
                        'concept_key',
                        'availability',
                        'progress_access',
                        'unlock_after_level_number',
                    ],
                ],
            ]);
        $this->assertNoStore($initial);

        $this->assertLevelProgress($initial, 1, 'available', 'allowed', null);
        $this->assertLevelProgress($initial, 2, 'available', 'allowed', null);
        $this->assertLevelProgress($initial, 3, 'available', 'locked', 2);
        $this->withHeader('Origin', 'https://localhost')->getJson('/api/v1/me/levels/3/sessions')->assertNotFound();

        $this->withHeader('Origin', 'https://localhost')
            ->getJson('/api/v1/me/levels/2/sessions')
            ->assertOk();

        $this->completeLevelOneViaHttp();

        $checkpointReady = $this->withHeader('Origin', 'https://localhost')
            ->getJson('/api/v1/me/levels')
            ->assertOk();
        $this->assertNoStore($checkpointReady);
        $this->assertLevelProgress($checkpointReady, 2, 'available', 'allowed', null);
        $levels = $checkpointReady->json('levels');
        self::assertIsArray($levels);
        $levelOne = collect($levels)->firstWhere('level_number', 1);
        self::assertIsArray($levelOne);
        self::assertSame('ready', $levelOne['checkpoint_status']);
        self::assertSame('not_recorded', $levelOne['spoken_evidence']['evidence_state']);
        self::assertFalse($levelOne['spoken_evidence']['spoken_progress_claim']);

        $this->withHeader('Origin', 'https://localhost')
            ->getJson('/api/v1/me/levels/1/checkpoint')
            ->assertOk()
            ->assertJsonPath('pass_threshold', 80)
            ->assertJsonCount(3, 'items');

        $this->withHeaders(['Origin' => 'https://localhost'])->postJson(
            '/api/v1/me/levels/1/checkpoint-submissions',
            [
                'idempotency_key' => (string) Str::uuid(),
                'answers' => [
                    'checkpoint-can-01' => 1,
                    'checkpoint-can-02' => 1,
                    'checkpoint-can-03' => 1,
                ],
            ],
        )->assertCreated()
            ->assertJsonPath('passed', false)
            ->assertJsonPath('attempts_remaining', 2);

        $this->passLevelOneCheckpointViaHttp();

        $unlocked = $this->withHeader('Origin', 'https://localhost')
            ->getJson('/api/v1/me/levels')
            ->assertOk();
        $this->assertNoStore($unlocked);

        $this->assertLevelProgress($unlocked, 1, 'available', 'allowed', null);
        $this->assertLevelProgress($unlocked, 2, 'available', 'allowed', null);

        $this->withHeader('Origin', 'https://localhost')
            ->getJson('/api/v1/me/levels/2/sessions')
            ->assertOk()
            ->assertJsonPath('level_number', 2);
    }

    public function test_demo_plan_opens_levels_one_and_two_without_prior_completion(): void
    {
        $organization = Organization::query()
            ->where('system_key', 'global-english-direct')
            ->firstOrFail();
        $author = $this->createUser();
        [$authorMembership, $authorContext] = $this->createMembership(
            $organization,
            $author,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $authorMembership,
            $authorContext,
            ['curriculum.author', 'media.metadata.manage'],
            'level-session-demo-plan',
        );

        $this->commissionPublishedBlueprint(
            $authorContext,
            (string) $organization->getKey(),
            (string) $author->getKey(),
            'can',
            $this->canonicalBlueprint('can', 'Level 1', 'can-'),
        );
        $this->commissionPublishedBlueprint(
            $authorContext,
            (string) $organization->getKey(),
            (string) $author->getKey(),
            'should',
            $this->canonicalBlueprint('should', 'Level 2', 'should-'),
        );

        $this->signInWeb('+919876543220');
        $this->activateDemoPlan();

        $initial = $this->withHeader('Origin', 'https://localhost')
            ->getJson('/api/v1/me/levels')
            ->assertOk();
        $this->assertNoStore($initial);

        $this->assertLevelProgress($initial, 1, 'available', 'allowed', null);
        $this->assertLevelProgress($initial, 2, 'available', 'allowed', null);

        $this->withHeader('Origin', 'https://localhost')
            ->getJson('/api/v1/me/levels/2/sessions')
            ->assertOk()
            ->assertJsonPath('level_number', 2);
    }

    public function test_checkpoint_submission_replays_without_duplicate_attempts_or_unlocks(): void
    {
        $organization = Organization::query()
            ->where('system_key', 'global-english-direct')
            ->firstOrFail();
        $author = $this->createUser();
        [$authorMembership, $authorContext] = $this->createMembership(
            $organization,
            $author,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $authorMembership,
            $authorContext,
            ['curriculum.author', 'media.metadata.manage'],
            'level-session-checkpoint-replay',
        );

        $this->commissionPublishedBlueprint(
            $authorContext,
            (string) $organization->getKey(),
            (string) $author->getKey(),
            'can',
            $this->canonicalBlueprint('can', 'Level 1', 'can-'),
        );

        $this->signInWeb('+919876543218');
        $this->activatePaidFreeSpeechPlan();
        $this->completeLevelOneViaHttp();

        $idempotencyKey = (string) Str::uuid();
        $payload = [
            'idempotency_key' => $idempotencyKey,
            'answers' => [
                'checkpoint-can-01' => 0,
                'checkpoint-can-02' => 0,
                'checkpoint-can-03' => 0,
            ],
        ];
        $origin = ['Origin' => 'https://localhost'];

        $first = $this->withHeaders($origin)->postJson(
            '/api/v1/me/levels/1/checkpoint-submissions',
            $payload,
        )->assertCreated()
            ->assertJsonPath('replayed', false)
            ->assertJsonPath('passed', true);
        $attemptId = $first->json('attempt_id');
        $unlockId = $first->json('level_unlock.unlock_decision_id');
        self::assertIsString($attemptId);
        self::assertNull($unlockId);

        $second = $this->withHeaders($origin)->postJson(
            '/api/v1/me/levels/1/checkpoint-submissions',
            $payload,
        )->assertOk()
            ->assertJsonPath('replayed', true)
            ->assertJsonPath('attempt_id', $attemptId)
            ->assertJsonPath('level_unlock', null);

        $learnerId = (string) auth()->id();
        self::assertSame(
            1,
            DB::table('level_checkpoint_attempts')
                ->where('organization_id', $organization->getKey())
                ->where('user_id', $learnerId)
                ->where('level_number', 1)
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('level_unlock_decisions')
                ->where('organization_id', $organization->getKey())
                ->where('user_id', $learnerId)
                ->where('level_number', 2)
                ->count(),
        );
    }

    public function test_every_configured_level_keeps_tutor_context_and_completes_practice_on_pass(): void
    {
        // Isolate within-level gates from separately tested previous-level checkpoints.
        config()->set('level_sessions.sequential_unlock.enabled', false);
        $organization = Organization::query()->where('system_key', 'global-english-direct')->firstOrFail();
        $author = $this->createUser();
        [$membership, $context] = $this->createMembership($organization, $author, Membership::TYPE_ORGANIZATION_ADMIN);
        $this->grantTestPermissions($membership, $context, ['curriculum.author', 'media.metadata.manage'], 'all-level-tutor');
        foreach (config('level_sessions.level_concepts') as $level => $concept) {
            $this->commissionPublishedBlueprint($context, (string) $organization->getKey(), (string) $author->getKey(),
                $concept, $this->canonicalBlueprint($concept, 'Level '.$level, 'level-'.$level.'-'));
        }
        $this->signInWeb();
        $this->activatePaidFreeSpeechPlan();
        $origin = ['Origin' => 'https://localhost'];
        foreach (config('level_sessions.level_concepts') as $level => $concept) {
            $this->travel(1)->minutes(); // Each level represents a separate learner visit, not a rate-limit load test.
            $base = '/api/v1/me/levels/'.$level.'/sessions/';
            $help = ['idempotency_key' => (string) Str::uuid(), 'ui_locale' => 'en-IN', 'purpose' => 'question_help',
                'recovery_notice_version' => ConversationDialogueService::NOTICE, 'recovery_notice_accepted' => true];
            $this->withHeaders($origin)->postJson($base.'introduction/conversation-sessions', [...$help, 'lesson' => ['concept_key' => 'injected']])->assertUnprocessable();
            $session = $this->withHeaders($origin)->postJson($base.'introduction/conversation-sessions', $help)
                ->assertCreated()->assertJsonPath('interaction.flow', 'question_help')->json('id');
            $this->withHeaders($origin)->postJson($base.'introduction/conversation-sessions', $help)->assertOk()->assertJsonPath('id', $session);
            $state = DB::table('conversation_dialogue_states')->where('organization_id', $organization->getKey())->where('conversation_session_id', $session)->firstOrFail();
            $definition = app(ConversationRecoveryStore::class)->read((string) $organization->getKey(), $state->learner_membership_id, $session, $state->start_artifact_id);
            self::assertIsArray($definition);
            self::assertArrayHasKey('lesson', $definition);
            self::assertIsArray($definition['lesson']);
            self::assertSame($level, $definition['lesson']['level_number']);
            self::assertSame($concept, $definition['lesson']['concept_key']);
            self::assertSame(['I '.$concept.' + base verb.'], $definition['lesson']['target_patterns']);
            self::assertArrayNotHasKey('check', $definition['lesson']);
            self::assertArrayNotHasKey('correct_option', $definition['lesson']);
            $this->withHeaders($origin)->postJson($base.'practice/conversation-sessions', [...$help, 'idempotency_key' => (string) Str::uuid()])->assertNotFound();
            $this->withHeaders($origin)->postJson($base.'introduction/completions', ['idempotency_key' => (string) Str::uuid()])->assertCreated();
            $this->withHeaders($origin)->postJson($base.'practice/check-submissions', ['idempotency_key' => (string) Str::uuid(), 'answers' => ['level-'.$level.'-01' => 1, 'level-'.$level.'-02' => 1, 'level-'.$level.'-03' => 1]])->assertCreated()->assertJsonPath('passed', false);
            $this->withHeaders($origin)->postJson($base.'conversation/conversation-sessions', ['idempotency_key' => (string) Str::uuid(), 'ui_locale' => 'en-IN'])->assertNotFound();
            $check = ['idempotency_key' => (string) Str::uuid(), 'answers' => ['level-'.$level.'-01' => 0, 'level-'.$level.'-02' => 0, 'level-'.$level.'-03' => 0]];
            $this->withHeaders($origin)->postJson($base.'practice/check-submissions', $check)->assertCreated()->assertJsonPath('passed', true);
            $this->withHeaders($origin)->postJson($base.'practice/check-submissions', $check)->assertOk()->assertJsonPath('passed', true);
            self::assertSame(1, DB::table('level_session_completions')->where('organization_id', $organization->getKey())->where('user_id', auth()->id())->where('level_number', $level)->where('part_id', 'practice')->count());
            $this->withHeaders($origin)->getJson($base.'practice')->assertOk()->assertJsonPath('completed', true);
            $this->withHeaders($origin)->postJson($base.'conversation/conversation-sessions', ['idempotency_key' => (string) Str::uuid(), 'ui_locale' => 'en-IN'])->assertCreated();
        }
    }

    private function signInWeb(string $mobileNumber = '+919876543219'): void
    {
        $issued = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => $mobileNumber,
            ])
            ->assertAccepted();
        $challengeId = $issued->json('challenge_id');
        self::assertIsString($challengeId);

        $verified = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => $mobileNumber,
                'code' => self::OTP,
            ])
            ->assertOk();
        $cookie = $verified->getCookie((string) config('session.cookie'));

        self::assertNotNull($cookie);
        $this->withCookie($cookie->getName(), (string) $cookie->getValue());
    }

    private function activatePaidFreeSpeechPlan(): void
    {
        $this->activateSubscriptionPlan('monthly_modules');
    }

    private function activateDemoPlan(): void
    {
        $this->activateSubscriptionPlan(DirectPlanDefaults::DEMO);
    }

    private function activateSubscriptionPlan(string $planCode): void
    {
        $plans = $this->getJson('/api/v1/me/subscription-plans')
            ->assertOk()
            ->json('plans');
        self::assertIsArray($plans);
        $planId = collect($plans)
            ->firstWhere('code', $planCode)['id'] ?? null;
        self::assertIsString($planId);

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/v1/me/subscription-checkouts', [
            'plan_id' => $planId,
        ])->assertCreated();
    }

    private function completeLevelOneViaHttp(): void
    {
        $origin = ['Origin' => 'https://localhost'];

        $this->withHeaders($origin)->postJson(
            '/api/v1/me/levels/1/sessions/introduction/completions',
            ['idempotency_key' => (string) Str::uuid()],
        )->assertCreated();

        $this->withHeaders($origin)->postJson(
            '/api/v1/me/levels/1/sessions/practice/check-submissions',
            [
                'idempotency_key' => (string) Str::uuid(),
                'answers' => [
                    'can-01' => 0,
                    'can-02' => 0,
                    'can-03' => 0,
                ],
            ],
        )->assertCreated()
            ->assertJsonPath('passed', true);

        $this->withHeaders($origin)->postJson(
            '/api/v1/me/levels/1/sessions/practice/completions',
            ['idempotency_key' => (string) Str::uuid()],
        )->assertCreated();

        $this->withHeaders($origin)->postJson(
            '/api/v1/me/levels/1/sessions/conversation/completions',
            ['idempotency_key' => (string) Str::uuid()],
        )->assertCreated();

        $this->withHeaders($origin)->postJson(
            '/api/v1/me/levels/1/sessions/interaction/completions',
            ['idempotency_key' => (string) Str::uuid()],
        )->assertCreated();
    }

    private function passLevelOneCheckpointViaHttp(): void
    {
        $this->withHeaders(['Origin' => 'https://localhost'])->postJson(
            '/api/v1/me/levels/1/checkpoint-submissions',
            [
                'idempotency_key' => (string) Str::uuid(),
                'answers' => [
                    'checkpoint-can-01' => 0,
                    'checkpoint-can-02' => 0,
                    'checkpoint-can-03' => 0,
                ],
            ],
        )->assertCreated()
            ->assertJsonPath('passed', true)
            ->assertJsonPath('spoken_evidence.evidence_state', 'not_recorded')
            ->assertJsonPath('spoken_evidence.spoken_state', 'unassessed')
            ->assertJsonPath('spoken_evidence.spoken_progress_claim', false)
            ->assertJsonPath('level_unlock', null);
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function assertLevelProgress(
        TestResponse $response,
        int $levelNumber,
        string $availability,
        string $progressAccess,
        ?int $unlockAfterLevelNumber,
    ): void {
        $levels = $response->json('levels');
        self::assertIsArray($levels);
        $level = collect($levels)->firstWhere('level_number', $levelNumber);
        self::assertIsArray($level);
        self::assertSame($availability, $level['availability']);
        self::assertSame($progressAccess, $level['progress_access']);
        self::assertSame($unlockAfterLevelNumber, $level['unlock_after_level_number']);
    }

    /**
     * @param  array<string, mixed>  $canonical
     */
    private function commissionPublishedBlueprint(
        OrganizationContext $authorContext,
        string $organizationId,
        string $authorUserId,
        string $conceptKey,
        array $canonical,
    ): void {
        $hash = CanonicalJson::sha256($canonical);
        $programId = $this->insertProgram(
            $organizationId,
            (string) $authorContext->membershipId,
        );
        $attachment = app(LessonPracticeAttachmentService::class);
        $created = $this->contexts()->run(
            $authorContext,
            fn (): array => $attachment->createBlueprint(
                $authorContext,
                $programId,
                (string) Str::ulid(),
                $conceptKey,
                $canonical,
                (string) Str::ulid(),
            ),
        );
        $blueprintId = (string) $created['blueprint']->getKey();
        $this->contexts()->run(
            $authorContext,
            function () use ($attachment, $authorContext, $blueprintId, $hash): void {
                $attachment->recordReview(
                    $authorContext,
                    $blueprintId,
                    'english_academic',
                    'approved',
                    $hash,
                    (string) Str::ulid(),
                );
                $attachment->recordReview(
                    $authorContext,
                    $blueprintId,
                    'kannada',
                    'approved',
                    $hash,
                    (string) Str::ulid(),
                );
            },
        );

        [$appointmentId, $appointmentEventId] = $this->insertRightsAppointment(
            $organizationId,
            $programId,
            $authorUserId,
            (string) $authorContext->membershipId,
        );
        $parts = [];
        foreach (['introduction', 'practice', 'conversation', 'interaction'] as $index => $partId) {
            $parts[] = $this->insertLessonAudioPart(
                $organizationId,
                $programId,
                (string) $authorContext->membershipId,
                $partId,
                $index + 1,
                $appointmentId,
                $appointmentEventId,
            );
        }
        $this->contexts()->run(
            $authorContext,
            fn (): array => $attachment->attachParts(
                $authorContext,
                $blueprintId,
                $hash,
                $parts,
                (string) Str::ulid(),
            ),
        );
        $published = $this->contexts()->run(
            $authorContext,
            fn (): array => $attachment->publishBlueprint(
                $authorContext,
                $blueprintId,
                $hash,
                'levels-http-'.$conceptKey,
            ),
        );
        self::assertTrue($published['created']);
    }

    /**
     * @return array<string, mixed>
     */
    private function canonicalBlueprint(
        string $conceptKey,
        string $levelLabel,
        string $checkItemPrefix,
    ): array {
        return [
            'schema' => 'global-english.lesson-practice-loop.v1',
            'lesson' => [
                'level_label' => $levelLabel,
                'concept_key' => $conceptKey,
                'objective' => 'Use '.$conceptKey.' accurately.',
                'communication_function' => 'ability_and_permission',
                'target_patterns' => ['I '.$conceptKey.' + base verb.'],
                'misconception_codes' => [strtoupper($conceptKey).'_BASE_VERB'],
            ],
            'ordered_parts' => [
                ['order' => 1, 'part_id' => 'introduction'],
                ['order' => 2, 'part_id' => 'practice'],
                ['order' => 3, 'part_id' => 'conversation'],
                ['order' => 4, 'part_id' => 'interaction'],
            ],
            'talk' => [
                'scenario' => 'Ask for permission.',
                'elicitation_goals' => ['Ask one question.'],
                'fallback_turns' => ['Can I use your pen?'],
            ],
            'check' => [
                'pass_threshold' => 80,
                'items' => [
                    [
                        'id' => $checkItemPrefix.'01',
                        'weight' => 35,
                        'prompt' => 'Choose the correct sentence.',
                        'options' => ['I can speak English.', 'I can speaking English.', 'I can to speak English.'],
                        'correct_option' => 0,
                        'rationale' => 'Use the base verb.',
                    ],
                    [
                        'id' => $checkItemPrefix.'02',
                        'weight' => 30,
                        'prompt' => 'Choose the permission question.',
                        'options' => ['Can I use your pen?', 'I can use your pen?', 'Can I using your pen?'],
                        'correct_option' => 0,
                        'rationale' => 'Permission questions use Can I + base verb.',
                    ],
                    [
                        'id' => $checkItemPrefix.'03',
                        'weight' => 35,
                        'prompt' => 'Choose the answer.',
                        'options' => ['Yes, you can.', 'Yes, you can to.', 'Yes, you can sitting.'],
                        'correct_option' => 0,
                        'rationale' => 'Short permission answer uses can.',
                    ],
                ],
            ],
            'apply' => [
                'task' => 'State one ability and ask permission.',
                'rubric' => ['Uses one accurate statement'],
                'evidence_state' => 'not_recorded',
            ],
            'kannada_guidance' => [
                'summary' => 'ಈ ಪಾಠದಲ್ಲಿ '.$conceptKey.' ಬಳಕೆಯನ್ನು ಕಲಿಯಿರಿ.',
            ],
            'publication' => [
                'eligible' => true,
                'blockers' => [],
            ],
        ];
    }

    private function insertProgram(string $organizationId, string $membershipId): string
    {
        $programId = (string) Str::ulid();
        $now = CarbonImmutable::now();
        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organizationId,
            'key' => 'level-session-levels-http-'.$programId,
            'internal_name' => 'Level session levels HTTP test',
            'created_by_membership_id' => $membershipId,
            'created_by_attribution' => 'test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $programId;
    }

    /**
     * @return array{
     *   order: int,
     *   part_id: string,
     *   asset_id: string,
     *   metadata_revision_id: string,
     *   metadata_content_hash: string,
     *   rights_revision_id: string
     * }
     */
    private function insertLessonAudioPart(
        string $organizationId,
        string $programId,
        string $membershipId,
        string $partId,
        int $order,
        string $appointmentId,
        string $appointmentEventId,
    ): array {
        $mediaAssetId = (string) Str::ulid();
        $scanId = (string) Str::ulid();
        $rightsId = (string) Str::ulid();
        $metadataId = (string) Str::ulid();
        $sourceHash = hash('sha256', 'level-session-levels-http-'.$partId);
        $now = CarbonImmutable::now();
        DB::table('media_assets')->insert([
            'id' => $mediaAssetId,
            'organization_id' => $organizationId,
            'key' => 'level-session-levels-http-'.$partId.'-'.$mediaAssetId,
            'asset_kind' => 'lesson_audio',
            'storage_object_key' => 'private/'.$partId.'.mp3',
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
            'technical_metadata_hash' => hash('sha256', 'technical-'.$partId),
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
            'evidence_hash' => hash('sha256', 'rights-'.$partId),
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

        return [
            'order' => $order,
            'part_id' => $partId,
            'asset_id' => $mediaAssetId,
            'metadata_revision_id' => $metadataId,
            'metadata_content_hash' => hash('sha256', 'metadata-'.$partId),
            'rights_revision_id' => $rightsId,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
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
            'qualification_basis' => 'Synthetic level-session levels HTTP fixture',
            'approval_evidence_key' => 'APP-0061',
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

    /**
     * @param  TestResponse<Response>  $response
     */
    private function assertNoStore(TestResponse $response): void
    {
        self::assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
    }
}

final class LevelSessionLevelsHttpFixedOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private readonly string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class LevelSessionLevelsHttpNullOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    public function send(
        string $normalizedMobileNumber,
        string $code,
    ): void {
        //
    }
}

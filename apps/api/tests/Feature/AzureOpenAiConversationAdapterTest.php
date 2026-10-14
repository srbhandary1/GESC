<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Conversation\Dialogue\DialogueModelPolicy;
use App\Conversation\Orchestration\ConversationOutputValidator;
use App\Conversation\Providers\AzureOpenAi\AzureOpenAiConversationConfiguration;
use App\Conversation\Providers\AzureOpenAi\AzureOpenAiConversationModelProvider;
use App\Conversation\Providers\ConversationModelRequest;
use App\Conversation\Providers\ConversationProviderException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AzureOpenAiConversationAdapterTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_rate_limiting_is_distinct_from_an_unknown_provider_failure(): void
    {
        $this->configureAuthorizedBoundary();
        Http::fake(['*' => Http::response([], 429)]);
        try {
            $this->app->make(AzureOpenAiConversationModelProvider::class)->respond($this->sampleRequest());
            self::fail('Expected a rate-limited failure.');
        } catch (ConversationProviderException $exception) {
            $this->assertSame('provider_rate_limited', $exception->safeCode);
            $this->assertTrue($exception->retryable);
        }
    }

    public function test_default_configuration_fails_before_any_http_request(): void
    {
        Http::preventStrayRequests();

        try {
            $this->app->make(AzureOpenAiConversationModelProvider::class)
                ->respond($this->sampleRequest());
            self::fail('Expected the disabled provider to fail closed.');
        } catch (ConversationProviderException $exception) {
            $this->assertSame('provider_not_authorized', $exception->safeCode);
            $this->assertFalse($exception->retryable);
        }

        Http::assertNothingSent();
    }

    public function test_production_environment_cannot_authorize_live_azure_openai(): void
    {
        $this->configureAuthorizedBoundary();
        $this->app->detectEnvironment(static fn (): string => 'production');

        $errors = $this->app->make(
            AzureOpenAiConversationConfiguration::class,
        )->preflightErrors();

        $this->assertContains('environment_not_allowlisted', $errors);
        Http::preventStrayRequests();

        try {
            $this->app->make(AzureOpenAiConversationModelProvider::class)
                ->respond($this->sampleRequest());
            self::fail('Expected production to fail closed.');
        } catch (ConversationProviderException $exception) {
            $this->assertSame('provider_not_authorized', $exception->safeCode);
        }

        Http::assertNothingSent();
    }

    public function test_configuration_rejects_repository_secret_and_http_endpoint(): void
    {
        $this->configureAuthorizedBoundary();
        config()->set(
            'conversation.azure_openai.endpoint',
            'http://0.0.0.0:8000/provider',
        );
        config()->set(
            'conversation.azure_openai.secret_file',
            base_path('.env.example'),
        );

        $errors = $this->app->make(
            AzureOpenAiConversationConfiguration::class,
        )->preflightErrors();

        $this->assertContains('endpoint_invalid', $errors);
        $this->assertContains('secret_file_invalid', $errors);
    }

    public function test_configuration_rejects_soft_launch_scaffold_evidence(): void
    {
        $this->configureAuthorizedBoundary();
        config()->set([
            'conversation.azure_openai.endpoint' => 'https://REPLACE.openai.azure.com',
            'conversation.azure_openai.allowed_host' => 'REPLACE.openai.azure.com',
            'conversation.azure_openai.deployment' => 'REPLACE_DEPLOYMENT_NAME',
            'conversation.azure_openai.evidence.subscription' => 'soft-launch-subscription-v1',
            'conversation.azure_openai.evidence.contracting_entity' => 'soft-launch-entity-v1',
            'conversation.azure_openai.evidence.agreement_sha256' => str_repeat('a', 64),
            'conversation.azure_openai.evidence.dpa_sha256' => str_repeat('b', 64),
            'conversation.azure_openai.evidence.subprocessor_sha256' => str_repeat('c', 64),
            'conversation.azure_openai.evidence.privacy_legal_review' => 'soft-launch-privacy-v1',
            'conversation.azure_openai.evidence.logging_disabled' => 'soft-launch-logging-v1',
            'conversation.azure_openai.evidence.credential_version' => 'soft-launch-credential-v1',
            'conversation.azure_openai.evidence.region_acceptance' => 'soft-launch-region-v1',
        ]);

        $errors = $this->app->make(
            AzureOpenAiConversationConfiguration::class,
        )->preflightErrors();

        $this->assertContains('endpoint_invalid', $errors);
        $this->assertContains('deployment_invalid', $errors);
        $this->assertContains('subscription_evidence_missing', $errors);
        $this->assertContains('contracting_entity_evidence_missing', $errors);
        $this->assertContains('agreement_sha256_invalid', $errors);
        $this->assertContains('dpa_sha256_invalid', $errors);
        $this->assertContains('subprocessor_sha256_invalid', $errors);
        $this->assertContains('privacy_legal_review_evidence_missing', $errors);
        $this->assertContains('logging_disabled_evidence_missing', $errors);
        $this->assertContains('credential_version_evidence_missing', $errors);
        $this->assertContains('region_acceptance_evidence_missing', $errors);
    }

    public function test_chat_completions_maps_structured_turn_with_explanation_l1(): void
    {
        $this->configureAuthorizedBoundary();
        Http::preventStrayRequests();

        $modelJson = json_encode([
            'understood_intent' => 'share_about_work',
            'assistant_reply_en' => 'Thank you for sharing that. What happened next?',
            'safety_state' => 'ok',
            'priority_correction' => [
                'needed' => true,
                'error_category' => 'grammar.article',
                'correction_en' => 'Say: "I enjoy learning English after work."',
                'explanation_l1' => 'ಕೆಲಸದ ನಂತರ ಇಂಗ್ಲಿಷ್ ಕಲಿಯುವುದು ಇಷ್ಟ ಎಂದು ಹೇಳಿ.',
                'explanation_locale' => 'kn-IN',
                'retry_recommended' => true,
            ],
        ], JSON_THROW_ON_ERROR);

        Http::fake([
            'https://conv-test.openai.azure.com/*' => Http::response(
                [
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => $modelJson,
                        ],
                    ]],
                    'usage' => [
                        'prompt_tokens' => 120,
                        'completion_tokens' => 80,
                    ],
                ],
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        $result = $this->app->make(AzureOpenAiConversationModelProvider::class)
            ->respond($this->sampleRequest());

        $this->assertSame('share_about_work', $result->understoodIntent);
        $this->assertSame(
            'Thank you for sharing that. What happened next?',
            $result->assistantReply,
        );
        $this->assertNotNull($result->priorityCorrection);
        $this->assertSame(
            'ಕೆಲಸದ ನಂತರ ಇಂಗ್ಲಿಷ್ ಕಲಿಯುವುದು ಇಷ್ಟ ಎಂದು ಹೇಳಿ.',
            $result->priorityCorrection->explanationL1,
        );
        $this->assertSame('kn-IN', $result->priorityCorrection->explanationLocale);
        $this->assertSame('azure-openai', $result->usage()->providerKey);
        $this->assertSame('confidence-gpt-dev', $result->usage()->modelVersion);
        $this->assertSame(120, $result->usage()->inputUnits);
        $this->assertSame(80, $result->usage()->outputUnits);
        $this->assertSame(
            [
                ['input', 'provider_input_tokens', 120],
                ['output', 'provider_output_tokens', 80],
            ],
            array_map(
                static fn ($component): array => [
                    $component->componentKey,
                    $component->unitKind,
                    $component->quantity,
                ],
                $result->usage()->normalizedBillableComponents(),
            ),
        );

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://conv-test.openai.azure.com/openai/deployments/confidence-gpt-dev/chat/completions?api-version=2024-10-21'
                && $request->hasHeader('api-key', 'not-a-real-provider-key')
                && ($payload['response_format']['type'] ?? null) === 'json_object'
                && str_contains((string) ($payload['messages'][1]['content'] ?? ''), 'kn-IN')
                && str_contains((string) ($payload['messages'][0]['content'] ?? ''), 'Speak only as Swathi')
                && ! str_contains($request->body(), 'not-a-real-provider-key');
        });
    }

    public function test_correction_contract_matches_validator_without_weakening_its_category_guard(): void
    {
        $this->configureAuthorizedBoundary();
        Http::preventStrayRequests();
        $sequence = Http::sequence();
        foreach (['past tense', 'grammar.tense.past'] as $category) {
            $sequence->push([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'understood_intent' => 'describe_yesterday',
                        'assistant_reply_en' => 'What did you buy at the market?',
                        'safety_state' => 'ok',
                        'priority_correction' => [
                            'needed' => true,
                            'error_category' => $category,
                            'correction_en' => 'Yesterday I went to the market.',
                            'explanation_l1' => 'Use went for an action yesterday.',
                            'explanation_locale' => 'en-IN',
                            'retry_recommended' => true,
                            'learner_span' => 'go',
                        ],
                    ], JSON_THROW_ON_ERROR)],
                ]],
            ]);
        }
        Http::fake(['https://conv-test.openai.azure.com/*' => $sequence]);
        $sample = $this->sampleRequest();
        $request = new ConversationModelRequest(
            interactionId: 'synthetic-grammar-contract',
            transcript: 'Yesterday I go to market.',
            personaStyleCode: $sample->personaStyleCode,
            scenarioResponseCode: $sample->scenarioResponseCode,
            coachingMode: 'gentle',
            uiLocale: 'en-IN',
            promptVersionId: $sample->promptVersionId,
            promptBodySha256: $sample->promptBodySha256,
            promptBody: $sample->promptBody,
        );
        $provider = $this->app->make(AzureOpenAiConversationModelProvider::class);
        $validator = new ConversationOutputValidator;

        $this->assertFalse($validator->validate($provider->respond($request)));
        $valid = $provider->respond($request);
        $this->assertTrue($validator->validate($valid));
        $this->assertSame('grammar.tense.past', $valid->priorityCorrection?->errorCategory);

        Http::assertSent(function (Request $httpRequest) use ($sample): bool {
            $payload = $httpRequest->data();
            $this->assertSame(1024, $payload['max_tokens'] ?? null);
            $system = (string) ($payload['messages'][0]['content'] ?? '');
            $user = (string) ($payload['messages'][1]['content'] ?? '');
            $contract = explode("\n\nOutput contract version: confidence-turn-output-2026-09-25.4\n", $system, 2)[1] ?? '';
            foreach (['100', '1000', '500', '200'] as $maximum) {
                if (! str_contains($contract, 'at most '.$maximum.' characters')) {
                    return false;
                }
            }

            return str_contains($contract, '30 words/240 characters')
                && str_contains($contract, '^[a-z0-9][a-z0-9._-]{0,63}$')
                && str_contains($contract, '"grammar.tense.past"')
                && str_contains($contract, 'must exactly equal session ui_locale')
                && str_contains($contract, 'no < or > characters')
                && str_contains($contract, 'Never use shame language')
                && str_contains($user, '"output_contract_sha256":"'.hash('sha256', $contract).'"')
                && str_contains($user, '"prompt_body_sha256":"'.$sample->promptBodySha256.'"');
        });
        Http::assertSentCount(2);
    }

    public function test_streaming_chat_records_first_content_and_completion_separately(): void
    {
        $this->configureAuthorizedBoundary();
        config()->set('conversation.azure_openai.stream_responses', true);
        Http::preventStrayRequests();

        $modelJson = json_encode([
            'understood_intent' => 'share_about_work',
            'assistant_reply_en' => 'Thank you. What happened next?',
            'safety_state' => 'ok',
            'priority_correction' => null,
        ], JSON_THROW_ON_ERROR);
        $splitAt = intdiv(strlen($modelJson), 2);
        $stream = $this->sse([
            ['choices' => [[
                'index' => 0,
                'delta' => ['role' => 'assistant'],
            ]]],
            ['choices' => [[
                'index' => 0,
                'delta' => ['content' => substr($modelJson, 0, $splitAt)],
            ]]],
            ['choices' => [[
                'index' => 0,
                'delta' => ['content' => substr($modelJson, $splitAt)],
                'finish_reason' => 'stop',
            ]]],
            [
                'choices' => [],
                'usage' => [
                    'prompt_tokens' => 120,
                    'completion_tokens' => 80,
                ],
            ],
        ]);
        Http::fake([
            'https://conv-test.openai.azure.com/*' => Http::response(
                $stream,
                200,
                ['Content-Type' => 'text/event-stream'],
            ),
        ]);

        $result = $this->app->make(AzureOpenAiConversationModelProvider::class)
            ->respond($this->sampleRequest());

        $this->assertSame('share_about_work', $result->understoodIntent);
        $this->assertSame(
            'Thank you. What happened next?',
            $result->assistantReply,
        );
        $this->assertNotNull($result->usage()->connectionLatencyMilliseconds);
        $this->assertNotNull($result->usage()->firstResultLatencyMilliseconds);
        $this->assertLessThanOrEqual(
            $result->usage()->latencyMilliseconds,
            $result->usage()->firstResultLatencyMilliseconds,
        );
        $this->assertSame(120, $result->usage()->inputUnits);
        $this->assertSame(80, $result->usage()->outputUnits);
        $this->assertCount(
            2,
            $result->usage()->normalizedBillableComponents(),
        );

        Http::assertSent(static function (Request $request): bool {
            $payload = $request->data();

            return ($payload['stream'] ?? null) === true
                && ($payload['stream_options']['include_usage'] ?? null) === true
                && $request->hasHeader('Accept', 'text/event-stream');
        });
    }

    public function test_lesson_question_payload_pins_scope_without_switching_to_a_drill(): void
    {
        $this->configureAuthorizedBoundary();
        Http::preventStrayRequests();
        Http::fake(['https://conv-test.openai.azure.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'understood_intent' => 'lesson_help', 'assistant_reply' => ['text' => 'Use should to give advice, such as "You should rest."', 'locale' => 'en-IN'],
            'safety_state' => 'ok', 'priority_correction' => null, 'dialogue' => ['assessment' => 'unassessed', 'complete' => false, 'objective_evidence' => []],
        ], JSON_THROW_ON_ERROR)]]]], 200, ['Content-Type' => 'application/json'])]);
        $sample = $this->sampleRequest();
        $lesson = ['level_number' => 2, 'part_id' => 'practice', 'concept_key' => 'should', 'objective' => 'Use should for advice.', 'target_patterns' => ['You should + base verb.']];
        $request = new ConversationModelRequest('lesson-help', 'Explain this lesson', $sample->personaStyleCode, $sample->scenarioResponseCode, 'gentle', 'en-IN', $sample->promptVersionId, $sample->promptBodySha256, $sample->promptBody,
            dialogue: ['flow' => 'question_help', 'action' => 'question', 'requested_reply_locale' => 'en-IN', 'lesson' => $lesson]);
        $result = app(AzureOpenAiConversationModelProvider::class)->respond($request);
        self::assertTrue((new DialogueModelPolicy)->valid($result, $request->dialogue));
        Http::assertSent(function (Request $sent): bool {
            $system = $sent->data()['messages'][0]['content'];
            $payload = $sent->data()['messages'][1]['content'];

            return str_contains($system, 'tutor for that exact lesson') && str_contains($system, 'redirect to the current concept')
                && str_contains($payload, 'You should + base verb.') && str_contains($payload, 'Explain this lesson')
                && ! str_contains($payload, 'correct_option') && ! str_contains($system, 'strict-pattern-gate');
        });
    }

    public function test_dialogue_maps_locale_bearing_reply_and_separate_outcome_contract(): void
    {
        $this->configureAuthorizedBoundary();
        Http::preventStrayRequests();
        Http::fake(['https://conv-test.openai.azure.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'understood_intent' => 'explain_past_tense', 'assistant_reply' => ['text' => 'ಈಗಾಗಲೇ ನಡೆದ ಕೆಲಸವನ್ನು ಹೇಳಲು ಭೂತಕಾಲವನ್ನು ಬಳಸುತ್ತೇವೆ.', 'locale' => 'kn-IN'],
            'safety_state' => 'ok', 'priority_correction' => null, 'dialogue' => ['assessment' => 'unassessed', 'complete' => false, 'objective_evidence' => []],
        ], JSON_THROW_ON_ERROR)]]]], 200, ['Content-Type' => 'application/json'])]);
        $sample = $this->sampleRequest();
        $request = new ConversationModelRequest('synthetic-help', 'Yes', $sample->personaStyleCode, $sample->scenarioResponseCode, 'gentle', 'kn-IN', $sample->promptVersionId, $sample->promptBodySha256, $sample->promptBody,
            dialogue: ['flow' => 'question_help', 'action' => 'accept_explanation', 'ui_locale' => 'kn-IN', 'requested_reply_locale' => 'kn-IN',
                'history' => [['id' => 'synthetic-private-turn-id', 'learner' => 'What is past tense?', 'reply' => ['text' => 'It describes past actions.', 'locale' => 'en-IN'], 'assessment' => 'unassessed', 'action' => 'question']],
                'objective_progress' => [['objective_key' => 'synthetic-objective', 'completion_state' => 'unassessed', 'evidence_digest' => 'synthetic-private-evidence-digest']]]);
        $result = app(AzureOpenAiConversationModelProvider::class)->respond($request);
        $this->assertSame('kn-IN', $result->replyLocale);
        $this->assertTrue((new DialogueModelPolicy)->valid($result, $request->dialogue));
        Http::assertSent(function (Request $sent): bool {
            $system = $sent->data()['messages'][0]['content'];
            $format = $sent->data()['response_format'];
            $properties = $format['json_schema']['schema']['properties'];

            return $format['type'] === 'json_schema'
                && $format['json_schema']['strict'] === true
                && $format['json_schema']['schema']['additionalProperties'] === false
                && $properties['priority_correction']['type'] === 'null'
                && $properties['assistant_reply']['properties']['locale']['enum'] === ['kn-IN']
                && $properties['dialogue']['properties']['assessment']['enum'] === ['unassessed']
                && $properties['dialogue']['properties']['objective_evidence']['maxItems'] === 0
                && str_contains($sent->body(), 'dialogue_contract_sha256')
                && str_contains($sent->body(), DialogueModelPolicy::VERSION)
                && ! str_contains($system, 'assistant_reply_en (nonempty string')
                && ! str_contains($system, 'Speak only as Swathi, the on-screen English teacher.')
                && str_contains($system, 'assistant_reply (object with text:')
                && str_contains($system, 'objective_evidence MUST be []')
                && ! str_contains($system, 'COPY priority_correction.correction_en character-for-character')
                && ! str_contains($system, 'next meaningful line from the AI role')
                && str_contains($system, "answer the learner's actual question")
                && str_contains($system, 'AI English practice partner, not a human')
                && str_contains($system, 'Never change learning records.')
                && str_ends_with($sent->data()['messages'][1]['content'], '"Yes"')
                && str_contains($sent->body(), 'What is past tense?')
                && str_contains($sent->body(), 'It describes past actions.')
                && str_contains($sent->body(), 'synthetic-objective')
                && ! str_contains($sent->body(), 'synthetic-private-turn-id')
                && ! str_contains($sent->body(), 'synthetic-private-evidence-digest')
                && ! str_contains($sent->body(), 'synthetic-help')
                && ! str_contains($sent->body(), 'prompt_version_id');
        });
    }

    public function test_free_reading_translates_only_the_server_resolved_bubble_not_the_button_or_history(): void
    {
        $this->configureAuthorizedBoundary();
        Http::preventStrayRequests();
        Http::fake(['https://conv-test.openai.azure.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'understood_intent' => 'read_translation', 'assistant_reply' => ['text' => 'ನಮಸ್ಕಾರ! ಇಂದು ನೀವು ಏನು ಮಾತನಾಡಲು ಬಯಸುತ್ತೀರಿ?', 'locale' => 'kn-IN'],
            'safety_state' => 'ok', 'priority_correction' => null, 'dialogue' => ['assessment' => 'unassessed', 'complete' => false, 'objective_evidence' => []],
        ], JSON_THROW_ON_ERROR)]]]], 200, ['Content-Type' => 'application/json'])]);
        $sample = $this->sampleRequest();
        $source = ['text' => 'Hello! What would you like to talk about today?', 'locale' => 'en-IN'];
        $request = new ConversationModelRequest('synthetic-reading', 'Read in my selected language', $sample->personaStyleCode, $sample->scenarioResponseCode, 'gentle', 'kn-IN', $sample->promptVersionId, $sample->promptBodySha256, $sample->promptBody,
            memoryFacts: ['Unrelated private note'],
            dialogue: ['flow' => 'free_speech', 'action' => 'accept_explanation', 'requested_reply_locale' => 'kn-IN',
                'explanation_target' => ['question' => '', 'answer' => $source],
                'history' => [['learner' => 'Unrelated learner utterance', 'reply' => ['text' => 'Unrelated reply']]]]);
        $result = app(AzureOpenAiConversationModelProvider::class)->respond($request);
        $this->assertSame('kn-IN', $result->replyLocale);
        Http::assertSent(function (Request $sent) use ($source): bool {
            $data = $sent->data();

            return json_decode($data['messages'][1]['content'], true) === ['target_locale' => 'kn-IN', 'source_reply' => $source]
                && str_contains($data['messages'][0]['content'], 'ONLY a faithful translation')
                && $data['response_format']['json_schema']['schema']['properties']['priority_correction']['type'] === 'null'
                && ! str_contains($sent->body(), 'Read in my selected language')
                && ! str_contains($sent->body(), 'Unrelated');
        });
    }

    public function test_free_speech_constrains_unassessed_outcome_and_optional_nonblocking_correction(): void
    {
        $this->configureAuthorizedBoundary();
        Http::preventStrayRequests();
        Http::fake(['https://conv-test.openai.azure.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'understood_intent' => 'gardening', 'assistant_reply' => ['text' => 'How long have you grown tomatoes?', 'locale' => 'en-IN'],
            'safety_state' => 'ok', 'priority_correction' => null, 'dialogue' => ['assessment' => 'unassessed', 'complete' => false, 'objective_evidence' => []],
        ], JSON_THROW_ON_ERROR)]]]], 200, ['Content-Type' => 'application/json'])]);
        $sample = $this->sampleRequest();
        $request = new ConversationModelRequest('synthetic-free', 'I grow tomatoes.', $sample->personaStyleCode, $sample->scenarioResponseCode, 'gentle', 'kn-IN', $sample->promptVersionId, $sample->promptBodySha256, $sample->promptBody,
            dialogue: ['flow' => 'free_speech', 'action' => 'reply', 'ui_locale' => 'kn-IN', 'requested_reply_locale' => 'en-IN']);
        $result = app(AzureOpenAiConversationModelProvider::class)->respond($request);
        $this->assertTrue((new DialogueModelPolicy)->valid($result, $request->dialogue));
        Http::assertSent(function (Request $sent): bool {
            $format = $sent->data()['response_format'];
            $properties = $format['json_schema']['schema']['properties'];
            $correction = $properties['priority_correction']['anyOf'];

            return $format['type'] === 'json_schema'
                && $format['json_schema']['strict'] === true
                && $format['json_schema']['name'] === 'free_speech_v1'
                && $properties['assistant_reply']['properties']['locale']['enum'] === ['en-IN']
                && $properties['dialogue']['properties']['assessment']['enum'] === ['unassessed']
                && $properties['dialogue']['properties']['complete']['enum'] === [false]
                && $properties['dialogue']['properties']['objective_evidence']['maxItems'] === 0
                && $correction[0]['type'] === 'null'
                && $correction[1]['additionalProperties'] === false
                && $correction[1]['properties']['needed']['enum'] === [true]
                && $correction[1]['properties']['explanation_locale']['enum'] === ['kn-IN']
                && $correction[1]['properties']['retry_recommended']['enum'] === [false]
                && str_contains($sent->data()['messages'][0]['content'], 'Kannada using Kannada script')
                && str_contains($sent->data()['messages'][0]['content'], 'I ಜೊತೆಗೆ water ಬಳಸಿ.')
                && ! str_contains($sent->data()['messages'][0]['content'], 'This turn is QUESTION HELP');
        });
    }

    public function test_stream_without_provider_usage_is_not_priced_as_tokens(): void
    {
        $this->configureAuthorizedBoundary();
        config()->set('conversation.azure_openai.stream_responses', true);
        Http::preventStrayRequests();

        $modelJson = json_encode([
            'understood_intent' => 'share_about_work',
            'assistant_reply_en' => 'Thank you. Please continue.',
            'safety_state' => 'ok',
            'priority_correction' => null,
        ], JSON_THROW_ON_ERROR);
        Http::fake([
            'https://conv-test.openai.azure.com/*' => Http::response(
                $this->sse([[
                    'choices' => [[
                        'index' => 0,
                        'delta' => ['content' => $modelJson],
                        'finish_reason' => 'stop',
                    ]],
                ]]),
                200,
                ['Content-Type' => 'text/event-stream'],
            ),
        ]);

        $result = $this->app->make(AzureOpenAiConversationModelProvider::class)
            ->respond($this->sampleRequest());

        $this->assertSame('estimated_words', $result->usage()->inputUnitKind);
        $this->assertSame('estimated_words', $result->usage()->outputUnitKind);
        $this->assertSame(0, $result->usage()->estimatedCostMicrounits);
        $this->assertSame(
            [],
            $result->usage()->normalizedBillableComponents(),
        );
    }

    public function test_incomplete_stream_fails_closed_without_leaking_content(): void
    {
        $this->configureAuthorizedBoundary();
        config()->set('conversation.azure_openai.stream_responses', true);
        Http::preventStrayRequests();
        Http::fake([
            'https://conv-test.openai.azure.com/*' => Http::response(
                'data: {"choices":[{"index":0,"delta":{"content":"private partial"}}]}'
                    ."\n\n",
                200,
                ['Content-Type' => 'text/event-stream'],
            ),
        ]);

        try {
            $this->app->make(AzureOpenAiConversationModelProvider::class)
                ->respond($this->sampleRequest());
            self::fail('Expected the incomplete stream to fail closed.');
        } catch (ConversationProviderException $exception) {
            $this->assertSame('provider_stream_incomplete', $exception->safeCode);
            $this->assertTrue($exception->retryable);
            $this->assertStringNotContainsString(
                'private partial',
                $exception->getMessage(),
            );
        }
    }

    public function test_stream_byte_limit_fails_closed_without_leaking_content(): void
    {
        $this->configureAuthorizedBoundary();
        config()->set([
            'conversation.azure_openai.stream_responses' => true,
            'conversation.azure_openai.maximum_json_response_bytes' => 64,
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://conv-test.openai.azure.com/*' => Http::response(
                $this->sse([[
                    'choices' => [[
                        'index' => 0,
                        'delta' => [
                            'content' => 'private content that must never appear in an exception',
                        ],
                    ]],
                ]]),
                200,
                ['Content-Type' => 'text/event-stream'],
            ),
        ]);

        try {
            $this->app->make(AzureOpenAiConversationModelProvider::class)
                ->respond($this->sampleRequest());
            self::fail('Expected the bounded stream to fail closed.');
        } catch (ConversationProviderException $exception) {
            $this->assertSame('provider_response_too_large', $exception->safeCode);
            $this->assertFalse($exception->retryable);
            $this->assertStringNotContainsString(
                'private content',
                $exception->getMessage(),
            );
        }
    }

    public function test_chat_completions_maps_unneeded_correction_to_null(): void
    {
        $this->configureAuthorizedBoundary();
        Http::preventStrayRequests();

        $modelJson = json_encode([
            'understood_intent' => 'share_about_work',
            'assistant_reply_en' => 'That sounds like a productive day.',
            'safety_state' => 'ok',
            'priority_correction' => [
                'needed' => false,
                'error_category' => 'none',
                'correction_en' => 'none',
                'explanation_l1' => 'none',
                'explanation_locale' => 'kn-IN',
                'retry_recommended' => false,
            ],
        ], JSON_THROW_ON_ERROR);

        Http::fake([
            'https://conv-test.openai.azure.com/*' => Http::response(
                [
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => $modelJson,
                        ],
                    ]],
                ],
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        $result = $this->app->make(AzureOpenAiConversationModelProvider::class)
            ->respond($this->sampleRequest());

        $this->assertNull($result->priorityCorrection);
        $this->assertSame('estimated_words', $result->usage()->inputUnitKind);
        $this->assertSame('estimated_words', $result->usage()->outputUnitKind);
        $this->assertSame(0, $result->usage()->estimatedCostMicrounits);
        $this->assertSame([], $result->usage()->normalizedBillableComponents());
    }

    public function test_lesson_drill_payload_is_sent_to_azure(): void
    {
        $this->configureAuthorizedBoundary();
        Http::preventStrayRequests();
        $modelJson = json_encode([
            'understood_intent' => 'lesson_drill_can',
            'assistant_reply_en' => 'Say: I can speak English. Now you try.',
            'safety_state' => 'ok',
            'priority_correction' => [
                'needed' => false,
                'error_category' => 'none',
                'correction_en' => 'none',
                'explanation_l1' => 'none',
                'explanation_locale' => 'kn-IN',
                'retry_recommended' => false,
            ],
        ], JSON_THROW_ON_ERROR);
        Http::fake([
            'https://conv-test.openai.azure.com/*' => Http::response(
                [
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => $modelJson,
                        ],
                    ]],
                ],
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        $body = "You are a patient Friend coach.\nReturn structured JSON.";
        $this->app->make(AzureOpenAiConversationModelProvider::class)
            ->respond(new ConversationModelRequest(
                interactionId: 'opaque-turn-id',
                transcript: 'Tell me about your weekend.',
                personaStyleCode: 'patient-friend-v1',
                scenarioResponseCode: 'friend.skill-focus.past-simple.v1',
                coachingMode: 'tutor',
                uiLocale: 'kn-IN',
                promptVersionId: (string) Str::ulid(),
                promptBodySha256: hash('sha256', $body),
                promptBody: $body,
                lessonDrill: [
                    'concept_key' => 'can',
                    'target_patterns' => ['I can + base verb.', 'Can I + base verb?'],
                ],
            ));

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $user = (string) ($payload['messages'][1]['content'] ?? '');

            return str_contains($user, 'grammar drill, not free conversation')
                && str_contains($user, 'I can + base verb.')
                && str_contains($user, '"concept_key":"can"')
                && str_contains($user, 'one-pattern drill');
        });
    }

    public function test_malformed_model_json_fails_closed_without_leaking_body(): void
    {
        $this->configureAuthorizedBoundary();
        Http::preventStrayRequests();
        Http::fake([
            'https://conv-test.openai.azure.com/*' => Http::response(
                [
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'not-json {{{',
                        ],
                    ]],
                ],
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        try {
            $this->app->make(AzureOpenAiConversationModelProvider::class)
                ->respond($this->sampleRequest());
            self::fail('Expected malformed response to fail closed.');
        } catch (ConversationProviderException $exception) {
            $this->assertSame('provider_malformed_response', $exception->safeCode);
            $this->assertStringNotContainsString(
                'not-json',
                $exception->getMessage(),
            );
        }
    }

    private function sampleRequest(): ConversationModelRequest
    {
        $body = "You are a patient Friend coach.\nReturn structured JSON.";

        return new ConversationModelRequest(
            interactionId: 'opaque-turn-id',
            transcript: 'I enjoy learn English after work.',
            personaStyleCode: 'patient-friend-v1',
            scenarioResponseCode: 'friend.everyday.follow-up.v1',
            coachingMode: 'gentle',
            uiLocale: 'kn-IN',
            promptVersionId: (string) Str::ulid(),
            promptBodySha256: hash('sha256', $body),
            promptBody: $body,
        );
    }

    private function configureAuthorizedBoundary(): void
    {
        $this->app->detectEnvironment(
            static fn (): string => 'conversation-model-dev',
        );
        $agreementSha256 = hash('sha256', 'conversation-agreement-evidence-v1');
        $dpaSha256 = hash('sha256', 'conversation-dpa-evidence-v1');
        $subprocessorSha256 = hash('sha256', 'conversation-subprocessor-evidence-v1');
        $secretPath = $this->temporaryPath('secret');
        $vendorPath = $this->temporaryPath('vendor.json');
        $spendPath = $this->temporaryPath('spend.json');
        file_put_contents($secretPath, 'not-a-real-provider-key');
        chmod($secretPath, 0600);
        $this->writeEvidence($vendorPath, [
            'schema_version' => 1,
            'provider' => 'azure-openai',
            'region' => 'centralindia',
            'approved_for_conversation_model' => true,
            'data_logging_enabled' => false,
            'model_improvement_enabled' => false,
            'subscription_evidence_id' => 'subscription-evidence-v1',
            'contracting_entity_review_id' => 'entity-review-v1',
            'agreement_sha256' => $agreementSha256,
            'dpa_sha256' => $dpaSha256,
            'subprocessor_sha256' => $subprocessorSha256,
            'privacy_legal_review_id' => 'privacy-review-v1',
            'logging_disabled_evidence_id' => 'logging-review-v1',
            'reviewed_at' => '2026-08-07T00:00:00Z',
            'valid_until' => '2099-08-07T00:00:00Z',
        ]);
        $this->writeEvidence($spendPath, [
            'schema_version' => 1,
            'approval_id' => 'APP-0022',
            'approved' => true,
            'cap_inr' => 500,
            'reviewed_at' => '2026-08-07T00:00:00Z',
            'valid_until' => '2099-08-07T00:00:00Z',
        ]);

        config()->set([
            'conversation.provider_driver' => 'azure-openai-dev',
            'conversation.azure_openai.live_requests_enabled' => true,
            'conversation.azure_openai.endpoint' => 'https://conv-test.openai.azure.com',
            'conversation.azure_openai.allowed_host' => 'conv-test.openai.azure.com',
            'conversation.azure_openai.deployment' => 'confidence-gpt-dev',
            'conversation.azure_openai.api_version' => '2024-10-21',
            'conversation.azure_openai.credential_kind' => 'api-key',
            'conversation.azure_openai.secret_file' => $secretPath,
            'conversation.azure_openai.maximum_spend_inr' => 10000.0,
            'conversation.azure_openai.approved_spend_cap_inr' => 500,
            'conversation.azure_openai.vendor_evidence_path' => $vendorPath,
            'conversation.azure_openai.spend_approval_path' => $spendPath,
            'conversation.azure_openai.evidence.subscription' => 'subscription-evidence-v1',
            'conversation.azure_openai.evidence.contracting_entity' => 'entity-review-v1',
            'conversation.azure_openai.evidence.agreement_sha256' => $agreementSha256,
            'conversation.azure_openai.evidence.dpa_sha256' => $dpaSha256,
            'conversation.azure_openai.evidence.subprocessor_sha256' => $subprocessorSha256,
            'conversation.azure_openai.evidence.privacy_legal_review' => 'privacy-review-v1',
            'conversation.azure_openai.evidence.logging_disabled' => 'logging-review-v1',
            'conversation.azure_openai.evidence.credential_version' => 'credential-version-v1',
            'conversation.azure_openai.evidence.spend_approval' => 'spend-approval-v1',
            'conversation.azure_openai.evidence.region_acceptance' => 'region-acceptance-v1',
        ]);
    }

    private function temporaryPath(string $suffix): string
    {
        $path = sys_get_temp_dir()
            .'/a4-openai-'.Str::random(20).'-'.$suffix;
        $this->temporaryFiles[] = $path;

        return $path;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function sse(array $events): string
    {
        $body = '';
        foreach ($events as $event) {
            $body .= 'data: '.json_encode(
                $event,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
            )."\n\n";
        }

        return $body."data: [DONE]\n\n";
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function writeEvidence(string $path, array $evidence): void
    {
        file_put_contents($path, json_encode($evidence, JSON_THROW_ON_ERROR));
        chmod($path, 0600);
    }
}

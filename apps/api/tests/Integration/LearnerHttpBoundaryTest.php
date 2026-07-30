<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

final class LearnerHttpBoundaryTest extends TenancyIntegrationTestCase
{
    private const string OTP = '135790';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            OtpCodeGenerator::class,
            new LearnerHttpFixedOtpCodeGenerator(self::OTP),
        );
        $this->app->instance(
            OtpDeliveryAdapter::class,
            new LearnerHttpNullOtpDeliveryAdapter,
        );
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    public function test_every_learner_route_requires_first_party_authentication(): void
    {
        config()->set('passport.private_key', null);
        config()->set('passport.public_key', null);
        $missionId = (string) Str::ulid();
        $attemptId = (string) Str::ulid();
        $assetId = (string) Str::ulid();
        $enrollmentId = (string) Str::ulid();
        $conversationSessionId = (string) Str::ulid();
        $conversationTurnId = (string) Str::ulid();
        $idempotencyKey = (string) Str::uuid();

        $requests = [
            fn (): TestResponse => $this->getJson('/api/v1/home'),
            fn (): TestResponse => $this->withHeader(
                'Idempotency-Key',
                $idempotencyKey,
            )->putJson('/api/v1/me/onboarding', [
                'adult_confirmed' => true,
                'goal_key' => 'everyday-conversation',
            ]),
            fn (): TestResponse => $this->getJson(sprintf(
                '/api/v1/missions/%s?enrollment_id=%s',
                $missionId,
                $enrollmentId,
            )),
            fn (): TestResponse => $this->getJson(sprintf(
                '/api/v1/missions/%s/offline-bundle?enrollment_id=%s',
                $missionId,
                $enrollmentId,
            )),
            fn (): TestResponse => $this->withHeader(
                'Idempotency-Key',
                $idempotencyKey,
            )->postJson(
                sprintf('/api/v1/missions/%s/attempts', $missionId),
                ['enrollment_id' => $enrollmentId],
            ),
            fn (): TestResponse => $this->withHeader(
                'Idempotency-Key',
                $idempotencyKey,
            )->postJson(
                sprintf('/api/v1/attempts/%s/responses', $attemptId),
                [
                    'check_id' => 'daily-check',
                    'selected_option_id' => 'option-one',
                    'local_recording' => null,
                ],
            ),
            fn (): TestResponse => $this->withHeader(
                'Idempotency-Key',
                $idempotencyKey,
            )->postJson(
                sprintf('/api/v1/attempts/%s/submit', $attemptId),
            ),
            fn (): TestResponse => $this->getJson(sprintf(
                '/api/v1/progress?enrollment_id=%s',
                $enrollmentId,
            )),
            fn (): TestResponse => $this->withHeader(
                'Idempotency-Key',
                $idempotencyKey,
            )->postJson('/api/v1/offline-completions', [
                'schema' => 'global-english.offline-completion.v1',
                'bundle_id' => (string) Str::ulid(),
                'lease' => 'not-a-valid-lease',
                'client_attempt_id' => (string) Str::uuid(),
                'check_id' => 'daily-check',
                'selected_option_id' => 'option-one',
                'local_recording' => null,
            ]),
            fn (): TestResponse => $this->getJson(sprintf(
                '/api/v1/media-assets/%s/content?enrollment_id=%s&mission_id=%s',
                $assetId,
                $enrollmentId,
                $missionId,
            )),
            fn (): TestResponse => $this->withHeader(
                'Idempotency-Key',
                $idempotencyKey,
            )->postJson('/api/v1/conversation-sessions', [
                'persona_key' => 'patient-friend-prototype',
                'scenario_key' => 'everyday-friend-prototype',
                'coaching_mode' => 'gentle',
            ]),
            fn (): TestResponse => $this->withHeader(
                'Idempotency-Key',
                $idempotencyKey,
            )->postJson(sprintf(
                '/api/v1/conversation-sessions/%s/turns',
                $conversationSessionId,
            ), [
                'prototype_utterance' => 'Synthetic unauthenticated turn.',
            ]),
            fn (): TestResponse => $this->get(sprintf(
                '/api/v1/conversation-turns/%s/events',
                $conversationTurnId,
            )),
        ];

        foreach ($requests as $request) {
            $response = $request()
                ->assertUnauthorized()
                ->assertHeader(
                    'Content-Type',
                    'application/problem+json',
                )
                ->assertJsonPath(
                    'message_key',
                    'problem.unauthenticated',
                );

            $this->assertNoStore($response);
        }
    }

    public function test_fake_friend_turn_runs_through_http_and_streams_safe_events(): void
    {
        $this->signInWeb();
        $sessionCommand = (string) Str::uuid();
        $turnCommand = (string) Str::uuid();
        $utterance = 'I practise English after work.';

        $session = $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => $sessionCommand,
        ])->postJson('/api/v1/conversation-sessions', [
            'persona_key' => 'patient-friend-prototype',
            'scenario_key' => 'everyday-friend-prototype',
            'coaching_mode' => 'gentle',
        ])
            ->assertCreated()
            ->assertJsonPath('mode', 'friend')
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('replayed', false)
            ->assertJsonPath(
                'persona.disclosure',
                'I am a deterministic AI prototype, not a human.',
            );
        $this->assertNoStore($session);
        $sessionId = $session->json('id');
        self::assertIsString($sessionId);

        $replay = $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => $sessionCommand,
        ])->postJson('/api/v1/conversation-sessions', [
            'persona_key' => 'patient-friend-prototype',
            'scenario_key' => 'everyday-friend-prototype',
            'coaching_mode' => 'gentle',
        ])
            ->assertOk()
            ->assertJsonPath('id', $sessionId)
            ->assertJsonPath('replayed', true);
        $this->assertNoStore($replay);

        $turnPath = sprintf(
            '/api/v1/conversation-sessions/%s/turns',
            $sessionId,
        );
        $turn = $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => $turnCommand,
        ])->postJson($turnPath, [
            'prototype_utterance' => $utterance,
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('transcript', $utterance)
            ->assertJsonPath(
                'assistant_reply',
                'Thank you for sharing that. What would you like to talk about next?',
            )
            ->assertJsonPath('speech.playback_available', false)
            ->assertJsonPath(
                'speech.rendering_mode',
                'deterministic-fake-text-only',
            )
            ->assertJsonPath('usage.provider_attempts', 3)
            ->assertJsonPath('usage.estimated_cost_microunits', 0);
        $this->assertNoStore($turn);
        $turnId = $turn->json('id');
        self::assertIsString($turnId);

        $stream = $this->get(sprintf(
            '/api/v1/conversation-turns/%s/events',
            $turnId,
        ))
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'text/event-stream; charset=UTF-8',
            );
        $this->assertNoStore($stream);
        $streamed = $stream->streamedContent();
        self::assertStringContainsString(
            'event: conversation-status',
            $streamed,
        );
        self::assertStringContainsString(
            '"event_code":"turn.completed"',
            $streamed,
        );
        self::assertStringNotContainsString($utterance, $streamed);

        $rawAudioRejected = $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson($turnPath, [
            'prototype_utterance' => 'Safe visible input.',
            'raw_audio' => 'conversation-audio-must-not-enter-api',
        ])->assertUnprocessable();
        self::assertStringNotContainsString(
            'conversation-audio-must-not-enter-api',
            (string) $rawAudioRejected->getContent(),
        );
        $this->assertNoStore($rawAudioRejected);
    }

    public function test_onboarding_and_learner_mutation_validation_runs_through_http(): void
    {
        $this->signInWeb();
        $firstCommand = (string) Str::uuid();

        $completed = $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => $firstCommand,
        ])->putJson('/api/v1/me/onboarding', [
            'adult_confirmed' => true,
            'goal_key' => 'everyday-conversation',
        ])
            ->assertOk()
            ->assertJsonPath('onboarding.completed', true)
            ->assertJsonPath('onboarding.adult_confirmed', true)
            ->assertJsonPath(
                'onboarding.goal_key',
                'everyday-conversation',
            )
            ->assertJsonPath('enrollment', null)
            ->assertJsonPath('next_mission', null);
        $this->assertNoStore($completed);

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => $firstCommand,
        ])->putJson('/api/v1/me/onboarding', [
            'adult_confirmed' => true,
            'goal_key' => 'everyday-conversation',
        ])->assertOk();

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->putJson('/api/v1/me/onboarding', [
            'adult_confirmed' => 'yes',
            'goal_key' => 'everyday-conversation',
        ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'message_key',
                'problem.validation_failed',
            );

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->putJson('/api/v1/me/onboarding', [
            'adult_confirmed' => true,
            'goal_key' => 'everyday-conversation',
            'organization_id' => (string) Str::ulid(),
        ])->assertUnprocessable();

        $missionId = (string) Str::ulid();
        $attemptId = (string) Str::ulid();
        $enrollmentId = (string) Str::ulid();

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson(
            sprintf('/api/v1/missions/%s/attempts', $missionId),
            [
                'enrollment_id' => $enrollmentId,
                'attempt_number' => 42,
            ],
        )->assertUnprocessable();

        $responsePath = sprintf(
            '/api/v1/attempts/%s/responses',
            $attemptId,
        );
        $responseHeaders = [
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ];

        $this->withHeaders($responseHeaders)
            ->postJson($responsePath, [
                'check_id' => 'daily-check',
                'selected_option_id' => 'option-one',
            ])
            ->assertUnprocessable();

        $this->withHeaders($responseHeaders)
            ->postJson($responsePath, [
                'check_id' => 'daily-check',
                'selected_option_id' => 'option-one',
                'local_recording' => null,
            ])
            ->assertNotFound()
            ->assertJsonPath('message_key', 'problem.not_found');

        $rawAudioRejected = $this->withHeaders($responseHeaders)
            ->postJson($responsePath, [
                'check_id' => 'daily-check',
                'selected_option_id' => 'option-one',
                'local_recording' => null,
                'raw_audio' => 'learner-audio-must-not-enter-api',
            ])
            ->assertUnprocessable();
        self::assertStringNotContainsString(
            'learner-audio-must-not-enter-api',
            (string) $rawAudioRejected->getContent(),
        );
        $this->assertNoStore($rawAudioRejected);

        $this->withHeaders($responseHeaders)
            ->postJson($responsePath, [
                'check_id' => 'daily-check',
                'selected_option_id' => 'option-one',
                'local_recording' => [
                    'captured' => true,
                    'client_capture_id' => (string) Str::uuid(),
                    'mime_type' => 'audio/webm',
                    'byte_size' => 512,
                    'duration_ms' => 1200,
                    'audio_digest' => str_repeat('a', 64),
                ],
            ])
            ->assertUnprocessable();

        $this->withHeaders($responseHeaders)
            ->postJson($responsePath, [
                'check_id' => 'daily-check',
                'selected_option_id' => 'option-one',
                'local_recording' => [
                    'captured' => true,
                    'client_capture_id' => (string) Str::uuid(),
                    'mime_type' => 'audio/webm',
                    'byte_size' => '512',
                    'duration_ms' => '1200',
                ],
            ])
            ->assertUnprocessable();

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson(
            sprintf('/api/v1/attempts/%s/submit', $attemptId),
            ['knowledge_score' => 100],
        )->assertUnprocessable();

        $this->getJson(sprintf(
            '/api/v1/missions/%s/offline-bundle?enrollment_id=%s&organization_id=%s',
            $missionId,
            $enrollmentId,
            (string) Str::ulid(),
        ))->assertUnprocessable();

        $offlineHeaders = [
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ];
        $offlineBody = [
            'schema' => 'global-english.offline-completion.v1',
            'bundle_id' => (string) Str::ulid(),
            'lease' => 'not-a-valid-lease',
            'client_attempt_id' => (string) Str::uuid(),
            'check_id' => 'daily-check',
            'selected_option_id' => 'option-one',
            'local_recording' => null,
        ];

        $this->withHeaders($offlineHeaders)
            ->postJson('/api/v1/offline-completions', $offlineBody)
            ->assertNotFound()
            ->assertJsonPath('message_key', 'problem.not_found');

        $rawOfflineAudioRejected = $this
            ->withHeaders($offlineHeaders)
            ->postJson('/api/v1/offline-completions', [
                ...$offlineBody,
                'raw_audio' => 'offline-audio-must-not-enter-api',
                'knowledge_score' => 100,
            ])
            ->assertUnprocessable();
        self::assertStringNotContainsString(
            'offline-audio-must-not-enter-api',
            (string) $rawOfflineAudioRejected->getContent(),
        );
        $this->assertNoStore($rawOfflineAudioRejected);
    }

    private function signInWeb(): void
    {
        $mobileNumber = '+919876543218';
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

final class LearnerHttpFixedOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private readonly string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class LearnerHttpNullOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    public function send(
        string $normalizedMobileNumber,
        string $code,
    ): void {
        //
    }
}

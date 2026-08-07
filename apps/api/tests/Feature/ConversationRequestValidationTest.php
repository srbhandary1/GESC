<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Conversation\ConversationTurnStateMachine;
use App\Http\Requests\Conversation\CreateConversationTurnRequest;
use App\Http\Requests\Conversation\StartConversationSessionRequest;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

final class ConversationRequestValidationTest extends TestCase
{
    public function test_session_request_is_strict_and_header_idempotent(): void
    {
        $valid = StartConversationSessionRequest::create(
            '/api/v1/conversation-sessions',
            'POST',
            [
                'persona_key' => 'patient-friend-prototype',
                'scenario_key' => 'everyday-friend-prototype',
                'coaching_mode' => 'gentle',
                'ui_locale' => 'kn-IN',
            ],
        );
        $this->assertFalse($this->validator($valid)->fails());

        $tutor = StartConversationSessionRequest::create(
            '/api/v1/conversation-sessions',
            'POST',
            [
                'persona_key' => 'patient-friend-prototype',
                'scenario_key' => 'everyday-friend-prototype',
                'coaching_mode' => 'tutor',
                'ui_locale' => 'kn-IN',
            ],
        );
        $this->assertFalse($this->validator($tutor)->fails());

        $unsupported = StartConversationSessionRequest::create(
            '/api/v1/conversation-sessions',
            'POST',
            [
                'persona_key' => 'patient-friend-prototype',
                'scenario_key' => 'everyday-friend-prototype',
                'coaching_mode' => 'gentle',
                'ui_locale' => 'kn-IN',
                'organization_id' => (string) Str::ulid(),
            ],
        );
        $this->assertTrue($this->validator($unsupported)->fails());

        $realProviderField = StartConversationSessionRequest::create(
            '/api/v1/conversation-sessions',
            'POST',
            [
                'persona_key' => 'patient-friend-prototype',
                'scenario_key' => 'everyday-friend-prototype',
                'coaching_mode' => 'gentle',
                'ui_locale' => 'kn-IN',
                'provider' => 'external-model',
            ],
        );
        $this->assertTrue($this->validator($realProviderField)->fails());
    }

    public function test_turn_request_accepts_only_bounded_text(): void
    {
        $valid = CreateConversationTurnRequest::create(
            '/api/v1/conversation-sessions/example/turns',
            'POST',
            ['prototype_utterance' => 'I enjoy learning English.'],
        );
        $this->assertFalse($this->validator($valid)->fails());

        $blank = CreateConversationTurnRequest::create(
            '/api/v1/conversation-sessions/example/turns',
            'POST',
            ['prototype_utterance' => '   '],
        );
        $this->assertTrue($this->validator($blank)->fails());

        $audio = CreateConversationTurnRequest::create(
            '/api/v1/conversation-sessions/example/turns',
            'POST',
            [
                'prototype_utterance' => 'Safe fixture.',
                'raw_audio' => 'not-allowed',
            ],
        );
        $this->assertTrue($this->validator($audio)->fails());
    }

    public function test_turn_state_machine_rejects_skips_and_terminal_retries(): void
    {
        $machine = new ConversationTurnStateMachine;

        foreach ([
            ['accepted', 'transcribing'],
            ['transcribing', 'generating_reply'],
            ['generating_reply', 'synthesizing'],
            ['synthesizing', 'completed'],
        ] as [$from, $to]) {
            $machine->assertTransition($from, $to);
            $this->addToAssertionCount(1);
        }

        foreach ([
            ['accepted', 'completed'],
            ['completed', 'transcribing'],
            ['failed', 'transcribing'],
        ] as [$from, $to]) {
            try {
                $machine->assertTransition($from, $to);
                self::fail(sprintf(
                    'Expected %s to %s to fail.',
                    $from,
                    $to,
                ));
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function validator(
        StartConversationSessionRequest|CreateConversationTurnRequest $request,
    ): ValidatorContract {
        $request->headers->set(
            'Idempotency-Key',
            (string) Str::uuid(),
        );
        $validator = Validator::make(
            $request->validationData(),
            $request->rules(),
        );
        $request->withValidator($validator);

        return $validator;
    }
}

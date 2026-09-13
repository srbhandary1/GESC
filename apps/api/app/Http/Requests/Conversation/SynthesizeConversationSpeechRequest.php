<?php

declare(strict_types=1);

namespace App\Http\Requests\Conversation;

use App\Conversation\Support\GuidanceTtsPolicy;
use App\Http\Requests\IdempotentRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

final class SynthesizeConversationSpeechRequest extends IdempotentRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function payloadRules(): array
    {
        return [
            'purpose' => [
                'required',
                'string',
                Rule::in(GuidanceTtsPolicy::purposes()),
            ],
            'text' => [
                'required',
                'string',
                'min:1',
                'max:'.min(
                    (int) config('conversation.maximum_utterance_characters', 500),
                    (int) config('conversation.guidance_tts.maximum_characters', 400),
                ),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                array_diff(
                    array_keys($this->all()),
                    ['purpose', 'text'],
                ) !== []
            ) {
                $validator->errors()->add(
                    'body',
                    'The request contains unsupported fields.',
                );
            }

            $text = $this->input('text');

            if (is_string($text) && trim($text) === '') {
                $validator->errors()->add(
                    'text',
                    'The speech text must not be blank.',
                );
            }
        });
    }
}

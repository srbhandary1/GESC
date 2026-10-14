<?php

declare(strict_types=1);

namespace App\Http\Requests\Conversation;

use App\Conversation\Support\ConversationUiLocales;
use App\Http\Requests\IdempotentRequest;
use Illuminate\Contracts\Validation\Validator;

final class CreateConversationTurnRequest extends IdempotentRequest
{
    /**
     * @return array<string, list<string>>
     */
    protected function payloadRules(): array
    {
        return [
            'action' => ['sometimes', 'string', 'in:reply,retry,question,confused,accept_explanation,decline_explanation'],
            'explanation_target' => ['sometimes', 'required', 'string', 'regex:/\A(opening|[0-9A-HJKMNP-TV-Z]{26})\z/'],
            'expected_revision' => ['required_with:action', 'integer', 'min:0'],
            'voice_recording_id' => ['sometimes', 'ulid'],
            'transcript_source' => ['sometimes', 'string', 'in:typed,original_speech,translated_speech'],
            'transcript_locale' => ['sometimes', 'string', 'in:'.implode(',', [...ConversationUiLocales::all(), 'unknown'])],
            'prototype_utterance' => [
                'required',
                'string',
                'min:1',
                'max:'.(int) config(
                    'conversation.maximum_utterance_characters',
                    500,
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
                    ['prototype_utterance', 'action', 'explanation_target', 'expected_revision', 'voice_recording_id', 'transcript_source', 'transcript_locale'],
                ) !== []
            ) {
                $validator->errors()->add(
                    'body',
                    'The request contains unsupported fields.',
                );
            }

            $utterance = $this->input('prototype_utterance');

            if (
                is_string($utterance)
                && trim($utterance) === ''
            ) {
                $validator->errors()->add(
                    'prototype_utterance',
                    'The prototype utterance must not be blank.',
                );
            }
        });
    }
}

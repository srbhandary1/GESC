<?php

declare(strict_types=1);

namespace App\Http\Requests\Conversation;

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
                    ['prototype_utterance'],
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

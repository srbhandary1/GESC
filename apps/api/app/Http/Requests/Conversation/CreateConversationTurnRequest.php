<?php

declare(strict_types=1);

namespace App\Http\Requests\Conversation;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class CreateConversationTurnRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
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
            'idempotency_key' => [
                'required',
                'string',
                'regex:/\A(?:[0-9A-HJKMNP-TV-Z]{26}|[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})\z/',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return [
            ...parent::validationData(),
            'idempotency_key' => $this->header('Idempotency-Key'),
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

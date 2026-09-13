<?php

declare(strict_types=1);

namespace App\Http\Requests\Conversation;

use App\Conversation\Support\ConversationUiLocales;
use App\Http\Requests\IdempotentRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

final class StartConversationSessionRequest extends IdempotentRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function payloadRules(): array
    {
        return [
            'persona_key' => [
                'required',
                'string',
                'max:100',
                'regex:/\A[a-z0-9][a-z0-9._-]{0,99}\z/',
            ],
            'scenario_key' => [
                'required',
                'string',
                'max:100',
                'regex:/\A[a-z0-9][a-z0-9._-]{0,99}\z/',
            ],
            'coaching_mode' => [
                'required',
                'string',
                Rule::in(['gentle', 'tutor', 'fluency']),
            ],
            'ui_locale' => [
                'required',
                'string',
                Rule::in(ConversationUiLocales::all()),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                array_diff(
                    array_keys($this->all()),
                    [
                        'persona_key',
                        'scenario_key',
                        'coaching_mode',
                        'ui_locale',
                    ],
                ) !== []
            ) {
                $validator->errors()->add(
                    'body',
                    'The request contains unsupported fields.',
                );
            }
        });
    }
}

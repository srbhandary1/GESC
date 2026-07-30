<?php

declare(strict_types=1);

namespace App\Http\Requests\Conversation;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StartConversationSessionRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
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
                Rule::in(['gentle']),
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
                    ['persona_key', 'scenario_key', 'coaching_mode'],
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

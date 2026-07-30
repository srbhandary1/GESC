<?php

declare(strict_types=1);

namespace App\Http\Requests\Learner;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CompleteOnboardingRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'adult_confirmed' => [
                'required',
                'boolean',
                'accepted',
            ],
            'goal_key' => [
                'required',
                'string',
                Rule::in(config('learning.goal_keys', [])),
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
            if ($this->input('adult_confirmed') !== true) {
                $validator->errors()->add(
                    'adult_confirmed',
                    'Adult confirmation must be the boolean true.',
                );
            }

            if (
                array_diff(
                    array_keys($this->all()),
                    ['adult_confirmed', 'goal_key'],
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

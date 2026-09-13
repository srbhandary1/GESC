<?php

declare(strict_types=1);

namespace App\Http\Requests\Learner;

use App\Http\Requests\IdempotentRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

final class CompleteOnboardingRequest extends IdempotentRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function payloadRules(): array
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

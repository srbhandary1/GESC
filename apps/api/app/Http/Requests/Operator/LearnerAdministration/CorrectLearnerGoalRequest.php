<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\LearnerAdministration;

use Illuminate\Validation\Rule;

final class CorrectLearnerGoalRequest extends StrictLearnerAdministrationCommandRequest
{
    protected function payloadRules(): array
    {
        return [
            'goal_key' => [
                'required',
                'string',
                Rule::in((array) config('learning.goal_keys', [])),
            ],
            'expected_version' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return ['goal_key', 'expected_version'];
    }
}

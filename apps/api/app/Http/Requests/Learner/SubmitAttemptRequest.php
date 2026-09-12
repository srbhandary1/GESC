<?php

declare(strict_types=1);

namespace App\Http\Requests\Learner;

use App\Http\Requests\IdempotentRequest;
use Illuminate\Contracts\Validation\Validator;

final class SubmitAttemptRequest extends IdempotentRequest
{
    /**
     * @return array<string, list<string>>
     */
    protected function payloadRules(): array
    {
        return [
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->all() !== []) {
                $validator->errors()->add(
                    'body',
                    'The request body must be empty.',
                );
            }
        });
    }
}

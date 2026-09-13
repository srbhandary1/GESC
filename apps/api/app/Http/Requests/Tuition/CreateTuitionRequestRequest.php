<?php

declare(strict_types=1);

namespace App\Http\Requests\Tuition;

use App\Http\Requests\IdempotentRequest;
use Illuminate\Contracts\Validation\Validator;

final class CreateTuitionRequestRequest extends IdempotentRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function payloadRules(): array
    {
        return [
            'preferred_window' => ['required', 'string', 'min:3', 'max:120'],
            'topic' => ['required', 'string', 'min:3', 'max:200'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['preferred_window', 'topic', 'notes'];
            if (array_diff(array_keys($this->all()), $allowed) !== []) {
                $validator->errors()->add(
                    'body',
                    'The request contains unsupported fields.',
                );
            }
        });
    }
}

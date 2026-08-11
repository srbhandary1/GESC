<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator;

use App\Http\Requests\IdempotentRequest;
use Illuminate\Contracts\Validation\Validator;

final class CreatePrivacyErasureRequest extends IdempotentRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function payloadRules(): array
    {
        return [
            'target_membership_id' => ['required', 'string', 'ulid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                array_diff(
                    array_keys($this->all()),
                    ['target_membership_id'],
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

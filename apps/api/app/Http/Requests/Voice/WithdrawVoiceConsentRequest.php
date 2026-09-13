<?php

declare(strict_types=1);

namespace App\Http\Requests\Voice;

use App\Http\Requests\IdempotentRequest;
use Illuminate\Contracts\Validation\Validator;

final class WithdrawVoiceConsentRequest extends IdempotentRequest
{
    /**
     * @return array<string, list<string>>
     */
    protected function payloadRules(): array
    {
        return [
            'requested_locale' => [
                'required',
                'string',
                'in:en-IN,kn-IN,ml-IN,ta-IN,te-IN,hi-IN',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                array_diff(
                    array_keys($this->all()),
                    ['requested_locale'],
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

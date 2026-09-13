<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator;

use App\Http\Requests\IdempotentRequest;
use Illuminate\Contracts\Validation\Validator;

abstract class StrictSpeechEconomicsCommandRequest extends IdempotentRequest
{
    /** @return list<string> */
    abstract protected function allowedPayloadKeys(): array;

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), $this->allowedPayloadKeys()) !== []) {
                $validator->errors()->add('body', 'The request contains unsupported fields.');
            }
        });
    }
}

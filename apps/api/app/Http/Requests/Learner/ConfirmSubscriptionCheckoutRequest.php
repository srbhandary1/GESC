<?php

declare(strict_types=1);

namespace App\Http\Requests\Learner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ConfirmSubscriptionCheckoutRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'payment_id' => ['required', 'string', 'regex:/\Apay_[A-Za-z0-9]{8,64}\z/'],
            'signature' => ['required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), ['payment_id', 'signature']) !== []) {
                $validator->errors()->add('body', 'The request contains unsupported fields.');
            }
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;

final class AcceptInstitutionInviteRequest extends IdempotentRequest
{
    /**
     * @return array<string, list<string>>
     */
    protected function payloadRules(): array
    {
        return [
            'code' => ['required', 'string', 'min:12', 'max:128'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $unexpected = array_diff(
                array_keys($this->all()),
                ['code'],
            );

            if ($unexpected !== []) {
                $validator->errors()->add(
                    'body',
                    'The request contains unsupported fields.',
                );
            }
        });
    }
}

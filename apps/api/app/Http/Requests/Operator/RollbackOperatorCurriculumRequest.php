<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator;

use App\Http\Requests\IdempotentRequest;
use Illuminate\Contracts\Validation\Validator;

final class RollbackOperatorCurriculumRequest extends IdempotentRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function payloadRules(): array
    {
        return [
            'program_id' => ['required', 'string', 'ulid'],
            'target_publication_id' => ['required', 'string', 'ulid'],
            'expected_head_version' => [
                'required',
                'integer',
                'min:1',
                'max:2147483647',
            ],
            'reason_key' => [
                'required',
                'string',
                'regex:/\A[a-z][a-z0-9_]{0,119}\z/',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                array_diff(
                    array_keys($this->all()),
                    [
                        'program_id',
                        'target_publication_id',
                        'expected_head_version',
                        'reason_key',
                    ],
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

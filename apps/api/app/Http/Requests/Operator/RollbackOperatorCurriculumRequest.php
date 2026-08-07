<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class RollbackOperatorCurriculumRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
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
            'idempotency_key' => [
                'required',
                'string',
                'regex:/\A(?:[0-9A-HJKMNP-TV-Z]{26}|[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})\z/',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return [
            ...parent::validationData(),
            'idempotency_key' => $this->header('Idempotency-Key'),
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

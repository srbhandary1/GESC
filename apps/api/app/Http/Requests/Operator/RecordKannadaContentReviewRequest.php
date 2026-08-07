<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RecordKannadaContentReviewRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'source_revision_id' => ['required', 'string', 'ulid'],
            'source_content_hash' => [
                'required',
                'string',
                'regex:/\A[0-9a-f]{64}\z/',
            ],
            'localized_variant_id' => ['required', 'string', 'ulid'],
            'localized_content_hash' => [
                'required',
                'string',
                'regex:/\A[0-9a-f]{64}\z/',
            ],
            'authority_appointment_id' => ['required', 'string', 'ulid'],
            'decision' => [
                'required',
                'string',
                Rule::in(['approved', 'rejected']),
            ],
            'rationale_key' => [
                'sometimes',
                'nullable',
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
                        'source_revision_id',
                        'source_content_hash',
                        'localized_variant_id',
                        'localized_content_hash',
                        'authority_appointment_id',
                        'decision',
                        'rationale_key',
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

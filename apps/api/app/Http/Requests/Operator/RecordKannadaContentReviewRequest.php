<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator;

use App\Http\Requests\IdempotentRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

final class RecordKannadaContentReviewRequest extends IdempotentRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function payloadRules(): array
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
                'required',
                'string',
                Rule::in(['meets_standard', 'language_revision_required']),
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

            $decision = $this->input('decision');
            $rationale = $this->input('rationale_key');

            if (
                ($decision === 'approved' && $rationale !== 'meets_standard')
                || ($decision === 'rejected'
                    && $rationale !== 'language_revision_required')
            ) {
                $validator->errors()->add(
                    'rationale_key',
                    'The rationale is not valid for this Kannada decision.',
                );
            }
        });
    }
}

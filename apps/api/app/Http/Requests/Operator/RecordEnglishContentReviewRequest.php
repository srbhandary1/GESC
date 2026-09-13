<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator;

use App\Http\Requests\IdempotentRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

final class RecordEnglishContentReviewRequest extends IdempotentRequest
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
            'authority_appointment_id' => ['required', 'string', 'ulid'],
            'decision' => [
                'required',
                'string',
                Rule::in(['approved', 'rejected']),
            ],
            'rationale_key' => [
                'required',
                'string',
                Rule::in([
                    'meets_standard',
                    'accuracy_revision_required',
                    'english_only_justified',
                ]),
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
                ($decision === 'approved' && ! in_array(
                    $rationale,
                    ['meets_standard', 'english_only_justified'],
                    true,
                ))
                || ($decision === 'rejected'
                    && $rationale !== 'accuracy_revision_required')
            ) {
                $validator->errors()->add(
                    'rationale_key',
                    'The rationale is not valid for this English decision.',
                );
            }
        });
    }
}

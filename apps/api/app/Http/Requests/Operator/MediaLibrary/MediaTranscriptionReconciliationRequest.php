<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\MediaLibrary;

use App\Http\Requests\IdempotentRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

final class MediaTranscriptionReconciliationRequest extends IdempotentRequest
{
    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['outcome', 'operator_label', 'corrected_transcript'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $values[$field] = trim($value);
            }
        }
        if ($values !== []) {
            $this->merge($values);
        }
    }

    /** @return array<string, list<mixed>> */
    protected function payloadRules(): array
    {
        return [
            'expected_asset_version' => ['required', 'integer', 'min:1'],
            'candidate_sha256' => [
                'required', 'string', 'regex:/\A[0-9a-f]{64}\z/',
            ],
            'outcome' => [
                'required',
                Rule::in(['confirmed', 'corrected', 'discarded', 'needs_attention']),
            ],
            'operator_label' => [
                'nullable', 'string', 'max:160',
                'required_if:outcome,confirmed,corrected',
            ],
            'corrected_transcript' => [
                'nullable', 'string', 'max:65536',
                'required_if:outcome,corrected',
            ],
            'expected_head_revision_id' => ['nullable', 'string', 'ulid'],
            'expected_metadata_version' => ['required', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = [
                'expected_asset_version', 'candidate_sha256', 'outcome',
                'operator_label', 'corrected_transcript',
                'expected_head_revision_id', 'expected_metadata_version',
            ];
            if (array_diff(array_keys($this->request->all()), $allowed) !== []) {
                $validator->errors()->add('body', 'The request contains unsupported fields.');
            }
            if ($this->query() !== []) {
                $validator->errors()->add('query', 'Command endpoints do not accept query parameters.');
            }
            if (! $this->isJson()) {
                $validator->errors()->add('content_type', 'Media commands require application/json.');
            }
            $outcome = $this->input('outcome');
            if (
                in_array($outcome, ['discarded', 'needs_attention'], true)
                && ($this->input('operator_label') !== null
                    || $this->input('corrected_transcript') !== null)
            ) {
                $validator->errors()->add('outcome', 'The outcome contains unsupported authoring fields.');
            }
            if (
                $outcome === 'confirmed'
                && $this->input('corrected_transcript') !== null
            ) {
                $validator->errors()->add('corrected_transcript', 'Confirmed candidates cannot include corrected text.');
            }
        });
    }
}

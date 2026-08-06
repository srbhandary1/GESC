<?php

declare(strict_types=1);

namespace App\Http\Requests\Memory;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateLearnerMemoryFactRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'fact' => [
                'required',
                'string',
                'regex:/\A[0-9A-HJKMNP-TV-Z]{26}\z/',
            ],
            'fact_text' => [
                'required',
                'string',
                'min:1',
                'max:'.(int) config('memory.maximum_fact_characters', 200),
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
            'fact' => $this->route('fact'),
            'idempotency_key' => $this->header('Idempotency-Key'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                array_diff(array_keys($this->all()), ['fact_text']) !== []
            ) {
                $validator->errors()->add(
                    'body',
                    'The request contains unsupported fields.',
                );
            }

            $text = $this->input('fact_text');

            if (is_string($text) && trim($text) === '') {
                $validator->errors()->add(
                    'fact_text',
                    'The fact text must not be blank.',
                );
            }
        });
    }
}

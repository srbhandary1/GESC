<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class SearchOperatorShellRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $term = $this->query('q');

        if (is_string($term)) {
            $this->query->set('q', trim($term));
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => [
                'required',
                'string',
                'min:2',
                'max:80',
                'not_regex:/[\x00-\x1F\x7F]/u',
            ],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:25'],
            'cursor' => ['sometimes', 'string', 'max:4096'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                array_diff(
                    array_keys($this->query()),
                    ['q', 'limit', 'cursor'],
                ) !== []
            ) {
                $validator->errors()->add(
                    'query',
                    'The request contains unsupported query parameters.',
                );
            }
        });
    }
}

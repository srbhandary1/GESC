<?php

declare(strict_types=1);

namespace App\Http\Requests\Memory;

use App\Http\Requests\IdempotentRequest;
use Illuminate\Contracts\Validation\Validator;

final class CreateLearnerMemoryFactRequest extends IdempotentRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function payloadRules(): array
    {
        return [
            'fact_text' => [
                'required',
                'string',
                'min:1',
                'max:'.(int) config('memory.maximum_fact_characters', 200),
            ],
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

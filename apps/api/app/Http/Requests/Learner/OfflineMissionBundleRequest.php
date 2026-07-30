<?php

declare(strict_types=1);

namespace App\Http\Requests\Learner;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class OfflineMissionBundleRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'enrollment_id' => ['required', 'ulid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                array_diff(
                    array_keys($this->query()),
                    ['enrollment_id'],
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

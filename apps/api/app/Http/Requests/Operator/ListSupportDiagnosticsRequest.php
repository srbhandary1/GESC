<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

final class ListSupportDiagnosticsRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Voice;

use Illuminate\Foundation\Http\FormRequest;

final class ListVoiceConsentsRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'string', 'in:en-IN,kn-IN,ml-IN,ta-IN,te-IN,hi-IN'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Learner;

use Illuminate\Foundation\Http\FormRequest;

final class MediaContentRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'enrollment_id' => ['required', 'ulid'],
            'mission_id' => ['required', 'ulid'],
        ];
    }
}

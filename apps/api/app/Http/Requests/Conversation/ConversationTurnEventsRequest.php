<?php

declare(strict_types=1);

namespace App\Http\Requests\Conversation;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class ConversationTurnEventsRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->query() !== []) {
                $validator->errors()->add(
                    'query',
                    'The request contains unsupported query parameters.',
                );
            }
        });
    }
}

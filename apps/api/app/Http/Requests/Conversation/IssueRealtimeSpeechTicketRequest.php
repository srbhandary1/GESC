<?php

declare(strict_types=1);

namespace App\Http\Requests\Conversation;

use App\Http\Requests\IdempotentRequest;
use Illuminate\Contracts\Validation\Validator;

final class IssueRealtimeSpeechTicketRequest extends IdempotentRequest
{
    /**
     * @return array<string, list<string>>
     */
    protected function payloadRules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (array_keys($this->all()) !== []) {
                $validator->errors()->add(
                    'body',
                    'The request contains unsupported fields.',
                );
            }
        });
    }
}

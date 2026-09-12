<?php

declare(strict_types=1);

namespace App\Http\Requests\Voice;

use App\Http\Requests\IdempotentRequest;
use Illuminate\Contracts\Validation\Validator;

final class ReviewVoiceTranscriptRequest extends IdempotentRequest
{
    /**
     * @return array<string, list<string>>
     */
    protected function payloadRules(): array
    {
        return [
            'action' => ['required', 'string', 'in:accept,correct,discard'],
            'corrected_transcript' => [
                'nullable',
                'string',
                'max:500',
                'required_if:action,correct',
                'prohibited_unless:action,correct',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                array_diff(
                    array_keys($this->all()),
                    ['action', 'corrected_transcript'],
                ) !== []
            ) {
                $validator->errors()->add(
                    'body',
                    'The request contains unsupported fields.',
                );
            }

            if (
                $this->input('action') === 'correct'
                && trim((string) $this->input('corrected_transcript')) === ''
            ) {
                $validator->errors()->add(
                    'corrected_transcript',
                    'The corrected transcript must not be blank.',
                );
            }
        });
    }
}

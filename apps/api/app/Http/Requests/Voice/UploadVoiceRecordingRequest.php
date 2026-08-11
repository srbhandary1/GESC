<?php

declare(strict_types=1);

namespace App\Http\Requests\Voice;

use App\Http\Requests\IdempotentRequest;
use Illuminate\Contracts\Validation\Validator;

final class UploadVoiceRecordingRequest extends IdempotentRequest
{
    /**
     * @return array<string, list<string>>
     */
    protected function payloadRules(): array
    {
        return [
            'audio' => ['required', 'file', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), ['audio']) !== []) {
                $validator->errors()->add(
                    'body',
                    'The request contains unsupported fields.',
                );
            }
        });
    }
}

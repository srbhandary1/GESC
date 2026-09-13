<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\MediaLibrary;

use App\Media\MediaKindPolicy;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MediaReadRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:25'],
            'cursor' => ['sometimes', 'string', 'max:4096'],
            'kind' => ['sometimes', Rule::in(MediaKindPolicy::SUPPORTED)],
            'state' => [
                'sometimes',
                'string',
                Rule::in([
                    'active', 'retired', 'uploading', 'finalized', 'scan_clean',
                    'accepted', 'rejected', 'expired', 'inspection_failed',
                    'scan_failed', 'promotion_failed',
                ]),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = match ($this->route()?->getName()) {
                'api.v1.operator.media.assets.index' => [
                    'limit', 'cursor', 'kind', 'state',
                ],
                'api.v1.operator.media.uploads.index' => [
                    'limit', 'cursor', 'state',
                ],
                default => [],
            };

            if (array_diff(array_keys($this->query()), $allowed) !== []) {
                $validator->errors()->add(
                    'query',
                    'The request contains unsupported query parameters.',
                );
            }
        });
    }
}

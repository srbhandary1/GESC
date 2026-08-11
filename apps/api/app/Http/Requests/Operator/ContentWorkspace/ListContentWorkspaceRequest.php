<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\ContentWorkspace;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListContentWorkspaceRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $routeName = $this->route()?->getName();

        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:25'],
            'cursor' => ['sometimes', 'string', 'max:4096'],
            'document_key' => [
                $routeName === 'api.v1.operator.content.workspaces.preview.show'
                    ? 'required'
                    : 'sometimes',
                'string',
                'max:120',
                'regex:/\A[a-z0-9][a-z0-9._-]*\z/',
            ],
            'queue' => [
                $routeName === 'api.v1.operator.content.review-queues.index'
                    ? 'required'
                    : 'sometimes',
                'string',
                Rule::in(['structure', 'english', 'kannada', 'readiness']),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = match ($this->route()?->getName()) {
                'api.v1.operator.content.workspaces.index',
                'api.v1.operator.content.workspaces.history.index' => [
                    'limit',
                    'cursor',
                ],
                'api.v1.operator.content.workspaces.preview.show' => [
                    'document_key',
                ],
                'api.v1.operator.content.review-queues.index' => [
                    'queue',
                    'limit',
                    'cursor',
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

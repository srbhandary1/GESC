<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\OrganizationAdministration;

use Illuminate\Contracts\Validation\Validator;

final class CreateRoleRequest extends StrictOrganizationAdministrationCommandRequest
{
    protected function prepareForValidation(): void
    {
        $this->trimStringFields(['key', 'name']);
    }

    protected function payloadRules(): array
    {
        return [
            'key' => ['required', 'string', 'max:80', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
            'name' => ['required', 'string', 'max:120', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'permission_keys' => ['required', 'array', 'max:100'],
            'permission_keys.*' => [
                'required', 'string', 'distinct:strict', 'max:120',
                'regex:/\A[a-z][a-z0-9_.-]*\z/',
            ],
            'expected_version' => ['required', 'integer', 'in:0'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return ['key', 'name', 'permission_keys', 'expected_version'];
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator): void {
            $keys = $this->input('permission_keys');

            if (! is_array($keys)) {
                return;
            }

            $sorted = $keys;
            sort($sorted, SORT_STRING);

            if ($sorted !== $keys) {
                $validator->errors()->add(
                    'permission_keys',
                    'Permission keys must be sorted.',
                );
            }
        });
    }
}

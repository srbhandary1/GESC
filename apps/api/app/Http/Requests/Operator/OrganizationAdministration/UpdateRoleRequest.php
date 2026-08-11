<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\OrganizationAdministration;

use Illuminate\Contracts\Validation\Validator;

final class UpdateRoleRequest extends StrictOrganizationAdministrationCommandRequest
{
    protected function prepareForValidation(): void
    {
        $this->trimStringFields(['name']);
    }

    protected function payloadRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'permission_keys' => ['required', 'array', 'max:100'],
            'permission_keys.*' => [
                'required', 'string', 'distinct:strict', 'max:120',
                'regex:/\A[a-z][a-z0-9_.-]*\z/',
            ],
            'expected_version' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return ['name', 'permission_keys', 'expected_version'];
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

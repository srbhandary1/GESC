<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\OrganizationAdministration;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListOrganizationAdministrationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $prefix = $this->query('display_name_prefix');

        if (is_string($prefix)) {
            $this->query->set('display_name_prefix', trim($prefix));
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:25'],
            'cursor' => ['sometimes', 'string', 'max:4096'],
            'status' => [
                'sometimes',
                'string',
                Rule::in($this->allowedStatuses()),
            ],
            'branch_id' => ['sometimes', 'string', 'ulid'],
            'display_name_prefix' => [
                'sometimes',
                'string',
                'min:1',
                'max:80',
                'not_regex:/[%_\x00-\x1F\x7F]/u',
            ],
            'authority_kind' => [
                'sometimes',
                'string',
                'in:english_academic_reviewer,kannada_reviewer,rights_confirmer',
            ],
            'state' => [
                'sometimes',
                'string',
                'in:pending,active,surrendered,revoked,expired',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = match ($this->route()?->getName()) {
                'api.v1.operator.branches.index' => ['limit', 'cursor', 'status'],
                'api.v1.operator.cohorts.index' => ['limit', 'cursor', 'status', 'branch_id'],
                'api.v1.operator.memberships.index',
                'api.v1.operator.trainer-profiles.index' => [
                    'limit', 'cursor', 'status', 'display_name_prefix',
                ],
                'api.v1.operator.institution-invites.index',
                'api.v1.operator.roles.index' => ['limit', 'cursor', 'status'],
                'api.v1.operator.trainer-profiles.authority-appointments.index' => [
                    'limit', 'cursor', 'authority_kind', 'state',
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

    /**
     * @return list<string>
     */
    private function allowedStatuses(): array
    {
        return match ($this->route()?->getName()) {
            'api.v1.operator.branches.index',
            'api.v1.operator.cohorts.index' => ['active', 'archived'],
            'api.v1.operator.memberships.index',
            'api.v1.operator.trainer-profiles.index' => [
                'active', 'suspended', 'ended',
            ],
            'api.v1.operator.institution-invites.index' => [
                'pending', 'accepted', 'revoked', 'expired',
            ],
            'api.v1.operator.roles.index' => ['active', 'retired'],
            default => [],
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\OrganizationAdministration;

final class ChangeMembershipRoleRequest extends StrictOrganizationAdministrationCommandRequest
{
    protected function payloadRules(): array
    {
        return [
            'role_id' => ['required', 'string', 'ulid'],
            'expected_membership_version' => [
                'required', 'integer', 'min:1', 'max:2147483647',
            ],
            'expected_role_version' => [
                'required', 'integer', 'min:1', 'max:2147483647',
            ],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return ['role_id', 'expected_membership_version', 'expected_role_version'];
    }
}

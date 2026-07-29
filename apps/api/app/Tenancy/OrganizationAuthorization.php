<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class OrganizationAuthorization
{
    public function __construct(
        private OrganizationContextManager $contexts,
    ) {}

    public function allows(
        User $user,
        string $permission,
        ?OrganizationOwned $resource = null,
    ): bool {
        $context = $this->contexts->currentOrNull();

        if (
            $context === null
            || $context->membershipId === null
            || $context->actorUserId !== $user->getKey()
            || $user->status !== User::STATUS_ACTIVE
            || (
                $resource !== null
                && $resource->organizationId() !== $context->organizationId
            )
        ) {
            return false;
        }

        return DB::table('memberships as memberships')
            ->join(
                'organizations as organizations',
                'organizations.id',
                '=',
                'memberships.organization_id',
            )
            ->join('membership_roles as membership_roles', function ($join): void {
                $join
                    ->on('membership_roles.organization_id', '=', 'memberships.organization_id')
                    ->on('membership_roles.membership_id', '=', 'memberships.id');
            })
            ->join('roles as roles', function ($join): void {
                $join
                    ->on('roles.organization_id', '=', 'membership_roles.organization_id')
                    ->on('roles.id', '=', 'membership_roles.role_id');
            })
            ->join('role_permissions as role_permissions', function ($join): void {
                $join
                    ->on('role_permissions.organization_id', '=', 'roles.organization_id')
                    ->on('role_permissions.role_id', '=', 'roles.id');
            })
            ->join('permissions as permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('memberships.organization_id', $context->organizationId)
            ->where('memberships.id', $context->membershipId)
            ->where('memberships.user_id', $user->getKey())
            ->where('memberships.status', 'active')
            ->where('organizations.status', 'active')
            ->where('permissions.key', $permission)
            ->exists();
    }
}

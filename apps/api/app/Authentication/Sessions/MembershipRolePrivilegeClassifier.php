<?php

declare(strict_types=1);

namespace App\Authentication\Sessions;

use App\Tenancy\PermissionCatalogue;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class MembershipRolePrivilegeClassifier implements PrivilegeClassifier
{
    public function isPrivileged(Authenticatable $user): bool
    {
        return DB::table('memberships')
            ->join('membership_roles', function ($join): void {
                $join
                    ->on('membership_roles.organization_id', '=', 'memberships.organization_id')
                    ->on('membership_roles.membership_id', '=', 'memberships.id');
            })
            ->join('roles', function ($join): void {
                $join
                    ->on('roles.organization_id', '=', 'membership_roles.organization_id')
                    ->on('roles.id', '=', 'membership_roles.role_id');
            })
            ->join('role_permissions', function ($join): void {
                $join
                    ->on('role_permissions.organization_id', '=', 'roles.organization_id')
                    ->on('role_permissions.role_id', '=', 'roles.id');
            })
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('memberships.user_id', (string) $user->getAuthIdentifier())
            ->where('memberships.status', 'active')
            ->where('roles.status', 'active')
            ->whereIn('permissions.key', PermissionCatalogue::keys())
            ->where('permissions.key', '<>', 'learning.participate')
            ->exists();
    }
}

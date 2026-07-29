<?php

declare(strict_types=1);

namespace App\Authentication\Sessions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class MembershipRolePrivilegeClassifier implements PrivilegeClassifier
{
    public function isPrivileged(Authenticatable $user): bool
    {
        $roleKeys = config('authentication.privileged.role_keys', []);

        if (! is_array($roleKeys) || $roleKeys === []) {
            return false;
        }

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
            ->where('memberships.user_id', (string) $user->getAuthIdentifier())
            ->where('memberships.status', 'active')
            ->whereIn('roles.key', $roleKeys)
            ->exists();
    }
}

<?php

declare(strict_types=1);

namespace App\Authentication\Sessions;

use Illuminate\Contracts\Auth\Authenticatable;

final readonly class PlatformOrMembershipPrivilegeClassifier implements PrivilegeClassifier
{
    public function __construct(
        private MembershipRolePrivilegeClassifier $memberships,
    ) {}

    public function isPrivileged(Authenticatable $user): bool
    {
        $userId = (string) $user->getAuthIdentifier();

        foreach ([
            config('platform.account_recovery.manager_user_ids', []),
            config('platform.content_emergency.actor_user_ids', []),
        ] as $configuredActorIds) {
            if (is_array($configuredActorIds)
                && in_array($userId, $configuredActorIds, true)) {
                return true;
            }
        }

        return $this->memberships->isPrivileged($user);
    }
}

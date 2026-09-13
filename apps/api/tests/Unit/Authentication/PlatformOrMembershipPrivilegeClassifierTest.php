<?php

declare(strict_types=1);

namespace Tests\Unit\Authentication;

use App\Authentication\Sessions\MembershipRolePrivilegeClassifier;
use App\Authentication\Sessions\PlatformOrMembershipPrivilegeClassifier;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PlatformOrMembershipPrivilegeClassifierTest extends TestCase
{
    public function test_explicit_platform_actor_configuration_is_privileged_without_membership_lookup(): void
    {
        $recoveryActor = $this->unsavedUser();
        $emergencyActor = $this->unsavedUser();
        config()->set('platform.account_recovery.manager_user_ids', [
            (string) $recoveryActor->getKey(),
        ]);
        config()->set('platform.content_emergency.actor_user_ids', [
            (string) $emergencyActor->getKey(),
        ]);
        $classifier = new PlatformOrMembershipPrivilegeClassifier(
            new MembershipRolePrivilegeClassifier,
        );

        self::assertTrue($classifier->isPrivileged($recoveryActor));
        self::assertTrue($classifier->isPrivileged($emergencyActor));
    }

    private function unsavedUser(): User
    {
        $user = new User;
        $user->forceFill(['id' => (string) Str::ulid()]);

        return $user;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Sessions\MembershipRolePrivilegeClassifier;
use App\Authentication\Sessions\PrivilegeClassifier;
use App\Models\Membership;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

final class PermissionDerivedPrivilegeClassifierTest extends TenancyIntegrationTestCase
{
    public function test_role_name_and_membership_type_do_not_make_a_user_privileged(): void
    {
        $organization = $this->createOrganization('Role Name Privilege Tenant');
        $user = $this->createUser();
        [$membership, $context] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $role = $this->createRole($context, 'organization_admin');
        DB::table('membership_roles')->insert([
            'organization_id' => $organization->getKey(),
            'membership_id' => $membership->getKey(),
            'role_id' => $role->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::assertFalse($this->classifier()->isPrivileged($user));
    }

    public function test_learning_participation_alone_is_not_privileged(): void
    {
        $organization = $this->createOrganization('Learner Privilege Tenant');
        $user = $this->createUser();
        [$membership, $context] = $this->createMembership(
            $organization,
            $user,
        );
        $this->grantTestPermissions(
            $membership,
            $context,
            ['learning.participate'],
        );

        self::assertFalse($this->classifier()->isPrivileged($user));
    }

    public function test_database_permission_outside_code_owned_catalogue_is_not_privileged(): void
    {
        $organization = $this->createOrganization('Uncatalogued Privilege Tenant');
        $user = $this->createUser();
        [$membership, $context] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_TRAINER,
        );
        $role = $this->createRole($context, 'uncatalogued-authority');
        $permission = Permission::query()->create([
            'key' => 'unreviewed.permission',
            'description' => 'Synthetic permission outside the code-owned catalogue.',
        ]);
        DB::table('membership_roles')->insert([
            'organization_id' => $organization->getKey(),
            'membership_id' => $membership->getKey(),
            'role_id' => $role->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('role_permissions')->insert([
            'organization_id' => $organization->getKey(),
            'role_id' => $role->getKey(),
            'permission_id' => $permission->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::assertFalse($this->classifier()->isPrivileged($user));
    }

    public function test_any_other_seeded_permission_on_a_custom_role_is_privileged(): void
    {
        $organization = $this->createOrganization('Custom Privilege Tenant');
        $user = $this->createUser();
        [$membership, $context] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_TRAINER,
        );
        $this->grantTestPermissions(
            $membership,
            $context,
            ['branches.read'],
            'custom-privilege-role',
        );

        self::assertTrue($this->classifier()->isPrivileged($user));

        DB::table('memberships')
            ->where('id', $membership->getKey())
            ->update([
                'status' => Membership::STATUS_SUSPENDED,
                'updated_at' => now(),
            ]);

        self::assertFalse($this->classifier()->isPrivileged($user));
    }

    public function test_configured_platform_actors_are_privileged_without_organization_grants(): void
    {
        $recoveryActor = $this->createUser();
        $emergencyActor = $this->createUser();
        config()->set('platform.account_recovery.manager_user_ids', [
            (string) $recoveryActor->getKey(),
        ]);
        config()->set('platform.content_emergency.actor_user_ids', [
            (string) $emergencyActor->getKey(),
        ]);

        self::assertFalse($this->classifier()->isPrivileged($recoveryActor));
        self::assertFalse($this->classifier()->isPrivileged($emergencyActor));
        self::assertTrue($this->applicationClassifier()->isPrivileged($recoveryActor));
        self::assertTrue($this->applicationClassifier()->isPrivileged($emergencyActor));
    }

    private function classifier(): MembershipRolePrivilegeClassifier
    {
        return $this->app->make(MembershipRolePrivilegeClassifier::class);
    }

    private function applicationClassifier(): PrivilegeClassifier
    {
        return $this->app->make(PrivilegeClassifier::class);
    }
}

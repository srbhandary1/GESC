<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Identity\IdentityIdentifierDigester;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\UserIdentity;
use App\Tenancy\DirectLearnerProvisioner;
use App\Tenancy\Exceptions\FoundationConfigurationException;
use App\Tenancy\Exceptions\IdentityUnavailable;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;

final class DirectLearnerProvisionerTest extends TenancyIntegrationTestCase
{
    public function test_foundation_seed_is_idempotent_and_direct_tenant_is_ordinary(): void
    {
        $this->seed(FoundationSeeder::class);
        $this->seed(FoundationSeeder::class);

        $organization = Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->sole();

        $this->assertSame(Organization::KIND_PLATFORM_DIRECT, $organization->kind);
        $this->assertSame(1, DB::table('organizations')
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->count());
        $this->assertSame(1, DB::table('roles')
            ->where('organization_id', $organization->getKey())
            ->where('key', 'learner')
            ->count());
        $this->assertSame(40, DB::table('permissions')->count());
    }

    public function test_verified_identity_provisioning_is_transactional_and_replay_safe(): void
    {
        $phone = '+919876543210';
        $provisioner = $this->app->make(DirectLearnerProvisioner::class);

        $first = $provisioner->provisionVerifiedIdentity('phone', $phone, '••••3210');
        $second = $provisioner->provisionVerifiedIdentity('phone', $phone, 'ignored');

        $this->assertFalse($first->replayed);
        $this->assertTrue($second->replayed);
        $this->assertSame($first->user->getKey(), $second->user->getKey());
        $this->assertSame($first->identity->getKey(), $second->identity->getKey());
        $this->assertSame($first->membership->getKey(), $second->membership->getKey());
        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame(1, DB::table('user_identities')->count());
        $this->assertSame(1, DB::table('memberships')->count());
        $this->assertSame(1, DB::table('membership_roles')->count());
        $this->assertSame(1, DB::table('audit_events')
            ->where('action', 'membership.direct_assigned')
            ->count());
        $this->assertSame(1, DB::table('outbox_events')
            ->where('event_type', 'membership.direct_assigned')
            ->count());

        $recorded = json_encode([
            DB::table('audit_events')->get()->all(),
            DB::table('outbox_events')->get()->all(),
        ]);

        $this->assertIsString($recorded);
        $this->assertStringNotContainsString($phone, $recorded);
        $this->assertStringNotContainsString('ignored', $recorded);
    }

    public function test_existing_verified_direct_staff_identity_authenticates_without_learner_mutation(): void
    {
        $phone = '+919876543211';
        $organization = Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->sole();
        $user = $this->createUser();
        [$membership] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $role = DB::table('roles')
            ->where('organization_id', $organization->getKey())
            ->where('key', 'organization_admin')
            ->sole(['id']);
        $now = now();
        DB::table('membership_roles')->insert([
            'organization_id' => $organization->getKey(),
            'membership_id' => $membership->getKey(),
            'role_id' => $role->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $identity = UserIdentity::query()->forceCreate([
            'user_id' => $user->getKey(),
            'type' => 'phone',
            'identifier_digest' => $this->app
                ->make(IdentityIdentifierDigester::class)
                ->digest('phone', $phone),
            'display_hint' => null,
            'status' => UserIdentity::STATUS_VERIFIED,
            'verified_at' => $now,
        ]);

        $result = $this->app->make(DirectLearnerProvisioner::class)
            ->provisionVerifiedIdentity('phone', $phone);

        self::assertTrue($result->replayed);
        self::assertSame($user->getKey(), $result->user->getKey());
        self::assertSame($identity->getKey(), $result->identity->getKey());
        self::assertSame($membership->getKey(), $result->membership->getKey());
        $this->assertDatabaseHas('memberships', [
            'id' => $membership->getKey(),
            'membership_type' => Membership::TYPE_ORGANIZATION_ADMIN,
            'status' => Membership::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('user_identities', 1);
        $this->assertDatabaseCount('memberships', 1);
        $this->assertDatabaseCount('membership_roles', 1);
        $this->assertDatabaseCount('audit_events', 0);
        $this->assertDatabaseCount('outbox_events', 0);
    }

    public function test_existing_verified_suspended_direct_staff_identity_is_rejected_without_mutation(): void
    {
        $phone = '+919876543212';
        $organization = Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->sole();
        $user = $this->createUser();
        [$membership] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_TRAINER,
        );
        DB::table('memberships')
            ->where('id', $membership->getKey())
            ->update(['status' => Membership::STATUS_SUSPENDED]);
        UserIdentity::query()->forceCreate([
            'user_id' => $user->getKey(),
            'type' => 'phone',
            'identifier_digest' => $this->app
                ->make(IdentityIdentifierDigester::class)
                ->digest('phone', $phone),
            'display_hint' => null,
            'status' => UserIdentity::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        try {
            $this->app->make(DirectLearnerProvisioner::class)
                ->provisionVerifiedIdentity('phone', $phone);
            self::fail('Expected a suspended staff identity to be rejected.');
        } catch (IdentityUnavailable) {
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('user_identities', 1);
            $this->assertDatabaseCount('memberships', 1);
            $this->assertDatabaseCount('membership_roles', 0);
            $this->assertDatabaseCount('audit_events', 0);
            $this->assertDatabaseCount('outbox_events', 0);
        }
    }

    public function test_missing_foundation_role_rolls_back_identity_and_membership_writes(): void
    {
        $learnerRole = DB::table('roles')
            ->join(
                'organizations',
                'organizations.id',
                '=',
                'roles.organization_id',
            )
            ->where('organizations.system_key', Organization::DIRECT_SYSTEM_KEY)
            ->where('roles.key', 'learner')
            ->select(['roles.id', 'roles.organization_id'])
            ->sole();

        DB::table('role_permissions')
            ->where('organization_id', $learnerRole->organization_id)
            ->where('role_id', $learnerRole->id)
            ->delete();
        DB::table('roles')
            ->where('organization_id', $learnerRole->organization_id)
            ->where('id', $learnerRole->id)
            ->delete();

        try {
            $this->app->make(DirectLearnerProvisioner::class)
                ->provisionVerifiedIdentity('phone', '+919999999999');
            $this->fail('Expected the missing foundation role to reject provisioning.');
        } catch (FoundationConfigurationException) {
            $this->assertSame(0, DB::table('users')->count());
            $this->assertSame(0, DB::table('user_identities')->count());
            $this->assertSame(0, DB::table('memberships')->count());
            $this->assertSame(0, DB::table('membership_roles')->count());
            $this->assertSame(0, DB::table('audit_events')->count());
            $this->assertSame(0, DB::table('outbox_events')->count());
        }
    }

    public function test_replacing_a_phone_identity_preserves_the_stable_user_key(): void
    {
        $provisioner = $this->app->make(DirectLearnerProvisioner::class);
        $original = $provisioner->provisionVerifiedIdentity(
            'phone',
            '+919876543210',
        );
        $original->identity->forceFill([
            'status' => UserIdentity::STATUS_DISABLED,
        ])->save();
        $replacementIdentity = UserIdentity::query()->forceCreate([
            'user_id' => $original->user->getKey(),
            'type' => 'phone',
            'identifier_digest' => $this->app
                ->make(IdentityIdentifierDigester::class)
                ->digest('phone', '+919999999999'),
            'display_hint' => null,
            'status' => UserIdentity::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        $replacement = $provisioner->provisionVerifiedIdentity(
            'phone',
            '+919999999999',
        );

        self::assertSame(
            $original->user->getKey(),
            $replacement->user->getKey(),
        );
        self::assertSame(
            $original->membership->getKey(),
            $replacement->membership->getKey(),
        );
        self::assertSame(
            $replacementIdentity->getKey(),
            $replacement->identity->getKey(),
        );
        self::assertNotSame(
            $original->identity->getKey(),
            $replacement->identity->getKey(),
        );
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('memberships', 1);
        $this->assertDatabaseCount('user_identities', 2);
    }
}

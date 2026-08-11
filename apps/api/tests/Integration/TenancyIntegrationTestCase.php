<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\AuthenticationSession;
use App\Models\Cohort;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\PrivilegedMfaFactor;
use App\Models\Role;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextSource;
use Carbon\CarbonInterface;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

abstract class TenancyIntegrationTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FoundationSeeder::class);
    }

    protected function createOrganization(string $name): Organization
    {
        return Organization::query()->create([
            'system_key' => null,
            'name' => $name,
            'kind' => Organization::KIND_INSTITUTION,
            'status' => Organization::STATUS_ACTIVE,
            'timezone' => 'Asia/Kolkata',
        ]);
    }

    protected function createUser(): User
    {
        return User::query()->forceCreate([
            'display_name' => null,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    /**
     * @return array{Membership, OrganizationContext}
     */
    protected function createMembership(
        Organization $organization,
        User $user,
        string $membershipType = Membership::TYPE_LEARNER,
    ): array {
        $membershipId = (string) Str::ulid();
        $now = now();
        DB::table('memberships')->insert([
            'id' => $membershipId,
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'membership_type' => $membershipType,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $context = new OrganizationContext(
            organizationId: (string) $organization->getKey(),
            actorUserId: (string) $user->getKey(),
            membershipId: $membershipId,
            source: OrganizationContextSource::Membership,
        );

        $membership = $this->contexts()->run(
            $context,
            static fn (): Membership => Membership::query()->findOrFail(
                $membershipId,
            ),
        );

        return [$membership, $context];
    }

    protected function createRole(
        OrganizationContext $context,
        string $key = 'test-role',
    ): Role {
        return $this->contexts()->run(
            $context,
            static function () use ($key): Role {
                $role = new Role;
                $role->forceFill([
                    'key' => $key,
                    'name' => Str::headline($key),
                    'is_system' => false,
                ])->save();

                return $role;
            },
        );
    }

    protected function createCohort(
        OrganizationContext $context,
        string $name = 'Foundation Cohort',
    ): Cohort {
        return $this->contexts()->run(
            $context,
            static function () use ($name): Cohort {
                $cohort = new Cohort;
                $cohort->forceFill([
                    'key' => 'cohort-'.Str::lower((string) Str::ulid()),
                    'name' => $name,
                    'status' => Cohort::STATUS_ACTIVE,
                ])->save();

                return $cohort;
            },
        );
    }

    protected function contexts(): OrganizationContextManager
    {
        return $this->app->make(OrganizationContextManager::class);
    }

    protected function createTestPrivilegedMfaFactor(
        string $userId,
    ): PrivilegedMfaFactor {
        $existing = PrivilegedMfaFactor::query()
            ->where('user_id', $userId)
            ->where('status', PrivilegedMfaFactor::STATUS_ACTIVE)
            ->first();

        if ($existing instanceof PrivilegedMfaFactor) {
            return $existing;
        }

        /** @var PrivilegedMfaFactor */
        return PrivilegedMfaFactor::query()->forceCreate([
            'user_id' => $userId,
            'factor_type' => PrivilegedMfaFactor::TYPE_TOTP,
            'secret_ciphertext' => Crypt::encryptString(str_repeat('A', 32)),
            'status' => PrivilegedMfaFactor::STATUS_ACTIVE,
            'confirmed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createTestPrivilegedAuthenticationSession(
        User|string $user,
        CarbonInterface $mfaVerifiedAt,
        array $attributes = [],
    ): AuthenticationSession {
        $userId = $user instanceof User
            ? (string) $user->getKey()
            : $user;
        $factor = $this->createTestPrivilegedMfaFactor($userId);
        $now = now();

        /** @var AuthenticationSession */
        return AuthenticationSession::query()->forceCreate([
            'user_id' => $userId,
            'storage_session_digest' => hash(
                'sha256',
                'synthetic-privileged-session-'.Str::ulid(),
            ),
            'storage_session_ciphertext' => 'synthetic-test-session',
            'is_privileged' => true,
            'started_at' => $now->copy()->subMinute(),
            'last_seen_at' => $now,
            'idle_expires_at' => $now->copy()->addHour(),
            'absolute_expires_at' => $now->copy()->addHours(2),
            'mfa_verified_at' => $mfaVerifiedAt,
            'mfa_factor_id' => $factor->getKey(),
            'mfa_verified_method' => AuthenticationSession::MFA_METHOD_TOTP,
            'mfa_verified_scope' => AuthenticationSession::MFA_SCOPE_PRIVILEGED_ACTIONS,
            'revoked_at' => null,
            'revocation_reason' => null,
            ...$attributes,
        ]);
    }

    protected function markTestAuthenticationSessionPrivileged(
        string $authenticationSessionId,
        User|string $user,
        CarbonInterface $mfaVerifiedAt,
    ): void {
        $userId = $user instanceof User
            ? (string) $user->getKey()
            : $user;
        $factor = $this->createTestPrivilegedMfaFactor($userId);

        AuthenticationSession::query()
            ->whereKey($authenticationSessionId)
            ->update([
                'is_privileged' => true,
                'mfa_verified_at' => $mfaVerifiedAt,
                'mfa_factor_id' => $factor->getKey(),
                'mfa_verified_method' => AuthenticationSession::MFA_METHOD_TOTP,
                'mfa_verified_scope' => AuthenticationSession::MFA_SCOPE_PRIVILEGED_ACTIONS,
            ]);
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    protected function grantTestPermissions(
        Membership $membership,
        OrganizationContext $context,
        array $permissionKeys,
        string $roleKeyPrefix = 'test',
    ): Role {
        $role = $this->createRole(
            $context,
            $roleKeyPrefix.'-'.Str::lower((string) Str::ulid()),
        );
        $now = now();

        DB::table('membership_roles')->insert([
            'organization_id' => $membership->organizationId(),
            'membership_id' => $membership->getKey(),
            'role_id' => $role->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissions = Permission::query()
            ->whereIn('key', $permissionKeys)
            ->get()
            ->keyBy('key');
        $grants = [];

        foreach ($permissionKeys as $permissionKey) {
            $permission = $permissions->get($permissionKey);

            if (! $permission instanceof Permission) {
                throw new RuntimeException(sprintf(
                    'Missing synthetic test permission: %s.',
                    $permissionKey,
                ));
            }

            $grants[] = [
                'organization_id' => $membership->organizationId(),
                'role_id' => $role->getKey(),
                'permission_id' => $permission->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($grants !== []) {
            DB::table('role_permissions')->insert($grants);
        }

        return $role;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Cohort;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextSource;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entitlements\CourtesyPremiumGrantService;
use App\Entitlements\DirectPlanDefaults;
use App\Entitlements\EntitlementAccess;
use App\Entitlements\LearnerSubscriptionService;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextSource;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

final class CourtesyPremiumGrantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FoundationSeeder::class);
    }

    public function test_catalogue_flags_premium_as_recommended(): void
    {
        [$learner, $context] = $this->learner('alpha.learner');
        $manager = $this->app->make(OrganizationContextManager::class);
        $manager->activate($context);

        try {
            $catalogue = $this->app->make(LearnerSubscriptionService::class)->catalogue($learner);
            $premium = collect($catalogue['plans'])->firstWhere('code', DirectPlanDefaults::MONTHLY_ROLEPLAY);
            $modules = collect($catalogue['plans'])->firstWhere('code', DirectPlanDefaults::MONTHLY_MODULES);
            self::assertIsArray($premium);
            self::assertSame('Premium', $premium['name']);
            self::assertTrue($premium['recommended']);
            self::assertIsArray($modules);
            self::assertSame('Modules', $modules['name']);
            self::assertFalse($modules['recommended']);
            self::assertSame(DirectPlanDefaults::MONTHLY_ROLEPLAY, $catalogue['plans'][0]['code']);
            $talk = collect($catalogue['plans'])->firstWhere('code', DirectPlanDefaults::TALK_PACK_30);
            self::assertIsArray($talk);
            self::assertTrue($talk['is_talk_pack']);
            self::assertFalse($talk['recommended']);
            self::assertSame(DirectPlanDefaults::TALK_PACK_30_SECONDS, $talk['live_ai_cap_seconds']);
        } finally {
            $manager->clear();
        }
    }

    public function test_courtesy_grant_gives_each_direct_learner_one_premium_month(): void
    {
        [$first, $firstContext] = $this->learner('alpha.learner');
        [$second] = $this->learner('beta.learner');
        $manager = $this->app->make(OrganizationContextManager::class);
        $manager->activate($firstContext);

        try {
            $this->app->make(EntitlementAccess::class)->snapshot($first);
            $organizationId = $firstContext->organizationId;
            $service = $this->app->make(CourtesyPremiumGrantService::class);
            $firstRun = $service->grantForOrganization($organizationId, null);
            self::assertSame(2, $firstRun->considered);
            self::assertSame(2, $firstRun->granted);
            self::assertSame(0, $firstRun->skipped);
            $replay = $service->grantForOrganization($organizationId, null);
            self::assertSame(0, $replay->granted);
            self::assertSame(2, $replay->skipped);
            $snapshot = $this->app->make(EntitlementAccess::class)->snapshot($first);
            self::assertSame(DirectPlanDefaults::MONTHLY_ROLEPLAY, $snapshot['plan_code']);
            self::assertTrue($snapshot['allows_roleplay']);
            self::assertNull($snapshot['max_level_number']);
            self::assertSame(DirectPlanDefaults::PREMIUM_LIVE_AI_CAP_SECONDS, $snapshot['live_ai_cap_seconds']);
            self::assertSame(2, $service->grantedMembershipCount($organizationId));
            self::assertSame(1, DB::table('learner_entitlements')
                ->where('learner_membership_id', $firstContext->membershipId)
                ->where('status', 'active')
                ->count());
            unset($second);
        } finally {
            $manager->clear();
        }
    }

    public function test_csv_limits_courtesy_grants_to_listed_usernames(): void
    {
        [$included, $context] = $this->learner('alpha.learner');
        $this->learner('beta.learner');
        $manager = $this->app->make(OrganizationContextManager::class);
        $manager->activate($context);

        try {
            $summary = $this->app->make(CourtesyPremiumGrantService::class)->grantForOrganization(
                $context->organizationId,
                null,
                false,
                ['alpha.learner', 'missing.learner'],
            );
            self::assertSame(1, $summary->considered);
            self::assertSame(1, $summary->granted);
            self::assertSame(0, $summary->skipped);
            self::assertSame(1, $summary->unmatchedUsernames);
            $snapshot = $this->app->make(EntitlementAccess::class)->snapshot($included);
            self::assertSame(DirectPlanDefaults::MONTHLY_ROLEPLAY, $snapshot['plan_code']);
        } finally {
            $manager->clear();
        }
    }

    public function test_dry_run_does_not_write_grants(): void
    {
        $this->learner('alpha.learner');
        $command = $this->artisan('entitlements:grant-courtesy-premium', ['--dry-run' => true]);
        self::assertInstanceOf(PendingCommand::class, $command);
        $command
            ->expectsOutputToContain('Would process')
            ->assertSuccessful();
        self::assertSame(0, DB::table('learner_entitlements')->count());
    }

    /**
     * @return array{User, OrganizationContext}
     */
    private function learner(string $username): array
    {
        $organization = Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->firstOrFail();
        $user = User::query()->forceCreate([
            'display_name' => $username,
            'status' => User::STATUS_ACTIVE,
        ]);
        $user->forceFill(['username' => $username])->save();
        $membershipId = (string) Str::ulid();
        $now = now();
        DB::table('memberships')->insert([
            'id' => $membershipId,
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'membership_type' => Membership::TYPE_LEARNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $roleId = (string) Str::ulid();
        DB::table('roles')->insert([
            'id' => $roleId,
            'organization_id' => $organization->getKey(),
            'key' => 'courtesy-'.$username,
            'name' => 'Courtesy test learner',
            'is_system' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('membership_roles')->insert([
            'organization_id' => $organization->getKey(),
            'membership_id' => $membershipId,
            'role_id' => $roleId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $permissionId = Permission::query()->where('key', 'learning.participate')->value('id');
        self::assertNotNull($permissionId);
        DB::table('role_permissions')->insert([
            'organization_id' => $organization->getKey(),
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            $user,
            new OrganizationContext(
                organizationId: (string) $organization->getKey(),
                actorUserId: (string) $user->getKey(),
                membershipId: $membershipId,
                source: OrganizationContextSource::Membership,
            ),
        ];
    }
}

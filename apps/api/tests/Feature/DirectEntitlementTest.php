<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entitlements\DirectPlanDefaults;
use App\Entitlements\EntitlementAccess;
use App\Entitlements\Exceptions\EntitlementDenied;
use App\Entitlements\LearnerSubscriptionService;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextSource;
use Carbon\CarbonImmutable;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Tests\TestCase;

final class DirectEntitlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FoundationSeeder::class);
    }

    public function test_me_entitlement_requires_authentication(): void
    {
        $this->getJson('/api/v1/me/entitlement')->assertUnauthorized();
    }

    public function test_direct_learner_receives_auto_demo_with_level_and_ai_caps(): void
    {
        [$learner, $context] = $this->learner();
        $access = $this->access();
        $this->app->make(OrganizationContextManager::class)->activate($context);

        try {
            $snapshot = $access->snapshot($learner);
            self::assertTrue($snapshot['applies']);
            self::assertSame(DirectPlanDefaults::DEMO, $snapshot['plan_code']);
            self::assertSame(2, $snapshot['max_level_number']);
            self::assertTrue($snapshot['allows_roleplay']);
            self::assertSame(300, $snapshot['live_ai_cap_seconds']);
            self::assertSame(300, $snapshot['live_ai_seconds_remaining']);
            $access->assertLevel($learner, 2);
            $access->assertRoleplay($learner);

            try {
                $access->assertLevel($learner, 3);
                self::fail('Level 3 should be locked on demo.');
            } catch (EntitlementDenied $denied) {
                self::assertSame(EntitlementDenied::LEVEL_LOCKED, $denied->messageKey);
            }

            $chargeKey = (string) Str::ulid();
            $access->consumeLiveAi($learner, $chargeKey, 300);
            $access->consumeLiveAi($learner, $chargeKey, 300);
            $after = $access->snapshot($learner);
            self::assertSame(0, $after['live_ai_seconds_remaining']);

            try {
                $access->consumeLiveAi($learner, (string) Str::ulid(), 1);
                self::fail('Exhausted demo AI should deny.');
            } catch (EntitlementDenied $denied) {
                self::assertSame(EntitlementDenied::DEMO_AI_EXHAUSTED, $denied->messageKey);
            }
        } finally {
            $this->app->make(OrganizationContextManager::class)->clear();
        }
    }

    public function test_monthly_modules_checkout_locks_roleplay_and_unlocks_all_levels(): void
    {
        [$learner, $context] = $this->learner();
        $manager = $this->app->make(OrganizationContextManager::class);
        $manager->activate($context);

        try {
            $this->app->make(EntitlementAccess::class)->snapshot($learner);
            $planId = DB::table('entitlement_plans')
                ->where('organization_id', $context->organizationId)
                ->where('code', DirectPlanDefaults::MONTHLY_MODULES)
                ->value('id');
            self::assertIsString($planId);
            $checkout = $this->app->make(LearnerSubscriptionService::class)->checkout(
                $learner,
                $planId,
                (string) Str::ulid(),
            );
            self::assertSame('completed', $checkout['status']);
            self::assertSame(35000, $checkout['amount_ex_gst_paise']);
            self::assertSame(6300, $checkout['gst_paise']);
            self::assertSame(41300, $checkout['total_paise']);
            $snapshot = $this->app->make(EntitlementAccess::class)->snapshot($learner);
            self::assertSame(DirectPlanDefaults::MONTHLY_MODULES, $snapshot['plan_code']);
            self::assertSame(DirectPlanDefaults::MODULES_LIVE_AI_CAP_SECONDS, $snapshot['live_ai_cap_seconds']);
            self::assertSame(DirectPlanDefaults::MODULES_LIVE_AI_CAP_SECONDS, $snapshot['live_ai_seconds_remaining']);
            self::assertNull($snapshot['max_level_number']);
            self::assertFalse($snapshot['allows_roleplay']);
            $this->app->make(EntitlementAccess::class)->assertLevel($learner, 17);

            try {
                $this->app->make(EntitlementAccess::class)->assertRoleplay($learner);
                self::fail('Role-play should be locked on ₹350.');
            } catch (EntitlementDenied $denied) {
                self::assertSame(EntitlementDenied::ROLEPLAY_LOCKED, $denied->messageKey);
            }
        } finally {
            $manager->clear();
        }
    }

    public function test_roleplay_plan_allows_roleplay(): void
    {
        [$learner, $context] = $this->learner();
        $manager = $this->app->make(OrganizationContextManager::class);
        $manager->activate($context);

        try {
            $planId = DB::table('entitlement_plans')
                ->where('organization_id', $context->organizationId)
                ->where('code', DirectPlanDefaults::MONTHLY_ROLEPLAY)
                ->value('id');
            self::assertIsString($planId);
            $this->app->make(LearnerSubscriptionService::class)->checkout(
                $learner,
                $planId,
                (string) Str::ulid(),
            );
            $this->app->make(EntitlementAccess::class)->assertRoleplay($learner);
            $this->app->make(EntitlementAccess::class)->assertLevel($learner, 17);
            $snapshot = $this->app->make(EntitlementAccess::class)->snapshot($learner);
            self::assertSame(DirectPlanDefaults::PREMIUM_LIVE_AI_CAP_SECONDS, $snapshot['live_ai_cap_seconds']);
        } finally {
            $manager->clear();
        }
    }

    public function test_institutional_learners_are_not_paywalled(): void
    {
        $organization = Organization::query()->create([
            'system_key' => null,
            'name' => 'Partner college',
            'kind' => Organization::KIND_INSTITUTION,
            'status' => Organization::STATUS_ACTIVE,
            'timezone' => 'Asia/Kolkata',
        ]);
        [$learner, $context] = $this->learnerIn($organization);
        $manager = $this->app->make(OrganizationContextManager::class);
        $manager->activate($context);

        try {
            $snapshot = $this->app->make(EntitlementAccess::class)->snapshot($learner);
            self::assertFalse($snapshot['applies']);
            $this->app->make(EntitlementAccess::class)->assertLevel($learner, 17);
            $this->app->make(EntitlementAccess::class)->assertRoleplay($learner);
        } finally {
            $manager->clear();
        }
    }

    public function test_paid_live_ai_cap_and_talk_pack_top_up(): void
    {
        [$learner, $context] = $this->learner();
        $manager = $this->app->make(OrganizationContextManager::class);
        $manager->activate($context);

        try {
            $subscriptions = $this->app->make(LearnerSubscriptionService::class);
            $access = $this->app->make(EntitlementAccess::class);
            $modulesId = DB::table('entitlement_plans')
                ->where('organization_id', $context->organizationId)
                ->where('code', DirectPlanDefaults::MONTHLY_MODULES)
                ->value('id');
            $talkId = DB::table('entitlement_plans')
                ->where('organization_id', $context->organizationId)
                ->where('code', DirectPlanDefaults::TALK_PACK_30)
                ->value('id');
            self::assertIsString($modulesId);
            self::assertIsString($talkId);
            $subscriptions->checkout($learner, $modulesId, (string) Str::ulid());
            $access->consumeLiveAi($learner, (string) Str::ulid(), DirectPlanDefaults::MODULES_LIVE_AI_CAP_SECONDS);
            self::assertSame(0, $access->snapshot($learner)['live_ai_seconds_remaining']);

            try {
                $access->consumeLiveAi($learner, (string) Str::ulid(), 1);
                self::fail('Paid AI cap should deny.');
            } catch (EntitlementDenied $denied) {
                self::assertSame(EntitlementDenied::AI_EXHAUSTED, $denied->messageKey);
            }

            $checkout = $subscriptions->checkout($learner, $talkId, (string) Str::ulid());
            self::assertSame('completed', $checkout['status']);
            self::assertSame(19900, $checkout['amount_ex_gst_paise']);
            self::assertSame(3582, $checkout['gst_paise']);
            $afterPack = $access->snapshot($learner);
            self::assertSame(DirectPlanDefaults::MONTHLY_MODULES, $afterPack['plan_code']);
            self::assertFalse($afterPack['allows_roleplay']);
            self::assertSame(
                DirectPlanDefaults::MODULES_LIVE_AI_CAP_SECONDS + DirectPlanDefaults::TALK_PACK_30_SECONDS,
                $afterPack['live_ai_cap_seconds'],
            );
            self::assertSame(DirectPlanDefaults::TALK_PACK_30_SECONDS, $afterPack['live_ai_seconds_remaining']);
            $access->consumeLiveAi($learner, (string) Str::ulid(), DirectPlanDefaults::TALK_PACK_30_SECONDS);
            self::assertSame(0, $access->snapshot($learner)['live_ai_seconds_remaining']);
        } finally {
            $manager->clear();
        }
    }

    public function test_talk_pack_requires_an_active_paid_month(): void
    {
        [$learner, $context] = $this->learner();
        $manager = $this->app->make(OrganizationContextManager::class);
        $manager->activate($context);

        try {
            $this->app->make(EntitlementAccess::class)->snapshot($learner);
            $talkId = DB::table('entitlement_plans')
                ->where('organization_id', $context->organizationId)
                ->where('code', DirectPlanDefaults::TALK_PACK_60)
                ->value('id');
            self::assertIsString($talkId);

            try {
                $this->app->make(LearnerSubscriptionService::class)->checkout(
                    $learner,
                    $talkId,
                    (string) Str::ulid(),
                );
                self::fail('Demo must not buy talk packs.');
            } catch (EntitlementDenied $denied) {
                self::assertSame(EntitlementDenied::TALK_PACK_REQUIRES_PAID, $denied->messageKey);
            }
        } finally {
            $manager->clear();
        }
    }

    public function test_membership_grant_rejects_a_talk_pack_plan(): void
    {
        [$learner, $context] = $this->learner();
        $manager = $this->app->make(OrganizationContextManager::class);
        $manager->activate($context);

        try {
            $this->access()->snapshot($learner);
            $plan = DB::table('entitlement_plans')
                ->where('organization_id', $context->organizationId)
                ->where('code', DirectPlanDefaults::TALK_PACK_30)
                ->first();
            self::assertInstanceOf(stdClass::class, $plan);
            self::assertIsString($context->membershipId);

            try {
                $this->access()->grantPlan(
                    $context->organizationId,
                    $context->membershipId,
                    $plan,
                    'operator_grant',
                    $context->membershipId,
                    [],
                    CarbonImmutable::now('Asia/Kolkata'),
                );
                self::fail('Talk packs must not replace a membership.');
            } catch (EntitlementDenied $denied) {
                self::assertSame(EntitlementDenied::CHECKOUT_UNAVAILABLE, $denied->messageKey);
            }
        } finally {
            $manager->clear();
        }
    }

    public function test_talk_pack_expires_with_the_paid_month_when_sooner_than_30_days(): void
    {
        [$learner, $context] = $this->learner();
        $manager = $this->app->make(OrganizationContextManager::class);
        $manager->activate($context);

        try {
            $subscriptions = $this->app->make(LearnerSubscriptionService::class);
            $modulesId = DB::table('entitlement_plans')
                ->where('organization_id', $context->organizationId)
                ->where('code', DirectPlanDefaults::MONTHLY_MODULES)
                ->value('id');
            $talkId = DB::table('entitlement_plans')
                ->where('organization_id', $context->organizationId)
                ->where('code', DirectPlanDefaults::TALK_PACK_30)
                ->value('id');
            self::assertIsString($modulesId);
            self::assertIsString($talkId);
            $subscriptions->checkout($learner, $modulesId, (string) Str::ulid());
            $endsSoon = now('Asia/Kolkata')->addDays(2);
            DB::table('learner_entitlements')
                ->where('organization_id', $context->organizationId)
                ->where('learner_membership_id', $context->membershipId)
                ->where('status', 'active')
                ->update(['expires_at' => $endsSoon]);
            $subscriptions->checkout($learner, $talkId, (string) Str::ulid());
            $entitlement = DB::table('learner_entitlements')
                ->where('organization_id', $context->organizationId)
                ->where('learner_membership_id', $context->membershipId)
                ->where('status', 'active')
                ->first();
            $pack = DB::table('live_ai_talk_packs')
                ->where('organization_id', $context->organizationId)
                ->orderByDesc('created_at')
                ->first();
            self::assertNotNull($entitlement);
            self::assertNotNull($pack);
            self::assertSame(
                CarbonImmutable::parse((string) $entitlement->expires_at)->getTimestamp(),
                CarbonImmutable::parse((string) $pack->expires_at)->getTimestamp(),
            );
        } finally {
            $manager->clear();
        }
    }

    private function access(): EntitlementAccess
    {
        return $this->app->make(EntitlementAccess::class);
    }

    /**
     * @return array{User, OrganizationContext}
     */
    private function learner(): array
    {
        $organization = Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->firstOrFail();

        return $this->learnerIn($organization);
    }

    /**
     * @return array{User, OrganizationContext}
     */
    private function learnerIn(Organization $organization): array
    {
        $user = User::query()->forceCreate([
            'display_name' => 'Direct learner',
            'status' => User::STATUS_ACTIVE,
        ]);
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
            'key' => 'entitlement-test-learner',
            'name' => 'Entitlement test learner',
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

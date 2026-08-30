<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\Home\MeaningfulSpeakingDayPredicate;
use App\Learning\Home\MeaningfulSpeakingDayProjector;
use App\Learning\LearnerAuthority;
use App\Learning\Progress\LearnerProgressDashboardService;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextSource;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LearnerProgressDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FoundationSeeder::class);
    }

    public function test_progress_dashboard_requires_first_party_authentication(): void
    {
        $this->getJson('/api/v1/me/progress-dashboard')
            ->assertUnauthorized();
    }

    public function test_progress_dashboard_returns_contract_shape_for_learner(): void
    {
        [$learner, $context] = $this->learner();
        $contexts = new OrganizationContextManager;
        $this->app->instance(OrganizationContextManager::class, $contexts);
        $dashboard = $this->dashboardService($contexts);
        $contexts->activate($context);

        try {
            $payload = $dashboard->forLearner($learner);
        } finally {
            $contexts->clear();
        }

        self::assertArrayHasKey('enrollment', $payload);
        self::assertArrayHasKey('objectives', $payload);
        self::assertArrayHasKey('speaking_activity', $payload);
        self::assertArrayHasKey('attempt_improvement', $payload);
        self::assertArrayHasKey('delayed_review', $payload);
        self::assertArrayHasKey('recurring_errors', $payload);
        self::assertNull($payload['enrollment']);
        self::assertSame([], $payload['objectives']);
        self::assertSame(
            [
                'meaningful_speaking_days_count',
                'completed_turn_count',
                'speaking_duration_ms',
                'speaking_minutes',
                'timezone',
            ],
            array_keys($payload['speaking_activity']),
        );
        self::assertSame(
            [
                'missions_attempted',
                'missions_passed_on_first_attempt',
                'missions_improved_on_retry',
                'missions_still_not_mastered',
            ],
            array_keys($payload['attempt_improvement']),
        );
        self::assertSame(
            [
                'scheduled_count',
                'due_count',
                'pending_count',
                'completed_count',
                'completed_on_time_count',
                'completed_late_count',
            ],
            array_keys($payload['delayed_review']),
        );
        self::assertSame([], $payload['recurring_errors']['patterns']);
    }

    public function test_progress_dashboard_rejects_non_learner_membership(): void
    {
        $organization = Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->firstOrFail();
        $user = User::query()->forceCreate([
            'display_name' => 'Trainer user',
            'status' => User::STATUS_ACTIVE,
        ]);
        $membershipId = (string) Str::ulid();
        $now = now();
        DB::table('memberships')->insert([
            'id' => $membershipId,
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'membership_type' => Membership::TYPE_TRAINER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $roleId = (string) Str::ulid();
        DB::table('roles')->insert([
            'id' => $roleId,
            'organization_id' => $organization->getKey(),
            'key' => 'trainer-progress-test',
            'name' => 'Trainer progress test',
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
        $permissionId = Permission::query()
            ->where('key', 'learning.participate')
            ->value('id');
        self::assertNotNull($permissionId);
        DB::table('role_permissions')->insert([
            'organization_id' => $organization->getKey(),
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $context = new OrganizationContext(
            organizationId: (string) $organization->getKey(),
            actorUserId: (string) $user->getKey(),
            membershipId: $membershipId,
            source: OrganizationContextSource::Membership,
        );
        $contexts = new OrganizationContextManager;
        $this->app->instance(OrganizationContextManager::class, $contexts);
        $dashboard = $this->dashboardService($contexts);
        $contexts->activate($context);

        try {
            $dashboard->forLearner($user);
            self::fail('Expected non-learner membership rejection.');
        } catch (LearningOperationRejected) {
            $this->addToAssertionCount(1);
        } finally {
            $contexts->clear();
        }
    }

    /**
     * @return array{User, OrganizationContext}
     */
    private function learner(): array
    {
        $organization = Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->firstOrFail();
        $user = User::query()->forceCreate([
            'display_name' => 'Learner user',
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
            'key' => 'learner-progress-test',
            'name' => 'Learner progress test',
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
        $permissionId = Permission::query()
            ->where('key', 'learning.participate')
            ->value('id');
        self::assertNotNull($permissionId);
        DB::table('role_permissions')->insert([
            'organization_id' => $organization->getKey(),
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $context = new OrganizationContext(
            organizationId: (string) $organization->getKey(),
            actorUserId: (string) $user->getKey(),
            membershipId: $membershipId,
            source: OrganizationContextSource::Membership,
        );

        return [$user, $context];
    }

    private function dashboardService(
        OrganizationContextManager $contexts,
    ): LearnerProgressDashboardService {
        return new LearnerProgressDashboardService(
            new LearnerAuthority(
                $contexts,
                new OrganizationAuthorization($contexts),
            ),
            new MeaningfulSpeakingDayProjector(
                new MeaningfulSpeakingDayPredicate,
            ),
        );
    }
}

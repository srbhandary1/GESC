<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Learning\Progress\LearnerProgressDashboardService;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProgressDashboardTenancyTest extends TenancyIntegrationTestCase
{
    public function test_progress_dashboard_excludes_other_organization_rows(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            self::markTestSkipped(
                'PostgreSQL integration tests require pdo_pgsql; this host cannot run them.',
            );
        }

        [$learnerA, $membershipA, $contextA] = $this->learner();
        $organizationB = $this->createOrganization('Progress tenant B');
        [$learnerB, $membershipB, $contextB] = $this->learner($organizationB);

        DB::table('learner_error_patterns')->insert([
            [
                'id' => (string) Str::ulid(),
                'organization_id' => $membershipA->organizationId(),
                'learner_membership_id' => $membershipA->getKey(),
                'error_category' => 'grammar.article',
                'observation_count' => 2,
                'first_observed_at' => now()->subDays(10),
                'last_observed_at' => now()->subDay(),
            ],
            [
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationB->getKey(),
                'learner_membership_id' => $membershipB->getKey(),
                'error_category' => 'grammar.preposition',
                'observation_count' => 3,
                'first_observed_at' => now()->subDays(8),
                'last_observed_at' => now()->subDay(),
            ],
        ]);

        self::assertSame(2, DB::table('learner_error_patterns')->count());

        $dashboard = $this->app->make(LearnerProgressDashboardService::class);
        $payloadA = $this->contexts()->run(
            $contextA,
            fn (): array => $dashboard->forLearner($learnerA),
        );
        $payloadB = $this->contexts()->run(
            $contextB,
            fn (): array => $dashboard->forLearner($learnerB),
        );

        self::assertCount(1, $payloadA['recurring_errors']['patterns']);
        self::assertSame(
            'grammar.article',
            $payloadA['recurring_errors']['patterns'][0]['error_category'],
        );
        self::assertCount(1, $payloadB['recurring_errors']['patterns']);
        self::assertSame(
            'grammar.preposition',
            $payloadB['recurring_errors']['patterns'][0]['error_category'],
        );
        self::assertSame([], $payloadA['objectives']);
        self::assertSame([], $payloadB['objectives']);
        self::assertSame(0, $payloadA['speaking_activity']['meaningful_speaking_days_count']);
        self::assertSame(0, $payloadB['speaking_activity']['meaningful_speaking_days_count']);
    }

    /**
     * @return array{User, Membership, OrganizationContext}
     */
    private function learner(
        ?Organization $organization = null,
    ): array {
        $organization ??= Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->firstOrFail();
        $user = $this->createUser();
        [$membership, $context] = $this->createMembership(
            $organization,
            $user,
        );
        $role = $this->createRole(
            $context,
            'progress-'.strtolower((string) Str::ulid()),
        );
        $permission = Permission::query()
            ->where('key', 'learning.participate')
            ->firstOrFail();
        $now = now();
        DB::table('membership_roles')->insert([
            'organization_id' => $organization->getKey(),
            'membership_id' => $membership->getKey(),
            'role_id' => $role->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('role_permissions')->insert([
            'organization_id' => $organization->getKey(),
            'role_id' => $role->getKey(),
            'permission_id' => $permission->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$user, $membership, $context];
    }
}

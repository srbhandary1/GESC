<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Conversation\Memory\Exceptions\MemoryOperationRejected;
use App\Conversation\Memory\LearnerMemoryService;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LearnerMemoryTest extends TenancyIntegrationTestCase
{
    public function test_memory_crud_is_visible_audited_and_learning_isolated(): void
    {
        [$learner, , $context] = $this->learner();
        $memory = $this->app->make(LearnerMemoryService::class);
        $learningBefore = $this->learningCounts();

        $created = $this->contexts()->run(
            $context,
            fn (): array => $memory->create(
                learner: $learner,
                factText: '  I practise English after work.  ',
                idempotencyKey: (string) Str::uuid(),
            ),
        );
        $listed = $this->contexts()->run(
            $context,
            fn (): array => $memory->list($learner),
        );
        $updated = $this->contexts()->run(
            $context,
            fn (): array => $memory->update(
                learner: $learner,
                factId: (string) $created['id'],
                factText: 'I practise English every evening.',
                idempotencyKey: (string) Str::uuid(),
            ),
        );
        $deleted = $this->contexts()->run(
            $context,
            fn (): array => $memory->delete(
                learner: $learner,
                factId: (string) $created['id'],
                idempotencyKey: (string) Str::uuid(),
            ),
        );

        $this->assertSame('I practise English after work.', $created['fact_text']);
        $this->assertFalse($created['replayed']);
        $this->assertCount(1, $listed);
        $this->assertSame($created['id'], $listed[0]['id']);
        $this->assertSame(
            'I practise English every evening.',
            $updated['fact_text'],
        );
        $this->assertSame('deleted', $deleted['status']);
        $this->assertSame(
            [],
            $this->contexts()->run(
                $context,
                fn (): array => $memory->list($learner),
            ),
        );
        $this->assertSame(
            ['created', 'updated', 'deleted'],
            DB::table('memory_events')
                ->orderBy('recorded_at')
                ->pluck('event_code')
                ->all(),
        );
        $this->assertSame($learningBefore, $this->learningCounts());
    }

    public function test_memory_is_denied_across_learners_and_tenants(): void
    {
        [$learner, $membership, $context] = $this->learner();
        $memory = $this->app->make(LearnerMemoryService::class);
        $created = $this->contexts()->run(
            $context,
            fn (): array => $memory->create(
                learner: $learner,
                factText: 'Shared only with me.',
                idempotencyKey: (string) Str::uuid(),
            ),
        );

        [$otherLearner, , $otherContext] = $this->learner(
            organization: Organization::query()->findOrFail(
                $membership->organizationId(),
            ),
        );

        try {
            $this->contexts()->run(
                $otherContext,
                fn (): array => $memory->update(
                    learner: $otherLearner,
                    factId: (string) $created['id'],
                    factText: 'Should not work.',
                    idempotencyKey: (string) Str::uuid(),
                ),
            );
            self::fail('Expected same-tenant other learner denial.');
        } catch (MemoryOperationRejected) {
            $this->addToAssertionCount(1);
        }

        [$tenantLearner, , $tenantContext] = $this->learner(
            organization: $this->createOrganization('Memory other tenant'),
        );

        try {
            $this->contexts()->run(
                $tenantContext,
                fn (): array => $memory->delete(
                    learner: $tenantLearner,
                    factId: (string) $created['id'],
                    idempotencyKey: (string) Str::uuid(),
                ),
            );
            self::fail('Expected cross-tenant denial.');
        } catch (MemoryOperationRejected) {
            $this->addToAssertionCount(1);
        }
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
            'memory-'.strtolower((string) Str::ulid()),
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

    /**
     * @return array<string, int>
     */
    private function learningCounts(): array
    {
        return collect([
            'learner_attempts',
            'item_responses',
            'attempt_submissions',
            'mastery_states',
            'review_schedules',
            'learning_commands',
        ])->mapWithKeys(
            static fn (string $table): array => [
                $table => DB::table($table)->count(),
            ],
        )->all();
    }
}

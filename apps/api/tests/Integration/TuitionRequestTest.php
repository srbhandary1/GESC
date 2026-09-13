<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\Tuition\Exceptions\TuitionRequestIdempotencyConflict;
use App\Learning\Tuition\TuitionRequestService;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TuitionRequestTest extends TenancyIntegrationTestCase
{
    public function test_tuition_requests_are_org_scoped_and_replay_safe(): void
    {
        [$learner, $membership, $context] = $this->learner();
        $tuition = $this->app->make(TuitionRequestService::class);
        $createKey = (string) Str::uuid();

        $created = $this->contexts()->run(
            $context,
            fn (): array => $tuition->create(
                learner: $learner,
                preferredWindow: 'Weekday evenings after 7pm',
                topic: 'Level 3 speaking practice',
                notes: 'Prefer Kannada explanation.',
                idempotencyKey: $createKey,
            ),
        );
        $replayed = $this->contexts()->run(
            $context,
            fn (): array => $tuition->create(
                learner: $learner,
                preferredWindow: 'Weekday evenings after 7pm',
                topic: 'Level 3 speaking practice',
                notes: 'Prefer Kannada explanation.',
                idempotencyKey: $createKey,
            ),
        );

        $this->assertTrue($created['created']);
        $this->assertFalse($replayed['created']);
        $this->assertSame($created['request']['id'], $replayed['request']['id']);
        $listed = $this->contexts()->run(
            $context,
            fn (): array => $tuition->listMine($learner),
        );
        $this->assertCount(1, $listed);
        $this->assertSame('submitted', $listed[0]['status']);

        try {
            $this->contexts()->run(
                $context,
                fn (): array => $tuition->create(
                    learner: $learner,
                    preferredWindow: 'Different window',
                    topic: 'Different topic',
                    notes: null,
                    idempotencyKey: $createKey,
                ),
            );
            self::fail('Expected idempotency conflict.');
        } catch (TuitionRequestIdempotencyConflict) {
            $this->addToAssertionCount(1);
        }

        [$otherLearner, , $otherContext] = $this->learner(
            organization: Organization::query()->findOrFail(
                $membership->organizationId(),
            ),
        );
        $otherList = $this->contexts()->run(
            $otherContext,
            fn (): array => $tuition->listMine($otherLearner),
        );
        $this->assertSame([], $otherList);

        [$foreignLearner, , $foreignContext] = $this->learner(
            organization: $this->createOrganization('Tuition other tenant'),
        );
        $foreignList = $this->contexts()->run(
            $foreignContext,
            fn (): array => $tuition->listMine($foreignLearner),
        );
        $this->assertSame([], $foreignList);

        $operator = $this->createUser();
        [$operatorMembership, $operatorContext] = $this->createMembership(
            Organization::query()->findOrFail($membership->organizationId()),
            $operator,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $operatorMembership,
            $operatorContext,
            ['learners.read'],
            'tuition-operator',
        );
        $operatorList = $this->contexts()->run(
            $operatorContext,
            fn (): array => $tuition->listForOperator($operator),
        );
        $this->assertCount(1, $operatorList);
        $this->assertSame($created['request']['id'], $operatorList[0]['id']);

        $acknowledged = $this->contexts()->run(
            $operatorContext,
            fn (): array => $tuition->acknowledge(
                $operator,
                (string) $created['request']['id'],
                (string) Str::uuid(),
            ),
        );
        $this->assertTrue($acknowledged['created']);
        $this->assertSame('acknowledged', $acknowledged['request']['status']);

        $foreignOperator = $this->createUser();
        [$foreignOpMembership, $foreignOpContext] = $this->createMembership(
            Organization::query()->findOrFail(
                (string) $foreignContext->organizationId,
            ),
            $foreignOperator,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $foreignOpMembership,
            $foreignOpContext,
            ['learners.read'],
            'tuition-foreign-operator',
        );
        $foreignOperatorList = $this->contexts()->run(
            $foreignOpContext,
            fn (): array => $tuition->listForOperator($foreignOperator),
        );
        $this->assertSame([], $foreignOperatorList);
    }

    public function test_non_learners_cannot_create_tuition_requests(): void
    {
        $organization = Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->firstOrFail();
        $user = $this->createUser();
        [, $context] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );

        $this->expectException(LearningOperationRejected::class);
        $this->contexts()->run(
            $context,
            fn (): array => $this->app->make(TuitionRequestService::class)->create(
                learner: $user,
                preferredWindow: 'Weekends',
                topic: 'Should not work',
                notes: null,
                idempotencyKey: (string) Str::uuid(),
            ),
        );
    }

    /**
     * @return array{User, Membership, OrganizationContext}
     */
    private function learner(?Organization $organization = null): array
    {
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
            'tuition-'.strtolower((string) Str::ulid()),
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

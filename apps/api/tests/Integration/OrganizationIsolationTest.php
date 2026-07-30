<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Audit\AuditEventRecorder;
use App\Models\Cohort;
use App\Models\Enrollment;
use App\Models\InstitutionInvite;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Role;
use App\Policies\CohortPolicy;
use App\Policies\EnrollmentPolicy;
use App\Policies\InstitutionInvitePolicy;
use App\Policies\MembershipPolicy;
use App\Policies\RolePolicy;
use App\Tenancy\Exceptions\AmbiguousOrganizationContext;
use App\Tenancy\Exceptions\InvalidOrganizationContext;
use App\Tenancy\Exceptions\MissingOrganizationContext;
use App\Tenancy\InstitutionInviteCodeDigester;
use App\Tenancy\OrganizationContextResolver;
use App\Tenancy\PlatformOperationAuthorizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;

final class OrganizationIsolationTest extends TenancyIntegrationTestCase
{
    public function test_tenant_queries_fail_closed_and_context_never_leaks(): void
    {
        $organization = $this->createOrganization('Context Institution');
        $user = $this->createUser();
        [, $context] = $this->createMembership($organization, $user);

        try {
            Cohort::query()->count();
            $this->fail('A tenant query without context must fail closed.');
        } catch (MissingOrganizationContext) {
            $this->assertNull($this->contexts()->currentOrNull());
        }

        try {
            $this->contexts()->run(
                $context,
                static fn (): never => throw new RuntimeException('synthetic failure'),
            );
        } catch (RuntimeException) {
            $this->assertNull($this->contexts()->currentOrNull());
        }
    }

    public function test_scope_hides_other_tenant_and_creation_ignores_caller_ownership(): void
    {
        $organizationA = $this->createOrganization('Tenant A');
        $userA = $this->createUser();
        [, $contextA] = $this->createMembership($organizationA, $userA);
        $organizationB = $this->createOrganization('Tenant B');
        $userB = $this->createUser();
        [, $contextB] = $this->createMembership($organizationB, $userB);
        $cohortA = $this->createCohort($contextA, 'A Cohort');
        $cohortB = $this->createCohort($contextB, 'B Cohort');

        $this->contexts()->run($contextA, function () use (
            $organizationA,
            $organizationB,
            $cohortA,
            $cohortB,
        ): void {
            $this->assertSame([$cohortA->getKey()], Cohort::query()->pluck('id')->all());
            $this->assertNull(Cohort::query()->find($cohortB->getKey()));

            $created = new Cohort([
                'organization_id' => $organizationB->getKey(),
                'name' => 'Caller Ownership Attempt',
                'status' => Cohort::STATUS_ACTIVE,
            ]);
            $created->forceFill(['key' => 'caller-owned-key'])->save();

            $this->assertSame($organizationA->getKey(), $created->organizationId());
        });
    }

    public function test_model_rejects_explicit_cross_tenant_ownership_mutation(): void
    {
        $organizationA = $this->createOrganization('Mutation Tenant A');
        $userA = $this->createUser();
        [, $contextA] = $this->createMembership($organizationA, $userA);
        $organizationB = $this->createOrganization('Mutation Tenant B');

        $this->expectException(LogicException::class);

        $this->contexts()->run($contextA, static function () use ($organizationB): void {
            $cohort = new Cohort([
                'key' => 'explicit-cross-tenant',
                'name' => 'Cross Tenant',
                'status' => Cohort::STATUS_ACTIVE,
            ]);
            $cohort->setAttribute('organization_id', $organizationB->getKey());
            $cohort->save();
        });
    }

    public function test_authoritative_tenant_fields_cannot_be_mass_assigned(): void
    {
        $organizationA = $this->createOrganization('Mass Assignment Tenant A');
        $userA = $this->createUser();
        [$membershipA, $contextA] = $this->createMembership(
            $organizationA,
            $userA,
        );
        $otherUser = $this->createUser();
        [$otherMembership] = $this->createMembership(
            $organizationA,
            $otherUser,
        );
        $roleA = $this->createRole($contextA, 'mass-assignment-role-a');
        $roleB = $this->createRole($contextA, 'mass-assignment-role-b');
        $cohortA = $this->createCohort(
            $contextA,
            'Mass Assignment Cohort A',
        );
        $cohortB = $this->createCohort(
            $contextA,
            'Mass Assignment Cohort B',
        );
        $organizationB = $this->createOrganization('Mass Assignment Tenant B');
        $codes = $this->app->make(InstitutionInviteCodeDigester::class);
        $invite = $this->contexts()->run(
            $contextA,
            static fn (): InstitutionInvite => InstitutionInvite::query()
                ->forceCreate([
                    'code_digest' => $codes->digest('MASS-ASSIGNMENT-INVITE'),
                    'membership_type' => Membership::TYPE_LEARNER,
                    'role_id' => $roleA->getKey(),
                    'cohort_id' => $cohortA->getKey(),
                    'status' => InstitutionInvite::STATUS_PENDING,
                    'expires_at' => now()->addDay(),
                ]),
        );
        $enrollment = $this->contexts()->run(
            $contextA,
            static fn (): Enrollment => Enrollment::query()->forceCreate([
                'learner_membership_id' => $membershipA->getKey(),
                'cohort_id' => $cohortA->getKey(),
                'status' => Enrollment::STATUS_PENDING_CURRICULUM,
                'enrolled_at' => now(),
            ]),
        );

        $membershipA->fill([
            'organization_id' => $organizationB->getKey(),
            'user_id' => $otherUser->getKey(),
            'membership_type' => Membership::TYPE_TRAINER,
            'status' => Membership::STATUS_ENDED,
            'joined_at' => now()->subYear(),
            'ended_at' => now(),
        ]);
        self::assertFalse($membershipA->isDirty());

        $roleA->fill([
            'organization_id' => $organizationB->getKey(),
            'key' => 'elevated-system-role',
            'is_system' => true,
        ]);
        self::assertFalse($roleA->isDirty());

        $cohortA->fill([
            'organization_id' => $organizationB->getKey(),
            'key' => 'cross-tenant-cohort-key',
        ]);
        self::assertFalse($cohortA->isDirty());

        $invite->fill([
            'organization_id' => $organizationB->getKey(),
            'code_digest' => hash('sha256', 'attacker-controlled-code'),
            'membership_type' => Membership::TYPE_ORGANIZATION_ADMIN,
            'role_id' => $roleB->getKey(),
            'cohort_id' => $cohortB->getKey(),
            'status' => InstitutionInvite::STATUS_ACCEPTED,
            'expires_at' => now()->addYear(),
            'accepted_membership_id' => $otherMembership->getKey(),
            'accepted_by_user_id' => $otherUser->getKey(),
            'accepted_idempotency_key' => (string) Str::uuid(),
            'accepted_request_fingerprint' => hash('sha256', 'attacker'),
            'accepted_at' => now(),
        ]);
        self::assertFalse($invite->isDirty());

        $enrollment->fill([
            'organization_id' => $organizationB->getKey(),
            'learner_membership_id' => $otherMembership->getKey(),
            'cohort_id' => $cohortB->getKey(),
            'status' => 'completed',
            'enrolled_at' => now()->subYear(),
            'ended_at' => now(),
        ]);
        self::assertFalse($enrollment->isDirty());
    }

    public function test_cross_tenant_creation_is_rejected_for_every_tenant_model(): void
    {
        $organizationA = $this->createOrganization('Create Guard Tenant A');
        $actor = $this->createUser();
        [$membershipA, $contextA] = $this->createMembership(
            $organizationA,
            $actor,
        );
        $cohortA = $this->createCohort($contextA, 'Create Guard Cohort');
        $organizationB = $this->createOrganization('Create Guard Tenant B');
        $targetUser = $this->createUser();

        $models = [
            (new Membership)->forceFill([
                'organization_id' => $organizationB->getKey(),
                'user_id' => $targetUser->getKey(),
                'membership_type' => Membership::TYPE_LEARNER,
                'status' => Membership::STATUS_ACTIVE,
                'joined_at' => now(),
            ]),
            (new Role)->forceFill([
                'organization_id' => $organizationB->getKey(),
                'key' => 'cross-tenant-created-role',
                'name' => 'Cross-tenant Created Role',
                'is_system' => false,
            ]),
            (new Cohort)->forceFill([
                'organization_id' => $organizationB->getKey(),
                'key' => 'cross-tenant-created-cohort',
                'name' => 'Cross-tenant Created Cohort',
                'status' => Cohort::STATUS_ACTIVE,
            ]),
            (new InstitutionInvite)->forceFill([
                'organization_id' => $organizationB->getKey(),
                'code_digest' => hash('sha256', 'cross-created-invite'),
                'membership_type' => Membership::TYPE_LEARNER,
                'status' => InstitutionInvite::STATUS_PENDING,
                'expires_at' => now()->addDay(),
            ]),
            (new Enrollment)->forceFill([
                'organization_id' => $organizationB->getKey(),
                'learner_membership_id' => $membershipA->getKey(),
                'cohort_id' => $cohortA->getKey(),
                'status' => Enrollment::STATUS_PENDING_CURRICULUM,
                'enrolled_at' => now(),
            ]),
        ];

        $this->contexts()->run($contextA, function () use ($models): void {
            foreach ($models as $model) {
                try {
                    $model->save();
                    self::fail(sprintf(
                        '%s must reject cross-tenant creation.',
                        $model::class,
                    ));
                } catch (LogicException) {
                    self::assertFalse($model->exists);
                }
            }
        });
    }

    public function test_cross_tenant_update_and_delete_are_rejected_for_every_tenant_model(): void
    {
        $organizationA = $this->createOrganization('Write Matrix Tenant A');
        $userA = $this->createUser();
        [, $contextA] = $this->createMembership($organizationA, $userA);
        $organizationB = $this->createOrganization('Write Matrix Tenant B');
        $userB = $this->createUser();
        [$membershipB, $contextB] = $this->createMembership(
            $organizationB,
            $userB,
        );
        $roleB = $this->createRole($contextB, 'write-matrix-role-b');
        $cohortB = $this->createCohort($contextB, 'Write Matrix Cohort B');
        $codes = $this->app->make(InstitutionInviteCodeDigester::class);
        $inviteB = $this->contexts()->run(
            $contextB,
            static fn (): InstitutionInvite => InstitutionInvite::query()
                ->forceCreate([
                    'code_digest' => $codes->digest('WRITE-MATRIX-INVITE-B'),
                    'membership_type' => Membership::TYPE_LEARNER,
                    'status' => InstitutionInvite::STATUS_PENDING,
                    'expires_at' => now()->addDay(),
                ]),
        );
        $enrollmentB = $this->contexts()->run(
            $contextB,
            static fn (): Enrollment => Enrollment::query()->forceCreate([
                'learner_membership_id' => $membershipB->getKey(),
                'cohort_id' => $cohortB->getKey(),
                'status' => Enrollment::STATUS_PENDING_CURRICULUM,
                'enrolled_at' => now(),
            ]),
        );
        $models = [
            [$membershipB, 'status', Membership::STATUS_SUSPENDED],
            [$roleB, 'name', 'Cross-tenant Role Update'],
            [$cohortB, 'name', 'Cross-tenant Cohort Update'],
            [$inviteB, 'expires_at', now()->addYear()],
            [$enrollmentB, 'enrolled_at', now()->subYear()],
        ];

        $this->contexts()->run($contextA, function () use ($models): void {
            foreach ($models as [$model, $attribute, $value]) {
                $model->setAttribute($attribute, $value);

                try {
                    $model->save();
                    self::fail(sprintf(
                        '%s must reject cross-tenant updates.',
                        $model::class,
                    ));
                } catch (LogicException) {
                    $this->assertDatabaseHas($model->getTable(), [
                        'id' => $model->getKey(),
                    ]);
                }

                try {
                    $model->delete();
                    self::fail(sprintf(
                        '%s must reject cross-tenant deletes.',
                        $model::class,
                    ));
                } catch (LogicException) {
                    $this->assertDatabaseHas($model->getTable(), [
                        'id' => $model->getKey(),
                    ]);
                }
            }
        });
    }

    public function test_unscoped_model_cannot_be_updated_or_deleted_from_another_context(): void
    {
        $organizationA = $this->createOrganization('Write Tenant A');
        $userA = $this->createUser();
        [, $contextA] = $this->createMembership($organizationA, $userA);
        $organizationB = $this->createOrganization('Write Tenant B');
        $userB = $this->createUser();
        [, $contextB] = $this->createMembership($organizationB, $userB);
        $cohortB = $this->createCohort($contextB, 'Protected Tenant B Cohort');

        $this->contexts()->run($contextA, function () use ($cohortB): void {
            /** @var Cohort $unscoped */
            $unscoped = Cohort::withoutGlobalScope('organization')
                ->findOrFail($cohortB->getKey());
            $unscoped->name = 'Cross-tenant update attempt';

            try {
                $unscoped->save();
                $this->fail('An unscoped model must not bypass update tenancy checks.');
            } catch (LogicException) {
                $this->assertDatabaseHas('cohorts', [
                    'id' => $cohortB->getKey(),
                    'name' => 'Protected Tenant B Cohort',
                ]);
            }

            try {
                $unscoped->delete();
                $this->fail('An unscoped model must not bypass delete tenancy checks.');
            } catch (LogicException) {
                $this->assertDatabaseHas('cohorts', [
                    'id' => $cohortB->getKey(),
                ]);
            }
        });
    }

    public function test_policy_requires_both_permission_and_tenant_equality(): void
    {
        $organizationA = $this->createOrganization('Policy Tenant A');
        $userA = $this->createUser();
        [$membershipA, $contextA] = $this->createMembership($organizationA, $userA);
        $organizationB = $this->createOrganization('Policy Tenant B');
        $userB = $this->createUser();
        [, $contextB] = $this->createMembership($organizationB, $userB);
        $role = $this->createRole($contextA, 'cohort-reader');
        $cohortA = $this->createCohort($contextA, 'Policy A Cohort');
        $cohortB = $this->createCohort($contextB, 'Policy B Cohort');
        $permission = Permission::query()->where('key', 'cohorts.read')->sole();
        $now = now();

        DB::table('membership_roles')->insert([
            'organization_id' => $organizationA->getKey(),
            'membership_id' => $membershipA->getKey(),
            'role_id' => $role->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('role_permissions')->insert([
            'organization_id' => $organizationA->getKey(),
            'role_id' => $role->getKey(),
            'permission_id' => $permission->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->contexts()->run($contextA, function () use (
            $userA,
            $cohortA,
            $cohortB,
        ): void {
            $policy = $this->app->make(CohortPolicy::class);

            $this->assertTrue($policy->view($userA, $cohortA));
            $this->assertFalse($policy->view($userA, $cohortB));
        });

        $organizationA->forceFill(['status' => 'suspended'])->save();

        $this->contexts()->run($contextA, function () use (
            $userA,
            $cohortA,
        ): void {
            $this->assertFalse(
                $this->app
                    ->make(CohortPolicy::class)
                    ->view($userA, $cohortA),
            );
        });
    }

    public function test_every_tenant_model_and_policy_denies_cross_tenant_access(): void
    {
        $organizationA = $this->createOrganization('Complete Policy Tenant A');
        $userA = $this->createUser();
        [$membershipA, $contextA] = $this->createMembership($organizationA, $userA);
        $organizationB = $this->createOrganization('Complete Policy Tenant B');
        $userB = $this->createUser();
        [$membershipB, $contextB] = $this->createMembership($organizationB, $userB);
        $roleA = $this->createRole($contextA, 'all-foundation-reader');
        $roleB = $this->createRole($contextB, 'tenant-b-reader');
        $cohortA = $this->createCohort($contextA, 'Complete A Cohort');
        $cohortB = $this->createCohort($contextB, 'Complete B Cohort');
        $codes = $this->app->make(InstitutionInviteCodeDigester::class);
        $inviteA = $this->contexts()->run(
            $contextA,
            static fn (): InstitutionInvite => InstitutionInvite::query()->forceCreate([
                'code_digest' => $codes->digest('COMPLETE-A-INVITE'),
                'membership_type' => Membership::TYPE_LEARNER,
                'status' => InstitutionInvite::STATUS_PENDING,
                'expires_at' => now()->addDay(),
            ]),
        );
        $inviteB = $this->contexts()->run(
            $contextB,
            static fn (): InstitutionInvite => InstitutionInvite::query()->forceCreate([
                'code_digest' => $codes->digest('COMPLETE-B-INVITE'),
                'membership_type' => Membership::TYPE_LEARNER,
                'status' => InstitutionInvite::STATUS_PENDING,
                'expires_at' => now()->addDay(),
            ]),
        );
        $enrollmentA = $this->contexts()->run(
            $contextA,
            static fn (): Enrollment => Enrollment::query()->forceCreate([
                'learner_membership_id' => $membershipA->getKey(),
                'cohort_id' => $cohortA->getKey(),
                'status' => Enrollment::STATUS_PENDING_CURRICULUM,
                'enrolled_at' => now(),
            ]),
        );
        $enrollmentB = $this->contexts()->run(
            $contextB,
            static fn (): Enrollment => Enrollment::query()->forceCreate([
                'learner_membership_id' => $membershipB->getKey(),
                'cohort_id' => $cohortB->getKey(),
                'status' => Enrollment::STATUS_PENDING_CURRICULUM,
                'enrolled_at' => now(),
            ]),
        );
        $now = now();

        DB::table('membership_roles')->insert([
            'organization_id' => $organizationA->getKey(),
            'membership_id' => $membershipA->getKey(),
            'role_id' => $roleA->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (Permission::query()->get() as $permission) {
            DB::table('role_permissions')->insert([
                'organization_id' => $organizationA->getKey(),
                'role_id' => $roleA->getKey(),
                'permission_id' => $permission->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->contexts()->run($contextA, function () use (
            $userA,
            $userB,
            $membershipA,
            $membershipB,
            $roleA,
            $roleB,
            $cohortA,
            $cohortB,
            $inviteA,
            $inviteB,
            $enrollmentA,
            $enrollmentB,
        ): void {
            $this->assertSame(
                [$membershipA->getKey()],
                Membership::query()->pluck('id')->all(),
            );
            $this->assertSame(
                [$roleA->getKey()],
                Role::query()->pluck('id')->all(),
            );
            $this->assertSame(
                [$cohortA->getKey()],
                Cohort::query()->pluck('id')->all(),
            );
            $this->assertSame(
                [$inviteA->getKey()],
                InstitutionInvite::query()->pluck('id')->all(),
            );
            $this->assertSame(
                [$enrollmentA->getKey()],
                Enrollment::query()->pluck('id')->all(),
            );
            $this->assertNull(Membership::query()->find($membershipB->getKey()));
            $this->assertNull(Role::query()->find($roleB->getKey()));
            $this->assertNull(Cohort::query()->find($cohortB->getKey()));
            $this->assertNull(InstitutionInvite::query()->find($inviteB->getKey()));
            $this->assertNull(Enrollment::query()->find($enrollmentB->getKey()));

            $membershipPolicy = $this->app->make(MembershipPolicy::class);
            $this->assertTrue($membershipPolicy->viewAny($userA));
            $this->assertFalse($membershipPolicy->viewAny($userB));
            $this->assertTrue($membershipPolicy->create($userA));
            $this->assertFalse($membershipPolicy->create($userB));
            $this->assertTrue($membershipPolicy->view($userA, $membershipA));
            $this->assertFalse($membershipPolicy->view($userA, $membershipB));
            $this->assertTrue($membershipPolicy->update($userA, $membershipA));
            $this->assertFalse($membershipPolicy->update($userA, $membershipB));
            $this->assertTrue($membershipPolicy->delete($userA, $membershipA));
            $this->assertFalse($membershipPolicy->delete($userA, $membershipB));

            $rolePolicy = $this->app->make(RolePolicy::class);
            $this->assertTrue($rolePolicy->viewAny($userA));
            $this->assertFalse($rolePolicy->viewAny($userB));
            $this->assertTrue($rolePolicy->create($userA));
            $this->assertFalse($rolePolicy->create($userB));
            $this->assertTrue($rolePolicy->view($userA, $roleA));
            $this->assertFalse($rolePolicy->view($userA, $roleB));
            $this->assertTrue($rolePolicy->update($userA, $roleA));
            $this->assertFalse($rolePolicy->update($userA, $roleB));
            $this->assertTrue($rolePolicy->delete($userA, $roleA));
            $this->assertFalse($rolePolicy->delete($userA, $roleB));

            $cohortPolicy = $this->app->make(CohortPolicy::class);
            $this->assertTrue($cohortPolicy->viewAny($userA));
            $this->assertFalse($cohortPolicy->viewAny($userB));
            $this->assertTrue($cohortPolicy->create($userA));
            $this->assertFalse($cohortPolicy->create($userB));
            $this->assertTrue($cohortPolicy->view($userA, $cohortA));
            $this->assertFalse($cohortPolicy->view($userA, $cohortB));
            $this->assertTrue($cohortPolicy->update($userA, $cohortA));
            $this->assertFalse($cohortPolicy->update($userA, $cohortB));
            $this->assertTrue($cohortPolicy->delete($userA, $cohortA));
            $this->assertFalse($cohortPolicy->delete($userA, $cohortB));

            $invitePolicy = $this->app->make(InstitutionInvitePolicy::class);
            $this->assertTrue($invitePolicy->viewAny($userA));
            $this->assertFalse($invitePolicy->viewAny($userB));
            $this->assertTrue($invitePolicy->create($userA));
            $this->assertFalse($invitePolicy->create($userB));
            $this->assertTrue($invitePolicy->view($userA, $inviteA));
            $this->assertFalse($invitePolicy->view($userA, $inviteB));
            $this->assertTrue($invitePolicy->update($userA, $inviteA));
            $this->assertFalse($invitePolicy->update($userA, $inviteB));
            $this->assertTrue($invitePolicy->delete($userA, $inviteA));
            $this->assertFalse($invitePolicy->delete($userA, $inviteB));

            $enrollmentPolicy = $this->app->make(EnrollmentPolicy::class);
            $this->assertTrue($enrollmentPolicy->viewAny($userA));
            $this->assertFalse($enrollmentPolicy->viewAny($userB));
            $this->assertTrue($enrollmentPolicy->create($userA));
            $this->assertFalse($enrollmentPolicy->create($userB));
            $this->assertTrue($enrollmentPolicy->view($userA, $enrollmentA));
            $this->assertFalse($enrollmentPolicy->view($userA, $enrollmentB));
            $this->assertTrue($enrollmentPolicy->update($userA, $enrollmentA));
            $this->assertFalse($enrollmentPolicy->update($userA, $enrollmentB));
            $this->assertTrue($enrollmentPolicy->delete($userA, $enrollmentA));
            $this->assertFalse($enrollmentPolicy->delete($userA, $enrollmentB));

            foreach ([
                [new Membership, $membershipA, $membershipB],
                [new Role, $roleA, $roleB],
                [new Cohort, $cohortA, $cohortB],
                [new InstitutionInvite, $inviteA, $inviteB],
                [new Enrollment, $enrollmentA, $enrollmentB],
            ] as [$binding, $ownResource, $otherResource]) {
                $resolved = $binding->resolveRouteBinding(
                    (string) $ownResource->getKey(),
                );
                $this->assertSame(
                    $ownResource->getKey(),
                    $resolved?->getKey(),
                );
                $this->assertNull($binding->resolveRouteBinding(
                    (string) $otherResource->getKey(),
                ));
            }
        });
    }

    public function test_context_resolver_validates_membership_and_rejects_ambiguity(): void
    {
        $user = $this->createUser();
        $firstOrganization = $this->createOrganization('Resolver Tenant A');
        [$firstMembership] = $this->createMembership($firstOrganization, $user);
        $resolver = $this->app->make(OrganizationContextResolver::class);

        $single = $resolver->singleActiveMembership($user);
        $selected = $resolver->forSelectedMembership(
            $user,
            (string) $firstMembership->getKey(),
        );

        $this->assertSame($firstOrganization->getKey(), $single->organizationId);
        $this->assertSame($single->organizationId, $selected->organizationId);

        $secondOrganization = $this->createOrganization('Resolver Tenant B');
        $this->createMembership($secondOrganization, $user);

        try {
            $resolver->singleActiveMembership($user);
            $this->fail('Multiple memberships must require an explicit selection.');
        } catch (AmbiguousOrganizationContext) {
            $this->assertSame(
                $firstOrganization->getKey(),
                $resolver->forSelectedMembership(
                    $user,
                    (string) $firstMembership->getKey(),
                )->organizationId,
            );
        }

        try {
            $resolver->forSelectedMembership(
                $this->createUser(),
                (string) $firstMembership->getKey(),
            );
            $this->fail('A membership must not establish context for another user.');
        } catch (InvalidOrganizationContext) {
            $this->assertNull($this->contexts()->currentOrNull());
        }
    }

    public function test_postgresql_composite_fk_rejects_cross_tenant_role_assignment(): void
    {
        $organizationA = $this->createOrganization('Role FK Tenant A');
        $userA = $this->createUser();
        [$membershipA] = $this->createMembership($organizationA, $userA);
        $organizationB = $this->createOrganization('Role FK Tenant B');
        $userB = $this->createUser();
        [, $contextB] = $this->createMembership($organizationB, $userB);
        $roleB = $this->createRole($contextB, 'tenant-b-role');

        $this->expectException(QueryException::class);

        DB::table('membership_roles')->insert([
            'organization_id' => $organizationA->getKey(),
            'membership_id' => $membershipA->getKey(),
            'role_id' => $roleB->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_postgresql_composite_fk_rejects_cross_tenant_enrollment(): void
    {
        $organizationA = $this->createOrganization('Enrollment FK Tenant A');
        $userA = $this->createUser();
        [$membershipA] = $this->createMembership($organizationA, $userA);
        $organizationB = $this->createOrganization('Enrollment FK Tenant B');
        $userB = $this->createUser();
        [, $contextB] = $this->createMembership($organizationB, $userB);
        $cohortB = $this->createCohort($contextB, 'Tenant B Cohort');

        $this->expectException(QueryException::class);

        DB::table('enrollments')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationA->getKey(),
            'learner_membership_id' => $membershipA->getKey(),
            'cohort_id' => $cohortB->getKey(),
            'status' => 'pending_curriculum',
            'enrolled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_audit_events_are_append_only(): void
    {
        $user = $this->createUser();
        $authorization = $this->app
            ->make(PlatformOperationAuthorizer::class)
            ->authorizeAuthenticationSessionAudit(
                action: 'authentication.session.revoked',
                reasonKey: 'user_revoked',
                authenticationSessionId: (string) $user->getKey(),
                actorUserId: (string) $user->getKey(),
            );
        $event = $this->app->make(AuditEventRecorder::class)->recordPlatform(
            $authorization,
        );

        $this->expectException(LogicException::class);

        $event->forceFill(['action' => 'authentication.session.changed'])->save();
    }

    public function test_postgresql_rejects_direct_audit_event_mutation(): void
    {
        $user = $this->createUser();
        $authorization = $this->app
            ->make(PlatformOperationAuthorizer::class)
            ->authorizeAuthenticationSessionAudit(
                action: 'authentication.session.revoked',
                reasonKey: 'user_revoked',
                authenticationSessionId: (string) $user->getKey(),
                actorUserId: (string) $user->getKey(),
            );
        $event = $this->app->make(AuditEventRecorder::class)->recordPlatform(
            $authorization,
        );

        $this->expectException(QueryException::class);

        DB::table('audit_events')
            ->where('id', $event->getKey())
            ->update(['action' => 'authentication.session.changed']);
    }
}

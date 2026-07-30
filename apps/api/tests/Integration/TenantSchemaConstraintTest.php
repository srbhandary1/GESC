<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Membership;
use App\Models\Permission;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

final class TenantSchemaConstraintTest extends TenancyIntegrationTestCase
{
    #[DataProvider('organizationOwnedTableProvider')]
    public function test_postgresql_rejects_null_organization_ownership(
        string $table,
    ): void {
        $organization = $this->createOrganization('Non-null Ownership Tenant');
        $user = $this->createUser();
        [$membership, $context] = $this->createMembership(
            $organization,
            $user,
        );
        $role = $this->createRole($context, 'non-null-role');
        $cohort = $this->createCohort($context, 'Non-null Cohort');
        $permission = Permission::query()
            ->where('key', 'roles.manage')
            ->sole();
        $now = now();
        $payload = match ($table) {
            'memberships' => [
                'id' => (string) Str::ulid(),
                'organization_id' => null,
                'user_id' => $this->createUser()->getKey(),
                'membership_type' => Membership::TYPE_LEARNER,
                'status' => Membership::STATUS_ACTIVE,
                'joined_at' => $now,
                'ended_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            'roles' => [
                'id' => (string) Str::ulid(),
                'organization_id' => null,
                'key' => 'null-ownership-role',
                'name' => 'Null Ownership Role',
                'is_system' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            'cohorts' => [
                'id' => (string) Str::ulid(),
                'organization_id' => null,
                'key' => 'null-ownership-cohort',
                'name' => 'Null Ownership Cohort',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            'role_permissions' => [
                'organization_id' => null,
                'role_id' => $role->getKey(),
                'permission_id' => $permission->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            'membership_roles' => [
                'organization_id' => null,
                'membership_id' => $membership->getKey(),
                'role_id' => $role->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            'cohort_trainers' => [
                'organization_id' => null,
                'cohort_id' => $cohort->getKey(),
                'trainer_membership_id' => $membership->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            'institution_invites' => [
                'id' => (string) Str::ulid(),
                'organization_id' => null,
                'code_digest' => hash('sha256', 'null-ownership-invite'),
                'membership_type' => Membership::TYPE_LEARNER,
                'role_id' => null,
                'cohort_id' => null,
                'status' => 'pending',
                'expires_at' => $now->copy()->addDay(),
                'accepted_membership_id' => null,
                'accepted_by_user_id' => null,
                'accepted_idempotency_key' => null,
                'accepted_request_fingerprint' => null,
                'accepted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            'enrollments' => [
                'id' => (string) Str::ulid(),
                'organization_id' => null,
                'learner_membership_id' => $membership->getKey(),
                'cohort_id' => $cohort->getKey(),
                'status' => 'pending_curriculum',
                'enrolled_at' => $now,
                'ended_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            default => throw new InvalidArgumentException(
                'Unknown organization-owned table.',
            ),
        };

        try {
            DB::table($table)->insert($payload);
            self::fail(sprintf(
                '%s must reject null organization ownership.',
                $table,
            ));
        } catch (QueryException $exception) {
            self::assertSame('23502', (string) $exception->getCode());
            self::assertStringContainsString(
                'organization_id',
                $exception->getMessage(),
            );
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function organizationOwnedTableProvider(): array
    {
        return [
            'membership' => ['memberships'],
            'role' => ['roles'],
            'cohort' => ['cohorts'],
            'role permission attachment' => ['role_permissions'],
            'membership role attachment' => ['membership_roles'],
            'cohort trainer attachment' => ['cohort_trainers'],
            'institution invite' => ['institution_invites'],
            'enrollment' => ['enrollments'],
        ];
    }

    public function test_tenant_prefixed_business_key_is_reusable_between_tenants_but_unique_within_one(): void
    {
        $organizationA = $this->createOrganization('Unique Key Tenant A');
        $userA = $this->createUser();
        [, $contextA] = $this->createMembership($organizationA, $userA);
        $organizationB = $this->createOrganization('Unique Key Tenant B');
        $userB = $this->createUser();
        [, $contextB] = $this->createMembership($organizationB, $userB);
        $now = now();

        foreach ([$contextA, $contextB] as $context) {
            DB::table('cohorts')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $context->organizationId,
                'key' => 'shared-business-key',
                'name' => 'Shared Business Key',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        try {
            DB::table('cohorts')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationA->getKey(),
                'key' => 'shared-business-key',
                'name' => 'Duplicate Business Key',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            self::fail('A business key must be unique within one organization.');
        } catch (QueryException $exception) {
            self::assertSame('23505', (string) $exception->getCode());
            self::assertStringContainsString(
                'cohorts_organization_id_key_unique',
                $exception->getMessage(),
            );
        }
    }

    #[DataProvider('crossTenantAttachmentProvider')]
    public function test_postgresql_rejects_cross_tenant_attachments(
        string $attachment,
    ): void {
        $organizationA = $this->createOrganization('Attachment Tenant A');
        $userA = $this->createUser();
        [$membershipA, $contextA] = $this->createMembership(
            $organizationA,
            $userA,
        );
        $roleA = $this->createRole($contextA, 'attachment-role-a');
        $cohortA = $this->createCohort($contextA, 'Attachment Cohort A');
        $organizationB = $this->createOrganization('Attachment Tenant B');
        $userB = $this->createUser();
        [$membershipB, $contextB] = $this->createMembership(
            $organizationB,
            $userB,
        );
        $roleB = $this->createRole($contextB, 'attachment-role-b');
        $cohortB = $this->createCohort($contextB, 'Attachment Cohort B');
        $permission = Permission::query()
            ->where('key', 'roles.manage')
            ->sole();
        $now = now();
        [$table, $payload, $constraint] = match ($attachment) {
            'role_permission' => [
                'role_permissions',
                [
                    'organization_id' => $organizationA->getKey(),
                    'role_id' => $roleB->getKey(),
                    'permission_id' => $permission->getKey(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                'role_permissions_role_tenant_fk',
            ],
            'membership_role' => [
                'membership_roles',
                [
                    'organization_id' => $organizationA->getKey(),
                    'membership_id' => $membershipA->getKey(),
                    'role_id' => $roleB->getKey(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                'membership_roles_role_tenant_fk',
            ],
            'cohort_trainer' => [
                'cohort_trainers',
                [
                    'organization_id' => $organizationA->getKey(),
                    'cohort_id' => $cohortB->getKey(),
                    'trainer_membership_id' => $membershipA->getKey(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                'cohort_trainers_cohort_tenant_fk',
            ],
            'invite_role' => [
                'institution_invites',
                [
                    'id' => (string) Str::ulid(),
                    'organization_id' => $organizationA->getKey(),
                    'code_digest' => hash('sha256', 'cross-role-invite'),
                    'membership_type' => Membership::TYPE_LEARNER,
                    'role_id' => $roleB->getKey(),
                    'cohort_id' => null,
                    'status' => 'pending',
                    'expires_at' => $now->copy()->addDay(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                'institution_invites_role_tenant_fk',
            ],
            'invite_cohort' => [
                'institution_invites',
                [
                    'id' => (string) Str::ulid(),
                    'organization_id' => $organizationA->getKey(),
                    'code_digest' => hash('sha256', 'cross-cohort-invite'),
                    'membership_type' => Membership::TYPE_LEARNER,
                    'role_id' => null,
                    'cohort_id' => $cohortB->getKey(),
                    'status' => 'pending',
                    'expires_at' => $now->copy()->addDay(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                'institution_invites_cohort_tenant_fk',
            ],
            'enrollment' => [
                'enrollments',
                [
                    'id' => (string) Str::ulid(),
                    'organization_id' => $organizationA->getKey(),
                    'learner_membership_id' => $membershipA->getKey(),
                    'cohort_id' => $cohortB->getKey(),
                    'status' => 'pending_curriculum',
                    'enrolled_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                'enrollments_cohort_tenant_fk',
            ],
            default => throw new InvalidArgumentException(
                'Unknown tenant attachment.',
            ),
        };

        self::assertSame(
            $organizationA->getKey(),
            $membershipA->organizationId(),
        );
        self::assertSame(
            $organizationB->getKey(),
            $membershipB->organizationId(),
        );
        self::assertSame($organizationA->getKey(), $roleA->organizationId());
        self::assertSame($organizationA->getKey(), $cohortA->organizationId());

        try {
            DB::table($table)->insert($payload);
            self::fail(sprintf(
                'The %s cross-tenant attachment must fail.',
                $attachment,
            ));
        } catch (QueryException $exception) {
            self::assertSame('23503', (string) $exception->getCode());
            self::assertStringContainsString(
                $constraint,
                $exception->getMessage(),
            );
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function crossTenantAttachmentProvider(): array
    {
        return [
            'role permission' => ['role_permission'],
            'membership role' => ['membership_role'],
            'cohort trainer' => ['cohort_trainer'],
            'invite role' => ['invite_role'],
            'invite cohort' => ['invite_cohort'],
            'enrollment' => ['enrollment'],
        ];
    }
}

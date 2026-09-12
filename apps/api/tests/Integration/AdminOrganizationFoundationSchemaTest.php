<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Branch;
use App\Models\Membership;
use App\Models\OrganizationAdministrationEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class AdminOrganizationFoundationSchemaTest extends TenancyIntegrationTestCase
{
    public function test_admin_organization_foundation_columns_and_config_are_present(): void
    {
        $expectedColumns = [
            'branches' => [
                'organization_id',
                'key',
                'name',
                'status',
                'version',
                'archived_at',
            ],
            'organization_administration_commands' => [
                'organization_id',
                'actor_membership_id',
                'idempotency_key',
                'operation',
                'target_type',
                'target_id',
                'request_fingerprint',
                'result_type',
                'result_id',
                'completed_at',
                'expires_at',
            ],
            'organization_administration_events' => [
                'organization_id',
                'actor_membership_id',
                'event_type',
                'subject_membership_id',
                'subject_role_id',
                'subject_cohort_id',
                'subject_branch_id',
                'subject_invitation_id',
                'permission_id',
                'command_id',
                'reason_key',
                'effective_at',
            ],
            'authentication_session_cleanup_requests' => [
                'authentication_session_id',
                'status',
                'attempts',
                'available_at',
                'leased_until',
                'completed_at',
            ],
        ];

        foreach ($expectedColumns as $table => $columns) {
            $actual = DB::table('information_schema.columns')
                ->where('table_schema', 'public')
                ->where('table_name', $table)
                ->whereIn('column_name', $columns)
                ->pluck('column_name')
                ->all();

            self::assertEqualsCanonicalizing($columns, $actual);
        }

        self::assertSame(
            365,
            config('operator.organization_administration_history_days'),
        );
        self::assertSame(
            [24, 72, 168],
            config('operator.institution_invite_allowed_hours'),
        );
    }

    public function test_postgresql_rejects_a_cross_tenant_branch_relationship(): void
    {
        $organizationA = $this->createOrganization('Branch Tenant A');
        $organizationB = $this->createOrganization('Branch Tenant B');
        $userB = $this->createUser();
        [, $contextB] = $this->createMembership($organizationB, $userB);
        $branchB = $this->contexts()->run(
            $contextB,
            static function (): Branch {
                $branch = new Branch;
                $branch->forceFill([
                    'key' => 'branch-b',
                    'name' => 'Branch B',
                    'status' => Branch::STATUS_ACTIVE,
                ])->save();

                return $branch;
            },
        );

        $this->expectPostgresViolation('23503');

        DB::table('cohorts')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationA->getKey(),
            'branch_id' => $branchB->getKey(),
            'key' => 'forged-cross-tenant-branch',
            'name' => 'Forged Cross Tenant Branch',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_postgresql_rejects_an_admin_event_with_the_wrong_target_shape(): void
    {
        $organization = $this->createOrganization('Event Shape Tenant');
        $user = $this->createUser();
        [$membership, $context] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $role = $this->createRole($context, 'event-shape-role');
        $branch = $this->contexts()->run(
            $context,
            static function (): Branch {
                $branch = new Branch;
                $branch->forceFill([
                    'key' => 'event-shape-branch',
                    'name' => 'Event Shape Branch',
                    'status' => Branch::STATUS_ACTIVE,
                ])->save();

                return $branch;
            },
        );

        $this->expectPostgresViolation('23514');

        DB::table('organization_administration_events')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->getKey(),
            'actor_membership_id' => $membership->getKey(),
            'event_type' => OrganizationAdministrationEvent::BRANCH_CREATED,
            'subject_membership_id' => null,
            'subject_role_id' => $role->getKey(),
            'subject_cohort_id' => null,
            'subject_branch_id' => $branch->getKey(),
            'subject_invitation_id' => null,
            'permission_id' => null,
            'command_id' => null,
            'reason_key' => null,
            'effective_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function test_postgresql_rejects_a_cross_tenant_admin_event_subject(): void
    {
        $organizationA = $this->createOrganization('Event Tenant A');
        $actor = $this->createUser();
        [$actorMembership] = $this->createMembership(
            $organizationA,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $organizationB = $this->createOrganization('Event Tenant B');
        $userB = $this->createUser();
        [, $contextB] = $this->createMembership($organizationB, $userB);
        $branchB = $this->contexts()->run(
            $contextB,
            static function (): Branch {
                $branch = new Branch;
                $branch->forceFill([
                    'key' => 'event-branch-b',
                    'name' => 'Event Branch B',
                    'status' => Branch::STATUS_ACTIVE,
                ])->save();

                return $branch;
            },
        );

        $this->expectPostgresViolation('23503');

        DB::table('organization_administration_events')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationA->getKey(),
            'actor_membership_id' => $actorMembership->getKey(),
            'event_type' => OrganizationAdministrationEvent::BRANCH_CREATED,
            'subject_membership_id' => null,
            'subject_role_id' => null,
            'subject_cohort_id' => null,
            'subject_branch_id' => $branchB->getKey(),
            'subject_invitation_id' => null,
            'permission_id' => null,
            'command_id' => null,
            'reason_key' => null,
            'effective_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function test_postgresql_rejects_a_web_selection_for_a_different_user(): void
    {
        $organization = $this->createOrganization('Web Selection Tenant');
        $user = $this->createUser();
        $otherUser = $this->createUser();
        [$otherMembership] = $this->createMembership(
            $organization,
            $otherUser,
        );

        $this->expectPostgresViolation('23503');

        $this->insertAuthenticationSession([
            'user_id' => $user->getKey(),
            'selected_organization_id' => $organization->getKey(),
            'selected_membership_id' => $otherMembership->getKey(),
        ]);
    }

    public function test_postgresql_requires_web_selection_columns_as_a_pair(): void
    {
        $organization = $this->createOrganization('Web Selection Pair Tenant');
        $user = $this->createUser();

        $this->expectPostgresViolation('23514');

        $this->insertAuthenticationSession([
            'user_id' => $user->getKey(),
            'selected_organization_id' => $organization->getKey(),
            'selected_membership_id' => null,
        ]);
    }

    public function test_postgresql_rejects_a_web_selection_with_a_mismatched_organization(): void
    {
        $organizationA = $this->createOrganization('Web Selection Tenant A');
        $organizationB = $this->createOrganization('Web Selection Tenant B');
        $user = $this->createUser();
        [$membershipA] = $this->createMembership($organizationA, $user);

        $this->expectPostgresViolation('23503');

        $this->insertAuthenticationSession([
            'user_id' => $user->getKey(),
            'selected_organization_id' => $organizationB->getKey(),
            'selected_membership_id' => $membershipA->getKey(),
        ]);
    }

    public function test_postgresql_restricts_operator_invitation_lifetime_choices(): void
    {
        $organization = $this->createOrganization('Invite Lifetime Tenant');
        $user = $this->createUser();
        [$issuer] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $createdAt = now();

        DB::table('institution_invites')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->getKey(),
            'code_digest' => hash('sha256', 'allowed-operator-invite'),
            'membership_type' => Membership::TYPE_TRAINER,
            'issued_by_membership_id' => $issuer->getKey(),
            'role_id' => null,
            'cohort_id' => null,
            'status' => 'pending',
            'expires_at' => $createdAt->copy()->addHours(72),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $this->expectPostgresViolation('23514');

        DB::table('institution_invites')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->getKey(),
            'code_digest' => hash('sha256', 'forged-operator-invite'),
            'membership_type' => Membership::TYPE_TRAINER,
            'issued_by_membership_id' => $issuer->getKey(),
            'role_id' => null,
            'cohort_id' => null,
            'status' => 'pending',
            'expires_at' => $createdAt->copy()->addHours(48),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function test_postgresql_rejects_a_cross_tenant_invitation_issuer(): void
    {
        $organizationA = $this->createOrganization('Invite Issuer Tenant A');
        $organizationB = $this->createOrganization('Invite Issuer Tenant B');
        $userB = $this->createUser();
        [$issuerB] = $this->createMembership(
            $organizationB,
            $userB,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $createdAt = now();

        $this->expectPostgresViolation('23503');

        DB::table('institution_invites')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationA->getKey(),
            'code_digest' => hash('sha256', 'cross-tenant-invite-issuer'),
            'membership_type' => Membership::TYPE_TRAINER,
            'issued_by_membership_id' => $issuerB->getKey(),
            'role_id' => null,
            'cohort_id' => null,
            'status' => 'pending',
            'expires_at' => $createdAt->copy()->addHours(72),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function test_postgresql_requires_command_expiry_to_be_exactly_365_days(): void
    {
        $organization = $this->createOrganization('Command Expiry Tenant');
        $user = $this->createUser();
        [$membership] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $completedAt = now();

        $this->expectPostgresViolation('23514');

        DB::table('organization_administration_commands')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->getKey(),
            'actor_membership_id' => $membership->getKey(),
            'idempotency_key' => (string) Str::uuid(),
            'operation' => 'branch.create',
            'target_type' => 'branch',
            'target_id' => (string) Str::ulid(),
            'request_fingerprint' => hash('sha256', 'command-request'),
            'result_type' => 'branch',
            'result_id' => (string) Str::ulid(),
            'completed_at' => $completedAt,
            'expires_at' => $completedAt->copy()->addDays(364),
            'created_at' => $completedAt,
        ]);
    }

    public function test_postgresql_rejects_a_processing_cleanup_without_a_lease(): void
    {
        $user = $this->createUser();
        $authenticationSession = $this->insertAuthenticationSession([
            'user_id' => $user->getKey(),
            'revoked_at' => now(),
            'revocation_reason' => 'test_revoked',
        ]);

        $this->expectPostgresViolation('23514');

        DB::table('authentication_session_cleanup_requests')->insert([
            'id' => (string) Str::ulid(),
            'authentication_session_id' => $authenticationSession,
            'status' => 'processing',
            'attempts' => 1,
            'available_at' => now(),
            'leased_until' => null,
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_migration_down_restores_a_tombstone_digest_before_requiring_it(): void
    {
        $organization = $this->createOrganization('Invite Rollback Tenant');
        $user = $this->createUser();
        [$membership] = $this->createMembership($organization, $user);
        $inviteId = (string) Str::ulid();
        $createdAt = now();
        DB::table('institution_invites')->insert([
            'id' => $inviteId,
            'organization_id' => $organization->getKey(),
            'code_digest' => null,
            'membership_type' => Membership::TYPE_TRAINER,
            'issued_by_membership_id' => $membership->getKey(),
            'role_id' => null,
            'cohort_id' => null,
            'status' => 'accepted',
            'expires_at' => $createdAt->copy()->addHours(24),
            'accepted_membership_id' => $membership->getKey(),
            'accepted_by_user_id' => $user->getKey(),
            'accepted_idempotency_key' => (string) Str::uuid(),
            'accepted_request_fingerprint' => hash(
                'sha256',
                'accepted-request',
            ),
            'accepted_at' => $createdAt,
            'code_discard_after' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $learnerMigration = require database_path(
            'migrations/2026_08_09_000130_create_admin_learner_foundation.php',
        );
        $learnerMigration->down();
        $migration = require database_path(
            'migrations/2026_08_09_000120_create_admin_org_foundation.php',
        );
        $migration->down();

        self::assertSame(
            hash('sha256', "admin-org-rollback\0".$inviteId),
            DB::table('institution_invites')
                ->where('id', $inviteId)
                ->value('code_digest'),
        );
        self::assertFalse(
            Schema::hasColumn(
                'authentication_sessions',
                'selected_membership_id',
            ),
        );

        $legacyRevokedInviteId = (string) Str::ulid();
        DB::table('institution_invites')->insert([
            'id' => $legacyRevokedInviteId,
            'organization_id' => $organization->getKey(),
            'code_digest' => hash('sha256', 'legacy-revoked-invite'),
            'membership_type' => Membership::TYPE_TRAINER,
            'role_id' => null,
            'cohort_id' => null,
            'status' => 'revoked',
            'expires_at' => $createdAt->copy()->addDay(),
            'accepted_membership_id' => null,
            'accepted_by_user_id' => null,
            'accepted_idempotency_key' => null,
            'accepted_request_fingerprint' => null,
            'accepted_at' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $migration->up();
        $learnerMigration->up();

        self::assertTrue(Schema::hasTable('branches'));
        self::assertTrue(
            Schema::hasColumn(
                'authentication_sessions',
                'selected_membership_id',
            ),
        );
        $legacyRevokedInvite = DB::table('institution_invites')
            ->where('id', $legacyRevokedInviteId)
            ->sole([
                'issued_by_membership_id',
                'revoked_by_membership_id',
                'revoked_at',
                'code_discard_after',
            ]);
        self::assertNull($legacyRevokedInvite->issued_by_membership_id);
        self::assertNull($legacyRevokedInvite->revoked_by_membership_id);
        self::assertNotNull($legacyRevokedInvite->revoked_at);
        self::assertNotNull($legacyRevokedInvite->code_discard_after);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertAuthenticationSession(array $overrides): string
    {
        $id = (string) Str::ulid();
        $now = now();
        DB::table('authentication_sessions')->insert([
            'id' => $id,
            'user_id' => $overrides['user_id'],
            'selected_organization_id' => null,
            'selected_membership_id' => null,
            'storage_session_digest' => hash('sha256', $id),
            'storage_session_ciphertext' => Crypt::encryptString(
                'schema-session-'.$id,
            ),
            'is_privileged' => false,
            'started_at' => $now,
            'last_seen_at' => $now,
            'idle_expires_at' => $now->copy()->addHour(),
            'absolute_expires_at' => $now->copy()->addDay(),
            'mfa_verified_at' => null,
            'mfa_factor_id' => null,
            'mfa_verified_method' => null,
            'mfa_verified_scope' => null,
            'revoked_at' => null,
            'revocation_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
            ...$overrides,
        ]);

        return $id;
    }

    private function expectPostgresViolation(string $sqlState): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionCode($sqlState);
    }
}

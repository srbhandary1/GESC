<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Models\AuthenticationSession;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Operator\OrganizationAdministration\OrganizationAdministrationMaintenance;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Symfony\Component\HttpFoundation\Response;

final class OrganizationAdministrationApiTest extends TenancyIntegrationTestCase
{
    private const string OTP = '975310';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            OtpCodeGenerator::class,
            new OrganizationAdministrationOtpCodeGenerator(self::OTP),
        );
        $this->app->instance(
            OtpDeliveryAdapter::class,
            new OrganizationAdministrationNullOtpDeliveryAdapter,
        );
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_branch_api_is_strict_tenant_scoped_replay_safe_and_uses_safe_problem_keys(): void
    {
        $actor = $this->signInWeb('+919100000001');
        $organization = $this->createOrganization('Administration Primary');
        $foreignOrganization = $this->createOrganization('Administration Foreign');
        [$actorMembership, $actorContext] = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $actorMembership,
            $actorContext,
            ['branches.manage', 'branches.read'],
            'branch-operator',
        );
        $foreignBranchId = $this->insertBranch(
            $foreignOrganization,
            'foreign-branch',
            'Foreign Branch',
        );
        $this->selectMembership($actorMembership);
        $body = [
            'key' => 'central-branch',
            'name' => 'Central Branch',
            'expected_version' => 0,
        ];

        $this->postCommand(
            '/api/v1/operator/branches',
            $body,
            (string) Str::ulid(),
        )
            ->assertForbidden()
            ->assertJsonPath('message_key', 'problem.fresh_mfa_required');
        $this->assertDatabaseCount('organization_administration_commands', 0);

        $this->freshenCurrentWebSession($actor);
        $idempotencyKey = (string) Str::ulid();
        $created = $this->postCommand(
            '/api/v1/operator/branches',
            $body,
            $idempotencyKey,
        )
            ->assertCreated()
            ->assertJsonPath('data.key', 'central-branch')
            ->assertJsonPath('data.name', 'Central Branch')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('replayed', false)
            ->assertJsonMissingPath('data.archived_at');
        $branchId = $created->json('data.id');
        self::assertIsString($branchId);

        $this->postCommand(
            '/api/v1/operator/branches',
            $body,
            $idempotencyKey,
        )
            ->assertOk()
            ->assertJsonPath('data.id', $branchId)
            ->assertJsonPath('replayed', true);
        self::assertSame(
            1,
            DB::table('branches')
                ->where('organization_id', $organization->getKey())
                ->where('key', 'central-branch')
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('organization_administration_events')
                ->where('organization_id', $organization->getKey())
                ->where('event_type', 'branch.created')
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('audit_events')
                ->where('organization_id', $organization->getKey())
                ->where('action', 'organization_administration.branch.created')
                ->count(),
        );

        $this->postCommand(
            '/api/v1/operator/branches',
            [...$body, 'name' => 'Changed Replay'],
            $idempotencyKey,
        )
            ->assertConflict()
            ->assertJsonPath(
                'message_key',
                'problem.organization_administration.idempotency_conflict',
            )
            ->assertJsonMissing(['Changed Replay']);

        $this->postCommand(
            '/api/v1/operator/branches',
            [...$body, 'organization_id' => $foreignOrganization->getKey()],
            (string) Str::ulid(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('message_key', 'problem.validation_failed');
        $this->getJson('/api/v1/operator/branches?status=ended')
            ->assertUnprocessable();

        $this->postCommand(
            sprintf('/api/v1/operator/branches/%s/updates', $branchId),
            ['name' => 'Stale Name', 'expected_version' => 99],
            (string) Str::ulid(),
        )
            ->assertConflict()
            ->assertJsonPath(
                'message_key',
                'problem.organization_administration.stale_state',
            );

        $cohort = $this->createCohort($actorContext, 'Attached Cohort');
        DB::table('cohorts')->where('id', $cohort->getKey())->update([
            'branch_id' => $branchId,
        ]);
        $this->postCommand(
            sprintf('/api/v1/operator/branches/%s/archivals', $branchId),
            ['expected_version' => 1],
            (string) Str::ulid(),
        )
            ->assertConflict()
            ->assertJsonPath(
                'message_key',
                'problem.organization_administration.active_dependency',
            );

        $this->postCommand(
            sprintf('/api/v1/operator/branches/%s/updates', $foreignBranchId),
            ['name' => 'Cross Tenant Rename', 'expected_version' => 1],
            (string) Str::ulid(),
        )
            ->assertNotFound()
            ->assertJsonPath('message_key', 'problem.not_found');
        $listedIds = array_column(
            $this->getJson('/api/v1/operator/branches')
                ->assertOk()
                ->json('data'),
            'id',
        );
        self::assertContains($branchId, $listedIds);
        self::assertNotContains($foreignBranchId, $listedIds);
    }

    public function test_role_commands_enforce_catalogue_ceiling_system_self_and_last_manager_boundaries(): void
    {
        $actor = $this->signInWeb('+919100000002');
        $organization = $this->createOrganization('Role Boundaries');
        [$actorMembership, $actorContext] = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $actorRole = $this->grantTestPermissions(
            $actorMembership,
            $actorContext,
            ['memberships.manage', 'roles.manage'],
            'role-manager',
        );
        $injectedPermissionId = (string) Str::ulid();
        DB::table('permissions')->insert([
            'id' => $injectedPermissionId,
            'key' => 'injected.root',
            'description' => 'Synthetic non-catalogue permission.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('role_permissions')->insert([
            'organization_id' => $organization->getKey(),
            'role_id' => $actorRole->getKey(),
            'permission_id' => $injectedPermissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $systemRoleId = $this->insertRole(
            $organization,
            'system-protected',
            'Protected System Role',
            true,
        );
        $targetUser = $this->createUser();
        [$targetMembership, $targetContext] = $this->createMembership(
            $organization,
            $targetUser,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $targetManagerRole = $this->grantTestPermissions(
            $targetMembership,
            $targetContext,
            ['memberships.manage', 'roles.manage'],
            'target-manager',
        );

        $this->selectMembership($actorMembership);
        $this->freshenCurrentWebSession($actor);
        $roles = $this->getJson('/api/v1/operator/roles')
            ->assertOk()
            ->assertJsonMissing(['injected.root' => 'injected.root']);
        $delegableKeys = array_column(
            $roles->json('delegable_permissions'),
            'key',
        );
        self::assertContains('roles.manage', $delegableKeys);
        self::assertNotContains('injected.root', $delegableKeys);
        $this->getJson('/api/v1/operator/roles?status=pending')
            ->assertUnprocessable();

        $this->postCommand(
            '/api/v1/operator/roles',
            [
                'key' => 'forbidden-escalation',
                'name' => 'Forbidden Escalation',
                'permission_keys' => ['injected.root'],
                'expected_version' => 0,
            ],
            (string) Str::ulid(),
        )
            ->assertNotFound()
            ->assertJsonPath('message_key', 'problem.not_found');
        $this->assertDatabaseMissing('roles', [
            'organization_id' => $organization->getKey(),
            'key' => 'forbidden-escalation',
        ]);

        $this->postCommand(
            '/api/v1/operator/roles',
            [
                'key' => 'delegated-role',
                'name' => 'Delegated Role',
                'permission_keys' => ['roles.manage'],
                'expected_version' => 0,
            ],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('data.permission_keys.0', 'roles.manage');

        $this->postCommand(
            sprintf('/api/v1/operator/roles/%s/updates', $systemRoleId),
            [
                'name' => 'Attempted System Rename',
                'permission_keys' => ['roles.manage'],
                'expected_version' => 1,
            ],
            (string) Str::ulid(),
        )
            ->assertNotFound()
            ->assertJsonPath('message_key', 'problem.not_found');
        self::assertSame(
            'Protected System Role',
            DB::table('roles')->where('id', $systemRoleId)->value('name'),
        );

        $this->postCommand(
            sprintf(
                '/api/v1/operator/memberships/%s/deactivations',
                $actorMembership->getKey(),
            ),
            ['expected_version' => 1],
            (string) Str::ulid(),
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message_key',
                'problem.organization_administration.self_target_forbidden',
            );

        $membershipsManagePermissionId = Permission::query()
            ->where('key', 'memberships.manage')
            ->value('id');
        self::assertIsString($membershipsManagePermissionId);
        DB::table('role_permissions')
            ->where('organization_id', $organization->getKey())
            ->where('role_id', $actorRole->getKey())
            ->where('permission_id', $membershipsManagePermissionId)
            ->delete();

        $this->postCommand(
            sprintf(
                '/api/v1/operator/memberships/%s/role-revocations',
                $targetMembership->getKey(),
            ),
            [
                'role_id' => $targetManagerRole->getKey(),
                'expected_membership_version' => 1,
                'expected_role_version' => 1,
            ],
            (string) Str::ulid(),
        )
            ->assertConflict()
            ->assertJsonPath(
                'message_key',
                'problem.organization_administration.last_manager_required',
            );
        $this->assertDatabaseHas('membership_roles', [
            'organization_id' => $organization->getKey(),
            'membership_id' => $targetMembership->getKey(),
            'role_id' => $targetManagerRole->getKey(),
        ]);
        self::assertSame(
            1,
            (int) DB::table('memberships')
                ->where('id', $targetMembership->getKey())
                ->value('version'),
        );
    }

    public function test_invitation_secret_is_one_time_and_maintenance_drains_late_and_batched_history(): void
    {
        CarbonImmutable::setTestNow('2026-08-09T12:00:00+00:00');
        $actor = $this->signInWeb('+919100000003');
        $organization = $this->createOrganization('Invitation Retention');
        [$actorMembership, $actorContext] = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $actorMembership,
            $actorContext,
            ['institution_invites.manage'],
            'invite-manager',
        );
        $this->selectMembership($actorMembership);
        $this->freshenCurrentWebSession($actor);
        $body = [
            'membership_type' => Membership::TYPE_TRAINER,
            'expires_in_hours' => 24,
            'expected_version' => 0,
        ];
        $idempotencyKey = (string) Str::ulid();

        $issued = $this->postCommand(
            '/api/v1/operator/institution-invites',
            $body,
            $idempotencyKey,
        )
            ->assertCreated()
            ->assertJsonPath('replayed', false)
            ->assertJsonPath('data.status', 'pending');
        $invitationId = $issued->json('data.id');
        $activationCode = $issued->json('activation_code');
        self::assertIsString($invitationId);
        self::assertIsString($activationCode);
        self::assertGreaterThanOrEqual(22, strlen($activationCode));
        self::assertIsString($issued->json('data.updated_at'));

        $this->postCommand(
            '/api/v1/operator/institution-invites',
            $body,
            $idempotencyKey,
        )
            ->assertOk()
            ->assertJsonPath('data.id', $invitationId)
            ->assertJsonPath('activation_code', null)
            ->assertJsonPath('replayed', true);
        self::assertSame(
            1,
            DB::table('institution_invites')
                ->where('organization_id', $organization->getKey())
                ->where('id', $invitationId)
                ->count(),
        );

        $lateCreatedAt = now()->subHours(26);
        DB::table('institution_invites')->where('id', $invitationId)->update([
            'created_at' => $lateCreatedAt,
            'updated_at' => $lateCreatedAt,
            'expires_at' => $lateCreatedAt->copy()->addHours(24),
        ]);
        $terminalIds = [];

        for ($index = 0; $index < 3; $index++) {
            $terminalIds[] = $this->insertTerminalInvitation(
                $organization,
                now()->subDays(10),
                now()->subHours(2),
            );
        }

        $branchId = $this->insertBranch(
            $organization,
            'retention-anchor',
            'Retention Anchor',
        );
        $this->insertExpiredAdministrationHistory(
            $organization,
            $actorMembership,
            $branchId,
            3,
        );
        $oldCleanupRequestIds = [];

        for ($index = 0; $index < 3; $index++) {
            $session = $this->createTestPrivilegedAuthenticationSession(
                $actor,
                now()->subDays(366),
                [
                    'started_at' => now()->subDays(400),
                    'last_seen_at' => now()->subDays(367),
                    'idle_expires_at' => now()->subDays(366),
                    'absolute_expires_at' => now()->subDays(365),
                    'revoked_at' => now()->subDays(366),
                    'revocation_reason' => 'operator_membership_revoked',
                ],
            );
            $oldCleanupRequestIds[] = $this->insertCompletedCleanupRequest(
                $session,
                now()->subDays(366),
            );
        }
        $exhaustedSession = $this->createTestPrivilegedAuthenticationSession(
            $actor,
            now()->subDay(),
            [
                'started_at' => now()->subDays(2),
                'last_seen_at' => now()->subDay(),
                'idle_expires_at' => now()->subHours(23),
                'absolute_expires_at' => now()->subHours(22),
                'revoked_at' => now()->subDay(),
                'revocation_reason' => 'operator_membership_revoked',
            ],
        );
        $exhaustedCleanupRequestId = $this->insertExhaustedCleanupRequest(
            $exhaustedSession,
        );

        $result = $this->app
            ->make(OrganizationAdministrationMaintenance::class)
            ->run(2);

        self::assertSame(1, $result['invitations_expired']);
        self::assertSame(4, $result['invitation_digests_cleared']);
        self::assertSame(3, $result['administration_events_purged']);
        self::assertSame(3, $result['administration_commands_purged']);
        self::assertSame(3, $result['session_cleanup_requests_purged']);
        self::assertSame(1, $result['session_cleanup_exhausted_backlog']);
        self::assertSame(0, $result['overdue_terminal_digest_backlog']);
        self::assertDatabaseHas('institution_invites', [
            'id' => $invitationId,
            'status' => 'expired',
            'version' => 2,
            'code_digest' => null,
        ]);
        $discardAfter = DB::table('institution_invites')
            ->where('id', $invitationId)
            ->value('code_discard_after');
        self::assertIsString($discardAfter);
        self::assertTrue(
            CarbonImmutable::parse($discardAfter)->equalTo(
                $lateCreatedAt->copy()->addHours(24),
            ),
        );

        foreach ($terminalIds as $terminalId) {
            $this->assertDatabaseHas('institution_invites', [
                'id' => $terminalId,
                'code_digest' => null,
            ]);
        }

        foreach ($oldCleanupRequestIds as $requestId) {
            $this->assertDatabaseMissing(
                'authentication_session_cleanup_requests',
                ['id' => $requestId],
            );
        }
        $this->assertDatabaseHas('authentication_session_cleanup_requests', [
            'id' => $exhaustedCleanupRequestId,
            'status' => 'pending',
            'attempts' => 10,
        ]);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $command = $this->artisan(
                'operator:maintain-organization-administration',
            );
            self::assertInstanceOf(PendingCommand::class, $command);
            $command->assertExitCode(1);
        }

        $purgeableInvitationIds = [];

        for ($index = 0; $index < 3; $index++) {
            $purgeableInvitationIds[] = $this->insertTerminalInvitation(
                $organization,
                now()->subDays(367),
                now()->subDays(366),
                null,
            );
        }

        $second = $this->app
            ->make(OrganizationAdministrationMaintenance::class)
            ->run(2);
        self::assertSame(3, $second['terminal_invitations_purged']);

        foreach ($purgeableInvitationIds as $purgeableInvitationId) {
            $this->assertDatabaseMissing('institution_invites', [
                'id' => $purgeableInvitationId,
            ]);
        }
    }

    public function test_session_projection_is_active_only_while_revocation_covers_every_exact_selected_credential(): void
    {
        CarbonImmutable::setTestNow('2026-08-09T13:00:00+00:00');
        $actor = $this->signInWeb('+919100000004');
        $organization = $this->createOrganization('Credential Scope');
        $foreignOrganization = $this->createOrganization('Credential Foreign');
        [$actorMembership, $actorContext] = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $actorMembership,
            $actorContext,
            ['membership_sessions.revoke', 'memberships.read'],
            'credential-operator',
        );
        $target = $this->createUser();
        [$targetMembership] = $this->createMembership(
            $organization,
            $target,
            Membership::TYPE_TRAINER,
        );
        [$foreignMembership] = $this->createMembership(
            $foreignOrganization,
            $target,
            Membership::TYPE_TRAINER,
        );
        $activeWeb = $this->createSelectedWebSession(
            $target,
            $organization,
            $targetMembership,
            now()->addHour(),
        );
        $expiredWeb = $this->createSelectedWebSession(
            $target,
            $organization,
            $targetMembership,
            now()->subMinute(),
        );
        $foreignWeb = $this->createSelectedWebSession(
            $target,
            $foreignOrganization,
            $foreignMembership,
            now()->addHour(),
        );
        $client = $this->createPassportClient();
        $activeMobileId = $this->insertMobileFamily(
            $client,
            $target,
            $organization,
            $targetMembership,
            now()->addHour(),
            'active-mobile',
        );
        $expiredMobileId = $this->insertMobileFamily(
            $client,
            $target,
            $organization,
            $targetMembership,
            now()->subMinute(),
            'expired-mobile',
        );
        $inactiveMobileId = $this->insertMobileFamily(
            $client,
            $target,
            $organization,
            $targetMembership,
            now()->addHour(),
            'inactive-mobile',
        );
        DB::table('mobile_token_families')
            ->where('id', $inactiveMobileId)
            ->update(['last_used_at' => now()->subDays(31)]);
        $foreignMobileId = $this->insertMobileFamily(
            $client,
            $target,
            $foreignOrganization,
            $foreignMembership,
            now()->addHour(),
            'foreign-mobile',
        );

        $this->selectMembership($actorMembership);
        $this->freshenCurrentWebSession($actor);
        $projection = $this->getJson(sprintf(
            '/api/v1/operator/membership-sessions/%s',
            $targetMembership->getKey(),
        ))
            ->assertOk()
            ->assertJsonPath('data.active_web_session_count', 1)
            ->assertJsonPath('data.active_android_family_count', 1)
            ->assertJsonPath('data.allowed_actions.0', 'membership_session.revoke');
        $fingerprint = $projection->json('data.state_fingerprint');
        self::assertIsString($fingerprint);
        $idempotencyKey = (string) Str::ulid();

        $this->postCommand(
            sprintf(
                '/api/v1/operator/membership-sessions/%s/revocations',
                $targetMembership->getKey(),
            ),
            ['state_fingerprint' => $fingerprint],
            $idempotencyKey,
        )
            ->assertOk()
            ->assertJsonPath('data.active_web_session_count', 0)
            ->assertJsonPath('data.active_android_family_count', 0)
            ->assertJsonPath('data.revoked_web_session_count', 2)
            ->assertJsonPath('data.revoked_android_family_count', 3)
            ->assertJsonPath('data.allowed_actions', [])
            ->assertJsonPath('replayed', false);

        foreach ([$activeWeb, $expiredWeb] as $session) {
            $this->assertDatabaseHas('authentication_sessions', [
                'id' => $session->getKey(),
                'revocation_reason' => 'operator_membership_revoked',
            ]);
        }

        foreach ([$activeMobileId, $expiredMobileId, $inactiveMobileId] as $familyId) {
            $this->assertDatabaseHas('mobile_token_families', [
                'id' => $familyId,
                'revocation_reason' => 'operator_membership_revoked',
            ]);
        }

        $this->assertDatabaseHas('authentication_sessions', [
            'id' => $foreignWeb->getKey(),
            'revoked_at' => null,
        ]);
        $this->assertDatabaseHas('mobile_token_families', [
            'id' => $foreignMobileId,
            'revoked_at' => null,
        ]);
        self::assertSame(
            2,
            DB::table('authentication_session_cleanup_requests')
                ->whereIn('authentication_session_id', [
                    $activeWeb->getKey(),
                    $expiredWeb->getKey(),
                ])
                ->count(),
        );

        $this->postCommand(
            sprintf(
                '/api/v1/operator/membership-sessions/%s/revocations',
                $targetMembership->getKey(),
            ),
            ['state_fingerprint' => $fingerprint],
            $idempotencyKey,
        )
            ->assertOk()
            ->assertJsonPath('data.revoked_web_session_count', 2)
            ->assertJsonPath('data.revoked_android_family_count', 3)
            ->assertJsonPath('replayed', true);
    }

    public function test_deactivation_cascades_current_authority_but_preserves_other_tenant_and_elapsed_appointment_state(): void
    {
        CarbonImmutable::setTestNow('2026-08-09T14:00:00+00:00');
        $actor = $this->signInWeb('+919100000005');
        $organization = $this->createOrganization('Membership Lifecycle');
        $foreignOrganization = $this->createOrganization('Membership Lifecycle Foreign');
        [$actorMembership, $actorContext] = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $actorMembership,
            $actorContext,
            ['memberships.manage', 'roles.manage'],
            'lifecycle-manager',
        );
        $target = $this->createUser();
        [$targetMembership, $targetContext] = $this->createMembership(
            $organization,
            $target,
            Membership::TYPE_TRAINER,
        );
        [$foreignMembership] = $this->createMembership(
            $foreignOrganization,
            $target,
            Membership::TYPE_TRAINER,
        );
        $targetRole = $this->grantTestPermissions(
            $targetMembership,
            $targetContext,
            ['trainer_profiles.read'],
            'target-authority',
        );
        $cohort = $this->createCohort($actorContext, 'Lifecycle Cohort');
        DB::table('cohort_trainers')->insert([
            'organization_id' => $organization->getKey(),
            'cohort_id' => $cohort->getKey(),
            'trainer_membership_id' => $targetMembership->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $currentAppointmentId = $this->insertAppointment(
            $organization,
            $actorMembership,
            $target,
            $targetMembership,
            now()->subHour(),
            now()->addDay(),
            'Current qualification',
            'evidence.current',
        );
        $elapsedAppointmentId = $this->insertAppointment(
            $organization,
            $actorMembership,
            $target,
            $targetMembership,
            now()->subDays(2),
            now()->subDay(),
            'Elapsed qualification',
            'evidence.elapsed',
        );
        $activeWeb = $this->createSelectedWebSession(
            $target,
            $organization,
            $targetMembership,
            now()->addHour(),
        );
        $expiredWeb = $this->createSelectedWebSession(
            $target,
            $organization,
            $targetMembership,
            now()->subMinute(),
        );
        $foreignWeb = $this->createSelectedWebSession(
            $target,
            $foreignOrganization,
            $foreignMembership,
            now()->addHour(),
        );

        $this->selectMembership($actorMembership);
        $this->freshenCurrentWebSession($actor);
        $this->postCommand(
            sprintf(
                '/api/v1/operator/memberships/%s/deactivations',
                $targetMembership->getKey(),
            ),
            ['expected_version' => 1],
            (string) Str::ulid(),
        )
            ->assertOk()
            ->assertJsonPath('data.status', Membership::STATUS_SUSPENDED)
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.roles', []);

        $this->assertDatabaseMissing('membership_roles', [
            'organization_id' => $organization->getKey(),
            'membership_id' => $targetMembership->getKey(),
            'role_id' => $targetRole->getKey(),
        ]);
        $this->assertDatabaseMissing('cohort_trainers', [
            'organization_id' => $organization->getKey(),
            'trainer_membership_id' => $targetMembership->getKey(),
            'cohort_id' => $cohort->getKey(),
        ]);
        $this->assertDatabaseHas('authority_appointment_events', [
            'organization_id' => $organization->getKey(),
            'authority_appointment_id' => $currentAppointmentId,
            'event_type' => 'revoked',
            'reason_key' => 'membership_suspended',
            'invalidates_prior_approvals' => false,
        ]);
        $this->assertDatabaseMissing('authority_appointment_events', [
            'organization_id' => $organization->getKey(),
            'authority_appointment_id' => $elapsedAppointmentId,
            'event_type' => 'revoked',
            'reason_key' => 'membership_suspended',
        ]);

        foreach ([$activeWeb, $expiredWeb] as $session) {
            $this->assertDatabaseHas('authentication_sessions', [
                'id' => $session->getKey(),
                'revocation_reason' => 'membership_suspended',
            ]);
        }

        $this->assertDatabaseHas('authentication_sessions', [
            'id' => $foreignWeb->getKey(),
            'revoked_at' => null,
        ]);
        $this->assertDatabaseHas('memberships', [
            'id' => $foreignMembership->getKey(),
            'organization_id' => $foreignOrganization->getKey(),
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $this->postCommand(
            sprintf(
                '/api/v1/operator/memberships/%s/reactivations',
                $targetMembership->getKey(),
            ),
            ['expected_version' => 2],
            (string) Str::ulid(),
        )
            ->assertOk()
            ->assertJsonPath('data.status', Membership::STATUS_ACTIVE)
            ->assertJsonPath('data.version', 3)
            ->assertJsonPath('data.roles', []);
        $this->assertDatabaseMissing('cohort_trainers', [
            'organization_id' => $organization->getKey(),
            'trainer_membership_id' => $targetMembership->getKey(),
        ]);
    }

    public function test_trainer_manage_only_result_and_private_appointment_scope_are_atomic_and_expiry_safe(): void
    {
        CarbonImmutable::setTestNow('2026-08-09T15:00:00+00:00');
        $appointee = $this->signInWeb('+919100000006');
        $manager = $this->signInWeb('+919100000007');
        $organization = $this->createOrganization('Trainer Authority');
        $foreignOrganization = $this->createOrganization('Trainer Authority Foreign');
        [$managerMembership, $managerContext] = $this->createMembership(
            $organization,
            $manager,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        [$appointeeMembership] = $this->createMembership(
            $organization,
            $appointee,
            Membership::TYPE_TRAINER,
        );
        $this->grantTestPermissions(
            $managerMembership,
            $managerContext,
            ['trainer_profiles.manage'],
            'trainer-manager-only',
        );
        $cohort = $this->createCohort($managerContext, 'Trainer Cohort');

        $this->selectMembership($managerMembership);
        $this->freshenCurrentWebSession($manager);
        $this->postCommand(
            sprintf(
                '/api/v1/operator/trainer-profiles/%s/cohort-assignments',
                $appointeeMembership->getKey(),
            ),
            [
                'cohort_id' => $cohort->getKey(),
                'expected_membership_version' => 1,
                'expected_cohort_version' => 1,
            ],
            (string) Str::ulid(),
        )
            ->assertOk()
            ->assertJsonPath('data.membership_id', $appointeeMembership->getKey())
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.cohorts.0.id', $cohort->getKey())
            ->assertJsonPath('data.cohorts.0.version', 1);

        $this->grantTestPermissions(
            $managerMembership,
            $managerContext,
            ['curriculum.authority.manage'],
            'appointment-manager',
        );
        $created = $this->postCommand(
            sprintf(
                '/api/v1/operator/trainer-profiles/%s/authority-appointments',
                $appointeeMembership->getKey(),
            ),
            [
                'authority_kind' => 'english_academic_reviewer',
                'scope_type' => 'organization',
                'program_id' => null,
                'valid_from' => now()->subMinute()->format('Y-m-d\TH:i:sP'),
                'valid_until' => now()->addDay()->format('Y-m-d\TH:i:sP'),
                'qualification_basis' => 'Private qualification narrative',
                'approval_evidence_key' => 'evidence.private',
                'expected_membership_version' => 2,
            ],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('data.state', 'pending')
            ->assertJsonPath(
                'data.qualification_basis',
                'Private qualification narrative',
            );
        $appointmentId = $created->json('data.id');
        $stateFingerprint = $created->json('data.state_fingerprint');
        self::assertIsString($appointmentId);
        self::assertIsString($stateFingerprint);

        $list = $this->getJson(sprintf(
            '/api/v1/operator/trainer-profiles/%s/authority-appointments',
            $appointeeMembership->getKey(),
        ))
            ->assertOk()
            ->assertJsonPath('data.0.id', $appointmentId)
            ->assertJsonMissingPath('data.0.qualification_basis')
            ->assertJsonMissingPath('data.0.approval_evidence_key');
        self::assertSame('pending', $list->json('data.0.state'));

        $this->getJson(sprintf(
            '/api/v1/operator/trainer-profiles/%s/authority-appointments/%s',
            $appointeeMembership->getKey(),
            $appointmentId,
        ))
            ->assertOk()
            ->assertJsonPath(
                'data.qualification_basis',
                'Private qualification narrative',
            )
            ->assertJsonPath('data.approval_evidence_key', 'evidence.private');

        $foreignUser = $this->createUser();
        [$foreignMembership] = $this->createMembership(
            $foreignOrganization,
            $foreignUser,
            Membership::TYPE_TRAINER,
        );
        $foreignManager = $this->createUser();
        [$foreignManagerMembership] = $this->createMembership(
            $foreignOrganization,
            $foreignManager,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $foreignAppointmentId = $this->insertAppointment(
            $foreignOrganization,
            $foreignManagerMembership,
            $foreignUser,
            $foreignMembership,
            now()->subHour(),
            now()->addDay(),
            'Foreign private narrative',
            'evidence.foreign',
        );
        $this->getJson(sprintf(
            '/api/v1/operator/trainer-profiles/%s/authority-appointments/%s',
            $foreignMembership->getKey(),
            $foreignAppointmentId,
        ))
            ->assertNotFound()
            ->assertJsonMissing(['Foreign private narrative']);

        $expiredAppointmentId = $this->insertAppointment(
            $organization,
            $managerMembership,
            $appointee,
            $appointeeMembership,
            now()->subDays(2),
            now()->subDay(),
            'Elapsed private narrative',
            'evidence.expired',
        );
        $expiredDetail = $this->getJson(sprintf(
            '/api/v1/operator/trainer-profiles/%s/authority-appointments/%s',
            $appointeeMembership->getKey(),
            $expiredAppointmentId,
        ))
            ->assertOk()
            ->assertJsonPath('data.state', 'expired')
            ->assertJsonPath('data.allowed_actions', []);
        $expiredFingerprint = $expiredDetail->json('data.state_fingerprint');
        self::assertIsString($expiredFingerprint);
        $this->postCommand(
            sprintf(
                '/api/v1/operator/trainer-profiles/%s/authority-appointments/%s/revocations',
                $appointeeMembership->getKey(),
                $expiredAppointmentId,
            ),
            [
                'state_fingerprint' => $expiredFingerprint,
                'reason_key' => 'operator_revoked',
            ],
            (string) Str::ulid(),
        )
            ->assertConflict()
            ->assertJsonPath(
                'message_key',
                'problem.organization_administration.stale_state',
            );
        $this->assertDatabaseMissing('authority_appointment_events', [
            'authority_appointment_id' => $expiredAppointmentId,
            'event_type' => 'revoked',
        ]);

        $this->postCommand(
            sprintf(
                '/api/v1/operator/trainer-profiles/%s/authority-appointments/%s/acceptances',
                $appointeeMembership->getKey(),
                $appointmentId,
            ),
            ['state_fingerprint' => $stateFingerprint],
            (string) Str::ulid(),
        )->assertNotFound();

        $appointee = $this->signInWeb('+919100000006');
        $this->selectMembership($appointeeMembership);
        $this->freshenCurrentWebSession($appointee);
        $this->getJson(sprintf(
            '/api/v1/operator/trainer-profiles/%s/authority-appointments/%s',
            $appointeeMembership->getKey(),
            $appointmentId,
        ))
            ->assertOk()
            ->assertJsonPath(
                'data.qualification_basis',
                'Private qualification narrative',
            );
        $this->postCommand(
            sprintf(
                '/api/v1/operator/trainer-profiles/%s/authority-appointments/%s/acceptances',
                $appointeeMembership->getKey(),
                $appointmentId,
            ),
            ['state_fingerprint' => $stateFingerprint],
            (string) Str::ulid(),
        )
            ->assertOk()
            ->assertJsonPath('data.state', 'active');

        $this->postCommand(
            sprintf(
                '/api/v1/operator/trainer-profiles/%s/authority-appointments/%s/surrenders',
                $appointeeMembership->getKey(),
                $expiredAppointmentId,
            ),
            ['state_fingerprint' => $expiredFingerprint],
            (string) Str::ulid(),
        )
            ->assertConflict()
            ->assertJsonPath(
                'message_key',
                'problem.organization_administration.stale_state',
            );
    }

    public function test_organization_administrator_appointee_can_read_and_accept_own_appointment(): void
    {
        CarbonImmutable::setTestNow('2026-08-10T10:00:00+00:00');
        $appointee = $this->signInWeb('+919100000016');
        $organization = $this->createOrganization('Administrator Appointee');
        [$appointeeMembership] = $this->createMembership(
            $organization,
            $appointee,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $appointmentId = $this->insertAppointment(
            $organization,
            $appointeeMembership,
            $appointee,
            $appointeeMembership,
            now()->subMinute(),
            now()->addDay(),
            'APP-0047',
            'APP-0004',
        );

        $this->selectMembership($appointeeMembership);
        $this->freshenCurrentWebSession($appointee);
        $detail = $this->getJson(sprintf(
            '/api/v1/operator/trainer-profiles/%s/authority-appointments/%s',
            $appointeeMembership->getKey(),
            $appointmentId,
        ))
            ->assertOk()
            ->assertJsonPath('data.state', 'pending')
            ->assertJsonPath('data.qualification_basis', 'APP-0047')
            ->assertJsonPath('data.allowed_actions.0', 'appointment.accept');
        $fingerprint = $detail->json('data.state_fingerprint');
        self::assertIsString($fingerprint);

        $this->postCommand(
            sprintf(
                '/api/v1/operator/trainer-profiles/%s/authority-appointments/%s/acceptances',
                $appointeeMembership->getKey(),
                $appointmentId,
            ),
            ['state_fingerprint' => $fingerprint],
            (string) Str::ulid(),
        )
            ->assertOk()
            ->assertJsonPath('data.state', 'active');
        $this->assertDatabaseHas('authority_appointment_events', [
            'organization_id' => $organization->getKey(),
            'authority_appointment_id' => $appointmentId,
            'event_type' => 'accepted',
            'actor_membership_id' => $appointeeMembership->getKey(),
        ]);
    }

    public function test_organization_settings_only_manifest_stays_on_overview_without_empty_people_navigation(): void
    {
        $actor = $this->signInWeb('+919100000008');
        $organization = $this->createOrganization('Settings Only');
        [$membership, $context] = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $membership,
            $context,
            ['organization.settings.read'],
            'settings-reader',
        );
        $this->selectMembership($membership);

        $this->getJson('/api/v1/operator/overview')
            ->assertOk()
            ->assertJsonPath('navigation', ['overview'])
            ->assertJsonPath('panels', ['organization_settings'])
            ->assertJsonPath('search_available', false);
    }

    private function signInWeb(string $mobileNumber): User
    {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        Auth::forgetGuards();

        $issued = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => $mobileNumber,
            ])
            ->assertAccepted();
        $challengeId = $issued->json('challenge_id');
        self::assertIsString($challengeId);

        $verified = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => $mobileNumber,
                'code' => self::OTP,
            ])
            ->assertOk();
        $cookie = $verified->getCookie((string) config('session.cookie'));
        self::assertNotNull($cookie);
        $this->withCookie($cookie->getName(), (string) $cookie->getValue());
        $userId = $verified->json('user.id');
        self::assertIsString($userId);

        return User::query()->whereKey($userId)->sole();
    }

    private function selectMembership(Membership $membership): void
    {
        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => $membership->getKey(),
            ])
            ->assertOk();
    }

    private function freshenCurrentWebSession(User $user): void
    {
        $session = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->firstOrFail();
        $this->markTestAuthenticationSessionPrivileged(
            (string) $session->getKey(),
            $user,
            now(),
        );
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    private function postCommand(
        string $uri,
        array $body,
        string $idempotencyKey,
    ): TestResponse {
        return $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->withHeader('Origin', 'https://localhost')
            ->postJson($uri, $body);
    }

    private function insertBranch(
        Organization $organization,
        string $key,
        string $name,
    ): string {
        $id = (string) Str::ulid();
        DB::table('branches')->insert([
            'id' => $id,
            'organization_id' => $organization->getKey(),
            'key' => $key,
            'name' => $name,
            'status' => 'active',
            'version' => 1,
            'archived_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function insertRole(
        Organization $organization,
        string $key,
        string $name,
        bool $system,
    ): string {
        $id = (string) Str::ulid();
        DB::table('roles')->insert([
            'id' => $id,
            'organization_id' => $organization->getKey(),
            'key' => $key,
            'name' => $name,
            'is_system' => $system,
            'status' => 'active',
            'version' => 1,
            'retired_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function insertTerminalInvitation(
        Organization $organization,
        CarbonInterface $transitionAt,
        CarbonInterface $discardAt,
        ?string $digest = 'generate',
    ): string {
        $id = (string) Str::ulid();
        DB::table('institution_invites')->insert([
            'id' => $id,
            'organization_id' => $organization->getKey(),
            'code_digest' => $digest === 'generate'
                ? hash('sha256', 'terminal-invite-'.$id)
                : $digest,
            'membership_type' => Membership::TYPE_TRAINER,
            'role_id' => null,
            'cohort_id' => null,
            'status' => 'expired',
            'expires_at' => $transitionAt,
            'accepted_membership_id' => null,
            'accepted_by_user_id' => null,
            'accepted_idempotency_key' => null,
            'accepted_request_fingerprint' => null,
            'accepted_at' => null,
            'issued_by_membership_id' => null,
            'revoked_by_membership_id' => null,
            'revoked_at' => null,
            'code_discard_after' => $discardAt,
            'version' => 1,
            'created_at' => $transitionAt->copy()->subDay(),
            'updated_at' => $transitionAt,
        ]);

        return $id;
    }

    private function insertExpiredAdministrationHistory(
        Organization $organization,
        Membership $actorMembership,
        string $branchId,
        int $count,
    ): void {
        $completedAt = now()->subDays(366);

        for ($index = 0; $index < $count; $index++) {
            $commandId = (string) Str::ulid();
            DB::table('organization_administration_commands')->insert([
                'id' => $commandId,
                'organization_id' => $organization->getKey(),
                'actor_membership_id' => $actorMembership->getKey(),
                'idempotency_key' => (string) Str::uuid(),
                'operation' => 'branch.update',
                'target_type' => 'branch',
                'target_id' => $branchId,
                'request_fingerprint' => hash(
                    'sha256',
                    sprintf('old-command-%d', $index),
                ),
                'result_type' => 'branch',
                'result_id' => $branchId,
                'completed_at' => $completedAt,
                'expires_at' => $completedAt->copy()->addDays(365),
                'created_at' => $completedAt,
            ]);
            DB::table('organization_administration_events')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $organization->getKey(),
                'actor_membership_id' => $actorMembership->getKey(),
                'event_type' => 'branch.updated',
                'subject_membership_id' => null,
                'subject_role_id' => null,
                'subject_cohort_id' => null,
                'subject_branch_id' => $branchId,
                'subject_invitation_id' => null,
                'permission_id' => null,
                'command_id' => $commandId,
                'reason_key' => null,
                'effective_at' => $completedAt,
                'created_at' => $completedAt,
            ]);
        }
    }

    private function insertCompletedCleanupRequest(
        AuthenticationSession $session,
        CarbonInterface $completedAt,
    ): string {
        $id = (string) Str::ulid();
        DB::table('authentication_session_cleanup_requests')->insert([
            'id' => $id,
            'authentication_session_id' => $session->getKey(),
            'status' => 'completed',
            'attempts' => 1,
            'available_at' => $completedAt,
            'leased_until' => null,
            'completed_at' => $completedAt,
            'created_at' => $completedAt,
            'updated_at' => $completedAt,
        ]);

        return $id;
    }

    private function insertExhaustedCleanupRequest(
        AuthenticationSession $session,
    ): string {
        $id = (string) Str::ulid();
        DB::table('authentication_session_cleanup_requests')->insert([
            'id' => $id,
            'authentication_session_id' => $session->getKey(),
            'status' => 'pending',
            'attempts' => 10,
            'available_at' => now()->subHour(),
            'leased_until' => null,
            'completed_at' => null,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subHour(),
        ]);

        return $id;
    }

    private function createSelectedWebSession(
        User $user,
        Organization $organization,
        Membership $membership,
        CarbonInterface $idleExpiresAt,
    ): AuthenticationSession {
        return $this->createTestPrivilegedAuthenticationSession(
            $user,
            now(),
            [
                'selected_organization_id' => $organization->getKey(),
                'selected_membership_id' => $membership->getKey(),
                'started_at' => now()->subHours(2),
                'last_seen_at' => now()->subMinutes(2),
                'idle_expires_at' => $idleExpiresAt,
                'absolute_expires_at' => now()->addHours(2),
            ],
        );
    }

    private function createPassportClient(): Client
    {
        return Client::query()->create([
            'name' => 'Organization administration test client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => ['com.globalenglish.app:/oauth/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ]);
    }

    private function insertMobileFamily(
        Client $client,
        User $user,
        Organization $organization,
        Membership $membership,
        CarbonInterface $absoluteExpiresAt,
        string $label,
    ): string {
        $id = (string) Str::ulid();
        DB::table('mobile_token_families')->insert([
            'id' => $id,
            'user_id' => $user->getKey(),
            'client_id' => $client->getKey(),
            'selected_organization_id' => $organization->getKey(),
            'selected_membership_id' => $membership->getKey(),
            'installation_digest' => hash('sha256', $label.'-'.$id),
            'last_used_at' => now()->subMinute(),
            'absolute_expires_at' => $absoluteExpiresAt,
            'revoked_at' => null,
            'revocation_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function insertAppointment(
        Organization $organization,
        Membership $managerMembership,
        User $appointee,
        Membership $appointeeMembership,
        CarbonInterface $validFrom,
        CarbonInterface $validUntil,
        string $qualificationBasis,
        string $evidenceKey,
    ): string {
        $id = (string) Str::ulid();
        $eventId = (string) Str::ulid();
        DB::table('authority_appointments')->insert([
            'id' => $id,
            'organization_id' => $organization->getKey(),
            'appointee_user_id' => $appointee->getKey(),
            'appointee_membership_id' => $appointeeMembership->getKey(),
            'authority_kind' => 'english_academic_reviewer',
            'scope_type' => 'organization',
            'program_id' => null,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'qualification_basis' => $qualificationBasis,
            'approval_evidence_key' => $evidenceKey,
            'appointed_by_membership_id' => $managerMembership->getKey(),
            'appointed_at' => $validFrom,
            'created_at' => $validFrom,
            'updated_at' => $validFrom,
        ]);
        DB::table('authority_appointment_events')->insert([
            'id' => $eventId,
            'organization_id' => $organization->getKey(),
            'authority_appointment_id' => $id,
            'event_type' => 'appointed',
            'actor_membership_id' => $managerMembership->getKey(),
            'reason_key' => null,
            'invalidates_prior_approvals' => false,
            'effective_at' => $validFrom,
            'created_at' => $validFrom,
            'updated_at' => $validFrom,
        ]);

        return $id;
    }
}

final class OrganizationAdministrationOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private readonly string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class OrganizationAdministrationNullOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    public function send(
        string $normalizedMobileNumber,
        string $code,
    ): void {
        // Test-only null delivery.
    }
}

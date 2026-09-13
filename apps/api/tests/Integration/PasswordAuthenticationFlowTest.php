<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Password\PasswordCredentialService;
use App\Authentication\Password\PasswordPolicy;
use App\Authentication\Password\PasswordPolicyViolation;
use App\Authentication\Password\PasswordRecoveryDeliveryAdapter;
use App\Identity\IdentityIdentifierDigester;
use App\Models\AuthenticationSession;
use App\Models\InstitutionInvite;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\OtpChallenge;
use App\Models\PasswordRecoveryChallenge;
use App\Models\PrivilegedMfaFactor;
use App\Models\Role;
use App\Models\User;
use App\Models\UserIdentity;
use App\Tenancy\InstitutionInviteCodeDigester;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextSource;
use Carbon\CarbonImmutable;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

final class PasswordAuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    private RecordingPasswordRecoveryDelivery $delivery;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('authentication.mode', 'credential_migration');
        $this->seed(FoundationSeeder::class);
        $this->delivery = new RecordingPasswordRecoveryDelivery;
        $this->app->instance(PasswordRecoveryDeliveryAdapter::class, $this->delivery);
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_policy_enforces_username_unicode_length_nfc_and_blocklist(): void
    {
        $policy = $this->app->make(PasswordPolicy::class);

        self::assertSame('learner.one', $policy->normalizeUsername(' Learner.One '));
        self::assertSame(
            str_repeat('ಅ', 15),
            $policy->normalizeAndValidatePassword(str_repeat('ಅ', 15), 'learner.one'),
        );
        self::assertSame(
            'é'.str_repeat('a', 14),
            $policy->normalizeAndValidatePassword("e\u{0301}".str_repeat('a', 14), 'learner.one'),
        );

        foreach ([
            ['abc', str_repeat('a', 15)],
            ['learner.one', str_repeat('a', 14)],
            ['learner.one', str_repeat('a', 129)],
            ['learner.one', '123456789012345'],
        ] as [$username, $password]) {
            try {
                $username === 'abc'
                    ? $policy->normalizeUsername($username)
                    : $policy->normalizeAndValidatePassword($password, $username);
                self::fail('The invalid credential candidate was accepted.');
            } catch (PasswordPolicyViolation) {
                // Expected: every candidate in the table violates the policy.
            }
        }
    }

    public function test_password_sign_in_is_generic_and_confirmation_is_session_version_bound(): void
    {
        $user = $this->credentialReadyUser('learner.one', 'Correct horse battery 2026!');

        $signedIn = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/password/sign-ins', [
                'username' => 'LEARNER.ONE',
                'password' => 'Correct horse battery 2026!',
            ])
            ->assertOk()
            ->assertJsonPath('user.id', (string) $user->getKey())
            ->assertJsonPath('session.password_confirmed_at', null);
        $sessionId = $signedIn->json('session.id');
        self::assertIsString($sessionId);
        $cookie = $signedIn->getCookie((string) config('session.cookie'));
        self::assertNotNull($cookie);
        $this->withCookie($cookie->getName(), (string) $cookie->getValue());

        AuthenticationSession::query()->whereKey($sessionId)->update([
            'is_privileged' => true,
        ]);
        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/password/confirmations', [
                'password' => 'Correct horse battery 2026!',
            ])
            ->assertOk();
        $session = AuthenticationSession::query()->findOrFail($sessionId);
        self::assertNotNull($session->password_confirmed_at);
        self::assertSame(1, $session->password_confirmed_credential_version);
        self::assertTrue(
            $this->app->make(PasswordCredentialService::class)
                ->hasFreshConfirmation($session),
        );

        User::query()->whereKey($user->getKey())->update(['credential_version' => 2]);
        $staleSession = $session->fresh();
        self::assertInstanceOf(AuthenticationSession::class, $staleSession);
        self::assertFalse(
            $this->app->make(PasswordCredentialService::class)
                ->hasFreshConfirmation($staleSession),
        );

        foreach (['missing.user', 'learner.one'] as $username) {
            $this->withHeader('Origin', 'https://localhost')
                ->postJson('/api/v1/auth/password/sign-ins', [
                    'username' => $username,
                    'password' => 'Definitely wrong password!',
                ])
                ->assertUnprocessable()
                ->assertJsonPath('detail', 'One or more request values are invalid.');
        }
    }

    public function test_recovery_is_role_neutral_generic_single_use_and_globally_advances_the_credential(): void
    {
        $user = $this->credentialReadyUser('learner.two', 'Correct horse battery 2026!');
        $tenantAuthorityBefore = $this->attachAuthorityAcrossTwoOrganizations($user);
        UserIdentity::query()->forceCreate([
            'user_id' => $user->getKey(),
            'type' => 'email',
            'identifier_digest' => hash('sha256', 'learner@example.test'),
            'display_hint' => 'l***@example.test',
            'status' => UserIdentity::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);
        $generic = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/password/recovery-requests', [
                'username' => 'learner.two',
            ])
            ->assertAccepted()
            ->json();
        self::assertSame($generic, $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/password/recovery-requests', [
                'username' => 'unknown.user',
            ])
            ->assertAccepted()
            ->json());
        self::assertCount(1, $this->delivery->deliveries);
        $delivered = $this->delivery->deliveries[0];

        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/password/recoveries', [
                'challenge_id' => $delivered['challenge_id'],
                'username' => 'learner.two',
                'token' => $delivered['token'],
                'new_password' => 'New learner recovery phrase 2026!',
            ])
            ->assertNoContent();
        self::assertSame(2, $user->fresh()?->credential_version);
        self::assertSame($tenantAuthorityBefore, $this->tenantAuthoritySnapshot($user));
        self::assertSame(
            'consumed',
            PasswordRecoveryChallenge::query()
                ->findOrFail($delivered['challenge_id'])->status,
        );

        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/password/recoveries', [
                'challenge_id' => $delivered['challenge_id'],
                'username' => 'learner.two',
                'token' => $delivered['token'],
                'new_password' => 'Another learner recovery phrase!',
            ])
            ->assertUnprocessable();
    }

    public function test_password_only_mode_retires_legacy_otp_contracts(): void
    {
        config()->set('authentication.mode', 'password_only');

        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => '+919876543210',
            ])
            ->assertGone();
    }

    public function test_invitation_enrollment_creates_one_password_only_account_atomically(): void
    {
        config()->set('authentication.mode', 'password_only');
        $rawCode = 'GEI-CLOSED-GROUP-2026';
        $invite = $this->closedGroupLearnerInvite($rawCode);

        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/password/invitation-enrollments', [
                'invitation_code' => $rawCode,
                'username' => 'closed.tester',
                'password' => 'Closed group learner phrase 2026!',
            ])
            ->assertNoContent();

        $user = User::query()->where('username', 'closed.tester')->sole();
        self::assertNotNull($user->credential_cutover_at);
        self::assertTrue($user->passwordCredential()->exists());
        $membership = DB::table('memberships')
            ->where('user_id', $user->getKey())
            ->sole();
        self::assertSame(Membership::TYPE_LEARNER, $membership->membership_type);
        self::assertSame(
            (string) $invite->organization_id,
            (string) $membership->organization_id,
        );
        self::assertSame(1, DB::table('membership_roles')
            ->where('membership_id', $membership->id)
            ->count());
        self::assertSame(
            InstitutionInvite::STATUS_ACCEPTED,
            $invite->fresh()?->status,
        );

        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/password/sign-ins', [
                'username' => 'closed.tester',
                'password' => 'Closed group learner phrase 2026!',
            ])
            ->assertOk();
    }

    public function test_invitation_enrollment_failure_is_generic_and_rolls_back_every_account_write(): void
    {
        config()->set('authentication.mode', 'password_only');
        $usersBefore = User::query()->count();

        $response = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/password/invitation-enrollments', [
                'invitation_code' => 'GEI-NOT-A-REAL-CODE',
                'username' => 'closed.invalid',
                'password' => 'Closed group learner phrase 2026!',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('detail', 'One or more request values are invalid.');

        self::assertSame($usersBefore, User::query()->count());
        self::assertSame(0, DB::table('password_credentials')
            ->where('user_id', User::query()->where('username', 'closed.invalid')->value('id'))
            ->count());
        self::assertStringNotContainsString(
            'GEI-NOT-A-REAL-CODE',
            (string) $response->getContent(),
        );
        self::assertStringNotContainsString(
            'Closed group learner phrase 2026!',
            (string) $response->getContent(),
        );
    }

    public function test_cutover_command_is_complete_idempotent_and_clears_legacy_secrets(): void
    {
        $user = $this->credentialReadyUser('cutover.user', 'Correct horse battery 2026!');
        $excluded = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        $signedIn = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/password/sign-ins', [
                'username' => 'cutover.user',
                'password' => 'Correct horse battery 2026!',
            ])->assertOk();
        $sessionId = $signedIn->json('session.id');
        self::assertIsString($sessionId);
        /** @var PrivilegedMfaFactor $factor */
        $factor = PrivilegedMfaFactor::query()->forceCreate([
            'user_id' => $user->getKey(),
            'factor_type' => PrivilegedMfaFactor::TYPE_TOTP,
            'secret_ciphertext' => encrypt(str_repeat('A', 32)),
            'status' => PrivilegedMfaFactor::STATUS_ACTIVE,
            'confirmed_at' => now(),
        ]);
        /** @var OtpChallenge $otp */
        $otp = OtpChallenge::query()->forceCreate([
            'destination_digest' => hash('sha256', 'destination'),
            'code_digest' => hash('sha256', 'code'),
            'failed_attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);
        $manifestPath = '/tmp/auth-password-cutover-'.strtolower((string) Str::ulid()).'.json';
        config()->set('platform.account_recovery.manager_user_ids', [$excluded->getKey()]);
        file_put_contents($manifestPath, json_encode([
            'exclusions' => [[
                'user_id' => $excluded->getKey(),
                'reason_key' => 'cutover_excluded.synthetic_fixture',
                'owner_user_id' => $excluded->getKey(),
            ]],
        ], JSON_THROW_ON_ERROR));

        try {
            $this->assertCutoverCommandFails($manifestPath, true);

            config()->set('platform.account_recovery.manager_user_ids', [$user->getKey()]);
            file_put_contents($manifestPath, json_encode([
                'exclusions' => [[
                    'user_id' => $excluded->getKey(),
                    'reason_key' => 'cutover_excluded.synthetic_fixture',
                    'owner_user_id' => $user->getKey(),
                ]],
            ], JSON_THROW_ON_ERROR));
            $this->assertCutoverCommandSucceeds($manifestPath, true);
            self::assertSame('credential_migration', $this->app->make(PasswordCredentialService::class)->mode());

            $this->assertCutoverCommandSucceeds($manifestPath);
            self::assertSame('password_only', $this->app->make(PasswordCredentialService::class)->mode());
            self::assertNotNull($user->fresh()?->credential_cutover_at);
            self::assertNull($excluded->fresh()?->credential_cutover_at);
            self::assertSame('password_cutover', AuthenticationSession::query()->findOrFail($sessionId)->revocation_reason);
            $revokedFactor = PrivilegedMfaFactor::query()
                ->whereKey($factor->getKey())
                ->firstOrFail();
            self::assertSame(PrivilegedMfaFactor::STATUS_REVOKED, $revokedFactor->status);
            self::assertNull($revokedFactor->secret_ciphertext);
            self::assertNotNull($otp->fresh()?->superseded_at);

            $this->assertCutoverCommandSucceeds($manifestPath);
            $this->assertDatabaseCount('password_authentication_cutovers', 1);
            $this->assertDatabaseHas('password_authentication_cutovers', [
                'active_user_count' => 2,
                'credential_ready_count' => 1,
                'excluded_user_count' => 1,
            ]);
        } finally {
            if (is_file($manifestPath)) {
                unlink($manifestPath);
            }
        }
    }

    public function test_one_manager_issues_immediate_setup_without_mutating_tenant_authority(): void
    {
        $managerPassword = 'Correct manager battery 2026!';
        $manager = $this->credentialReadyUser('recovery.manager', $managerPassword);
        /** @var User $target */
        $target = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        UserIdentity::query()->forceCreate([
            'user_id' => $target->getKey(),
            'type' => 'email',
            'identifier_digest' => hash('sha256', 'excluded@example.test'),
            'display_hint' => 'e***@example.test',
            'status' => UserIdentity::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);
        DB::table('password_authentication_cutovers')->insert([
            'scope_key' => 'global',
            'status' => 'password_only',
            'manifest_digest' => str_repeat('a', 64),
            'evidence_digest' => str_repeat('b', 64),
            'active_user_count' => 2,
            'credential_ready_count' => 1,
            'excluded_user_count' => 1,
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('password_cutover_exclusions')->insert([
            'user_id' => (string) $target->getKey(),
            'cutover_scope_key' => 'global',
            'reason_key' => 'cutover_excluded.synthetic_fixture',
            'owner_user_id' => (string) $manager->getKey(),
            'manifest_digest' => str_repeat('a', 64),
            'reconciled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tenantAuthorityBefore = $this->attachAuthorityAcrossTwoOrganizations($target);
        config()->set('platform.account_recovery.manager_user_ids', [
            (string) $manager->getKey(),
        ]);
        $this->signInAndConfirm($manager, $managerPassword);

        $created = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/platform/account-recovery/setups', [
                'target_user_id' => (string) $target->getKey(),
                'reason_key' => 'account_recovery.migration_setup',
                'evidence_reference' => 'support-case:AUTH-2026-SETUP',
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'delivered');
        $eventId = $created->json('id');
        self::assertIsString($eventId);
        self::assertCount(1, $this->delivery->deliveries);
        $delivered = $this->delivery->deliveries[0];
        self::assertSame(43, strlen($delivered['token']));
        $challenge = PasswordRecoveryChallenge::query()
            ->findOrFail($delivered['challenge_id']);
        self::assertSame(PasswordRecoveryChallenge::PURPOSE_ACCOUNT_SETUP, $challenge->purpose);
        self::assertSame($eventId, $challenge->account_recovery_management_event_id);
        self::assertNull($challenge->privileged_recovery_case_id);

        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/password/setups', [
                'challenge_id' => $delivered['challenge_id'],
                'username' => 'recovered.operator',
                'token' => $delivered['token'],
                'password' => 'Target chooses this battery 2026!',
            ])->assertNoContent();
        $recovered = $target->fresh();
        self::assertInstanceOf(User::class, $recovered);
        self::assertSame('recovered.operator', $recovered->username);
        self::assertTrue($recovered->passwordCredential()->exists());
        self::assertSame($tenantAuthorityBefore, $this->tenantAuthoritySnapshot($target));
        $this->assertDatabaseHas('audit_events', [
            'capability_key' => 'platform.account_recovery.manage',
            'subject_id' => $eventId,
            'action' => 'authentication.account_recovery.setup_requested',
        ]);
        $this->assertDatabaseCount('privileged_password_recovery_cases', 0);

        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/platform/identity-recovery-cases', [])
            ->assertNotFound();
    }

    public function test_manager_can_replace_lost_contact_with_content_minimized_evidence(): void
    {
        $managerPassword = 'Correct manager battery 2026!';
        $manager = $this->credentialReadyUser('contact.manager', $managerPassword);
        $target = $this->credentialReadyUser('contact.target', 'Correct target battery 2026!');
        $identifiers = $this->app->make(IdentityIdentifierDigester::class);
        /** @var UserIdentity $former */
        $former = UserIdentity::query()->forceCreate([
            'user_id' => $target->getKey(),
            'type' => 'email',
            'identifier_digest' => $identifiers->digest('email', 'former@example.test'),
            'display_hint' => 'f***@example.test',
            'status' => UserIdentity::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);
        config()->set('platform.account_recovery.manager_user_ids', [
            (string) $manager->getKey(),
        ]);
        $this->signInAndConfirm($manager, $managerPassword);

        config()->set('platform.account_recovery.manager_user_ids', []);
        config()->set('platform.identity_recovery.actor_user_ids', [
            (string) $manager->getKey(),
        ]);
        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/platform/account-recovery/users/'.$target->getKey().'/contact', [
                'contact_type' => 'email',
                'contact' => 'replacement@example.test',
                'reason_key' => 'account_recovery.verified_contact_replacement',
                'evidence_reference' => 'support-case:AUTH-2026-CONTACT',
            ])->assertUnprocessable();

        config()->set('platform.account_recovery.manager_user_ids', [
            (string) $manager->getKey(),
        ]);
        $response = $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/platform/account-recovery/users/'.$target->getKey().'/contact', [
                'contact_type' => 'email',
                'contact' => 'replacement@example.test',
                'reason_key' => 'account_recovery.verified_contact_replacement',
                'evidence_reference' => 'support-case:AUTH-2026-CONTACT',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('notification_outcome', 'unavailable');
        $eventId = $response->json('id');
        self::assertIsString($eventId);
        self::assertSame(UserIdentity::STATUS_DISABLED, $former->fresh()?->status);
        $this->assertDatabaseHas('user_identities', [
            'user_id' => (string) $target->getKey(),
            'type' => 'email',
            'identifier_digest' => $identifiers->digest('email', 'replacement@example.test'),
            'status' => UserIdentity::STATUS_VERIFIED,
        ]);
        $this->assertDatabaseHas('account_recovery_notification_events', [
            'management_event_id' => $eventId,
            'former_identity_id' => (string) $former->getKey(),
            'outcome' => 'unavailable',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'capability_key' => 'platform.account_recovery.manage',
            'subject_id' => $eventId,
            'action' => 'authentication.account_recovery.contact_replaced',
        ]);
        $serializedEvidence = json_encode([
            DB::table('account_recovery_management_events')->where('id', $eventId)->first(),
            DB::table('audit_events')->where('subject_id', $eventId)->get()->all(),
            DB::table('outbox_events')->where('aggregate_id', $eventId)->get()->all(),
        ], JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('replacement@example.test', $serializedEvidence);
        self::assertStringNotContainsString('support-case:AUTH-2026-CONTACT', $serializedEvidence);
    }

    private function credentialReadyUser(string $username, string $password): User
    {
        /** @var User $user */
        $user = User::query()->forceCreate([
            'display_name' => null,
            'status' => User::STATUS_ACTIVE,
        ]);
        $request = Request::create('/internal/password-establishment', 'POST');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $this->app->make(PasswordCredentialService::class)
            ->establish($user, $username, $password, $request);

        return User::query()->whereKey($user->getKey())->firstOrFail();
    }

    private function signInAndConfirm(User $user, string $password): void
    {
        $signedIn = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/password/sign-ins', [
                'username' => (string) $user->username,
                'password' => $password,
            ])->assertOk();
        $cookie = $signedIn->getCookie((string) config('session.cookie'));
        self::assertNotNull($cookie);
        $this->withCookie($cookie->getName(), (string) $cookie->getValue());
        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/password/confirmations', [
                'password' => $password,
            ])->assertOk();
    }

    private function closedGroupLearnerInvite(string $rawCode): InstitutionInvite
    {
        $organization = Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->sole();
        /** @var User $issuer */
        $issuer = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        $context = new OrganizationContext(
            organizationId: (string) $organization->getKey(),
            actorUserId: (string) $issuer->getKey(),
            membershipId: null,
            source: OrganizationContextSource::InstitutionInvite,
        );

        return $this->app->make(OrganizationContextManager::class)->run(
            $context,
            function () use ($rawCode): InstitutionInvite {
                $role = Role::query()
                    ->where('key', Role::DIRECT_LEARNER_KEY)
                    ->sole();

                return InstitutionInvite::query()->forceCreate([
                    'code_digest' => $this->app
                        ->make(InstitutionInviteCodeDigester::class)
                        ->digest($rawCode),
                    'membership_type' => Membership::TYPE_LEARNER,
                    'role_id' => $role->getKey(),
                    'status' => InstitutionInvite::STATUS_PENDING,
                    'expires_at' => now()->addDay(),
                ]);
            },
        );
    }

    private function attachAuthorityAcrossTwoOrganizations(User $target): string
    {
        $now = now();

        foreach ([
            ['direct', 'platform_direct', 'organization_admin'],
            ['institution', 'institution', 'learner'],
        ] as [$suffix, $kind, $membershipType]) {
            $organizationId = (string) Str::ulid();
            $membershipId = (string) Str::ulid();
            $roleId = (string) Str::ulid();
            DB::table('organizations')->insert([
                'id' => $organizationId,
                'system_key' => null,
                'name' => 'Recovery '.$suffix,
                'kind' => $kind,
                'status' => 'active',
                'timezone' => 'Asia/Kolkata',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('memberships')->insert([
                'id' => $membershipId,
                'organization_id' => $organizationId,
                'user_id' => $target->getKey(),
                'membership_type' => $membershipType,
                'status' => 'active',
                'joined_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('roles')->insert([
                'id' => $roleId,
                'organization_id' => $organizationId,
                'key' => 'recovery-'.$suffix,
                'name' => 'Recovery '.$suffix,
                'is_system' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('membership_roles')->insert([
                'organization_id' => $organizationId,
                'membership_id' => $membershipId,
                'role_id' => $roleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $this->tenantAuthoritySnapshot($target);
    }

    private function tenantAuthoritySnapshot(User $target): string
    {
        $memberships = DB::table('memberships')
            ->where('user_id', $target->getKey())
            ->orderBy('organization_id')
            ->get(['id', 'organization_id', 'membership_type', 'status'])
            ->all();
        $membershipIds = array_map(
            static fn (object $membership): string => (string) $membership->id,
            $memberships,
        );

        return json_encode([
            'memberships' => $memberships,
            'roles' => DB::table('membership_roles')
                ->whereIn('membership_id', $membershipIds)
                ->orderBy('organization_id')
                ->get(['organization_id', 'membership_id', 'role_id'])
                ->all(),
        ], JSON_THROW_ON_ERROR);
    }

    private function assertCutoverCommandSucceeds(string $manifestPath, bool $dryRun = false): void
    {
        $parameters = ['--manifest' => $manifestPath];

        if ($dryRun) {
            $parameters['--dry-run'] = true;
        }

        $result = $this->artisan('auth:password-cutover', $parameters);

        if ($result instanceof PendingCommand) {
            $result->assertSuccessful();

            return;
        }

        self::assertSame(0, $result);
    }

    private function assertCutoverCommandFails(string $manifestPath, bool $dryRun = false): void
    {
        $parameters = ['--manifest' => $manifestPath];

        if ($dryRun) {
            $parameters['--dry-run'] = true;
        }

        $result = $this->artisan('auth:password-cutover', $parameters);

        if ($result instanceof PendingCommand) {
            $result->assertExitCode(1);

            return;
        }

        self::assertSame(1, $result);
    }
}

final class RecordingPasswordRecoveryDelivery implements PasswordRecoveryDeliveryAdapter
{
    /** @var list<array{identity_id: string, challenge_id: string, token: string}> */
    public array $deliveries = [];

    public function deliver(
        UserIdentity $identity,
        string $challengeId,
        string $token,
    ): void {
        $this->deliveries[] = [
            'identity_id' => (string) $identity->getKey(),
            'challenge_id' => $challengeId,
            'token' => $token,
        ];
    }
}

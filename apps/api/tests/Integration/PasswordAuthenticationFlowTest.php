<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Password\PasswordCredentialService;
use App\Authentication\Password\PasswordPolicy;
use App\Authentication\Password\PasswordPolicyViolation;
use App\Authentication\Password\PasswordRecoveryDeliveryAdapter;
use App\Authentication\Password\PasswordRecoveryFailed;
use App\Authentication\Password\PrivilegedPasswordRecoveryService;
use App\Models\AuthenticationSession;
use App\Models\OtpChallenge;
use App\Models\PasswordRecoveryChallenge;
use App\Models\PrivilegedMfaFactor;
use App\Models\User;
use App\Models\UserIdentity;
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

    public function test_recovery_is_generic_single_use_and_globally_advances_the_credential(): void
    {
        $user = $this->credentialReadyUser('learner.two', 'Correct horse battery 2026!');
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
        config()->set('platform.identity_recovery.actor_user_ids', [$excluded->getKey()]);
        file_put_contents($manifestPath, json_encode([
            'exclusions' => [[
                'user_id' => $excluded->getKey(),
                'reason_key' => 'cutover_excluded.synthetic_fixture',
                'owner_user_id' => $excluded->getKey(),
            ]],
        ], JSON_THROW_ON_ERROR));

        try {
            $this->assertCutoverCommandFails($manifestPath, true);

            config()->set('platform.identity_recovery.actor_user_ids', [$user->getKey()]);
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

    public function test_privileged_recovery_requires_two_distinct_operators_and_24_hours(): void
    {
        CarbonImmutable::setTestNow('2026-08-11T10:00:00+00:00');
        $target = $this->credentialReadyUser('privileged.target', 'Correct horse battery 2026!');
        UserIdentity::query()->forceCreate([
            'user_id' => $target->getKey(),
            'type' => 'email',
            'identifier_digest' => hash('sha256', 'privileged@example.test'),
            'display_hint' => 'p***@example.test',
            'status' => UserIdentity::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);
        /** @var User $firstOperator */
        $firstOperator = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        /** @var User $secondOperator */
        $secondOperator = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        config()->set('platform.identity_recovery.actor_user_ids', [
            (string) $firstOperator->getKey(),
            (string) $secondOperator->getKey(),
        ]);
        $request = Request::create('/internal/platform-recovery', 'POST');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->setLaravelSession($this->app->make('session')->driver());
        $service = $this->app->make(PrivilegedPasswordRecoveryService::class);
        $evidence = 'support-case:AUTH-2026-0001';
        $case = $service->open(
            $firstOperator,
            (string) $target->getKey(),
            'identity_recovery.verified_support_case',
            $evidence,
            $request,
        );
        self::assertSame('pending_approval', $case['status']);
        $caseId = $case['id'];
        self::assertIsString($caseId);

        $service->approve($firstOperator, $caseId, $evidence, $request);
        try {
            $service->approve($firstOperator, $caseId, $evidence, $request);
            self::fail('The same operator approved twice.');
        } catch (PasswordRecoveryFailed) {
            // Expected: one operator cannot provide both approvals.
        }
        $scheduled = $service->approve($secondOperator, $caseId, $evidence, $request);
        self::assertSame('waiting_delay', $scheduled['status']);
        self::assertSame(2, $scheduled['approval_count']);

        try {
            $service->complete($firstOperator, $caseId, $evidence, $request);
            self::fail('The recovery completed before the delay.');
        } catch (PasswordRecoveryFailed) {
            // Expected: the mandatory recovery delay has not elapsed.
        }

        CarbonImmutable::setTestNow(now()->addHours(24));
        $completed = $service->complete($firstOperator, $caseId, $evidence, $request);
        self::assertSame('completed', $completed['status']);
        self::assertCount(1, $this->delivery->deliveries);
        $this->assertDatabaseHas('audit_events', [
            'scope_kind' => 'platform',
            'capability_key' => 'platform.identity_recovery.approve',
            'subject_id' => $caseId,
            'action' => 'authentication.password_recovery.completed',
        ]);
        $this->assertDatabaseHas('outbox_events', [
            'scope_kind' => 'platform',
            'aggregate_id' => $caseId,
            'event_type' => 'authentication.password_recovery.completed',
        ]);

        CarbonImmutable::setTestNow();
    }

    public function test_excluded_user_recovery_reconciles_exact_manifest_and_establishes_target_chosen_credentials(): void
    {
        CarbonImmutable::setTestNow('2026-08-11T10:00:00+00:00');
        $firstOperator = $this->credentialReadyUser('recovery.one', 'Correct horse battery 2026!');
        $secondOperator = $this->credentialReadyUser('recovery.two', 'Another correct battery 2026!');
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
        $tenantAuthorityBefore = $this->attachAuthorityAcrossTwoOrganizations($target);
        config()->set('platform.identity_recovery.actor_user_ids', [
            (string) $firstOperator->getKey(),
            (string) $secondOperator->getKey(),
        ]);
        $manifestPath = '/tmp/auth-password-excluded-'.strtolower((string) Str::ulid()).'.json';
        $manifest = json_encode([
            'exclusions' => [[
                'user_id' => (string) $target->getKey(),
                'reason_key' => 'cutover_excluded.synthetic_fixture',
                'owner_user_id' => (string) $firstOperator->getKey(),
            ]],
        ], JSON_THROW_ON_ERROR);
        file_put_contents($manifestPath, $manifest);
        chmod($manifestPath, 0600);

        try {
            $this->assertCutoverCommandSucceeds($manifestPath);
            config()->set('authentication.password.cutover_exclusion_reconciliation_enabled', true);
            config()->set('authentication.password.cutover_manifest_sha256', hash('sha256', $manifest));
            config()->set('platform.identity_recovery.actor_user_ids', [
                (string) $firstOperator->getKey(),
                (string) $firstOperator->getKey(),
            ]);
            $this->assertExclusionReconciliation($manifestPath, true, false);
            config()->set('platform.identity_recovery.actor_user_ids', [
                (string) $firstOperator->getKey(),
                (string) $secondOperator->getKey(),
            ]);
            $this->assertExclusionReconciliation($manifestPath, true, true);
            $this->assertExclusionReconciliation($manifestPath, false, true);
            $this->assertDatabaseHas('password_cutover_exclusions', [
                'user_id' => (string) $target->getKey(),
                'owner_user_id' => (string) $firstOperator->getKey(),
            ]);

            $request = Request::create('/internal/platform-recovery', 'POST');
            $request->server->set('REMOTE_ADDR', '127.0.0.1');
            $request->setLaravelSession($this->app->make('session')->driver());
            $service = $this->app->make(PrivilegedPasswordRecoveryService::class);
            $evidence = 'support-case:AUTH-2026-EXCLUDED';

            try {
                $service->open(
                    $secondOperator,
                    (string) $target->getKey(),
                    'identity_recovery.verified_support_case',
                    $evidence,
                    $request,
                );
                self::fail('A non-owner recovery actor opened the excluded-user case.');
            } catch (PasswordRecoveryFailed) {
                // Expected: the manifest owner must open the excluded-user case.
            }

            $case = $service->open(
                $firstOperator,
                (string) $target->getKey(),
                'identity_recovery.verified_support_case',
                $evidence,
                $request,
            );
            self::assertSame(
                PasswordRecoveryChallenge::PURPOSE_EXCLUDED_CREDENTIAL_ESTABLISHMENT,
                $case['recovery_kind'],
            );
            $caseId = $case['id'];
            self::assertIsString($caseId);
            $service->approve($firstOperator, $caseId, $evidence, $request);
            $service->approve($secondOperator, $caseId, $evidence, $request);

            try {
                $service->complete($firstOperator, $caseId, $evidence, $request);
                self::fail('Excluded-user establishment completed before the 24-hour delay.');
            } catch (PasswordRecoveryFailed) {
                // Expected: no local time bypass exists.
            }

            CarbonImmutable::setTestNow(now()->addHours(24));
            $completed = $service->complete($firstOperator, $caseId, $evidence, $request);
            self::assertSame('completed', $completed['status']);
            self::assertCount(1, $this->delivery->deliveries);
            $delivered = $this->delivery->deliveries[0];
            self::assertSame(43, strlen($delivered['token']));
            $challenge = PasswordRecoveryChallenge::query()
                ->findOrFail($delivered['challenge_id']);
            self::assertSame(
                PasswordRecoveryChallenge::PURPOSE_EXCLUDED_CREDENTIAL_ESTABLISHMENT,
                $challenge->purpose,
            );
            self::assertSame($caseId, $challenge->privileged_recovery_case_id);
            self::assertNotSame($delivered['token'], $challenge->token_digest);

            $wrongTokenResponse = $this->withHeader('Origin', 'https://localhost')
                ->postJson('/api/v1/auth/password/establishment-recoveries', [
                    'challenge_id' => $delivered['challenge_id'],
                    'username' => 'recovered.operator',
                    'token' => str_repeat('x', 43),
                    'password' => 'Target chooses this battery 2026!',
                ])->assertUnprocessable();
            self::assertSame(1, $challenge->fresh()?->failed_attempts);
            self::assertStringNotContainsString(
                str_repeat('x', 43),
                (string) $wrongTokenResponse->getContent(),
            );

            $this->credentialReadyUser('reserved.name', 'Reserved correct battery 2026!');
            $this->withHeader('Origin', 'https://localhost')
                ->postJson('/api/v1/auth/password/establishment-recoveries', [
                    'challenge_id' => $delivered['challenge_id'],
                    'username' => 'reserved.name',
                    'token' => $delivered['token'],
                    'password' => 'Target chooses this battery 2026!',
                ])->assertUnprocessable();
            self::assertSame(
                PasswordRecoveryChallenge::STATUS_PENDING,
                $challenge->fresh()->status,
            );

            $this->withHeader('Origin', 'https://localhost')
                ->postJson('/api/v1/auth/password/establishment-recoveries', [
                    'challenge_id' => $delivered['challenge_id'],
                    'username' => 'recovered.operator',
                    'token' => $delivered['token'],
                    'password' => 'Target chooses this battery 2026!',
                ])->assertNoContent();
            $recovered = $target->fresh();
            self::assertInstanceOf(User::class, $recovered);
            self::assertSame('recovered.operator', $recovered->username);
            self::assertNotNull($recovered->credential_cutover_at);
            self::assertTrue($recovered->passwordCredential()->exists());
            self::assertSame(
                PasswordRecoveryChallenge::STATUS_CONSUMED,
                $challenge->fresh()->status,
            );
            self::assertSame(
                $tenantAuthorityBefore,
                $this->tenantAuthoritySnapshot($target),
                'Global credential establishment must not mutate either tenant membership or role.',
            );

            $contentFreeEvidence = json_encode([
                'audit' => DB::table('audit_events')
                    ->where('subject_id', $caseId)->get()->all(),
                'outbox' => DB::table('outbox_events')
                    ->where('aggregate_id', $caseId)->get()->all(),
                'security' => DB::table('password_security_events')
                    ->where('user_id', $target->getKey())->get()->all(),
            ], JSON_THROW_ON_ERROR);

            foreach ([
                $delivered['token'],
                'Target chooses this battery 2026!',
                'recovered.operator',
                'excluded@example.test',
            ] as $secret) {
                self::assertStringNotContainsString($secret, $contentFreeEvidence);
            }

            $this->withHeader('Origin', 'https://localhost')
                ->postJson('/api/v1/auth/password/establishment-recoveries', [
                    'challenge_id' => $delivered['challenge_id'],
                    'username' => 'recovered.operator',
                    'token' => $delivered['token'],
                    'password' => 'Target chooses this battery 2026!',
                ])->assertUnprocessable();
        } finally {
            if (is_file($manifestPath)) {
                unlink($manifestPath);
            }
        }
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

    private function assertExclusionReconciliation(
        string $manifestPath,
        bool $dryRun,
        bool $successful,
    ): void {
        $parameters = ['--manifest' => $manifestPath];

        if ($dryRun) {
            $parameters['--dry-run'] = true;
        }

        $result = $this->artisan(
            'auth:reconcile-password-cutover-exclusions',
            $parameters,
        );

        if ($result instanceof PendingCommand) {
            $successful ? $result->assertSuccessful() : $result->assertFailed();

            return;
        }

        self::assertSame($successful ? 0 : 1, $result);
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

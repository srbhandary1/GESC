<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Mfa\Rfc6238PrivilegedMfaVerifier;
use App\Authentication\Mfa\TotpProvisioner;
use App\Authentication\Mobile\MobileTokenFamilyService;
use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Models\MobileTokenFamily;
use App\Models\PrivilegedMfaFactor;
use App\Models\PrivilegedMfaRecoveryCode;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Laravel\Passport\Client;
use Tests\TestCase;

final class PrivilegedMfaLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const string OTP = '246813';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FoundationSeeder::class);
        $this->app->instance(
            OtpCodeGenerator::class,
            new PrivilegedMfaFixedOtpCodeGenerator(self::OTP),
        );
        $this->app->instance(
            OtpDeliveryAdapter::class,
            new PrivilegedMfaNullOtpDeliveryAdapter,
        );
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
        CarbonImmutable::setTestNow('2026-08-08T10:00:00+00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_totp_replay_recovery_replacement_and_global_revocation(): void
    {
        $logs = [];
        Log::listen(static function (MessageLogged $event) use (&$logs): void {
            $logs[] = [$event->message, $event->context];
        });
        [$user, $sessionId] = $this->signInAndGrantPrivilege(
            '+919876543251',
        );

        $this->getJson('/api/v1/auth/mfa')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('factor_status', 'none')
            ->assertJsonPath('can_enroll', true)
            ->assertJsonPath('requires_support', false);

        $this->postJson('/api/v1/operator/privacy/exports', [])
            ->assertForbidden();

        $enrollment = $this->postJson('/api/v1/auth/mfa/enrollments', [])
            ->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonStructure([
                'factor_id',
                'challenge_id',
                'provisioning_uri',
                'expires_at',
            ]);
        $factorId = $enrollment->json('factor_id');
        $enrollmentChallengeId = $enrollment->json('challenge_id');
        $provisioningUri = $enrollment->json('provisioning_uri');
        self::assertIsString($factorId);
        self::assertIsString($enrollmentChallengeId);
        self::assertIsString($provisioningUri);
        $secret = $this->secretFromProvisioningUri($provisioningUri);
        $initialCode = $this->totpCode($secret);

        $confirmed = $this->postJson(
            '/api/v1/auth/mfa/enrollments/'.$factorId.'/confirm',
            [
                'challenge_id' => $enrollmentChallengeId,
                'code' => $initialCode,
            ],
        )->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('sessions_revoked', false)
            ->assertJsonCount(10, 'recovery_codes');
        $recoveryCodes = $confirmed->json('recovery_codes');
        self::assertIsArray($recoveryCodes);
        self::assertCount(10, array_unique($recoveryCodes));

        $factor = PrivilegedMfaFactor::query()->findOrFail($factorId);
        self::assertNotSame($secret, $factor->secret_ciphertext);
        self::assertSame(20, strlen((string) TotpProvisioner::decodeBase32($secret)));
        $this->assertDatabaseCount('privileged_mfa_recovery_codes', 10);
        self::assertSame(
            10,
            PrivilegedMfaRecoveryCode::query()
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at')
                ->count(),
        );

        $replayChallenge = $this->issueAssertionChallenge();
        $this->postJson(
            '/api/v1/auth/mfa/challenges/'.$replayChallenge.'/verify',
            ['method' => 'totp', 'code' => $initialCode],
        )->assertUnprocessable()
            ->assertJsonMissing(['code' => $initialCode]);
        $this->assertDatabaseHas('privileged_mfa_challenges', [
            'id' => $replayChallenge,
            'failed_attempts' => 1,
            'consumed_at' => null,
        ]);

        CarbonImmutable::setTestNow(now()->addSeconds(30));
        $freshChallenge = $this->issueAssertionChallenge();
        $this->postJson(
            '/api/v1/auth/mfa/challenges/'.$freshChallenge.'/verify',
            ['method' => 'totp', 'code' => $this->totpCode($secret)],
        )->assertOk()
            ->assertJsonPath('scope', 'privileged_actions');

        $this->postJson('/api/v1/operator/privacy/exports', [])
            ->assertUnprocessable();

        $recoveryChallenge = $this->issueAssertionChallenge();
        $usedRecoveryCode = $recoveryCodes[0] ?? null;
        self::assertIsString($usedRecoveryCode);
        $this->postJson(
            '/api/v1/auth/mfa/challenges/'.$recoveryChallenge.'/verify',
            ['method' => 'recovery_code', 'code' => $usedRecoveryCode],
        )->assertOk()
            ->assertJsonPath('scope', 'factor_replacement');
        $this->postJson('/api/v1/operator/privacy/exports', [])
            ->assertForbidden();

        $replacement = $this->postJson('/api/v1/auth/mfa/enrollments', [])
            ->assertCreated();
        $replacementFactorId = $replacement->json('factor_id');
        $replacementChallengeId = $replacement->json('challenge_id');
        $replacementUri = $replacement->json('provisioning_uri');
        self::assertIsString($replacementFactorId);
        self::assertIsString($replacementChallengeId);
        self::assertIsString($replacementUri);
        $replacementSecret = $this->secretFromProvisioningUri($replacementUri);
        $mobileFamily = $this->createTrackedMobileFamily($user);

        CarbonImmutable::setTestNow(now()->addSeconds(30));
        $replacementConfirmed = $this->postJson(
            '/api/v1/auth/mfa/enrollments/'.$replacementFactorId.'/confirm',
            [
                'challenge_id' => $replacementChallengeId,
                'code' => $this->totpCode($replacementSecret),
            ],
        )->assertOk()
            ->assertJsonPath('sessions_revoked', true)
            ->assertJsonCount(10, 'recovery_codes');

        $this->assertDatabaseHas('privileged_mfa_factors', [
            'id' => $factorId,
            'secret_ciphertext' => null,
            'status' => 'replaced',
        ]);
        $this->assertDatabaseHas('privileged_mfa_factors', [
            'id' => $replacementFactorId,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('authentication_sessions', [
            'id' => $sessionId,
            'revocation_reason' => 'mfa_factor_replaced',
        ]);
        $this->assertDatabaseHas('mobile_token_families', [
            'id' => $mobileFamily->getKey(),
            'revocation_reason' => 'mfa_factor_replaced',
        ]);
        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $mobileFamily->getAttribute('test_access_token_id'),
            'revoked' => true,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'authentication.mfa.recovery.used',
            'reason_key' => 'recovery_code_used',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'authentication.mfa.factor.replaced',
            'reason_key' => 'factor_replaced',
        ]);

        $sensitiveRecord = json_encode([
            'factors' => DB::table('privileged_mfa_factors')->get()->all(),
            'recovery' => DB::table('privileged_mfa_recovery_codes')->get()->all(),
            'audit' => DB::table('audit_events')->get()->all(),
            'logs' => $logs,
        ], JSON_THROW_ON_ERROR);

        foreach ([
            $secret,
            $replacementSecret,
            $initialCode,
            $usedRecoveryCode,
            ...$replacementConfirmed->json('recovery_codes'),
        ] as $rawSecret) {
            self::assertIsString($rawSecret);
            self::assertStringNotContainsString($rawSecret, $sensitiveRecord);
        }
    }

    public function test_recovery_regeneration_and_revocation_require_totp_and_block_sms_reset(): void
    {
        [$user] = $this->signInAndGrantPrivilege('+919876543252');
        [$factorId, $secret, $initialRecoveryCodes] = $this->enrollCurrentUser();

        CarbonImmutable::setTestNow(now()->addSeconds(30));
        $this->verifyCurrentTotp($secret);
        $regenerated = $this->postJson(
            '/api/v1/auth/mfa/recovery-codes/regenerate',
            [],
        )->assertOk()
            ->assertJsonPath('sessions_revoked', true)
            ->assertJsonCount(10, 'recovery_codes');
        $regeneratedCodes = $regenerated->json('recovery_codes');
        self::assertIsArray($regeneratedCodes);
        self::assertSame([], array_intersect($initialRecoveryCodes, $regeneratedCodes));
        $this->assertDatabaseHas('privileged_mfa_recovery_codes', [
            'factor_id' => $factorId,
            'invalidated_at' => now(),
        ]);

        [, $newSessionId] = $this->signInExistingUser('+919876543252');
        CarbonImmutable::setTestNow(now()->addSeconds(30));
        $this->verifyCurrentTotp($secret);
        $this->deleteJson('/api/v1/auth/mfa/factor')
            ->assertNoContent();
        $this->assertDatabaseHas('privileged_mfa_factors', [
            'id' => $factorId,
            'secret_ciphertext' => null,
            'status' => 'revoked',
        ]);
        $this->assertDatabaseHas('authentication_sessions', [
            'id' => $newSessionId,
            'revocation_reason' => 'mfa_factor_revoked',
        ]);

        $this->signInExistingUser('+919876543252');
        $this->getJson('/api/v1/auth/mfa')
            ->assertOk()
            ->assertJsonPath('factor_status', 'support_required')
            ->assertJsonPath('can_enroll', false)
            ->assertJsonPath('requires_support', true);
        $this->postJson('/api/v1/auth/mfa/enrollments', [])
            ->assertConflict();
        self::assertSame(
            (string) $user->getKey(),
            (string) User::query()->sole()->getKey(),
        );
    }

    public function test_assertion_challenges_enforce_failure_cap_and_expiry(): void
    {
        $this->signInAndGrantPrivilege('+919876543253');
        [, $secret] = $this->enrollCurrentUser();
        CarbonImmutable::setTestNow(now()->addSeconds(30));
        $challengeId = $this->issueAssertionChallenge();
        $validCode = $this->totpCode($secret);
        $invalidCode = ($validCode[0] === '0' ? '1' : '0').substr($validCode, 1);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson(
                '/api/v1/auth/mfa/challenges/'.$challengeId.'/verify',
                ['method' => 'totp', 'code' => $invalidCode],
            )->assertUnprocessable()
                ->assertJsonMissing(['code' => $invalidCode]);
        }

        $this->assertDatabaseHas('privileged_mfa_challenges', [
            'id' => $challengeId,
            'failed_attempts' => 5,
            'consumed_at' => now(),
        ]);
        $this->postJson(
            '/api/v1/auth/mfa/challenges/'.$challengeId.'/verify',
            ['method' => 'totp', 'code' => $invalidCode],
        )->assertStatus(429);

        $expiredChallengeId = $this->issueAssertionChallenge();
        CarbonImmutable::setTestNow(now()->addMinutes(5)->addSecond());
        $this->postJson(
            '/api/v1/auth/mfa/challenges/'.$expiredChallengeId.'/verify',
            ['method' => 'totp', 'code' => $this->totpCode($secret)],
        )->assertUnprocessable();
        $this->assertDatabaseHas('privileged_mfa_challenges', [
            'id' => $expiredChallengeId,
            'consumed_at' => now(),
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'authentication.mfa.verification.failed',
            'reason_key' => 'challenge_expired',
            'subject_id' => $expiredChallengeId,
        ]);
    }

    public function test_expired_pending_enrollment_secret_is_destroyed_deterministically(): void
    {
        $this->signInAndGrantPrivilege('+919876543254');
        $enrollment = $this->postJson('/api/v1/auth/mfa/enrollments', [])
            ->assertCreated();
        $factorId = $enrollment->json('factor_id');
        $challengeId = $enrollment->json('challenge_id');
        self::assertIsString($factorId);
        self::assertIsString($challengeId);
        self::assertNotNull(
            PrivilegedMfaFactor::query()
                ->whereKey($factorId)
                ->value('secret_ciphertext'),
        );

        CarbonImmutable::setTestNow(now()->addMinutes(5)->addSecond());
        $firstPurge = $this->artisan('auth:mfa-expire-pending');
        self::assertInstanceOf(PendingCommand::class, $firstPurge);
        $firstPurge
            ->expectsOutput('{"expired":1}')
            ->assertSuccessful()
            ->execute();
        $this->assertDatabaseHas('privileged_mfa_factors', [
            'id' => $factorId,
            'secret_ciphertext' => null,
            'status' => PrivilegedMfaFactor::STATUS_ABANDONED,
            'pending_expires_at' => null,
        ]);
        $this->assertDatabaseHas('privileged_mfa_challenges', [
            'id' => $challengeId,
            'superseded_at' => now(),
        ]);
        $secondPurge = $this->artisan('auth:mfa-expire-pending');
        self::assertInstanceOf(PendingCommand::class, $secondPurge);
        $secondPurge
            ->expectsOutput('{"expired":0}')
            ->assertSuccessful()
            ->execute();
    }

    /**
     * @return array{User, string}
     */
    private function signInAndGrantPrivilege(string $mobileNumber): array
    {
        [$user, $sessionId] = $this->signInExistingUser($mobileNumber);
        $membership = DB::table('memberships')
            ->where('user_id', $user->getKey())
            ->sole(['id', 'organization_id']);
        $adminRoleId = DB::table('roles')
            ->where('organization_id', $membership->organization_id)
            ->where('key', Role::DIRECT_ORGANIZATION_ADMIN_KEY)
            ->value('id');
        self::assertIsString($adminRoleId);
        DB::table('membership_roles')->insertOrIgnore([
            'organization_id' => $membership->organization_id,
            'membership_id' => $membership->id,
            'role_id' => $adminRoleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $sessionId];
    }

    /**
     * @return array{User, string}
     */
    private function signInExistingUser(string $mobileNumber): array
    {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        Auth::forgetGuards();
        $issued = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => $mobileNumber,
            ])->assertAccepted();
        $challengeId = $issued->json('challenge_id');
        self::assertIsString($challengeId);
        $verified = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => $mobileNumber,
                'code' => self::OTP,
            ])->assertOk();
        $cookie = $verified->getCookie((string) config('session.cookie'));
        self::assertNotNull($cookie);
        $this->withCookie($cookie->getName(), (string) $cookie->getValue());
        $userId = $verified->json('user.id');
        $sessionId = $verified->json('session.id');
        self::assertIsString($userId);
        self::assertIsString($sessionId);

        return [User::query()->findOrFail($userId), $sessionId];
    }

    /**
     * @return array{string, string, list<string>}
     */
    private function enrollCurrentUser(): array
    {
        $this->getJson('/api/v1/auth/mfa')->assertOk();
        $enrollment = $this->postJson('/api/v1/auth/mfa/enrollments', [])
            ->assertCreated();
        $factorId = $enrollment->json('factor_id');
        $challengeId = $enrollment->json('challenge_id');
        $uri = $enrollment->json('provisioning_uri');
        self::assertIsString($factorId);
        self::assertIsString($challengeId);
        self::assertIsString($uri);
        $secret = $this->secretFromProvisioningUri($uri);
        $confirmed = $this->postJson(
            '/api/v1/auth/mfa/enrollments/'.$factorId.'/confirm',
            [
                'challenge_id' => $challengeId,
                'code' => $this->totpCode($secret),
            ],
        )->assertOk();
        $codes = $confirmed->json('recovery_codes');
        self::assertIsArray($codes);

        return [$factorId, $secret, array_values($codes)];
    }

    private function issueAssertionChallenge(): string
    {
        $response = $this->postJson('/api/v1/auth/mfa/challenges', [])
            ->assertCreated();
        $challengeId = $response->json('challenge_id');
        self::assertIsString($challengeId);

        return $challengeId;
    }

    private function verifyCurrentTotp(string $secret): void
    {
        $challengeId = $this->issueAssertionChallenge();
        $this->postJson(
            '/api/v1/auth/mfa/challenges/'.$challengeId.'/verify',
            ['method' => 'totp', 'code' => $this->totpCode($secret)],
        )->assertOk()
            ->assertJsonPath('scope', 'privileged_actions');
    }

    private function secretFromProvisioningUri(string $uri): string
    {
        $query = parse_url($uri, PHP_URL_QUERY);
        self::assertIsString($query);
        parse_str($query, $parameters);
        $secret = $parameters['secret'] ?? null;
        self::assertIsString($secret);

        return $secret;
    }

    private function totpCode(string $secret): string
    {
        return $this->app
            ->make(Rfc6238PrivilegedMfaVerifier::class)
            ->codeForTesting($secret, now()->getTimestamp());
    }

    private function createTrackedMobileFamily(User $user): MobileTokenFamily
    {
        $client = Client::query()->create([
            'name' => 'MFA global revocation client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => ['com.globalenglish.app:/oauth/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ]);
        $accessId = Str::random(80);
        $refreshId = Str::random(80);
        DB::table('oauth_access_tokens')->insert([
            'id' => $accessId,
            'user_id' => $user->getKey(),
            'client_id' => $client->getKey(),
            'name' => null,
            'scopes' => json_encode(['mobile:api'], JSON_THROW_ON_ERROR),
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);
        DB::table('oauth_refresh_tokens')->insert([
            'id' => $refreshId,
            'access_token_id' => $accessId,
            'revoked' => false,
            'expires_at' => now()->addDays(90),
        ]);
        $this->app->make(MobileTokenFamilyService::class)->trackIssuedTokens(
            'authorization_code',
            (string) Str::ulid(),
            $this->encodedAccessToken($accessId),
            'mfa-test-refresh-token',
            null,
        );
        $family = MobileTokenFamily::query()->sole();
        $family->setAttribute('test_access_token_id', $accessId);

        return $family;
    }

    private function encodedAccessToken(string $identifier): string
    {
        $base64Url = static fn (string $value): string => rtrim(
            strtr(base64_encode($value), '+/', '-_'),
            '=',
        );

        return $base64Url(json_encode(['alg' => 'RS256'], JSON_THROW_ON_ERROR))
            .'.'.$base64Url(json_encode(['jti' => $identifier], JSON_THROW_ON_ERROR))
            .'.signature';
    }
}

final class PrivilegedMfaFixedOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private readonly string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class PrivilegedMfaNullOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    public function send(string $mobileNumber, string $code): void {}
}

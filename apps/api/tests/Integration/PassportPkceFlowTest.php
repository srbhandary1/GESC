<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Authentication\Password\PasswordCredentialService;
use App\Models\AuthenticationSession;
use App\Models\InstitutionInvite;
use App\Models\Membership;
use App\Models\MobileTokenFamily;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\InstitutionInviteCodeDigester;
use Carbon\CarbonImmutable;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class PassportPkceFlowTest extends TestCase
{
    use RefreshDatabase;

    private PassportJourneyOtpDeliveryAdapter $delivery;

    protected function setUp(): void
    {
        parent::setUp();

        config(['authentication.mode' => 'legacy_otp']);
        $this->seed(FoundationSeeder::class);
        $this->delivery = new PassportJourneyOtpDeliveryAdapter;
        $this->app->instance(OtpDeliveryAdapter::class, $this->delivery);
        $this->app->instance(
            OtpCodeGenerator::class,
            new PassportJourneyOtpCodeGenerator('123456'),
        );
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_public_pkce_flow_rotates_refresh_tokens_and_revokes_on_replay(): void
    {
        $this->configurePassportKeys();

        $client = $this->createPublicClient();
        $state = Str::random(40);
        $verifier = Str::random(64);
        $challenge = rtrim(strtr(
            base64_encode(hash('sha256', $verifier, true)),
            '+/',
            '-_',
        ), '=');
        $redirectUri = 'com.globalenglish.app:/oauth/callback';

        $authorization = $this->authorizeThroughOtp(
            $client,
            $redirectUri,
            $state,
            $challenge,
        );

        $location = (string) $authorization->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $redirectQuery);
        self::assertSame($state, $redirectQuery['state'] ?? null);
        self::assertIsString($redirectQuery['code'] ?? null);
        $this->assertDatabaseCount('authentication_sessions', 1);

        $installationId = (string) Str::ulid();
        $tokenExchange = [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'redirect_uri' => $redirectUri,
            'code' => $redirectQuery['code'],
            'code_verifier' => $verifier,
            'installation_id' => $installationId,
        ];
        $this->withHeader(
            'Authorization',
            'Basic '.base64_encode($client->getKey().':must-not-be-used'),
        )->post('/oauth/token', $tokenExchange)
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_request');
        $this->withoutHeader('Authorization');

        $issued = $this->post('/oauth/token', $tokenExchange)
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 900);

        $firstRefreshToken = $issued->json('refresh_token');
        self::assertIsString($firstRefreshToken);

        $family = MobileTokenFamily::query()->firstOrFail();
        self::assertTrue($family->absolute_expires_at->isBetween(
            now()->addDays(90)->subMinute(),
            now()->addDays(90)->addMinute(),
        ));
        $passportRefreshExpiry = DB::table('oauth_refresh_tokens')
            ->where('revoked', false)
            ->max('expires_at');
        self::assertIsString($passportRefreshExpiry);
        self::assertTrue(now()->parse($passportRefreshExpiry)->isBetween(
            now()->addDays(30)->subMinute(),
            now()->addDays(30)->addMinute(),
        ));

        $rotated = $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->getKey(),
            'refresh_token' => $firstRefreshToken,
            'installation_id' => $installationId,
        ])
            ->assertOk()
            ->assertJsonPath('expires_in', 900);

        self::assertIsString($rotated->json('refresh_token'));
        self::assertNotSame($firstRefreshToken, $rotated->json('refresh_token'));

        $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->getKey(),
            'refresh_token' => $firstRefreshToken,
            'installation_id' => $installationId,
        ])
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_grant');

        self::assertSame(
            'refresh_token_reuse',
            $family->fresh()?->revocation_reason,
        );
        self::assertSame(
            0,
            DB::table('oauth_refresh_tokens')->where('revoked', false)->count(),
        );
        $this->assertDatabaseHas('audit_events', [
            'action' => 'authentication.mobile_credential.reuse_detected',
            'subject_id' => $family->getKey(),
        ]);
    }

    public function test_mobile_server_browser_invite_otp_creates_only_the_institution_membership_on_postgresql(): void
    {
        $this->configurePassportKeys();

        $rawInviteCode = 'GEI-POSTGRES-MOBILE-INVITE';
        [$organizationId, $inviteId] = $this->createInstitutionInvite(
            $rawInviteCode,
        );
        $client = $this->createPublicClient();
        $authorization = $this->authorizationParameters();

        $approved = $this->authorizeThroughOtp(
            $client,
            $authorization['redirect_uri'],
            $authorization['state'],
            $authorization['challenge'],
            $rawInviteCode,
        );

        $location = (string) $approved->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $redirectQuery);
        self::assertIsString($redirectQuery['code'] ?? null);
        $userId = DB::table('users')->value('id');
        self::assertIsString($userId);
        $directOrganizationId = DB::table('organizations')
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->value('id');
        self::assertIsString($directOrganizationId);
        self::assertSame(1, DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->count());
        self::assertSame(0, DB::table('memberships')
            ->where('organization_id', $directOrganizationId)
            ->where('user_id', $userId)
            ->count());
        $challengeId = DB::table('otp_challenges')->value('id');
        self::assertIsString($challengeId);
        $this->assertDatabaseHas('institution_invites', [
            'id' => $inviteId,
            'status' => InstitutionInvite::STATUS_ACCEPTED,
            'accepted_by_user_id' => $userId,
            'accepted_idempotency_key' => $challengeId,
        ]);
        $this->assertDatabaseCount('authentication_sessions', 1);

        $recorded = json_encode([
            'invite' => DB::table('institution_invites')
                ->where('id', $inviteId)
                ->first(),
            'sessions' => DB::table('sessions')->pluck('payload')->all(),
            'audit' => DB::table('audit_events')->get()->all(),
            'outbox' => DB::table('outbox_events')->get()->all(),
        ], JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($rawInviteCode, $recorded);
    }

    public function test_mobile_system_browser_uses_password_only_without_exposing_legacy_otp_fields(): void
    {
        config(['authentication.mode' => 'password_only']);
        $this->configurePassportKeys();

        $user = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        $this->app->make(PasswordCredentialService::class)->establish(
            $user,
            'learner.one',
            'correct horse battery staple 2026',
            Request::create('/test/password-establishment', 'POST'),
        );

        $client = $this->createPublicClient();
        $authorization = $this->authorizationParameters();
        $target = $this->authorizationTarget(
            $client,
            $authorization['redirect_uri'],
            $authorization['state'],
            $authorization['challenge'],
        );

        $guest = $this->get($target)
            ->assertRedirectToRoute('auth.mobile.sign-in');
        $this->assertAuthenticationPageHeaders($guest);
        $this->adoptSessionCookie($guest, true);

        $signIn = $this->get('/auth/mobile/sign-in')
            ->assertOk()
            ->assertSee('Enter your username and password.')
            ->assertSee('name="username"', false)
            ->assertSee('name="password"', false)
            ->assertDontSee('name="mobile_number"', false)
            ->assertDontSee('institution_invite_code', false);
        $this->assertAuthenticationPageHeaders($signIn);

        $signedIn = $this->post('/auth/mobile/sign-in/password', [
            'username' => 'learner.one',
            'password' => 'correct horse battery staple 2026',
        ])->assertRedirect();
        $this->assertAuthenticationPageHeaders($signedIn);
        $this->assertSameAuthorizationTarget(
            $target,
            $this->relativeLocation((string) $signedIn->headers->get('Location')),
        );
        $this->assertDatabaseCount('authentication_sessions', 1);
        $this->adoptSessionCookie($signedIn, true);

        $completed = $this->get($target)->assertRedirect();
        $this->assertAuthenticationPageHeaders($completed);
        self::assertSame(
            'com.globalenglish.app',
            parse_url((string) $completed->headers->get('Location'), PHP_URL_SCHEME),
        );
        self::assertStringNotContainsString(
            'Continue to',
            (string) $completed->getContent(),
        );

        $this->post('/auth/mobile/sign-in/otp/requests', [
            'mobile_number' => '+919876543210',
        ])->assertGone();
    }

    public function test_expired_web_authentication_session_cannot_authorize_a_mobile_client(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T10:00:00+00:00');
        $this->configurePassportKeys();

        $client = $this->createPublicClient();
        $authorization = $this->authorizationParameters();
        $this->authorizeThroughOtp(
            $client,
            $authorization['redirect_uri'],
            $authorization['state'],
            $authorization['challenge'],
        );

        $authenticationSession = AuthenticationSession::query()->firstOrFail();
        CarbonImmutable::setTestNow(now()->addMinutes(121));

        $response = $this->get($this->authorizationTarget(
            $client,
            $authorization['redirect_uri'],
            Str::random(40),
            $authorization['challenge'],
        ))
            ->assertRedirectToRoute('auth.mobile.sign-in');

        $this->assertAuthenticationPageHeaders($response);
        self::assertSame(
            'expired',
            $authenticationSession->fresh()?->revocation_reason,
        );
        $this->assertDatabaseHas('audit_events', [
            'action' => 'authentication.session.expired',
            'subject_id' => $authenticationSession->getKey(),
        ]);
    }

    public function test_suspended_user_cannot_authorize_a_mobile_client(): void
    {
        $this->configurePassportKeys();

        $client = $this->createPublicClient();
        $authorization = $this->authorizationParameters();
        $this->authorizeThroughOtp(
            $client,
            $authorization['redirect_uri'],
            $authorization['state'],
            $authorization['challenge'],
        );

        $user = User::query()->firstOrFail();
        $authenticationSession = AuthenticationSession::query()->firstOrFail();
        $user->forceFill(['status' => User::STATUS_SUSPENDED])->save();

        $response = $this->get($this->authorizationTarget(
            $client,
            $authorization['redirect_uri'],
            Str::random(40),
            $authorization['challenge'],
        ))
            ->assertRedirectToRoute('auth.mobile.sign-in');

        $this->assertAuthenticationPageHeaders($response);
        self::assertSame(
            'user_not_active',
            $authenticationSession->fresh()?->revocation_reason,
        );
    }

    public function test_registered_client_cannot_redirect_authorization_to_an_unregistered_uri(): void
    {
        $this->configurePassportKeys();

        $client = $this->createPublicClient();
        $authorization = $this->authorizationParameters();
        $this->authorizeThroughOtp(
            $client,
            $authorization['redirect_uri'],
            $authorization['state'],
            $authorization['challenge'],
        );

        $response = $this->get($this->authorizationTarget(
            $client,
            'https://attacker.example/callback',
            Str::random(40),
            $authorization['challenge'],
        ));

        self::assertContains($response->getStatusCode(), [400, 401]);
        self::assertFalse($response->isRedirect());
        self::assertStringNotContainsString(
            'attacker.example',
            (string) $response->headers->get('Location'),
        );
        $this->assertAuthenticationPageHeaders($response);
    }

    public function test_mobile_revocation_endpoint_revokes_the_matching_family(): void
    {
        $this->configurePassportKeys();

        $client = $this->createPublicClient();
        $authorization = $this->authorizationParameters();
        $callback = $this->authorizeThroughOtp(
            $client,
            $authorization['redirect_uri'],
            $authorization['state'],
            $authorization['challenge'],
        );
        parse_str(
            (string) parse_url(
                (string) $callback->headers->get('Location'),
                PHP_URL_QUERY,
            ),
            $callbackQuery,
        );
        self::assertIsString($callbackQuery['code'] ?? null);
        $installationId = (string) Str::ulid();
        $issued = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'redirect_uri' => $authorization['redirect_uri'],
            'code' => $callbackQuery['code'],
            'code_verifier' => $authorization['verifier'],
            'installation_id' => $installationId,
        ])->assertOk();
        $accessToken = $issued->json('access_token');
        self::assertIsString($accessToken);
        $family = MobileTokenFamily::query()->firstOrFail();

        $this->withToken($accessToken)
            ->postJson('/api/v1/auth/mobile/revoke', [
                'installation_id' => $installationId,
                'unexpected' => true,
            ])
            ->assertUnprocessable();
        self::assertNull($family->fresh()?->revoked_at);

        $response = $this->withToken($accessToken)
            ->postJson('/api/v1/auth/mobile/revoke', [
                'installation_id' => $installationId,
            ])
            ->assertNoContent();
        self::assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );

        self::assertSame('mobile_logout', $family->fresh()?->revocation_reason);
        self::assertSame(
            0,
            DB::table('oauth_access_tokens')->where('revoked', false)->count(),
        );
        self::assertSame(
            0,
            DB::table('oauth_refresh_tokens')->where('revoked', false)->count(),
        );
        $this->assertDatabaseHas('audit_events', [
            'action' => 'authentication.mobile_credential.revoked',
            'subject_type' => 'mobile_credential_family',
            'subject_id' => $family->getKey(),
        ]);
        $auditPayload = json_encode(
            DB::table('audit_events')->get()->all(),
            JSON_THROW_ON_ERROR,
        );
        self::assertStringNotContainsString($accessToken, $auditPayload);
        self::assertStringNotContainsString(
            (string) $issued->json('refresh_token'),
            $auditPayload,
        );
        self::assertStringNotContainsString($installationId, $auditPayload);
    }

    public function test_mobile_revocation_endpoint_requires_mobile_api_scope(): void
    {
        $this->configurePassportKeys();

        $user = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);

        Passport::actingAs($user, []);

        $this->postJson('/api/v1/auth/mobile/revoke', [
            'installation_id' => (string) Str::ulid(),
        ])->assertForbidden();
    }

    /**
     * @return array{
     *     redirect_uri: string,
     *     state: string,
     *     challenge: string,
     *     verifier: string
     * }
     */
    private function authorizationParameters(): array
    {
        $verifier = Str::random(64);

        return [
            'redirect_uri' => 'com.globalenglish.app:/oauth/callback',
            'state' => Str::random(40),
            'challenge' => rtrim(strtr(
                base64_encode(hash('sha256', $verifier, true)),
                '+/',
                '-_',
            ), '='),
            'verifier' => $verifier,
        ];
    }

    /**
     * @return TestResponse<Response>
     */
    private function authorizeThroughOtp(
        Client $client,
        string $redirectUri,
        string $state,
        string $challenge,
        ?string $institutionInviteCode = null,
    ): TestResponse {
        $target = $this->authorizationTarget(
            $client,
            $redirectUri,
            $state,
            $challenge,
        );

        $guest = $this->get($target)
            ->assertRedirectToRoute('auth.mobile.sign-in');
        $this->assertAuthenticationPageHeaders($guest);
        $this->adoptSessionCookie($guest, true);

        $signIn = $this->get('/auth/mobile/sign-in')
            ->assertOk()
            ->assertSee('Sign in to Global English');
        $this->assertAuthenticationPageHeaders($signIn);

        $challengeRequest = ['mobile_number' => '+919876543210'];

        if ($institutionInviteCode !== null) {
            $challengeRequest['institution_invite_code'] = $institutionInviteCode;
        }

        $challengeResponse = $this->post(
            '/auth/mobile/sign-in/otp/requests',
            $challengeRequest,
        )
            ->assertOk()
            ->assertSee('Enter the verification code');
        $this->assertAuthenticationPageHeaders($challengeResponse);
        $this->adoptSessionCookie($challengeResponse);
        self::assertSame(
            [['+919876543210', '123456']],
            $this->delivery->deliveries,
        );
        $this->assertDatabaseSessionExcludes('+919876543210');
        $this->assertDatabaseSessionExcludes('123456');

        if ($institutionInviteCode !== null) {
            $this->assertDatabaseSessionExcludes($institutionInviteCode);
        }

        $verified = $this->post(
            '/auth/mobile/sign-in/otp/verifications',
            ['code' => '123456'],
        )->assertRedirect();
        $this->assertAuthenticationPageHeaders($verified);
        $this->assertSameAuthorizationTarget(
            $target,
            $this->relativeLocation((string) $verified->headers->get('Location')),
        );
        $this->adoptSessionCookie($verified, true);
        $this->assertDatabaseSessionExcludes('+919876543210');
        $this->assertDatabaseSessionExcludes('123456');

        if ($institutionInviteCode !== null) {
            $this->assertDatabaseSessionExcludes($institutionInviteCode);
        }

        $approved = $this->get($target)->assertRedirect();
        $this->assertAuthenticationPageHeaders($approved);

        self::assertSame(
            'com.globalenglish.app',
            parse_url((string) $approved->headers->get('Location'), PHP_URL_SCHEME),
        );

        return $approved;
    }

    /**
     * @return array{string, string}
     */
    private function createInstitutionInvite(string $rawCode): array
    {
        $now = now();
        $organizationId = (string) Str::ulid();
        $inviteId = (string) Str::ulid();
        $roleId = (string) Str::ulid();

        DB::table('organizations')->insert([
            'id' => $organizationId,
            'system_key' => null,
            'name' => 'PostgreSQL Mobile Institution',
            'kind' => Organization::KIND_INSTITUTION,
            'status' => Organization::STATUS_ACTIVE,
            'timezone' => 'Asia/Kolkata',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('roles')->insert([
            'id' => $roleId,
            'organization_id' => $organizationId,
            'key' => 'learner-safe',
            'name' => 'Learner',
            'is_system' => false,
            'status' => 'active',
            'version' => 1,
            'retired_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('role_permissions')->insert([
            'organization_id' => $organizationId,
            'role_id' => $roleId,
            'permission_id' => DB::table('permissions')
                ->where('key', 'learning.participate')
                ->value('id'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('institution_invites')->insert([
            'id' => $inviteId,
            'organization_id' => $organizationId,
            'code_digest' => $this->app
                ->make(InstitutionInviteCodeDigester::class)
                ->digest($rawCode),
            'membership_type' => Membership::TYPE_LEARNER,
            'role_id' => $roleId,
            'status' => InstitutionInvite::STATUS_PENDING,
            'expires_at' => $now->copy()->addDay(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$organizationId, $inviteId];
    }

    private function authorizationTarget(
        Client $client,
        string $redirectUri,
        string $state,
        string $challenge,
    ): string {
        return '/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'mobile:api',
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    private function createPublicClient(): Client
    {
        return Client::query()->create([
            'name' => 'Global English Android integration client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => ['com.globalenglish.app:/oauth/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ]);
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function adoptSessionCookie(
        TestResponse $response,
        bool $required = false,
    ): void {
        $cookie = $response->getCookie((string) config('session.cookie'));

        if ($cookie === null) {
            self::assertFalse($required, 'The response did not issue the expected session cookie.');

            return;
        }

        self::assertTrue($cookie->isSecure());
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('lax', $cookie->getSameSite());
        $this->withCookie($cookie->getName(), (string) $cookie->getValue());
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function assertAuthenticationPageHeaders(TestResponse $response): void
    {
        self::assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertStringContainsString(
            "frame-ancestors 'none'",
            (string) $response->headers->get('Content-Security-Policy'),
        );
    }

    private function assertDatabaseSessionExcludes(string $sensitiveValue): void
    {
        $payloads = DB::table('sessions')->pluck('payload');
        self::assertNotEmpty($payloads);

        foreach ($payloads as $payload) {
            self::assertIsString($payload);
            $decoded = base64_decode($payload, true);
            self::assertIsString($decoded);
            self::assertStringNotContainsString($sensitiveValue, $decoded);
        }
    }

    private function relativeLocation(string $location): string
    {
        $path = parse_url($location, PHP_URL_PATH);
        $query = parse_url($location, PHP_URL_QUERY);
        self::assertIsString($path);

        return $path.(is_string($query) ? '?'.$query : '');
    }

    private function assertSameAuthorizationTarget(
        string $expected,
        string $actual,
    ): void {
        self::assertSame(
            parse_url($expected, PHP_URL_PATH),
            parse_url($actual, PHP_URL_PATH),
        );

        parse_str((string) parse_url($expected, PHP_URL_QUERY), $expectedQuery);
        parse_str((string) parse_url($actual, PHP_URL_QUERY), $actualQuery);
        ksort($expectedQuery);
        ksort($actualQuery);

        self::assertSame($expectedQuery, $actualQuery);
    }

    private function configurePassportKeys(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($key);

        $exported = openssl_pkey_export($key, $privateKey);
        self::assertTrue($exported);
        self::assertIsString($privateKey);

        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        self::assertIsString($details['key'] ?? null);

        config([
            'passport.private_key' => $privateKey,
            'passport.public_key' => $details['key'],
        ]);
    }
}

final class PassportJourneyOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private readonly string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class PassportJourneyOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    /**
     * @var list<array{string, string}>
     */
    public array $deliveries = [];

    public function send(string $normalizedMobileNumber, string $code): void
    {
        $this->deliveries[] = [$normalizedMobileNumber, $code];
    }
}

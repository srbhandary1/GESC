<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Authentication\Mobile\MobileOAuthClient;
use App\Authentication\Password\PasswordCredentialService;
use App\Models\AuthenticationSession;
use App\Models\MobileTokenFamily;
use App\Models\User;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * @phpstan-require-extends TestCase
 */
trait MobilePasswordSignInTests
{
    protected function prepareMobilePasswordSignIn(): void
    {
        config(['authentication.mode' => 'password_only']);
        $this->seed(FoundationSeeder::class);
        $this->configurePassportKeys();
        $this->withServerVariables(['HTTPS' => 'on']);
    }

    public function test_mobile_password_sign_in_issues_a_refreshable_family(): void
    {
        $user = $this->credentialReadyUser('learner.one', 'Correct horse battery 2026!');
        $client = $this->createApprovedMobileClient();
        $installationId = (string) Str::ulid();

        $issued = $this->postJson('/api/v1/auth/mobile/password/sign-ins', [
            'username' => 'LEARNER.ONE',
            'password' => 'Correct horse battery 2026!',
            'installation_id' => $installationId,
            'client_id' => $client->getKey(),
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 900);

        self::assertStringContainsString(
            'no-store',
            (string) $issued->headers->get('Cache-Control'),
        );

        $accessToken = $issued->json('access_token');
        $refreshToken = $issued->json('refresh_token');
        self::assertIsString($accessToken);
        self::assertIsString($refreshToken);
        $payload = $issued->json();
        self::assertIsArray($payload);
        $keys = array_keys($payload);
        sort($keys);
        self::assertSame(
            ['access_token', 'expires_in', 'refresh_token', 'token_type'],
            $keys,
        );
        $issued->assertCookieMissing((string) config('session.cookie'));
        self::assertSame(0, AuthenticationSession::query()->count());
        self::assertSame(1, MobileTokenFamily::query()->count());

        $this->withToken($accessToken)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.id', (string) $user->getKey());

        $rotated = $this->withoutToken()->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->getKey(),
            'refresh_token' => $refreshToken,
            'installation_id' => $installationId,
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer');

        $rotatedAccessToken = $rotated->json('access_token');
        self::assertIsString($rotatedAccessToken);
        self::assertNotSame($accessToken, $rotatedAccessToken);
        Auth::forgetGuards();
        $this->withToken($rotatedAccessToken)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.id', (string) $user->getKey());
    }

    public function test_mobile_password_sign_in_is_generic_for_wrong_credentials_and_clients(): void
    {
        $client = $this->createApprovedMobileClient();
        $this->credentialReadyUser('learner.one', 'Correct horse battery 2026!');
        $installationId = (string) Str::ulid();

        foreach ([
            [
                'username' => 'missing.user',
                'password' => 'Definitely wrong password!',
                'installation_id' => $installationId,
                'client_id' => $client->getKey(),
            ],
            [
                'username' => 'learner.one',
                'password' => 'Definitely wrong password!',
                'installation_id' => $installationId,
                'client_id' => $client->getKey(),
            ],
            [
                'username' => 'learner.one',
                'password' => 'Correct horse battery 2026!',
                'installation_id' => $installationId,
                'client_id' => (string) Str::uuid(),
            ],
        ] as $payload) {
            $this->postJson('/api/v1/auth/mobile/password/sign-ins', $payload)
                ->assertUnprocessable()
                ->assertJsonPath('detail', 'One or more request values are invalid.');
        }

        self::assertSame(0, MobileTokenFamily::query()->count());
    }

    public function test_mobile_password_sign_in_requires_an_installation_id(): void
    {
        $client = $this->createApprovedMobileClient();
        $this->credentialReadyUser('learner.one', 'Correct horse battery 2026!');

        $this->postJson('/api/v1/auth/mobile/password/sign-ins', [
            'username' => 'learner.one',
            'password' => 'Correct horse battery 2026!',
            'client_id' => $client->getKey(),
        ])->assertUnprocessable();

        self::assertSame(0, MobileTokenFamily::query()->count());
    }

    public function test_web_password_sign_in_without_a_stateful_browser_is_unauthorized(): void
    {
        $this->credentialReadyUser('learner.one', 'Correct horse battery 2026!');

        $this->postJson('/api/v1/auth/password/sign-ins', [
            'username' => 'learner.one',
            'password' => 'Correct horse battery 2026!',
        ])->assertUnauthorized();
    }

    public function test_oauth_token_password_grant_remains_rejected(): void
    {
        $client = $this->createApprovedMobileClient();

        $this->postJson('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->getKey(),
            'username' => 'learner.one',
            'password' => 'Correct horse battery 2026!',
            'installation_id' => (string) Str::ulid(),
        ])
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_request');
    }

    public function test_mobile_password_mode_and_recovery_are_available_without_a_session(): void
    {
        $client = $this->createApprovedMobileClient();

        $this->getJson('/api/v1/auth/mobile/password/mode')
            ->assertOk()
            ->assertJsonPath('mode', 'password_only')
            ->assertJsonPath('client_id', (string) $client->getKey());

        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/mobile/password/recovery-requests', [
                'username' => 'learner.one',
            ])
            ->assertAccepted()
            ->assertJsonPath(
                'message',
                'If the account is eligible, recovery instructions will be sent.',
            );
    }

    public function test_mobile_password_sign_in_does_not_require_csrf_from_a_browser_origin(): void
    {
        $user = $this->credentialReadyUser('learner.one', 'Correct horse battery 2026!');
        $client = $this->createApprovedMobileClient();

        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/mobile/password/sign-ins', [
                'username' => 'learner.one',
                'password' => 'Correct horse battery 2026!',
                'installation_id' => (string) Str::ulid(),
                'client_id' => $client->getKey(),
            ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer');

        self::assertSame(0, AuthenticationSession::query()->count());
        $this->assertDatabaseHas('mobile_token_families', [
            'user_id' => $user->getKey(),
        ]);
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

    private function createApprovedMobileClient(): MobileOAuthClient
    {
        return MobileOAuthClient::query()->create([
            'name' => 'Global English Android password client',
            'secret' => null,
            'provider' => 'users',
            'owner_type' => null,
            'owner_id' => null,
            'redirect_uris' => ['com.globalenglish.app:/oauth/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ]);
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

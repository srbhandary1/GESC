<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Authentication\Federated\FederatedAccount;
use App\Authentication\Federated\FederatedSignInService;
use App\Authentication\Mobile\MobileOAuthClient;
use App\Authentication\Password\PasswordCredentialService;
use App\Models\AuthenticationSession;
use App\Models\FederatedIdentity;
use App\Models\MobileTokenFamily;
use App\Models\User;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * @phpstan-require-extends TestCase
 */
trait FederatedSignInTests
{
    protected function prepareFederatedSignIn(): void
    {
        config([
            'authentication.mode' => 'password_only',
            'sanctum.stateful' => array_values(array_unique([
                ...(array) config('sanctum.stateful'),
                'localhost',
                '127.0.0.1',
            ])),
        ]);
        $this->seed(FoundationSeeder::class);
        $this->configurePassportKeys();
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->flushHeaders();
        $this->withCredentials();
    }

    public function test_web_federated_sign_in_issues_the_same_sanctum_session(): void
    {
        $user = $this->credentialReadyUser('learner.one', 'Correct horse battery 2026!');
        $this->linkProvider($user, FederatedIdentity::PROVIDER_GOOGLE, 'google-subject-1');

        $callback = $this->completeWebProviderCallback(
            FederatedIdentity::PROVIDER_GOOGLE,
            'google-subject-1',
        );
        $callback->assertRedirect('/');
        $this->followSessionCookie($callback);
        self::assertSame(1, AuthenticationSession::query()->count());
        $this->assertAuthenticated('web');

        $this->withHeader('Origin', 'https://localhost')
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.id', (string) $user->getKey());
    }

    public function test_federated_sign_in_does_not_create_an_account(): void
    {
        $usersBefore = User::query()->count();
        $callback = $this->completeWebProviderCallback(
            FederatedIdentity::PROVIDER_GOOGLE,
            'unknown-google-subject',
        );
        $callback->assertRedirect('/');

        self::assertSame($usersBefore, User::query()->count());
        self::assertSame(0, AuthenticationSession::query()->count());
        self::assertSame(0, FederatedIdentity::query()->count());
    }

    public function test_web_link_then_sign_in_is_idempotent_for_the_same_subject(): void
    {
        $user = $this->credentialReadyUser('learner.one', 'Correct horse battery 2026!');
        $signedIn = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/password/sign-ins', [
                'username' => 'learner.one',
                'password' => 'Correct horse battery 2026!',
            ])
            ->assertOk();
        $this->followSessionCookie($signedIn);

        $this->completeWebProviderCallback(
            FederatedIdentity::PROVIDER_FACEBOOK,
            'facebook-subject-1',
            'link',
        )->assertRedirect('/');

        self::assertSame(1, FederatedIdentity::query()->count());
        $this->assertDatabaseHas('federated_identities', [
            'user_id' => $user->getKey(),
            'provider' => FederatedIdentity::PROVIDER_FACEBOOK,
        ]);

        $this->completeWebProviderCallback(
            FederatedIdentity::PROVIDER_FACEBOOK,
            'facebook-subject-1',
            'link',
        )->assertRedirect('/');

        self::assertSame(1, FederatedIdentity::query()->count());
    }

    public function test_mobile_federated_sign_in_issues_a_refreshable_family(): void
    {
        $user = $this->credentialReadyUser('learner.one', 'Correct horse battery 2026!');
        $this->linkProvider($user, FederatedIdentity::PROVIDER_GOOGLE, 'google-subject-1');
        $client = $this->createApprovedMobileClient();
        $installationId = (string) Str::ulid();

        $authorization = $this->getJson(
            '/api/v1/auth/mobile/social/google/authorization?'.http_build_query([
                'client_id' => $client->getKey(),
                'installation_id' => $installationId,
            ]),
        )
            ->assertOk()
            ->json('authorization_url');
        self::assertIsString($authorization);

        $authorize = $this->get($authorization);
        $authorize->assertRedirect();
        $state = $this->redirectQueryParam(
            (string) $authorize->headers->get('Location'),
            'state',
        );

        $callback = $this->get('/oauth/social/google/callback?'.http_build_query([
            'code' => 'google-subject-1',
            'state' => $state,
        ]));
        $callback->assertRedirect();
        $callbackLocation = $callback->headers->get('Location');
        self::assertIsString($callbackLocation);
        self::assertStringStartsWith('com.globalenglish.app:/oauth/callback?', $callbackLocation);
        $ticket = $this->redirectQueryParam($callbackLocation, 'social_ticket');

        $issued = $this->postJson('/api/v1/auth/mobile/social/sign-ins', [
            'ticket' => $ticket,
            'installation_id' => $installationId,
            'client_id' => $client->getKey(),
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer');

        $issued->assertCookieMissing((string) config('session.cookie'));
        self::assertSame(0, AuthenticationSession::query()->count());
        self::assertSame(1, MobileTokenFamily::query()->count());

        $accessToken = $issued->json('access_token');
        self::assertIsString($accessToken);
        $this->withToken($accessToken)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.id', (string) $user->getKey());
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

    public function test_social_providers_are_available_without_a_session(): void
    {
        $this->getJson('/api/v1/auth/social/providers')
            ->assertOk()
            ->assertExactJson(['google' => true, 'facebook' => true]);

        $this->getJson('/api/v1/auth/mobile/social/providers')
            ->assertOk()
            ->assertExactJson(['google' => true, 'facebook' => true]);
    }

    /** @return TestResponse<Response> */
    private function completeWebProviderCallback(
        string $provider,
        string $subject,
        string $intent = 'sign_in',
    ): TestResponse {
        $authorize = $this->withHeader('Origin', 'https://localhost')
            ->get('/oauth/social/'.$provider.'/authorize?'.http_build_query([
                'intent' => $intent,
            ]));
        $authorize->assertRedirect();
        $state = $this->redirectQueryParam(
            (string) $authorize->headers->get('Location'),
            'state',
        );
        $this->followSessionCookie($authorize);

        return $this->withHeader('Origin', 'https://localhost')
            ->get('/oauth/social/'.$provider.'/callback?'.http_build_query([
                'code' => $subject,
                'state' => $state,
            ]));
    }

    /** @param TestResponse<Response> $response */
    private function followSessionCookie(TestResponse $response): void
    {
        $cookie = $response->getCookie((string) config('session.cookie'));

        if ($cookie === null) {
            return;
        }

        $this->withCookie($cookie->getName(), (string) $cookie->getValue());
    }

    private function redirectQueryParam(string $location, string $name): string
    {
        $queryString = parse_url($location, PHP_URL_QUERY);
        self::assertIsString($queryString);
        $query = [];
        parse_str($queryString, $query);
        $value = $query[$name] ?? null;
        self::assertIsString($value);

        return $value;
    }

    private function linkProvider(User $user, string $provider, string $subject): void
    {
        $this->app->make(FederatedSignInService::class)->link(
            $user,
            new FederatedAccount($provider, $subject),
        );
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
            'name' => 'Global English Android federated client',
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

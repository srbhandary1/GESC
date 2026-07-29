<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\EnforceMobileOAuthPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class MobileOAuthPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorization_requires_state_and_s256_pkce(): void
    {
        $middleware = new EnforceMobileOAuthPolicy;
        $request = Request::create('/oauth/authorize', 'GET', [
            'response_type' => 'code',
            'state' => Str::random(32),
            'code_challenge' => Str::random(43),
            'code_challenge_method' => 'plain',
        ]);

        $response = $middleware->handle(
            $request,
            static fn (): Response => new Response(status: 204),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('S256 PKCE', (string) $response->getContent());
    }

    public function test_public_token_policy_rejects_secrets_and_other_grants(): void
    {
        $this->postJson('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => (string) Str::uuid(),
            'client_secret' => 'must-not-be-used',
            'installation_id' => (string) Str::ulid(),
        ])
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_request')
            ->assertJsonMissing(['client_secret' => 'must-not-be-used']);
    }

    public function test_public_token_policy_rejects_even_an_empty_client_secret_parameter(): void
    {
        $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => (string) Str::uuid(),
            'client_secret' => '',
            'refresh_token' => 'not-a-real-token',
            'installation_id' => (string) Str::ulid(),
        ])
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_request');
    }

    public function test_policy_allows_only_an_active_public_first_party_users_client(): void
    {
        $middleware = new EnforceMobileOAuthPolicy;
        $approved = $this->createMobileClient();
        $implicitUsersProvider = $this->createMobileClient([
            'provider' => null,
        ]);

        foreach ([$approved, $implicitUsersProvider] as $client) {
            self::assertSame(
                204,
                $middleware->handle(
                    $this->authorizationRequest($client),
                    static fn (): Response => new Response(status: 204),
                )->getStatusCode(),
            );
            self::assertSame(
                204,
                $middleware->handle(
                    $this->tokenRequest($client),
                    static fn (): Response => new Response(status: 204),
                )->getStatusCode(),
            );
        }

        $rejectedClients = [
            $this->createMobileClient(['secret' => 'confidential-secret']),
            $this->createMobileClient(['revoked' => true]),
            $this->createMobileClient(['provider' => 'unapproved-users']),
            $this->createMobileClient([
                'owner_type' => User::class,
                'owner_id' => (string) Str::ulid(),
            ]),
            $this->createMobileClient([
                'grant_types' => ['authorization_code'],
            ]),
            $this->createMobileClient([
                'grant_types' => ['refresh_token'],
            ]),
        ];

        foreach ($rejectedClients as $client) {
            self::assertSame(
                400,
                $middleware->handle(
                    $this->authorizationRequest($client),
                    static fn (): Response => new Response(status: 204),
                )->getStatusCode(),
            );
            self::assertSame(
                400,
                $middleware->handle(
                    $this->tokenRequest($client),
                    static fn (): Response => new Response(status: 204),
                )->getStatusCode(),
            );
        }
    }

    public function test_policy_rejects_http_basic_and_php_auth_client_credentials(): void
    {
        $middleware = new EnforceMobileOAuthPolicy;
        $client = $this->createMobileClient();
        $basicAuthorization = 'Basic '.base64_encode(
            $client->getKey().':must-not-be-used',
        );

        $authorizationRequest = $this->authorizationRequest($client);
        $authorizationRequest->headers->set(
            'Authorization',
            $basicAuthorization,
        );
        self::assertSame(
            400,
            $middleware->handle(
                $authorizationRequest,
                static fn (): Response => new Response(status: 204),
            )->getStatusCode(),
        );

        $tokenRequest = $this->tokenRequest($client);
        $tokenRequest->headers->set('Authorization', $basicAuthorization);
        self::assertSame(
            400,
            $middleware->handle(
                $tokenRequest,
                static fn (): Response => new Response(status: 204),
            )->getStatusCode(),
        );

        $phpAuthRequest = $this->tokenRequest($client);
        $phpAuthRequest->server->set('PHP_AUTH_USER', $client->getKey());
        $phpAuthRequest->server->set('PHP_AUTH_PW', 'must-not-be-used');
        self::assertSame(
            400,
            $middleware->handle(
                $phpAuthRequest,
                static fn (): Response => new Response(status: 204),
            )->getStatusCode(),
        );
    }

    public function test_authorization_requires_the_mobile_api_scope(): void
    {
        $middleware = new EnforceMobileOAuthPolicy;
        $request = Request::create('/oauth/authorize', 'GET', [
            'response_type' => 'code',
            'scope' => 'openid',
            'state' => Str::random(32),
            'code_challenge' => Str::random(43),
            'code_challenge_method' => 'S256',
        ]);

        $response = $middleware->handle(
            $request,
            static fn (): Response => new Response(status: 204),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('S256 PKCE', (string) $response->getContent());
    }

    public function test_transient_browser_oauth_cookie_endpoint_is_disabled(): void
    {
        $this->postJson('/oauth/token/refresh')
            ->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createMobileClient(array $overrides = []): Client
    {
        return Client::query()->create([
            'name' => 'Global English Android policy client',
            'secret' => null,
            'provider' => 'users',
            'owner_type' => null,
            'owner_id' => null,
            'redirect_uris' => ['com.globalenglish.app:/oauth/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
            ...$overrides,
        ]);
    }

    private function authorizationRequest(Client $client): Request
    {
        return Request::create('/oauth/authorize', 'GET', [
            'client_id' => $client->getKey(),
            'redirect_uri' => 'com.globalenglish.app:/oauth/callback',
            'response_type' => 'code',
            'scope' => 'mobile:api',
            'state' => Str::random(40),
            'code_challenge' => Str::random(43),
            'code_challenge_method' => 'S256',
        ]);
    }

    private function tokenRequest(Client $client): Request
    {
        return Request::create('/oauth/token', 'POST', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'redirect_uri' => 'com.globalenglish.app:/oauth/callback',
            'code' => 'synthetic-authorization-code',
            'code_verifier' => Str::random(64),
            'installation_id' => (string) Str::ulid(),
        ]);
    }
}

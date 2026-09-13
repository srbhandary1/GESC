<?php

declare(strict_types=1);

namespace Tests\Unit\Authentication\Mobile;

use App\Authentication\Mobile\ApprovedNativeOAuthClientResolver;
use App\Authentication\Mobile\MobileOAuthClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApprovedNativeOAuthClientResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_only_approved_native_client_id(): void
    {
        $client = $this->createApprovedMobileClient();

        $resolved = $this->app
            ->make(ApprovedNativeOAuthClientResolver::class)
            ->resolveClientId();

        self::assertSame((string) $client->getKey(), $resolved);
    }

    public function test_it_stays_fail_closed_when_no_approved_native_client_exists(): void
    {
        self::assertNull(
            $this->app
                ->make(ApprovedNativeOAuthClientResolver::class)
                ->resolveClientId(),
        );
    }

    public function test_it_prefers_the_uniquely_named_closed_beta_client_when_multiple_exist(): void
    {
        $this->createApprovedMobileClient(['name' => 'Global English Android Local']);
        $closedBeta = $this->createApprovedMobileClient(['name' => 'Android closed-beta']);

        $resolved = $this->app
            ->make(ApprovedNativeOAuthClientResolver::class)
            ->resolveClientId();

        self::assertSame((string) $closedBeta->getKey(), $resolved);
    }

    public function test_it_stays_fail_closed_when_multiple_unnamed_duplicate_clients_exist(): void
    {
        $this->createApprovedMobileClient();
        $this->createApprovedMobileClient();

        self::assertNull(
            $this->app
                ->make(ApprovedNativeOAuthClientResolver::class)
                ->resolveClientId(),
        );
    }

    public function test_it_uses_a_configured_client_id_when_set(): void
    {
        $local = $this->createApprovedMobileClient(['name' => 'Global English Android Local']);
        $this->createApprovedMobileClient(['name' => 'Android closed-beta']);
        config(['authentication.mobile.native_oauth_client_id' => (string) $local->getKey()]);

        $resolved = $this->app
            ->make(ApprovedNativeOAuthClientResolver::class)
            ->resolveClientId();

        self::assertSame((string) $local->getKey(), $resolved);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createApprovedMobileClient(array $overrides = []): MobileOAuthClient
    {
        return MobileOAuthClient::query()->create([
            'name' => 'Global English Android resolver test client',
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
}

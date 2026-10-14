<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Mobile\MobileOAuthClient;
use App\Entitlements\DirectPlanDefaults;
use App\Models\AuthenticationSession;
use App\Models\User;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MobilePaidRegistrationTest extends TenancyIntegrationTestCase
{
    private MobileOAuthClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FoundationSeeder::class);
        config()->set('authentication.mode', 'password_only');
        config()->set('billing.driver', 'dummy');
        $this->configurePassportKeys();
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->client = MobileOAuthClient::query()->create([
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

    public function test_captured_payment_creates_the_account_and_a_mobile_token_family(): void
    {
        $before = User::query()->count();

        $issued = $this->postJson('/api/v1/auth/mobile/direct/paid-registrations', [
            'phone' => '74390 91943',
            'password' => 'Correct horse battery 2026!',
            'plan_id' => $this->planId(DirectPlanDefaults::MONTHLY_MODULES),
            'idempotency_key' => (string) Str::ulid(),
            'installation_id' => (string) Str::ulid(),
            'client_id' => $this->client->getKey(),
        ])->assertCreated();

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
        self::assertSame($before + 1, User::query()->count());

        $accessToken = $issued->json('access_token');
        self::assertIsString($accessToken);
        $this->withToken($accessToken)
            ->getJson('/api/v1/me')
            ->assertOk();
        self::assertSame('7439091943', User::query()->where('username', '7439091943')->value('username'));
        self::assertSame(
            DirectPlanDefaults::MONTHLY_MODULES,
            json_decode((string) DB::table('learner_entitlements')->where('status', 'active')->value('plan_snapshot'), true)['code'] ?? null,
        );
    }

    public function test_rejected_password_creates_no_account_and_no_tokens(): void
    {
        $before = User::query()->count();

        $this->postJson('/api/v1/auth/mobile/direct/paid-registrations', [
            'phone' => '9876543210',
            'password' => 'short',
            'plan_id' => $this->planId(DirectPlanDefaults::MONTHLY_MODULES),
            'idempotency_key' => (string) Str::ulid(),
            'installation_id' => (string) Str::ulid(),
            'client_id' => $this->client->getKey(),
        ])->assertUnprocessable();

        self::assertSame($before, User::query()->count());
        self::assertSame(0, DB::table('subscription_checkouts')->count());
    }

    private function planId(string $code): string
    {
        $this->getJson('/api/v1/direct/subscription-plans')->assertOk();
        $id = DB::table('entitlement_plans')->where('code', $code)->value('id');
        self::assertIsString($id);

        return $id;
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

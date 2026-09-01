<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Mobile\MobileTokenFamilyService;
use App\Models\MobileRefreshToken;
use App\Models\MobileTokenFamily;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Tests\TestCase;

final class MobileTokenFamilyTest extends TestCase
{
    use RefreshDatabase;

    public function test_rotation_is_single_use_and_replay_revokes_the_whole_family(): void
    {
        $user = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        $client = Client::query()->create([
            'name' => 'Global English Android test client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => ['com.globalenglish.app:/oauth/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ]);
        $installationId = (string) Str::ulid();
        $service = $this->app->make(MobileTokenFamilyService::class);

        [$firstAccessId, $firstRefreshId] = $this->insertPassportPair(
            $user,
            $client,
        );
        $service->trackIssuedTokens(
            'authorization_code',
            $installationId,
            $this->encodedAccessToken($firstAccessId),
            'first-refresh-secret',
            null,
        );

        $first = $service->lockRefreshToken('first-refresh-secret');
        self::assertInstanceOf(MobileRefreshToken::class, $first);
        self::assertTrue($service->validateRefresh($first, $installationId));

        [$secondAccessId, $secondRefreshId] = $this->insertPassportPair(
            $user,
            $client,
        );
        $service->trackIssuedTokens(
            'refresh_token',
            $installationId,
            $this->encodedAccessToken($secondAccessId),
            'second-refresh-secret',
            $first,
        );

        self::assertNotNull($first->fresh()?->rotated_at);
        self::assertNotSame(
            'second-refresh-secret',
            MobileRefreshToken::query()
                ->where('passport_refresh_token_id', $secondRefreshId)
                ->value('token_digest'),
        );

        $replayed = $service->lockRefreshToken('first-refresh-secret');
        self::assertInstanceOf(MobileRefreshToken::class, $replayed);
        self::assertFalse($service->validateRefresh($replayed, $installationId));

        $family = MobileTokenFamily::query()->firstOrFail();
        self::assertSame('refresh_token_reuse', $family->revocation_reason);
        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $firstAccessId,
            'revoked' => true,
        ]);
        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $secondAccessId,
            'revoked' => true,
        ]);
        $this->assertDatabaseHas('oauth_refresh_tokens', [
            'id' => $firstRefreshId,
            'revoked' => true,
        ]);
        $this->assertDatabaseHas('oauth_refresh_tokens', [
            'id' => $secondRefreshId,
            'revoked' => true,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'authentication.mobile_credential.reuse_detected',
            'subject_type' => 'mobile_credential_family',
            'subject_id' => $family->getKey(),
        ]);
    }

    public function test_password_sign_in_grant_creates_a_token_family(): void
    {
        $user = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        $client = Client::query()->create([
            'name' => 'Global English Android password family client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => ['com.globalenglish.app:/oauth/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ]);
        $installationId = (string) Str::ulid();
        $service = $this->app->make(MobileTokenFamilyService::class);
        [$accessTokenId] = $this->insertPassportPair($user, $client);

        $service->trackIssuedTokens(
            'password_sign_in',
            $installationId,
            $this->encodedAccessToken($accessTokenId),
            'password-sign-in-refresh-secret',
            null,
        );

        $refreshToken = $service->lockRefreshToken('password-sign-in-refresh-secret');
        self::assertInstanceOf(MobileRefreshToken::class, $refreshToken);
        self::assertTrue($service->validateRefresh($refreshToken, $installationId));
        $family = MobileTokenFamily::query()->findOrFail($refreshToken->family_id);
        self::assertSame((string) $user->getKey(), $family->user_id);
        self::assertSame(1, MobileTokenFamily::query()->count());
    }

    public function test_refresh_tracking_rejects_missing_passport_records(): void
    {
        $user = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        $client = Client::query()->create([
            'name' => 'Global English Android integrity test client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => ['com.globalenglish.app:/oauth/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ]);
        $family = MobileTokenFamily::query()->forceCreate([
            'user_id' => $user->getKey(),
            'client_id' => $client->getKey(),
            'installation_digest' => str_repeat('a', 64),
            'last_used_at' => now(),
            'absolute_expires_at' => now()->addDays(90),
        ]);

        try {
            DB::transaction(static function () use ($family): void {
                DB::table('mobile_refresh_tokens')->insert([
                    'id' => (string) Str::ulid(),
                    'family_id' => $family->getKey(),
                    'token_digest' => str_repeat('b', 64),
                    'passport_refresh_token_id' => Str::random(80),
                    'access_token_id' => Str::random(80),
                    'expires_at' => now()->addDays(30),
                    'rotated_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
            self::fail('Expected missing Passport credential records to violate foreign keys.');
        } catch (QueryException $exception) {
            self::assertSame('23503', $exception->getCode());
        }

        $this->assertDatabaseCount('mobile_refresh_tokens', 0);
    }

    public function test_refresh_validation_revokes_the_family_on_installation_mismatch(): void
    {
        [
            $service,
            $family,
            $refreshToken,
            $installationId,
            $accessTokenId,
            $passportRefreshTokenId,
        ] = $this->trackedFamily();
        $differentInstallationId = (string) Str::ulid();
        self::assertNotSame($installationId, $differentInstallationId);

        self::assertFalse(
            $service->validateRefresh(
                $refreshToken,
                $differentInstallationId,
            ),
        );
        self::assertSame(
            'installation_mismatch',
            $family->fresh()?->revocation_reason,
        );
        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $accessTokenId,
            'revoked' => true,
        ]);
        $this->assertDatabaseHas('oauth_refresh_tokens', [
            'id' => $passportRefreshTokenId,
            'revoked' => true,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'authentication.mobile_installation.mismatch',
            'subject_id' => $family->getKey(),
        ]);
    }

    public function test_refresh_validation_revokes_at_every_expiry_boundary(): void
    {
        [
            $inactivityService,
            $inactiveFamily,
            $inactiveRefresh,
            $inactiveInstallationId,
        ] = $this->trackedFamily();
        $inactiveFamily->forceFill([
            'last_used_at' => now()->subDays(30),
        ])->save();

        self::assertFalse(
            $inactivityService->validateRefresh(
                $inactiveRefresh,
                $inactiveInstallationId,
            ),
        );
        self::assertSame('expired', $inactiveFamily->fresh()?->revocation_reason);

        [
            $absoluteService,
            $absoluteFamily,
            $absoluteRefresh,
            $absoluteInstallationId,
        ] = $this->trackedFamily();
        $absoluteFamily->forceFill([
            'absolute_expires_at' => now(),
        ])->save();

        self::assertFalse(
            $absoluteService->validateRefresh(
                $absoluteRefresh,
                $absoluteInstallationId,
            ),
        );
        self::assertSame('expired', $absoluteFamily->fresh()?->revocation_reason);

        [
            $refreshService,
            $refreshFamily,
            $expiredRefresh,
            $refreshInstallationId,
        ] = $this->trackedFamily();
        $expiredRefresh->forceFill([
            'expires_at' => now(),
        ])->save();

        self::assertFalse(
            $refreshService->validateRefresh(
                $expiredRefresh,
                $refreshInstallationId,
            ),
        );
        self::assertSame('expired', $refreshFamily->fresh()?->revocation_reason);
        $this->assertDatabaseCount('audit_events', 3);
    }

    /**
     * @return array{
     *     MobileTokenFamilyService,
     *     MobileTokenFamily,
     *     MobileRefreshToken,
     *     string,
     *     string,
     *     string
     * }
     */
    private function trackedFamily(): array
    {
        $user = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        $client = Client::query()->create([
            'name' => 'Global English Android boundary test client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => ['com.globalenglish.app:/oauth/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ]);
        $installationId = (string) Str::ulid();
        $service = $this->app->make(MobileTokenFamilyService::class);
        [$accessTokenId, $passportRefreshTokenId] = $this->insertPassportPair(
            $user,
            $client,
        );
        $rawRefreshToken = 'refresh-'.Str::random(48);
        $service->trackIssuedTokens(
            'authorization_code',
            $installationId,
            $this->encodedAccessToken($accessTokenId),
            $rawRefreshToken,
            null,
        );
        $refreshToken = $service->lockRefreshToken($rawRefreshToken);
        self::assertInstanceOf(MobileRefreshToken::class, $refreshToken);
        $family = MobileTokenFamily::query()
            ->findOrFail($refreshToken->family_id);

        return [
            $service,
            $family,
            $refreshToken,
            $installationId,
            $accessTokenId,
            $passportRefreshTokenId,
        ];
    }

    /**
     * @return array{string, string}
     */
    private function insertPassportPair(User $user, Client $client): array
    {
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

        return [$accessId, $refreshId];
    }

    private function encodedAccessToken(string $identifier): string
    {
        $header = $this->base64Url(json_encode(['alg' => 'RS256'], JSON_THROW_ON_ERROR));
        $payload = $this->base64Url(json_encode(['jti' => $identifier], JSON_THROW_ON_ERROR));

        return $header.'.'.$payload.'.signature';
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

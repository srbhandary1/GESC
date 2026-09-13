<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Identity\OrganizationSelectionStore;
use App\Models\Membership;
use App\Models\MobileRefreshToken;
use App\Models\MobileTokenFamily;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\OrganizationContextManager;
use Closure;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use League\OAuth2\Server\ResourceServer;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class OrganizationContextMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private const string OTP = '246810';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FoundationSeeder::class);
        $this->app->instance(
            OtpCodeGenerator::class,
            new OrganizationContextMiddlewareOtpCodeGenerator(self::OTP),
        );
        $this->app->instance(
            OtpDeliveryAdapter::class,
            new OrganizationContextMiddlewareOtpDeliveryAdapter,
        );
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();

        Route::middleware([
            'api',
            'auth.first-party',
            InjectOrganizationSelectionForTest::class,
            'organization.context',
        ])->match(
            ['GET', 'POST'],
            '/api/v1/testing/organization-context-probe',
            static function (
                OrganizationContextManager $contexts,
            ): JsonResponse {
                $context = $contexts->current();

                return response()->json([
                    'organization_id' => $context->organizationId,
                    'membership_id' => $context->membershipId,
                    'visible_organization_ids' => Membership::query()
                        ->pluck('organization_id')
                        ->unique()
                        ->values()
                        ->all(),
                ])->header('Cache-Control', 'no-store');
            },
        );
    }

    public function test_http_middleware_activates_only_valid_membership_derived_context(): void
    {
        $user = $this->signIn();
        $directOrganization = Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->sole();
        $directMembership = DB::table('memberships')
            ->where('organization_id', $directOrganization->getKey())
            ->where('user_id', $user->getKey())
            ->sole(['id']);
        self::assertIsString($directMembership->id);

        $institutionId = (string) Str::ulid();
        $otherUserId = (string) Str::ulid();
        $otherMembershipId = (string) Str::ulid();
        $now = now();
        DB::table('organizations')->insert([
            'id' => $institutionId,
            'system_key' => null,
            'name' => 'Context Middleware Institution',
            'kind' => Organization::KIND_INSTITUTION,
            'status' => Organization::STATUS_ACTIVE,
            'timezone' => 'Asia/Kolkata',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('users')->insert([
            'id' => $otherUserId,
            'display_name' => null,
            'status' => User::STATUS_ACTIVE,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('memberships')->insert([
            'id' => $otherMembershipId,
            'organization_id' => $institutionId,
            'user_id' => $otherUserId,
            'membership_type' => Membership::TYPE_LEARNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $single = $this->withHeaders([
            'Origin' => 'https://localhost',
            'X-Organization-ID' => $institutionId,
        ])->postJson('/api/v1/testing/organization-context-probe', [
            'organization_id' => $institutionId,
            'membership_id' => $otherMembershipId,
        ])
            ->assertOk()
            ->assertJsonPath(
                'organization_id',
                $directOrganization->getKey(),
            )
            ->assertJsonPath('membership_id', $directMembership->id)
            ->assertJsonPath(
                'visible_organization_ids.0',
                $directOrganization->getKey(),
            )
            ->assertJsonCount(1, 'visible_organization_ids');
        self::assertStringContainsString(
            'no-store',
            (string) $single->headers->get('Cache-Control'),
        );
        $webSessionId = DB::table('authentication_sessions')
            ->where('user_id', $user->getKey())
            ->sole()
            ->id;
        self::assertIsString($webSessionId);
        $this->assertDatabaseHas('authentication_sessions', [
            'id' => $webSessionId,
            'selected_organization_id' => $directOrganization->getKey(),
            'selected_membership_id' => $directMembership->id,
        ]);
        $this->assertContextCleared();

        $institutionMembershipId = (string) Str::ulid();
        DB::table('memberships')->insert([
            'id' => $institutionMembershipId,
            'organization_id' => $institutionId,
            'user_id' => $user->getKey(),
            'membership_type' => Membership::TYPE_LEARNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->withHeader('Origin', 'https://localhost')
            ->getJson('/api/v1/testing/organization-context-probe')
            ->assertOk()
            ->assertJsonPath(
                'organization_id',
                $directOrganization->getKey(),
            )
            ->assertJsonPath('membership_id', $directMembership->id);
        $this->assertContextCleared();

        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => $institutionMembershipId,
            ])
            ->assertOk();
        $this->assertDatabaseHas('authentication_sessions', [
            'id' => $webSessionId,
            'selected_organization_id' => $institutionId,
            'selected_membership_id' => $institutionMembershipId,
        ]);

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'X-Organization-ID' => $directOrganization->getKey(),
        ])->postJson('/api/v1/testing/organization-context-probe', [
            'organization_id' => $directOrganization->getKey(),
            'membership_id' => $directMembership->id,
        ])
            ->assertOk()
            ->assertJsonPath('organization_id', $institutionId)
            ->assertJsonPath('membership_id', $institutionMembershipId)
            ->assertJsonPath(
                'visible_organization_ids.0',
                $institutionId,
            )
            ->assertJsonCount(1, 'visible_organization_ids');
        $this->assertContextCleared();

        DB::table('memberships')
            ->where('id', $institutionMembershipId)
            ->update([
                'status' => Membership::STATUS_SUSPENDED,
                'updated_at' => now(),
            ]);

        $this->withHeader('Origin', 'https://localhost')
            ->getJson('/api/v1/testing/organization-context-probe')
            ->assertForbidden()
            ->assertJsonPath('message_key', 'problem.forbidden');
        $this->assertContextCleared();

        DB::table('memberships')
            ->where('id', $institutionMembershipId)
            ->update([
                'status' => Membership::STATUS_ACTIVE,
                'updated_at' => now(),
            ]);

        $this->withHeaders([
            'Origin' => 'https://localhost',
            InjectOrganizationSelectionForTest::HEADER => $otherMembershipId,
        ])->getJson('/api/v1/testing/organization-context-probe')
            ->assertUnauthorized()
            ->assertJsonPath('message_key', 'problem.unauthenticated');
        $this->assertDatabaseHas('authentication_sessions', [
            'id' => $webSessionId,
            'selected_organization_id' => null,
            'selected_membership_id' => null,
        ]);
        $this->assertContextCleared();
    }

    public function test_mobile_selection_is_reconstructed_and_cannot_be_replaced_by_an_organization_header(): void
    {
        $user = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        $firstOrganizationId = $this->insertOrganization(
            'Mobile Context Institution A',
        );
        $secondOrganizationId = $this->insertOrganization(
            'Mobile Context Institution B',
        );
        $firstMembershipId = $this->insertMembership(
            $firstOrganizationId,
            (string) $user->getKey(),
        );
        $secondMembershipId = $this->insertMembership(
            $secondOrganizationId,
            (string) $user->getKey(),
        );
        $client = Client::query()->create([
            'name' => 'Global English Android context client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => ['com.globalenglish.app:/oauth/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ]);
        $accessTokenId = Str::random(80);
        $passportRefreshTokenId = Str::random(80);
        DB::table('oauth_access_tokens')->insert([
            'id' => $accessTokenId,
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
            'id' => $passportRefreshTokenId,
            'access_token_id' => $accessTokenId,
            'revoked' => false,
            'expires_at' => now()->addDays(30),
        ]);
        $family = MobileTokenFamily::query()->forceCreate([
            'user_id' => $user->getKey(),
            'client_id' => $client->getKey(),
            'selected_organization_id' => $secondOrganizationId,
            'selected_membership_id' => $secondMembershipId,
            'installation_digest' => str_repeat('a', 64),
            'last_used_at' => now(),
            'absolute_expires_at' => now()->addDays(90),
        ]);
        MobileRefreshToken::query()->forceCreate([
            'family_id' => $family->getKey(),
            'token_digest' => str_repeat('b', 64),
            'passport_refresh_token_id' => $passportRefreshTokenId,
            'access_token_id' => $accessTokenId,
            'expires_at' => now()->addDays(30),
        ]);
        $this->app->instance(
            ResourceServer::class,
            Mockery::mock(ResourceServer::class),
        );
        Passport::actingAs($user, ['mobile:api'], 'api', $client);
        $user->withAccessToken(new AccessToken([
            'oauth_access_token_id' => $accessTokenId,
            'oauth_client_id' => $client->getKey(),
            'oauth_user_id' => $user->getKey(),
            'oauth_scopes' => ['mobile:api'],
        ]));
        $this->withToken('synthetic-mobile-context-token');

        $this->withHeader('X-Organization-ID', $firstOrganizationId)
            ->getJson('/api/v1/testing/organization-context-probe')
            ->assertOk()
            ->assertJsonPath('organization_id', $secondOrganizationId)
            ->assertJsonPath('membership_id', $secondMembershipId)
            ->assertJsonPath(
                'visible_organization_ids.0',
                $secondOrganizationId,
            )
            ->assertJsonCount(1, 'visible_organization_ids');
        $this->assertContextCleared();

        $family->forceFill([
            'selected_organization_id' => null,
            'selected_membership_id' => null,
        ])->save();

        $this->getJson('/api/v1/testing/organization-context-probe')
            ->assertForbidden()
            ->assertJsonPath('message_key', 'problem.forbidden');
        $this->assertContextCleared();
        self::assertNotSame($firstMembershipId, $secondMembershipId);
    }

    private function signIn(): User
    {
        $issued = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => '+919876543219',
            ])
            ->assertAccepted();
        $challengeId = $issued->json('challenge_id');
        self::assertIsString($challengeId);

        $verified = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => '+919876543219',
                'code' => self::OTP,
            ])
            ->assertOk();
        $this->adoptSessionCookie($verified);

        return User::query()->sole();
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function adoptSessionCookie(TestResponse $response): void
    {
        $cookie = $response->getCookie((string) config('session.cookie'));
        self::assertNotNull($cookie);

        $this->withCookie($cookie->getName(), (string) $cookie->getValue());
    }

    private function assertContextCleared(): void
    {
        self::assertNull(
            $this->app->make(OrganizationContextManager::class)
                ->currentOrNull(),
        );
    }

    private function insertOrganization(string $name): string
    {
        $organizationId = (string) Str::ulid();
        $now = now();
        DB::table('organizations')->insert([
            'id' => $organizationId,
            'system_key' => null,
            'name' => $name,
            'kind' => Organization::KIND_INSTITUTION,
            'status' => Organization::STATUS_ACTIVE,
            'timezone' => 'Asia/Kolkata',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $organizationId;
    }

    private function insertMembership(
        string $organizationId,
        string $userId,
    ): string {
        $membershipId = (string) Str::ulid();
        $now = now();
        DB::table('memberships')->insert([
            'id' => $membershipId,
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'membership_type' => Membership::TYPE_LEARNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $membershipId;
    }
}

final class InjectOrganizationSelectionForTest
{
    /**
     * @var string
     */
    public const HEADER = 'X-Test-Selected-Membership';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $membershipId = $request->header(self::HEADER);

        if (is_string($membershipId)) {
            $request->session()->put(
                OrganizationSelectionStore::WEB_SESSION_KEY,
                $membershipId,
            );
        }

        return $next($request);
    }
}

final class OrganizationContextMiddlewareOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private readonly string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class OrganizationContextMiddlewareOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    public function send(string $normalizedMobileNumber, string $code): void {}
}

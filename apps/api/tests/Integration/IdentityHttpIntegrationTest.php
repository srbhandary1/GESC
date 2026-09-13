<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Models\InstitutionInvite;
use App\Models\Membership;
use App\Models\MobileRefreshToken;
use App\Models\MobileTokenFamily;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\InstitutionInviteCodeDigester;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use League\OAuth2\Server\ResourceServer;
use Mockery;
use Symfony\Component\HttpFoundation\Response;

final class IdentityHttpIntegrationTest extends TenancyIntegrationTestCase
{
    private const string OTP = '123456';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            OtpCodeGenerator::class,
            new IdentityHttpFixedOtpCodeGenerator(self::OTP),
        );
        $this->app->instance(
            OtpDeliveryAdapter::class,
            new IdentityHttpNullOtpDeliveryAdapter,
        );
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    public function test_unauthenticated_identity_request_fails_closed_without_resolving_the_mobile_guard(): void
    {
        config()->set('passport.private_key', null);
        config()->set('passport.public_key', null);

        $response = $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('message_key', 'problem.unauthenticated');

        $this->assertNoStore($response);
    }

    public function test_web_identity_requires_explicit_context_when_membership_is_ambiguous_and_persists_a_verified_selection(): void
    {
        $user = $this->signInWeb();

        $initial = $this->withHeader('Origin', 'https://localhost')
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->getKey())
            ->assertJsonPath(
                'current_membership.organization.kind',
                Organization::KIND_PLATFORM_DIRECT,
            )
            ->assertJsonPath(
                'current_membership.membership_type',
                Membership::TYPE_LEARNER,
            )
            ->assertJsonCount(1, 'memberships');
        $this->assertNoStore($initial);

        $institution = $this->createOrganization('HTTP Context Institution');
        [$institutionMembership] = $this->createMembership($institution, $user);
        $otherUser = $this->createUser();
        [$otherMembership] = $this->createMembership($institution, $otherUser);

        $this->withHeader('Origin', 'https://localhost')
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('current_membership', null)
            ->assertJsonCount(2, 'memberships');

        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => $institutionMembership->getKey(),
                'organization_id' => $institution->getKey(),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message_key', 'problem.validation_failed');

        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => $otherMembership->getKey(),
            ])
            ->assertNotFound()
            ->assertJsonPath('message_key', 'problem.not_found')
            ->assertJsonMissing([
                'membership_id' => $otherMembership->getKey(),
                'organization_id' => $institution->getKey(),
            ]);

        $selected = $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => $institutionMembership->getKey(),
            ])
            ->assertOk()
            ->assertJsonPath(
                'current_membership.id',
                $institutionMembership->getKey(),
            )
            ->assertJsonPath(
                'current_membership.organization.id',
                $institution->getKey(),
            );
        $this->assertNoStore($selected);

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'X-Organization-ID' => (string) Organization::query()
                ->where('kind', Organization::KIND_PLATFORM_DIRECT)
                ->value('id'),
        ])->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath(
                'current_membership.id',
                $institutionMembership->getKey(),
            );

        DB::table('memberships')
            ->where('id', $institutionMembership->getKey())
            ->update(['status' => Membership::STATUS_SUSPENDED]);

        $this->withHeader('Origin', 'https://localhost')
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('current_membership', null);

        DB::table('memberships')
            ->where('id', $institutionMembership->getKey())
            ->update(['status' => Membership::STATUS_ACTIVE]);

        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => $institutionMembership->getKey(),
            ])
            ->assertOk();

        DB::table('organizations')
            ->where('id', $institution->getKey())
            ->update(['status' => 'suspended']);

        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => $institutionMembership->getKey(),
            ])
            ->assertNotFound()
            ->assertJsonPath('message_key', 'problem.not_found');

        $this->withHeader('Origin', 'https://localhost')
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('current_membership', null)
            ->assertJsonCount(1, 'memberships');
    }

    public function test_mobile_identity_requires_scope_and_persists_context_in_the_active_token_family(): void
    {
        $user = $this->createUser();
        $firstOrganization = $this->createOrganization('Mobile Institution A');
        $secondOrganization = $this->createOrganization('Mobile Institution B');
        [$firstMembership] = $this->createMembership($firstOrganization, $user);
        [$secondMembership] = $this->createMembership($secondOrganization, $user);
        [$client, $family, $accessTokenId] = $this->actAsMobile($user);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('current_membership', null)
            ->assertJsonCount(2, 'memberships');

        $selected = $this->putJson('/api/v1/me/organization-context', [
            'membership_id' => $secondMembership->getKey(),
        ])
            ->assertOk()
            ->assertJsonPath(
                'current_membership.id',
                $secondMembership->getKey(),
            );
        $this->assertNoStore($selected);
        self::assertSame(
            $secondMembership->getKey(),
            $family->fresh()?->selected_membership_id,
        );

        $this->withHeader(
            'X-Organization-ID',
            (string) $firstOrganization->getKey(),
        )->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath(
                'current_membership.id',
                $secondMembership->getKey(),
            );

        $withoutScope = new AccessToken([
            'oauth_access_token_id' => $accessTokenId,
            'oauth_client_id' => $client->getKey(),
            'oauth_user_id' => $user->getKey(),
            'oauth_scopes' => [],
        ]);
        $user->withAccessToken($withoutScope);

        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('message_key', 'problem.unauthenticated');

        $withScope = new AccessToken([
            'oauth_access_token_id' => $accessTokenId,
            'oauth_client_id' => $client->getKey(),
            'oauth_user_id' => $user->getKey(),
            'oauth_scopes' => ['mobile:api'],
        ]);
        $user->withAccessToken($withScope);
        $family->forceFill([
            'revoked_at' => now(),
            'revocation_reason' => 'test_revocation',
        ])->save();

        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('message_key', 'problem.unauthenticated');

        self::assertNotSame(
            $firstMembership->getKey(),
            $family->fresh()?->selected_membership_id,
        );
    }

    public function test_invite_acceptance_is_replay_safe_binds_the_idempotency_key_and_never_persists_the_raw_code(): void
    {
        $user = $this->signInWeb('+919876543211');
        $organization = $this->createOrganization('HTTP Invite Institution');
        $issuer = $this->createUser();
        [, $issuerContext] = $this->createMembership(
            $organization,
            $issuer,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $role = $this->createRole($issuerContext, 'institution-learner');
        $cohort = $this->createCohort($issuerContext);
        $rawCode = 'GEI-HTTP-7F4G-9R2K';
        $invite = $this->createInvite(
            $rawCode,
            $organization,
            $issuerContext,
            $role->getKey(),
            $cohort->getKey(),
        );

        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/institution-invites/accept', [
                'code' => $rawCode,
            ])
            ->assertUnprocessable();

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => 'not-a-valid-key',
        ])->postJson('/api/v1/institution-invites/accept', [
            'code' => $rawCode,
        ])->assertUnprocessable();

        $idempotencyKey = (string) Str::ulid();
        $accepted = $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/institution-invites/accept', [
            'code' => $rawCode,
        ])
            ->assertCreated()
            ->assertJsonPath('membership.organization.id', $organization->getKey())
            ->assertJsonPath('membership.status', Membership::STATUS_ACTIVE)
            ->assertJsonPath('membership.role_keys.0', 'institution-learner')
            ->assertJsonPath('enrollment.status', 'pending_curriculum')
            ->assertJsonPath('replayed', false);
        $this->assertNoStore($accepted);

        $firstPayload = $accepted->json();
        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/institution-invites/accept', [
            'code' => $rawCode,
        ])
            ->assertOk()
            ->assertJsonPath('replayed', true)
            ->assertJsonPath(
                'membership.id',
                $firstPayload['membership']['id'],
            )
            ->assertJsonPath(
                'enrollment.id',
                $firstPayload['enrollment']['id'],
            );

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::ulid(),
        ])->postJson('/api/v1/institution-invites/accept', [
            'code' => $rawCode,
        ])
            ->assertConflict()
            ->assertJsonPath('message_key', 'problem.conflict')
            ->assertJsonMissing(['code' => $rawCode]);

        $storedInvite = DB::table('institution_invites')
            ->where('id', $invite->getKey())
            ->first();

        self::assertNotNull($storedInvite);
        self::assertSame($user->getKey(), $storedInvite->accepted_by_user_id);
        self::assertSame(
            $idempotencyKey,
            $storedInvite->accepted_idempotency_key,
        );
        self::assertNotSame(
            $rawCode,
            $storedInvite->accepted_request_fingerprint,
        );
        self::assertStringNotContainsString(
            $rawCode,
            json_encode($storedInvite, JSON_THROW_ON_ERROR),
        );
    }

    public function test_idempotency_key_reuse_with_another_invite_conflicts_without_binding_the_second_invite(): void
    {
        $user = $this->signInWeb('+919876543212');
        $organization = $this->createOrganization('Idempotency Institution');
        $issuer = $this->createUser();
        [, $issuerContext] = $this->createMembership(
            $organization,
            $issuer,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $firstCode = 'GEI-IDEMPOTENCY-FIRST';
        $secondCode = 'GEI-IDEMPOTENCY-SECOND';
        $firstInvite = $this->createInvite(
            $firstCode,
            $organization,
            $issuerContext,
        );
        $secondInvite = $this->createInvite(
            $secondCode,
            $organization,
            $issuerContext,
        );
        $idempotencyKey = (string) Str::ulid();

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/institution-invites/accept', [
            'code' => $firstCode,
        ])->assertCreated();

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/institution-invites/accept', [
            'code' => $secondCode,
        ])
            ->assertConflict()
            ->assertJsonPath('message_key', 'problem.conflict');

        $this->assertDatabaseHas('institution_invites', [
            'id' => $firstInvite->getKey(),
            'status' => InstitutionInvite::STATUS_ACCEPTED,
            'accepted_idempotency_key' => $idempotencyKey,
        ]);
        $this->assertDatabaseHas('institution_invites', [
            'id' => $secondInvite->getKey(),
            'status' => InstitutionInvite::STATUS_PENDING,
            'accepted_idempotency_key' => null,
            'accepted_request_fingerprint' => null,
        ]);
    }

    public function test_duplicate_invite_code_across_organizations_and_unavailable_codes_fail_with_the_same_non_enumerating_response(): void
    {
        $this->signInWeb('+919876543213');
        $duplicateCode = 'GEI-DUPLICATE-CODE-01';

        foreach (['Duplicate Institution A', 'Duplicate Institution B'] as $name) {
            $organization = $this->createOrganization($name);
            $issuer = $this->createUser();
            [, $context] = $this->createMembership(
                $organization,
                $issuer,
                Membership::TYPE_ORGANIZATION_ADMIN,
            );
            $this->createInvite($duplicateCode, $organization, $context);
        }

        $inactiveOrganization = $this->createOrganization('Inactive Institution');
        $inactiveIssuer = $this->createUser();
        [, $inactiveContext] = $this->createMembership(
            $inactiveOrganization,
            $inactiveIssuer,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $inactiveCode = 'GEI-INACTIVE-CODE-001';
        $this->createInvite(
            $inactiveCode,
            $inactiveOrganization,
            $inactiveContext,
        );
        DB::table('organizations')
            ->where('id', $inactiveOrganization->getKey())
            ->update(['status' => 'suspended']);

        $duplicate = $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::ulid(),
        ])->postJson('/api/v1/institution-invites/accept', [
            'code' => $duplicateCode,
        ])
            ->assertNotFound()
            ->assertJsonPath('message_key', 'problem.not_found');

        $forged = $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::ulid(),
        ])->postJson('/api/v1/institution-invites/accept', [
            'code' => 'GEI-FORGED-CODE-0001',
        ])
            ->assertNotFound()
            ->assertJsonPath('message_key', 'problem.not_found');

        $inactive = $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::ulid(),
        ])->postJson('/api/v1/institution-invites/accept', [
            'code' => $inactiveCode,
        ])
            ->assertNotFound()
            ->assertJsonPath('message_key', 'problem.not_found');

        self::assertSame($duplicate->json('title'), $forged->json('title'));
        self::assertSame($duplicate->json('detail'), $forged->json('detail'));
        self::assertSame(
            $duplicate->json('message_key'),
            $forged->json('message_key'),
        );
        self::assertSame($forged->json('detail'), $inactive->json('detail'));
        $this->assertDatabaseMissing('institution_invites', [
            'status' => InstitutionInvite::STATUS_ACCEPTED,
        ]);
    }

    private function signInWeb(
        string $mobileNumber = '+919876543210',
    ): User {
        $issued = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => $mobileNumber,
            ])
            ->assertAccepted();
        $challengeId = $issued->json('challenge_id');
        self::assertIsString($challengeId);

        $verified = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => $mobileNumber,
                'code' => self::OTP,
            ])
            ->assertOk();
        $this->adoptSessionCookie($verified);

        return User::query()->sole();
    }

    /**
     * @return array{Client, MobileTokenFamily, string}
     */
    private function actAsMobile(User $user): array
    {
        $client = Client::query()->create([
            'name' => 'Global English Android identity HTTP client',
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
            'selected_organization_id' => null,
            'selected_membership_id' => null,
            'installation_digest' => hash('sha256', 'synthetic-installation'),
            'last_used_at' => now(),
            'absolute_expires_at' => now()->addDays(90),
        ]);
        MobileRefreshToken::query()->forceCreate([
            'family_id' => $family->getKey(),
            'token_digest' => hash('sha256', 'synthetic-refresh-token'),
            'passport_refresh_token_id' => $passportRefreshTokenId,
            'access_token_id' => $accessTokenId,
            'expires_at' => now()->addDays(30),
        ]);

        $this->app->instance(
            ResourceServer::class,
            Mockery::mock(ResourceServer::class),
        );
        Passport::actingAs($user, ['mobile:api'], 'api', $client);
        $this->withToken('synthetic-mobile-access-token');
        $user->withAccessToken(new AccessToken([
            'oauth_access_token_id' => $accessTokenId,
            'oauth_client_id' => $client->getKey(),
            'oauth_user_id' => $user->getKey(),
            'oauth_scopes' => ['mobile:api'],
        ]));

        return [$client, $family, $accessTokenId];
    }

    private function createInvite(
        string $rawCode,
        Organization $organization,
        OrganizationContext $context,
        ?string $roleId = null,
        ?string $cohortId = null,
    ): InstitutionInvite {
        $now = now();

        if ($roleId === null) {
            $roleId = (string) Str::ulid();
            DB::table('roles')->insert([
                'id' => $roleId,
                'organization_id' => $organization->getKey(),
                'key' => 'learner-safe-'.Str::lower((string) Str::ulid()),
                'name' => 'Learner',
                'is_system' => false,
                'status' => 'active',
                'version' => 1,
                'retired_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('role_permissions')->insertOrIgnore([
            'organization_id' => $organization->getKey(),
            'role_id' => $roleId,
            'permission_id' => DB::table('permissions')
                ->where('key', 'learning.participate')
                ->value('id'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->contexts()->run(
            $context,
            fn (): InstitutionInvite => InstitutionInvite::query()->forceCreate([
                'code_digest' => $this->app
                    ->make(InstitutionInviteCodeDigester::class)
                    ->digest($rawCode),
                'membership_type' => Membership::TYPE_LEARNER,
                'role_id' => $roleId,
                'cohort_id' => $cohortId,
                'status' => InstitutionInvite::STATUS_PENDING,
                'expires_at' => now()->addDay(),
            ]),
        );
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

    /**
     * @param  TestResponse<Response>  $response
     */
    private function assertNoStore(TestResponse $response): void
    {
        self::assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
    }
}

final class IdentityHttpFixedOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private readonly string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class IdentityHttpNullOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    public function send(string $normalizedMobileNumber, string $code): void
    {
        //
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Entitlements\DirectPlanDefaults;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

final class EntitlementAdministrationApiTest extends TenancyIntegrationTestCase
{
    private const string OTP = '864209';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            OtpCodeGenerator::class,
            new EntitlementAdministrationOtpCodeGenerator(self::OTP),
        );
        $this->app->instance(
            OtpDeliveryAdapter::class,
            new EntitlementAdministrationNullOtpDeliveryAdapter,
        );
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    public function test_entitlement_administration_is_tenant_scoped_and_replay_safe(): void
    {
        $actor = $this->signInWeb('+919100000031');
        $organization = $this->createOrganization('Entitlement Administration');
        $foreignOrganization = $this->createOrganization('Foreign Entitlements');
        [$actorMembership, $actorContext] = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $actorMembership,
            $actorContext,
            [
                'entitlement_plans.read',
                'entitlement_plans.manage',
                'entitlements.read',
                'entitlements.grant',
                'entitlements.adjust',
                'entitlements.revoke',
            ],
            'entitlement-administrator',
        );
        $learnerUser = $this->createUser();
        [$learnerMembership] = $this->createMembership(
            $organization,
            $learnerUser,
            Membership::TYPE_LEARNER,
        );
        $foreignUser = $this->createUser();
        [$foreignMembership] = $this->createMembership(
            $foreignOrganization,
            $foreignUser,
            Membership::TYPE_LEARNER,
        );
        $this->selectMembership($actorMembership);

        $overview = $this->getJson('/api/v1/operator/overview')
            ->assertOk()
            ->assertJsonPath('navigation', ['overview', 'entitlements']);
        $this->assertNoStore($overview);

        $workspace = $this->getJson('/api/v1/operator/entitlement-workspace')
            ->assertOk()
            ->assertJsonPath('tax.gstin', DirectPlanDefaults::PLACEHOLDER_GSTIN)
            ->assertJsonPath('tax.gstin_is_placeholder', true)
            ->assertJsonPath('tax.gst_rate_bps', 1800);
        $this->assertNoStore($workspace);
        $plans = $workspace->json('plans');
        self::assertIsArray($plans);
        $planId = collect($plans)
            ->firstWhere('code', DirectPlanDefaults::MONTHLY_ROLEPLAY)['id'] ?? null;
        self::assertIsString($planId);

        $this->getJson('/api/v1/operator/learners/'.$foreignMembership->getKey().'/entitlements')
            ->assertNotFound();

        $taxKey = (string) Str::ulid();
        $updated = $this->postCommand(
            '/api/v1/operator/tax-settings/updates',
            [
                'gst_registered' => true,
                'gstin' => '29ABCDE1234F1Z5',
                'gst_rate_bps' => 1800,
                'expected_version' => 1,
            ],
            $taxKey,
        )
            ->assertOk()
            ->assertJsonPath('replayed', false)
            ->assertJsonPath('data.workspace.tax.gstin', '29ABCDE1234F1Z5')
            ->assertJsonPath('data.workspace.tax.gstin_is_placeholder', false);
        $this->assertNoStore($updated);
        $this->postCommand(
            '/api/v1/operator/tax-settings/updates',
            [
                'gst_registered' => true,
                'gstin' => '29ABCDE1234F1Z5',
                'gst_rate_bps' => 1800,
                'expected_version' => 1,
            ],
            $taxKey,
        )
            ->assertOk()
            ->assertJsonPath('replayed', true)
            ->assertJsonPath('data.workspace.tax.version', 2);
        self::assertSame(1, DB::table('organization_tax_settings')
            ->where('organization_id', $organization->getKey())
            ->count());

        $grantKey = (string) Str::ulid();
        $granted = $this->postCommand(
            '/api/v1/operator/learners/'.$learnerMembership->getKey().'/entitlement-grants',
            ['plan_id' => $planId],
            $grantKey,
        )
            ->assertCreated()
            ->assertJsonPath('replayed', false)
            ->assertJsonPath('data.result_type', 'entitlement');
        $this->assertNoStore($granted);
        $this->postCommand(
            '/api/v1/operator/learners/'.$learnerMembership->getKey().'/entitlement-grants',
            ['plan_id' => $planId],
            $grantKey,
        )
            ->assertOk()
            ->assertJsonPath('replayed', true);
        self::assertSame(1, DB::table('learner_entitlements')
            ->where('learner_membership_id', $learnerMembership->getKey())
            ->where('status', 'active')
            ->count());

        $this->postCommand(
            '/api/v1/operator/courtesy-premium-grants',
            [],
            (string) Str::ulid(),
        )->assertNotFound();
    }

    public function test_direct_courtesy_premium_grant_is_replay_safe(): void
    {
        $actor = $this->signInWeb('+919100000032');
        $organization = $this->createOrganization('Direct Courtesy');
        $organization->forceFill(['kind' => Organization::KIND_PLATFORM_DIRECT])->save();
        [$actorMembership, $actorContext] = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $actorMembership,
            $actorContext,
            [
                'entitlement_plans.read',
                'entitlement_plans.manage',
                'entitlements.read',
                'entitlements.grant',
                'entitlements.adjust',
                'entitlements.revoke',
            ],
            'direct-courtesy-administrator',
        );
        $learnerUser = $this->createUser();
        [$learnerMembership] = $this->createMembership(
            $organization,
            $learnerUser,
            Membership::TYPE_LEARNER,
        );
        $this->selectMembership($actorMembership);

        $key = (string) Str::ulid();
        $this->postCommand('/api/v1/operator/courtesy-premium-grants', [], $key)
            ->assertOk()
            ->assertJsonPath('replayed', false)
            ->assertJsonPath('data.result_type', 'courtesy_premium_batch')
            ->assertJsonPath('data.workspace.courtesy_premium.granted_memberships', 1);
        $this->postCommand('/api/v1/operator/courtesy-premium-grants', [], $key)
            ->assertOk()
            ->assertJsonPath('replayed', true);
        self::assertSame(DirectPlanDefaults::MONTHLY_ROLEPLAY, DB::table('learner_entitlements')
            ->join('entitlement_plans', 'learner_entitlements.plan_id', '=', 'entitlement_plans.id')
            ->where('learner_entitlements.learner_membership_id', $learnerMembership->getKey())
            ->where('learner_entitlements.status', 'active')
            ->value('entitlement_plans.code'));
    }

    private function signInWeb(string $mobileNumber): User
    {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        Auth::forgetGuards();

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
        $cookie = $verified->getCookie((string) config('session.cookie'));
        self::assertNotNull($cookie);
        $this->withCookie($cookie->getName(), (string) $cookie->getValue());
        $userId = $verified->json('user.id');
        self::assertIsString($userId);

        return User::query()->whereKey($userId)->sole();
    }

    private function selectMembership(Membership $membership): void
    {
        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => $membership->getKey(),
            ])
            ->assertOk();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    private function postCommand(
        string $uri,
        array $body,
        string $idempotencyKey,
    ): TestResponse {
        return $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->withHeader('Origin', 'https://localhost')
            ->postJson($uri, $body);
    }

    /** @param TestResponse<Response> $response */
    private function assertNoStore(TestResponse $response): void
    {
        self::assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
    }
}

final class EntitlementAdministrationOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private readonly string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class EntitlementAdministrationNullOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    public function send(string $normalizedMobileNumber, string $code): void {}
}

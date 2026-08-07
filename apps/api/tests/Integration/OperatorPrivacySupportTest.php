<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class OperatorPrivacySupportTest extends TenancyIntegrationTestCase
{
    private const string OTP = '246813';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            OtpCodeGenerator::class,
            new OperatorPrivacySupportOtpCodeGenerator(self::OTP),
        );
        $this->app->instance(
            OtpDeliveryAdapter::class,
            new OperatorPrivacySupportNullOtpDeliveryAdapter,
        );
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    public function test_operator_privacy_support_is_tenant_isolated(): void
    {
        $user = $this->signInWeb('+919876543221');
        $organization = $this->createOrganization('Privacy Ops Tenant');
        $foreign = $this->createOrganization('Privacy Foreign Tenant');

        [$operatorMembership, $operatorContext] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->attachOperatorPermissions(
            $operatorMembership,
            $operatorContext,
            [
                'support.diagnostics.read',
                'privacy.export.request',
                'privacy.erasure.request',
            ],
        );

        $learner = $this->createUser();
        [$learnerMembership] = $this->createMembership(
            $organization,
            $learner,
            Membership::TYPE_LEARNER,
        );

        $foreignLearner = $this->createUser();
        [$foreignMembership] = $this->createMembership(
            $foreign,
            $foreignLearner,
            Membership::TYPE_LEARNER,
        );

        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => $operatorMembership->getKey(),
            ])
            ->assertOk();

        $diagnostics = $this->getJson(
            '/api/v1/operator/support/diagnostics',
        )
            ->assertOk()
            ->assertJsonStructure([
                'generated_at',
                'items',
            ]);
        $this->assertNoStore($diagnostics);

        $exportKey = (string) Str::uuid();
        $export = $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => $exportKey,
        ])->postJson('/api/v1/operator/privacy/exports', [
            'target_membership_id' => $learnerMembership->getKey(),
        ])
            ->assertCreated()
            ->assertJsonPath(
                'target_membership_id',
                $learnerMembership->getKey(),
            )
            ->assertJsonPath(
                'summary.content_note',
                'identifier_digests_only_no_audio_or_transcripts',
            )
            ->assertJsonPath('status', 'completed');
        $this->assertNoStore($export);
        $exportId = $export->json('export_request_id');
        self::assertIsString($exportId);

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => $exportKey,
        ])->postJson('/api/v1/operator/privacy/exports', [
            'target_membership_id' => $learnerMembership->getKey(),
        ])
            ->assertCreated()
            ->assertJsonPath('export_request_id', $exportId);

        $this->getJson('/api/v1/operator/privacy/exports/'.$exportId)
            ->assertOk()
            ->assertJsonPath('export_request_id', $exportId);

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/v1/operator/privacy/exports', [
            'target_membership_id' => $foreignMembership->getKey(),
        ])->assertNotFound();

        $erasureKey = (string) Str::uuid();
        $erasure = $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => $erasureKey,
        ])->postJson('/api/v1/operator/privacy/erasures', [
            'target_membership_id' => $learnerMembership->getKey(),
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('voice_recording_count', 0);
        $this->assertNoStore($erasure);
        $erasureId = $erasure->json('erasure_request_id');
        self::assertIsString($erasureId);

        $this->getJson('/api/v1/operator/privacy/erasures/'.$erasureId)
            ->assertOk()
            ->assertJsonPath('erasure_request_id', $erasureId);

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/v1/operator/privacy/erasures', [
            'target_membership_id' => $foreignMembership->getKey(),
        ])->assertNotFound();

        $this->assertDatabaseHas('audit_events', [
            'organization_id' => $organization->getKey(),
            'action' => 'privacy.export.requested',
            'subject_id' => $exportId,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'organization_id' => $organization->getKey(),
            'action' => 'privacy.erasure.requested',
            'subject_id' => $erasureId,
        ]);
        $this->assertDatabaseMissing('privacy_export_requests', [
            'organization_id' => $foreign->getKey(),
        ]);
    }

    public function test_missing_privacy_permission_is_not_found(): void
    {
        $user = $this->signInWeb('+919876543222');
        $organization = $this->createOrganization('Privacy Denied Tenant');
        [$membership] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );

        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => $membership->getKey(),
            ])
            ->assertOk();

        $this->getJson('/api/v1/operator/support/diagnostics')
            ->assertNotFound();
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

    /**
     * @param  list<string>  $permissionKeys
     */
    private function attachOperatorPermissions(
        Membership $membership,
        OrganizationContext $context,
        array $permissionKeys,
    ): void {
        $role = $this->createRole(
            $context,
            'privacy-'.strtolower((string) Str::ulid()),
        );
        $now = now();
        DB::table('membership_roles')->insert([
            'organization_id' => $membership->organizationId(),
            'membership_id' => $membership->getKey(),
            'role_id' => $role->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($permissionKeys as $permissionKey) {
            $permission = Permission::query()
                ->where('key', $permissionKey)
                ->first();

            if (! $permission instanceof Permission) {
                throw new RuntimeException(sprintf(
                    'Missing synthetic test permission: %s.',
                    $permissionKey,
                ));
            }

            DB::table('role_permissions')->insert([
                'organization_id' => $membership->organizationId(),
                'role_id' => $role->getKey(),
                'permission_id' => $permission->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
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

final class OperatorPrivacySupportOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private readonly string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class OperatorPrivacySupportNullOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    public function send(
        string $normalizedMobileNumber,
        string $code,
    ): void {
    }
}

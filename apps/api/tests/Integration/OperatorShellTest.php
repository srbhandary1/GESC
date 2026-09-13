<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

final class OperatorShellTest extends TenancyIntegrationTestCase
{
    private const string OTP = '246813';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            OtpCodeGenerator::class,
            new OperatorShellOtpCodeGenerator(self::OTP),
        );
        $this->app->instance(
            OtpDeliveryAdapter::class,
            new OperatorShellNullOtpDeliveryAdapter,
        );
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    public function test_overview_search_and_audit_paging_are_tenant_isolated(): void
    {
        $user = $this->signInWeb('+919876543224');
        $organization = $this->createOrganization('Shell Primary Tenant');
        $foreignOrganization = $this->createOrganization(
            'Shell Foreign Tenant',
        );
        [$membership, $context] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        [$foreignMembership, $foreignContext] = $this->createMembership(
            $foreignOrganization,
            $user,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $permissions = [
            'curriculum.author',
            'audit.read',
            'support.diagnostics.read',
        ];
        $this->grantTestPermissions(
            $membership,
            $context,
            $permissions,
            'operator-shell-primary',
        );
        $this->grantTestPermissions(
            $foreignMembership,
            $foreignContext,
            $permissions,
            'operator-shell-foreign',
        );
        $firstVersionId = $this->insertProgramVersion(
            $organization,
            $membership,
            'shell-alpha',
            'Shell Alpha Programme',
            'v1',
        );
        $secondVersionId = $this->insertProgramVersion(
            $organization,
            $membership,
            'shell-beta',
            'Shell Beta Programme',
            'v1',
        );
        $foreignVersionId = $this->insertProgramVersion(
            $foreignOrganization,
            $foreignMembership,
            'shell-foreign',
            'Shell Foreign Programme',
            'v1',
        );
        $newerAuditId = $this->insertAuditEvent(
            $organization,
            $user,
            $membership,
            'curriculum.publication.activated',
            now(),
        );
        $olderAuditId = $this->insertAuditEvent(
            $organization,
            $user,
            $membership,
            'curriculum.content_revision.appended',
            now()->subMinute(),
        );
        $foreignAuditId = $this->insertAuditEvent(
            $foreignOrganization,
            $user,
            $foreignMembership,
            'curriculum.publication.activated',
            now()->addMinute(),
        );

        $this->selectMembership($membership);

        $overview = $this->getJson('/api/v1/operator/overview')
            ->assertOk()
            ->assertJsonPath('organization.id', $organization->getKey())
            ->assertJsonPath('organization.name', 'Shell Primary Tenant')
            ->assertJsonPath('organization.timezone', 'Asia/Kolkata')
            ->assertJsonPath('navigation', [
                'overview',
                'learning',
                'operations',
                'safety',
            ])
            ->assertJsonPath('panels', [
                'pilot_analytics',
                'content_studio',
                'review_queue',
                'publish_status',
                'support_diagnostics',
                'audit',
            ])
            ->assertJsonMissing(['permissions']);
        $this->assertNoStore($overview);

        $firstSearch = $this->getJson(
            '/api/v1/operator/search?q=shell&limit=1',
        )
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.kind', 'curriculum_version')
            ->assertJsonPath('items.0.id', $firstVersionId)
            ->assertJsonPath('items.0.section', 'learning')
            ->assertJsonMissing(['permissions'])
            ->assertJsonMissing(['transcript'])
            ->assertJsonMissing(['payload']);
        $this->assertNoStore($firstSearch);
        $searchCursor = $firstSearch->json('next_cursor');
        self::assertIsString($searchCursor);
        self::assertNotSame('', $searchCursor);

        $secondSearch = $this->getJson(sprintf(
            '/api/v1/operator/search?q=shell&limit=1&cursor=%s',
            rawurlencode($searchCursor),
        ))
            ->assertOk()
            ->assertJsonPath('items.0.id', $secondVersionId);
        self::assertNotSame(
            $firstSearch->json('items.0.id'),
            $secondSearch->json('items.0.id'),
        );
        self::assertNotSame(
            $foreignVersionId,
            $secondSearch->json('items.0.id'),
        );

        $auditSearch = $this->getJson(
            '/api/v1/operator/search?q=curriculum.publication.activated',
        )
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $newerAuditId)
            ->assertJsonPath('items.0.kind', 'audit_event');
        self::assertNotSame(
            $foreignAuditId,
            $auditSearch->json('items.0.id'),
        );

        $this->getJson(sprintf(
            '/api/v1/operator/search?q=different&cursor=%s',
            rawurlencode($searchCursor),
        ))->assertUnprocessable();

        $auditPage = $this->getJson(
            '/api/v1/operator/audit-events?limit=1',
        )
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $newerAuditId);
        $auditCursor = $auditPage->json('next_cursor');
        self::assertIsString($auditCursor);

        $nextAuditPage = $this->getJson(sprintf(
            '/api/v1/operator/audit-events?limit=1&cursor=%s',
            rawurlencode($auditCursor),
        ))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $olderAuditId)
            ->assertJsonPath('next_cursor', null);
        self::assertNotSame(
            $auditPage->json('items.0.id'),
            $nextAuditPage->json('items.0.id'),
        );
        self::assertNotSame(
            $foreignAuditId,
            $nextAuditPage->json('items.0.id'),
        );

        $this->selectMembership($foreignMembership);
        $this->getJson(sprintf(
            '/api/v1/operator/search?q=shell&cursor=%s',
            rawurlencode($searchCursor),
        ))->assertUnprocessable();
        $this->getJson(sprintf(
            '/api/v1/operator/audit-events?cursor=%s',
            rawurlencode($auditCursor),
        ))->assertUnprocessable();
        $this->getJson(
            '/api/v1/operator/search?q=shell&cursor=not-encrypted',
        )->assertUnprocessable();
        $this->getJson(
            '/api/v1/operator/audit-events?cursor=not-encrypted',
        )->assertUnprocessable();
    }

    public function test_overview_and_search_omit_unauthorized_sources(): void
    {
        $user = $this->signInWeb('+919876543225');
        $organization = $this->createOrganization('Shell Audit Tenant');
        [$membership, $context] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_TRAINER,
        );
        $this->grantTestPermissions(
            $membership,
            $context,
            ['audit.read'],
            'operator-shell-audit',
        );
        $this->insertProgramVersion(
            $organization,
            $membership,
            'hidden-shell-content',
            'Hidden Shell Content',
            'v1',
        );
        $auditId = $this->insertAuditEvent(
            $organization,
            $user,
            $membership,
            'curriculum.publication.activated',
            now(),
        );
        $this->selectMembership($membership);

        $this->getJson('/api/v1/operator/overview')
            ->assertOk()
            ->assertJsonPath('navigation', ['overview', 'safety'])
            ->assertJsonPath('panels', ['pilot_analytics', 'audit']);
        $this->getJson('/api/v1/operator/search?q=Hidden')
            ->assertOk()
            ->assertJsonPath('items', [])
            ->assertJsonPath('next_cursor', null);
        $this->getJson(
            '/api/v1/operator/search?q=curriculum.publication.activated',
        )
            ->assertOk()
            ->assertJsonPath('items.0.id', $auditId);
    }

    public function test_shell_reads_fail_closed_without_atomic_permission(): void
    {
        $user = $this->signInWeb('+919876543226');
        $organization = $this->createOrganization('Shell Denied Tenant');
        [$membership] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->selectMembership($membership);

        $this->getJson('/api/v1/operator/overview')->assertNotFound();
        $this->getJson('/api/v1/operator/search?q=shell')->assertNotFound();
        $this->getJson('/api/v1/operator/search?q=x')
            ->assertUnprocessable();
        $this->getJson('/api/v1/operator/search?q=shell&unexpected=true')
            ->assertUnprocessable();
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

    private function insertProgramVersion(
        Organization $organization,
        Membership $creator,
        string $programKey,
        string $name,
        string $versionKey,
    ): string {
        $programId = (string) Str::ulid();
        $versionId = (string) Str::ulid();
        $now = now();

        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organization->getKey(),
            'key' => $programKey,
            'internal_name' => $name,
            'created_by_membership_id' => $creator->getKey(),
            'created_by_attribution' => 'operator-shell-test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_versions')->insert([
            'id' => $versionId,
            'organization_id' => $organization->getKey(),
            'program_id' => $programId,
            'key' => $versionKey,
            'created_by_membership_id' => $creator->getKey(),
            'created_by_attribution' => 'operator-shell-test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $versionId;
    }

    private function insertAuditEvent(
        Organization $organization,
        User $actor,
        Membership $membership,
        string $action,
        mixed $occurredAt,
    ): string {
        $auditId = (string) Str::ulid();

        DB::table('audit_events')->insert([
            'id' => $auditId,
            'scope_kind' => 'organization',
            'organization_id' => $organization->getKey(),
            'actor_user_id' => $actor->getKey(),
            'actor_membership_id' => $membership->getKey(),
            'target_organization_id' => null,
            'capability_key' => null,
            'reason_key' => null,
            'action' => $action,
            'subject_type' => 'curriculum_version',
            'subject_id' => (string) Str::ulid(),
            'correlation_id' => (string) Str::uuid(),
            'occurred_at' => $occurredAt,
        ]);

        return $auditId;
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

final class OperatorShellOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private readonly string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class OperatorShellNullOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    public function send(
        string $normalizedMobileNumber,
        string $code,
    ): void {
        // Test-only null delivery.
    }
}

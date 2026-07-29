<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Audit\AuditEventRecorder;
use App\Authentication\Mobile\MobileTokenFamilyService;
use App\Models\Membership;
use App\Models\MobileTokenFamily;
use App\Tenancy\Exceptions\UnauthorizedPlatformOperation;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextSource;
use App\Tenancy\PlatformCapability;
use App\Tenancy\PlatformOperationAuthorization;
use App\Tenancy\PlatformOperationAuthorizer;
use App\Tenancy\PlatformOperationExecutor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Passport\Client;
use RuntimeException;

final class PlatformOperationAuthorizationTest extends TenancyIntegrationTestCase
{
    public function test_authorized_platform_context_is_bound_to_capability_reason_organization_and_safe_audit(): void
    {
        $organization = $this->createOrganization('Platform Operation Tenant');
        $actor = $this->createUser();
        $targetUser = $this->createUser();
        $correlationId = (string) Str::uuid();
        $authorization = $this->app
            ->make(PlatformOperationAuthorizer::class)
            ->authorizeFoundationTenantBootstrap(
                organization: $organization,
                actor: $actor,
                reasonKey: 'test.fixture.membership_create',
                correlationId: $correlationId,
            );

        $membership = $this->app
            ->make(PlatformOperationExecutor::class)
            ->run(
                $authorization,
                static fn (): Membership => Membership::query()->forceCreate([
                    'user_id' => $targetUser->getKey(),
                    'membership_type' => Membership::TYPE_LEARNER,
                    'status' => Membership::STATUS_ACTIVE,
                    'joined_at' => Date::now(),
                ]),
            );

        self::assertSame(
            $organization->getKey(),
            $membership->organizationId(),
        );
        self::assertNull($this->contexts()->currentOrNull());
        $this->assertDatabaseHas('audit_events', [
            'scope_kind' => 'platform',
            'organization_id' => null,
            'target_organization_id' => $organization->getKey(),
            'actor_user_id' => $actor->getKey(),
            'actor_membership_id' => null,
            'capability_key' => PlatformCapability::FoundationTenantBootstrap->value,
            'reason_key' => 'test.fixture.membership_create',
            'action' => 'platform.foundation_tenant.bootstrap',
            'subject_type' => 'organization',
            'subject_id' => $organization->getKey(),
            'correlation_id' => $correlationId,
        ]);

        $auditEvidence = DB::table('audit_events')->sole();
        $serializedEvidence = json_encode(
            $auditEvidence,
            JSON_THROW_ON_ERROR,
        );
        self::assertStringNotContainsString(
            'learner@example.test',
            $serializedEvidence,
        );
        self::assertStringNotContainsString(
            'raw learner profile',
            $serializedEvidence,
        );
    }

    public function test_missing_or_unapproved_platform_reason_fails_before_audit(): void
    {
        $organization = $this->createOrganization('Reason Guard Tenant');
        $actor = $this->createUser();
        $authorizer = $this->app->make(PlatformOperationAuthorizer::class);

        foreach (['', 'support.ticket.contains-personal-data'] as $reason) {
            try {
                $authorizer->authorizeFoundationTenantBootstrap(
                    organization: $organization,
                    actor: $actor,
                    reasonKey: $reason,
                );
                self::fail('An unapproved platform-operation reason must fail closed.');
            } catch (UnauthorizedPlatformOperation) {
                self::assertDatabaseCount('audit_events', 0);
            }
        }
    }

    public function test_forged_platform_authorization_and_direct_context_construction_fail_closed(): void
    {
        $organization = $this->createOrganization('Forgery Guard Tenant');
        $actor = $this->createUser();
        $valid = $this->app
            ->make(PlatformOperationAuthorizer::class)
            ->authorizeFoundationTenantBootstrap(
                organization: $organization,
                actor: $actor,
                reasonKey: 'test.fixture.membership_create',
            );
        $forged = new PlatformOperationAuthorization(
            capability: $valid->capability,
            reasonKey: $valid->reasonKey,
            targetOrganizationId: $valid->targetOrganizationId,
            actorUserId: $valid->actorUserId,
            action: $valid->action,
            subjectType: $valid->subjectType,
            subjectId: $valid->subjectId,
            correlationId: $valid->correlationId,
            issuedAtEpoch: $valid->issuedAtEpoch,
            nonce: $valid->nonce,
            signature: str_repeat('0', 64),
        );

        try {
            $this->app
                ->make(AuditEventRecorder::class)
                ->recordPlatform($forged);
            self::fail('A forged platform authorization must fail closed.');
        } catch (UnauthorizedPlatformOperation) {
            self::assertDatabaseCount('audit_events', 0);
        }

        try {
            new OrganizationContext(
                organizationId: (string) $organization->getKey(),
                actorUserId: (string) $actor->getKey(),
                membershipId: null,
                source: OrganizationContextSource::PlatformOperation,
            );
            self::fail('Direct platform context construction must require authorization evidence.');
        } catch (InvalidArgumentException) {
            self::assertDatabaseCount('audit_events', 0);
        }
    }

    public function test_platform_audit_cannot_authorize_a_different_organization_context(): void
    {
        $organizationA = $this->createOrganization('Authorized Platform Tenant');
        $organizationB = $this->createOrganization('Unauthorized Platform Tenant');
        $actor = $this->createUser();
        $authorization = $this->app
            ->make(PlatformOperationAuthorizer::class)
            ->authorizeFoundationTenantBootstrap(
                organization: $organizationA,
                actor: $actor,
                reasonKey: 'test.fixture.membership_create',
            );
        $event = $this->app
            ->make(AuditEventRecorder::class)
            ->recordPlatform($authorization);

        $this->expectException(UnauthorizedPlatformOperation::class);

        new OrganizationContext(
            organizationId: (string) $organizationB->getKey(),
            actorUserId: (string) $actor->getKey(),
            membershipId: null,
            source: OrganizationContextSource::PlatformOperation,
            platformAuthorization: $authorization,
            platformAuditEventId: (string) $event->getKey(),
        );
    }

    public function test_non_context_platform_capability_is_rejected_before_audit(): void
    {
        $actor = $this->createUser();
        $authorization = $this->app
            ->make(PlatformOperationAuthorizer::class)
            ->authorizeAuthenticationSessionAudit(
                action: 'authentication.session.revoked',
                reasonKey: 'user_revoked',
                authenticationSessionId: (string) Str::ulid(),
                actorUserId: (string) $actor->getKey(),
            );

        try {
            $this->app
                ->make(PlatformOperationExecutor::class)
                ->run($authorization, static fn (): null => null);
            self::fail('A non-context platform capability must fail closed.');
        } catch (UnauthorizedPlatformOperation) {
            self::assertDatabaseCount('audit_events', 0);
        }
    }

    public function test_platform_context_operation_and_audit_are_atomic(): void
    {
        $organization = $this->createOrganization('Atomic Platform Tenant');
        $actor = $this->createUser();
        $targetUser = $this->createUser();
        $authorization = $this->app
            ->make(PlatformOperationAuthorizer::class)
            ->authorizeFoundationTenantBootstrap(
                organization: $organization,
                actor: $actor,
                reasonKey: 'test.fixture.membership_create',
            );

        try {
            $this->app
                ->make(PlatformOperationExecutor::class)
                ->run(
                    $authorization,
                    function () use ($targetUser): void {
                        $this->createMembershipThenFail(
                            (string) $targetUser->getKey(),
                        );
                    },
                );
            self::fail('A failed platform operation must be rolled back.');
        } catch (RuntimeException) {
            self::assertDatabaseMissing('memberships', [
                'organization_id' => $organization->getKey(),
                'user_id' => $targetUser->getKey(),
            ]);
            self::assertDatabaseCount('audit_events', 0);
            self::assertNull($this->contexts()->currentOrNull());
        }
    }

    public function test_mobile_revocation_reason_is_authorized_before_credentials_are_mutated(): void
    {
        $user = $this->createUser();
        $client = $this->createMobileClient();
        $family = MobileTokenFamily::query()->forceCreate([
            'user_id' => $user->getKey(),
            'client_id' => $client->getKey(),
            'selected_organization_id' => null,
            'selected_membership_id' => null,
            'installation_digest' => str_repeat('a', 64),
            'last_used_at' => Date::now(),
            'absolute_expires_at' => Date::now()->addDay(),
        ]);

        try {
            $this->app
                ->make(MobileTokenFamilyService::class)
                ->revoke($family, 'operator-entered-sensitive-reason');
            self::fail('An unapproved mobile revocation reason must fail closed.');
        } catch (UnauthorizedPlatformOperation) {
            $family->refresh();
            self::assertNull($family->revoked_at);
            self::assertNull($family->revocation_reason);
            self::assertDatabaseCount('audit_events', 0);
        }
    }

    private function createMobileClient(): Client
    {
        return Client::query()->create([
            'name' => 'Platform authorization test client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => ['com.globalenglish.app:/oauth/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ]);
    }

    private function createMembershipThenFail(string $targetUserId): void
    {
        Membership::query()->forceCreate([
            'user_id' => $targetUserId,
            'membership_type' => Membership::TYPE_LEARNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => Date::now(),
        ]);

        throw new RuntimeException('synthetic platform failure');
    }
}

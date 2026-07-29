<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Authentication\AuthenticationSecretDigester;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\Exceptions\UnauthorizedPlatformOperation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

final readonly class PlatformOperationAuthorizer
{
    private const int AUTHORIZATION_TTL_SECONDS = 300;

    /**
     * @var list<string>
     */
    private const array AUTHENTICATION_SESSION_ACTIONS = [
        'authentication.session.expired',
        'authentication.session.revoked',
    ];

    /**
     * @var list<string>
     */
    private const array AUTHENTICATION_SESSION_REASONS = [
        'expired',
        'invalid_or_expired',
        'logout',
        'user_inactive',
        'user_not_active',
        'user_revoked',
        'user_revoked_all',
    ];

    /**
     * @var list<string>
     */
    private const array MOBILE_CREDENTIAL_ACTIONS = [
        'authentication.mobile_credential.reuse_detected',
        'authentication.mobile_installation.mismatch',
        'authentication.mobile_credential.revoked',
    ];

    /**
     * @var list<string>
     */
    private const array MOBILE_CREDENTIAL_REASONS = [
        'expired',
        'installation_mismatch',
        'mobile_logout',
        'refresh_token_reuse',
    ];

    public function __construct(
        private AuthenticationSecretDigester $secrets,
    ) {}

    public function authorizeFoundationTenantBootstrap(
        Organization $organization,
        User $actor,
        string $reasonKey,
        ?string $correlationId = null,
    ): PlatformOperationAuthorization {
        if (
            ! app()->environment('testing')
            || $organization->status !== Organization::STATUS_ACTIVE
            || $actor->status !== User::STATUS_ACTIVE
            || $reasonKey !== 'test.fixture.membership_create'
            || ! Organization::query()
                ->whereKey($organization->getKey())
                ->where('status', Organization::STATUS_ACTIVE)
                ->exists()
            || ! User::query()
                ->whereKey($actor->getKey())
                ->where('status', User::STATUS_ACTIVE)
                ->exists()
        ) {
            throw new UnauthorizedPlatformOperation;
        }

        return $this->issue(
            capability: PlatformCapability::FoundationTenantBootstrap,
            reasonKey: $reasonKey,
            targetOrganizationId: (string) $organization->getKey(),
            actorUserId: (string) $actor->getKey(),
            action: 'platform.foundation_tenant.bootstrap',
            subjectType: 'organization',
            subjectId: (string) $organization->getKey(),
            correlationId: $correlationId,
        );
    }

    public function authorizeAuthenticationSessionAudit(
        string $action,
        string $reasonKey,
        string $authenticationSessionId,
        string $actorUserId,
        ?string $correlationId = null,
    ): PlatformOperationAuthorization {
        if (
            ! in_array($action, self::AUTHENTICATION_SESSION_ACTIONS, true)
            || ! in_array(
                $reasonKey,
                self::AUTHENTICATION_SESSION_REASONS,
                true,
            )
        ) {
            throw new UnauthorizedPlatformOperation;
        }

        return $this->issue(
            capability: PlatformCapability::AuthenticationSessionLifecycle,
            reasonKey: $reasonKey,
            targetOrganizationId: null,
            actorUserId: $actorUserId,
            action: $action,
            subjectType: 'authentication_session',
            subjectId: $authenticationSessionId,
            correlationId: $correlationId,
        );
    }

    public function authorizeMobileCredentialAudit(
        string $action,
        string $reasonKey,
        string $familyId,
        string $actorUserId,
        ?string $correlationId = null,
    ): PlatformOperationAuthorization {
        if (
            ! in_array($action, self::MOBILE_CREDENTIAL_ACTIONS, true)
            || ! in_array($reasonKey, self::MOBILE_CREDENTIAL_REASONS, true)
        ) {
            throw new UnauthorizedPlatformOperation;
        }

        return $this->issue(
            capability: PlatformCapability::MobileCredentialLifecycle,
            reasonKey: $reasonKey,
            targetOrganizationId: null,
            actorUserId: $actorUserId,
            action: $action,
            subjectType: 'mobile_credential_family',
            subjectId: $familyId,
            correlationId: $correlationId,
        );
    }

    public function assertAuditAuthorized(
        PlatformOperationAuthorization $authorization,
    ): void {
        $this->assertSignature($authorization);

        $allowed = match ($authorization->capability) {
            PlatformCapability::FoundationTenantBootstrap => $authorization->action
                === 'platform.foundation_tenant.bootstrap'
                && $authorization->reasonKey
                    === 'test.fixture.membership_create'
                && $authorization->subjectType === 'organization'
                && $authorization->targetOrganizationId !== null
                && $authorization->actorUserId !== null
                && hash_equals(
                    $authorization->targetOrganizationId,
                    $authorization->subjectId,
                ),
            PlatformCapability::AuthenticationSessionLifecycle => in_array(
                $authorization->action,
                self::AUTHENTICATION_SESSION_ACTIONS,
                true,
            ) && $authorization->subjectType === 'authentication_session'
                && in_array(
                    $authorization->reasonKey,
                    self::AUTHENTICATION_SESSION_REASONS,
                    true,
                )
                && $authorization->targetOrganizationId === null,
            PlatformCapability::MobileCredentialLifecycle => in_array(
                $authorization->action,
                self::MOBILE_CREDENTIAL_ACTIONS,
                true,
            ) && $authorization->subjectType === 'mobile_credential_family'
                && in_array(
                    $authorization->reasonKey,
                    self::MOBILE_CREDENTIAL_REASONS,
                    true,
                )
                && $authorization->targetOrganizationId === null,
        };

        if (! $allowed) {
            throw new UnauthorizedPlatformOperation;
        }
    }

    public function assertContextAuthorized(
        PlatformOperationAuthorization $authorization,
        string $auditEventId,
        string $organizationId,
        string $actorUserId,
    ): void {
        $this->assertContextOperationAuthorized($authorization);

        if (
            $authorization->targetOrganizationId === null
            || $authorization->actorUserId === null
            || ! hash_equals(
                $authorization->targetOrganizationId,
                $organizationId,
            )
            || ! hash_equals($authorization->actorUserId, $actorUserId)
        ) {
            throw new UnauthorizedPlatformOperation;
        }

        $auditExists = DB::table('audit_events')
            ->where('id', $auditEventId)
            ->where('scope_kind', 'platform')
            ->where('target_organization_id', $organizationId)
            ->where('actor_user_id', $actorUserId)
            ->where('capability_key', $authorization->capability->value)
            ->where('reason_key', $authorization->reasonKey)
            ->where('action', $authorization->action)
            ->where('subject_type', $authorization->subjectType)
            ->where('subject_id', $authorization->subjectId)
            ->where('correlation_id', $authorization->correlationId)
            ->exists();

        if (! $auditExists) {
            throw new UnauthorizedPlatformOperation;
        }
    }

    public function assertContextOperationAuthorized(
        PlatformOperationAuthorization $authorization,
    ): void {
        $this->assertAuditAuthorized($authorization);

        if (
            $authorization->capability
                !== PlatformCapability::FoundationTenantBootstrap
            || $authorization->targetOrganizationId === null
            || $authorization->actorUserId === null
            || ! DB::table('organizations')
                ->where('id', $authorization->targetOrganizationId)
                ->where('status', Organization::STATUS_ACTIVE)
                ->exists()
            || ! DB::table('users')
                ->where('id', $authorization->actorUserId)
                ->where('status', User::STATUS_ACTIVE)
                ->exists()
        ) {
            throw new UnauthorizedPlatformOperation;
        }
    }

    private function issue(
        PlatformCapability $capability,
        string $reasonKey,
        ?string $targetOrganizationId,
        ?string $actorUserId,
        string $action,
        string $subjectType,
        string $subjectId,
        ?string $correlationId,
    ): PlatformOperationAuthorization {
        $this->assertDescriptor($reasonKey);
        $this->assertDescriptor($action);
        $this->assertDescriptor($subjectType);
        $this->assertUlid($subjectId);

        if ($targetOrganizationId !== null) {
            $this->assertUlid($targetOrganizationId);
        }

        if ($actorUserId !== null) {
            $this->assertUlid($actorUserId);
        }

        if ($correlationId !== null && ! Str::isUuid($correlationId)) {
            throw new UnauthorizedPlatformOperation;
        }

        $issuedAtEpoch = Date::now()->getTimestamp();
        $nonce = (string) Str::uuid();
        $unsigned = new PlatformOperationAuthorization(
            capability: $capability,
            reasonKey: $reasonKey,
            targetOrganizationId: $targetOrganizationId,
            actorUserId: $actorUserId,
            action: $action,
            subjectType: $subjectType,
            subjectId: $subjectId,
            correlationId: $correlationId,
            issuedAtEpoch: $issuedAtEpoch,
            nonce: $nonce,
            signature: '',
        );

        return new PlatformOperationAuthorization(
            capability: $capability,
            reasonKey: $reasonKey,
            targetOrganizationId: $targetOrganizationId,
            actorUserId: $actorUserId,
            action: $action,
            subjectType: $subjectType,
            subjectId: $subjectId,
            correlationId: $correlationId,
            issuedAtEpoch: $issuedAtEpoch,
            nonce: $nonce,
            signature: $this->signature($unsigned),
        );
    }

    private function assertSignature(
        PlatformOperationAuthorization $authorization,
    ): void {
        $this->assertDescriptor($authorization->reasonKey);
        $this->assertDescriptor($authorization->action);
        $this->assertDescriptor($authorization->subjectType);
        $this->assertUlid($authorization->subjectId);

        if ($authorization->targetOrganizationId !== null) {
            $this->assertUlid($authorization->targetOrganizationId);
        }

        if ($authorization->actorUserId !== null) {
            $this->assertUlid($authorization->actorUserId);
        }

        if (
            $authorization->correlationId !== null
            && ! Str::isUuid($authorization->correlationId)
        ) {
            throw new UnauthorizedPlatformOperation;
        }

        $age = Date::now()->getTimestamp() - $authorization->issuedAtEpoch;

        if (
            $age < 0
            || $age > self::AUTHORIZATION_TTL_SECONDS
            || ! Str::isUuid($authorization->nonce)
            || ! hash_equals(
                $this->signature($authorization),
                $authorization->signature,
            )
        ) {
            throw new UnauthorizedPlatformOperation;
        }
    }

    private function signature(
        PlatformOperationAuthorization $authorization,
    ): string {
        try {
            $payload = json_encode([
                'capability' => $authorization->capability->value,
                'reason_key' => $authorization->reasonKey,
                'target_organization_id' => $authorization->targetOrganizationId,
                'actor_user_id' => $authorization->actorUserId,
                'action' => $authorization->action,
                'subject_type' => $authorization->subjectType,
                'subject_id' => $authorization->subjectId,
                'correlation_id' => $authorization->correlationId,
                'issued_at_epoch' => $authorization->issuedAtEpoch,
                'nonce' => $authorization->nonce,
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new UnauthorizedPlatformOperation;
        }

        return $this->secrets->digest(
            'platform-operation-authorization',
            $payload,
        );
    }

    private function assertDescriptor(string $value): void
    {
        if (preg_match('/\A[a-z][a-z0-9_.-]{0,119}\z/', $value) !== 1) {
            throw new UnauthorizedPlatformOperation;
        }
    }

    private function assertUlid(string $value): void
    {
        if (! Str::isUlid($value)) {
            throw new UnauthorizedPlatformOperation;
        }
    }
}

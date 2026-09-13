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
    private const array CONTENT_EMERGENCY_SUSPEND_REASONS = [
        'content.emergency.legal',
        'content.emergency.rights',
        'content.emergency.safety',
        'content.emergency.security',
    ];

    /**
     * @var list<string>
     */
    private const array CONTENT_EMERGENCY_RESUME_REASONS = [
        'content.emergency.legal_resolved',
        'content.emergency.rights_restored',
        'content.emergency.safety_remediated',
        'content.emergency.security_remediated',
    ];

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
        'mfa_factor_replaced',
        'mfa_factor_revoked',
        'mfa_recovery_regenerated',
        'password_established',
        'password_changed',
        'username_changed',
        'password_recovered',
        'account_setup_requested',
        'account_setup_completed',
        'recovery_contact_replaced',
        'password_cutover',
        'privacy_erasure',
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
        'mfa_factor_replaced',
        'mfa_factor_revoked',
        'mfa_recovery_regenerated',
        'password_established',
        'password_changed',
        'username_changed',
        'password_recovered',
        'account_setup_requested',
        'account_setup_completed',
        'recovery_contact_replaced',
        'password_cutover',
        'privacy_erasure',
    ];

    /** @var array<string, list<string>> */
    private const array ACCOUNT_RECOVERY_MANAGEMENT_AUDIT = [
        'authentication.account_recovery.setup_requested' => [
            'account_recovery.migration_setup',
        ],
        'authentication.account_recovery.contact_replaced' => [
            'account_recovery.verified_contact_replacement',
        ],
    ];

    /**
     * @var array<string, array{subject: string, reasons: list<string>}>
     */
    private const array PRIVILEGED_MFA_AUDIT = [
        'authentication.mfa.enrollment.started' => [
            'subject' => 'privileged_mfa_factor',
            'reasons' => ['enrollment_started'],
        ],
        'authentication.mfa.enrollment.confirmed' => [
            'subject' => 'privileged_mfa_factor',
            'reasons' => ['enrollment_confirmed'],
        ],
        'authentication.mfa.challenge.issued' => [
            'subject' => 'privileged_mfa_challenge',
            'reasons' => ['assertion_challenge_issued'],
        ],
        'authentication.mfa.verification.succeeded' => [
            'subject' => 'privileged_mfa_challenge',
            'reasons' => [
                'enrollment_totp_verified',
                'totp_verified',
                'recovery_verified',
            ],
        ],
        'authentication.mfa.verification.failed' => [
            'subject' => 'privileged_mfa_challenge',
            'reasons' => [
                'invalid_assertion',
                'challenge_expired',
                'attempt_limit_reached',
            ],
        ],
        'authentication.mfa.recovery.used' => [
            'subject' => 'privileged_mfa_recovery_code',
            'reasons' => ['recovery_code_used'],
        ],
        'authentication.mfa.recovery.regenerated' => [
            'subject' => 'privileged_mfa_factor',
            'reasons' => ['recovery_codes_regenerated'],
        ],
        'authentication.mfa.factor.replaced' => [
            'subject' => 'privileged_mfa_factor',
            'reasons' => ['factor_replaced'],
        ],
        'authentication.mfa.factor.revoked' => [
            'subject' => 'privileged_mfa_factor',
            'reasons' => ['factor_revoked'],
        ],
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

    public function authorizePrivilegedMfaAudit(
        string $action,
        string $reasonKey,
        string $subjectType,
        string $subjectId,
        string $actorUserId,
        ?string $correlationId = null,
    ): PlatformOperationAuthorization {
        $definition = self::PRIVILEGED_MFA_AUDIT[$action] ?? null;

        if (
            $definition === null
            || $subjectType !== $definition['subject']
            || ! in_array($reasonKey, $definition['reasons'], true)
        ) {
            throw new UnauthorizedPlatformOperation;
        }

        return $this->issue(
            capability: PlatformCapability::PrivilegedMfaLifecycle,
            reasonKey: $reasonKey,
            targetOrganizationId: null,
            actorUserId: $actorUserId,
            action: $action,
            subjectType: $subjectType,
            subjectId: $subjectId,
            correlationId: $correlationId,
        );
    }

    public function authorizeAccountRecoveryManagementAudit(
        string $action,
        string $reasonKey,
        string $managementEventId,
        string $actorUserId,
        ?string $correlationId = null,
    ): PlatformOperationAuthorization {
        if (
            ! isset(self::ACCOUNT_RECOVERY_MANAGEMENT_AUDIT[$action])
            || ! in_array(
                $reasonKey,
                self::ACCOUNT_RECOVERY_MANAGEMENT_AUDIT[$action],
                true,
            )
        ) {
            throw new UnauthorizedPlatformOperation;
        }

        return $this->issue(
            capability: PlatformCapability::AccountRecoveryManagement,
            reasonKey: $reasonKey,
            targetOrganizationId: null,
            actorUserId: $actorUserId,
            action: $action,
            subjectType: 'account_recovery_management_event',
            subjectId: $managementEventId,
            correlationId: $correlationId,
        );
    }

    public function authorizeContentEmergencySuspension(
        Organization $targetOrganization,
        string $programId,
        User $actor,
        string $authenticationSessionId,
        string $reasonKey,
        ?string $correlationId = null,
    ): PlatformOperationAuthorization {
        return $this->authorizeContentEmergencyChange(
            targetOrganization: $targetOrganization,
            programId: $programId,
            actor: $actor,
            authenticationSessionId: $authenticationSessionId,
            action: 'curriculum.publication.suspended',
            allowedReasons: self::CONTENT_EMERGENCY_SUSPEND_REASONS,
            reasonKey: $reasonKey,
            correlationId: $correlationId,
        );
    }

    public function authorizeContentEmergencyResumption(
        Organization $targetOrganization,
        string $programId,
        User $actor,
        string $authenticationSessionId,
        string $reasonKey,
        ?string $correlationId = null,
    ): PlatformOperationAuthorization {
        return $this->authorizeContentEmergencyChange(
            targetOrganization: $targetOrganization,
            programId: $programId,
            actor: $actor,
            authenticationSessionId: $authenticationSessionId,
            action: 'curriculum.publication.resumed',
            allowedReasons: self::CONTENT_EMERGENCY_RESUME_REASONS,
            reasonKey: $reasonKey,
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
            PlatformCapability::PrivilegedMfaLifecycle => $this
                ->privilegedMfaAuthorizationIsAllowed($authorization),
            PlatformCapability::AccountRecoveryManagement => isset(
                self::ACCOUNT_RECOVERY_MANAGEMENT_AUDIT[$authorization->action],
            )
                && in_array(
                    $authorization->reasonKey,
                    self::ACCOUNT_RECOVERY_MANAGEMENT_AUDIT[$authorization->action],
                    true,
                )
                && $authorization->subjectType === 'account_recovery_management_event'
                && $authorization->targetOrganizationId === null
                && $authorization->actorUserId !== null,
            PlatformCapability::ContentEmergencySuspend => $authorization
                ->subjectType === 'program'
                && $authorization->targetOrganizationId !== null
                && $authorization->actorUserId !== null
                && $authorization->authenticationSessionId !== null
                && $authorization->correlationId !== null
                && (
                    (
                        $authorization->action
                            === 'curriculum.publication.suspended'
                        && in_array(
                            $authorization->reasonKey,
                            self::CONTENT_EMERGENCY_SUSPEND_REASONS,
                            true,
                        )
                    )
                    || (
                        $authorization->action
                            === 'curriculum.publication.resumed'
                        && in_array(
                            $authorization->reasonKey,
                            self::CONTENT_EMERGENCY_RESUME_REASONS,
                            true,
                        )
                    )
                ),
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
            ->where('authorization_nonce', $authorization->nonce)
            ->exists();

        if (! $auditExists) {
            throw new UnauthorizedPlatformOperation;
        }
    }

    public function assertContextOperationAuthorized(
        PlatformOperationAuthorization $authorization,
    ): void {
        $this->assertAuditAuthorized($authorization);

        $allowed = match ($authorization->capability) {
            PlatformCapability::FoundationTenantBootstrap => $this
                ->foundationContextIsCurrent($authorization),
            PlatformCapability::ContentEmergencySuspend => $this
                ->contentEmergencyContextIsCurrent($authorization),
            PlatformCapability::AuthenticationSessionLifecycle,
            PlatformCapability::MobileCredentialLifecycle,
            PlatformCapability::PrivilegedMfaLifecycle => false,
            PlatformCapability::AccountRecoveryManagement => false,
        };

        if (! $allowed) {
            throw new UnauthorizedPlatformOperation;
        }
    }

    /**
     * @param  list<string>  $allowedReasons
     */
    private function authorizeContentEmergencyChange(
        Organization $targetOrganization,
        string $programId,
        User $actor,
        string $authenticationSessionId,
        string $action,
        array $allowedReasons,
        string $reasonKey,
        ?string $correlationId,
    ): PlatformOperationAuthorization {
        $organizationId = (string) $targetOrganization->getKey();
        $actorUserId = (string) $actor->getKey();

        if (
            ! in_array($reasonKey, $allowedReasons, true)
            || $targetOrganization->status !== Organization::STATUS_ACTIVE
            || $actor->status !== User::STATUS_ACTIVE
            || ! in_array(
                $actorUserId,
                $this->contentEmergencyActorUserIds(),
                true,
            )
            || ! DB::table('organizations')
                ->where('id', $organizationId)
                ->where('status', Organization::STATUS_ACTIVE)
                ->exists()
            || ! DB::table('users')
                ->where('id', $actorUserId)
                ->where('status', User::STATUS_ACTIVE)
                ->exists()
            || ! DB::table('programs')
                ->where('organization_id', $organizationId)
                ->where('id', $programId)
                ->exists()
            || ! $this->freshPrivilegedSessionExists(
                $authenticationSessionId,
                $actorUserId,
            )
        ) {
            throw new UnauthorizedPlatformOperation;
        }

        return $this->issue(
            capability: PlatformCapability::ContentEmergencySuspend,
            reasonKey: $reasonKey,
            targetOrganizationId: $organizationId,
            actorUserId: $actorUserId,
            action: $action,
            subjectType: 'program',
            subjectId: $programId,
            correlationId: $correlationId ?? (string) Str::uuid(),
            authenticationSessionId: $authenticationSessionId,
        );
    }

    private function foundationContextIsCurrent(
        PlatformOperationAuthorization $authorization,
    ): bool {
        return $authorization->targetOrganizationId !== null
            && $authorization->actorUserId !== null
            && DB::table('organizations')
                ->where('id', $authorization->targetOrganizationId)
                ->where('status', Organization::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first(['id']) instanceof \stdClass
            && DB::table('users')
                ->where('id', $authorization->actorUserId)
                ->where('status', User::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first(['id']) instanceof \stdClass;
    }

    private function contentEmergencyContextIsCurrent(
        PlatformOperationAuthorization $authorization,
    ): bool {
        if (
            $authorization->targetOrganizationId === null
            || $authorization->actorUserId === null
            || $authorization->authenticationSessionId === null
            || ! in_array(
                $authorization->actorUserId,
                $this->contentEmergencyActorUserIds(),
                true,
            )
        ) {
            return false;
        }

        return DB::table('organizations')
            ->where('id', $authorization->targetOrganizationId)
            ->where('status', Organization::STATUS_ACTIVE)
            ->lockForUpdate()
            ->first(['id']) instanceof \stdClass
            && DB::table('users')
                ->where('id', $authorization->actorUserId)
                ->where('status', User::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first(['id']) instanceof \stdClass
            && DB::table('programs')
                ->where('organization_id', $authorization->targetOrganizationId)
                ->where('id', $authorization->subjectId)
                ->lockForUpdate()
                ->first(['id']) instanceof \stdClass
            && $this->freshPrivilegedSessionExists(
                $authorization->authenticationSessionId,
                $authorization->actorUserId,
                true,
            );
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
        ?string $authenticationSessionId = null,
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

        if ($authenticationSessionId !== null) {
            $this->assertUlid($authenticationSessionId);
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
            authenticationSessionId: $authenticationSessionId,
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
            authenticationSessionId: $authenticationSessionId,
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

        if ($authorization->authenticationSessionId !== null) {
            $this->assertUlid($authorization->authenticationSessionId);
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
                'authentication_session_id' => $authorization
                    ->authenticationSessionId,
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

    /**
     * @return list<string>
     */
    private function contentEmergencyActorUserIds(): array
    {
        $configured = config(
            'platform.content_emergency.actor_user_ids',
            [],
        );

        if (! is_array($configured)) {
            throw new UnauthorizedPlatformOperation;
        }

        $actorUserIds = [];

        foreach ($configured as $actorUserId) {
            if (! is_string($actorUserId) || ! Str::isUlid($actorUserId)) {
                throw new UnauthorizedPlatformOperation;
            }

            $actorUserIds[] = $actorUserId;
        }

        return array_values(array_unique($actorUserIds));
    }

    private function freshPrivilegedSessionExists(
        string $authenticationSessionId,
        string $actorUserId,
        bool $lockForUpdate = false,
    ): bool {
        if (
            ! Str::isUlid($authenticationSessionId)
            || ! Str::isUlid($actorUserId)
        ) {
            return false;
        }

        $now = Date::now();
        $query = DB::table('authentication_sessions')
            ->join('users', 'users.id', '=', 'authentication_sessions.user_id')
            ->where('authentication_sessions.id', $authenticationSessionId)
            ->where('authentication_sessions.user_id', $actorUserId)
            ->where('authentication_sessions.is_privileged', true)
            ->whereNull('authentication_sessions.revoked_at')
            ->where('authentication_sessions.idle_expires_at', '>', $now)
            ->where('authentication_sessions.absolute_expires_at', '>', $now)
            ->where(function ($freshness) use ($now): void {
                $freshness->where(function ($password) use ($now): void {
                    $password->where(
                        'authentication_sessions.password_confirmed_at',
                        '>',
                        $now->copy()->subMinutes((int) config(
                            'authentication.password.confirmation_minutes',
                            5,
                        )),
                    )->whereColumn(
                        'authentication_sessions.password_confirmed_credential_version',
                        'users.credential_version',
                    );
                })->orWhere(function ($mfa) use ($now): void {
                    $mfa->where(
                        'authentication_sessions.mfa_verified_at',
                        '>',
                        $now->copy()->subMinutes((int) config(
                            'authentication.privileged.mfa_fresh_minutes',
                            15,
                        )),
                    )->where('authentication_sessions.mfa_verified_method', 'totp')
                        ->where('authentication_sessions.mfa_verified_scope', 'privileged_actions')
                        ->whereNotNull('authentication_sessions.mfa_factor_id');
                });
            });

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first(['authentication_sessions.id']) instanceof \stdClass;
    }

    private function privilegedMfaAuthorizationIsAllowed(
        PlatformOperationAuthorization $authorization,
    ): bool {
        $definition = self::PRIVILEGED_MFA_AUDIT[$authorization->action] ?? null;

        return $definition !== null
            && $authorization->subjectType === $definition['subject']
            && in_array(
                $authorization->reasonKey,
                $definition['reasons'],
                true,
            )
            && $authorization->targetOrganizationId === null
            && $authorization->actorUserId !== null;
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

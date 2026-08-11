<?php

declare(strict_types=1);

namespace App\Authentication\Mfa;

use App\Audit\AuditEventRecorder;
use App\Authentication\Mobile\MobileTokenFamilyService;
use App\Authentication\Sessions\AuthenticationSessionService;
use App\Models\AuthenticationSession;
use App\Models\PrivilegedMfaChallenge;
use App\Models\PrivilegedMfaFactor;
use App\Models\PrivilegedMfaRecoveryCode;
use App\Models\User;
use App\Observability\CorrelationId;
use App\Tenancy\PlatformOperationAuthorizer;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class PrivilegedMfaService
{
    private const int CHALLENGE_TTL_MINUTES = 5;

    private const int MAX_FAILED_ATTEMPTS = 5;

    public function __construct(
        private PrivilegedMfaVerifier $verifier,
        private TotpProvisioner $totp,
        private RecoveryCodeManager $recoveryCodes,
        private AuthenticationSessionService $sessions,
        private MobileTokenFamilyService $mobileFamilies,
        private AuditEventRecorder $audit,
        private PlatformOperationAuthorizer $platformOperations,
    ) {}

    /**
     * @return array{
     *   factor_status: 'none'|'pending'|'active'|'support_required',
     *   factor_id: string|null,
     *   pending_expires_at: string|null,
     *   recovery_codes_remaining: int,
     *   fresh_assertion_scope: string|null,
     *   fresh_assertion_expires_at: string|null,
     *   can_enroll: bool,
     *   requires_support: bool
     * }
     */
    public function status(
        User $user,
        AuthenticationSession $authenticationSession,
    ): array {
        $userId = (string) $user->getKey();
        /** @var PrivilegedMfaFactor|null $active */
        $active = PrivilegedMfaFactor::query()
            ->where('user_id', $userId)
            ->where('status', PrivilegedMfaFactor::STATUS_ACTIVE)
            ->first();
        /** @var PrivilegedMfaFactor|null $pending */
        $pending = PrivilegedMfaFactor::query()
            ->where('user_id', $userId)
            ->where('status', PrivilegedMfaFactor::STATUS_PENDING)
            ->where('pending_expires_at', '>', Date::now())
            ->first();
        $hasConfirmedHistory = PrivilegedMfaFactor::query()
            ->where('user_id', $userId)
            ->whereNotNull('confirmed_at')
            ->exists();
        $requiresSupport = $active === null && $hasConfirmedHistory;
        $factor = $active ?? $pending;
        $fresh = $this->sessions->hasFreshMfaForFactorReplacement(
            $authenticationSession,
        );
        $scope = $fresh ? $authenticationSession->mfa_verified_scope : null;
        $freshExpiresAt = $fresh
            ? $authenticationSession->mfa_verified_at?->copy()->addMinutes(
                (int) config('authentication.privileged.mfa_fresh_minutes', 15),
            )->toIso8601String()
            : null;
        $remaining = $active === null
            ? 0
            : PrivilegedMfaRecoveryCode::query()
                ->where('user_id', $userId)
                ->where('factor_id', (string) $active->getKey())
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at')
                ->count();

        return [
            'factor_status' => match (true) {
                $active !== null => 'active',
                $pending !== null => 'pending',
                $requiresSupport => 'support_required',
                default => 'none',
            },
            'factor_id' => $factor === null ? null : (string) $factor->getKey(),
            'pending_expires_at' => $pending?->pending_expires_at?->toIso8601String(),
            'recovery_codes_remaining' => $remaining,
            'fresh_assertion_scope' => $scope,
            'fresh_assertion_expires_at' => $freshExpiresAt,
            'can_enroll' => $authenticationSession->is_privileged
                && ! $requiresSupport
                && (
                    $active === null
                    || $this->sessions->hasFreshMfaForFactorReplacement(
                        $authenticationSession,
                    )
                ),
            'requires_support' => $requiresSupport,
        ];
    }

    /**
     * @return array{factor_id: string, challenge_id: string, provisioning_uri: string, expires_at: string}
     */
    public function beginEnrollment(
        User $user,
        AuthenticationSession $authenticationSession,
        Request $request,
    ): array {
        return DB::transaction(function () use (
            $user,
            $authenticationSession,
            $request,
        ): array {
            $session = $this->lockUsablePrivilegedSession(
                $user,
                $authenticationSession,
            );

            if ($session === null) {
                throw new PrivilegedMfaOperationRejected;
            }

            $userId = (string) $user->getKey();
            /** @var PrivilegedMfaFactor|null $active */
            $active = PrivilegedMfaFactor::query()
                ->where('user_id', $userId)
                ->where('status', PrivilegedMfaFactor::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();
            $hasConfirmedHistory = PrivilegedMfaFactor::query()
                ->where('user_id', $userId)
                ->whereNotNull('confirmed_at')
                ->lockForUpdate()
                ->exists();

            if (
                ($active !== null
                    && ! $this->sessions->hasFreshMfaForFactorReplacement($session))
                || ($active === null && $hasConfirmedHistory)
            ) {
                throw new PrivilegedMfaOperationRejected;
            }

            /** @var Collection<int, PrivilegedMfaFactor> $pendingFactors */
            $pendingFactors = PrivilegedMfaFactor::query()
                ->where('user_id', $userId)
                ->where('status', PrivilegedMfaFactor::STATUS_PENDING)
                ->lockForUpdate()
                ->get();

            foreach ($pendingFactors as $pending) {
                $pending->forceFill([
                    'secret_ciphertext' => null,
                    'status' => PrivilegedMfaFactor::STATUS_ABANDONED,
                    'pending_expires_at' => null,
                ])->save();
                PrivilegedMfaChallenge::query()
                    ->where('factor_id', (string) $pending->getKey())
                    ->whereNull('consumed_at')
                    ->whereNull('superseded_at')
                    ->update([
                        'superseded_at' => Date::now(),
                        'updated_at' => Date::now(),
                    ]);
            }

            $secret = $this->totp->generateSecret();
            $expiresAt = Date::now()->addMinutes(self::CHALLENGE_TTL_MINUTES);
            /** @var PrivilegedMfaFactor $factor */
            $factor = PrivilegedMfaFactor::query()->forceCreate([
                'user_id' => $userId,
                'factor_type' => PrivilegedMfaFactor::TYPE_TOTP,
                'secret_ciphertext' => Crypt::encryptString($secret),
                'status' => PrivilegedMfaFactor::STATUS_PENDING,
                'pending_expires_at' => $expiresAt,
            ]);
            /** @var PrivilegedMfaChallenge $challenge */
            $challenge = PrivilegedMfaChallenge::query()->forceCreate([
                'user_id' => $userId,
                'authentication_session_id' => (string) $session->getKey(),
                'factor_id' => (string) $factor->getKey(),
                'purpose' => PrivilegedMfaChallenge::PURPOSE_ENROLLMENT_CONFIRMATION,
                'failed_attempts' => 0,
                'expires_at' => $expiresAt,
            ]);

            $this->recordAudit(
                $request,
                $userId,
                'authentication.mfa.enrollment.started',
                'enrollment_started',
                'privileged_mfa_factor',
                (string) $factor->getKey(),
            );

            return [
                'factor_id' => (string) $factor->getKey(),
                'challenge_id' => (string) $challenge->getKey(),
                'provisioning_uri' => $this->totp->provisioningUri(
                    $secret,
                    $userId,
                ),
                'expires_at' => $expiresAt->toIso8601String(),
            ];
        });
    }

    /**
     * @return array{recovery_codes: list<string>, sessions_revoked: bool}
     */
    public function confirmEnrollment(
        User $user,
        AuthenticationSession $authenticationSession,
        string $factorId,
        string $challengeId,
        string $code,
        Request $request,
    ): array {
        /** @var array{ok: bool, recovery_codes?: list<string>, sessions_revoked?: bool} $result */
        $result = DB::transaction(function () use (
            $user,
            $authenticationSession,
            $factorId,
            $challengeId,
            $code,
            $request,
        ): array {
            $session = $this->lockUsablePrivilegedSession(
                $user,
                $authenticationSession,
            );
            $userId = (string) $user->getKey();
            /** @var PrivilegedMfaFactor|null $factor */
            $factor = PrivilegedMfaFactor::query()
                ->whereKey($factorId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();
            /** @var PrivilegedMfaChallenge|null $challenge */
            $challenge = PrivilegedMfaChallenge::query()
                ->whereKey($challengeId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (
                $session === null
                || $factor === null
                || $challenge === null
                || $factor->status !== PrivilegedMfaFactor::STATUS_PENDING
                || $challenge->purpose
                    !== PrivilegedMfaChallenge::PURPOSE_ENROLLMENT_CONFIRMATION
                || ! hash_equals($challenge->factor_id, $factorId)
                || ! hash_equals(
                    $challenge->authentication_session_id,
                    (string) $session->getKey(),
                )
            ) {
                return ['ok' => false];
            }

            if (! $this->challengeIsUsable($challenge)) {
                $this->recordChallengeFailure($challenge, $userId, $request);

                return ['ok' => false];
            }

            $timeStep = $this->verifyTotpFactor($factor, $code);

            if ($timeStep === null) {
                $this->recordInvalidAssertion($challenge, $userId, $request);

                return ['ok' => false];
            }

            /** @var PrivilegedMfaFactor|null $active */
            $active = PrivilegedMfaFactor::query()
                ->where('user_id', $userId)
                ->where('status', PrivilegedMfaFactor::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();
            $now = Date::now();
            $wasReplacement = $active !== null;

            if ($active !== null) {
                $active->forceFill([
                    'secret_ciphertext' => null,
                    'status' => PrivilegedMfaFactor::STATUS_REPLACED,
                    'replaced_at' => $now,
                ])->save();
                $this->invalidateRecoveryCodes($userId, (string) $active->getKey());
                $this->recordAudit(
                    $request,
                    $userId,
                    'authentication.mfa.factor.replaced',
                    'factor_replaced',
                    'privileged_mfa_factor',
                    (string) $active->getKey(),
                );
            }

            $factor->forceFill([
                'status' => PrivilegedMfaFactor::STATUS_ACTIVE,
                'pending_expires_at' => null,
                'confirmed_at' => $now,
                'last_used_time_step' => $timeStep,
            ])->save();
            $challenge->forceFill(['consumed_at' => $now])->save();
            $session->forceFill([
                'mfa_verified_at' => $now,
                'mfa_factor_id' => $factorId,
                'mfa_verified_method' => AuthenticationSession::MFA_METHOD_TOTP,
                'mfa_verified_scope' => AuthenticationSession::MFA_SCOPE_PRIVILEGED_ACTIONS,
            ])->save();
            $rawRecoveryCodes = $this->createRecoveryCodes(
                $userId,
                $factorId,
            );

            $this->recordAudit(
                $request,
                $userId,
                'authentication.mfa.verification.succeeded',
                'enrollment_totp_verified',
                'privileged_mfa_challenge',
                $challengeId,
            );
            $this->recordAudit(
                $request,
                $userId,
                'authentication.mfa.enrollment.confirmed',
                'enrollment_confirmed',
                'privileged_mfa_factor',
                $factorId,
            );

            if ($wasReplacement) {
                $this->revokeAllCredentials($user, $request, 'mfa_factor_replaced');
            }

            return [
                'ok' => true,
                'recovery_codes' => $rawRecoveryCodes,
                'sessions_revoked' => $wasReplacement,
            ];
        });

        $code = str_repeat('0', strlen($code));
        unset($code);

        if (! $result['ok']) {
            throw new PrivilegedMfaVerificationFailed;
        }

        return [
            'recovery_codes' => $result['recovery_codes'] ?? [],
            'sessions_revoked' => $result['sessions_revoked'] ?? false,
        ];
    }

    /**
     * @return array{challenge_id: string, expires_at: string}
     */
    public function issueAssertionChallenge(
        User $user,
        AuthenticationSession $authenticationSession,
        Request $request,
    ): array {
        return DB::transaction(function () use (
            $user,
            $authenticationSession,
            $request,
        ): array {
            $session = $this->lockUsablePrivilegedSession(
                $user,
                $authenticationSession,
            );
            $userId = (string) $user->getKey();
            /** @var PrivilegedMfaFactor|null $factor */
            $factor = PrivilegedMfaFactor::query()
                ->where('user_id', $userId)
                ->where('status', PrivilegedMfaFactor::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if ($session === null || $factor === null) {
                throw new PrivilegedMfaOperationRejected;
            }

            $now = Date::now();
            PrivilegedMfaChallenge::query()
                ->where('user_id', $userId)
                ->where('authentication_session_id', (string) $session->getKey())
                ->where('purpose', PrivilegedMfaChallenge::PURPOSE_ASSERTION)
                ->whereNull('consumed_at')
                ->whereNull('superseded_at')
                ->update([
                    'superseded_at' => $now,
                    'updated_at' => $now,
                ]);
            $expiresAt = $now->copy()->addMinutes(self::CHALLENGE_TTL_MINUTES);
            /** @var PrivilegedMfaChallenge $challenge */
            $challenge = PrivilegedMfaChallenge::query()->forceCreate([
                'user_id' => $userId,
                'authentication_session_id' => (string) $session->getKey(),
                'factor_id' => (string) $factor->getKey(),
                'purpose' => PrivilegedMfaChallenge::PURPOSE_ASSERTION,
                'failed_attempts' => 0,
                'expires_at' => $expiresAt,
            ]);

            $this->recordAudit(
                $request,
                $userId,
                'authentication.mfa.challenge.issued',
                'assertion_challenge_issued',
                'privileged_mfa_challenge',
                (string) $challenge->getKey(),
            );

            return [
                'challenge_id' => (string) $challenge->getKey(),
                'expires_at' => $expiresAt->toIso8601String(),
            ];
        });
    }

    /**
     * @return array{verified_at: string, scope: string}
     */
    public function verifyAssertionChallenge(
        User $user,
        AuthenticationSession $authenticationSession,
        string $challengeId,
        string $method,
        string $code,
        Request $request,
    ): array {
        /** @var array{ok: bool, verified_at?: string, scope?: string} $result */
        $result = DB::transaction(function () use (
            $user,
            $authenticationSession,
            $challengeId,
            $method,
            $code,
            $request,
        ): array {
            $session = $this->lockUsablePrivilegedSession(
                $user,
                $authenticationSession,
            );
            $userId = (string) $user->getKey();
            /** @var PrivilegedMfaChallenge|null $challenge */
            $challenge = PrivilegedMfaChallenge::query()
                ->whereKey($challengeId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (
                $session === null
                || $challenge === null
                || $challenge->purpose !== PrivilegedMfaChallenge::PURPOSE_ASSERTION
                || ! hash_equals(
                    $challenge->authentication_session_id,
                    (string) $session->getKey(),
                )
            ) {
                return ['ok' => false];
            }

            /** @var PrivilegedMfaFactor|null $factor */
            $factor = PrivilegedMfaFactor::query()
                ->whereKey($challenge->factor_id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (
                $factor === null
                || $factor->status !== PrivilegedMfaFactor::STATUS_ACTIVE
                || ! $this->challengeIsUsable($challenge)
            ) {
                $this->recordChallengeFailure($challenge, $userId, $request);

                return ['ok' => false];
            }

            $now = Date::now();
            $scope = AuthenticationSession::MFA_SCOPE_PRIVILEGED_ACTIONS;
            $auditReason = 'totp_verified';
            $recoveryCode = null;

            if ($method === AuthenticationSession::MFA_METHOD_TOTP) {
                $timeStep = $this->verifyTotpFactor($factor, $code);

                if ($timeStep === null) {
                    $this->recordInvalidAssertion($challenge, $userId, $request);

                    return ['ok' => false];
                }

                $factor->forceFill(['last_used_time_step' => $timeStep])->save();
            } elseif ($method === AuthenticationSession::MFA_METHOD_RECOVERY_CODE) {
                $digest = $this->recoveryCodes->digest(
                    (string) $factor->getKey(),
                    $code,
                );
                /** @var PrivilegedMfaRecoveryCode|null $recoveryCode */
                $recoveryCode = $digest === null
                    ? null
                    : PrivilegedMfaRecoveryCode::query()
                        ->where('user_id', $userId)
                        ->where('factor_id', (string) $factor->getKey())
                        ->where('code_digest', $digest)
                        ->whereNull('consumed_at')
                        ->whereNull('invalidated_at')
                        ->lockForUpdate()
                        ->first();

                if ($recoveryCode === null) {
                    $this->recordInvalidAssertion($challenge, $userId, $request);

                    return ['ok' => false];
                }

                $scope = AuthenticationSession::MFA_SCOPE_FACTOR_REPLACEMENT;
                $auditReason = 'recovery_verified';
                $recoveryCode->forceFill(['consumed_at' => $now])->save();
            } else {
                $this->recordInvalidAssertion($challenge, $userId, $request);

                return ['ok' => false];
            }

            $challenge->forceFill(['consumed_at' => $now])->save();
            $session->forceFill([
                'mfa_verified_at' => $now,
                'mfa_factor_id' => (string) $factor->getKey(),
                'mfa_verified_method' => $method,
                'mfa_verified_scope' => $scope,
            ])->save();
            $this->recordAudit(
                $request,
                $userId,
                'authentication.mfa.verification.succeeded',
                $auditReason,
                'privileged_mfa_challenge',
                $challengeId,
            );

            if ($recoveryCode !== null) {
                $this->recordAudit(
                    $request,
                    $userId,
                    'authentication.mfa.recovery.used',
                    'recovery_code_used',
                    'privileged_mfa_recovery_code',
                    (string) $recoveryCode->getKey(),
                );
            }

            return [
                'ok' => true,
                'verified_at' => $now->toIso8601String(),
                'scope' => $scope,
            ];
        });

        $code = str_repeat('0', strlen($code));
        unset($code);

        if (! $result['ok']) {
            throw new PrivilegedMfaVerificationFailed;
        }

        return [
            'verified_at' => $result['verified_at'] ?? '',
            'scope' => $result['scope'] ?? '',
        ];
    }

    /**
     * @return array{recovery_codes: list<string>, sessions_revoked: true}
     */
    public function regenerateRecoveryCodes(
        User $user,
        AuthenticationSession $authenticationSession,
        Request $request,
    ): array {
        return DB::transaction(function () use (
            $user,
            $authenticationSession,
            $request,
        ): array {
            $session = $this->lockUsablePrivilegedSession(
                $user,
                $authenticationSession,
            );
            $userId = (string) $user->getKey();
            /** @var PrivilegedMfaFactor|null $factor */
            $factor = PrivilegedMfaFactor::query()
                ->where('user_id', $userId)
                ->where('status', PrivilegedMfaFactor::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if (
                $session === null
                || $factor === null
                || ! $this->sessions->hasFreshMfa($session)
                || ! hash_equals(
                    (string) $factor->getKey(),
                    (string) $session->mfa_factor_id,
                )
            ) {
                throw new PrivilegedMfaOperationRejected;
            }

            $factorId = (string) $factor->getKey();
            $this->invalidateRecoveryCodes($userId, $factorId);
            $codes = $this->createRecoveryCodes($userId, $factorId);
            $this->recordAudit(
                $request,
                $userId,
                'authentication.mfa.recovery.regenerated',
                'recovery_codes_regenerated',
                'privileged_mfa_factor',
                $factorId,
            );
            $this->revokeAllCredentials(
                $user,
                $request,
                'mfa_recovery_regenerated',
            );

            return [
                'recovery_codes' => $codes,
                'sessions_revoked' => true,
            ];
        });
    }

    public function revokeFactor(
        User $user,
        AuthenticationSession $authenticationSession,
        Request $request,
    ): void {
        DB::transaction(function () use (
            $user,
            $authenticationSession,
            $request,
        ): void {
            $session = $this->lockUsablePrivilegedSession(
                $user,
                $authenticationSession,
            );
            $userId = (string) $user->getKey();
            /** @var PrivilegedMfaFactor|null $factor */
            $factor = PrivilegedMfaFactor::query()
                ->where('user_id', $userId)
                ->where('status', PrivilegedMfaFactor::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if (
                $session === null
                || $factor === null
                || ! $this->sessions->hasFreshMfa($session)
                || ! hash_equals(
                    (string) $factor->getKey(),
                    (string) $session->mfa_factor_id,
                )
            ) {
                throw new PrivilegedMfaOperationRejected;
            }

            $factorId = (string) $factor->getKey();
            $factor->forceFill([
                'secret_ciphertext' => null,
                'status' => PrivilegedMfaFactor::STATUS_REVOKED,
                'revoked_at' => Date::now(),
            ])->save();
            $this->invalidateRecoveryCodes($userId, $factorId);
            $this->recordAudit(
                $request,
                $userId,
                'authentication.mfa.factor.revoked',
                'factor_revoked',
                'privileged_mfa_factor',
                $factorId,
            );
            $this->revokeAllCredentials($user, $request, 'mfa_factor_revoked');
        });
    }

    private function lockUsablePrivilegedSession(
        User $user,
        AuthenticationSession $authenticationSession,
    ): ?AuthenticationSession {
        $userId = (string) $user->getKey();
        $lockedUser = User::query()
            ->whereKey($userId)
            ->lockForUpdate()
            ->first(['id']);

        if ($lockedUser === null) {
            return null;
        }

        /** @var AuthenticationSession|null $locked */
        $locked = AuthenticationSession::query()
            ->whereKey((string) $authenticationSession->getKey())
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();
        $now = Date::now();

        if (
            $locked === null
            || ! $locked->is_privileged
            || $locked->revoked_at !== null
            || $locked->idle_expires_at->lessThanOrEqualTo($now)
            || $locked->absolute_expires_at->lessThanOrEqualTo($now)
        ) {
            return null;
        }

        return $locked;
    }

    private function challengeIsUsable(
        PrivilegedMfaChallenge $challenge,
    ): bool {
        return $challenge->consumed_at === null
            && $challenge->superseded_at === null
            && $challenge->failed_attempts < self::MAX_FAILED_ATTEMPTS
            && $challenge->expires_at->greaterThan(Date::now());
    }

    private function verifyTotpFactor(
        PrivilegedMfaFactor $factor,
        string $code,
    ): ?int {
        if (
            ! is_string($factor->secret_ciphertext)
            || $factor->secret_ciphertext === ''
        ) {
            return null;
        }

        try {
            $secret = Crypt::decryptString($factor->secret_ciphertext);
        } catch (DecryptException) {
            return null;
        }

        return $this->verifier->verify(
            $secret,
            $code,
            $factor->last_used_time_step,
        );
    }

    private function recordChallengeFailure(
        PrivilegedMfaChallenge $challenge,
        string $userId,
        Request $request,
    ): void {
        $reason = match (true) {
            $challenge->expires_at->lessThanOrEqualTo(Date::now()) => 'challenge_expired',
            $challenge->failed_attempts >= self::MAX_FAILED_ATTEMPTS => 'attempt_limit_reached',
            default => 'invalid_assertion',
        };

        if ($challenge->consumed_at === null && $reason !== 'invalid_assertion') {
            $challenge->forceFill(['consumed_at' => Date::now()])->save();
        }

        $this->recordAudit(
            $request,
            $userId,
            'authentication.mfa.verification.failed',
            $reason,
            'privileged_mfa_challenge',
            (string) $challenge->getKey(),
        );
    }

    private function recordInvalidAssertion(
        PrivilegedMfaChallenge $challenge,
        string $userId,
        Request $request,
    ): void {
        $attempts = min(
            self::MAX_FAILED_ATTEMPTS,
            $challenge->failed_attempts + 1,
        );
        $challenge->forceFill([
            'failed_attempts' => $attempts,
            'consumed_at' => $attempts >= self::MAX_FAILED_ATTEMPTS
                ? Date::now()
                : null,
        ])->save();
        $this->recordAudit(
            $request,
            $userId,
            'authentication.mfa.verification.failed',
            $attempts >= self::MAX_FAILED_ATTEMPTS
                ? 'attempt_limit_reached'
                : 'invalid_assertion',
            'privileged_mfa_challenge',
            (string) $challenge->getKey(),
        );
    }

    /**
     * @return list<string>
     */
    private function createRecoveryCodes(
        string $userId,
        string $factorId,
    ): array {
        $codes = $this->recoveryCodes->generate();

        foreach ($codes as $code) {
            $digest = $this->recoveryCodes->digest($factorId, $code);

            if ($digest === null) {
                throw new \LogicException('Generated recovery code was invalid.');
            }

            PrivilegedMfaRecoveryCode::query()->forceCreate([
                'user_id' => $userId,
                'factor_id' => $factorId,
                'code_digest' => $digest,
            ]);
        }

        return $codes;
    }

    private function invalidateRecoveryCodes(
        string $userId,
        string $factorId,
    ): void {
        PrivilegedMfaRecoveryCode::query()
            ->where('user_id', $userId)
            ->where('factor_id', $factorId)
            ->whereNull('consumed_at')
            ->whereNull('invalidated_at')
            ->update([
                'invalidated_at' => Date::now(),
                'updated_at' => Date::now(),
            ]);
    }

    private function revokeAllCredentials(
        User $user,
        Request $request,
        string $reason,
    ): void {
        $this->sessions->revokeAll($user, $request, $reason);
        $this->mobileFamilies->revokeAllForUser(
            (string) $user->getKey(),
            $reason,
        );
    }

    private function recordAudit(
        Request $request,
        string $actorUserId,
        string $action,
        string $reasonKey,
        string $subjectType,
        string $subjectId,
    ): void {
        $this->audit->recordPlatform(
            $this->platformOperations->authorizePrivilegedMfaAudit(
                action: $action,
                reasonKey: $reasonKey,
                subjectType: $subjectType,
                subjectId: $subjectId,
                actorUserId: $actorUserId,
                correlationId: CorrelationId::forRequest($request),
            ),
        );
    }
}

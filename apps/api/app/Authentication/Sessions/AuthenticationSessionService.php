<?php

declare(strict_types=1);

namespace App\Authentication\Sessions;

use App\Audit\AuditEventRecorder;
use App\Authentication\AuthenticationSecretDigester;
use App\Models\AuthenticationSession;
use App\Observability\CorrelationId;
use App\Tenancy\PlatformOperationAuthorizer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class AuthenticationSessionService
{
    public const SESSION_KEY = 'authentication_session_id';

    public function __construct(
        private PrivilegeClassifier $privileges,
        private AuditEventRecorder $audit,
        private PlatformOperationAuthorizer $platformOperations,
        private AuthenticationSecretDigester $secrets,
        private AuthenticationSessionCleanupWorker $cleanup,
    ) {}

    public function start(Authenticatable $user, Request $request): AuthenticationSession
    {
        Auth::guard('web')->login($user);

        $now = Date::now();
        $privileged = $this->privileges->isPrivileged($user);
        $idleMinutes = $this->idleMinutes($privileged);
        $absoluteMinutes = $this->absoluteMinutes($privileged);

        /** @var AuthenticationSession $authenticationSession */
        $authenticationSession = AuthenticationSession::query()->forceCreate([
            'user_id' => (string) $user->getAuthIdentifier(),
            'storage_session_digest' => $this->secrets->digest(
                'web-session-storage',
                $request->session()->getId(),
            ),
            'storage_session_ciphertext' => Crypt::encryptString(
                $request->session()->getId(),
            ),
            'is_privileged' => $privileged,
            'started_at' => $now,
            'last_seen_at' => $now,
            'idle_expires_at' => $now->copy()->addMinutes($idleMinutes),
            'absolute_expires_at' => $now->copy()->addMinutes($absoluteMinutes),
        ]);

        $request->session()->put(self::SESSION_KEY, (string) $authenticationSession->getKey());

        return $authenticationSession;
    }

    public function touch(Authenticatable $user, Request $request): ?AuthenticationSession
    {
        $publicSessionId = $request->session()->get(self::SESSION_KEY);

        if (! is_string($publicSessionId) || $publicSessionId === '') {
            return null;
        }

        return DB::transaction(function () use ($publicSessionId, $user, $request): ?AuthenticationSession {
            /** @var AuthenticationSession|null $authenticationSession */
            $authenticationSession = AuthenticationSession::query()
                ->whereKey($publicSessionId)
                ->lockForUpdate()
                ->first();

            if (
                $authenticationSession === null
                || ! hash_equals(
                    $authenticationSession->storage_session_digest,
                    $this->secrets->digest(
                        'web-session-storage',
                        $request->session()->getId(),
                    ),
                )
                || ! hash_equals(
                    $authenticationSession->user_id,
                    (string) $user->getAuthIdentifier(),
                )
            ) {
                return null;
            }

            $now = Date::now();

            if (
                $authenticationSession->revoked_at !== null
                || $authenticationSession->idle_expires_at->lessThanOrEqualTo($now)
                || $authenticationSession->absolute_expires_at->lessThanOrEqualTo($now)
            ) {
                $authenticationSession->forceFill([
                    'revoked_at' => $authenticationSession->revoked_at ?? $now,
                    'revocation_reason' => $authenticationSession->revocation_reason ?? 'expired',
                    'selected_organization_id' => null,
                    'selected_membership_id' => null,
                ])->save();
                $this->cleanup->enqueue($authenticationSession);
                $this->audit->recordPlatform(
                    $this->platformOperations
                        ->authorizeAuthenticationSessionAudit(
                            action: 'authentication.session.expired',
                            reasonKey: 'expired',
                            authenticationSessionId: (string) $authenticationSession->getKey(),
                            actorUserId: (string) $user->getAuthIdentifier(),
                            correlationId: CorrelationId::forRequest($request),
                        ),
                );

                return null;
            }

            $privileged = $authenticationSession->is_privileged
                || $this->privileges->isPrivileged($user);
            $absoluteExpiresAt = $authenticationSession->absolute_expires_at;

            if ($privileged) {
                $privilegedAbsoluteExpiry = $authenticationSession->started_at
                    ->copy()
                    ->addMinutes($this->absoluteMinutes(true));

                if ($privilegedAbsoluteExpiry->lessThan($absoluteExpiresAt)) {
                    $absoluteExpiresAt = $privilegedAbsoluteExpiry;
                }

                if ($absoluteExpiresAt->lessThanOrEqualTo($now)) {
                    $authenticationSession->forceFill([
                        'is_privileged' => true,
                        'absolute_expires_at' => $absoluteExpiresAt,
                        'revoked_at' => $now,
                        'revocation_reason' => 'expired',
                        'selected_organization_id' => null,
                        'selected_membership_id' => null,
                    ])->save();
                    $this->cleanup->enqueue($authenticationSession);
                    $this->audit->recordPlatform(
                        $this->platformOperations
                            ->authorizeAuthenticationSessionAudit(
                                action: 'authentication.session.expired',
                                reasonKey: 'expired',
                                authenticationSessionId: (string) $authenticationSession->getKey(),
                                actorUserId: (string) $user->getAuthIdentifier(),
                                correlationId: CorrelationId::forRequest($request),
                            ),
                    );

                    return null;
                }
            }

            $authenticationSession->forceFill([
                'is_privileged' => $privileged,
                'last_seen_at' => $now,
                'idle_expires_at' => $now->copy()->addMinutes($this->idleMinutes($privileged)),
                'absolute_expires_at' => $absoluteExpiresAt,
            ])->save();

            return $authenticationSession;
        });
    }

    /**
     * @return Collection<int, AuthenticationSession>
     */
    public function activeFor(Authenticatable $user): Collection
    {
        $now = Date::now();

        return AuthenticationSession::query()
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->whereNull('revoked_at')
            ->where('idle_expires_at', '>', $now)
            ->where('absolute_expires_at', '>', $now)
            ->orderByDesc('last_seen_at')
            ->get();
    }

    public function revoke(
        Authenticatable $user,
        string $publicSessionId,
        Request $request,
        string $reason = 'user_revoked',
    ): bool {
        /** @var array{cleanup_request_id: string, current: bool}|null $revoked */
        $revoked = DB::transaction(function () use (
            $user,
            $publicSessionId,
            $request,
            $reason,
        ): ?array {
            /** @var AuthenticationSession|null $authenticationSession */
            $authenticationSession = AuthenticationSession::query()
                ->whereKey($publicSessionId)
                ->where('user_id', (string) $user->getAuthIdentifier())
                ->lockForUpdate()
                ->first();

            if ($authenticationSession === null) {
                return null;
            }

            $wasActive = $authenticationSession->revoked_at === null;

            $authenticationSession->forceFill([
                'revoked_at' => $authenticationSession->revoked_at ?? Date::now(),
                'revocation_reason' => $authenticationSession->revocation_reason ?? $reason,
                'selected_organization_id' => null,
                'selected_membership_id' => null,
            ])->save();
            $cleanupRequestId = $this->cleanup->enqueue(
                $authenticationSession,
            );

            if ($wasActive) {
                $this->audit->recordPlatform(
                    $this->platformOperations
                        ->authorizeAuthenticationSessionAudit(
                            action: 'authentication.session.revoked',
                            reasonKey: $reason,
                            authenticationSessionId: (string) $authenticationSession->getKey(),
                            actorUserId: (string) $user->getAuthIdentifier(),
                            correlationId: CorrelationId::forRequest($request),
                        ),
                );
            }

            return [
                'cleanup_request_id' => $cleanupRequestId,
                'current' => hash_equals(
                    (string) $request->session()->get(self::SESSION_KEY, ''),
                    (string) $authenticationSession->getKey(),
                ),
            ];
        });

        if ($revoked === null) {
            return false;
        }

        $finalize = function () use ($revoked, $request): void {
            $this->cleanup->processOne($revoked['cleanup_request_id']);

            if ($revoked['current']) {
                $this->invalidateCurrent($request);
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($finalize);

            return true;
        }

        $finalize();

        return true;
    }

    public function revokeAll(
        Authenticatable $user,
        Request $request,
        string $reason = 'user_revoked_all',
    ): void {
        /** @var list<string> $cleanupRequestIds */
        $cleanupRequestIds = DB::transaction(function () use (
            $user,
            $request,
            $reason,
        ): array {
            /** @var Collection<int, AuthenticationSession> $sessions */
            $sessions = AuthenticationSession::query()
                ->where('user_id', (string) $user->getAuthIdentifier())
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get();

            AuthenticationSession::query()
                ->whereIn('id', $sessions->modelKeys())
                ->update([
                    'revoked_at' => Date::now(),
                    'revocation_reason' => $reason,
                    'selected_organization_id' => null,
                    'selected_membership_id' => null,
                    'updated_at' => Date::now(),
                ]);

            $cleanupRequestIds = [];

            foreach ($sessions as $authenticationSession) {
                $authenticationSession->forceFill([
                    'revoked_at' => Date::now(),
                    'revocation_reason' => $reason,
                    'selected_organization_id' => null,
                    'selected_membership_id' => null,
                ]);
                $cleanupRequestIds[] = $this->cleanup->enqueue(
                    $authenticationSession,
                );
                $this->audit->recordPlatform(
                    $this->platformOperations
                        ->authorizeAuthenticationSessionAudit(
                            action: 'authentication.session.revoked',
                            reasonKey: $reason,
                            authenticationSessionId: (string) $authenticationSession->getKey(),
                            actorUserId: (string) $user->getAuthIdentifier(),
                            correlationId: CorrelationId::forRequest($request),
                        ),
                );
            }

            return $cleanupRequestIds;
        });

        $finalize = function () use ($cleanupRequestIds, $request): void {
            foreach ($cleanupRequestIds as $cleanupRequestId) {
                $this->cleanup->processOne($cleanupRequestId);
            }

            $this->invalidateCurrent($request);
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($finalize);

            return;
        }

        $finalize();
    }

    public function revokeCurrent(
        Authenticatable $user,
        Request $request,
        string $reason = 'logout',
    ): void {
        $publicSessionId = $request->session()->get(self::SESSION_KEY);

        if (is_string($publicSessionId) && $publicSessionId !== '') {
            if ($this->revoke($user, $publicSessionId, $request, $reason)) {
                return;
            }
        }

        $this->invalidateCurrent($request);
    }

    public function hasFreshMfa(AuthenticationSession $authenticationSession): bool
    {
        if (! $authenticationSession->is_privileged) {
            return false;
        }

        $verifiedAt = $authenticationSession->mfa_verified_at;

        return $verifiedAt !== null
            && $authenticationSession->mfa_factor_id !== null
            && $authenticationSession->mfa_verified_method
                === AuthenticationSession::MFA_METHOD_TOTP
            && $authenticationSession->mfa_verified_scope
                === AuthenticationSession::MFA_SCOPE_PRIVILEGED_ACTIONS
            && $verifiedAt->greaterThan(
                Date::now()->subMinutes((int) config(
                    'authentication.privileged.mfa_fresh_minutes',
                    15,
                )),
            );
    }

    public function hasFreshMfaForFactorReplacement(
        AuthenticationSession $authenticationSession,
    ): bool {
        if (! $authenticationSession->is_privileged) {
            return false;
        }

        $verifiedAt = $authenticationSession->mfa_verified_at;
        $methodAndScopeAreAllowed = (
            $authenticationSession->mfa_verified_method
                === AuthenticationSession::MFA_METHOD_TOTP
            && $authenticationSession->mfa_verified_scope
                === AuthenticationSession::MFA_SCOPE_PRIVILEGED_ACTIONS
        ) || (
            $authenticationSession->mfa_verified_method
                === AuthenticationSession::MFA_METHOD_RECOVERY_CODE
            && $authenticationSession->mfa_verified_scope
                === AuthenticationSession::MFA_SCOPE_FACTOR_REPLACEMENT
        );

        return $verifiedAt !== null
            && $authenticationSession->mfa_factor_id !== null
            && $methodAndScopeAreAllowed
            && $verifiedAt->greaterThan(
                Date::now()->subMinutes((int) config(
                    'authentication.privileged.mfa_fresh_minutes',
                    15,
                )),
            );
    }

    private function invalidateCurrent(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function idleMinutes(bool $privileged): int
    {
        return (int) config(
            $privileged
                ? 'authentication.privileged.idle_minutes'
                : 'authentication.web.idle_minutes',
            $privileged ? 30 : 120,
        );
    }

    private function absoluteMinutes(bool $privileged): int
    {
        return (int) config(
            $privileged
                ? 'authentication.privileged.absolute_minutes'
                : 'authentication.web.absolute_minutes',
            $privileged ? 720 : 10_080,
        );
    }
}

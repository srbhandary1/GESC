<?php

declare(strict_types=1);

namespace App\Authentication\Password;

use App\Authentication\Sessions\PrivilegeClassifier;
use App\Models\PasswordRecoveryChallenge;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class PasswordRecoveryService
{
    public function __construct(
        private PasswordPolicy $policy,
        private RecoveryTokenDigester $digester,
        private PasswordRecoveryDeliveryAdapter $delivery,
        private PasswordCredentialService $passwords,
        private PrivilegeClassifier $privileges,
    ) {}

    public function request(string $username): void
    {
        try {
            $canonical = $this->policy->normalizeUsername($username);
        } catch (PasswordPolicyViolation) {
            $canonical = strtolower(trim($username));
        }

        $this->issue($canonical, false, null);
    }

    public function issuePrivileged(User $user, string $caseId): bool
    {
        if ($user->username === null || ! $user->passwordCredential()->exists()) {
            return $this->issueExcludedEstablishment($user, $caseId);
        }

        return $this->issue((string) $user->username, true, $caseId);
    }

    private function issue(string $canonical, bool $allowPrivileged, ?string $caseId): bool
    {
        $lookupDigest = $this->digester->digest('password-recovery-lookup', $canonical);
        $token = Str::random(64);
        $challengeId = (string) Str::ulid();

        /** @var User|null $user */
        $user = User::query()
            ->where('username', $canonical)
            ->where('status', User::STATUS_ACTIVE)
            ->first();
        $eligible = $user instanceof User
            && $user->passwordCredential()->exists()
            && ($allowPrivileged || ! $this->isPrivileged($user));
        /** @var UserIdentity|null $identity */
        $identity = $eligible
            ? UserIdentity::query()
                ->where('user_id', $user->getKey())
                ->where('status', UserIdentity::STATUS_VERIFIED)
                ->whereIn('type', ['phone', 'email'])
                ->orderBy('verified_at')
                ->first()
            : null;

        DB::transaction(function () use (
            $caseId,
            $challengeId,
            $identity,
            $lookupDigest,
            $token,
            $user,
        ): void {
            if ($user instanceof User) {
                PasswordRecoveryChallenge::query()
                    ->where('user_id', $user->getKey())
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->update([
                        'status' => 'superseded',
                        'superseded_at' => Date::now(),
                        'updated_at' => Date::now(),
                    ]);
            }

            PasswordRecoveryChallenge::query()->forceCreate([
                'id' => $challengeId,
                'user_id' => $identity instanceof UserIdentity && $user instanceof User
                    ? (string) $user->getKey()
                    : null,
                'recovery_identity_id' => $identity?->getKey(),
                'purpose' => PasswordRecoveryChallenge::PURPOSE_PASSWORD_RESET,
                'privileged_recovery_case_id' => $caseId,
                'lookup_digest' => $lookupDigest,
                'token_digest' => $this->digester->digest('password-recovery-token', $token),
                'status' => 'pending',
                'failed_attempts' => 0,
                'delivery_outcome' => $identity instanceof UserIdentity
                    ? 'pending'
                    : 'not_eligible',
                'expires_at' => Date::now()->addMinutes(15),
            ]);
        }, 3);

        if (! $identity instanceof UserIdentity) {
            return false;
        }

        try {
            $this->delivery->deliver($identity, $challengeId, $token);
            PasswordRecoveryChallenge::query()->whereKey($challengeId)->update([
                'delivery_outcome' => 'accepted',
                'updated_at' => Date::now(),
            ]);

            return true;
        } catch (Throwable) {
            PasswordRecoveryChallenge::query()->whereKey($challengeId)->update([
                'status' => 'superseded',
                'delivery_outcome' => 'unavailable',
                'superseded_at' => Date::now(),
                'updated_at' => Date::now(),
            ]);

            return false;
        }
    }

    private function issueExcludedEstablishment(User $user, string $caseId): bool
    {
        if ($user->status !== User::STATUS_ACTIVE
            || $user->username !== null
            || $user->passwordCredential()->exists()
            || ! DB::table('password_cutover_exclusions')->where('user_id', $user->getKey())->exists()) {
            return false;
        }

        /** @var UserIdentity|null $identity */
        $identity = UserIdentity::query()
            ->where('user_id', $user->getKey())
            ->where('status', UserIdentity::STATUS_VERIFIED)
            ->whereIn('type', ['phone', 'email'])
            ->orderBy('verified_at')
            ->first();

        if (! $identity instanceof UserIdentity) {
            return false;
        }

        $challengeId = (string) Str::ulid();
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        DB::transaction(function () use ($caseId, $challengeId, $identity, $token, $user): void {
            PasswordRecoveryChallenge::query()
                ->where('user_id', $user->getKey())
                ->where('status', PasswordRecoveryChallenge::STATUS_PENDING)
                ->lockForUpdate()
                ->update([
                    'status' => PasswordRecoveryChallenge::STATUS_SUPERSEDED,
                    'superseded_at' => Date::now(),
                    'updated_at' => Date::now(),
                ]);
            PasswordRecoveryChallenge::query()->forceCreate([
                'id' => $challengeId,
                'user_id' => (string) $user->getKey(),
                'recovery_identity_id' => (string) $identity->getKey(),
                'purpose' => PasswordRecoveryChallenge::PURPOSE_EXCLUDED_CREDENTIAL_ESTABLISHMENT,
                'privileged_recovery_case_id' => $caseId,
                'lookup_digest' => $this->digester->digest(
                    'password-establishment-lookup',
                    (string) $user->getKey(),
                ),
                'token_digest' => $this->digester->digest('password-recovery-token', $token),
                'status' => PasswordRecoveryChallenge::STATUS_PENDING,
                'failed_attempts' => 0,
                'delivery_outcome' => 'pending',
                'expires_at' => Date::now()->addMinutes(15),
            ]);
        }, 3);

        try {
            $this->delivery->deliver($identity, $challengeId, $token);
            PasswordRecoveryChallenge::query()->whereKey($challengeId)->update([
                'delivery_outcome' => 'accepted',
                'updated_at' => Date::now(),
            ]);

            return true;
        } catch (Throwable) {
            PasswordRecoveryChallenge::query()->whereKey($challengeId)->update([
                'status' => PasswordRecoveryChallenge::STATUS_SUPERSEDED,
                'delivery_outcome' => 'unavailable',
                'superseded_at' => Date::now(),
                'updated_at' => Date::now(),
            ]);

            return false;
        } finally {
            $token = '';
        }
    }

    public function consumeExcludedEstablishment(
        string $challengeId,
        string $username,
        string $token,
        string $password,
        Request $request,
    ): User {
        $result = DB::transaction(function () use (
            $challengeId,
            $password,
            $request,
            $token,
            $username,
        ): ?User {
            /** @var PasswordRecoveryChallenge|null $challenge */
            $challenge = PasswordRecoveryChallenge::query()
                ->whereKey($challengeId)->lockForUpdate()->first();

            if (! $challenge instanceof PasswordRecoveryChallenge) {
                return null;
            }

            $now = Date::now();
            $tokenMatches = hash_equals(
                $challenge->token_digest,
                $this->digester->digest('password-recovery-token', $token),
            );

            if ($challenge->purpose !== PasswordRecoveryChallenge::PURPOSE_EXCLUDED_CREDENTIAL_ESTABLISHMENT
                || $challenge->status !== PasswordRecoveryChallenge::STATUS_PENDING
                || $challenge->expires_at->lessThanOrEqualTo($now)
                || $challenge->failed_attempts >= 5
                || ! $tokenMatches
                || $challenge->user_id === null
                || $challenge->privileged_recovery_case_id === null) {
                $this->recordFailedAttempt($challenge, $now);

                return null;
            }

            /** @var User|null $user */
            $user = User::query()->whereKey($challenge->user_id)->lockForUpdate()->first();
            $caseValid = DB::table('privileged_password_recovery_cases')
                ->where('id', $challenge->privileged_recovery_case_id)
                ->where('target_user_id', $challenge->user_id)
                ->where('recovery_kind', PasswordRecoveryChallenge::PURPOSE_EXCLUDED_CREDENTIAL_ESTABLISHMENT)
                ->where('status', 'completed')
                ->exists();

            if (! $user instanceof User
                || $user->status !== User::STATUS_ACTIVE
                || $user->username !== null
                || $user->passwordCredential()->exists()
                || ! $caseValid
                || ! DB::table('password_cutover_exclusions')->where('user_id', $user->getKey())->exists()) {
                $this->recordFailedAttempt($challenge, $now);

                return null;
            }

            $this->passwords->establish($user, $username, $password, $request);
            $user->forceFill(['credential_cutover_at' => $now])->save();
            $challenge->forceFill([
                'status' => PasswordRecoveryChallenge::STATUS_CONSUMED,
                'consumed_at' => $now,
            ])->save();
            DB::table('password_security_events')->insert([
                'id' => (string) Str::ulid(),
                'user_id' => (string) $user->getKey(),
                'event_type' => 'password.excluded_establishment_completed',
                'outcome' => 'succeeded',
                'request_digest' => $this->digester->digest(
                    'password-establishment-event',
                    $challengeId."\0".(string) ($request->ip() ?? 'unknown'),
                ),
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $user->fresh() ?? $user;
        }, 3);

        if (! $result instanceof User) {
            throw new PasswordRecoveryFailed;
        }

        return $result;
    }

    public function consume(
        string $challengeId,
        string $username,
        string $token,
        string $newPassword,
        Request $request,
    ): User {
        try {
            $canonical = $this->policy->normalizeUsername($username);
        } catch (PasswordPolicyViolation) {
            throw new PasswordRecoveryFailed;
        }

        $result = DB::transaction(function () use (
            $canonical,
            $challengeId,
            $newPassword,
            $request,
            $token,
        ): ?User {
            /** @var PasswordRecoveryChallenge|null $challenge */
            $challenge = PasswordRecoveryChallenge::query()
                ->whereKey($challengeId)
                ->lockForUpdate()
                ->first();

            if (! $challenge instanceof PasswordRecoveryChallenge) {
                return null;
            }

            $now = Date::now();
            $tokenMatches = hash_equals(
                $challenge->token_digest,
                $this->digester->digest('password-recovery-token', $token),
            );
            $lookupMatches = hash_equals(
                $challenge->lookup_digest,
                $this->digester->digest('password-recovery-lookup', $canonical),
            );

            if (
                $challenge->status !== 'pending'
                || $challenge->expires_at->lessThanOrEqualTo($now)
                || $challenge->failed_attempts >= 5
                || ! $tokenMatches
                || ! $lookupMatches
                || $challenge->user_id === null
            ) {
                $attempts = min(5, $challenge->failed_attempts + 1);
                $challenge->forceFill([
                    'failed_attempts' => $attempts,
                    'status' => $challenge->expires_at->lessThanOrEqualTo($now)
                        || $attempts >= 5
                        ? 'expired'
                        : $challenge->status,
                ])->save();

                return null;
            }

            /** @var User|null $user */
            $user = User::query()->whereKey($challenge->user_id)->lockForUpdate()->first();

            if (! $user instanceof User || $user->status !== User::STATUS_ACTIVE) {
                $this->recordFailedAttempt($challenge, $now);

                return null;
            }

            $this->passwords->resetPassword($user, $newPassword, $request);
            $challenge->forceFill([
                'status' => 'consumed',
                'consumed_at' => $now,
            ])->save();

            return $user;
        }, 3);

        if (! $result instanceof User) {
            throw new PasswordRecoveryFailed;
        }

        return $result;
    }

    private function recordFailedAttempt(
        PasswordRecoveryChallenge $challenge,
        \DateTimeInterface $now,
    ): void {
        $attempts = min(5, $challenge->failed_attempts + 1);
        $expired = $challenge->expires_at->lessThanOrEqualTo($now) || $attempts >= 5;
        $challenge->forceFill([
            'failed_attempts' => $attempts,
            'status' => $expired
                ? PasswordRecoveryChallenge::STATUS_EXPIRED
                : $challenge->status,
        ])->save();
    }

    private function isPrivileged(User $user): bool
    {
        if ($this->privileges->isPrivileged($user)) {
            return true;
        }

        foreach ([
            config('platform.identity_recovery.actor_user_ids', []),
            config('platform.content_emergency.actor_user_ids', []),
        ] as $configured) {
            if (! is_array($configured)) {
                return true;
            }

            if (in_array((string) $user->getKey(), $configured, true)) {
                return true;
            }
        }

        return false;
    }
}

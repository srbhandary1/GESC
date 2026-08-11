<?php

declare(strict_types=1);

namespace App\Authentication\Password;

use App\Authentication\AuthenticationSecretDigester;
use App\Models\AuthenticationSession;
use App\Models\PasswordCredential;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class PasswordCredentialService
{
    private static ?string $dummyHash = null;

    public function __construct(
        private readonly PasswordPolicy $policy,
        private readonly AuthenticationSecretDigester $digester,
    ) {}

    public function authenticate(string $username, string $password, Request $request): User
    {
        try {
            $canonical = $this->policy->normalizeUsername($username);
        } catch (PasswordPolicyViolation) {
            $canonical = '__invalid__';
        }

        /** @var User|null $user */
        $user = User::query()
            ->where('username', $canonical)
            ->where('status', User::STATUS_ACTIVE)
            ->first();
        /** @var PasswordCredential|null $credential */
        $credential = $user instanceof User
            ? PasswordCredential::query()->whereKey($user->getKey())->first()
            : null;
        $hash = $credential instanceof PasswordCredential
            ? $credential->password_hash
            : $this->dummyHash();
        $verified = password_verify($password, $hash);

        $this->record(
            $user?->getKey() ? (string) $user->getKey() : null,
            'password.sign_in',
            $verified && $credential instanceof PasswordCredential ? 'succeeded' : 'failed',
            $canonical,
            $request,
        );

        if (! $verified || ! $user instanceof User || ! $credential instanceof PasswordCredential) {
            $this->progressiveDelay('password.sign_in', $canonical, $request);
            throw new PasswordAuthenticationFailed;
        }

        if ($this->needsRehash($credential->password_hash)) {
            $credential->forceFill([
                'password_hash' => $this->hash($password),
                'policy_version' => (int) config('authentication.password.policy_version', 1),
                'changed_at' => Date::now(),
            ])->save();
        }

        return $user;
    }

    public function establish(User $user, string $username, string $password, Request $request): void
    {
        $canonical = $this->policy->normalizeUsername($username);
        $normalized = $this->policy->normalizeAndValidatePassword($password, $canonical);

        DB::transaction(function () use ($user, $canonical, $normalized): void {
            /** @var User|null $locked */
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            if (! $locked instanceof User || $locked->status !== User::STATUS_ACTIVE) {
                throw new PasswordAuthenticationFailed;
            }

            if (PasswordCredential::query()->whereKey($locked->getKey())->exists()) {
                throw new PasswordPolicyViolation('A password is already established.');
            }

            if (User::query()
                ->where('username', $canonical)
                ->where('id', '<>', $locked->getKey())
                ->exists()) {
                throw new PasswordPolicyViolation('The username is unavailable.');
            }

            $now = Date::now();
            $version = max(1, ((int) $locked->credential_version) + 1);
            $locked->forceFill([
                'username' => $canonical,
                'username_established_at' => $now,
                'credential_version' => $version,
            ])->save();
            PasswordCredential::query()->forceCreate([
                'user_id' => (string) $locked->getKey(),
                'password_hash' => $this->hash($normalized),
                'algorithm' => 'argon2id',
                'policy_version' => (int) config('authentication.password.policy_version', 1),
                'credential_version' => $version,
                'established_at' => $now,
                'changed_at' => $now,
            ]);
        }, 3);

        $this->record((string) $user->getKey(), 'password.established', 'succeeded', $canonical, $request);
    }

    public function confirm(
        User $user,
        AuthenticationSession $session,
        string $password,
        Request $request,
    ): void {
        try {
            DB::transaction(function () use ($user, $session, $password): void {
                /** @var User|null $lockedUser */
                $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->first();
                /** @var PasswordCredential|null $credential */
                $credential = PasswordCredential::query()->whereKey($user->getKey())->lockForUpdate()->first();
                /** @var AuthenticationSession|null $lockedSession */
                $lockedSession = AuthenticationSession::query()->whereKey($session->getKey())->lockForUpdate()->first();

                if (
                    ! $lockedUser instanceof User
                    || ! $credential instanceof PasswordCredential
                    || ! $lockedSession instanceof AuthenticationSession
                    || ! $lockedSession->is_privileged
                    || $lockedSession->revoked_at !== null
                    || ! password_verify($password, $credential->password_hash)
                ) {
                    throw new PasswordAuthenticationFailed;
                }

                $lockedSession->forceFill([
                    'password_confirmed_at' => Date::now(),
                    'password_confirmed_credential_version' => (int) $lockedUser->credential_version,
                    'mfa_verified_at' => null,
                    'mfa_factor_id' => null,
                    'mfa_verified_method' => null,
                    'mfa_verified_scope' => null,
                ])->save();
            }, 3);
        } catch (PasswordAuthenticationFailed $exception) {
            $this->record((string) $user->getKey(), 'password.confirmed', 'failed', (string) $session->getKey(), $request);
            $this->progressiveDelay('password.confirmed', (string) $session->getKey(), $request);
            throw $exception;
        }

        $this->record((string) $user->getKey(), 'password.confirmed', 'succeeded', (string) $session->getKey(), $request);
    }

    public function changePassword(
        User $user,
        string $currentPassword,
        string $newPassword,
        Request $request,
    ): void {
        DB::transaction(function () use ($user, $currentPassword, $newPassword): void {
            /** @var User|null $lockedUser */
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->first();
            /** @var PasswordCredential|null $credential */
            $credential = PasswordCredential::query()->whereKey($user->getKey())->lockForUpdate()->first();

            if (
                ! $lockedUser instanceof User
                || ! $credential instanceof PasswordCredential
                || ! password_verify($currentPassword, $credential->password_hash)
            ) {
                throw new PasswordAuthenticationFailed;
            }

            $normalized = $this->policy->normalizeAndValidatePassword(
                $newPassword,
                (string) $lockedUser->username,
            );
            $version = ((int) $lockedUser->credential_version) + 1;
            $lockedUser->forceFill(['credential_version' => $version])->save();
            $credential->forceFill([
                'password_hash' => $this->hash($normalized),
                'policy_version' => (int) config('authentication.password.policy_version', 1),
                'credential_version' => $version,
                'changed_at' => Date::now(),
                'compromised_at' => null,
            ])->save();
            AuthenticationSession::query()
                ->where('user_id', $lockedUser->getKey())
                ->update([
                    'password_confirmed_at' => null,
                    'password_confirmed_credential_version' => null,
                ]);
        }, 3);

        $this->record((string) $user->getKey(), 'password.changed', 'succeeded', (string) $user->getKey(), $request);
    }

    public function changeUsername(
        User $user,
        AuthenticationSession $session,
        string $username,
        Request $request,
    ): void {
        if (! $this->hasFreshConfirmation($session)) {
            throw new FreshPasswordConfirmationRequired;
        }

        $canonical = $this->policy->normalizeUsername($username);

        DB::transaction(function () use ($user, $canonical): void {
            /** @var User|null $locked */
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            if (! $locked instanceof User || User::query()
                ->where('username', $canonical)
                ->where('id', '<>', $locked->getKey())
                ->exists()) {
                throw new PasswordPolicyViolation('The username is unavailable.');
            }

            $version = ((int) $locked->credential_version) + 1;
            $locked->forceFill([
                'username' => $canonical,
                'username_established_at' => Date::now(),
                'credential_version' => $version,
            ])->save();
            PasswordCredential::query()->whereKey($locked->getKey())->update([
                'credential_version' => $version,
                'changed_at' => Date::now(),
            ]);
            AuthenticationSession::query()->where('user_id', $locked->getKey())->update([
                'password_confirmed_at' => null,
                'password_confirmed_credential_version' => null,
            ]);
        }, 3);

        $this->record((string) $user->getKey(), 'username.changed', 'succeeded', $canonical, $request);
    }

    public function resetPassword(User $user, string $newPassword, Request $request): void
    {
        /** @var User|null $lockedUser */
        $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->first();
        /** @var PasswordCredential|null $credential */
        $credential = PasswordCredential::query()
            ->whereKey($user->getKey())
            ->lockForUpdate()
            ->first();

        if (! $lockedUser instanceof User || ! $credential instanceof PasswordCredential) {
            throw new PasswordRecoveryFailed;
        }

        $normalized = $this->policy->normalizeAndValidatePassword(
            $newPassword,
            (string) $lockedUser->username,
        );
        $version = ((int) $lockedUser->credential_version) + 1;
        $lockedUser->forceFill(['credential_version' => $version])->save();
        $credential->forceFill([
            'password_hash' => $this->hash($normalized),
            'policy_version' => (int) config('authentication.password.policy_version', 1),
            'credential_version' => $version,
            'changed_at' => Date::now(),
            'compromised_at' => null,
        ])->save();
        AuthenticationSession::query()->where('user_id', $lockedUser->getKey())->update([
            'password_confirmed_at' => null,
            'password_confirmed_credential_version' => null,
        ]);
        $this->record((string) $user->getKey(), 'password.recovered', 'succeeded', (string) $user->getKey(), $request);
    }

    public function hasFreshConfirmation(AuthenticationSession $session): bool
    {
        if (! $session->is_privileged || $session->password_confirmed_at === null) {
            return false;
        }

        $version = User::query()->whereKey($session->user_id)->value('credential_version');

        return $version !== null
            && $session->password_confirmed_credential_version === (int) $version
            && $session->password_confirmed_at->greaterThan(
                Date::now()->subMinutes((int) config(
                    'authentication.password.confirmation_minutes',
                    5,
                )),
            );
    }

    public function mode(): string
    {
        if (
            Schema::hasTable('password_authentication_cutovers')
            && DB::table('password_authentication_cutovers')
                ->where('scope_key', 'global')
                ->where('status', 'password_only')
                ->exists()
        ) {
            return 'password_only';
        }

        $mode = (string) config('authentication.mode', 'legacy_otp');

        if (! in_array($mode, ['legacy_otp', 'credential_migration', 'password_only'], true)) {
            throw new RuntimeException('The authentication mode is invalid.');
        }

        return $mode;
    }

    private function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, $this->options());
    }

    private function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, $this->options());
    }

    /** @return array{memory_cost: int, time_cost: int, threads: int} */
    private function options(): array
    {
        $options = [
            'memory_cost' => (int) config('authentication.password.argon_memory', 65_536),
            'time_cost' => (int) config('authentication.password.argon_time', 3),
            'threads' => (int) config('authentication.password.argon_threads', 1),
        ];

        if ($options['memory_cost'] < 65_536 || $options['time_cost'] < 3 || $options['threads'] < 1) {
            throw new RuntimeException('Password hashing configuration is below the accepted minimum.');
        }

        return $options;
    }

    private function dummyHash(): string
    {
        return self::$dummyHash ??= $this->hash('not-a-real-password-value');
    }

    private function record(
        ?string $userId,
        string $event,
        string $outcome,
        string $key,
        Request $request,
    ): void {
        DB::table('password_security_events')->insert([
            'id' => (string) str()->ulid(),
            'user_id' => $userId,
            'event_type' => $event,
            'outcome' => $outcome,
            'request_digest' => $this->digester->digest(
                'password-security-event',
                $key."\0".(string) ($request->ip() ?? 'unknown'),
            ),
            'occurred_at' => Date::now(),
            'created_at' => Date::now(),
            'updated_at' => Date::now(),
        ]);
    }

    private function progressiveDelay(string $event, string $key, Request $request): void
    {
        $digest = $this->digester->digest(
            'password-security-event',
            $key."\0".(string) ($request->ip() ?? 'unknown'),
        );
        $failures = DB::table('password_security_events')
            ->where('event_type', $event)
            ->where('outcome', 'failed')
            ->where('request_digest', $digest)
            ->where('occurred_at', '>=', Date::now()->subMinutes(15))
            ->count();
        $microseconds = min(1_000_000, 100_000 * (2 ** min(3, max(0, $failures - 1))));

        usleep((int) $microseconds);
    }
}

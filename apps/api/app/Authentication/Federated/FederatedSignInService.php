<?php

declare(strict_types=1);

namespace App\Authentication\Federated;

use App\Authentication\AuthenticationSecretDigester;
use App\Authentication\Password\PasswordAuthenticationFailed;
use App\Models\FederatedIdentity;
use App\Models\PasswordCredential;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class FederatedSignInService
{
    public function __construct(
        private readonly AuthenticationSecretDigester $secrets,
    ) {}

    /**
     * @return list<string>
     */
    public static function providers(): array
    {
        return [
            FederatedIdentity::PROVIDER_GOOGLE,
            FederatedIdentity::PROVIDER_FACEBOOK,
            FederatedIdentity::PROVIDER_INSTAGRAM,
        ];
    }

    public function isProvider(string $provider): bool
    {
        return in_array($provider, self::providers(), true);
    }

    public function existingUserFor(FederatedAccount $account): ?User
    {
        $identity = $this->findIdentity($account);

        if (! $identity instanceof FederatedIdentity) {
            return null;
        }

        /** @var User|null $user */
        $user = User::query()
            ->whereKey($identity->user_id)
            ->where('status', User::STATUS_ACTIVE)
            ->first();

        return $user instanceof User && $this->hasPasswordCredential($user)
            ? $user
            : null;
    }

    public function generatedUsernameFor(FederatedAccount $account): string
    {
        $prefix = match ($account->provider) {
            FederatedIdentity::PROVIDER_GOOGLE => 'gg',
            FederatedIdentity::PROVIDER_FACEBOOK => 'fb',
            FederatedIdentity::PROVIDER_INSTAGRAM => 'ig',
            default => 'sc',
        };

        return $prefix.'.'.substr($this->subjectDigest($account), 0, 20);
    }

    public function userFor(FederatedAccount $account): User
    {
        $identity = $this->findIdentity($account);

        if (! $identity instanceof FederatedIdentity) {
            throw new PasswordAuthenticationFailed;
        }

        /** @var User|null $user */
        $user = User::query()
            ->whereKey($identity->user_id)
            ->where('status', User::STATUS_ACTIVE)
            ->first();

        if (! $user instanceof User || ! $this->hasPasswordCredential($user)) {
            throw new PasswordAuthenticationFailed;
        }

        return $user;
    }

    public function link(User $user, FederatedAccount $account): FederatedIdentity
    {
        if ($user->status !== User::STATUS_ACTIVE || ! $this->hasPasswordCredential($user)) {
            throw new PasswordAuthenticationFailed;
        }

        $digest = $this->subjectDigest($account);

        return DB::transaction(function () use ($user, $account, $digest): FederatedIdentity {
            /** @var User|null $locked */
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            if (! $locked instanceof User) {
                throw new PasswordAuthenticationFailed;
            }

            $existingSubject = FederatedIdentity::query()
                ->where('provider', $account->provider)
                ->where('subject_digest', $digest)
                ->lockForUpdate()
                ->first();

            if ($existingSubject instanceof FederatedIdentity) {
                if ($existingSubject->user_id !== (string) $locked->getKey()) {
                    throw new PasswordAuthenticationFailed;
                }

                return $existingSubject;
            }

            $existingProvider = FederatedIdentity::query()
                ->where('user_id', $locked->getKey())
                ->where('provider', $account->provider)
                ->lockForUpdate()
                ->first();

            if ($existingProvider instanceof FederatedIdentity) {
                throw new PasswordAuthenticationFailed;
            }

            /** @var FederatedIdentity $created */
            $created = FederatedIdentity::query()->forceCreate([
                'user_id' => (string) $locked->getKey(),
                'provider' => $account->provider,
                'subject_digest' => $digest,
                'linked_at' => Date::now(),
            ]);

            return $created;
        }, 3);
    }

    public function unlink(User $user, string $provider): void
    {
        FederatedIdentity::query()
            ->where('user_id', $user->getKey())
            ->where('provider', $provider)
            ->delete();
    }

    /**
     * @return array{google: bool, facebook: bool, instagram: bool}
     */
    public function linksFor(User $user): array
    {
        $linked = FederatedIdentity::query()
            ->where('user_id', $user->getKey())
            ->pluck('provider')
            ->all();

        return [
            'google' => in_array(FederatedIdentity::PROVIDER_GOOGLE, $linked, true),
            'facebook' => in_array(FederatedIdentity::PROVIDER_FACEBOOK, $linked, true),
            'instagram' => in_array(FederatedIdentity::PROVIDER_INSTAGRAM, $linked, true),
        ];
    }

    public function record(
        ?string $userId,
        string $outcome,
        string $provider,
        Request $request,
    ): void {
        DB::table('password_security_events')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $userId,
            'event_type' => 'federated.sign_in',
            'outcome' => $outcome,
            'request_digest' => $this->secrets->digest(
                'federated-security-event',
                $provider."\0".(string) ($request->ip() ?? 'unknown'),
            ),
            'occurred_at' => Date::now(),
            'created_at' => Date::now(),
            'updated_at' => Date::now(),
        ]);
    }

    private function findIdentity(FederatedAccount $account): ?FederatedIdentity
    {
        /** @var FederatedIdentity|null */
        return FederatedIdentity::query()
            ->where('provider', $account->provider)
            ->where('subject_digest', $this->subjectDigest($account))
            ->first();
    }

    private function subjectDigest(FederatedAccount $account): string
    {
        return $this->secrets->digest(
            'federated-identity-'.$account->provider,
            $account->subject,
        );
    }

    private function hasPasswordCredential(User $user): bool
    {
        return PasswordCredential::query()->whereKey($user->getKey())->exists();
    }
}

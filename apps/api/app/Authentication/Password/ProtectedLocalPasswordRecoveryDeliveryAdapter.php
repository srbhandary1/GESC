<?php

declare(strict_types=1);

namespace App\Authentication\Password;

use App\Models\PasswordRecoveryChallenge;
use App\Models\UserIdentity;
use Illuminate\Support\Facades\Date;
use RuntimeException;

final class ProtectedLocalPasswordRecoveryDeliveryAdapter implements PasswordRecoveryDeliveryAdapter
{
    public function deliver(
        UserIdentity $identity,
        string $challengeId,
        string $token,
    ): void {
        $this->guardEnabled();
        $challenge = PasswordRecoveryChallenge::query()->whereKey($challengeId)->first();

        if (! $challenge instanceof PasswordRecoveryChallenge
            || $challenge->recovery_identity_id !== (string) $identity->getKey()
            || $challenge->status !== PasswordRecoveryChallenge::STATUS_PENDING) {
            throw new PasswordRecoveryDeliveryUnavailable('The protected local delivery is invalid.');
        }

        $directory = $this->directory();
        $temporary = tempnam($directory, '.pending-');

        if (! is_string($temporary)) {
            throw new PasswordRecoveryDeliveryUnavailable('The protected local delivery is unavailable.');
        }

        try {
            $payload = json_encode([
                'challenge_id' => $challengeId,
                'token' => $token,
            ], JSON_THROW_ON_ERROR);

            if (file_put_contents($temporary, $payload, LOCK_EX) !== strlen($payload)
                || ! chmod($temporary, 0600)
                || ! rename($temporary, $this->path($challengeId))) {
                throw new PasswordRecoveryDeliveryUnavailable('The protected local delivery is unavailable.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /** @return array{challenge_id: string, expires_at: string, recovery_hint: string}|null */
    public function latestEstablishmentMetadata(): ?array
    {
        $this->guardEnabled();
        /** @var PasswordRecoveryChallenge|null $challenge */
        $challenge = PasswordRecoveryChallenge::query()
            ->where('purpose', PasswordRecoveryChallenge::PURPOSE_ACCOUNT_SETUP)
            ->where('status', PasswordRecoveryChallenge::STATUS_PENDING)
            ->where('delivery_outcome', 'accepted')
            ->where('expires_at', '>', Date::now())
            ->latest('created_at')
            ->first();

        if (! $challenge instanceof PasswordRecoveryChallenge
            || ! is_file($this->path((string) $challenge->getKey()))) {
            return null;
        }

        $hint = UserIdentity::query()->whereKey($challenge->recovery_identity_id)
            ->value('display_hint');

        return [
            'challenge_id' => (string) $challenge->getKey(),
            'expires_at' => $challenge->expires_at->toIso8601String(),
            'recovery_hint' => is_string($hint) ? $hint : 'verified contact',
        ];
    }

    public function tokenFor(string $challengeId): string
    {
        $this->guardEnabled();
        $raw = file_get_contents($this->path($challengeId));
        $payload = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($payload)
            || array_keys($payload) !== ['challenge_id', 'token']
            || $payload['challenge_id'] !== $challengeId
            || ! is_string($payload['token'])) {
            throw new PasswordRecoveryFailed;
        }

        return $payload['token'];
    }

    public function forget(string $challengeId): void
    {
        $path = $this->path($challengeId);

        if (is_file($path)) {
            unlink($path);
        }
    }

    private function guardEnabled(): void
    {
        if (! app()->environment(['local', 'testing'])
            || config('authentication.password.recovery_delivery_driver') !== 'protected-local') {
            throw new PasswordRecoveryDeliveryUnavailable(
                'Protected local password recovery delivery is disabled.',
            );
        }
    }

    private function directory(): string
    {
        $path = config('authentication.password.protected_delivery_path');

        if (! is_string($path) || ! str_starts_with($path, '/tmp/') || str_contains($path, '..')) {
            throw new PasswordRecoveryDeliveryUnavailable('The protected delivery path is invalid.');
        }

        if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new PasswordRecoveryDeliveryUnavailable('The protected delivery directory is unavailable.');
        }

        if (! chmod($path, 0700)) {
            throw new PasswordRecoveryDeliveryUnavailable('The protected delivery directory is unavailable.');
        }

        return $path;
    }

    private function path(string $challengeId): string
    {
        if (preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/D', $challengeId) !== 1) {
            throw new RuntimeException('The protected delivery identifier is invalid.');
        }

        return $this->directory().DIRECTORY_SEPARATOR.$challengeId.'.json';
    }
}

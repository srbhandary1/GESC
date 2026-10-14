<?php

declare(strict_types=1);

namespace App\Authentication\Password;

use Normalizer;

final readonly class PasswordPolicy
{
    public function __construct(private PasswordBlocklist $blocklist) {}

    public function normalizeUsername(string $username): string
    {
        $trimmed = strtolower(trim($username));

        if (preg_match('/\A[a-z][a-z0-9._-]{3,63}\z/D', $trimmed) === 1) {
            return $trimmed;
        }

        $compact = preg_replace('/[\s().-]+/', '', trim($username));

        if (is_string($compact) && preg_match('/\A\+?[0-9]{7,20}\z/D', $compact) === 1) {
            return $compact;
        }

        throw new PasswordPolicyViolation('The username is not valid.');
    }

    public function normalizeEstablishmentUsername(string $username): string
    {
        $trimmed = strtolower(trim($username));

        if (preg_match('/\A[a-z][a-z0-9._-]{3,63}\z/D', $trimmed) !== 1) {
            throw new PasswordPolicyViolation('The username is not valid.');
        }

        return $trimmed;
    }

    public function isImportablePasswordHash(string $hash): bool
    {
        if ($hash === '' || strlen($hash) > 255) {
            return false;
        }

        $info = password_get_info($hash);

        return ($info['algo'] ?? null) === PASSWORD_BCRYPT;
    }

    public function normalizeAndValidatePassword(
        string $password,
        string $username,
    ): string {
        $normalized = Normalizer::normalize($password, Normalizer::FORM_C);

        if (! is_string($normalized)) {
            throw new PasswordPolicyViolation('The password could not be normalized.');
        }

        $length = mb_strlen($normalized, 'UTF-8');

        if ($length < 15 || $length > 128) {
            throw new PasswordPolicyViolation('The password must contain 15 to 128 characters.');
        }

        if ($this->blocklist->contains($normalized, $username)) {
            throw new PasswordPolicyViolation('Choose a less common password.');
        }

        return $normalized;
    }
}

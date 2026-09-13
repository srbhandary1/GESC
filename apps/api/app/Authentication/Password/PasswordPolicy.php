<?php

declare(strict_types=1);

namespace App\Authentication\Password;

use Normalizer;

final readonly class PasswordPolicy
{
    public function __construct(private PasswordBlocklist $blocklist) {}

    public function normalizeUsername(string $username): string
    {
        $canonical = strtolower(trim($username));

        if (preg_match('/\A[a-z][a-z0-9._-]{3,63}\z/D', $canonical) !== 1) {
            throw new PasswordPolicyViolation('The username is not valid.');
        }

        return $canonical;
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

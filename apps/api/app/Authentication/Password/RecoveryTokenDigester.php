<?php

declare(strict_types=1);

namespace App\Authentication\Password;

use RuntimeException;

final class RecoveryTokenDigester
{
    public function digest(string $purpose, string $value): string
    {
        $configured = config('authentication.password.recovery_digest_key');

        if (! is_string($configured) || $configured === '') {
            throw new RuntimeException('AUTH_RECOVERY_DIGEST_KEY must be configured.');
        }

        $key = $configured;

        if (str_starts_with($configured, 'base64:')) {
            $decoded = base64_decode(substr($configured, 7), true);

            if (! is_string($decoded) || strlen($decoded) < 32) {
                throw new RuntimeException('AUTH_RECOVERY_DIGEST_KEY is invalid.');
            }

            $key = $decoded;
        }

        if (strlen($key) < 32) {
            throw new RuntimeException('AUTH_RECOVERY_DIGEST_KEY must contain at least 256 bits.');
        }

        return hash_hmac('sha256', $purpose."\0".$value, $key);
    }
}

<?php

declare(strict_types=1);

namespace App\Identity;

use InvalidArgumentException;
use RuntimeException;

final class IdentityIdentifierDigester
{
    private const string LOCAL_EXAMPLE_KEY =
        'base64:Z2xvYmFsLWVuZ2xpc2gtaWRlbnRpdHktbG9jYWwta2V5';

    public function digest(string $type, string $normalizedIdentifier): string
    {
        if (
            preg_match('/\A[a-z][a-z0-9_-]{1,39}\z/', $type) !== 1
            || $normalizedIdentifier === ''
            || strlen($normalizedIdentifier) > 512
        ) {
            throw new InvalidArgumentException('A normalized identity type and identifier are required.');
        }

        return hash_hmac(
            'sha256',
            "identity\0".$type."\0".$normalizedIdentifier,
            $this->identityKey(),
        );
    }

    private function identityKey(): string
    {
        $configured = config('authentication.identity.digest_key');

        if (
            ! is_string($configured)
            || $configured === ''
            || trim($configured) !== $configured
            || (
                app()->environment('production')
                && hash_equals(self::LOCAL_EXAMPLE_KEY, $configured)
            )
        ) {
            throw new RuntimeException(
                'A dedicated identity digest key is required.',
            );
        }

        if (str_starts_with($configured, 'base64:')) {
            $decoded = base64_decode(substr($configured, 7), true);

            if (! is_string($decoded) || strlen($decoded) < 32) {
                throw new RuntimeException(
                    'The identity digest key must contain at least 32 bytes.',
                );
            }

            return $decoded;
        }

        if (strlen($configured) < 32) {
            throw new RuntimeException(
                'The identity digest key must contain at least 32 bytes.',
            );
        }

        return $configured;
    }
}

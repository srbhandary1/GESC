<?php

declare(strict_types=1);

namespace App\Authentication;

use RuntimeException;

final class AuthenticationSecretDigester
{
    public function digest(string $purpose, string $value): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('APP_KEY must be configured before authentication is used.');
        }

        return hash_hmac('sha256', $purpose."\0".$value, $key);
    }
}

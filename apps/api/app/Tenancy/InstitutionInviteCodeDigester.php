<?php

declare(strict_types=1);

namespace App\Tenancy;

use InvalidArgumentException;
use RuntimeException;

final class InstitutionInviteCodeDigester
{
    public function digest(string $rawCode): string
    {
        if ($rawCode === '' || strlen($rawCode) > 256) {
            throw new InvalidArgumentException('An institution invitation code is required.');
        }

        return hash_hmac(
            'sha256',
            "institution-invite\0".$rawCode,
            $this->applicationKey(),
        );
    }

    private function applicationKey(): string
    {
        $configured = config('app.key');

        if (! is_string($configured) || $configured === '') {
            throw new RuntimeException('APP_KEY is required to digest invitation codes.');
        }

        if (str_starts_with($configured, 'base64:')) {
            $decoded = base64_decode(substr($configured, 7), true);

            if (! is_string($decoded) || $decoded === '') {
                throw new RuntimeException('APP_KEY is not valid base64.');
            }

            return $decoded;
        }

        return $configured;
    }
}

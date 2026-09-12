<?php

declare(strict_types=1);

namespace App\Authentication\Mfa;

use App\Authentication\AuthenticationSecretDigester;

final readonly class RecoveryCodeManager
{
    public const int CODE_COUNT = 10;

    public function __construct(private AuthenticationSecretDigester $secrets) {}

    /**
     * @return list<string>
     */
    public function generate(): array
    {
        $codes = [];

        for ($index = 0; $index < self::CODE_COUNT; $index++) {
            $raw = bin2hex(random_bytes(16));
            $codes[] = implode('-', str_split($raw, 8));
        }

        return $codes;
    }

    public function digest(string $factorId, string $code): ?string
    {
        $normalized = strtolower((string) preg_replace('/[-\s]/', '', $code));

        if (preg_match('/\A[0-9a-f]{32}\z/', $normalized) !== 1) {
            return null;
        }

        return $this->secrets->digest(
            'privileged-mfa-recovery:'.$factorId,
            $normalized,
        );
    }
}

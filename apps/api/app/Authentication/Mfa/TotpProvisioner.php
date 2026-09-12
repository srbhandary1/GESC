<?php

declare(strict_types=1);

namespace App\Authentication\Mfa;

final class TotpProvisioner
{
    private const string BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        return self::encodeBase32(random_bytes(20));
    }

    public function provisioningUri(string $base32Secret, string $userId): string
    {
        $issuer = (string) config('app.name', 'Global English');
        $account = $issuer.':'.$userId;

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($account),
            rawurlencode($base32Secret),
            rawurlencode($issuer),
            Rfc6238PrivilegedMfaVerifier::DIGITS,
            Rfc6238PrivilegedMfaVerifier::PERIOD_SECONDS,
        );
    }

    public static function encodeBase32(string $bytes): string
    {
        $buffer = 0;
        $bits = 0;
        $encoded = '';

        foreach (unpack('C*', $bytes) ?: [] as $byte) {
            $buffer = ($buffer << 8) | $byte;
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $encoded .= self::BASE32_ALPHABET[($buffer >> $bits) & 31];
                $buffer &= (1 << $bits) - 1;
            }
        }

        if ($bits > 0) {
            $encoded .= self::BASE32_ALPHABET[($buffer << (5 - $bits)) & 31];
        }

        return $encoded;
    }

    public static function decodeBase32(string $encoded): ?string
    {
        $encoded = strtoupper(trim($encoded));

        if ($encoded === '' || preg_match('/\A[A-Z2-7]+\z/', $encoded) !== 1) {
            return null;
        }

        $buffer = 0;
        $bits = 0;
        $decoded = '';

        foreach (str_split($encoded) as $character) {
            $value = strpos(self::BASE32_ALPHABET, $character);

            if ($value === false) {
                return null;
            }

            $buffer = ($buffer << 5) | $value;
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $decoded .= chr(($buffer >> $bits) & 0xFF);
                $buffer &= (1 << $bits) - 1;
            }
        }

        return $decoded;
    }
}

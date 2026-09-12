<?php

declare(strict_types=1);

namespace App\Authentication\Mfa;

use Illuminate\Support\Facades\Date;

final class Rfc6238PrivilegedMfaVerifier implements PrivilegedMfaVerifier
{
    public const int DIGITS = 6;

    public const int PERIOD_SECONDS = 30;

    public const int ALLOWED_DRIFT_STEPS = 1;

    public function verify(
        string $base32Secret,
        string $assertion,
        ?int $lastUsedTimeStep,
    ): ?int {
        if (preg_match('/\A[0-9]{6}\z/', $assertion) !== 1) {
            return null;
        }

        $secret = TotpProvisioner::decodeBase32($base32Secret);

        if ($secret === null || strlen($secret) < 20) {
            return null;
        }

        $currentStep = intdiv(
            Date::now()->getTimestamp(),
            self::PERIOD_SECONDS,
        );
        $matchedStep = null;

        for (
            $step = $currentStep - self::ALLOWED_DRIFT_STEPS;
            $step <= $currentStep + self::ALLOWED_DRIFT_STEPS;
            $step++
        ) {
            if (
                $step < 0
                || ($lastUsedTimeStep !== null && $step <= $lastUsedTimeStep)
            ) {
                continue;
            }

            if (hash_equals($this->codeAtStep($secret, $step), $assertion)) {
                // A six-digit value can very rarely collide across accepted
                // steps. Persist the highest match so the same assertion can
                // never be accepted again through a later matching step.
                $matchedStep = $step;
            }
        }

        return $matchedStep;
    }

    public function codeForTesting(string $base32Secret, int $timestamp): string
    {
        $secret = TotpProvisioner::decodeBase32($base32Secret);

        if ($secret === null || strlen($secret) < 20) {
            throw new \InvalidArgumentException('A valid 160-bit TOTP secret is required.');
        }

        return $this->codeAtStep(
            $secret,
            intdiv($timestamp, self::PERIOD_SECONDS),
        );
    }

    private function codeAtStep(string $secret, int $step): string
    {
        $counter = pack(
            'N2',
            intdiv($step, 4_294_967_296),
            $step % 4_294_967_296,
        );
        $digest = hash_hmac('sha1', $counter, $secret, true);
        $offset = ord($digest[19]) & 0x0F;
        $binary = (
            ((ord($digest[$offset]) & 0x7F) << 24)
            | ((ord($digest[$offset + 1]) & 0xFF) << 16)
            | ((ord($digest[$offset + 2]) & 0xFF) << 8)
            | (ord($digest[$offset + 3]) & 0xFF)
        );

        return str_pad(
            (string) ($binary % (10 ** self::DIGITS)),
            self::DIGITS,
            '0',
            STR_PAD_LEFT,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Learning\Offline;

use App\Curriculum\CanonicalJson;
use App\Learning\Exceptions\LearningOperationRejected;
use JsonException;

final class OfflineLeaseSigner
{
    /**
     * @param  array<string, mixed>  $claims
     */
    public function issue(array $claims): string
    {
        $payload = CanonicalJson::encode($claims);
        $signature = hash_hmac(
            'sha256',
            $payload,
            $this->signingKey(),
            true,
        );

        return $this->base64UrlEncode($payload)
            .'.'
            .$this->base64UrlEncode($signature);
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $token): array
    {
        if (strlen($token) > 8192) {
            throw new LearningOperationRejected;
        }

        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            throw new LearningOperationRejected;
        }

        $payload = $this->base64UrlDecode($parts[0]);
        $providedSignature = $this->base64UrlDecode($parts[1]);
        $expectedSignature = hash_hmac(
            'sha256',
            $payload,
            $this->signingKey(),
            true,
        );

        if (
            strlen($providedSignature) !== strlen($expectedSignature)
            || ! hash_equals($expectedSignature, $providedSignature)
        ) {
            throw new LearningOperationRejected;
        }

        try {
            $claims = json_decode(
                $payload,
                true,
                64,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            throw new LearningOperationRejected;
        }

        if (
            ! is_array($claims)
            || array_is_list($claims)
            || ! hash_equals(CanonicalJson::encode($claims), $payload)
        ) {
            throw new LearningOperationRejected;
        }

        return $claims;
    }

    private function signingKey(): string
    {
        $configured = config('offline.signing_key');

        if (! is_string($configured) || $configured === '') {
            throw new LearningOperationRejected;
        }

        $key = $configured;

        if (str_starts_with($configured, 'base64:')) {
            $decoded = base64_decode(substr($configured, 7), true);

            if (! is_string($decoded)) {
                throw new LearningOperationRejected;
            }

            $key = $decoded;
        }

        if (strlen($key) < 32) {
            throw new LearningOperationRejected;
        }

        return $key;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        if (
            $value === ''
            || preg_match('/\A[A-Za-z0-9_-]+\z/', $value) !== 1
        ) {
            throw new LearningOperationRejected;
        }

        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(
            strtr($value, '-_', '+/').str_repeat('=', $padding),
            true,
        );

        if (! is_string($decoded)) {
            throw new LearningOperationRejected;
        }

        return $decoded;
    }
}

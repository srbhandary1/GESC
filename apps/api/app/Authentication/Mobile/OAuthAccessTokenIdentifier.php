<?php

declare(strict_types=1);

namespace App\Authentication\Mobile;

use RuntimeException;

final class OAuthAccessTokenIdentifier
{
    public function fromEncodedToken(string $accessToken): string
    {
        $segments = explode('.', $accessToken);

        if (count($segments) !== 3) {
            throw new RuntimeException('The issued OAuth access token is malformed.');
        }

        $payload = base64_decode(strtr($segments[1], '-_', '+/'), true);
        $decoded = is_string($payload) ? json_decode($payload, true) : null;
        $identifier = is_array($decoded) ? ($decoded['jti'] ?? null) : null;

        if (! is_string($identifier) || $identifier === '') {
            throw new RuntimeException('The issued OAuth access token has no identifier.');
        }

        return $identifier;
    }
}

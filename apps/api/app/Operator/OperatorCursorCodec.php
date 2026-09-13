<?php

declare(strict_types=1);

namespace App\Operator;

use App\Operator\Exceptions\InvalidOperatorCursor;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use JsonException;
use LogicException;

final class OperatorCursorCodec
{
    /**
     * @param  array<string, mixed>  $position
     */
    public function encode(
        string $purpose,
        string $organizationId,
        array $position,
    ): string {
        try {
            $payload = json_encode([
                'version' => 1,
                'purpose' => $purpose,
                'organization_id' => $organizationId,
                'position' => $position,
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new LogicException(
                'The operator cursor position is not encodable.',
                previous: $exception,
            );
        }

        return Crypt::encryptString($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function decode(
        string $cursor,
        string $purpose,
        string $organizationId,
    ): array {
        try {
            $payload = json_decode(
                Crypt::decryptString($cursor),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (DecryptException|JsonException) {
            throw new InvalidOperatorCursor;
        }

        if (
            ! is_array($payload)
            || ($payload['version'] ?? null) !== 1
            || ($payload['purpose'] ?? null) !== $purpose
            || ($payload['organization_id'] ?? null) !== $organizationId
            || ! is_array($payload['position'] ?? null)
        ) {
            throw new InvalidOperatorCursor;
        }

        /** @var array<string, mixed> $position */
        $position = $payload['position'];

        return $position;
    }
}

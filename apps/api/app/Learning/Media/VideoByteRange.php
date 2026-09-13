<?php

declare(strict_types=1);

namespace App\Learning\Media;

use App\Learning\Exceptions\LearningOperationRejected;

final readonly class VideoByteRange
{
    private function __construct(
        public int $start,
        public int $end,
        public int $totalBytes,
        public bool $partial,
    ) {}

    public static function fromRequest(
        ?string $rangeHeader,
        ?string $ifRangeHeader,
        int $totalBytes,
        string $etag,
    ): self {
        if (
            $totalBytes < 1
            || preg_match('/\A"[0-9a-f]{64}"\z/', $etag) !== 1
        ) {
            throw new LearningOperationRejected;
        }

        $full = new self(0, $totalBytes - 1, $totalBytes, false);

        if ($rangeHeader === null || trim($rangeHeader) === '') {
            return $full;
        }

        if (
            $ifRangeHeader !== null
            && trim($ifRangeHeader) !== ''
            && ! hash_equals($etag, trim($ifRangeHeader))
        ) {
            return $full;
        }

        $matched = preg_match(
            '/\Abytes=(\d*)-(\d*)\z/i',
            trim($rangeHeader),
            $parts,
        );

        // Multiple or malformed ranges are ignored. The endpoint deliberately
        // serves only one range and never constructs multipart bodies.
        if ($matched !== 1 || ($parts[1] === '' && $parts[2] === '')) {
            return $full;
        }

        if ($parts[1] === '') {
            $suffix = self::normalizedDecimal($parts[2]);

            if ($suffix === '0') {
                throw new VideoRangeNotSatisfiable($totalBytes, $etag);
            }

            $start = self::decimalGreaterThanOrEqual(
                $suffix,
                $totalBytes,
            )
                ? 0
                : $totalBytes - (int) $suffix;

            return new self($start, $totalBytes - 1, $totalBytes, true);
        }

        $first = self::normalizedDecimal($parts[1]);
        $last = $parts[2] === ''
            ? null
            : self::normalizedDecimal($parts[2]);

        if (
            ($last !== null && self::compareDecimals($first, $last) > 0)
            || self::decimalGreaterThanOrEqual($first, $totalBytes)
        ) {
            if ($last !== null && self::compareDecimals($first, $last) > 0) {
                return $full;
            }

            throw new VideoRangeNotSatisfiable($totalBytes, $etag);
        }

        $start = (int) $first;
        $end = $last === null
            || self::decimalGreaterThanOrEqual($last, $totalBytes)
                ? $totalBytes - 1
                : (int) $last;

        return new self($start, $end, $totalBytes, true);
    }

    public function length(): int
    {
        return $this->end - $this->start + 1;
    }

    private static function normalizedDecimal(string $value): string
    {
        $normalized = ltrim($value, '0');

        return $normalized === '' ? '0' : $normalized;
    }

    private static function decimalGreaterThanOrEqual(
        string $value,
        int $comparison,
    ): bool {
        return self::compareDecimals($value, (string) $comparison) >= 0;
    }

    private static function compareDecimals(string $left, string $right): int
    {
        $length = strlen($left) <=> strlen($right);

        return $length !== 0 ? $length : strcmp($left, $right);
    }
}

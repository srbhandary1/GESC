<?php

declare(strict_types=1);

namespace App\Curriculum\Governance;

use App\Curriculum\Exceptions\CurriculumOperationRejected;
use Illuminate\Support\Str;

final class GovernanceInput
{
    public static function ulid(string $value): string
    {
        if (! Str::isUlid($value)) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    public static function sha256(string $value): string
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $value) !== 1) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    public static function key(string $value, int $maximumLength = 100): string
    {
        if (
            $maximumLength < 1
            || strlen($value) > $maximumLength
            || preg_match('/\A[a-z][a-z0-9._-]*\z/', $value) !== 1
        ) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    public static function idempotencyKey(string $value): string
    {
        if (
            preg_match(
                '/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/',
                $value,
            ) !== 1
        ) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    public static function evidenceReference(string $value): string
    {
        if (
            strlen($value) > 120
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:\/-]*\z/', $value) !== 1
        ) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    public static function text(
        string $value,
        int $maximumLength,
    ): string {
        $value = trim($value);

        if (
            $value === ''
            || mb_strlen($value) > $maximumLength
            || str_contains($value, "\0")
        ) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    public static function stableKeyList(
        array $values,
        int $maximumLength = 80,
    ): array {
        if ($values === []) {
            throw new CurriculumOperationRejected;
        }

        $normalized = [];

        foreach ($values as $value) {
            if (
                strlen($value) > $maximumLength
                || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $value) !== 1
            ) {
                throw new CurriculumOperationRejected;
            }

            $normalized[$value] = $value;
        }

        ksort($normalized, SORT_STRING);

        return array_values($normalized);
    }
}

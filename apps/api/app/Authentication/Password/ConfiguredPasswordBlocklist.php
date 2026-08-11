<?php

declare(strict_types=1);

namespace App\Authentication\Password;

use RuntimeException;

final class ConfiguredPasswordBlocklist implements PasswordBlocklist
{
    public function contains(string $normalizedCandidate, string $username): bool
    {
        $candidate = mb_strtolower($normalizedCandidate, 'UTF-8');
        $blocked = array_map(
            static fn (string $value): string => mb_strtolower(trim($value), 'UTF-8'),
            (array) config('authentication.password.blocked', []),
        );
        $path = config('authentication.password.blocklist_path');

        if (is_string($path) && $path !== '') {
            if (! str_starts_with($path, '/') || ! is_file($path) || ! is_readable($path)) {
                throw new RuntimeException('The password blocklist is unavailable.');
            }

            $expected = strtolower((string) config(
                'authentication.password.blocklist_sha256',
                '',
            ));
            $actual = hash_file('sha256', $path);

            if ($expected === '' || ! is_string($actual) || ! hash_equals($expected, $actual)) {
                throw new RuntimeException('The password blocklist integrity check failed.');
            }

            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $value) {
                $blocked[] = mb_strtolower(trim($value), 'UTF-8');
            }
        }

        return in_array($candidate, array_unique([
            ...$blocked,
            $username,
            'globalenglish',
            'global spoken english',
            'globalspokenenglish',
        ]), true);
    }
}

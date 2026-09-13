<?php

declare(strict_types=1);

namespace App\Identity;

use App\Identity\Exceptions\LocalOperatorIdentityBootstrapRejected;

final class ProtectedLocalIdentityInputReader
{
    public function read(string $path): string
    {
        $temporaryRoot = realpath(sys_get_temp_dir());
        $resolvedPath = realpath($path);
        $initial = @lstat($path);

        if (
            $path === ''
            || str_contains($path, "\0")
            || ! str_starts_with($path, DIRECTORY_SEPARATOR)
            || ! is_string($temporaryRoot)
            || ! is_string($resolvedPath)
            || ! is_array($initial)
            || is_link($path)
            || ! $this->isInside($resolvedPath, $temporaryRoot)
            || ! $this->isProtectedRegularFile($initial)
        ) {
            throw new LocalOperatorIdentityBootstrapRejected;
        }

        $handle = @fopen($resolvedPath, 'rb');

        if ($handle === false) {
            throw new LocalOperatorIdentityBootstrapRejected;
        }

        try {
            $opened = fstat($handle);

            if (
                ! is_array($opened)
                || ! $this->sameFile($initial, $opened)
                || ! $this->isProtectedRegularFile($opened)
            ) {
                throw new LocalOperatorIdentityBootstrapRejected;
            }

            $contents = stream_get_contents($handle, 65);

            if (! is_string($contents) || strlen($contents) > 64) {
                throw new LocalOperatorIdentityBootstrapRejected;
            }
        } finally {
            fclose($handle);
        }

        $value = rtrim($contents, "\r\n");

        if (
            $value === ''
            || str_contains($value, "\n")
            || str_contains($value, "\r")
        ) {
            throw new LocalOperatorIdentityBootstrapRejected;
        }

        return $value;
    }

    private function isInside(string $path, string $root): bool
    {
        return $path !== $root
            && str_starts_with($path, $root.DIRECTORY_SEPARATOR);
    }

    /** @param array<string|int, mixed> $stat */
    private function isProtectedRegularFile(array $stat): bool
    {
        $mode = $stat['mode'] ?? null;
        $uid = $stat['uid'] ?? null;

        if (
            ! is_int($mode)
            || ! is_int($uid)
            || ! function_exists('posix_geteuid')
            || $uid !== posix_geteuid()
            || ($mode & 0170000) !== 0100000
        ) {
            return false;
        }

        return in_array($mode & 0777, [0400, 0600], true);
    }

    /**
     * @param  array<string|int, mixed>  $before
     * @param  array<string|int, mixed>  $after
     */
    private function sameFile(array $before, array $after): bool
    {
        foreach (['dev', 'ino', 'uid', 'mode'] as $key) {
            if (
                ! array_key_exists($key, $before)
                || ! array_key_exists($key, $after)
                || $before[$key] !== $after[$key]
            ) {
                return false;
            }
        }

        return true;
    }
}

#!/bin/sh

set -eu

expected_node="$(cat .node-version)"
actual_node="$(node --version | sed 's/^v//')"

if [ "$actual_node" != "$expected_node" ]; then
    echo "Node.js $expected_node is required; found $actual_node." >&2
    exit 1
fi

expected_pnpm="$(node -p "require('./package.json').packageManager.split('@').at(-1)")"
actual_pnpm="$(pnpm --version)"

if [ "$actual_pnpm" != "$expected_pnpm" ]; then
    echo "pnpm $expected_pnpm is required; found $actual_pnpm." >&2
    exit 1
fi

expected_php="$(cat .php-version)"
expected_php_series="$(printf '%s' "$expected_php" | cut -d. -f1-2)"
actual_php="$(php -r 'echo PHP_VERSION;')"
actual_php_series="$(printf '%s' "$actual_php" | cut -d. -f1-2)"

if [ "$actual_php_series" != "$expected_php_series" ]; then
    echo "PHP $expected_php_series.x is required; found $actual_php." >&2
    exit 1
fi

if [ "$actual_php" != "$expected_php" ]; then
    echo "Warning: PHP $expected_php is pinned; local verification is using $actual_php." >&2
fi

echo "Toolchain ready: Node.js $actual_node, pnpm $actual_pnpm, PHP $actual_php."

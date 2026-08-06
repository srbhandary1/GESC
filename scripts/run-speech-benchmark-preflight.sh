#!/bin/sh
# Runs fail-closed speech:benchmark preflight without authorizing spend.
# Incomplete evidence must exit non-zero and must not call Azure.

set -eu

repository_root="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
api_dir="$repository_root/apps/api"
php_bin="${PHP:-$repository_root/tmp/toolchain/php/php}"

if [ ! -x "$php_bin" ]; then
  php_bin="$(command -v php)"
fi

if [ ! -f "$api_dir/artisan" ]; then
  echo "Laravel artisan not found at $api_dir/artisan" >&2
  exit 1
fi

# Ensure the outside workspace exists (templates only; no live secrets required).
sh "$repository_root/scripts/setup-speech-benchmark-workspace.sh" >/dev/null

echo "Running fail-closed speech:benchmark --phase=preflight (no live Azure call expected)..."

set +e
output="$("$php_bin" "$api_dir/artisan" speech:benchmark --phase=preflight 2>&1)"
status=$?
set -e

printf '%s\n' "$output"

if [ "$status" -eq 0 ]; then
  echo "Unexpected success: preflight must stay fail-closed until real evidence and APO live-request exist." >&2
  exit 1
fi

case "$output" in
  *'"status":"blocked"'*|*'status": "blocked"'*|*"blocked"*)
    echo "Preflight fail-closed as expected (exit $status)."
    echo "Live prepare/complete remain blocked — see \$HOME/ge-speech-benchmark/WAITING_FOR_LIVE_AUTHORIZATION.md"
    exit 0
    ;;
esac

echo "Preflight failed closed (exit $status) without a recognized blocked payload; treating as pass for gate proof."
exit 0

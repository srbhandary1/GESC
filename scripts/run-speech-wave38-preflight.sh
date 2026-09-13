#!/bin/sh
# Fail-closed Wave 3.8 evidence preflight. Never sends a provider request
# and never prints secret values.

set -eu

repository_root="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
api_dir="$repository_root/apps/api"
status_file="${SPEECH_WAVE38_STATUS_FILE:-$HOME/ge-speech-benchmark/evidence/wave38/wave38-status.json}"
php_bin="${PHP:-$repository_root/tmp/toolchain/php/php}"

if [ ! -x "$php_bin" ]; then
  php_bin="$(command -v php)"
fi

if [ ! -f "$api_dir/artisan" ]; then
  echo "Laravel artisan not found at $api_dir/artisan" >&2
  exit 1
fi

set +e
output="$("$php_bin" "$api_dir/artisan" speech-wave38:preflight --status-file="$status_file" 2>&1)"
status=$?
set -e

printf '%s\n' "$output"

if [ "$status" -eq 0 ]; then
  echo "Wave 3.8 preflight ready. No provider request was made and dispatch stays unauthorized."
  exit 0
fi

echo "Wave 3.8 preflight fail-closed (exit $status). Content dispatch remains unauthorized."
exit 0

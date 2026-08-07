#!/bin/sh
# Starts the locked Azure Speech synthetic prepare phase only when the
# outside-repo live authorization gate is satisfied. Never invents credentials.

set -eu

repository_root="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
workspace="${GE_SPEECH_BENCHMARK_ROOT:-$HOME/ge-speech-benchmark}"
auth_file="$workspace/LIVE_AUTHORIZATION.txt"
env_file="$workspace/env/speech-benchmark.env"
secret_file="$workspace/secrets/azure-speech.secret"
php_bin="${PHP:-$repository_root/tmp/toolchain/php/php}"
required_phrase='Authorize live synthetic Azure benchmark up to ₹1000'

if [ ! -x "$php_bin" ]; then
  php_bin="$(command -v php)"
fi

if [ ! -f "$auth_file" ]; then
  cat >&2 <<EOF
Blocked: missing $auth_file

Create that file with a single line exactly:
$required_phrase

Also complete vendor/spend evidence and paste the Azure secret into:
$secret_file

See: $workspace/WAITING_FOR_LIVE_AUTHORIZATION.md
EOF
  exit 2
fi

if ! grep -Fqx "$required_phrase" "$auth_file"; then
  echo "Blocked: $auth_file must contain the exact APO authorization phrase." >&2
  exit 2
fi

if [ ! -s "$secret_file" ]; then
  echo "Blocked: $secret_file is empty. Paste the Azure key or Entra token first." >&2
  exit 2
fi

if [ ! -f "$env_file" ]; then
  echo "Blocked: copy speech-benchmark.env.example to speech-benchmark.env and fill real values." >&2
  exit 2
fi

if ! grep -Eq '^AZURE_SPEECH_LIVE_REQUESTS_ENABLED=true[[:space:]]*$' "$env_file"; then
  echo "Blocked: set AZURE_SPEECH_LIVE_REQUESTS_ENABLED=true in $env_file only after APO authorization." >&2
  exit 2
fi

# shellcheck disable=SC1090
set -a
. "$env_file"
set +a

echo "Live-prepare gate passed. Running speech:benchmark --phase=prepare ..."
cd "$repository_root/apps/api"
exec "$php_bin" artisan speech:benchmark --phase=prepare

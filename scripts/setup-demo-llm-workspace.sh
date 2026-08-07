#!/bin/sh

set -eu

workspace="${GE_DEMO_LLM_ROOT:-$HOME/ge-demo-llm}"
env_dir="$workspace/env"
secrets_dir="$workspace/secrets"
env_file="$env_dir/demo-llm.env"
secret_file="$secrets_dir/openrouter.api-key"

mkdir -p "$env_dir" "$secrets_dir"
chmod 700 "$workspace" "$env_dir" "$secrets_dir"

if [ ! -f "$secret_file" ]; then
  umask 077
  : >"$secret_file"
  chmod 600 "$secret_file"
  echo "Created empty secret file: $secret_file" >&2
  echo "Paste your OpenRouter API key into that file (single line)." >&2
fi

if [ ! -f "$env_file" ]; then
  umask 077
  cat >"$env_file" <<EOF
# Demo-only OpenRouter settings for make demo (D-0094).
# Never commit this file or the secret.

OPENROUTER_API_KEY_FILE=$secret_file
OPENROUTER_MODEL=openrouter/free
EOF
  chmod 600 "$env_file"
  echo "Created $env_file" >&2
fi

echo "Demo LLM workspace ready at $workspace" >&2
echo "1. Create a key at https://openrouter.ai/keys" >&2
echo "2. Put it in $secret_file" >&2
echo "3. Run: make demo" >&2
echo "4. Open Practice (#practice) and send an English turn" >&2

#!/bin/sh
# Preflight gate before any live Confidence Conversation Azure OpenAI call.
# Does not itself call the provider. Accepts APP-0022 (₹500) or APP-0053 (₹10000).

set -eu

root_dir="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
workspace="${GE_CONVERSATION_MODEL_ROOT:-$HOME/ge-conversation-model}"
runtime="${GE_SOFT_LAUNCH_RUNTIME:-$HOME/ge-soft-launch}"
auth_file="$workspace/LIVE_AUTHORIZATION.txt"
secret_file="$workspace/secrets/azure-openai.secret"
env_file_default="$workspace/env/conversation-model.env"
env_file_soft="$workspace/env/soft-launch-conversation.env"
soft_launch_env="${GE_SOFT_LAUNCH_ENV:-$runtime/env/soft-launch.env}"
expected_500='Authorize live Confidence Conversation Azure OpenAI up to ₹500'
expected_10000='Authorize live Confidence Conversation Azure OpenAI up to ₹10000'

if [ ! -f "$auth_file" ]; then
  printf 'Missing %s\n' "$auth_file" >&2
  exit 1
fi

if grep -Fxq "$expected_10000" "$auth_file"; then
  authorized_cap=10000
elif grep -Fxq "$expected_500" "$auth_file"; then
  authorized_cap=500
else
  printf 'LIVE_AUTHORIZATION.txt must contain exactly one of:\n%s\n%s\n' \
    "$expected_500" "$expected_10000" >&2
  exit 1
fi

if [ ! -f "$secret_file" ] || [ ! -s "$secret_file" ]; then
  printf 'Secret file missing or empty: %s\n' "$secret_file" >&2
  exit 1
fi

if [ -f "$env_file_soft" ]; then
  env_file="$env_file_soft"
elif [ -f "$env_file_default" ]; then
  env_file="$env_file_default"
else
  printf 'Missing env file: %s or %s\n' "$env_file_soft" "$env_file_default" >&2
  exit 1
fi

if [ "$env_file" = "$env_file_soft" ]; then
  GE_SOFT_LAUNCH_CONVERSATION_ENV="$env_file"
  GE_SOFT_LAUNCH_ENV="$soft_launch_env"
  export GE_SOFT_LAUNCH_CONVERSATION_ENV GE_SOFT_LAUNCH_ENV
  # shellcheck disable=SC1091
  . "$root_dir/scripts/lib/soft-launch-environment.sh"
  load_soft_launch_environment
else
  set -a
  # shellcheck disable=SC1090
  . "$env_file"
  set +a
fi

if [ "${CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED:-false}" != "true" ]; then
  printf 'Set CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED=true in %s after evidence is ready.\n' "$env_file" >&2
  exit 1
fi

if [ "${CONVERSATION_PROVIDER_DRIVER:-}" != "azure-openai-dev" ]; then
  printf 'CONVERSATION_PROVIDER_DRIVER must be azure-openai-dev for live smoke.\n' >&2
  exit 1
fi

printf 'Live preflight OK for workspace %s (cap ₹%s; no provider call made).\n' \
  "$workspace" "$authorized_cap"
if [ "$env_file" = "$env_file_soft" ]; then
  printf 'Runtime loads provider settings from %s and the kill switch from %s.\n' \
    "$env_file" "$soft_launch_env"
else
  printf 'Source %s in a non-production shell, then run an authorized smoke path.\n' "$env_file"
fi

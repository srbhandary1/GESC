#!/bin/sh
# Preflight gate before any live Confidence Conversation Azure OpenAI call.
# Does not itself call the provider.

set -eu

workspace="${GE_CONVERSATION_MODEL_ROOT:-$HOME/ge-conversation-model}"
auth_file="$workspace/LIVE_AUTHORIZATION.txt"
secret_file="$workspace/secrets/azure-openai.secret"
env_file="$workspace/env/conversation-model.env"
expected='Authorize live Confidence Conversation Azure OpenAI up to ₹500'

if [ ! -f "$auth_file" ]; then
  printf 'Missing %s\n' "$auth_file" >&2
  exit 1
fi

if ! grep -Fxq "$expected" "$auth_file"; then
  printf 'LIVE_AUTHORIZATION.txt must contain exactly:\n%s\n' "$expected" >&2
  exit 1
fi

if [ ! -f "$secret_file" ] || [ ! -s "$secret_file" ]; then
  printf 'Secret file missing or empty: %s\n' "$secret_file" >&2
  exit 1
fi

if [ ! -f "$env_file" ]; then
  printf 'Missing env file: %s\n' "$env_file" >&2
  exit 1
fi

# shellcheck disable=SC1090
. "$env_file"

if [ "${CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED:-false}" != "true" ]; then
  printf 'Set CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED=true in %s after evidence is ready.\n' "$env_file" >&2
  exit 1
fi

if [ "${CONVERSATION_PROVIDER_DRIVER:-}" != "azure-openai-dev" ]; then
  printf 'CONVERSATION_PROVIDER_DRIVER must be azure-openai-dev for live smoke.\n' >&2
  exit 1
fi

printf 'Live preflight OK for workspace %s (no provider call made).\n' "$workspace"
printf 'Source %s in a non-production shell, then run an authorized smoke path.\n' "$env_file"

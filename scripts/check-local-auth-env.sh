#!/bin/sh

set -eu

api_env="apps/api/.env"

if [ ! -f "$api_env" ]; then
    echo "Missing $api_env. Run make bootstrap before make dev." >&2
    exit 1
fi

app_environment="$(
    awk -F= '$1 == "APP_ENV" { value = $2 } END { print value }' "$api_env"
)"

if [ "$app_environment" != "local" ]; then
    echo "$api_env must set APP_ENV=local before make dev." >&2
    exit 1
fi

session_encryption="$(
    awk -F= '$1 == "SESSION_ENCRYPT" { value = $2 } END { print value }' "$api_env"
)"

if [ "$session_encryption" != "true" ]; then
    echo "$api_env must set SESSION_ENCRYPT=true for the PR-003 identity flow." >&2
    exit 1
fi

identity_digest_key="$(
    awk -F= '$1 == "AUTH_IDENTITY_DIGEST_KEY" { value = $2 } END { print value }' "$api_env"
)"

if [ -z "$identity_digest_key" ]; then
    echo "$api_env must set AUTH_IDENTITY_DIGEST_KEY for stable identity lookup." >&2
    exit 1
fi

echo "Local authentication environment is configured with encrypted sessions and a dedicated identity digest key."

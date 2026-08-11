#!/bin/sh
# Deterministic regression contract for APP-0053 environment loading.

set -eu

root_dir="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
test_root="$(mktemp -d "${TMPDIR:-/tmp}/ge-soft-launch-env.XXXXXX")"
trap 'rm -rf -- "$test_root"' EXIT HUP INT TERM

runtime="$test_root/runtime"
workspace="$test_root/conversation"
mkdir -p "$runtime/env" "$workspace/env" "$workspace/secrets"

cat > "$runtime/env/soft-launch.env" <<'EOF'
APP_ENV=soft-launch
DB_DATABASE=soft-launch-database
CONVERSATION_PROVIDER_DRIVER=azure-openai-dev
CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED=true
CONVERSATION_AZURE_OPENAI_ENDPOINT=https://stale-copy.openai.azure.com
EOF

cat > "$workspace/env/soft-launch-conversation.env" <<EOF
APP_ENV=soft-launch
CONVERSATION_PROVIDER_DRIVER=azure-openai-dev
CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED=false
CONVERSATION_AZURE_OPENAI_ENDPOINT=https://configured.openai.azure.com
CONVERSATION_AZURE_OPENAI_ALLOWED_HOST=configured.openai.azure.com
CONVERSATION_AZURE_OPENAI_DEPLOYMENT=confidence-test
CONVERSATION_AZURE_OPENAI_API_VERSION=2024-10-21
CONVERSATION_AZURE_OPENAI_SECRET_FILE=$workspace/secrets/azure-openai.secret
EOF

(
  GE_SOFT_LAUNCH_RUNTIME="$runtime"
  GE_CONVERSATION_MODEL_ROOT="$workspace"
  export GE_SOFT_LAUNCH_RUNTIME GE_CONVERSATION_MODEL_ROOT
  # shellcheck disable=SC1091
  . "$root_dir/scripts/lib/soft-launch-environment.sh"
  load_soft_launch_environment

  test "$APP_ENV" = soft-launch
  test "$DB_DATABASE" = soft-launch-database
  test "$CONVERSATION_PROVIDER_DRIVER" = azure-openai-dev
  test "$CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED" = true
  test "$CONVERSATION_AZURE_OPENAI_ENDPOINT" = https://configured.openai.azure.com
)

printf '%s\n' 'Authorize live Confidence Conversation Azure OpenAI up to ₹10000' \
  > "$workspace/LIVE_AUTHORIZATION.txt"
printf '%s\n' 'synthetic-test-secret' > "$workspace/secrets/azure-openai.secret"
chmod 600 "$workspace/secrets/azure-openai.secret"

preflight_output="$(
  GE_SOFT_LAUNCH_RUNTIME="$runtime" \
  GE_CONVERSATION_MODEL_ROOT="$workspace" \
    sh "$root_dir/scripts/run-conversation-model-live-prepare.sh"
)"
printf '%s' "$preflight_output" | grep -Fq 'Live preflight OK'
if printf '%s' "$preflight_output" | grep -Fq 'synthetic-test-secret'; then
  printf '%s\n' 'preflight output exposed the secret' >&2
  exit 1
fi

conversation_hash_before="$(sha256sum "$workspace/env/soft-launch-conversation.env" | cut -d' ' -f1)"
GE_SOFT_LAUNCH_RUNTIME="$runtime" \
GE_CONVERSATION_MODEL_ROOT="$workspace" \
  bash "$root_dir/scripts/setup-soft-launch-workspace.sh" >/dev/null
conversation_hash_after="$(sha256sum "$workspace/env/soft-launch-conversation.env" | cut -d' ' -f1)"
test "$conversation_hash_before" = "$conversation_hash_after"

fresh_runtime="$test_root/fresh-runtime"
fresh_workspace="$test_root/fresh-conversation"
mkdir -p "$fresh_runtime/env"
printf '%s\n' 'APP_KEY=synthetic-test-key' > "$fresh_runtime/env/soft-launch.env"
GE_SOFT_LAUNCH_RUNTIME="$fresh_runtime" \
GE_CONVERSATION_MODEL_ROOT="$fresh_workspace" \
  bash "$root_dir/scripts/setup-soft-launch-workspace.sh" >/dev/null
grep -Fq '"approved_for_conversation_model": false' \
  "$fresh_workspace/evidence/vendor-evidence.json"
grep -Fq 'REPLACE_WITH_CURRENT_SUBSCRIPTION_EVIDENCE_ID' \
  "$fresh_workspace/env/soft-launch-conversation.env"

redirect_config="$test_root/Caddyfile.redirect"
direct_config="$test_root/Caddyfile.direct"
bash -c '
  source "$1"
  write_soft_launch_public_http_config "$2" 8080 8000 "/srv/web" \
    "https://soft-launch.example.test"
' _ "$root_dir/scripts/lib/soft-launch-public-http.sh" "$redirect_config"
grep -Fq 'redir * https://soft-launch.example.test{uri} 307' "$redirect_config"
if grep -Fq 'reverse_proxy' "$redirect_config"; then
  printf '%s\n' 'HTTPS static-IP entry must not serve authenticated HTTP directly' >&2
  exit 1
fi

bash -c '
  source "$1"
  write_soft_launch_public_http_config "$2" 8080 8000 "/srv/web" \
    "http://117.254.108.143:8080"
' _ "$root_dir/scripts/lib/soft-launch-public-http.sh" "$direct_config"
grep -Fq 'reverse_proxy 127.0.0.1:8000' "$direct_config"
grep -Fq 'root * /srv/web' "$direct_config"

reconciliation_aid="$root_dir/apps/web/public/lpl01-reconciliation.html"
grep -Fq 'Temporary controlled soft-launch commissioning aid.' "$reconciliation_aid"
grep -Fq 'fresh-password-confirmed API' "$reconciliation_aid"
if grep -Eq 'localhost-only|fresh-TOTP' "$reconciliation_aid"; then
  printf '%s\n' 'reconciliation aid contains a stale access or authentication label' >&2
  exit 1
fi

soft_launch_runner="$root_dir/scripts/run-soft-launch.sh"
grep -Fq 'nohup "${PHP_BIN}" -S "127.0.0.1:${API_PORT}" "${API_ROUTER}"' \
  "$soft_launch_runner"
if grep -Fq 'artisan serve' "$soft_launch_runner"; then
  printf '%s\n' 'soft-launch API must own the actual PHP server PID directly' >&2
  exit 1
fi

printf '%s\n' 'Soft-launch environment contract passed.'

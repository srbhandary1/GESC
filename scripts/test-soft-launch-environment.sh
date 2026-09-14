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
stable_config="$test_root/Caddyfile.stable"
bash -c '
  source "$1"
  write_soft_launch_public_http_config "$2" 8080 8000 "/srv/web" \
    "https://soft-launch.example.test"
' _ "$root_dir/scripts/lib/soft-launch-public-http.sh" "$redirect_config"
grep -Fq 'redir * https://soft-launch.example.test{uri} 307' "$redirect_config"
grep -Fq 'handle /api/*' "$redirect_config"
grep -Fq 'handle /oauth/*' "$redirect_config"
grep -Fq 'reverse_proxy 127.0.0.1:8000' "$redirect_config"
if grep -Fq 'root * /srv/web' "$redirect_config"; then
  printf '%s\n' 'HTTPS static-IP entry must not serve the web shell over HTTP' >&2
  exit 1
fi

bash -c '
  source "$1"
  write_soft_launch_public_http_config "$2" 8080 8000 "/srv/web" \
    "http://117.254.108.143:8080"
' _ "$root_dir/scripts/lib/soft-launch-public-http.sh" "$direct_config"
grep -Fq 'reverse_proxy 127.0.0.1:8000' "$direct_config"
grep -Fq 'root * /srv/web' "$direct_config"
grep -Fq 'handle /oauth/*' "$direct_config"

serve_http_config="$test_root/Caddyfile.serve-http"
GE_SOFT_LAUNCH_SERVE_PUBLIC_HTTP=true bash -c '
  source "$1"
  write_soft_launch_public_http_config "$2" 8080 8000 "/srv/web" \
    "https://soft-launch.example.test"
' _ "$root_dir/scripts/lib/soft-launch-public-http.sh" "$serve_http_config"
grep -Fq 'root * /srv/web' "$serve_http_config"
grep -Fq 'reverse_proxy 127.0.0.1:8000' "$serve_http_config"
grep -Fq '@androidApk path /downloads/*.apk' "$serve_http_config"
grep -Fq 'encode off' "$serve_http_config"
if grep -Fq 'redir * https://soft-launch.example.test{uri} 307' "$serve_http_config"; then
  printf '%s\n' 'GE_SOFT_LAUNCH_SERVE_PUBLIC_HTTP must serve the SPA instead of redirecting' >&2
  exit 1
fi

bash -c '
  source "$1"
  write_soft_launch_public_http_config "$2" 8080 8000 "/srv/web" \
    "https://ai.globalspokenenglish.com" "ai.globalspokenenglish.com" 8443 9080
' _ "$root_dir/scripts/lib/soft-launch-public-http.sh" "$stable_config"
grep -Fq 'admin off' "$stable_config"
grep -Fq 'http_port 8080' "$stable_config"
grep -Fq 'https_port 8443' "$stable_config"
grep -Fq 'redir * https://ai.globalspokenenglish.com{uri} 307' "$stable_config"
grep -Fq 'ai.globalspokenenglish.com {' "$stable_config"
grep -Fq 'reverse_proxy 127.0.0.1:9080' "$stable_config"
grep -Fq 'Strict-Transport-Security "max-age=31536000"' "$stable_config"
grep -Fq 'X-Content-Type-Options "nosniff"' "$stable_config"
if grep -Eq 'tls internal|trycloudflare' "$stable_config"; then
  printf '%s\n' 'stable public entry must use a public certificate and stable host only' >&2
  exit 1
fi

if bash -c '
  source "$1"
  write_soft_launch_public_http_config "$2" 8080 8000 "/srv/web" \
    "https://wrong.example.test" "ai.globalspokenenglish.com" 8443 9080
' _ "$root_dir/scripts/lib/soft-launch-public-http.sh" "$test_root/Caddyfile.invalid" 2>/dev/null; then
  printf '%s\n' 'stable public entry accepted a mismatched APP_URL' >&2
  exit 1
fi

reconciliation_aid="$root_dir/apps/web/public/lpl01-reconciliation.html"
if [ -e "$reconciliation_aid" ]; then
  printf '%s\n' 'completed reconciliation aid must remain retired' >&2
  exit 1
fi

soft_launch_runner="$root_dir/scripts/run-soft-launch.sh"
grep -Fq 'umask 077' "$soft_launch_runner"
grep -Fq 'nohup "${PHP_BIN}" -S "127.0.0.1:${api_port}" "${API_ROUTER}"' \
  "$soft_launch_runner"
grep -Fq 'ensure_api_listen_port' "$soft_launch_runner"
grep -Fq 'web_build_fingerprint' "$soft_launch_runner"
grep -Fq 'VITE_UI_TRANSLATION_PREVIEW=true' "$soft_launch_runner"
grep -Fq '.soft-launch-ui-preview' "$soft_launch_runner"
grep -Fq 'Web inputs changed during the build' "$soft_launch_runner"
grep -Fq 'refresh-web)' "$soft_launch_runner"
grep -Fq 'reload-public-http)' "$soft_launch_runner"
grep -Fq 'reload-api)' "$soft_launch_runner"
if grep -Fq 'artisan serve' "$soft_launch_runner"; then
  printf '%s\n' 'soft-launch API must own the actual PHP server PID directly' >&2
  exit 1
fi
grep -Fq 'the rotating Quick Tunnel is disabled' "$soft_launch_runner"
grep -Fq 'WAN 80 -> this host:%s; WAN 443 -> this host:%s' "$soft_launch_runner"

configured_runtime="$test_root/configured-runtime"
mkdir -p "$configured_runtime/env"
printf '%s\n' 'APP_ENV=soft-launch' > "$configured_runtime/env/soft-launch.env"
GE_SOFT_LAUNCH_RUNTIME="$configured_runtime" \
GE_SOFT_LAUNCH_STABLE_HOST=ai.globalspokenenglish.com \
  bash "$soft_launch_runner" configure-stable-host >/dev/null
grep -Fxq 'APP_URL=https://ai.globalspokenenglish.com' \
  "$configured_runtime/env/soft-launch.env"
grep -Fxq 'GE_SOFT_LAUNCH_STABLE_HOST=ai.globalspokenenglish.com' \
  "$configured_runtime/env/soft-launch.env"
grep -Fxq 'GE_SOFT_LAUNCH_PUBLIC_HTTPS_PORT=8443' \
  "$configured_runtime/env/soft-launch.env"
grep -Fq 'ai.globalspokenenglish.com,127.0.0.1:9080' \
  "$configured_runtime/env/soft-launch.env"
test "$(stat -c '%a' "$configured_runtime/env/soft-launch.env")" = 600

soft_launch_caddyfile="$root_dir/infra/soft-launch/Caddyfile"
grep -Fq 'handle /oauth/* {' "$soft_launch_caddyfile"
grep -Fq 'handle /auth/mobile/* {' "$soft_launch_caddyfile"
grep -Fq 'http://:9080 {' "$soft_launch_caddyfile"
grep -Fq 'bind 127.0.0.1' "$soft_launch_caddyfile"
grep -Fq 'reverse_proxy 127.0.0.1:{$GE_SOFT_LAUNCH_API_PORT:8000}' "$soft_launch_caddyfile"
grep -Fq 'header_up X-Forwarded-Proto https' "$soft_launch_caddyfile"
grep -Fq '@credentialPages path /password-establishment.html /password-recovery.html' \
  "$soft_launch_caddyfile"
grep -Fq 'handle @credentialPages {' "$soft_launch_caddyfile"
grep -Fq 'redir /privacy /privacy.html 308' "$soft_launch_caddyfile"
grep -Fq 'redir /account-deletion /account-deletion.html 308' "$soft_launch_caddyfile"
grep -Fq 'handle /privacy.html {' "$soft_launch_caddyfile"
grep -Fq 'handle /account-deletion.html {' "$soft_launch_caddyfile"
test -f "$root_dir/apps/web/public/privacy.html"
test -f "$root_dir/apps/web/public/account-deletion.html"
grep -Fq 'Sarvam' "$root_dir/apps/web/public/privacy.html"
grep -Fq 'operator privacy' "$root_dir/apps/web/public/account-deletion.html"
grep -Fq 'Cache-Control "no-store, max-age=0"' "$soft_launch_caddyfile"
if grep -Eq 'reconciliationPage|lpl01-reconciliation|Referrer-Policy "same-origin"' \
  "$soft_launch_caddyfile"; then
  printf '%s\n' 'soft-launch proxy must not expose the retired reconciliation page' >&2
  exit 1
fi
laravel_import_count="$(grep -Fc 'import laravel_backend' "$soft_launch_caddyfile")"
if [ "$laravel_import_count" -lt 6 ]; then
  printf '%s\n' 'soft-launch proxy must route API, Sanctum, OAuth, mobile authentication and health paths to Laravel' >&2
  exit 1
fi

service_worker="$root_dir/apps/web/public/service-worker.js"
offline_registration="$root_dir/apps/web/src/offline/registerOfflineShell.ts"
grep -Fq "'global-english-public-shell-'" "$service_worker"
grep -Fq "'/oauth/'" "$service_worker"
grep -Fq "'/auth/mobile/'" "$service_worker"
grep -Fq "'/oauth/'" "$offline_registration"
grep -Fq "'/auth/mobile/'" "$offline_registration"
grep -Fq "updateViaCache: 'none'" "$offline_registration"
grep -Fq 'await registration.update()' "$offline_registration"
grep -Fq 'OFFLINE_SHELL_VERSION' "$service_worker"
grep -Fq "version: CACHE_VERSION" "$service_worker"
grep -Fq "searchParams.get('release')" "$service_worker"
grep -Fq 'serviceWorkerPath()' "$offline_registration"
grep -Fq 'VITE_PUBLIC_SHELL_RELEASE' "$offline_registration"
grep -Fq "'/password-recovery.html'" "$service_worker"
grep -Fq "'/password-establishment.html'" "$service_worker"

soft_launch_unit="$root_dir/infra/soft-launch/global-english-soft-launch.service.in"
grep -Fq 'Type=simple' "$soft_launch_unit"
grep -Fq 'run-soft-launch.sh supervise' "$soft_launch_unit"
grep -Fq 'Restart=on-failure' "$soft_launch_unit"

printf '%s\n' 'Soft-launch environment contract passed.'

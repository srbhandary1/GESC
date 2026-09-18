#!/usr/bin/env bash
# Soft-launch readiness report (does not mutate data).

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RUNTIME="${GE_SOFT_LAUNCH_RUNTIME:-${HOME}/ge-soft-launch}"
CONV="${GE_CONVERSATION_MODEL_ROOT:-${HOME}/ge-conversation-model}"
SOFT_ENV="${GE_SOFT_LAUNCH_ENV:-${RUNTIME}/env/soft-launch.env}"
CONVERSATION_ENV="${GE_SOFT_LAUNCH_CONVERSATION_ENV:-${CONV}/env/soft-launch-conversation.env}"
PROXY_PORT="${GE_SOFT_LAUNCH_PROXY_PORT:-9080}"
PHP_BIN="${GE_SOFT_LAUNCH_PHP:-${ROOT_DIR}/tmp/toolchain/php/php}"

ok=0
warn=0
fail=0

pass() { printf '[PASS] %s\n' "$*"; ok=$((ok + 1)); }
note() { printf '[WARN] %s\n' "$*"; warn=$((warn + 1)); }
bad() { printf '[FAIL] %s\n' "$*"; fail=$((fail + 1)); }
azure_gate() {
  if [[ "${CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED:-false}" == "true" ]]; then
    bad "$*"
  else
    note "$*"
  fi
}

# shellcheck disable=SC1091
source "${ROOT_DIR}/scripts/lib/soft-launch-environment.sh"
GE_SOFT_LAUNCH_ENV="${SOFT_ENV}" \
GE_SOFT_LAUNCH_CONVERSATION_ENV="${CONVERSATION_ENV}" \
  load_soft_launch_environment

pidfile_alive() {
  local pid_file="$1"
  [[ -f "${pid_file}" ]] || return 1
  local pid
  pid="$(cat "${pid_file}")"
  [[ "${pid}" =~ ^[1-9][0-9]*$ ]] || return 1
  kill -0 "${pid}" 2>/dev/null
}

PID_DIR="${RUNTIME}/run"
for name in api worker scheduler caddy public-http; do
  if pidfile_alive "${PID_DIR}/${name}.pid"; then
    pass "${name} process is running"
  else
    bad "${name} process is not running"
  fi
done

if curl --silent --fail --max-time 3 "http://127.0.0.1:${PROXY_PORT}/api/v1/health/live" >/dev/null; then
  pass "proxy health/live"
else
  bad "proxy health/live unreachable on :${PROXY_PORT}"
fi

if curl --silent --fail --max-time 3 "http://127.0.0.1:${PROXY_PORT}/api/v1/health/ready" >/dev/null; then
  pass "proxy health/ready"
else
  bad "proxy health/ready"
fi

if [[ -f "${ROOT_DIR}/apps/web/dist/index.html" ]]; then
  pass "web production dist present"
else
  bad "apps/web/dist missing — run make build or soft-launch-start"
fi

if [[ -f "${SOFT_ENV}" ]]; then
  pass "soft-launch.env present"
else
  bad "missing ${SOFT_ENV} — run make soft-launch-setup"
fi

if [[ -f "${CONVERSATION_ENV}" ]]; then
  pass "soft-launch-conversation.env present"
else
  bad "missing ${CONVERSATION_ENV} — run make soft-launch-setup"
fi

PASSPORT_PRIVATE_KEY_FILE="${ROOT_DIR}/apps/api/storage/oauth-private.key"
PASSPORT_PUBLIC_KEY_FILE="${ROOT_DIR}/apps/api/storage/oauth-public.key"
if [[ -n "${PASSPORT_PRIVATE_KEY:-}" && -n "${PASSPORT_PUBLIC_KEY:-}" ]]; then
  pass "Passport signing key pair configured through the runtime environment"
elif [[ -s "${PASSPORT_PRIVATE_KEY_FILE}" ]] \
  && [[ -s "${PASSPORT_PUBLIC_KEY_FILE}" ]] \
  && [[ "$(stat -c '%a' "${PASSPORT_PRIVATE_KEY_FILE}")" == "600" ]]; then
  pass "Passport signing key pair present; private key is mode 0600"
else
  bad "Passport signing key pair missing or private key is not mode 0600"
fi

if [[ "${APP_ENV:-}" == "soft-launch" ]]; then
  pass "effective APP_ENV is soft-launch"
else
  bad "effective APP_ENV must be soft-launch"
fi

if [[ "${CONVERSATION_PROVIDER_DRIVER:-}" == "azure-openai-dev" ]]; then
  pass "effective conversation driver is azure-openai-dev"
else
  bad "effective conversation driver must be azure-openai-dev"
fi

if [[ -f "${CONV}/LIVE_AUTHORIZATION.txt" ]] \
  && grep -qx 'Authorize live Confidence Conversation Azure OpenAI up to ₹10000' \
    "${CONV}/LIVE_AUTHORIZATION.txt"; then
  pass "LIVE_AUTHORIZATION ₹10000 phrase present"
else
  azure_gate "LIVE_AUTHORIZATION ₹10000 phrase missing"
fi

if [[ -f "${CONV}/evidence/spend-approval.soft-launch.json" ]]; then
  pass "APP-0053 spend approval scaffold present"
else
  azure_gate "spend-approval.soft-launch.json missing"
fi

VENDOR_EVIDENCE="${CONV}/evidence/vendor-evidence.json"
if [[ -f "${VENDOR_EVIDENCE}" ]]; then
  pass "vendor evidence file present"
  if grep -Eq '"approved_for_conversation_model"[[:space:]]*:[[:space:]]*false|REPLACE_|soft-launch-(subscription|entity)-v1|"[abc]{64}"' "${VENDOR_EVIDENCE}"; then
    azure_gate "vendor evidence still contains setup placeholders"
  fi
else
  azure_gate "vendor-evidence.json missing"
fi

if [[ "${CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED:-false}" == "true" ]]; then
  pass "live Azure tutoring kill switch enabled"
else
  note "live Azure tutoring kill switch disabled"
fi

if [[ -n "${CONVERSATION_AZURE_OPENAI_ENDPOINT:-}" \
  && "${CONVERSATION_AZURE_OPENAI_ENDPOINT}" != *REPLACE* ]]; then
  pass "Azure endpoint configured"
else
  azure_gate "Azure endpoint is missing or still a placeholder"
fi

if [[ -n "${CONVERSATION_AZURE_OPENAI_ALLOWED_HOST:-}" \
  && "${CONVERSATION_AZURE_OPENAI_ALLOWED_HOST}" != *REPLACE* ]]; then
  pass "Azure endpoint allowlist host configured"
else
  azure_gate "Azure endpoint allowlist host is missing or still a placeholder"
fi

if [[ -n "${CONVERSATION_AZURE_OPENAI_DEPLOYMENT:-}" \
  && "${CONVERSATION_AZURE_OPENAI_DEPLOYMENT}" != *REPLACE* ]]; then
  pass "Azure deployment configured"
else
  azure_gate "Azure deployment is missing or still a placeholder"
fi

if [[ "${CONVERSATION_AZURE_OPENAI_API_VERSION:-}" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}(-preview)?$ ]]; then
  pass "Azure API version configured"
else
  azure_gate "Azure API version is invalid"
fi

SECRET="${CONV}/secrets/azure-openai.secret"
if [[ -f "${SECRET}" ]] && [[ -s "${SECRET}" ]] && [[ "$(stat -c '%a' "${SECRET}")" == "600" ]]; then
  pass "Azure secret file non-empty and mode 0600"
else
  azure_gate "Azure secret empty or not mode 0600 — live tutoring disabled until filled"
fi

if [[ "${CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED:-false}" == "true" ]]; then
  if [[ -x "${PHP_BIN}" ]]; then
    PROVIDER_ERRORS="$(
      "${PHP_BIN}" "${ROOT_DIR}/apps/api/artisan" tinker \
        --execute="echo json_encode(app(App\\Conversation\\Providers\\AzureOpenAi\\AzureOpenAiConversationConfiguration::class)->preflightErrors());" \
        2>/dev/null || true
    )"
    if [[ "${PROVIDER_ERRORS}" == "[]" ]]; then
      pass "Azure provider authorization preflight"
    elif [[ -n "${PROVIDER_ERRORS}" ]]; then
      bad "Azure provider authorization preflight: ${PROVIDER_ERRORS}"
    else
      bad "Azure provider authorization preflight could not be evaluated"
    fi
  else
    bad "PHP toolchain missing; live Azure provider preflight not evaluated"
  fi
fi

if [[ -n "${GE_SOFT_LAUNCH_STABLE_HOST:-}" ]]; then
  if [[ "${GE_SOFT_LAUNCH_STABLE_HOST}" =~ ^[a-z0-9]([a-z0-9.-]*[a-z0-9])$ ]] \
    && [[ "${GE_SOFT_LAUNCH_STABLE_HOST}" == *.* ]]; then
    pass "stable HTTPS hostname configured: ${GE_SOFT_LAUNCH_STABLE_HOST}"
  else
    bad "stable HTTPS hostname is malformed"
  fi
  if [[ "${APP_URL:-}" == "https://${GE_SOFT_LAUNCH_STABLE_HOST}" ]]; then
    pass "APP_URL matches the stable HTTPS hostname"
  else
    bad "APP_URL does not match the stable HTTPS hostname"
  fi
  if [[ "${SESSION_SECURE_COOKIE:-false}" == "true" ]] \
    && printf '%s' "${SANCTUM_STATEFUL_DOMAINS:-}" \
      | tr ',' '\n' | grep -Fxq "${GE_SOFT_LAUNCH_STABLE_HOST}"; then
    pass "stable host uses Secure cookies and same-origin Sanctum state"
  else
    bad "stable host session/Sanctum configuration is incomplete"
  fi
  if [[ -f "${RUNTIME}/env/Caddyfile.public" ]] \
    && grep -Fq "${GE_SOFT_LAUNCH_STABLE_HOST} {" "${RUNTIME}/env/Caddyfile.public" \
    && grep -Fq 'https_port 8443' "${RUNTIME}/env/Caddyfile.public"; then
    pass "stable public Caddy edge generated"
  else
    bad "stable public Caddy edge is not generated; run make soft-launch-restart"
  fi
elif [[ -f "${RUNTIME}/tunnel.url" ]]; then
  pass "temporary tunnel URL: $(cat "${RUNTIME}/tunnel.url")"
else
  note "no active tunnel URL — run make soft-launch-tunnel for HTTPS/mic"
fi

if [[ -x "${PHP_BIN}" ]]; then
  PUBLISHED="$("${PHP_BIN}" "${ROOT_DIR}/apps/api/artisan" tinker --execute="echo DB::table('publication_heads')->whereNotNull('active_publication_id')->count();" 2>/dev/null || true)"
  if [[ "${PUBLISHED}" =~ ^[0-9]+$ ]] && (( PUBLISHED > 0 )); then
    pass "programs with active publication head: ${PUBLISHED}"
  else
    note "no active publication head yet — complete Content Studio publish + enrollment pin"
  fi
else
  note "PHP toolchain missing; skipped publication count"
fi

printf '\nSummary: %s pass, %s warn, %s fail\n' "${ok}" "${warn}" "${fail}"
if (( fail > 0 )); then
  exit 1
fi
exit 0

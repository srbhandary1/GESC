#!/usr/bin/env bash
# Soft-launch readiness report (does not mutate data).

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RUNTIME="${GE_SOFT_LAUNCH_RUNTIME:-${HOME}/ge-soft-launch}"
CONV="${GE_CONVERSATION_MODEL_ROOT:-${HOME}/ge-conversation-model}"
SOFT_ENV="${GE_SOFT_LAUNCH_ENV:-${RUNTIME}/env/soft-launch.env}"
CONVERSATION_ENV="${GE_SOFT_LAUNCH_CONVERSATION_ENV:-${CONV}/env/soft-launch-conversation.env}"
PROXY_PORT="${GE_SOFT_LAUNCH_PROXY_PORT:-9080}"
PUBLIC_HTTP_PORT="${GE_SOFT_LAUNCH_PUBLIC_HTTP_PORT:-8080}"
PUBLIC_HTTPS_PORT="${GE_SOFT_LAUNCH_PUBLIC_HTTPS_PORT:-8443}"
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

if [[ "${VOICE_PROVIDER_DRIVER:-disabled}" == "indic" ]]; then
  pass "effective learner STT driver is indic"

  if [[ "${VOICE_UPLOAD_ENABLED:-false}" == "true" ]]; then
    pass "voice uploads enabled for Indic STT"
  else
    bad "VOICE_UPLOAD_ENABLED must be true for Indic STT"
  fi

  if [[ -n "${VOICE_ALLOWED_ORGANIZATION_IDS:-}" ]]; then
    pass "Indic organization allowlist is non-empty"
  else
    bad "VOICE_ALLOWED_ORGANIZATION_IDS must be non-empty for Indic STT"
  fi

  INDIC_SECRET="${VOICE_INDIC_SECRET_FILE:-}"
  if [[ -n "${INDIC_SECRET}" \
    && ! -L "${INDIC_SECRET}" \
    && -f "${INDIC_SECRET}" \
    && -s "${INDIC_SECRET}" \
    && "$(stat -c '%a' "${INDIC_SECRET}")" == "600" \
    && "$(stat -c '%u' "${INDIC_SECRET}")" == "$(id -u)" ]]; then
    pass "Indic secret file non-empty, owned, and mode 0600"
  else
    bad "Indic secret file missing, unsafe, empty, or not mode 0600"
  fi

  if [[ -x "${PHP_BIN}" ]]; then
    INDIC_ERRORS="$(
      "${PHP_BIN}" "${ROOT_DIR}/apps/api/artisan" tinker \
        --execute="echo json_encode(app(App\\Conversation\\Voice\\Providers\\Indic\\IndicRecordedSpeechConfiguration::class)->preflightErrors());" \
        2>/dev/null || true
    )"
    if [[ "${INDIC_ERRORS}" == "[]" ]]; then
      pass "Indic provider authorization preflight"
    elif [[ -n "${INDIC_ERRORS}" ]]; then
      bad "Indic provider authorization preflight: ${INDIC_ERRORS}"
    else
      bad "Indic provider authorization preflight could not be evaluated"
    fi

    INDIC_BINDING="$(
      "${PHP_BIN}" "${ROOT_DIR}/apps/api/artisan" tinker \
        --execute="echo get_class(app(App\\Conversation\\Voice\\Providers\\RecordedSpeechToTextProvider::class));" \
        2>/dev/null || true
    )"
    if [[ "${INDIC_BINDING}" == "App\\Conversation\\Voice\\Providers\\Indic\\IndicHttpSpeechToTextProvider" ]]; then
      pass "Indic recorded-STT dependency binding"
    else
      bad "Indic recorded-STT dependency binding is inactive"
    fi
  else
    bad "PHP toolchain missing; Indic provider checks not evaluated"
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
elif [[ "${GE_SOFT_LAUNCH_SERVE_PUBLIC_HTTP:-}" == "true" ]]; then
  expected_origin="${GE_SOFT_LAUNCH_PUBLIC_ORIGIN:-http://117.254.108.143:8080}"
  if [[ "${APP_URL:-}" == "${expected_origin}" ]]; then
    pass "static-IP HTTP origin: ${expected_origin}"
  else
    bad "APP_URL is ${APP_URL:-empty}; expected ${expected_origin} for static-IP HTTP"
  fi
  if [[ "${SESSION_SECURE_COOKIE:-true}" == "false" ]]; then
    pass "HTTP origin uses non-Secure session cookies"
  else
    bad "SESSION_SECURE_COOKIE must be false for the HTTP static-IP origin"
  fi
  if printf '%s' "${SANCTUM_STATEFUL_DOMAINS:-}" \
    | tr ',' '\n' | grep -Fxq '117.254.108.143:8080'; then
    pass "Sanctum allows 117.254.108.143:8080"
  else
    bad "SANCTUM_STATEFUL_DOMAINS must include 117.254.108.143:8080"
  fi
  if [[ -f "${RUNTIME}/env/Caddyfile.public" ]] \
    && grep -Fq ":${PUBLIC_HTTP_PORT} {" "${RUNTIME}/env/Caddyfile.public" \
    && grep -Fq 'reverse_proxy 127.0.0.1:' "${RUNTIME}/env/Caddyfile.public"; then
    pass "public :8080 Caddy edge serves the app"
  else
    bad "public Caddyfile is not serving HTTP on :8080; run make soft-launch-restart"
  fi
  if [[ -f "${RUNTIME}/env/Caddyfile.public" ]] \
    && grep -Fq ":${PUBLIC_HTTPS_PORT} {" "${RUNTIME}/env/Caddyfile.public" \
    && grep -Fq 'tls internal' "${RUNTIME}/env/Caddyfile.public"; then
    pass "public :8443 Caddy edge serves local-CA HTTPS for microphone"
  else
    bad "public Caddyfile is not serving HTTPS on :8443; run make soft-launch-restart"
  fi
  if printf '%s' "${SANCTUM_STATEFUL_DOMAINS:-}" \
    | tr ',' '\n' | grep -Fxq '117.254.108.143:8443'; then
    pass "Sanctum allows 117.254.108.143:8443"
  else
    bad "SANCTUM_STATEFUL_DOMAINS must include 117.254.108.143:8443"
  fi
  if curl --silent --fail --max-time 3 "http://127.0.0.1:${PUBLIC_HTTP_PORT}/api/v1/health/live" >/dev/null; then
    pass "public HTTP health/live on :8080"
  else
    bad "public HTTP :8080 health/live unreachable"
  fi
  if curl --silent --fail --insecure --max-time 3 \
    "https://127.0.0.1:${PUBLIC_HTTPS_PORT}/api/v1/health/live" >/dev/null; then
    pass "public HTTPS health/live on :8443"
  else
    note "public HTTPS :8443 health/live unreachable; microphone needs that listener"
  fi
  lan_ipv4="$(
    ip -4 route get 1.1.1.1 2>/dev/null \
      | awk '{for (i = 1; i <= NF; i++) if ($i == "src") { print $(i + 1); exit }}'
  )"
  if [[ -n "${lan_ipv4}" ]]; then
    pass "this host LAN IPv4 is ${lan_ipv4}"
    if curl --silent --fail --max-time 3 "http://${lan_ipv4}:${PUBLIC_HTTP_PORT}/api/v1/health/live" >/dev/null; then
      pass "LAN HTTP health/live on ${lan_ipv4}:${PUBLIC_HTTP_PORT}"
    else
      note "LAN ${lan_ipv4}:${PUBLIC_HTTP_PORT} did not answer; same-Wi-Fi testers cannot use the LAN URL"
    fi
    documented_forward_lan="${GE_SOFT_LAUNCH_FORWARDED_LAN_IPV4:-192.168.1.3}"
    if [[ "${lan_ipv4}" != "${documented_forward_lan}" ]]; then
      note "Netgear port-forward target ${documented_forward_lan} does not match this host ${lan_ipv4}; WAN :8080 will hang until the inner router forwards TCP ${PUBLIC_HTTP_PORT} to ${lan_ipv4}"
    fi
  fi
elif [[ -f "${RUNTIME}/tunnel.url" ]]; then
  pass "temporary tunnel URL: $(cat "${RUNTIME}/tunnel.url")"
else
  note "no active tunnel URL — LAN microphone uses https://<lan-ip>:8443"
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

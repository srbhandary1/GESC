#!/usr/bin/env bash
# Wave 6 automated preflight (6.1–6.2, ledger CHECK, unit tests).
# Does not perform staff microphone hold-to-talk (6.3+). See docs/ops/WAVE6_INDIC_STT_STAFF_TESTS.md.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RUNTIME="${GE_SOFT_LAUNCH_RUNTIME:-${HOME}/ge-soft-launch}"
SOFT_ENV="${GE_SOFT_LAUNCH_ENV:-${RUNTIME}/env/soft-launch.env}"
CONV="${GE_CONVERSATION_MODEL_ROOT:-${HOME}/ge-conversation-model}"
CONVERSATION_ENV="${GE_SOFT_LAUNCH_CONVERSATION_ENV:-${CONV}/env/soft-launch-conversation.env}"
PHP_BIN="${GE_SOFT_LAUNCH_PHP:-${ROOT_DIR}/tmp/toolchain/php/php}"
PROXY_PORT="${GE_SOFT_LAUNCH_PROXY_PORT:-9080}"

ok=0
warn=0
fail=0

pass() { printf '[PASS] %s\n' "$*"; ok=$((ok + 1)); }
note() { printf '[WARN] %s\n' "$*"; warn=$((warn + 1)); }
bad() { printf '[FAIL] %s\n' "$*"; fail=$((fail + 1)); }

# shellcheck disable=SC1091
source "${ROOT_DIR}/scripts/lib/soft-launch-environment.sh"
GE_SOFT_LAUNCH_ENV="${SOFT_ENV}" \
GE_SOFT_LAUNCH_CONVERSATION_ENV="${CONVERSATION_ENV}" \
  load_soft_launch_environment

require_var() {
  local name="$1"
  if [[ -n "${!name:-}" ]]; then
    pass "${name} is set"
  else
    bad "${name} is missing"
  fi
}

printf 'Wave 6 Indic STT preflight\n\n'

if [[ "${APP_ENV:-}" == "soft-launch" ]]; then
  pass 'APP_ENV is soft-launch'
else
  bad "APP_ENV must be soft-launch (got ${APP_ENV:-unset})"
fi

require_var VOICE_UPLOAD_ENABLED
require_var VOICE_PROVIDER_DRIVER
require_var VOICE_ALLOWED_ORGANIZATION_IDS
require_var VOICE_INDIC_TRANSCRIBE_URL
require_var VOICE_INDIC_ALLOWED_HOST
require_var VOICE_INDIC_SECRET_FILE
require_var VOICE_INDIC_AUTH_HEADER
require_var VOICE_INDIC_MODEL
require_var VOICE_INDIC_MODE
require_var VOICE_INDIC_LANGUAGE_CODE
require_var VOICE_INDIC_OUTPUT_LANGUAGE

if [[ "${VOICE_UPLOAD_ENABLED:-false}" == "true" ]]; then
  pass 'VOICE_UPLOAD_ENABLED=true'
else
  bad 'VOICE_UPLOAD_ENABLED must be true for Wave 6'
fi

if [[ "${VOICE_PROVIDER_DRIVER:-}" == "indic" ]]; then
  pass 'VOICE_PROVIDER_DRIVER=indic'
else
  bad "VOICE_PROVIDER_DRIVER must be indic (got ${VOICE_PROVIDER_DRIVER:-unset})"
fi

if [[ "${VOICE_INDIC_MODE:-}" == "translate" ]]; then
  pass 'VOICE_INDIC_MODE=translate'
else
  bad 'VOICE_INDIC_MODE must be translate'
fi

if [[ "${VOICE_INDIC_OUTPUT_LANGUAGE:-}" == "en-IN" ]]; then
  pass 'VOICE_INDIC_OUTPUT_LANGUAGE=en-IN'
else
  bad 'VOICE_INDIC_OUTPUT_LANGUAGE must be en-IN'
fi

SECRET_PATH="${VOICE_INDIC_SECRET_FILE:-}"
if [[ -n "${SECRET_PATH}" && -f "${SECRET_PATH}" ]]; then
  perms="$(stat -c '%a' "${SECRET_PATH}")"
  if [[ "${perms}" == "600" && -s "${SECRET_PATH}" ]]; then
    pass 'Indic secret file exists, non-empty, mode 0600'
  else
    bad "Indic secret file must be non-empty mode 0600 (got ${perms})"
  fi
else
  bad 'Indic secret file path missing or not a file'
fi

MAX_MS="$(
  "${PHP_BIN}" "${ROOT_DIR}/apps/api/artisan" tinker \
    --execute="echo (int) config('voice.maximum_duration_ms');" 2>/dev/null || true
)"
if [[ "${MAX_MS}" == "25000" ]]; then
  pass 'voice.maximum_duration_ms is 25000'
else
  bad "voice.maximum_duration_ms must be 25000 (got ${MAX_MS:-unset})"
fi

if curl --silent --fail --max-time 3 \
  "http://127.0.0.1:${PROXY_PORT}/api/v1/health/ready" >/dev/null; then
  pass 'soft-launch health/ready'
else
  bad "health/ready unreachable on :${PROXY_PORT}"
fi

if [[ -x "${PHP_BIN}" ]]; then
  BINDING="$(
    APP_ENV=soft-launch "${PHP_BIN}" "${ROOT_DIR}/apps/api/artisan" tinker \
      --execute="echo get_class(app(App\\Conversation\\Voice\\Providers\\RecordedSpeechToTextProvider::class));" \
      2>/dev/null || true
  )"
  if [[ "${BINDING}" == *IndicWithAzureFailoverSpeechToTextProvider* ]]; then
    pass 'RecordedSpeechToTextProvider binds IndicWithAzureFailoverSpeechToTextProvider'
  else
    bad "unexpected recorded STT binding: ${BINDING:-unset}"
  fi

  PREFLIGHT="$(
    APP_ENV=soft-launch "${PHP_BIN}" "${ROOT_DIR}/apps/api/artisan" tinker \
      --execute="echo json_encode(app(App\\Conversation\\Voice\\Providers\\Indic\\IndicRecordedSpeechConfiguration::class)->preflightErrors());" \
      2>/dev/null || true
  )"
  if [[ "${PREFLIGHT}" == "[]" ]]; then
    pass 'IndicRecordedSpeechConfiguration preflight'
  elif [[ -n "${PREFLIGHT}" ]]; then
    bad "Indic preflight errors: ${PREFLIGHT}"
  else
    bad 'Indic preflight could not be evaluated'
  fi

  MIGRATION="$(
    APP_ENV=soft-launch "${PHP_BIN}" "${ROOT_DIR}/apps/api/artisan" migrate:status \
      --no-ansi 2>/dev/null | grep -c '2026_09_01_000300' || true
  )"
  if [[ "${MIGRATION}" -ge 1 ]]; then
    pass 'migration 2026_09_01_000300 present'
  else
    bad 'migration 2026_09_01_000300 missing — run migrate on soft-launch DB'
  fi
else
  bad 'PHP toolchain missing'
fi

if DB_CONNECTION=sqlite DB_DATABASE=:memory: APP_ENV=testing \
  "${PHP_BIN}" "${ROOT_DIR}/apps/api/vendor/bin/phpunit" \
  --configuration "${ROOT_DIR}/apps/api/phpunit.xml" \
  "${ROOT_DIR}/apps/api/tests/Feature/IndicSpeechAdapterTest.php" \
  "${ROOT_DIR}/apps/api/tests/Feature/IndicWithAzureFailoverSpeechToTextProviderTest.php" \
  >/dev/null 2>&1; then
  pass 'Indic adapter PHPUnit (sqlite) 7/7'
else
  bad 'Indic adapter PHPUnit failed — run with DB_CONNECTION=sqlite DB_DATABASE=:memory: APP_ENV=testing'
fi

if [[ -f "${RUNTIME}/tunnel.url" ]]; then
  pass "tunnel origin for staff tests: $(cat "${RUNTIME}/tunnel.url")"
else
  note 'no tunnel URL — run make soft-launch-tunnel before remote mic staff tests'
fi

printf '\nSummary: %s pass, %s warn, %s fail\n' "${ok}" "${warn}" "${fail}"
if (( fail > 0 )); then
  exit 1
fi
exit 0

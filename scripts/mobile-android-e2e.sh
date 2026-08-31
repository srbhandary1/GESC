#!/usr/bin/env bash
# End-to-end checks for the production Android app against the soft-launch edge.
# Simulates outside-network usage via the WAN IP baked into the APK.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB_DIR="${ROOT_DIR}/apps/web"
RUNTIME="${GE_SOFT_LAUNCH_RUNTIME:-${HOME}/ge-soft-launch}"
APK="${WEB_DIR}/public/downloads/global-spoken-english.apk"
PACKAGE="com.globalenglish.app"
WAN_ORIGIN="${GE_SOFT_LAUNCH_ANDROID_API_ORIGIN:-http://117.254.108.143:8080}"
OAUTH_CLIENT_ID="${GE_SOFT_LAUNCH_ANDROID_OAUTH_CLIENT_ID:-019fdd3f-4bba-720b-9e4e-a771ecccdd7f}"
ADB="${ADB:-adb}"
SERIAL="${ANDROID_SERIAL:-$("$ADB" devices | awk '/\tdevice$/{print $1; exit}')}"
E2E_ENV="${MOBILE_E2E_ENV:-${RUNTIME}/env/mobile-e2e.env}"

pass=0
fail=0
skip=0

pass() { printf '[PASS] %s\n' "$*"; pass=$((pass + 1)); }
bad() { printf '[FAIL] %s\n' "$*"; fail=$((fail + 1)); }
skip_note() { printf '[SKIP] %s\n' "$*"; skip=$((skip + 1)); }

http_nc() {
  local host="$1"
  local port="$2"
  local path="$3"
  local wait="${4:-8}"
  local request="GET ${path} HTTP/1.1\r\nHost: ${host}:${port}\r\nConnection: close\r\n\r\n"
  "$ADB" -s "${SERIAL}" shell \
    "echo -n '${request}' | toybox nc -w ${wait} ${host} ${port}" \
    2>/dev/null | head -20
}

require_serial() {
  if [[ -z "${SERIAL}" ]]; then
    bad "No adb device/emulator connected"
    exit 1
  fi
}

printf '== Mobile Android E2E ==\n'
printf 'Device: %s\n' "${SERIAL:-none}"
printf 'WAN origin: %s\n' "${WAN_ORIGIN}"
printf 'OAuth client: %s\n\n' "${OAUTH_CLIENT_ID}"

if [[ ! -f "${APK}" ]]; then
  bad "Missing APK at ${APK} — run make soft-launch-android-apk"
  exit 1
fi
pass "Published APK present"

if unzip -p "${APK}" 'assets/public/assets/index-*.js' 2>/dev/null \
  | rg -q "${OAUTH_CLIENT_ID}"; then
  pass "APK bundle contains OAuth client UUID"
else
  bad "APK bundle is missing OAuth client UUID ${OAUTH_CLIENT_ID}"
fi

if unzip -p "${APK}" 'assets/public/assets/index-*.js' 2>/dev/null \
  | rg -q "${WAN_ORIGIN#http://}"; then
  pass "APK bundle contains WAN API origin"
else
  bad "APK bundle is missing WAN API origin ${WAN_ORIGIN}"
fi

if curl --silent --fail --max-time 5 "http://127.0.0.1:8080/api/v1/health/live" >/dev/null; then
  pass "Soft-launch API reachable on host :8080"
else
  bad "Soft-launch API unreachable on host :8080"
fi

require_serial

if "$ADB" -s "${SERIAL}" install -r "${APK}" >/dev/null; then
  pass "APK installed on ${SERIAL}"
else
  bad "APK install failed on ${SERIAL}"
fi

"$ADB" -s "${SERIAL}" shell pm clear "${PACKAGE}" >/dev/null || true
"$ADB" -s "${SERIAL}" shell am start -n "${PACKAGE}/.MainActivity" >/dev/null
sleep 3

if "$ADB" -s "${SERIAL}" shell pidof "${PACKAGE}" >/dev/null; then
  pass "App process running after cold start"
else
  bad "App process not running after cold start"
fi

"$ADB" -s "${SERIAL}" shell uiautomator dump /sdcard/ge-mobile-e2e.xml >/dev/null
UI="$("$ADB" -s "${SERIAL}" shell cat /sdcard/ge-mobile-e2e.xml | tr -d '\r')"
if grep -Fq 'android.webkit.WebView' <<<"${UI}" \
  && grep -Fq 'com.globalenglish.app' <<<"${UI}"; then
  pass "Android shell loaded Capacitor WebView"
else
  bad "Capacitor WebView not found in UI hierarchy"
fi

WAN_HOST="${WAN_ORIGIN#http://}"
WAN_HOST="${WAN_HOST%%:*}"
WAN_PORT="${WAN_ORIGIN##*:}"
WAN_PORT="${WAN_PORT%%/*}"

PKCE="$(python3 - <<'PY'
import base64, hashlib, os
verifier = base64.urlsafe_b64encode(os.urandom(48)).decode().rstrip("=")
challenge = base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest()).decode().rstrip("=")
state = base64.urlsafe_b64encode(os.urandom(24)).decode().rstrip("=")
print(challenge)
print(state)
PY
)"
CHALLENGE="$(sed -n '1p' <<<"${PKCE}")"
STATE="$(sed -n '2p' <<<"${PKCE}")"
REDIRECT='com.globalenglish.app%3A%2Foauth%2Fcallback'
AUTH_PATH="/oauth/authorize?client_id=${OAUTH_CLIENT_ID}&response_type=code&redirect_uri=${REDIRECT}&scope=mobile%3Aapi&state=${STATE}&code_challenge=${CHALLENGE}&code_challenge_method=S256"

if http_nc "${WAN_HOST}" "${WAN_PORT}" "/api/v1/health/live" 8 | grep -Fq '200 OK'; then
  pass "Emulator reached WAN API ${WAN_ORIGIN}/api/v1/health/live"
else
  skip_note "WAN API unreachable from emulator (same-network hairpin NAT) — expected for ${WAN_ORIGIN}; mobile-data users should still reach it"
fi

if http_nc "10.0.2.2" "8080" "/api/v1/health/live" 5 | grep -Fq '200 OK'; then
  pass "Emulator reached host gateway 10.0.2.2:8080 (local fallback path)"
else
  bad "Emulator cannot reach host gateway 10.0.2.2:8080"
fi

if curl --silent --show-error --max-time 8 \
  -o /dev/null \
  -w '%{http_code}' \
  "http://127.0.0.1:8080${AUTH_PATH}" | grep -Eq '302|303'; then
  pass "Live OAuth authorize accepts packaged client id"
else
  bad "OAuth authorize rejected for packaged client id"
fi

if [[ -f "${E2E_ENV}" ]]; then
  # shellcheck disable=SC1090
  source "${E2E_ENV}"
fi

if [[ -n "${MOBILE_E2E_USERNAME:-}" && -n "${MOBILE_E2E_PASSWORD:-}" ]]; then
  MOBILE_E2E_API_ORIGIN="http://127.0.0.1:8080" \
  MOBILE_E2E_OAUTH_CLIENT_ID="${OAUTH_CLIENT_ID}" \
  MOBILE_E2E_USERNAME="${MOBILE_E2E_USERNAME}" \
  MOBILE_E2E_PASSWORD="${MOBILE_E2E_PASSWORD}" \
    python3 "${ROOT_DIR}/scripts/mobile-android-authenticated-check.py" \
    && pass "Live authenticated mobile API journey" \
    || bad "Live authenticated mobile API journey"
else
  skip_note "Set ${E2E_ENV} with MOBILE_E2E_USERNAME/PASSWORD to run live authenticated recording checks"
fi

printf '\n== Playwright mobile path ==\n'
(
  cd "${WEB_DIR}"
  MOBILE_E2E_API_ORIGIN="http://127.0.0.1:8080" \
  MOBILE_E2E_OAUTH_CLIENT_ID="${OAUTH_CLIENT_ID}" \
  MOBILE_E2E_USERNAME="${MOBILE_E2E_USERNAME:-}" \
  MOBILE_E2E_PASSWORD="${MOBILE_E2E_PASSWORD:-}" \
    pnpm exec playwright test --config=playwright.mobile-android.config.ts
) && pass "Playwright mobile-android-live suite" || bad "Playwright mobile-android-live suite"

printf '\nSummary: %s passed, %s failed, %s skipped\n' "${pass}" "${fail}" "${skip}"
if (( fail > 0 )); then
  exit 1
fi

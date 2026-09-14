#!/usr/bin/env bash
# Closed-beta smoke against the staff LAN origin the Android APK uses.
# Does not print tokens, cookies, passwords, or personal learner payloads.
set -euo pipefail

ORIGIN="${GE_STAFF_API_ORIGIN:-http://117.254.108.143:8080}"
USERNAME="${GE_STAFF_USERNAME:-northstar}"
PASSWORD="${GE_STAFF_PASSWORD:-}"
INSTALLATION_ID="${GE_STAFF_INSTALLATION_ID:-01K7NORTHSTAR0SMOKE0000001}"

if [[ -z "${PASSWORD}" ]]; then
  printf '%s\n' 'Set GE_STAFF_PASSWORD to run the authenticated smoke.' >&2
  exit 1
fi

fail() {
  printf 'FAIL %s\n' "$*" >&2
  exit 1
}

expect_http() {
  local label="$1"
  local expected="$2"
  local actual="$3"
  if [[ "${actual}" != "${expected}" ]]; then
    fail "${label}: expected HTTP ${expected}, got ${actual}"
  fi
  printf 'PASS %s HTTP %s\n' "${label}" "${actual}"
}

health="$(curl -sS -o /tmp/ge-smoke-health.json -w '%{http_code}' \
  "${ORIGIN}/api/v1/health/live")"
expect_http 'health/live' 200 "${health}"

mode_code="$(curl -sS -o /tmp/ge-smoke-mode.json -w '%{http_code}' \
  -H 'Accept: application/json' \
  "${ORIGIN}/api/v1/auth/mobile/password/mode")"
expect_http 'mobile password mode' 200 "${mode_code}"

client_id="$(python3 - <<'PY'
import json
body = json.load(open('/tmp/ge-smoke-mode.json', encoding='utf-8'))
mode = body.get('mode')
client_id = body.get('client_id')
if mode != 'password_only':
    raise SystemExit(f'unexpected mode {mode!r}')
if not isinstance(client_id, str) or len(client_id) < 32:
    raise SystemExit('mobile mode did not return an OAuth client UUID')
print(client_id)
PY
)"
printf 'PASS mobile client_id resolved\n'

sign_in_code="$(curl -sS -o /tmp/ge-smoke-signin.json -w '%{http_code}' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'Origin: https://localhost' \
  -X POST "${ORIGIN}/api/v1/auth/mobile/password/sign-ins" \
  --data "{\"username\":\"${USERNAME}\",\"password\":\"${PASSWORD}\",\"installation_id\":\"${INSTALLATION_ID}\",\"client_id\":\"${client_id}\"}")"
if [[ "${sign_in_code}" != "200" ]]; then
  python3 - <<'PY'
import json
try:
    body = json.load(open('/tmp/ge-smoke-signin.json', encoding='utf-8'))
except Exception:
    body = {}
keys = sorted(body.keys())
print('sign-in response keys:', keys)
PY
  fail "Android password sign-in HTTP ${sign_in_code}"
fi
printf 'PASS Android password sign-in HTTP 200\n'

access_token="$(python3 - <<'PY'
import json
body = json.load(open('/tmp/ge-smoke-signin.json', encoding='utf-8'))
token = body.get('access_token')
if not isinstance(token, str) or token == '':
    raise SystemExit('sign-in did not return an access token')
print(token)
PY
)"

auth_get() {
  local label="$1"
  local path="$2"
  local expected="${3:-200}"
  local code
  code="$(curl -sS -o /tmp/ge-smoke-auth.json -w '%{http_code}' \
    -H 'Accept: application/json' \
    -H "Authorization: Bearer ${access_token}" \
    "${ORIGIN}${path}")"
  expect_http "${label}" "${expected}" "${code}"
}

auth_get 'GET /me' '/api/v1/me'
python3 - <<'PY'
import json
body = json.load(open('/tmp/ge-smoke-auth.json', encoding='utf-8'))
user = body.get('user') if isinstance(body, dict) else None
user_id = user.get('id') if isinstance(user, dict) else None
if not user_id:
    raise SystemExit('GET /me missing user.id')
memberships = body.get('memberships')
if not isinstance(memberships, list) or memberships == []:
    raise SystemExit('GET /me returned no memberships')
print('PASS authenticated memberships', len(memberships))
PY

auth_get 'GET /me/levels' '/api/v1/me/levels'
python3 - <<'PY'
import json
body = json.load(open('/tmp/ge-smoke-auth.json', encoding='utf-8'))
levels = body.get('levels') if isinstance(body, dict) else None
if not isinstance(levels, list) or levels == []:
    raise SystemExit('GET /me/levels returned no levels')
print('PASS level catalogue count', len(levels))
PY

auth_get 'GET /me/levels/1/sessions' '/api/v1/me/levels/1/sessions'
audio_code="$(curl -sS -o /dev/null -w '%{http_code}' \
  -H "Authorization: Bearer ${access_token}" \
  -I "${ORIGIN}/api/v1/me/levels/1/sessions/introduction/audio")"
if [[ "${audio_code}" != "200" ]]; then
  fail "Level 1 introduction audio HTTP ${audio_code}"
fi
printf 'PASS Level 1 introduction audio HTTP 200\n'

rm -f /tmp/ge-smoke-signin.json /tmp/ge-smoke-auth.json
printf 'STAFF CLOSED-BETA SMOKE PASSED against %s\n' "${ORIGIN}"

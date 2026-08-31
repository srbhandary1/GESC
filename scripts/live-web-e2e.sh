#!/usr/bin/env bash
# Authenticated live web E2E against the soft-launch edge (HTTPS for microphone).

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB_DIR="${ROOT_DIR}/apps/web"
RUNTIME="${GE_SOFT_LAUNCH_RUNTIME:-${HOME}/ge-soft-launch}"
E2E_ENV="${LIVE_WEB_E2E_ENV:-${RUNTIME}/env/mobile-e2e.env}"
BASE_URL="${LIVE_WEB_BASE_URL:-http://127.0.0.1:8080}"

if [[ -f "${E2E_ENV}" ]]; then
  # shellcheck disable=SC1090
  source "${E2E_ENV}"
fi

export LIVE_WEB_BASE_URL="${BASE_URL}"
export LIVE_WEB_USERNAME="${LIVE_WEB_USERNAME:-${MOBILE_E2E_USERNAME:-}}"
export LIVE_WEB_PASSWORD="${LIVE_WEB_PASSWORD:-${MOBILE_E2E_PASSWORD:-}}"

if [[ -z "${LIVE_WEB_USERNAME}" || -z "${LIVE_WEB_PASSWORD}" ]]; then
  printf 'Missing credentials. Set %s or LIVE_WEB_USERNAME/LIVE_WEB_PASSWORD.\n' "${E2E_ENV}" >&2
  exit 1
fi

curl --silent --fail --max-time 5 "${BASE_URL}/api/v1/health/live" >/dev/null \
  || {
    printf 'Soft-launch web edge is unreachable at %s\n' "${BASE_URL}" >&2
    exit 1
  }

(
  cd "${WEB_DIR}"
  pnpm exec playwright test --config=playwright.live-web.config.ts "$@"
)

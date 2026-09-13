#!/usr/bin/env bash
# Deploy Variant A learner theme to the live soft-launch stack WITHOUT touching
# demo mode, audio routing, or Sanctum/session configuration beyond a normal
# soft-launch tunnel refresh.
#
# Run on Real-Studioz (or any host already running make soft-launch-start).
# Do NOT use make demo-client-tunnel for this — demo is synthetic TTS only.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BRANCH="${GE_THEME_DEPLOY_BRANCH:-cursor/learner-variant-a-theme-3f88}"

cd "${ROOT_DIR}"

printf '%s\n' \
  '=== Variant A theme → soft-launch (production web, real lesson MP3s) ===' \
  ''

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Run from the GESC repository root." >&2
  exit 1
fi

current_branch="$(git branch --show-current)"
if [[ "${current_branch}" != "${BRANCH}" ]]; then
  git fetch origin "${BRANCH}"
  git checkout "${BRANCH}"
  git pull --ff-only origin "${BRANCH}" || true
fi

make soft-launch-refresh-web

if make soft-launch-status 2>/dev/null | grep -q 'tunnel.*running'; then
  printf '%s\n' '' 'Tunnel already running. Restarting API so Sanctum matches the live origin…'
  make soft-launch-restart
else
  printf '%s\n' '' 'Starting HTTPS tunnel (updates Sanctum + APP_URL for sign-in)…'
  make soft-launch-tunnel
fi

url=""
if [[ -f "${HOME}/ge-soft-launch/tunnel.url" ]]; then
  url="$(tr -d '[:space:]' < "${HOME}/ge-soft-launch/tunnel.url")"
fi

cat <<EOF

Done.
- Production web rebuilt (no VITE_LOCAL_DEMO).
- Learn/audio flow unchanged from main; only theme chrome updated.
- Sign in on the soft-launch HTTPS URL below, then open #learn.

EOF

if [[ -n "${url}" ]]; then
  printf '  %s/#learn\n\n' "${url}"
else
  printf '  Run: make soft-launch-status\n  or:  cat ~/ge-soft-launch/tunnel.url\n\n'
fi

printf '%s\n' \
  'Do not use Cloud Agent demo tunnels or scripts/run-review-soft-launch-proxy-tunnel.sh' \
  'for lesson audio — they break Sanctum sign-in.'

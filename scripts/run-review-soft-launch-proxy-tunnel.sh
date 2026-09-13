#!/usr/bin/env bash
# Proxies a temporary Cloudflare HTTPS URL to the operator's live soft-launch
# Caddy listener. Use this for remote review when a prior agent mistakenly
# published the synthetic VITE_LOCAL_DEMO dev server instead of real product.
#
# Requires: cloudflared binary and network reachability to the soft-launch host.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET="${GE_REVIEW_SOFT_LAUNCH_TARGET:-http://117.254.108.143:8080}"
PID_FILE="${GE_REVIEW_SOFT_LAUNCH_TUNNEL_PID_FILE:-${ROOT_DIR}/tmp/review-soft-launch-tunnel.pid}"
LOG_FILE="${GE_REVIEW_SOFT_LAUNCH_TUNNEL_LOG_FILE:-${ROOT_DIR}/tmp/review-soft-launch-tunnel.log}"
URL_FILE="${GE_REVIEW_SOFT_LAUNCH_TUNNEL_URL_FILE:-${ROOT_DIR}/tmp/review-soft-launch-tunnel.url}"
CLOUDFLARED="${GE_REVIEW_SOFT_LAUNCH_CLOUDFLARED:-/tmp/cloudflared}"
MODE="${1:-start}"

mkdir -p "${ROOT_DIR}/tmp"

resolve_cloudflared() {
  if [[ -x "${CLOUDFLARED}" ]]; then
    return 0
  fi
  if command -v cloudflared >/dev/null 2>&1; then
    CLOUDFLARED="$(command -v cloudflared)"
    return 0
  fi
  echo "cloudflared is required. Install it or set GE_REVIEW_SOFT_LAUNCH_CLOUDFLARED." >&2
  exit 1
}

wait_for_target() {
  curl --silent --show-error --fail --max-time 5 "${TARGET}/api/v1/health/live" >/dev/null
}

stop_tunnel() {
  if [[ -f "${PID_FILE}" ]]; then
    local pid
    pid="$(cat "${PID_FILE}")"
    kill "${pid}" 2>/dev/null || true
    rm -f "${PID_FILE}"
  fi
  pkill -f 'cloudflared tunnel --url http://127.0.0.1:4174' 2>/dev/null || true
}

start_tunnel() {
  resolve_cloudflared
  wait_for_target
  stop_tunnel
  rm -f "${LOG_FILE}" "${URL_FILE}"
  nohup "${CLOUDFLARED}" tunnel --url "${TARGET}" --no-autoupdate \
    >"${LOG_FILE}" 2>&1 &
  echo $! >"${PID_FILE}"

  local deadline=$(( $(date +%s) + 45 ))
  local url=""
  while (( $(date +%s) < deadline )); do
    url="$(grep -oE 'https://[a-zA-Z0-9-]+\.trycloudflare\.com' "${LOG_FILE}" | head -1 || true)"
    if [[ -n "${url}" ]]; then
      printf '%s\n' "${url}" >"${URL_FILE}"
      cat <<EOF

============================================================
Review soft-launch HTTPS proxy is ready
============================================================
Use this URL (real product + governed lesson audio):
  ${url}

Proxied target: ${TARGET}
Sign in with your soft-launch username/password.
Do NOT use demo-client-tunnel URLs for lesson MP3 review.

Stop: bash scripts/run-review-soft-launch-proxy-tunnel.sh stop
============================================================

EOF
      return 0
    fi
    sleep 0.5
  done
  echo "Timed out waiting for trycloudflare.com URL. Log:" >&2
  tail -20 "${LOG_FILE}" >&2 || true
  exit 1
}

case "${MODE}" in
  start) start_tunnel ;;
  stop) stop_tunnel ;;
  status)
    if [[ -f "${PID_FILE}" ]] && kill -0 "$(cat "${PID_FILE}")" 2>/dev/null; then
      echo "Review soft-launch tunnel running (pid $(cat "${PID_FILE}"))."
      [[ -f "${URL_FILE}" ]] && cat "${URL_FILE}"
    else
      echo "Review soft-launch tunnel is not running."
    fi
    ;;
  *)
    echo "Usage: $0 {start|stop|status}" >&2
    exit 1
    ;;
esac

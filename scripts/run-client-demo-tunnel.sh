#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PID_FILE="${GE_CLIENT_DEMO_TUNNEL_PID_FILE:-${ROOT_DIR}/tmp/client-demo-tunnel.pid}"
LOG_FILE="${GE_CLIENT_DEMO_TUNNEL_LOG_FILE:-${ROOT_DIR}/tmp/client-demo-tunnel.log}"
URL_FILE="${GE_CLIENT_DEMO_TUNNEL_URL_FILE:-${ROOT_DIR}/tmp/client-demo-tunnel.url}"
TARGET_URL="${GE_CLIENT_DEMO_TUNNEL_TARGET:-http://127.0.0.1:8080}"
MODE="${1:-start}"
CLOUDFLARED="${GE_CLIENT_DEMO_CLOUDFLARED:-cloudflared}"
TUNNEL_PID=""

resolve_cloudflared() {
  if [[ -x "${HOME}/.local/bin/cloudflared" ]]; then
    CLOUDFLARED="${HOME}/.local/bin/cloudflared"
    return 0
  fi
  if command -v cloudflared >/dev/null 2>&1; then
    CLOUDFLARED="$(command -v cloudflared)"
    return 0
  fi
  echo "cloudflared is required for the temporary HTTPS tunnel." >&2
  echo "Install it or set GE_CLIENT_DEMO_CLOUDFLARED to the binary path." >&2
  exit 1
}

wait_for_local_demo() {
  local deadline
  deadline=$(($(date +%s) + 30))
  while (( $(date +%s) < deadline )); do
    if curl --silent --show-error --fail --max-time 2 "${TARGET_URL}/healthz" >/dev/null 2>&1; then
      return 0
    fi
    sleep 0.4
  done
  echo "Local demo is not reachable at ${TARGET_URL}/healthz." >&2
  echo "Start it first with: make demo-client" >&2
  exit 1
}

extract_url() {
  local line url
  while IFS= read -r line; do
    if [[ "${line}" =~ https://[a-zA-Z0-9-]+\.trycloudflare\.com ]]; then
      url="$(grep -oE 'https://[a-zA-Z0-9-]+\.trycloudflare\.com' <<<"${line}" | head -1)"
      if [[ -n "${url}" ]]; then
        printf '%s\n' "${url}" > "${URL_FILE}"
        cat <<EOF

============================================================
Temporary HTTPS client tunnel is ready
============================================================
Share this URL with the client:
  ${url}

APK (if built):
  ${url}/downloads/global-english-demo.apk

This Cloudflare quick tunnel is temporary and account-less.
Prefer the static-IP URL after router port-forward is set:
  see docs/CLIENT_DEMO_RUNBOOK.md
Stop with: make demo-client-tunnel-stop
============================================================

EOF
        return 0
      fi
    fi
  done
  return 1
}

status_tunnel() {
  if [[ -f "${PID_FILE}" ]]; then
    local pid
    pid="$(cat "${PID_FILE}")"
    if kill -0 "${pid}" 2>/dev/null; then
      echo "Client demo tunnel is running (pid ${pid})."
      if [[ -f "${URL_FILE}" ]]; then
        echo "Client URL: $(cat "${URL_FILE}")"
      fi
      return 0
    fi
  fi
  echo "Client demo tunnel is not running."
  return 1
}

stop_tunnel() {
  if [[ ! -f "${PID_FILE}" ]]; then
    echo "No client demo tunnel pid file found."
    return 0
  fi

  local pid
  pid="$(cat "${PID_FILE}")"
  if kill -0 "${pid}" 2>/dev/null; then
    kill "${pid}" 2>/dev/null || true
    wait "${pid}" 2>/dev/null || true
    echo "Stopped client demo tunnel (pid ${pid})."
  else
    echo "Stale tunnel pid file removed; tunnel was not running."
  fi
  rm -f "${PID_FILE}"
}

cleanup() {
  if [[ -n "${TUNNEL_PID}" ]]; then
    kill "${TUNNEL_PID}" 2>/dev/null || true
    wait "${TUNNEL_PID}" 2>/dev/null || true
    TUNNEL_PID=""
  fi
  rm -f "${PID_FILE}"
}

start_tunnel() {
  resolve_cloudflared
  wait_for_local_demo

  if [[ -f "${PID_FILE}" ]] && kill -0 "$(cat "${PID_FILE}")" 2>/dev/null; then
    echo "Tunnel already running (pid $(cat "${PID_FILE}"))."
    [[ -f "${URL_FILE}" ]] && echo "Client URL: $(cat "${URL_FILE}")"
    exit 0
  fi

  mkdir -p "$(dirname "${PID_FILE}")" "$(dirname "${LOG_FILE}")"
  : > "${LOG_FILE}"
  rm -f "${URL_FILE}"
  trap cleanup EXIT INT TERM

  echo "Starting temporary Cloudflare quick tunnel to ${TARGET_URL}..."
  "${CLOUDFLARED}" tunnel --url "${TARGET_URL}" --no-autoupdate >"${LOG_FILE}" 2>&1 &
  TUNNEL_PID=$!
  echo "${TUNNEL_PID}" > "${PID_FILE}"

  local deadline
  deadline=$(($(date +%s) + 60))
  while (( $(date +%s) < deadline )); do
    if extract_url < "${LOG_FILE}"; then
      wait "${TUNNEL_PID}"
      TUNNEL_PID=""
      rm -f "${PID_FILE}"
      trap - EXIT INT TERM
      exit 0
    fi
    if ! kill -0 "${TUNNEL_PID}" 2>/dev/null; then
      echo "Tunnel process exited early. Log:" >&2
      tail -n 40 "${LOG_FILE}" >&2 || true
      cleanup
      trap - EXIT INT TERM
      exit 1
    fi
    sleep 0.5
  done

  echo "Timed out waiting for a trycloudflare.com URL. Log:" >&2
  tail -n 40 "${LOG_FILE}" >&2 || true
  cleanup
  trap - EXIT INT TERM
  exit 1
}

case "${MODE}" in
  start)
    start_tunnel
    ;;
  stop)
    stop_tunnel
    ;;
  status)
    status_tunnel
    ;;
  *)
    echo "Usage: $0 {start|stop|status}" >&2
    exit 1
    ;;
esac

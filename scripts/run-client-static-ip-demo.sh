#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PID_FILE="${GE_CLIENT_DEMO_PID_FILE:-${ROOT_DIR}/tmp/client-static-ip-demo.pid}"
LOG_FILE="${GE_CLIENT_DEMO_LOG_FILE:-${ROOT_DIR}/tmp/client-static-ip-demo.log}"
PUBLIC_HOST="${GE_CLIENT_DEMO_PUBLIC_HOST:-117.254.108.143}"
DEMO_PORT="${GE_CLIENT_DEMO_PORT:-8080}"
BIND_HOST="${GE_CLIENT_DEMO_BIND_HOST:-0.0.0.0}"
MODE="${1:-start}"
DIST_DIR="${ROOT_DIR}/apps/web/dist-demo"
APK_PATH="${ROOT_DIR}/apps/web/android/app/build/outputs/apk/debug/app-debug.apk"
DEMO_PID=""

is_ipv4() {
  local address="$1"
  local first second third fourth

  [[ "${address}" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]] || return 1
  IFS=. read -r first second third fourth <<< "${address}"
  (( first <= 255 && second <= 255 && third <= 255 && fourth <= 255 ))
}

validate_port() {
  local name="$1"
  local value="$2"

  if [[ ! "${value}" =~ ^[0-9]+$ ]] || (( value < 1 || value > 65535 )); then
    echo "${name} must be an integer from 1 through 65535." >&2
    exit 1
  fi
}

ensure_port_available() {
  local port="$1"

  if ss -tln | awk '{print $4}' | grep -Eq "(:|\\.)${port}$"; then
    echo "Port ${port} is already in use. Stop the earlier listener or choose another port." >&2
    exit 1
  fi
}

detect_lan_address() {
  local candidate=""

  if command -v ip >/dev/null 2>&1; then
    candidate="$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for (i = 1; i <= NF; i++) if ($i == "src") { print $(i + 1); exit }}')"
    if [[ -n "${candidate}" ]]; then
      printf '%s\n' "${candidate}"
      return 0
    fi
  fi

  hostname -I 2>/dev/null | awk '{print $1; exit}'
}

select_node() {
  local expected_node
  local local_node_bin="${ROOT_DIR}/tmp/toolchain/node/bin"

  expected_node="$(cat "${ROOT_DIR}/.node-version")"

  if [[ -x "${local_node_bin}/node" ]]; then
    PATH="${local_node_bin}:${PATH}"
    export PATH
  fi

  if ! command -v node >/dev/null 2>&1 || [[ "$(node --version | sed 's/^v//')" != "${expected_node}" ]]; then
    echo "Node.js ${expected_node} is required. Run make bootstrap after installing the pinned toolchain." >&2
    exit 1
  fi
}

wait_for_url() {
  local label="$1"
  local url="$2"
  local deadline

  deadline=$(($(date +%s) + 45))
  while (( $(date +%s) < deadline )); do
    if curl --silent --show-error --fail --max-time 2 "${url}" >/dev/null 2>&1; then
      return 0
    fi
    sleep 0.4
  done

  echo "${label} did not become reachable at ${url}." >&2
  return 1
}

print_access_card() {
  local lan_address="$1"
  local apk_line=""

  if [[ -f "${APK_PATH}" ]]; then
    apk_line="Android APK:   http://${PUBLIC_HOST}:${DEMO_PORT}/downloads/global-english-demo.apk"
  else
    apk_line="Android APK:   not built yet (run: make demo-android)"
  fi

  cat <<EOF

============================================================
Global English client demo is ready
============================================================
Client URL:     http://${PUBLIC_HOST}:${DEMO_PORT}
Local check:    http://${lan_address}:${DEMO_PORT}
${apk_line}

This is the disclosed synthetic in-memory demo only:
  no learner accounts, no API, no providers, no production data.
Browser microphone capture needs HTTPS or localhost; over plain
HTTP the client can still walk the mission using the mic-free path.

Router requirement (one-time):
  Forward TCP ${DEMO_PORT} on ${PUBLIC_HOST}
  to this machine ${lan_address}:${DEMO_PORT}

Keep this process running while the client is testing.
Stop with Ctrl+C, or: make demo-client-stop
============================================================

EOF
}

status_demo() {
  if [[ -f "${PID_FILE}" ]]; then
    local pid
    pid="$(cat "${PID_FILE}")"
    if kill -0 "${pid}" 2>/dev/null; then
      echo "Client static-IP demo is running (pid ${pid})."
      echo "Client URL: http://${PUBLIC_HOST}:${DEMO_PORT}"
      return 0
    fi
  fi
  echo "Client static-IP demo is not running."
  return 1
}

stop_demo() {
  if [[ ! -f "${PID_FILE}" ]]; then
    echo "No client static-IP demo pid file found."
    return 0
  fi

  local pid
  pid="$(cat "${PID_FILE}")"
  if kill -0 "${pid}" 2>/dev/null; then
    kill "${pid}" 2>/dev/null || true
    wait "${pid}" 2>/dev/null || true
    echo "Stopped client static-IP demo (pid ${pid})."
  else
    echo "Stale pid file removed; demo was not running."
  fi
  rm -f "${PID_FILE}"
}

check_demo() {
  is_ipv4 "${PUBLIC_HOST}" || {
    echo "GE_CLIENT_DEMO_PUBLIC_HOST must be an IPv4 address." >&2
    exit 1
  }
  validate_port GE_CLIENT_DEMO_PORT "${DEMO_PORT}"

  local lan_address
  lan_address="$(detect_lan_address)"
  echo "Public host: ${PUBLIC_HOST}"
  echo "LAN host:    ${lan_address:-unknown}"
  echo "Bind host:   ${BIND_HOST}"
  echo "Public port: ${DEMO_PORT}"
  echo "Client URL:  http://${PUBLIC_HOST}:${DEMO_PORT}"
  echo "Bundle dir:  ${DIST_DIR}"
  echo "APK path:    ${APK_PATH}"
  echo
  echo "Confirm router port-forward: ${PUBLIC_HOST}:${DEMO_PORT} -> ${lan_address}:${DEMO_PORT}"
}

cleanup() {
  if [[ -n "${DEMO_PID}" ]]; then
    kill "${DEMO_PID}" 2>/dev/null || true
    wait "${DEMO_PID}" 2>/dev/null || true
    DEMO_PID=""
  fi
  rm -f "${PID_FILE}"
}

ensure_bundle() {
  if [[ ! -f "${DIST_DIR}/index.html" ]]; then
    echo "Demo bundle missing. Building with make demo-build..."
    (cd "${ROOT_DIR}" && make demo-build)
  fi
}

start_demo() {
  check_demo
  ensure_port_available "${DEMO_PORT}"
  select_node
  ensure_bundle

  local lan_address
  lan_address="$(detect_lan_address)"
  local apk_arg=()

  if [[ -f "${APK_PATH}" ]]; then
    apk_arg=("${APK_PATH}")
  fi

  mkdir -p "$(dirname "${PID_FILE}")" "$(dirname "${LOG_FILE}")"
  trap cleanup EXIT INT TERM

  echo "Starting synthetic demo for remote client testing..."
  node "${ROOT_DIR}/scripts/demo-client-static-server.mjs" \
    "${BIND_HOST}" \
    "${DEMO_PORT}" \
    "${PUBLIC_HOST}" \
    "${DIST_DIR}" \
    "${apk_arg[@]+"${apk_arg[@]}"}" \
    >"${LOG_FILE}" 2>&1 &
  DEMO_PID=$!
  echo "${DEMO_PID}" > "${PID_FILE}"

  wait_for_url "Client demo server" "http://${lan_address}:${DEMO_PORT}/healthz" || {
    echo "Demo failed to become ready. Last log lines:" >&2
    tail -n 40 "${LOG_FILE}" >&2 || true
    cleanup
    trap - EXIT INT TERM
    exit 1
  }

  print_access_card "${lan_address}"

  if curl --silent --show-error --fail --max-time 5 "http://${PUBLIC_HOST}:${DEMO_PORT}/healthz" >/dev/null 2>&1; then
    echo "Public URL responded from this host (hairpin/NAT loopback works)."
  else
    echo "Note: this host could not reach ${PUBLIC_HOST}:${DEMO_PORT} directly."
    echo "That is common behind consumer NAT. Ask the client to open the URL,"
    echo "or verify the router forwards TCP ${DEMO_PORT} to ${lan_address}."
  fi
  echo
  echo "Log file: ${LOG_FILE}"
  echo

  wait "${DEMO_PID}"
  DEMO_PID=""
  rm -f "${PID_FILE}"
  trap - EXIT INT TERM
}

case "${MODE}" in
  start)
    start_demo
    ;;
  stop)
    stop_demo
    ;;
  status)
    status_demo
    ;;
  check)
    check_demo
    ;;
  *)
    echo "Usage: $0 {start|stop|status|check}" >&2
    exit 1
    ;;
esac

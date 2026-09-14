#!/usr/bin/env bash
# Ubuntu invite-only soft launch runtime (APP-0053).
# Serves the real web dist + Laravel API behind loopback Caddy and an optional
# Cloudflare HTTPS tunnel. Does not use the synthetic demo bundle.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENVIRONMENT_LOADER="${ROOT_DIR}/scripts/lib/soft-launch-environment.sh"
PUBLIC_HTTP_CONFIGURATOR="${ROOT_DIR}/scripts/lib/soft-launch-public-http.sh"
PHP_BIN="${GE_SOFT_LAUNCH_PHP:-${ROOT_DIR}/tmp/toolchain/php/php}"
API_PUBLIC="${ROOT_DIR}/apps/api/public"
API_ROUTER="${ROOT_DIR}/apps/api/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
NODE_BIN="${GE_SOFT_LAUNCH_NODE:-${ROOT_DIR}/tmp/toolchain/node/bin/node}"
PNPM_BIN="${GE_SOFT_LAUNCH_PNPM:-${ROOT_DIR}/tmp/toolchain/node/bin/pnpm}"
CADDY_BIN="${GE_SOFT_LAUNCH_CADDY:-${HOME}/.local/bin/caddy}"
CLOUDFLARED_BIN="${GE_SOFT_LAUNCH_CLOUDFLARED:-${HOME}/.local/bin/cloudflared}"

RUNTIME_DIR="${GE_SOFT_LAUNCH_RUNTIME:-${HOME}/ge-soft-launch}"
ENV_FILE="${GE_SOFT_LAUNCH_ENV:-${RUNTIME_DIR}/env/soft-launch.env}"
PID_DIR="${RUNTIME_DIR}/run"
LOG_DIR="${RUNTIME_DIR}/logs"
WEB_DIST="${ROOT_DIR}/apps/web/dist"
CADDYFILE="${ROOT_DIR}/infra/soft-launch/Caddyfile"
PROXY_PORT="${GE_SOFT_LAUNCH_PROXY_PORT:-9080}"
API_PORT="${GE_SOFT_LAUNCH_API_PORT:-8000}"
PUBLIC_HTTP_PORT="${GE_SOFT_LAUNCH_PUBLIC_HTTP_PORT:-8080}"
API_PORT_FALLBACK="${GE_SOFT_LAUNCH_API_PORT_FALLBACK:-8010}"
PUBLIC_HTTPS_PORT="${GE_SOFT_LAUNCH_PUBLIC_HTTPS_PORT:-8443}"
REQUESTED_STABLE_HOST="${GE_SOFT_LAUNCH_STABLE_HOST:-}"

MODE="${1:-status}"

mkdir -p "${PID_DIR}" "${LOG_DIR}" "${RUNTIME_DIR}/env" "${RUNTIME_DIR}/backups"

die() {
  printf '%s\n' "$*" >&2
  exit 1
}

validate_stable_host() {
  local host="$1"
  [[ "${host}" =~ ^[a-z0-9]([a-z0-9.-]*[a-z0-9])$ ]] \
    && [[ "${host}" == *.* ]] \
    || die "GE_SOFT_LAUNCH_STABLE_HOST must be a lowercase DNS hostname without a scheme, path or port."
}

stable_host_from_environment() {
  source_soft_launch_env
  local host="${GE_SOFT_LAUNCH_STABLE_HOST:-}"
  if [[ -n "${host}" ]]; then
    validate_stable_host "${host}"
  fi
  printf '%s' "${host}"
}

require_php() {
  if [[ ! -x "${PHP_BIN}" ]]; then
    PHP_BIN="$(command -v php || true)"
  fi
  [[ -x "${PHP_BIN}" ]] || die "PHP binary not found. Set GE_SOFT_LAUNCH_PHP."
}

require_node() {
  if [[ ! -x "${NODE_BIN}" ]]; then
    NODE_BIN="$(command -v node || true)"
  fi
  [[ -x "${NODE_BIN}" ]] || die "Node binary not found. Set GE_SOFT_LAUNCH_NODE."
}

web_build_fingerprint() {
  require_node
  "${NODE_BIN}" "${ROOT_DIR}/apps/web/buildFingerprint.ts"
}

require_caddy() {
  if [[ ! -x "${CADDY_BIN}" ]]; then
    if command -v caddy >/dev/null 2>&1; then
      CADDY_BIN="$(command -v caddy)"
    else
      die "caddy not found. Run: bash scripts/install-soft-launch-caddy.sh"
    fi
  fi
}

pid_alive() {
  local pid_file="$1"
  [[ -f "${pid_file}" ]] || return 1
  local pid
  pid="$(cat "${pid_file}")"
  kill -0 "${pid}" 2>/dev/null
}

stop_pidfile() {
  local name="$1"
  local pid_file="${PID_DIR}/${name}.pid"
  if pid_alive "${pid_file}"; then
    kill "$(cat "${pid_file}")" 2>/dev/null || true
    sleep 0.5
    if pid_alive "${pid_file}"; then
      kill -9 "$(cat "${pid_file}")" 2>/dev/null || true
    fi
  fi
  rm -f "${pid_file}"
}

# Kill leftover listeners on a TCP port (stale Caddy on :8080 is the usual case).
release_listen_port() {
  local port="$1"
  local pid
  local attempt
  for attempt in 1 2 3 4 5; do
    pid="$(ss -ltnp "sport = :${port}" 2>/dev/null \
      | sed -n 's/.*pid=\([0-9][0-9]*\).*/\1/p' \
      | sort -u \
      | head -1 || true)"
    if [[ -z "${pid}" ]]; then
      return 0
    fi
    kill "${pid}" 2>/dev/null || true
    sleep 0.3
    if kill -0 "${pid}" 2>/dev/null; then
      kill -9 "${pid}" 2>/dev/null || true
      sleep 0.2
    fi
  done
  if ss -ltn "sport = :${port}" 2>/dev/null | grep -q ":${port}"; then
    die "Port ${port} is still in use after releasing leftover listeners."
  fi
}

ensure_services() {
  (cd "${ROOT_DIR}" && docker compose up -d --wait postgres redis object-storage mail otp-stub)
}

stage_android_download() {
  local apk="${ROOT_DIR}/apps/web/public/downloads/global-spoken-english-android.apk"
  local staged="${WEB_DIST}/downloads/global-spoken-english-android.apk"

  if [[ ! -f "${apk}" ]]; then
    return 0
  fi

  mkdir -p "${WEB_DIST}/downloads"
  install -m 0644 "${apk}" "${staged}"
}

ensure_web_dist() {
  # Invite-only soft-launch uses an explicit translation-preview build so draft
  # Kannada and other pending catalogues render with the visible preview notice.
  local stamp="${WEB_DIST}/.soft-launch-web-build"
  local current_fingerprint
  local stamped_fingerprint=""
  current_fingerprint="$(web_build_fingerprint)"
  if [[ -f "${stamp}" ]]; then
    stamped_fingerprint="$(<"${stamp}")"
  fi
  if [[ ! -f "${WEB_DIST}/index.html" || "${stamped_fingerprint}" != "${current_fingerprint}" ]]; then
    require_php
    if [[ -x "${PNPM_BIN}" ]]; then
      (cd "${ROOT_DIR}" && PATH="$(dirname "${NODE_BIN}"):${PATH}" \
        env -u VITE_MOBILE_API_ORIGIN \
        -u VITE_MOBILE_OAUTH_CLIENT_ID \
        -u VITE_MOBILE_OAUTH_REDIRECT_URI \
        -u VITE_MOBILE_OAUTH_AUTHORIZE_URL \
        -u VITE_MOBILE_OAUTH_TOKEN_URL \
        -u GE_ANDROID_WEB_BUNDLE \
        VITE_UI_TRANSLATION_PREVIEW=true \
        "${PNPM_BIN}" --filter @global-english/web build)
    else
      (cd "${ROOT_DIR}" && env -u VITE_MOBILE_API_ORIGIN \
        -u VITE_MOBILE_OAUTH_CLIENT_ID \
        -u VITE_MOBILE_OAUTH_REDIRECT_URI \
        -u VITE_MOBILE_OAUTH_AUTHORIZE_URL \
        -u VITE_MOBILE_OAUTH_TOKEN_URL \
        -u GE_ANDROID_WEB_BUNDLE \
        VITE_UI_TRANSLATION_PREVIEW=true \
        pnpm --filter @global-english/web build)
    fi
    local built_fingerprint
    built_fingerprint="$(web_build_fingerprint)"
    [[ "${built_fingerprint}" == "${current_fingerprint}" ]] \
      || die "Web inputs changed during the build; restart to produce one coherent release."
    printf '%s\n' "${built_fingerprint}" >"${stamp}"
    printf 'soft-launch-ui-preview\n' >"${WEB_DIST}/.soft-launch-ui-preview"
    rm -f "${WEB_DIST}/.soft-launch-ui-approved-only"
  fi
  stage_android_download
  [[ -f "${WEB_DIST}/index.html" ]] || die "Web dist missing at ${WEB_DIST}"
}

source_soft_launch_env() {
  # shellcheck disable=SC1090
  source "${ENVIRONMENT_LOADER}"
  GE_SOFT_LAUNCH_ENV="${ENV_FILE}" load_soft_launch_environment
}

api_listen_port_free() {
  python3 - "$1" <<'PY'
import socket
import sys

port = int(sys.argv[1])
sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
try:
    sock.bind(("127.0.0.1", port))
except OSError:
    raise SystemExit(1)
finally:
    sock.close()
PY
}

api_health_ok() {
  curl --silent --fail --max-time 2 "http://127.0.0.1:${1}/api/v1/health/live" >/dev/null 2>&1
}

api_listener_pid() {
  ss -H -l -t -n -p "sport = :${1}" 2>/dev/null \
    | grep -oE 'pid=[0-9]+' \
    | head -1 \
    | cut -d= -f2
}

record_api_pid() {
  local pid="$1"
  if [[ -n "${pid}" ]] && kill -0 "${pid}" 2>/dev/null; then
    printf '%s\n' "${pid}" >"${PID_DIR}/api.pid"
  fi
}

persist_api_listen_port() {
  local port="$1"
  python3 - "${ENV_FILE}" "${port}" <<'PY'
from pathlib import Path
import sys

path = Path(sys.argv[1])
port = sys.argv[2]
text = path.read_text(encoding="utf-8") if path.exists() else ""
key = "GE_SOFT_LAUNCH_API_PORT"
written = False
lines = []
for raw in text.splitlines():
    if raw.startswith(f"{key}="):
        lines.append(f"{key}={port}")
        written = True
    else:
        lines.append(raw)
if not written:
    lines.append(f"{key}={port}")
path.write_text("\n".join(lines) + "\n", encoding="utf-8")
path.chmod(0o600)
PY
  GE_SOFT_LAUNCH_API_PORT="${port}"
  API_PORT="${port}"
  export GE_SOFT_LAUNCH_API_PORT API_PORT
}

ensure_api_listen_port() {
  local port
  local seen=" "
  for port in ${GE_SOFT_LAUNCH_API_PORT:-} 8000 "${API_PORT_FALLBACK}" 8011 8012 8013 8014 8015; do
    [[ -n "${port}" ]] || continue
    [[ "${seen}" == *" ${port} "* ]] && continue
    seen+=" ${port} "
    if api_health_ok "${port}"; then
      if [[ "${port}" != "8000" ]]; then
        printf 'Reusing Laravel API already live on 127.0.0.1:%s\n' "${port}" >&2
      fi
      persist_api_listen_port "${port}"
      record_api_pid "$(api_listener_pid "${port}")"
      return
    fi
    if api_listen_port_free "${port}"; then
      if [[ "${port}" != "8000" ]]; then
        printf 'Port 8000 is unavailable; binding Laravel API on 127.0.0.1:%s\n' "${port}" >&2
      fi
      persist_api_listen_port "${port}"
      return
    fi
  done
  die "No free Laravel API port between 8000 and 8015; another process is holding the listeners."
}

start_api() {
  require_php
  [[ -f "${API_PUBLIC}/index.php" ]] || die "Laravel public entrypoint is missing."
  [[ -f "${API_ROUTER}" ]] || die "Laravel development-server router is missing."
  source_soft_launch_env
  local api_port="${GE_SOFT_LAUNCH_API_PORT:-8000}"
  if api_health_ok "${api_port}"; then
    record_api_pid "$(api_listener_pid "${api_port}")"
    return
  fi
  stop_pidfile api
  (
    cd "${API_PUBLIC}"
    source_soft_launch_env
    api_port="${GE_SOFT_LAUNCH_API_PORT:-8000}"
    nohup "${PHP_BIN}" -S "127.0.0.1:${api_port}" "${API_ROUTER}" \
      >"${LOG_DIR}/api.log" 2>&1 &
    echo $! >"${PID_DIR}/api.pid"
  )
}

start_worker() {
  require_php
  stop_pidfile worker
  (
    cd "${ROOT_DIR}/apps/api"
    source_soft_launch_env
    nohup "${PHP_BIN}" artisan queue:work --tries=1 --timeout=90 \
      >"${LOG_DIR}/worker.log" 2>&1 &
    echo $! >"${PID_DIR}/worker.pid"
  )
}

start_scheduler() {
  require_php
  stop_pidfile scheduler
  (
    cd "${ROOT_DIR}/apps/api"
    source_soft_launch_env
    nohup bash -c "while true; do '${PHP_BIN}' artisan schedule:run --verbose --no-interaction; sleep 60; done" \
      >"${LOG_DIR}/scheduler.log" 2>&1 &
    echo $! >"${PID_DIR}/scheduler.pid"
  )
}

start_proxy() {
  require_caddy
  stop_pidfile caddy
  ensure_web_dist
  source_soft_launch_env
  export GE_SOFT_LAUNCH_API_PORT="${GE_SOFT_LAUNCH_API_PORT:-8000}"
  export GE_SOFT_LAUNCH_WEB_ROOT="${WEB_DIST}"
  nohup "${CADDY_BIN}" run --config "${CADDYFILE}" --adapter caddyfile \
    >"${LOG_DIR}/caddy.log" 2>&1 &
  echo $! >"${PID_DIR}/caddy.pid"
}

start_public_http() {
  # Staff Tailscale/LAN testing: GE_SOFT_LAUNCH_SERVE_PUBLIC_HTTP=true serves the
  # SPA on :8080. Otherwise HTML 307-redirects to the live HTTPS origin while
  # /api and /oauth reverse-proxy for the Android HTTP origin.
  require_caddy
  stop_pidfile public-http
  release_listen_port "${PUBLIC_HTTP_PORT}"
  ensure_web_dist
  source_soft_launch_env
  local api_port="${GE_SOFT_LAUNCH_API_PORT:-${API_PORT}}"
  if ! api_health_ok "${api_port}"; then
    die "Laravel API is not healthy on 127.0.0.1:${api_port}; public HTTP would 502 Android login."
  fi
  local stable_host="${GE_SOFT_LAUNCH_STABLE_HOST:-}"
  local public_https_port="${GE_SOFT_LAUNCH_PUBLIC_HTTPS_PORT:-${PUBLIC_HTTPS_PORT}}"
  if [[ -n "${stable_host}" ]]; then
    validate_stable_host "${stable_host}"
    [[ "${APP_URL:-}" == "https://${stable_host}" ]] \
      || die "APP_URL must equal https://${stable_host} when the stable HTTPS edge is enabled."
  fi
  # shellcheck disable=SC1090
  source "${PUBLIC_HTTP_CONFIGURATOR}"
  write_soft_launch_public_http_config \
    "${RUNTIME_DIR}/env/Caddyfile.public" \
    "${PUBLIC_HTTP_PORT}" \
    "${api_port}" \
    "${WEB_DIST}" \
    "${APP_URL:-}" \
    "${stable_host}" \
    "${public_https_port}" \
    "${PROXY_PORT}"
  if grep -Fq 'encode off' "${RUNTIME_DIR}/env/Caddyfile.public"; then
    die "Caddyfile.public contains unsupported 'encode off' and would fail to start."
  fi
  nohup "${CADDY_BIN}" run --config "${RUNTIME_DIR}/env/Caddyfile.public" --adapter caddyfile \
    >"${LOG_DIR}/public-http.log" 2>&1 &
  echo $! >"${PID_DIR}/public-http.pid"
  local deadline=$(($(date +%s) + 8))
  while (( $(date +%s) < deadline )); do
    if curl --silent --fail --max-time 2 \
      "http://127.0.0.1:${PUBLIC_HTTP_PORT}/api/v1/health/live" >/dev/null 2>&1; then
      return 0
    fi
    sleep 0.3
  done
  die "Public HTTP :${PUBLIC_HTTP_PORT} did not proxy /api/v1/health/live; see ${LOG_DIR}/public-http.log"
}

start_tunnel() {
  local stable_host
  stable_host="$(stable_host_from_environment)"
  [[ -z "${stable_host}" ]] \
    || die "Stable HTTPS is configured at https://${stable_host}; the rotating Quick Tunnel is disabled."
  if [[ ! -x "${CLOUDFLARED_BIN}" ]]; then
    if command -v cloudflared >/dev/null 2>&1; then
      CLOUDFLARED_BIN="$(command -v cloudflared)"
    else
      die "cloudflared not found"
    fi
  fi
  stop_pidfile tunnel
  rm -f "${RUNTIME_DIR}/tunnel.url"
  # HTTP/2: Safari (especially iPhone) prefers HTTP/3/QUIC and otherwise
  # shows “cannot connect to the server” when the Quick Tunnel QUIC path drops.
  nohup "${CLOUDFLARED_BIN}" tunnel --protocol http2 --url "http://127.0.0.1:${PROXY_PORT}" \
    >"${LOG_DIR}/tunnel.log" 2>&1 &
  echo $! >"${PID_DIR}/tunnel.pid"

  local deadline=$(($(date +%s) + 45))
  local url=""
  while (( $(date +%s) < deadline )); do
    url="$(grep -oE 'https://[a-zA-Z0-9-]+\.trycloudflare\.com' "${LOG_DIR}/tunnel.log" | head -1 || true)"
    if [[ -n "${url}" ]]; then
      printf '%s\n' "${url}" >"${RUNTIME_DIR}/tunnel.url"
      if [[ "${GE_SOFT_LAUNCH_SERVE_PUBLIC_HTTP:-}" == "true" ]]; then
        printf 'HTTPS soft-launch URL: %s\n' "${url}"
        printf 'APP_URL left on the staff static-IP origin so Android login on :8080 keeps working.\n'
        return 0
      fi
      apply_public_origin "${url}"
      printf 'HTTPS soft-launch URL: %s\n' "${url}"
      printf 'Session/APP_URL updated for this tunnel; API/worker restarted.\n'
      return 0
    fi
    sleep 0.5
  done
  die "Cloudflare tunnel URL not detected; see ${LOG_DIR}/tunnel.log"
}

apply_public_origin() {
  local origin="$1"
  persist_public_origin "${origin}" ""
  source_soft_launch_env
  ensure_api_listen_port
  stop_pidfile api
  start_api
  start_worker
  start_public_http
  wait_health
}

persist_public_origin() {
  local origin="$1"
  local stable_host="${2:-}"
  python3 - "${ENV_FILE}" "${origin}" "${stable_host}" \
    "${PROXY_PORT}" "${PUBLIC_HTTP_PORT}" "${PUBLIC_HTTPS_PORT}" <<'PY'
import sys
from pathlib import Path
from urllib.parse import urlparse

path = Path(sys.argv[1])
origin = sys.argv[2]
stable_host = sys.argv[3]
proxy_port = sys.argv[4]
public_http_port = sys.argv[5]
public_https_port = sys.argv[6]
parsed = urlparse(origin)
host = parsed.netloc
if not host:
    raise SystemExit(f"APP_URL origin has no host: {origin}")
text = path.read_text(encoding="utf-8") if path.exists() else ""
overrides = {
    "APP_URL": origin,
    "SESSION_SECURE_COOKIE": "false",
    "SESSION_COOKIE": "global_english_session",
    "SESSION_SAME_SITE": "lax",
    "SANCTUM_STATEFUL_DOMAINS": f"{host},117.254.108.143:{public_http_port},127.0.0.1:{proxy_port},127.0.0.1:{public_http_port},localhost:{proxy_port},127.0.0.1:5173,localhost:5173,127.0.0.1:5174,localhost:5174,100.89.51.18:{public_http_port},real-studioz.taila69adf.ts.net:{public_http_port},real-studioz.taila69adf.ts.net",
}
if stable_host:
    overrides["SESSION_SECURE_COOKIE"] = "true"
    overrides["GE_SOFT_LAUNCH_STABLE_HOST"] = stable_host
    overrides["GE_SOFT_LAUNCH_PUBLIC_HTTPS_PORT"] = public_https_port
lines = []
seen = set()
for raw in text.splitlines():
    if not raw.strip() or raw.strip().startswith("#") or "=" not in raw:
        lines.append(raw)
        continue
    key = raw.split("=", 1)[0]
    if key in overrides:
        lines.append(f"{key}={overrides[key]}")
        seen.add(key)
    else:
        lines.append(raw)
for key, value in overrides.items():
    if key not in seen:
        lines.append(f"{key}={value}")
path.write_text("\n".join(lines) + "\n", encoding="utf-8")
path.chmod(0o600)
PY
}

configure_stable_host() {
  local stable_host="${REQUESTED_STABLE_HOST}"
  [[ -n "${stable_host}" ]] \
    || die "Set HOST, for example: make soft-launch-configure-stable HOST=ai.globalspokenenglish.com"
  validate_stable_host "${stable_host}"
  persist_public_origin "https://${stable_host}" "${stable_host}"
  printf 'Configured stable HTTPS origin: https://%s\n' "${stable_host}"
  printf 'Next: point public DNS to the static IP and map WAN 80/443 to this host:%s/%s, then run make soft-launch-restart.\n' \
    "${PUBLIC_HTTP_PORT}" "${PUBLIC_HTTPS_PORT}"
}

wait_health() {
  local deadline=$(($(date +%s) + 60))
  while (( $(date +%s) < deadline )); do
    if curl --silent --show-error --fail --max-time 2 \
      "http://127.0.0.1:${PROXY_PORT}/api/v1/health/live" >/dev/null 2>&1; then
      return 0
    fi
    sleep 0.5
  done
  die "Soft-launch proxy health check failed; see ${LOG_DIR}/api.log and ${LOG_DIR}/caddy.log"
}

status() {
  local stable_host
  stable_host="$(stable_host_from_environment)"
  for name in api worker scheduler caddy public-http tunnel; do
    if pid_alive "${PID_DIR}/${name}.pid"; then
      printf '%-12s running (pid %s)\n' "${name}" "$(cat "${PID_DIR}/${name}.pid")"
    else
      printf '%-12s stopped\n' "${name}"
    fi
  done
  if [[ -f "${RUNTIME_DIR}/tunnel.url" ]]; then
    printf 'tunnel url: %s\n' "$(cat "${RUNTIME_DIR}/tunnel.url")"
  fi
  if [[ -n "${stable_host}" ]]; then
    printf 'stable url: https://%s\n' "${stable_host}"
    printf 'edge mapping: WAN 80 -> LAN %s; WAN 443 -> LAN %s\n' \
      "${PUBLIC_HTTP_PORT}" "${PUBLIC_HTTPS_PORT}"
  fi
  if curl --silent --fail --max-time 2 "http://127.0.0.1:${PROXY_PORT}/api/v1/health/live" >/dev/null 2>&1; then
    printf 'health live: ok\n'
  else
    printf 'health live: down\n'
  fi
}

backup_db() {
  local stamp
  stamp="$(date -u +%Y%m%dT%H%M%SZ)"
  local out="${RUNTIME_DIR}/backups/soft-launch-${stamp}.sql.gz"
  (
    umask 077
    cd "${ROOT_DIR}"
    # shellcheck disable=SC1091
    set -a
    source .env
    set +a
    docker compose exec -T postgres pg_dump -U "${POSTGRES_USER}" "${POSTGRES_DB}" \
      | gzip -c >"${out}"
  )
  chmod 600 "${out}"
  gzip -t "${out}"
  bash "${ROOT_DIR}/scripts/soft-launch-encrypt-backup.sh" "${out}"
  printf 'Wrote %s\n' "${out}"
}

supervise() {
  local stopping=false
  local health_failures=0
  local name

  trap 'stopping=true' INT TERM

  "$0" start
  local stable_host
  stable_host="$(stable_host_from_environment)"
  if [[ -z "${stable_host}" ]]; then
    "$0" tunnel
  fi

  while [[ "${stopping}" == "false" ]]; do
    for name in api worker scheduler caddy public-http; do
      if ! pid_alive "${PID_DIR}/${name}.pid"; then
        die "Soft-launch supervisor detected stopped process: ${name}"
      fi
    done
    if [[ -z "${stable_host}" ]] && ! pid_alive "${PID_DIR}/tunnel.pid"; then
      die "Soft-launch supervisor detected stopped process: tunnel"
    fi

    if curl --silent --fail --max-time 2 \
      "http://127.0.0.1:${PROXY_PORT}/api/v1/health/live" >/dev/null 2>&1; then
      health_failures=0
    else
      health_failures=$((health_failures + 1))
      if (( health_failures >= 3 )); then
        die "Soft-launch supervisor detected three consecutive health failures"
      fi
    fi

    sleep 5 &
    wait $! || true
  done
}

case "${MODE}" in
  start)
    ensure_services
    source_soft_launch_env
    if [[ "${GE_SOFT_LAUNCH_SERVE_PUBLIC_HTTP:-}" == "true" \
      && -z "${GE_SOFT_LAUNCH_STABLE_HOST:-}" ]]; then
      persist_public_origin \
        "${GE_SOFT_LAUNCH_PUBLIC_ORIGIN:-http://117.254.108.143:8080}"
      source_soft_launch_env
    fi
    ensure_api_listen_port
    ensure_web_dist
    start_api
    start_worker
    start_scheduler
    start_proxy
    start_public_http
    wait_health
    status
    stable_host="$(stable_host_from_environment)"
    if [[ -n "${stable_host}" ]]; then
      printf '\nPrimary HTTPS entry: https://%s\n' "${stable_host}"
      printf 'Router mapping: WAN 80 -> this host:%s; WAN 443 -> this host:%s\n' \
        "${PUBLIC_HTTP_PORT}" "${PUBLIC_HTTPS_PORT}"
    else
      printf '\nStaff Android/API entry: http://117.254.108.143:%s\n' \
        "${PUBLIC_HTTP_PORT}"
      printf 'Primary HTTPS entry: make soft-launch-tunnel\n'
    fi
    ;;
  stop)
    stop_pidfile tunnel
    rm -f "${RUNTIME_DIR}/tunnel.url"
    stop_pidfile public-http
    stop_pidfile caddy
    stop_pidfile scheduler
    stop_pidfile worker
    stop_pidfile api
    status
    ;;
  restart)
    restart_tunnel=false
    stable_host="$(stable_host_from_environment)"
    if [[ -z "${stable_host}" ]] && pid_alive "${PID_DIR}/tunnel.pid"; then
      restart_tunnel=true
    fi
    "$0" stop
    "$0" start
    if [[ "${restart_tunnel}" == "true" ]]; then
      "$0" tunnel
    fi
    ;;
  tunnel)
    start_tunnel
    ;;
  tunnel-stop)
    stop_pidfile tunnel
    rm -f "${RUNTIME_DIR}/tunnel.url"
    ;;
  configure-stable-host)
    configure_stable_host
    ;;
  status)
    status
    ;;
  reload-public-http)
    source_soft_launch_env
    ensure_api_listen_port
    start_public_http
    printf 'public HTTP listener reloaded on :%s\n' "${PUBLIC_HTTP_PORT}"
    ;;
  reload-api)
    source_soft_launch_env
    ensure_api_listen_port
    stop_pidfile api
    start_api
    wait_health
    ;;
  refresh-web)
    ensure_web_dist
    printf 'web build: %s\n' "$(web_build_fingerprint)"
    ;;
  backup)
    backup_db
    ;;
  health)
    wait_health
    curl --silent --show-error --fail "http://127.0.0.1:${PROXY_PORT}/api/v1/health/live"
    printf '\n'
    curl --silent --show-error --fail "http://127.0.0.1:${PROXY_PORT}/api/v1/health/ready"
    printf '\n'
    ;;
  supervise)
    supervise
    ;;
  *)
    die "Usage: $0 {start|stop|restart|status|refresh-web|reload-public-http|reload-api|tunnel|tunnel-stop|configure-stable-host|health|backup|supervise}"
    ;;
esac

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

MODE="${1:-status}"

mkdir -p "${PID_DIR}" "${LOG_DIR}" "${RUNTIME_DIR}/env" "${RUNTIME_DIR}/backups"

die() {
  printf '%s\n' "$*" >&2
  exit 1
}

require_php() {
  if [[ ! -x "${PHP_BIN}" ]]; then
    PHP_BIN="$(command -v php || true)"
  fi
  [[ -x "${PHP_BIN}" ]] || die "PHP binary not found. Set GE_SOFT_LAUNCH_PHP."
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

ensure_services() {
  (cd "${ROOT_DIR}" && docker compose up -d --wait postgres redis object-storage mail otp-stub)
}

ensure_web_dist() {
  if [[ ! -f "${WEB_DIST}/index.html" ]]; then
    require_php
    if [[ -x "${PNPM_BIN}" ]]; then
      (cd "${ROOT_DIR}" && PATH="$(dirname "${NODE_BIN}"):${PATH}" "${PNPM_BIN}" --filter @global-english/web build)
    else
      (cd "${ROOT_DIR}" && pnpm --filter @global-english/web build)
    fi
  fi
  [[ -f "${WEB_DIST}/index.html" ]] || die "Web dist missing at ${WEB_DIST}"
}

source_soft_launch_env() {
  # shellcheck disable=SC1090
  source "${ENVIRONMENT_LOADER}"
  GE_SOFT_LAUNCH_ENV="${ENV_FILE}" load_soft_launch_environment
}

start_api() {
  require_php
  [[ -f "${API_PUBLIC}/index.php" ]] || die "Laravel public entrypoint is missing."
  [[ -f "${API_ROUTER}" ]] || die "Laravel development-server router is missing."
  stop_pidfile api
  (
    cd "${API_PUBLIC}"
    source_soft_launch_env
    nohup "${PHP_BIN}" -S "127.0.0.1:${API_PORT}" "${API_ROUTER}" \
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
  export GE_SOFT_LAUNCH_WEB_ROOT="${WEB_DIST}"
  nohup "${CADDY_BIN}" run --config "${CADDYFILE}" --adapter caddyfile \
    >"${LOG_DIR}/caddy.log" 2>&1 &
  echo $! >"${PID_DIR}/caddy.pid"
}

start_public_http() {
  # Static-IP entry redirects authenticated use to the current HTTPS origin.
  # Direct HTTP serving remains available only before an HTTPS origin exists.
  require_caddy
  stop_pidfile public-http
  ensure_web_dist
  source_soft_launch_env
  # shellcheck disable=SC1090
  source "${PUBLIC_HTTP_CONFIGURATOR}"
  write_soft_launch_public_http_config \
    "${RUNTIME_DIR}/env/Caddyfile.public" \
    "${PUBLIC_HTTP_PORT}" \
    "${API_PORT}" \
    "${WEB_DIST}" \
    "${APP_URL:-}"
  nohup "${CADDY_BIN}" run --config "${RUNTIME_DIR}/env/Caddyfile.public" --adapter caddyfile \
    >"${LOG_DIR}/public-http.log" 2>&1 &
  echo $! >"${PID_DIR}/public-http.pid"
}

start_tunnel() {
  if [[ ! -x "${CLOUDFLARED_BIN}" ]]; then
    if command -v cloudflared >/dev/null 2>&1; then
      CLOUDFLARED_BIN="$(command -v cloudflared)"
    else
      die "cloudflared not found"
    fi
  fi
  stop_pidfile tunnel
  rm -f "${RUNTIME_DIR}/tunnel.url"
  nohup "${CLOUDFLARED_BIN}" tunnel --url "http://127.0.0.1:${PROXY_PORT}" \
    >"${LOG_DIR}/tunnel.log" 2>&1 &
  echo $! >"${PID_DIR}/tunnel.pid"

  local deadline=$(($(date +%s) + 45))
  local url=""
  while (( $(date +%s) < deadline )); do
    url="$(grep -oE 'https://[a-zA-Z0-9-]+\.trycloudflare\.com' "${LOG_DIR}/tunnel.log" | head -1 || true)"
    if [[ -n "${url}" ]]; then
      printf '%s\n' "${url}" >"${RUNTIME_DIR}/tunnel.url"
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
  local host
  host="$(printf '%s' "${origin}" | sed -E 's#https://##' | sed -E 's#/.*##')"
  python3 - <<PY
from pathlib import Path
path = Path("${ENV_FILE}")
text = path.read_text(encoding="utf-8") if path.exists() else ""
overrides = {
    "APP_URL": "${origin}",
    "SESSION_SECURE_COOKIE": "true",
    "SESSION_SAME_SITE": "lax",
    "SANCTUM_STATEFUL_DOMAINS": "${host},127.0.0.1:${PROXY_PORT},127.0.0.1:${PUBLIC_HTTP_PORT},localhost:${PROXY_PORT}",
}
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
  start_api
  start_worker
  start_public_http
  wait_health
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
    cd "${ROOT_DIR}"
    # shellcheck disable=SC1091
    set -a
    source .env
    set +a
    docker compose exec -T postgres pg_dump -U "${POSTGRES_USER}" "${POSTGRES_DB}" \
      | gzip -c >"${out}"
  )
  printf 'Wrote %s\n' "${out}"
}

case "${MODE}" in
  start)
    ensure_services
    source_soft_launch_env
    ensure_web_dist
    start_api
    start_worker
    start_scheduler
    start_proxy
    start_public_http
    wait_health
    status
    printf '\nPrimary HTTPS entry: make soft-launch-tunnel\n'
    printf 'Static-IP entry: http://127.0.0.1:%s (redirects to HTTPS when configured)\n' \
      "${PUBLIC_HTTP_PORT}"
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
    if pid_alive "${PID_DIR}/tunnel.pid"; then
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
  status)
    status
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
  *)
    die "Usage: $0 {start|stop|restart|status|tunnel|tunnel-stop|health|backup}"
    ;;
esac

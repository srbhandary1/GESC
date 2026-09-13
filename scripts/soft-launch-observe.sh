#!/usr/bin/env bash
# Wave 4 local observe: API down, queue stuck, provider 5xx.
# Writes counts only. Never copies log lines, transcripts, or secrets.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RUNTIME="${GE_SOFT_LAUNCH_RUNTIME:-${HOME}/ge-soft-launch}"
PID_DIR="${RUNTIME}/run"
ALERT_DIR="${RUNTIME}/alerts"
LOG_DIR="${RUNTIME}/logs"
PROXY_PORT="${GE_SOFT_LAUNCH_PROXY_PORT:-9080}"
API_LOG="${GE_SOFT_LAUNCH_LARAVEL_LOG:-${ROOT_DIR}/apps/api/storage/logs/laravel.log}"
HTTP_5XX_THRESHOLD="${GE_SOFT_LAUNCH_HTTP_5XX_THRESHOLD:-3}"
PROVIDER_FAIL_THRESHOLD="${GE_SOFT_LAUNCH_PROVIDER_FAIL_THRESHOLD:-3}"
QUEUE_BACKLOG_THRESHOLD="${GE_SOFT_LAUNCH_QUEUE_BACKLOG_THRESHOLD:-25}"
WINDOW_MINUTES="${GE_SOFT_LAUNCH_OBSERVE_WINDOW_MINUTES:-5}"
COMPOSE_VALUE="${DOCKER_COMPOSE:-docker compose}"

# shellcheck disable=SC1091
source "${ROOT_DIR}/scripts/lib/soft-launch-observe-signals.sh"

mkdir -p "${ALERT_DIR}"
chmod 700 "${ALERT_DIR}"

signals=()
api_fail_streak=0
queue_llen=0
worker_state=down
scheduler_state=down
redis_state=down

if pidfile_alive "${PID_DIR}/worker.pid"; then
  worker_state=up
fi
if pidfile_alive "${PID_DIR}/scheduler.pid"; then
  scheduler_state=up
fi

if curl --silent --fail --max-time 3 \
  "http://127.0.0.1:${PROXY_PORT}/api/v1/health/live" >/dev/null 2>&1; then
  api_fail_streak=0
else
  prev=0
  if [[ -f "${ALERT_DIR}/api-fail-streak" ]]; then
    prev="$(cat "${ALERT_DIR}/api-fail-streak" || true)"
  fi
  if [[ "${prev}" =~ ^[0-9]+$ ]]; then
    api_fail_streak=$((prev + 1))
  else
    api_fail_streak=1
  fi
  if (( api_fail_streak >= 2 )); then
    signals+=("api_down")
  fi
fi
printf '%s\n' "${api_fail_streak}" >"${ALERT_DIR}/api-fail-streak"
chmod 600 "${ALERT_DIR}/api-fail-streak"

if [[ "${worker_state}" != up || "${scheduler_state}" != up ]]; then
  signals+=("queue_stuck")
fi

read -r -a COMPOSE_COMMAND <<<"${COMPOSE_VALUE}"
if "${COMPOSE_COMMAND[@]}" exec -T redis redis-cli PING >/dev/null 2>&1; then
  redis_state=up
  while IFS= read -r key; do
    [[ -z "${key}" ]] && continue
    len="$("${COMPOSE_COMMAND[@]}" exec -T redis redis-cli LLEN "${key}" 2>/dev/null || printf '0')"
    if [[ "${len}" =~ ^[0-9]+$ ]] && (( len > queue_llen )); then
      queue_llen="${len}"
    fi
  done < <("${COMPOSE_COMMAND[@]}" exec -T redis redis-cli --scan --pattern '*queues*' 2>/dev/null || true)
  if (( queue_llen >= QUEUE_BACKLOG_THRESHOLD )); then
    signals+=("queue_stuck")
  fi
else
  signals+=("queue_stuck")
fi

cutoff="$(date -u -d "${WINDOW_MINUTES} minutes ago" +'%Y-%m-%d %H:%M:%S')"
count_recent_log_signals "${API_LOG}" "${cutoff}"
if (( SOFT_LAUNCH_HTTP_5XX >= HTTP_5XX_THRESHOLD \
  || SOFT_LAUNCH_PROVIDER_FAILURES >= PROVIDER_FAIL_THRESHOLD )); then
  signals+=("provider_5xx")
fi

# Deduplicate
if (( ${#signals[@]} > 0 )); then
  mapfile -t signals < <(printf '%s\n' "${signals[@]}" | sort -u)
fi

ts="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
signal_json="[]"
if (( ${#signals[@]} > 0 )); then
  signal_json="$(printf '%s\n' "${signals[@]}" | awk 'BEGIN{printf "["} {printf "%s\"%s\"", (NR==1?"":","), $0} END{printf "]"}')"
fi

line="$(printf '{"ts":"%s","signals":%s,"api_fail_streak":%s,"worker":"%s","scheduler":"%s","redis":"%s","queue_llen":%s,"http_5xx":%s,"health_5xx":%s,"provider_failures":%s}\n' \
  "${ts}" "${signal_json}" "${api_fail_streak}" "${worker_state}" "${scheduler_state}" \
  "${redis_state}" "${queue_llen}" "${SOFT_LAUNCH_HTTP_5XX}" "${SOFT_LAUNCH_HEALTH_5XX}" \
  "${SOFT_LAUNCH_PROVIDER_FAILURES}")"
printf '%s' "${line}" >>"${ALERT_DIR}/observe.jsonl"
chmod 600 "${ALERT_DIR}/observe.jsonl"

summary="ge-soft-launch observe ${ts} signals=${signal_json} worker=${worker_state} scheduler=${scheduler_state} redis=${redis_state} queue_llen=${queue_llen} http_5xx=${SOFT_LAUNCH_HTTP_5XX} provider_failures=${SOFT_LAUNCH_PROVIDER_FAILURES}"
systemd-cat -t ge-soft-launch-observe --priority=info <<<"${summary}" 2>/dev/null || true

if (( ${#signals[@]} > 0 )); then
  systemd-cat -t ge-soft-launch-observe --priority=err <<<"${summary}" 2>/dev/null || true
  if [[ -n "${GE_SOFT_LAUNCH_ALERT_MAILTO:-}" ]] && command -v mail >/dev/null 2>&1; then
    printf '%s\n' "${summary}" | mail -s "ge-soft-launch observe ${signal_json}" \
      "${GE_SOFT_LAUNCH_ALERT_MAILTO}" || true
  fi
  if [[ -n "${GE_SOFT_LAUNCH_ALERT_COMMAND:-}" ]]; then
    # Command receives the content-free summary only.
    "${GE_SOFT_LAUNCH_ALERT_COMMAND}" "${summary}" || true
  fi
  printf '%s\n' "${summary}"
  exit 1
fi

printf '%s\n' "${summary}"
exit 0

# Content-free log counters for soft-launch observe.
# Never print matching log lines (they may sit next to other fields).

pidfile_alive() {
  local pid_file="$1"
  [[ -f "${pid_file}" ]] || return 1
  local pid
  pid="$(cat "${pid_file}")"
  [[ "${pid}" =~ ^[1-9][0-9]*$ ]] || return 1
  kill -0 "${pid}" 2>/dev/null
}

count_recent_log_signals() {
  local log_file="$1"
  local cutoff="$2"
  SOFT_LAUNCH_HTTP_5XX=0
  SOFT_LAUNCH_HEALTH_5XX=0
  SOFT_LAUNCH_PROVIDER_FAILURES=0
  [[ -f "${log_file}" ]] || return 0

  local counts
  counts="$(
    tail -c 8388608 "${log_file}" | awk -v cutoff="${cutoff}" '
      BEGIN { http5=0; health5=0; prov=0 }
      {
        if ($0 !~ /^\[[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\]/) next
        ts = substr($0, 2, 19)
        if (ts < cutoff) next
        if ($0 ~ /provider_http_failure|provider_connection_failure|provider_transport_failure/) {
          prov++
        }
        if ($0 ~ /http\.request_completed/ && $0 ~ /"status_code":5[0-9][0-9]/) {
          if ($0 ~ /health\.live/ || $0 ~ /health\.ready/) {
            health5++
          } else {
            http5++
          }
        }
      }
      END { printf "%d %d %d", http5, health5, prov }
    '
  )"
  # shellcheck disable=SC2086
  set -- ${counts}
  SOFT_LAUNCH_HTTP_5XX="${1:-0}"
  SOFT_LAUNCH_HEALTH_5XX="${2:-0}"
  SOFT_LAUNCH_PROVIDER_FAILURES="${3:-0}"
}

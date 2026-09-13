#!/usr/bin/env bash
# Fixture tests for Wave 4 observe counters and backup wrapping.
set -euo pipefail

root_dir="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
test_root="$(mktemp -d "${TMPDIR:-/tmp}/ge-soft-launch-observe.XXXXXX")"
trap 'rm -rf -- "$test_root"' EXIT HUP INT TERM

# shellcheck disable=SC1091
source "${root_dir}/scripts/lib/soft-launch-observe-signals.sh"
# shellcheck disable=SC1091
source "${root_dir}/scripts/lib/soft-launch-backup-crypto.sh"

pid_file="${test_root}/worker.pid"
echo $$ >"${pid_file}"
pidfile_alive "${pid_file}"
echo 999999 >"${pid_file}"
if pidfile_alive "${pid_file}"; then
  printf 'expected dead pidfile to fail\n' >&2
  exit 1
fi

fixture="${test_root}/laravel.log"
cat >"${fixture}" <<'EOF'
[2026-09-01 12:00:00] soft-launch.INFO: http.request_completed {"http_method":"GET","route_name":"api.v1.health.live","status_code":200,"duration_ms":1}
[2026-09-01 12:00:01] soft-launch.WARNING: http.request_completed {"http_method":"POST","route_name":"api.v1.conversation.turns.store","status_code":500,"duration_ms":12}
[2026-09-01 12:00:02] soft-launch.WARNING: recorded speech failed provider_http_failure
[2026-09-01 12:00:03] soft-launch.WARNING: http.request_completed {"http_method":"GET","route_name":"api.v1.health.live","status_code":500,"duration_ms":2}
[2026-09-01 11:00:00] soft-launch.WARNING: http.request_completed {"http_method":"POST","route_name":"api.v1.conversation.turns.store","status_code":500,"duration_ms":9}
EOF

count_recent_log_signals "${fixture}" "2026-09-01 11:59:00"
test "${SOFT_LAUNCH_HTTP_5XX}" = 1
test "${SOFT_LAUNCH_HEALTH_5XX}" = 1
test "${SOFT_LAUNCH_PROVIDER_FAILURES}" = 1

key_file="${test_root}/backup.pass"
payload="${test_root}/soft-launch-20260901T120000Z.sql.gz"
enc="${test_root}/dump.enc"
printf 'synthetic-dump\n' | gzip -c >"${payload}"
ensure_backup_passphrase "${key_file}"
encrypt_gzip_dump "${payload}" "${enc}" "${key_file}"
verify_encrypted_gzip "${enc}" "${key_file}"
test "$(stat -c '%a' "${key_file}")" = 600
test "$(stat -c '%a' "${enc}")" = 600

printf 'x' > "${test_root}/not-a-dump.sql.gz"
if bash "${root_dir}/scripts/soft-launch-encrypt-backup.sh" "${test_root}/not-a-dump.sql.gz" \
  >/dev/null 2>"${test_root}/encrypt.err"; then
  printf 'encrypt accepted a non-stamped dump name\n' >&2
  exit 1
fi

printf '%s\n' 'soft-launch observe fixture tests passed'

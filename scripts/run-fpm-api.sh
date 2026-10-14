#!/usr/bin/env bash
# Bounded PHP-FPM runtime for the existing loopback HTTP proxy.
set -euo pipefail
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
source "${root}/scripts/lib/soft-launch-environment.sh"
load_soft_launch_environment
api_port="${GE_SOFT_LAUNCH_API_PORT:-8000}"
fpm_port="${GE_SOFT_LAUNCH_FPM_PORT:-19001}"
workers="${GE_SOFT_LAUNCH_FPM_WORKERS:-8}"
image="${GE_SOFT_LAUNCH_FPM_IMAGE:-global-english-api-fpm:8.4.23}"
caddy="${GE_SOFT_LAUNCH_CADDY:-${HOME}/.local/bin/caddy}"
for number in "${api_port}" "${fpm_port}" "${workers}"; do
  [[ "${number}" =~ ^[1-9][0-9]*$ ]] || exit 2
done
(( workers >= 4 && workers <= 32 && api_port < 65536 && fpm_port < 65536 && api_port != fpm_port )) || exit 2
[[ -x "${caddy}" ]] || { echo 'Set GE_SOFT_LAUNCH_CADDY to the Caddy binary.' >&2; exit 2; }
docker image inspect "${image}" >/dev/null
umask 077
runtime="$(mktemp -d "${TMPDIR:-/tmp}/gse-api-fpm.XXXXXX")"
container="gse-api-fpm-${api_port}"
proxy_pid=''
cleanup() {
  [[ -z "${proxy_pid}" ]] || kill "${proxy_pid}" 2>/dev/null || true
  docker stop --time 10 "${container}" >/dev/null 2>&1 || true
  rm -rf "${runtime}"
}
trap cleanup EXIT
trap 'exit 0' TERM INT
# Resolve release storage symlinks before entering the read-only application mount.
storage="$(realpath "${LARAVEL_STORAGE_PATH:-${root}/apps/api/storage}")"
[[ -d "${storage}" ]] || { echo 'Laravel storage directory does not exist.' >&2; exit 2; }
export LARAVEL_STORAGE_PATH="${storage}"
# Docker receives values through a private file, never shell arguments or logs.
python3 - "${runtime}/environment" <<'PY'
import os,sys
with open(sys.argv[1], 'w') as stream:
    for key,value in os.environ.items():
        if key.replace('_', '').isalnum() and key[0].isalpha():
            if '\n' in value or '\r' in value:
                raise SystemExit('FPM environment values must be single-line.')
            stream.write(f'{key}={value}\n')
PY
cat >"${runtime}/pool.conf" <<EOF
[global]
daemonize = no
error_log = /proc/self/fd/2
[app]
user = www-data
group = www-data
listen = 127.0.0.1:${fpm_port}
listen.allowed_clients = 127.0.0.1
listen.backlog = 128
pm = dynamic
pm.max_children = ${workers}
pm.start_servers = 2
pm.min_spare_servers = 2
pm.max_spare_servers = 4
pm.max_requests = 500
request_terminate_timeout = 60s
clear_env = no
catch_workers_output = yes
security.limit_extensions = .php
php_admin_value[memory_limit] = 192M
php_admin_flag[display_errors] = off
EOF
cat >"${runtime}/Caddyfile" <<EOF
{
  admin off
  auto_https off
}
http://:${api_port} {
  bind 127.0.0.1
  root * ${root}/apps/api/public
  rewrite * /index.php
  php_fastcgi 127.0.0.1:${fpm_port}
}
EOF
mounts=(--volume "${root}:${root}:ro" --volume "${runtime}:${runtime}:ro")
mounts+=(--volume "${storage}:${storage}:rw")
# Exact additional directories only: protected provider files and private recovery
# paths differ between hosts. No blanket home-directory or host-root mounts.
IFS=: read -r -a secret_roots <<< "${GE_SOFT_LAUNCH_FPM_SECRET_ROOTS:-}"
IFS=: read -r -a writable_roots <<< "${GE_SOFT_LAUNCH_FPM_WRITABLE_ROOTS:-}"
for path in "${secret_roots[@]}"; do
  [[ -z "${path}" ]] && continue
  [[ "${path}" == /* && "${path}" != / && -d "${path}" ]] || exit 2
  mounts+=(--volume "${path}:${path}:ro")
done
for path in "${writable_roots[@]}"; do
  [[ -z "${path}" ]] && continue
  [[ "${path}" == /* && "${path}" != / && -d "${path}" ]] || exit 2
  mounts+=(--volume "${path}:${path}:rw")
done
"${caddy}" run --config "${runtime}/Caddyfile" --adapter caddyfile &
proxy_pid=$!
docker run --rm --name "${container}" --network host --read-only \
  --cap-drop ALL --security-opt no-new-privileges --user "$(id -u):$(id -g)" \
  --tmpfs /tmp:rw,nosuid,nodev,noexec,size=64m --env-file "${runtime}/environment" \
  "${mounts[@]}" --workdir "${root}/apps/api" "${image}" \
  php-fpm --nodaemonize --fpm-config "${runtime}/pool.conf" &
fpm_pid=$!
wait -n "${proxy_pid}" "${fpm_pid}"

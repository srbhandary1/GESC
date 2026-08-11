#!/usr/bin/env bash
# Installs the APP-0053 user service without embedding secrets in systemd.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
UNIT_NAME="global-english-soft-launch.service"
TEMPLATE="${ROOT_DIR}/infra/soft-launch/${UNIT_NAME}.in"
USER_UNIT_DIR="${HOME}/.config/systemd/user"
TARGET="${USER_UNIT_DIR}/${UNIT_NAME}"
TEMP_UNIT="$(mktemp)"

cleanup() {
  rm -f "${TEMP_UNIT}"
}
trap cleanup EXIT

[[ -f "${TEMPLATE}" ]] || {
  printf 'Unit template missing: %s\n' "${TEMPLATE}" >&2
  exit 1
}

mkdir -p "${USER_UNIT_DIR}"
escaped_root="$(printf '%s' "${ROOT_DIR}" | sed 's/[&|]/\\&/g')"
sed "s|@PROJECT_ROOT@|${escaped_root}|g" "${TEMPLATE}" >"${TEMP_UNIT}"

# A transient unit with this name may be running from an earlier manual boot.
# Its original KillMode allowed daemon children to survive a plain stop, so use
# the repository shutdown path and clear that exact cgroup before replacement.
if [[ "$(systemctl --user show "${UNIT_NAME}" -p Transient --value 2>/dev/null || true)" == "yes" ]]; then
  bash "${ROOT_DIR}/scripts/run-soft-launch.sh" stop
  systemctl --user kill --kill-whom=all "${UNIT_NAME}" 2>/dev/null || true
fi
systemctl --user stop "${UNIT_NAME}" 2>/dev/null || true
systemctl --user reset-failed "${UNIT_NAME}" 2>/dev/null || true
install -m 0644 "${TEMP_UNIT}" "${TARGET}"
systemctl --user daemon-reload
systemctl --user enable "${UNIT_NAME}"

if loginctl enable-linger "${USER}" >/dev/null 2>&1; then
  printf 'Enabled user lingering for reboot startup.\n'
else
  printf '%s\n' \
    'User lingering could not be enabled automatically.' \
    "An administrator must run: sudo loginctl enable-linger ${USER}" >&2
fi

if [[ "${1:-}" == "--start" ]]; then
  systemctl --user start "${UNIT_NAME}"
fi

printf 'Installed %s\n' "${TARGET}"
systemctl --user is-enabled "${UNIT_NAME}"

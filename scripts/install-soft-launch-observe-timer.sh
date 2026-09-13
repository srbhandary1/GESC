#!/usr/bin/env bash
# Install the Wave 4 observe timer without restarting the main soft-launch unit.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
USER_UNIT_DIR="${HOME}/.config/systemd/user"
SERVICE_IN="${ROOT_DIR}/infra/soft-launch/global-english-soft-launch-observe.service.in"
TIMER_IN="${ROOT_DIR}/infra/soft-launch/global-english-soft-launch-observe.timer.in"
SERVICE_NAME="global-english-soft-launch-observe.service"
TIMER_NAME="global-english-soft-launch-observe.timer"

[[ -f "${SERVICE_IN}" && -f "${TIMER_IN}" ]] || {
  printf 'Observe unit templates missing.\n' >&2
  exit 1
}

mkdir -p "${USER_UNIT_DIR}"
escaped_root="$(printf '%s' "${ROOT_DIR}" | sed 's/[&|]/\\&/g')"
sed "s|@PROJECT_ROOT@|${escaped_root}|g" "${SERVICE_IN}" \
  >"${USER_UNIT_DIR}/${SERVICE_NAME}"
install -m 0644 "${TIMER_IN}" "${USER_UNIT_DIR}/${TIMER_NAME}"
chmod 0644 "${USER_UNIT_DIR}/${SERVICE_NAME}" "${USER_UNIT_DIR}/${TIMER_NAME}"

systemctl --user daemon-reload
systemctl --user enable --now "${TIMER_NAME}"
systemctl --user start "${SERVICE_NAME}" || true

printf 'Installed %s and %s\n' "${SERVICE_NAME}" "${TIMER_NAME}"
systemctl --user is-enabled "${TIMER_NAME}"
systemctl --user is-active "${TIMER_NAME}"

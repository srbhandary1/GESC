#!/usr/bin/env bash
# Encrypt a mode-0600 gzip dump and copy the ciphertext off the live backup dir.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# shellcheck disable=SC1091
source "${ROOT_DIR}/scripts/lib/soft-launch-backup-crypto.sh"

DUMP="${1:-}"
[[ -n "${DUMP}" && -f "${DUMP}" ]] || {
  printf 'Usage: %s /path/to/soft-launch-YYYYMMDDTHHMMSSZ.sql.gz\n' "$0" >&2
  exit 2
}

base="$(basename "${DUMP}")"
[[ "${base}" =~ ^soft-launch-[0-9]{8}T[0-9]{6}Z\.sql\.gz$ ]] || {
  printf 'Refusing to encrypt a file that is not a stamped soft-launch gzip dump.\n' >&2
  exit 2
}

gzip -t "${DUMP}"
chmod 600 "${DUMP}"

RUNTIME="${GE_SOFT_LAUNCH_RUNTIME:-${HOME}/ge-soft-launch}"
KEY_FILE="${GE_SOFT_LAUNCH_BACKUP_PASS_FILE:-${RUNTIME}/secrets/backup.pass}"
OFFSITE_DIR="${GE_SOFT_LAUNCH_OFFSITE_BACKUP_DIR:-${HOME}/ge-offsite-backups}"

ensure_backup_passphrase "${KEY_FILE}"
enc_local="${DUMP}.enc"
encrypt_gzip_dump "${DUMP}" "${enc_local}" "${KEY_FILE}"
verify_encrypted_gzip "${enc_local}" "${KEY_FILE}"

mkdir -p "${OFFSITE_DIR}"
chmod 700 "${OFFSITE_DIR}"
enc_offsite="${OFFSITE_DIR}/${base}.enc"
umask 077
cp -f "${enc_local}" "${enc_offsite}"
chmod 600 "${enc_offsite}"
verify_encrypted_gzip "${enc_offsite}" "${KEY_FILE}"

local_bytes="$(wc -c <"${DUMP}" | tr -d ' ')"
enc_bytes="$(wc -c <"${enc_offsite}" | tr -d ' ')"
enc_hash="$(sha256sum "${enc_offsite}" | awk '{print $1}')"
printf 'Encrypted dump %s bytes; offsite ciphertext %s bytes sha256=%s\n' \
  "${local_bytes}" "${enc_bytes}" "${enc_hash}"
printf 'Offsite ciphertext: %s\n' "${enc_offsite}"
printf 'Passphrase file remains mode 0600 outside git (path not printed).\n'

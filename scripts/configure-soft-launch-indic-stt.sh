#!/usr/bin/env bash
# Configure the outside-repository soft-launch environment for the fail-closed
# Indic recorded-STT adapter. Secret contents are never read or printed here.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RUNTIME_DIR="${GE_SOFT_LAUNCH_RUNTIME:-${HOME}/ge-soft-launch}"
ENV_FILE="${GE_SOFT_LAUNCH_ENV:-${RUNTIME_DIR}/env/soft-launch.env}"
SECRET_FILE="${1:-${RUNTIME_DIR}/secrets/sarvam.secret}"

die() {
  printf '%s\n' "$*" >&2
  exit 1
}

[[ -f "${ENV_FILE}" ]] || die "Missing soft-launch environment: ${ENV_FILE}"
[[ "${SECRET_FILE}" = /* ]] || die "Sarvam secret path must be absolute."
[[ ! -L "${SECRET_FILE}" ]] || die "Sarvam secret must not be a symlink."
[[ -f "${SECRET_FILE}" && -s "${SECRET_FILE}" ]] \
  || die "Sarvam secret must be a non-empty regular file."
[[ "$(stat -c '%a' "${SECRET_FILE}")" == "600" ]] \
  || die "Sarvam secret must have mode 0600."
[[ "$(stat -c '%u' "${SECRET_FILE}")" == "$(id -u)" ]] \
  || die "Sarvam secret must be owned by the current user."

resolved_secret="$(realpath "${SECRET_FILE}")"
case "${resolved_secret}/" in
  "${ROOT_DIR}/"*) die "Sarvam secret must live outside the repository." ;;
esac

python3 - "${ENV_FILE}" "${resolved_secret}" <<'PY'
import os
import stat
import sys
import tempfile
from pathlib import Path

env_path = Path(sys.argv[1])
secret_path = sys.argv[2]
updates = {
    "VOICE_UPLOAD_ENABLED": "true",
    "VOICE_PROVIDER_DRIVER": "indic",
    "VOICE_INDIC_TRANSCRIBE_URL": "https://api.sarvam.ai/speech-to-text",
    "VOICE_INDIC_ALLOWED_HOST": "api.sarvam.ai",
    "VOICE_INDIC_SECRET_FILE": secret_path,
    "VOICE_INDIC_AUTH_HEADER": "api-subscription-key",
    "VOICE_INDIC_MODEL": "saaras:v3",
    "VOICE_INDIC_MODE": "translate",
    "VOICE_INDIC_LANGUAGE_CODE": "unknown",
    "VOICE_INDIC_OUTPUT_LANGUAGE": "en-IN",
}

lines = env_path.read_text(encoding="utf-8").splitlines()
seen = set()
output = []
allowlist = ""

for line in lines:
    if "=" not in line or line.lstrip().startswith("#"):
        output.append(line)
        continue

    key, value = line.split("=", 1)
    if key == "VOICE_ALLOWED_ORGANIZATION_IDS":
        allowlist = value.strip()
    if key in updates:
        if key not in seen:
            output.append(f"{key}={updates[key]}")
            seen.add(key)
        continue
    output.append(line)

if not allowlist:
    raise SystemExit("VOICE_ALLOWED_ORGANIZATION_IDS must be non-empty.")

for key, value in updates.items():
    if key not in seen:
        output.append(f"{key}={value}")

mode = stat.S_IMODE(env_path.stat().st_mode)
fd, temporary = tempfile.mkstemp(prefix=".soft-launch.", dir=env_path.parent)
try:
    with os.fdopen(fd, "w", encoding="utf-8") as stream:
        stream.write("\n".join(output) + "\n")
    os.chmod(temporary, mode)
    os.replace(temporary, env_path)
finally:
    if os.path.exists(temporary):
        os.unlink(temporary)
PY

printf '%s\n' "Soft-launch Indic STT configuration written without secret output."

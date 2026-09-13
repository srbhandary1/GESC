# AES-256-CBC wrapping for soft-launch Postgres dumps.
# Passphrase file stays outside git. Do not print the passphrase.

ensure_backup_passphrase() {
  local key_file="$1"
  local key_dir
  key_dir="$(dirname "${key_file}")"
  mkdir -p "${key_dir}"
  chmod 700 "${key_dir}"
  if [[ ! -f "${key_file}" ]]; then
    umask 077
    openssl rand -base64 32 >"${key_file}"
    chmod 600 "${key_file}"
  fi
  [[ -f "${key_file}" && "$(stat -c '%a' "${key_file}")" == "600" ]]
}

encrypt_gzip_dump() {
  local src="$1"
  local dest="$2"
  local key_file="$3"
  umask 077
  openssl enc -aes-256-cbc -pbkdf2 -iter 200000 -salt \
    -in "${src}" -out "${dest}" -pass "file:${key_file}"
  chmod 600 "${dest}"
}

verify_encrypted_gzip() {
  local enc="$1"
  local key_file="$2"
  openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 \
    -in "${enc}" -pass "file:${key_file}" | gzip -t
}

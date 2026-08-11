#!/usr/bin/env bash
# Install a local Caddy binary for soft-launch reverse proxy (no root required).

set -euo pipefail

DEST="${GE_SOFT_LAUNCH_CADDY:-${HOME}/.local/bin/caddy}"
VERSION="${GE_SOFT_LAUNCH_CADDY_VERSION:-2.10.2}"
ARCH="$(uname -m)"
case "${ARCH}" in
  x86_64|amd64) ARCH_LABEL="amd64" ;;
  aarch64|arm64) ARCH_LABEL="arm64" ;;
  *) echo "Unsupported architecture: ${ARCH}" >&2; exit 1 ;;
esac

mkdir -p "$(dirname "${DEST}")"
TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

URL="https://github.com/caddyserver/caddy/releases/download/v${VERSION}/caddy_${VERSION}_linux_${ARCH_LABEL}.tar.gz"
curl -fsSL "${URL}" -o "${TMP}/caddy.tgz"
tar -xzf "${TMP}/caddy.tgz" -C "${TMP}" caddy
install -m 0755 "${TMP}/caddy" "${DEST}"
"${DEST}" version
printf 'Installed %s\n' "${DEST}"

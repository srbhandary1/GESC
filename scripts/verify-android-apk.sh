#!/usr/bin/env bash
# Fail closed when an APK is missing, truncated, or not installable.
set -euo pipefail
APK="${1:-apps/web/public/downloads/global-spoken-english-android.apk}"
MIN_BYTES="${GE_ANDROID_APK_MIN_BYTES:-15000000}"
ANDROID_HOME="${ANDROID_HOME:-$HOME/Android/Sdk}"
BUILD_TOOLS="$(ls -d "${ANDROID_HOME}"/build-tools/*/ 2>/dev/null | sort -V | tail -1)"

if [[ ! -f "${APK}" ]]; then
  printf 'APK not found: %s\n' "${APK}" >&2
  exit 1
fi

size="$(wc -c <"${APK}" | tr -d ' ')"
if (( size < MIN_BYTES )); then
  printf 'APK too small (%s bytes). Likely HTML or a partial download.\n' "${size}" >&2
  exit 1
fi

if ! file "${APK}" | grep -q 'Android package (APK)'; then
  printf 'Not a valid APK container: %s\n' "$(file -b "${APK}")" >&2
  exit 1
fi

if ! unzip -t "${APK}" >/dev/null; then
  printf 'APK zip integrity check failed.\n' >&2
  exit 1
fi

if [[ -n "${BUILD_TOOLS}" && -x "${BUILD_TOOLS}/aapt" ]]; then
  "${BUILD_TOOLS}/aapt" dump badging "${APK}" | sed -n '1,3p'
  if [[ -x "${BUILD_TOOLS}/apksigner" ]]; then
    "${BUILD_TOOLS}/apksigner" verify --print-certs "${APK}" >/dev/null
  fi
fi

sha256sum "${APK}"
printf 'APK verification passed (%s bytes).\n' "${size}"

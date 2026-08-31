#!/usr/bin/env bash
# Build the invite-only production-path debug APK, publish it for homepage
# download, then restore the soft-launch web dist (no mobile API origin baked in).

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB_DIR="${ROOT_DIR}/apps/web"
RUNTIME_DIR="${GE_SOFT_LAUNCH_RUNTIME:-${HOME}/ge-soft-launch}"
ARTIFACT_DIR="${RUNTIME_DIR}/artifacts/android"
PUBLIC_DOWNLOADS="${WEB_DIR}/public/downloads"
APK_NAME="global-spoken-english.apk"
ORIGIN="${GE_SOFT_LAUNCH_ANDROID_API_ORIGIN:-http://117.254.108.143:8080}"
OAUTH_CLIENT_ID="${GE_SOFT_LAUNCH_ANDROID_OAUTH_CLIENT_ID:-019fdd3f-4bba-720b-9e4e-a771ecccdd7f}"
JAVA_HOME="${JAVA_HOME:-${HOME}/.local/jdk/jdk-21.0.12+8}"
ANDROID_HOME="${ANDROID_HOME:-${HOME}/Android/Sdk}"
export JAVA_HOME ANDROID_HOME ANDROID_SDK_ROOT="${ANDROID_HOME}"
export PATH="${JAVA_HOME}/bin:${ANDROID_HOME}/cmdline-tools/latest/bin:${ANDROID_HOME}/platform-tools:${PATH}"
# Gradle reads VITE_MOBILE_API_ORIGIN into BuildConfig.MOBILE_API_ORIGIN.
# The native Custom Tabs plugin rejects every authorize URL when that field
# is empty, which looks like Sign in buttons doing nothing (D-0262).
export VITE_MOBILE_API_ORIGIN="${ORIGIN}"
export VITE_MOBILE_OAUTH_CLIENT_ID="${OAUTH_CLIENT_ID}"

die() {
  printf '%s\n' "$*" >&2
  exit 1
}

[[ -x "${JAVA_HOME}/bin/java" ]] || die "JDK 21 is missing at ${JAVA_HOME}"
[[ -d "${ANDROID_HOME}" ]] || die "Android SDK is missing at ${ANDROID_HOME}"
[[ "${ORIGIN}" =~ ^http://([0-9]{1,3}\.){3}[0-9]{1,3}:8080$ ]] \
  || die "GE_SOFT_LAUNCH_ANDROID_API_ORIGIN must be http://IPv4:8080"
[[ "${OAUTH_CLIENT_ID}" =~ ^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$ ]] \
  || die "GE_SOFT_LAUNCH_ANDROID_OAUTH_CLIENT_ID must be a Passport client UUID"

restore_web_preview() {
  (
    cd "${ROOT_DIR}"
    env -u VITE_MOBILE_API_ORIGIN -u VITE_MOBILE_OAUTH_CLIENT_ID \
      -u VITE_MOBILE_OAUTH_REDIRECT_URI -u VITE_MOBILE_OAUTH_AUTHORIZE_URL \
      -u VITE_MOBILE_OAUTH_TOKEN_URL \
      VITE_UI_TRANSLATION_PREVIEW=true \
      pnpm --filter @global-english/web build
  )
  printf 'preview\n' >"${WEB_DIR}/dist/.soft-launch-ui-preview"
  mkdir -p "${WEB_DIR}/dist/downloads"
  if [[ -f "${PUBLIC_DOWNLOADS}/${APK_NAME}" ]]; then
    cp -f "${PUBLIC_DOWNLOADS}/${APK_NAME}" "${WEB_DIR}/dist/downloads/${APK_NAME}"
  fi
}

trap restore_web_preview EXIT

(
  cd "${WEB_DIR}"
  VITE_UI_TRANSLATION_PREVIEW=true pnpm run build
  CAPACITOR_APP=production pnpm exec cap sync android
)
(
  cd "${WEB_DIR}/android-app"
  ./gradlew --no-daemon testDebugUnitTest assembleDebug
)

src_apk="${WEB_DIR}/android-app/app/build/outputs/apk/debug/app-debug.apk"
[[ -f "${src_apk}" ]] || die "Debug APK was not produced"
mkdir -p "${ARTIFACT_DIR}" "${PUBLIC_DOWNLOADS}"
install -m 0644 "${src_apk}" "${ARTIFACT_DIR}/${APK_NAME}"
install -m 0644 "${src_apk}" "${PUBLIC_DOWNLOADS}/${APK_NAME}"
printf 'Published %s for origin %s client %s\n' "${APK_NAME}" "${ORIGIN}" "${OAUTH_CLIENT_ID}"

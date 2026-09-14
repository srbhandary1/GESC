#!/usr/bin/env bash
# Run on Real-Studioz only. Builds and publishes the closed-beta APK with
# jezweb-c3 fixes (Swathi naming, listen stop/slider, disabled hold-to-talk).
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
export PATH="${ROOT}/tmp/toolchain/node/bin:${PATH:-}"
export JAVA_HOME="${JAVA_HOME:-$HOME/.local/jdk/jdk-21.0.12+8}"
export ANDROID_HOME="${ANDROID_HOME:-$HOME/Android/Sdk}"
git fetch origin cursor/learner-variant-c-jezweb-3f88
git checkout cursor/learner-variant-c-jezweb-3f88
git pull origin cursor/learner-variant-c-jezweb-3f88
VITE_MOBILE_API_ORIGIN=http://117.254.108.143:8080 make android-closed-beta
cp apps/web/android-app/app/build/outputs/apk/debug/app-debug.apk \
  apps/web/public/downloads/global-spoken-english-android.apk
bash scripts/verify-android-apk.sh apps/web/public/downloads/global-spoken-english-android.apk
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$HOME/ge-soft-launch/artifacts/closed-group"
cp apps/web/public/downloads/global-spoken-english-android.apk \
  "$HOME/ge-soft-launch/artifacts/closed-group/global-spoken-english-jezweb-c6-${STAMP}.apk"
make soft-launch-refresh-web
make soft-launch-reload-public-http 2>/dev/null || make soft-launch-restart
echo "Published: http://117.254.108.143:8080/downloads/global-spoken-english-android.apk?v=jezweb-c6"
echo "Web + APK refreshed. Ask testers to hard-reload (Ctrl+Shift+R) once."
sha256sum apps/web/public/downloads/global-spoken-english-android.apk

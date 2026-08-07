#!/usr/bin/env bash
set -euo pipefail
# Boots DM01 API34 emulator headless and installs the closed-beta debug APK.

export ANDROID_HOME="${ANDROID_HOME:-$HOME/Android/Sdk}"
export ANDROID_SDK_ROOT="$ANDROID_HOME"
export JAVA_HOME="${JAVA_HOME:-$HOME/.local/jdk/jdk-21.0.12+8}"
export PATH="$JAVA_HOME/bin:$ANDROID_HOME/cmdline-tools/latest/bin:$ANDROID_HOME/platform-tools:$ANDROID_HOME/emulator:$PATH"

AVD_NAME="${AVD_NAME:-DM01_API34_Pixel7}"
APK="${APK:-$HOME/projects/global-spoken-english/apps/web/android-app/app/build/outputs/apk/debug/app-debug.apk}"

python3 - <<'PY'
import os
import sys

try:
    fd = os.open('/dev/kvm', os.O_RDWR)
    os.close(fd)
except OSError as error:
    print(f'ERROR: cannot open /dev/kvm ({error}).', file=sys.stderr)
    print('Run: bash scripts/setup-android-emulator-kvm.sh', file=sys.stderr)
    print('Then start a new login shell.', file=sys.stderr)
    sys.exit(1)

print('KVM access OK')
PY

adb start-server >/dev/null
if ! adb devices | awk 'NR>1 && $2=="device"{found=1} END{exit !found}'; then
  echo "Starting emulator $AVD_NAME ..."
  nohup emulator -avd "$AVD_NAME" \
    -no-window -no-audio -no-boot-anim \
    -gpu swiftshader_indirect \
    -accel on \
    -netdelay none -netspeed full \
    > /tmp/emulator-dm01.log 2>&1 &
  echo $! > /tmp/emulator-dm01.pid
fi

echo "Waiting for boot..."
adb wait-for-device
for _ in $(seq 1 120); do
  boot="$(adb shell getprop sys.boot_completed 2>/dev/null | tr -d '\r')"
  if [ "$boot" = "1" ]; then
    echo "Boot completed"
    break
  fi
  sleep 2
done

adb install -r "$APK"
adb shell pm list packages | grep -F com.globalenglish.app || true
adb devices -l
echo "APK installed. Emulator log: /tmp/emulator-dm01.log"

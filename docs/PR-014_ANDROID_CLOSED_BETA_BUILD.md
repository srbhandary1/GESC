# PR-014 reproducible closed-beta Android build

**Authority:** [APP-0030](APPROVALS.md#app-0030)  
**Production package:** `com.globalenglish.app` at `apps/web/android-app`  
**Demo package (non-evidence):** `com.globalenglish.demo` at `apps/web/android` (D-0078)

## Dual-tree rule

| Env / command | Capacitor tree | App ID | Web assets |
|---|---|---|---|
| default / `make demo-android` | `apps/web/android` | `com.globalenglish.demo` | `dist-demo` |
| `CAPACITOR_APP=production` / `make android-closed-beta` | `apps/web/android-app` | `com.globalenglish.app` | `dist` |

`apps/web/capacitor.config.ts` branches on `CAPACITOR_APP=production`. The
demo identity must never be overwritten or promoted to production.

## Prerequisites

- JDK 21 (`JAVA_HOME`)
- Android SDK with platform 36 (`ANDROID_HOME`)
- Repository dependencies installed (`pnpm install` / `make bootstrap`)
- Passport public Android client registered with redirect URI
  `com.globalenglish.app:/oauth/callback` (local: `php artisan passport:client --public`)
- Absolute API origin reachable from the device/emulator

Optional IPv6 workaround: `GRADLE_OPTS=-Djava.net.preferIPv4Stack=true`.

## Build-time configuration

Ordinary product web assets are built with Vite production mode. Capacitor
WebView cannot use same-origin relative `/api` routes, so set:

```bash
export VITE_MOBILE_API_ORIGIN='https://your-closed-beta-api.example'
# optional overrides:
# export VITE_MOBILE_OAUTH_CLIENT_ID='public-android-client'
# export VITE_MOBILE_OAUTH_REDIRECT_URI='com.globalenglish.app:/oauth/callback'
# export VITE_MOBILE_OAUTH_AUTHORIZE_URL=...
# export VITE_MOBILE_OAUTH_TOKEN_URL=...
```

Never embed provider secrets, OTP credentials or Passport private keys in the
Android package.

## Debug APK (default closed-beta path)

```bash
make android-closed-beta
```

Equivalent steps:

```bash
cd apps/web
VITE_MOBILE_API_ORIGIN="$VITE_MOBILE_API_ORIGIN" pnpm build
CAPACITOR_APP=production pnpm exec cap sync android
cd android-app
./gradlew assembleDebug
```

Artifact:

`apps/web/android-app/app/build/outputs/apk/debug/app-debug.apk`

Install:

```bash
adb install -r apps/web/android-app/app/build/outputs/apk/debug/app-debug.apk
```

### Signing limitation (debug)

`assembleDebug` signs with the Android **debug keystore**. That is acceptable
for local/staff closed-beta smoke installs. It is **not** Play App Signing and
is not a public-release artifact. Record this limitation on acceptance.

## Release AAB / APK with a local upload keystore (optional)

1. Create a local upload keystore **outside the repository** (never commit):

```bash
keytool -genkeypair -v \
  -keystore "$HOME/ge-closed-beta-upload.jks" \
  -keyalg RSA -keysize 2048 -validity 3650 \
  -alias ge-closed-beta
```

2. Create an ignored `apps/web/android-app/keystore.properties` (do not commit):

```properties
storeFile=/absolute/path/to/ge-closed-beta-upload.jks
storePassword=...
keyAlias=ge-closed-beta
keyPassword=...
```

3. Wire `signingConfigs` in `app/build.gradle` when that file exists (operator
   local change) and run:

```bash
cd apps/web/android-app
./gradlew bundleRelease
# or: ./gradlew assembleRelease
```

Artifacts:

- AAB: `app/build/outputs/bundle/release/app-release.aab`
- APK: `app/build/outputs/apk/release/app-release.apk`

Google Play **public** listing remains unauthorized under APP-0030. Closed
testing track upload, if used, is an operational step outside this APP’s
acceptance of public release.

## Static identity checks

```bash
# After a successful assembleDebug / assembleRelease:
"$ANDROID_HOME/build-tools/"*/aapt2 dump badging \
  apps/web/android-app/app/build/outputs/apk/debug/app-debug.apk \
  | rg 'package:|sdkVersion:|targetSdkVersion:|uses-permission:'
```

Expect:

- `name='com.globalenglish.app'`
- `sdkVersion:'24'`
- `targetSdkVersion:'36'`
- permissions include `INTERNET`, `RECORD_AUDIO`, `MODIFY_AUDIO_SETTINGS`,
  `ACCESS_NETWORK_STATE`
- package **must not** be `com.globalenglish.demo`

## Secure storage selection

See [ADR-0012](adr/0012-android-native-secure-credential-store.md): project-owned
Capacitor plugin over AndroidX `EncryptedSharedPreferences`.

## Device verification

Follow [PR-014_ANDROID_DEVICE_MATRIX.md](PR-014_ANDROID_DEVICE_MATRIX.md).

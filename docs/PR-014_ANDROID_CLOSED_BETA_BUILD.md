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
export VITE_MOBILE_OAUTH_CLIENT_ID='019fdd3f-4bba-720b-9e4e-a771ecccdd7f'
# optional overrides:
# export VITE_MOBILE_OAUTH_AUTHORIZE_URL=...
# export VITE_MOBILE_OAUTH_TOKEN_URL=...
```

The redirect is fixed to `com.globalenglish.app:/oauth/callback`; any different
`VITE_MOBILE_OAUTH_REDIRECT_URI` makes Android authentication fail closed.
Authorization/token endpoint overrides must stay on the configured API origin
and may not include credentials, query strings or fragments. The API origin
must use HTTPS, except `http://10.0.2.2`, loopback or `localhost` for local
device/emulator development, and invite-only static IPv4 HTTP on port 8080
(`http://117.254.108.143:8080` / `http://192.168.1.4:8080`) under D-0257 /
D-0258. The native Custom Tabs plugin uses that same origin allowlist; it
must not require HTTPS when the baked origin is invite-only HTTP `:8080`.
Missing or unsafe configuration never falls back
to Sanctum cookie authentication inside the WebView.

Play `make android-release-bundle` still requires an `https://` API origin.

Never embed provider secrets, OTP credentials or Passport private keys in the
Android package.

## Debug APK (default closed-beta path)

```bash
make android-closed-beta
```

Equivalent steps:

```bash
cd apps/web
VITE_MOBILE_API_ORIGIN="$VITE_MOBILE_API_ORIGIN" \
  VITE_MOBILE_OAUTH_CLIENT_ID="$VITE_MOBILE_OAUTH_CLIENT_ID" pnpm build
CAPACITOR_APP=production pnpm exec cap sync android
cd android-app
VITE_MOBILE_API_ORIGIN="$VITE_MOBILE_API_ORIGIN" ./gradlew assembleDebug
```

The native Custom Tabs plugin pins `BuildConfig.MOBILE_API_ORIGIN` from
`VITE_MOBILE_API_ORIGIN`. Debug `assemble*` / `bundle*` tasks fail closed when
that origin is missing or not on the same allowlist as
`androidPkceEnvironment.ts`. An empty native origin makes every in-app Sign in
tap reject without opening a browser.

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

## Release AAB with an external upload keystore

1. Create a local upload keystore **outside the repository** (never commit):

```bash
keytool -genkeypair -v \
  -keystore "$HOME/ge-closed-beta-upload.jks" \
  -keyalg RSA -keysize 2048 -validity 3650 \
  -alias ge-closed-beta
```

2. Export the four signing inputs from an operator-controlled secret source.
   Do not put passwords in shell history, command-line arguments, `.env` files,
   Gradle properties or the repository:

```bash
export GE_ANDROID_UPLOAD_STORE_FILE=/absolute/path/outside/repository/ge-closed-beta-upload.jks
export GE_ANDROID_UPLOAD_STORE_PASSWORD
export GE_ANDROID_UPLOAD_KEY_ALIAS=ge-closed-beta
export GE_ANDROID_UPLOAD_KEY_PASSWORD
export VITE_MOBILE_API_ORIGIN=https://api.example.org
```

   A shell can populate the two password variables through a protected secret
   manager or a non-echoing prompt. Never paste them into chat or logs.

3. Build the production-path bundle:

```bash
make android-release-bundle
```

Artifact:

- AAB: `app/build/outputs/bundle/release/app-release.aab`

Gradle rejects release tasks unless all four signing variables are present,
the keystore exists and it is outside the repository. Debug builds do not
require signing inputs. The Make target also rejects non-HTTPS API origins.

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

## Authenticated request selection

See
[ANDROID_AUTHENTICATED_REQUEST_LIFECYCLE.md](ANDROID_AUTHENTICATED_REQUEST_LIFECYCLE.md).
The production Android runtime routes learner, conversation, operator,
event-stream, token-family revocation and private model-audio/lesson-video
requests through the shared Passport adapter. Access tokens are memory-only,
cookies are omitted, and a 401 receives at most one refresh and exact replay.
Do not add raw private-media `<audio src>` or `<video src>` paths to the Android
runtime because DOM media requests cannot attach the bearer.

## Device verification

Follow [PR-014_ANDROID_DEVICE_MATRIX.md](PR-014_ANDROID_DEVICE_MATRIX.md).

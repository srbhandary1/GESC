# PR-014 closed-beta Android device matrix

**Authority:** [APP-0030](APPROVALS.md#app-0030); evidence closure under
[APP-0032](APPROVALS.md#app-0032) (PH-01–PH-04)  
**Package:** `com.globalenglish.app` (`apps/web/android-app`)  
**Non-evidence:** `com.globalenglish.demo` / `make demo-android` (D-0078)

## Matrix (closed beta)

| ID | Form factor | OS / API | Notes |
|---|---|---|---|
| DM-01 | Phone (reference) | Android 14 / API 34 | Primary PKCE + Learn + Practice path |
| DM-02 | Phone (legacy floor) | Android 8.0 / API 26 | Above minSdk 24; permission UX differs |
| DM-03 | Phone (minSdk proxy) | Android 7.0 / API 24 | Floor support; Custom Tabs / Keystore quirks |
| DM-04 | Large phone / fold outer | Android 15 / API 35 | Lifecycle + mic interruption |
| DM-05 | Tablet (optional) | Android 13+ / API 33 | Layout/readability only if a tablet pilot exists |

Emulators may be used for smoke checks; **acceptance evidence for AN-07 / AN-08
requires at least DM-01 and DM-02 on physical devices** (or a documented
emulator substitute when hardware is unavailable, recorded as a limitation).

## Critical journeys to verify on each matrix device

1. Cold start → system-browser Passport PKCE → exact, single-use deep-link return → authenticated shell; repeated native delivery must not exchange twice.
2. App process death / restart → refresh from secure store → session restored (access token memory-only).
3. Learn online mission start (accepted learner journey) with mic grant; private model audio and lesson video must play through authenticated requests.
4. Mic permission denial → clean recovery, no credential leakage.
5. Background during recording / Custom Tabs → return without stuck auth state.
6. Connectivity loss/reconnect during an online action → deterministic error/retry UX; an expired access token causes one refresh/replay and terminal rejection returns to the anonymous shell.
7. Logout → token family revoke attempt → secure store cleared of refresh/pending; installation ULID retained.
8. Negative storage check after login and after logout (see below).

## Negative credential storage checks (AN-06)

On-device (Chrome remote WebView debugging or equivalent), after a successful
login and again after logout, confirm **absence** of refresh tokens, access
tokens, PKCE verifiers and installation secrets in:

- WebView `localStorage`
- WebView `sessionStorage`
- IndexedDB
- Cache Storage
- Ordinary Capacitor Preferences (if the Preferences plugin is present in the install)

Credentials must appear only in the project-owned
`EncryptedSharedPreferences` file (`ge_secure_credentials`) and the
in-memory access token inside the JS adapter.

## What cannot be automated in this workstation environment

- Physical device matrix execution (no guaranteed KVM / device farm here).
- Play closed-testing track upload.
- End-to-end Passport login against a production host (requires deployed API,
  public Android client, and OTP path appropriate to the environment).
- Full `make verify` is not claimed by this document unless separately run.

## AND-AUTH-01 automated/workstation evidence (2026-08-08)

| Check | Result |
|---|---|
| Cold refresh, rotation, 401 replay/coalescing and late-logout races | Pass in TypeScript contract tests |
| Exact/single-use callback and native atomic pending-state take | Pass in TypeScript/native source contract tests |
| Authenticated private model audio and lesson video | Pass in client/object-URL tests |
| PostgreSQL mobile token-family, PKCE and concurrent refresh behavior | Pass, 12 tests / 567 assertions |
| Android `testDebugUnitTest` | Pass with JDK 21 |
| Android `assembleDebug` | Pass, 93 actionable tasks |
| APK identity | Pass: `com.globalenglish.app`, minSdk 24, targetSdk 36 |
| APK SHA-256 | `3095c1a23e5705d3a3ed64a310dcc988e1ac5c3b1be8bf0b565319296441618b` |
| Attached device check | None: `adb devices -l` returned an empty device list |

This evidence validates repository behavior and packaging; it does not replace
the DM-01/DM-02 physical lifecycle and negative-storage checks below.

## Evidence log (fill during device runs)

| Device ID | Build SHA / versionName | Journeys 1–8 | Storage negative | Tester | Date |
|---|---|---|---|---|---|
| DM-01 (emulator substitute) | local closed-beta debug / `1.0` | Journey 1 Pass on emulator substitute (see PKCE note); journeys 2–8 not claimed | Not run | Codex terminal + APO KVM grant | 2026-08-07 |
| DM-02 | | | | | |
| DM-03 | | | | | |
| DM-04 | | | | | |

### Emulator substitute note (2026-08-07)

| Field | Value |
|---|---|
| AVD | `DM01_API34_Pixel7` (API 34 Google APIs x86_64; Android 14) |
| Acceleration | **KVM** after `scripts/setup-android-emulator-kvm.sh` (`kvm` group + ACL) |
| Boot / install | `scripts/run-android-emulator-matrix.sh` — boot completed; `adb install -r` Success |
| Package | `com.globalenglish.app` (`minSdk=24`, `targetSdk=36`, `versionName=1.0`) |
| Launch | `MainActivity` pid present; Home shell renders with language-fallback banner and Secure session CTA |
| Screenshots | `artifacts/android/dm01-kvm-launch-20260807.png`, `dm01-kvm-secure-session-20260807.png` (gitignored artifacts/) |
| Limitation | Emulator substitute for **install + cold-launch shell smoke**. Journeys 1–8 (PKCE against live API, Learn/mic, logout, storage negatives) **not claimed**. Physical DM-01/DM-02 still preferred for AN-07/AN-08 acceptance. |

### Emulator PKCE path progress (2026-08-07)

Local API for emulator: Docker `ge-api-serve` (`ge-php84-pgsql:local`) on compose network with
`php artisan serve --host=0.0.0.0 --port=8000 --no-reload`,
`APP_URL=http://10.0.2.2:8000`, `DB_HOST=postgres`, `REDIS_HOST=redis`,
`OTP_PROVIDER_BASE_URL=http://otp-stub:8080`. Public Android client
`019fdd3f-4bba-720b-9e4e-a771ecccdd7f` → `com.globalenglish.app:/oauth/callback`.
Closed-beta APK baked with `VITE_MOBILE_API_ORIGIN=http://10.0.2.2:8000` and
`CapacitorHttp.enabled=true`, plus cleartext NSC for `10.0.2.2` / loopback.

| Step | Result |
|---|---|
| Secure session CTA → Custom Tabs | Pass (Chrome FRE dismissed once) |
| Mobile OTP request/verify (local fixed code) | Pass |
| Authorize consent → `Location: com.globalenglish.app:/oauth/callback?code=…` | Pass (Custom Tabs shows `net::ERR_ABORTED` on custom-scheme redirect; expected) |
| Deep-link / `ge:oauth-callback` into WebView | Pass |
| Token exchange → authenticated shell | **Pass (emulator substitute)** — `/oauth/token` + `/api/v1/me`; UI shows `Signed in.` / organization / `Sign out`; screenshot `artifacts/android/dm01-oauth-final.png` |
| Journeys 2–8 | Not claimed |

Root causes fixed while debugging journey 1:

1. `artisan serve` without `--no-reload` stripped Docker Redis/DB env (sessions broke).
2. WebView is `https://localhost` → browser `fetch` to `http://10.0.2.2` was mixed-content blocked; enable CapacitorHttp + cleartext NSC.
3. Capacitor GET interceptor advertises `Content-Length: 0` with a real JSON body; hey-api treated `/me` as `{}` and the authenticated shell crashed. Fixed via `createAndroidCapacitorFetch()` + identity validation.
4. Direct DOM media URLs cannot attach Passport bearers. Production Android now downloads private model audio and lesson video through the shared authenticated transport and revokes each object URL after use.

Also landed earlier: `MainActivity` OAuth callback retry dispatch; PKCE pending not consumed on malformed/wrong-state raced deliveries.

**Limitation:** emulator substitute only. Physical DM-01/DM-02, journeys 2–8, storage negatives, and F0/AN-07 Exact accept remain open.

## Workstation assemble note (APP-0034)

| Field | Value |
|---|---|
| Commit | `21f577cb2b1ac032124f65d3774b46e45e665196` |
| Date | 2026-08-07 |
| Result | `assembleDebug` Pass locally (~5.8 MB `app-debug.apk`) |
| Physical DM-01/DM-02 | **Not run** — no device attached; matrix remains open |
| Limitation | Recorded under [F0_APP0034_EVIDENCE_PACKAGE.md](F0_APP0034_EVIDENCE_PACKAGE.md) |

## Workstation assemble re-verify (post APP-0036)

| Field | Value |
|---|---|
| Commit | `22815bdfea835acfdd8eb4968743dd2b7ec52a7d` |
| Date | 2026-08-07 |
| Result | `clean assembleDebug` Pass locally (~5.8 MB `app-debug.apk`) |
| APK SHA-256 | `3ac150b5777298e416f494de682cf3c7dbbec98052fe2ca366fd216f3289cae7` |
| Emulator substitute | **Failed** — AVD `eeshani_api_36` missing system image; `/dev/kvm` absent |
| Physical DM-01/DM-02 | **Still open** — attach a physical phone to run journeys 1–8 |

## AND-AUTH-01 workstation assemble (2026-08-08)

| Field | Value |
|---|---|
| Workspace base commit | `94c47009b244b9a53fecdf6933c2e751e2c8a1c0` (checkpoint changes uncommitted) |
| Result | `testDebugUnitTest` and final `assembleDebug` Pass |
| APK | 6,230,453-byte debug APK; SHA-256 `3095c1a23e5705d3a3ed64a310dcc988e1ac5c3b1be8bf0b565319296441618b` |
| Static identity | `com.globalenglish.app`; minSdk 24; targetSdk 36; expected permissions |
| Physical DM-01/DM-02 | **Not run** — `adb devices -l` listed no attached device |

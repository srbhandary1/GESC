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

1. Cold start → system-browser Passport PKCE → deep-link return → authenticated shell.
2. App process death / restart → refresh from secure store → session restored (access token memory-only).
3. Learn online mission start (accepted learner journey) with mic grant.
4. Mic permission denial → clean recovery, no credential leakage.
5. Background during recording / Custom Tabs → return without stuck auth state.
6. Connectivity loss/reconnect during an online action → deterministic error/retry UX.
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

## Evidence log (fill during device runs)

| Device ID | Build SHA / versionName | Journeys 1–8 | Storage negative | Tester | Date |
|---|---|---|---|---|---|
| DM-01 (emulator substitute) | `d97e66bd…` / `1.0` | Install + cold launch only (not 1–8) | Not run | Codex terminal setup | 2026-08-07 |
| DM-02 | | | | | |
| DM-03 | | | | | |
| DM-04 | | | | | |

### Emulator substitute note (2026-08-07)

| Field | Value |
|---|---|
| AVD | `DM01_API34_Pixel7` (API 34 Google APIs x86_64) |
| Acceleration | Software (`-accel off`) — user not in `kvm` group; `/dev/kvm` present but ACL denied |
| Package | `com.globalenglish.app` installed successfully |
| Launch | Process/activity started; first screenshot showed System UI ANR under software accel (`artifacts/android/dm01-emulator-launch-20260807.png`) |
| Limitation | Emulator substitute for **install smoke only**. UI journeys 1–8 **not claimed** (software accel too slow without KVM group membership). Physical DM-01/DM-02 still preferred. Grant KVM via `bash scripts/setup-android-emulator-kvm.sh` (interactive sudo once), then `bash scripts/run-android-emulator-matrix.sh`. |

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

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
| DM-01 | | | | | |
| DM-02 | | | | | |
| DM-03 | | | | | |
| DM-04 | | | | | |

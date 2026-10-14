# APP-VERSION-10 review — 26 September 2026

Status: deployed and verified; release `app-version-20260926-r1`, Android 1.0.9/code 10.

## Delivered behaviour and files

- `apps/web/src/appRelease.json` is the shared versionName/versionCode source.
  Android Gradle reads it directly; the bundled UI imports the same version.
  An older installed APK therefore retains its own version when the website changes.
- `androidDownload.ts`, `App` and `MarketingLanding` show `v1.0.9` on both website
  Android links and use `global-spoken-english-1.0.9.apk` as URL and download name.
- Account settings show a localized **App version 1.0.9**, including Android.
  The narrow-screen header keeps the version visible below its download icon.
- `scripts/stage-android-apk.mjs`, called after a successful closed-beta Gradle build,
  stages the versioned APK and the existing generic filename as a compatibility
  alias. Older shared links remain usable; new download links identify the version.
- Six catalogue entries and their pending-review hashes are updated. Tests cover
  named downloads, matching website text and version visibility in Android Account
  settings. Download documentation records the resulting artifact.

## Boundaries

No API, migration, new environment variable, permission, credential storage,
retention, billing, curriculum or AI-provider change. This exposes the installed
version for comparison with the website; it does not auto-install updates or
claim an automatic latest-version check. The current bundled-web architecture
pins the displayed version to the same manifest used by the Android package.

## Verification and release

`make verify` passed: 561 web tests, SQLite 363 tests/3,360 assertions,
PostgreSQL 441 tests/6,441 assertions, plus static/security/contracts and workspace
checks. `make e2e` passed 47 browser journeys and 4 API tests/103 assertions.
`make android-closed-beta` and `VITE_UI_TRANSLATION_PREVIEW=true make build`
passed; 13 native JVM tests passed. APK signature verification and package metadata
checks passed. An initial TypeScript helper mismatch and a dark-mode translation
notice contrast failure were fixed before successful reruns and the final builds.
The latter changes `jezwebLearnerChrome.css` to use readable notice text.

The connected phone was updated in place and Android reports 1.0.9/code 10; its
Account settings show `ಆ್ಯಪ್ ಆವೃತ್ತಿ 1.0.9`. Live readiness, homepage and APK hashes
passed after service restart (transient connection resets recovered on retry).
Live 390px mobile homepage inspection confirmed the visible v1.0.9 badge,
versioned URL/download filename and no horizontal overflow. `git diff --check` passed.
APK SHA-256: `fe071222aa2e161b51d373d1ed82d08c0513be9da29f1b9a51024e04d46aec99`;
15,374,059 bytes. No migration or new configuration is required. Existing privacy,
security and tenant boundaries are unchanged; no learner data is added to logs.

AI evaluation
is not rerun because prompts/providers/orchestration are unchanged. Recommended
next checkpoint: continue the already-open Kannada curriculum-instruction work;
automatic update notifications can be a separately bounded feature if requested.

# Synthetic demo distribution scope

## Purpose

The Accountable Product Owner requested an immediately presentable demo of
the accepted product slices on the web and as an Android application. This
bounded slice packages the existing disclosed synthetic in-memory demo
(D-0076) as:

1. a static web bundle (`apps/web/dist-demo`) built by the explicit
   `vite build --mode demo` distribution mode; and
2. a Capacitor 8 Android demo APK (`apps/web/android`) that wraps the same
   bundle.

It is presentation packaging only. It does not accept PR-009, authorize
PR-010 or later checkpoints, approve curriculum or language content, add a
provider path or authorize production deployment.

## Boundary

- The demo runtime activates only when `VITE_LOCAL_DEMO=true` and either a
  Vite development runtime (`make demo`) or the explicit `demo` build mode
  (`make demo-build`) is present. The ordinary production build mode cannot
  activate demo adapters; unit and configuration tests enforce both sides of
  the boundary.
- Demo fixtures remain in-memory, synthetic and reset on reload. The demo
  registers no service worker, writes no `localStorage`, IndexedDB,
  service-worker cache or other device storage, and makes no API or provider
  request.
- The visible synthetic-demo notice remains part of every demo surface. All
  five translated notices remain implementation drafts pending independent
  review; English is the reviewed source.
- The demo journey covers Home (recommended mission), Learn (Kannada-guided
  mission with model audio, local-only recording practice, deterministic
  check and result evidence), Progress (synthetic objective progress) and
  Practice (deterministic text-only Friend prototype with rotating follow-up
  replies and a tiny curated set of gentle pattern tips). Pattern tips are
  scripted demo content, not grammar analysis, and remain advisory.
- The Android wrapper packages the bundle with Capacitor 8.5.0 (minimum API
  24, target API 36, AGP 8.13.0, Gradle 8.14.3). `RECORD_AUDIO` supports the
  existing local-only practice capture; recordings stay in browser memory
  for playback/retry and are never uploaded or persisted.
- `com.globalenglish.demo` is a placeholder demo identity. The client-owned
  production package identity, Passport PKCE authentication, native secure
  storage, device-matrix testing and Play release engineering remain PR-014
  scope and are not implemented here.

## Commands

| Command | Behaviour |
|---|---|
| `make demo` | Development-server demo on `127.0.0.1:5173` (unchanged) |
| `make demo-build` | Build the distribution bundle into `apps/web/dist-demo` |
| `make demo-serve` | Serve the built bundle on `127.0.0.1:4175` |
| `make demo-e2e` | Chromium journey against the development-server demo |
| `make demo-dist-e2e` | Chromium journey against the built bundle |
| `make demo-android` | Build `apps/web/android/app/build/outputs/apk/debug/app-debug.apk` |

`make demo-android` requires JDK 21 (`JAVA_HOME`) and an Android SDK with
platform 36 (`ANDROID_HOME`). On an IPv6-limited network pass
`GRADLE_OPTS=-Djava.net.preferIPv4Stack=true`.

## Verification evidence

- Runtime-policy unit tests: the demo enables in a development runtime or
  the explicit distribution build and never when only the ordinary
  production mode is present.
- Configuration test: production mode builds to `dist/` with base `/`; demo
  mode builds to `dist-demo/` with relative base.
- Chromium demo journey (development server and built bundle): Home opens on
  the populated mission; the complete lesson, microphone-denial fallback,
  check, result, progress objectives and Friend turns (rotating reply plus
  one pattern tip) pass with zero `/api/` requests, zero `localStorage`
  entries, zero service-worker registrations and zero automated axe
  violations.
- Android APK static verification: `aapt2 dump badging` confirms the
  `com.globalenglish.demo` demo identity, `Global English Demo` label,
  minimum API 24, target API 36, the three declared permissions and the
  bundled `assets/public` demo files.
- The Android APK has not been executed on a device or emulator in this
  workstation session (no KVM access for the local AVD). On-device
  installation and a representative device pass remain outstanding evidence
  before the APK is shown beyond a controlled presentation.

## Explicitly out of scope

- PR-009 acceptance, live provider calls, spend or benchmark evidence;
- PR-010 and later checkpoint implementation;
- production deployment, hosting or store submission;
- curriculum, Kannada wording or media approval;
- authentication, learner accounts or tenant data in the demo;
- the PR-014 production Android application.

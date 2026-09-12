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
  service-worker cache or other device storage. Ordinary product API routes
  are unused. Under D-0092 / D-0116 / D-0117, the local Vite demo process
  **and** the demo-client static server may call Sarvam, ElevenLabs or
  Azure Speech TTS through `POST /demo/tts` when credentials are present in
  process env or the host settings file; the browser never receives a key.
  Static `dist-demo` and the Android APK still ship without secrets.
  Optional `VITE_DEMO_TTS_ORIGIN` points the APK at that host.
- The visible synthetic-demo notice remains on **Profile** (`Demo status`)
  while `demoMode` is on. Home, Learn, Practice and Progress no longer show
  a page-wide demo banner. All five translated notices remain implementation
  drafts pending independent review; English is the reviewed source.
- The demo distribution is an explicitly requested review artifact and sets
  `VITE_UI_TRANSLATION_PREVIEW=true`, so the Kannada-first interface and the
  other draft locales render with the existing visible preview notice
  (ADR-0005 review mechanism). Ordinary production builds remain
  `approved-only` and fall back visibly to English.
- Under D-0081 / D-0092 the demo journey presents a Phase 1 learner dashboard
  shell on **Home**, with primary shell navigation also visible for **Learn**,
  **Practice** (AI Friend), and **Progress**. Phase 1 modules: Dashboard,
  Attendance, AI Teacher (scripted replies + Azure/browser voice), Daily
  Activity, My Levels and Notice Board. Generative AI and approved curriculum
  remain deferred; Friend replies stay deterministic pattern scripting.
- Speech preference order (D-0092 / D-0116 / D-0117 / D-0079): neural TTS
  via `/demo/tts` when any of Sarvam, ElevenLabs or Azure is configured on
  the demo host (locale-aware failover; explicit `TTS_PROVIDER_ORDER`
  allowed), else on-device `speechSynthesis`. Spoken turns still use
  browser `SpeechRecognition`. Production STT/TTS remains behind the
  project-owned provider ports (PR-009+) and is unchanged by this
  presentation exception. See [DEMO_TTS.md](DEMO_TTS.md).
- Friend chat preference order (D-0094): OpenRouter free model via local
  `/demo/chat` when configured, else deterministic pattern replies. No
  ordinary Laravel LLM binding. See [DEMO_OPENROUTER.md](DEMO_OPENROUTER.md).
- Visible product name: **Global English Speaking Course**.
- The Android wrapper packages the bundle with Capacitor 8.5.0 (minimum API
  24, target API 36, AGP 8.13.0, Gradle 8.14.3). `RECORD_AUDIO` supports the
  existing local-only practice capture; recordings stay in browser memory
  for playback/retry and are never uploaded or persisted.
- `com.globalenglish.demo` is a placeholder demo identity. The production-path
  Capacitor tree is separate at `apps/web/android-app`
  (`com.globalenglish.app`) under [APP-0030](APPROVALS.md#app-0030) /
  [PR-014 closed-beta build](PR-014_ANDROID_CLOSED_BETA_BUILD.md). Do not
  promote this demo APK to production. Play public release remains unauthorized.

## Commands

| Command | Behaviour |
|---|---|
| `make demo` | Development-server demo on `127.0.0.1:5173` (unchanged) |
| `make demo-build` | Build the distribution bundle into `apps/web/dist-demo` |
| `make demo-serve` | Serve the built bundle on `127.0.0.1:4175` |
| `make demo-client` | Opt-in static-IP client serve on `0.0.0.0:8080` (see [CLIENT_DEMO_RUNBOOK.md](CLIENT_DEMO_RUNBOOK.md)) |
| `make demo-client-tunnel` | Temporary HTTPS Cloudflare tunnel for mic-capable remote review |
| `make demo-e2e` | Chromium journey against the development-server demo |
| `make demo-dist-e2e` | Chromium journey against the built bundle |
| `make demo-android` | Build `apps/web/android/app/build/outputs/apk/debug/app-debug.apk` |

Client presentation disclosure/walkthrough:
[CLIENT_DEMO_RUNBOOK.md](CLIENT_DEMO_RUNBOOK.md). Path from presentation to
Azure Speech evidence workspace:
[PROFESSIONAL_CLIENT_SETUP.md](PROFESSIONAL_CLIENT_SETUP.md).

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

---
title: "Android Mobile UX Remediation Evidence"
status: "UX-REM-00 complete; UX-REM-01 implementation complete with physical acceptance open"
started: "18 September 2026"
plan: "docs/ANDROID_MOBILE_UX_REMEDIATION_COMPLETION_PLAN.md"
---

# Android Mobile UX Remediation Evidence

Sequential execution authority is recorded as D-0319. It does not convert a
later decision, human-review, analytics, payment, Play or production gate into
an implementation PASS.

## UX-REM-00 — Candidate and device evidence path

### Session identity

| Field | Evidence |
|---|---|
| Repository HEAD | `b1660dccf6df0f9d50245729a2f38f66dbe4e2a1` |
| Branch | `cursor/learner-variant-c-jezweb-3f88` |
| Device | realme RMX3085 (`RMX3085L1`) |
| Serial | `J7GUXWEENBNZTW85` |
| Android / API | Android 13 / API 33 |
| Build fingerprint | `realme/RMX3085/RMX3085L1:13/SP1A.210812.016/R.1913398+a6a8f:user/release-keys` |
| WebView | `com.google.android.webview` 151.0.7922.199 |
| Physical display | 1080×2400; physical density 480; override density 408 |
| Navigation | Three-button (`navigation_mode=0`) |
| Font scale | `1.0` |
| Locale | `en-GB` |
| Package | `com.globalenglish.app` |
| Version | `versionName=1.0`, `versionCode=1` |
| SDK range | min SDK 24, target SDK 36 |
| Microphone at baseline | Granted; `USER_SET` flag present |

### ADB path

The phone was attached to the MacBook by USB. The Mac ADB daemon was exposed
to the Linux repository host through the approved reverse SSH port 5038.

Linux verification passed:

```text
List of devices attached
J7GUXWEENBNZTW85 device usb:339738624X product:RMX3085 model:RMX3085 device:RMX3085L1 transport_id:15
```

### Superseded installed APK

Before installing the pinned candidate, the package installed on the phone was
pulled into temporary storage and identified as:

```text
SHA-256: e7d1ecc92d162f951b91759ce8ac08b352e91ef7d19f75ba0f7f0d5750278d04
Bytes:   82,962,524
```

That APK is not used as remediation evidence.

### Pinned candidate build

Build inputs:

- repository HEAD `b1660dc`;
- `VITE_MOBILE_API_ORIGIN=https://ai.globalspokenenglish.com`;
- public Passport client `019fdd3f-4bba-720b-9e4e-a771ecccdd7f`;
- redirect `com.globalenglish.app:/oauth/callback`;
- JDK 21 and Android SDK 36; and
- translation-preview debug mode, as required by the closed-beta build
  contract.

The first sandboxed attempt built the web bundle and synchronized Capacitor,
then stopped because the Gradle wrapper cache under `~/.gradle` was read-only.
The exact build was rerun with approved host access.

Final build result:

```text
BUILD SUCCESSFUL in 7s
103 actionable tasks: 29 executed, 74 up-to-date
```

Candidate identity:

```text
Path:     apps/web/android-app/app/build/outputs/apk/debug/app-debug.apk
SHA-256:  fffbcec64978b98eaad9658670245fa03a27acf39b64e2612dbedfd663665012
Bytes:    82,981,433
Package:  com.globalenglish.app
Version:  1.0 (1)
Target:   SDK 36
```

`adb install -r` succeeded and preserved the test app data. The installed
`base.apk` was then pulled into temporary storage. Its SHA-256 and byte count
exactly matched the pinned candidate.

### Baseline capture status

The package launched and `MainActivity` became the focused application record.
The first attempted capture found the device asleep behind its six-digit lock
screen. ADB did not bypass that user authentication. After the phone owner
unlocked it, a new screenshot and accessibility hierarchy were captured.

The valid baseline shows the public guest Home surface in Kannada preview
mode. It contains public product copy and artwork only: no learner identity,
credential, authored learner text, transcript, audio or other personal learner
data is visible. The hierarchy confirms `com.globalenglish.app/.MainActivity`
as the resumed foreground application. WebView DOM content is not expanded in
the native UI Automator hierarchy, so DOM semantics remain a later TalkBack/
browser evidence concern rather than an inferred PASS.

Observed baseline characteristics, without assigning later-checkpoint verdicts:

- the Android native action bar displays `Global Spoken English App` above the
  web application chrome;
- the accepted guest Home uses the Kannada preview catalogue with visible
  English learning/product text where designed or unresolved; and
- the device remains at three-button navigation, font scale `1.0`, locale
  `en-GB` and granted microphone permission.

The valid screenshot and hierarchy remain in `/tmp` only and are not committed.
No font, locale, navigation, contrast, permission or developer-option setting
was changed. There is therefore no changed setting to restore.

### UX-REM-00 exit checklist

- [x] Linux `adb devices -l` shows the exact USB-attached phone through port
      5038. Evidence: ADB path above.
- [x] Candidate Git/APK/device identity is recorded. Evidence: session and
      candidate tables above.
- [x] The unlocked application baseline screenshot and accessibility hierarchy
      are captured from non-personal public guest data. Evidence: baseline
      capture status above.
- [x] The visible surface contains no personal learner data. Evidence: public
      guest Home inspection above.
- [x] A restore checklist exists for every device setting changed during the
      session. Evidence: no device setting was changed.

### Result

UX-REM-00 is complete on 18 September 2026. This evidence pins the candidate
and device path only. It does not mark any UX-REM-01 accessibility/reflow row,
authenticated learner journey, fresh-install permission row or Play/release
gate as passing.

## UX-REM-01 — Accessibility, reflow and adaptive layout

### Implementation evidence

The bounded change keeps the accepted five destinations and Variant C identity
while repairing the layout contracts that failed under enlarged text:

- phone navigation labels may wrap without truncation, retain 48×48 CSS-pixel
  targets and keep the active state non-color-only;
- authenticated phone header controls reflow to one column at narrow widths,
  and the paid-minutes chip cannot force a wider viewport;
- native Android landscape keeps the same five-destination bottom grid and
  makes the large header non-sticky on short screens;
- high-contrast and forced-colour modes explicitly preserve navigation glyph
  and label visibility;
- onboarding consumes web and native top/right/bottom/left safe areas plus the
  native IME inset; and
- quiz actions inherit one native navigation offset and use the larger of the
  navigation, safe-area and IME offsets without counting the IME twice as
  internal padding.

No navigation destination, history behavior, quiz answer/scoring behavior,
stored state, API, OpenAPI contract, provider, entitlement or authoritative
record changed.

### Automated evidence

| Check | Result | Evidence |
|---|---|---|
| Focused lint | PASS | ESLint on the two changed Playwright specs and the Variant C theme test exited 0 |
| Typecheck | PASS | All five typed workspace projects passed |
| Responsive authenticated chrome | PASS | English and Kannada at 200% root text across 320, 360 and 412 CSS px; five targets ≥48×48; no document/control overflow |
| Header chip | PASS | Paid `82 live-AI minutes available` control reflows without overflow in the authenticated harness |
| Short landscape | PASS | 800×412 CSS-pixel Android-native simulation has no document-level horizontal overflow and retains the five-destination bottom grid |
| Sticky quiz action | PASS | Closed keyboard action ends above the bottom navigation; 220 CSS-pixel native IME inset places the complete action above the IME |
| Contrast and motion | PASS | Forced-colour navigation remains opaque and active state remains bordered/non-colour-only; reduced-motion path is exercised |
| First-use insets | PASS | English and Kannada first screen at 320 CSS px / 200% consumes simulated native top/right/bottom/left and 120 CSS-pixel IME insets without horizontal overflow; full journeys then pass |
| Affected Playwright suites | PASS | 11/11 learner and level-path journeys passed |
| `make verify` | PASS | Pint/PHPStan; SQLite 321/321 (3,101 assertions); PostgreSQL 360/360 (5,226 assertions); web 466/466; contracts, localization, lint and typecheck passed |
| `make e2e` | PASS | API 4/4 (103 assertions) and Playwright 30/30 |
| `make build` | PASS | Laravel cache cycle and production Vite build passed; existing chunk-size warning only |
| Android build | PASS | `BUILD SUCCESSFUL in 7s`; 103 actionable tasks (29 executed, 74 up-to-date) |

### Installed candidate

The implementation candidate was built from the UX-REM-01 working tree based
on repository HEAD `b1660dccf6df0f9d50245729a2f38f66dbe4e2a1`, using the
same stable-origin, public PKCE client, JDK 21, Android SDK 36 and closed-beta
translation-preview inputs recorded for UX-REM-00.

```text
SHA-256: e132cc26dc9250420fe8bcb0be1c1283c37a4c9c66da81e518f23fe739941db9
Bytes:   82,981,429
Package: com.globalenglish.app
Version: 1.0 (1)
SDK:     min 24 / target 36
```

The reverse ADB transport dropped during the first streamed install and
returned no package-manager reason. A non-streaming `adb install -r` then
pushed all 82,981,429 bytes and returned `Success`, preserving app data. An
on-device `sha256sum` and `stat` of the installed `base.apk` exactly match the
candidate above.

### Physical-device and emulator disposition

The installed build launches on the same realme RMX3085 / Android 13 / API 33.
The available state is the public guest Kannada-preview Home, so this session
does not infer authenticated navigation, quiz, plans or TalkBack results.

ADB attempts to set exact `font_scale=2.0` and density 540 (320 CSS px on the
1080-pixel display) were rejected by the OEM with `WRITE_SETTINGS` and
`WRITE_SECURE_SETTINGS` security exceptions. Verification immediately after
the attempt shows the original `font_scale=1.0` and override density 408, so no
device-setting restoration was required.

The existing Pixel API 36 AVD was also started headlessly with software
acceleration because `/dev/kvm` is unavailable. It identified as Android 16 /
API 36 and reached an online ADB transport, but never set `sys.boot_completed`
or produced a focused application after the extended cold-boot window. The
emulator was stopped cleanly. No API 36 acceptance cell is marked PASS.

### Open physical acceptance

- [ ] TalkBack completes first use, quiz, result, plans close and focus return.
- [ ] English and Kannada pass on a device at its maximum font setting, with
      exact 200% evidence from a capable device/emulator.
- [ ] Exact 320 CSS-pixel portrait and short-height physical landscape pass.
- [ ] Physical split-screen preserves state and remains usable.
- [ ] Physical quiz CTA remains visible with IME open/closed.
- [ ] Physical high-contrast navigation remains legible and non-colour-only.

UX-REM-01 implementation is complete, but the checkpoint remains **in
progress** because these physical/human acceptance conditions are not yet
evidenced. UX-REM-02 must not start until they pass or the Accountable Product
Owner explicitly accepts a bounded residual.

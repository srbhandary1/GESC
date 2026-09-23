---
title: "Android Mobile UX Remediation Evidence"
status: "UX-REM-00 through UX-REM-06 technically complete; retained human review gates remain unpassed"
started: "18 September 2026"
plan: "docs/ANDROID_MOBILE_UX_REMEDIATION_COMPLETION_PLAN.md"
---

# Android Mobile UX Remediation Evidence

Sequential execution authority is recorded as D-0319. It does not convert a
later decision, human-review, analytics, payment, Play or production gate into
an implementation PASS.

## UX-REM-06 retained first-success sequence — 23 September 2026

The Accountable Product Owner accepted Option A. The refined current ten-stage
first-use sequence is authoritative for this release, including the existing
adult/goal command, independent optional voice purposes, typed fallback, Back
behavior and encrypted option-only restoration. The implementation already
conforms to that decision; no code, API/OpenAPI, schema, migration, consent,
authentication, restoration or catalogue change was required. Option B's
partial-onboarding contract is not authorized.

The implementation and full repository gates recorded under UX-REM-03/04/05
remain the technical evidence for this unchanged flow. Representative
first-use completion, voice-choice comprehension, median/p90 time,
abandonment point and invalid-submit measurements remain unpassed UX-HUM-03
evidence and are not inferred from automated tests.

## UX-REM-05 contextual plans and Continue-free parity — 23 September 2026

Contextual plans opened from a locked level now isolate the purchase decision:
unrelated password-security and federated-account panels are not rendered.
The panel still leads with current access and the locked/completed-level
context, shows exactly two membership cards, does not preselect either offer,
and makes Premium visually distinctive without changing selection state.

Each offer retains benefit-led differences and an accessible price disclosure
with ex-GST amount, GST and exact payable total. The inset-aware sticky dock
keeps **Continue free / review prior lessons** and **Close plans** available.
Close, Continue free and Android Back restore the exact originating locked
level and keyboard focus. Pending and ambiguous checkout states expose **Check
access** and do not claim success before reconciliation. Institutional
entitlements still render no Direct checkout, and live payment remains off.

Focused App/Subscription tests pass 32/32, typecheck passes, and the contextual
Playwright journey passes with 320-pixel no-overflow and scoped axe evidence.
Representative recall of current access, both plan differences, exact total
and Continue-free remains an unpassed UX-HUM-03 comprehension gate.

Final gates pass: `make verify` (SQLite 333/333, 3,161 assertions; PostgreSQL
361/361, 5,232 assertions; web 107 files / 479 tests), `make e2e` (API 4/4,
103 assertions; Playwright 30/30), and `make build` with the existing
large-chunk advisory. The package audit reports two moderate advisories and no
high-severity failure.

The combined UX-REM-03/04/05 debug-signed closed-beta APK was built from Git
`7d3faf7` with the stable HTTPS origin and public Passport client. Android unit
tests and `assembleDebug` passed (103 tasks). The verified artifact is
82,944,538 bytes with SHA-256
`8a7781c597ab1c67d11e54e94fecf2463b657fb977445923de50d405a823071d`,
package `com.globalenglish.app`, min SDK 24 and target SDK 36. Installation is
not claimed: the reverse-forwarded ADB daemon stopped listing the phone before
this exact candidate could be installed.

## UX-REM-04 quiz and result hierarchy — 23 September 2026

Aggregate quick-check and checkpoint scores are now absent while individual
question feedback is being reviewed. The score/pass result appears only after
the learner acknowledges the last rationale. One-question presentation,
48-pixel targets, focused retry, bounded attempts, idempotency and offline
submission semantics are unchanged.

The mission-result first viewport now contains the can-do outcome, correct/not
yet state, compact capability cue and dominant **Continue from Home** action.
Knowledge/spoken/local-recording facts and first/latest/best attempt evidence
remain available under keyboard-operable **Attempt details**. The due date and
objective schedule remain available under **Review details**. No authoritative
fact was removed or rewritten.

At 320×568 CSS pixels the can-do outcome and primary action are visible in the
same first viewport with no horizontal document overflow. At 200% text the
result order remains logical, definition lists collapse to one column, the
native disclosures operate by keyboard and the scoped result axe scan is
clean. Focused component tests pass 29/29 and localization tests pass 38/38.
Final gates pass: `make verify` (SQLite 333/333, 3,161 assertions; PostgreSQL
361/361, 5,232 assertions; web 107 files / 479 tests), `make e2e` (API 4/4,
103 assertions; Playwright 30/30), and `make build` with the existing
large-chunk advisory. The package audit reports two moderate advisories and no
high-severity failure.

Catalogue source revision is `ui-en-IN-2026-09-23.2` with hash
`sha256:b5d174c13b24c5d6cd1577435b2d4ca78ec268ede180945374cb83b9cdd5fe9f`.
All five non-English exact revisions remain `pending-independent-review` and
approved-only builds continue to fall back to English. Representative
five-second outcome recall and unprompted next-action evidence remains an
unpassed UX-HUM-03 gate; automated tests do not substitute for it.

## UX-REM-03 action copy and completion cues — 23 September 2026

The bounded implementation is complete. It changes learner-facing labels and
presentation state only:

- first use uses **Get started**, **Confirm age and continue**, **Save goal and
  continue**, explicit privacy/practice actions, and **Continue without
  voice** when no voice purpose is selected;
- a required adult, goal, starting route, audio outcome, microphone choice or
  practice response disables the primary action until selected instead of
  presenting an enabled generic Continue;
- Home uses **Start lesson** or **Start review**, and result retains
  **Continue from Home**;
- incorrect multi-item feedback states that the attempt was recorded, mastery
  did not change and one short retry is ready;
- every completed session shows a check icon, visible **Completed** text, an
  accessible completed name, and compact **X of 4 sessions complete** status;
- learner-facing `authoritative entitlement`, `receipt`, `unassessed` and
  `in this slice` wording was removed from the affected English surfaces; and
- existing recording-state coverage continues to prove exactly one visible
  **Type instead** action per state.

The `workplace-confidence` label remains **Speak with confidence at work**.
Changing it to **Work or study confidence** would broaden the stored goal's
meaning and historical cohort interpretation; D-0337 therefore retains the
current label/key rather than silently relabeling authoritative history.

Governed source revision `ui-en-IN-2026-09-23.1` has hash
`sha256:ef313dc6afd7d9c3d1fe244fb68dbda03e2057dafeec9cad94aa920fb0400ad7`.
All five non-English catalogues, including the revised Kannada draft, are
truthfully `pending-independent-review`. Approved-only resolution falls back
to English until each exact revision is reviewed; development preview may show
the drafts. Focused evidence passes: localization 38/38, web 70/70 and
learner/level-path Playwright 11/11, including scoped axe.

Final repository gates also pass: `make verify` (SQLite 333/333, 3,161
assertions; PostgreSQL 361/361, 5,233 assertions; web 107 files / 479 tests),
`make e2e` (API 4/4, 103 assertions; Playwright 30/30), and `make build`. The
build retains the existing large-chunk advisory, and the package audit reports
two moderate advisories but no high-severity failure.

No API, OpenAPI, schema, scoring, mastery, consent, entitlement, provider,
retention or credential-storage behavior changed. UX-REM-03 is technically
complete for ordered implementation. Representative-learner comprehension and
independent review of the exact five non-English revisions remain unpassed
UX-HUM-03 and UX-HUM-01 gates; those human gates are not inferred from
automated tests.

## UX-REM-02 Back, interruption and restoration — 23 September 2026

The exact c11 production-origin APK (SHA-256
`42cb92ca087cdd7403cf70107487a7673415aba7d7a8175696a04de28f7bdbb4`)
and the package-correct instrumentation APK were exercised on official Android
API 35 and API 36 x86_64 AVDs in gesture-navigation mode. The production APK
was not rebuilt by this checkpoint; only the test APK changed.

| Exit row | Result | Evidence |
|---|---|---|
| Gesture Back and predictive preview | Pass on API 35 and API 36 | The native AndroidX callback exposed a visible prior-question preview. Real edge gestures traversed question 2 → question 1 → session → Learn. Existing browser coverage supplies the session → level-list transition using production history helpers; the native test injects only the same allowlisted history identifiers and does not claim server content |
| Root Back | Pass on API 35 and API 36 | Manual gesture-navigation runs traversed Sign in → Home and then returned the app root to the launcher without a legacy Back override |
| Onboarding Back and focus | Pass | Focused component coverage traverses browser and in-flow Back and asserts the restored stage heading receives focus; the existing Chromium journey covers the production onboarding route |
| Restorable choice and account isolation | Pass | The existing ADR-0006 encrypted store retains only tenant/member/level/part/attempt-bound option identifiers. Reload resumes the exact question and selected option; tenant-binding tests reject a different learner. No credential, learner text, transcript, audio or provider content is stored |
| Offline truth states | Pass | The level checkpoint now renders saved/pending, syncing, server-verified synced, or needs-attention. A delayed browser response proves the visible transition; a replayed 409/4xx is removed from retry and visibly marked needs-attention rather than falsely reconciled |
| Exact replay | Pass | PostgreSQL concurrent replay creates one submitted authoritative attempt for one stable idempotency key (1 test, 8 assertions); component coverage preserves the command ID and one receipt path |
| Recording Back | Pass on API 35 and API 36 | A real edge gesture while the cancelable recording Back request is active is consumed before navigation; after cancellation is removed, the same gesture advances history. Existing component coverage asserts capture cancellation and the localized explanation |
| Microphone permission branches | Pass on API 35 and API 36 | Production permission bridge exercised with system dialogs: denied once, denied permanently, App Settings recovery and granted. Permission reset was performed externally before instrumentation because revoking the target permission terminates the instrumented process. Existing product coverage keeps Type instead available across permission states |

Focused verification passed: four Vitest files / 48 tests; the level-path
offline Playwright file 4/4; the broader learner/offline Playwright selection
11/11; and the concurrent PostgreSQL replay test 1/1 (8 assertions). The app
module's JDK-21 `:app:testDebugUnitTest :app:assembleDebugAndroidTest` target
passed. The unqualified multi-module `assembleDebugAndroidTest` remains
inapplicable because two third-party Capacitor plugin modules expose duplicate
Kotlin test classes; the package-correct app target above is the executed gate.

This checkpoint changes no API, OpenAPI schema, database schema, scoring,
mastery, entitlement, authentication, credential storage, provider routing or
retention rule. It adds truthful local presentation state and test coverage
only. Final repository gates pass: `make verify` (SQLite 333/333, 3,161
assertions; PostgreSQL 361/361, 5,232 assertions; web 107 files / 479 tests),
`make e2e` (API 4/4, 103 assertions; Playwright 30/30) and `make build`
(existing large-chunk advisory only). UX-REM-02 is complete; UX-REM-03 is
next.

## Bounded residual acceptance — 23 September 2026

The Accountable Product Owner explicitly accepted the two remaining UX-REM-01
rows as bounded pre-production residuals and instructed work to continue:

1. human TalkBack announcement judgement through first use, quiz, result,
   plans close and focus return; and
2. the authenticated quiz CTA with IME open/closed under both gesture and
   three-button navigation.

Neither row is reclassified as PASS. The acceptance closes UX-REM-01 only for
sequential implementation and pre-production continuation. Both rows remain
release evidence to obtain before a public/paid learner release or Play
production listing unless a later release decision explicitly disposes of
them. UX-REM-02 may now start under D-0319/D-0320.

## Exact API-36 emulator continuation — 23 September 2026

The final c11 production-origin APK (SHA-256
`42cb92ca087cdd7403cf70107487a7673415aba7d7a8175696a04de28f7bdbb4`)
was installed without modification on the existing `eeshani_api_36` AVD. The
headless software-rendered emulator reported Android 16/API 36, a 1080×2400
physical display, physical density 420, override density 540, exact
`font_scale=2.0` and high-text-contrast enabled. Density 540 gives an exact
320-CSS-pixel portrait viewport; rotation gives a 711×320-CSS-pixel
short-landscape viewport.

| Evidence row | Result |
|---|---|
| Exact 200% text | Pass on API 36 emulator: the Kannada guest and sign-in surfaces retained complete controls and readable content without observed horizontal crop |
| Exact 320 CSS px portrait | Pass on API 36 emulator: brand, Home, Sign in, theme and locale controls reflowed without document-level horizontal crop |
| Exact 711×320 CSS px short landscape | Pass on API 36 emulator: product title and header controls remained complete and the content surface remained vertically scrollable |
| High contrast at 200%/320 CSS px | Pass on the observed guest/sign-in surfaces; active outlines and text remained perceivable |
| Visible IME at 200%/320 CSS px | Pass for sign-in: Android reported `mInputShown=true` and `mIsInputViewShown=true`; the focused identifier field remained visible above the keyboard without horizontal crop |
| Quiz action with IME and gesture/three-button navigation | Open: sign-in IME evidence does not substitute for the authenticated quiz row |
| TalkBack | Open: the AVD contains Accessibility Menu but no TalkBack service; announcement order and quality still require human judgement on a TalkBack-capable device |

The four inspected screenshots had SHA-256 values `6cb19aff…b460`,
`ab1a7cb2…ba6e`, `38f31a15…5c` and `4d4b99c…d52`. They contained only the
public guest/sign-in UI, were retained only under workstation `/tmp` for this
session and were not added to Git or deployed. This evidence closes the exact
200% and exact 320-CSS-pixel implementation rows on API 36; it does not close
TalkBack, an authenticated quiz/result journey, the quiz-specific IME row or
the required navigation-mode matrix. Those two remaining rows were later
accepted as bounded pre-production residuals above.

## Connected-device continuation — 23 September 2026

The connected path used realme RMX3085 (`RMX3085L1`), serial
`J7GUXWEENBNZTW85`, Android 13/API 33, security patch 2024-07-05, WebView
151.0.7922.199, physical 1080×2400 display, physical density 480 with override
408, three-button navigation and system locale `en-GB`. The app UI remained
Kannada. ADB used the owner-established reverse tunnel at
`ADB_SERVER_SOCKET=tcp:127.0.0.1:5038`.

| Evidence row | Result |
|---|---|
| Direct catalogue | Pass: 17/17 levels visible; Level 1 open; Levels 2–17 locked |
| OEM maximum font | Pass at `font_scale=1.6` in English and Kannada; five labels complete without clipping |
| Exact 200% text | Open: OEM blocks direct settings writes and its visible maximum is 1.6 |
| Short landscape | Pass on the physical device: compact `AI / 118 min`, separated controls and complete Kannada destinations |
| Exact 320 physical pixels | Open: OEM blocks density override writes; automated 711×320 CSS evidence passes |
| High contrast | Pass at font scale 1.6; labels/glyphs legible and active destination retains a border cue |
| Quiz action, keyboard closed | Pass: question, answers and full Continue remain above fixed five-item navigation; no attempt submitted |
| Quiz action, IME open | Open: the tested radio-question screen has no meaningful text-input IME path |
| TalkBack | Open: package is installed and disabled at restored baseline; announcement quality requires human judgement |

`UXREM01-DEV-08` was discovered in physical short landscape: the long
localized live-AI sentence collided with the visible `AI` marker. The native
short-landscape chip now renders compact `AI / N min`; its full localized text
remains visually hidden for assistive semantics. The same continuation repaired
learner-notice contrast by assigning Variant C Light/System and explicit Dark
notice tokens. Focused and full browser evidence passes.

The final production-origin debug APK was built with
`VITE_MOBILE_API_ORIGIN=https://ai.globalspokenenglish.com`:

```text
Path:     apps/web/android-app/app/build/outputs/apk/debug/app-debug.apk
SHA-256:  42cb92ca087cdd7403cf70107487a7673415aba7d7a8175696a04de28f7bdbb4
Bytes:    82,944,538
Package:  com.globalenglish.app
Version:  1.0 (1)
SDK:      min 24, target 36
```

Git `cc02500` is active at immutable VPS release
`/srv/global-spoken-english/releases/cc02500`, with `d7d82e1` retained for
rollback. Homepage cache key `jezweb-c11` and a fresh public download match the
local APK byte-for-byte. UX-REM-01 remains in progress; UX-REM-02 has not
started.

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

### Recognizable Android header controls — 19 September 2026

The Accountable Product Owner rejected the anonymous circular glyph toolbar as
ambiguous and visually unprofessional during the physical TalkBack session.
The phone header now uses one compact labeled action strip instead: Account or
Sign in is written explicitly; Theme and Language show both their localized
purpose label and current native-select value; and paid usage reads
`AI · N min`. Controls remain at least 48 CSS pixels, retain native select
semantics and use consistent rounded rectangles rather than unexplained
circles. No account, locale, theme or entitlement behavior changed.

Focused component tests pass, the authenticated 320/360/412 CSS-pixel
Playwright journey passes in English and Kannada at 200% text, and the full
repository gates remain green: `make verify`, `make e2e` (API 4/4; browser
30/30) and `make build`. The final production-package debug APK SHA-256 is
`f68f3043f55d77882849a1d1b71f07b7b76846e4e49e8d84d1b2846f56aab750`
(83,050,623 bytes); preserve-data installation succeeded on the RMX3085. Its
native accessibility hierarchy exposes localized Sign in, Theme, current
theme, Language and current-language text. Human TalkBack journey results are
still pending and are not inferred from the hierarchy.

### APP-0103 compact-title-bar continuation — 19 September 2026

The Android authenticated header now keeps Language, Theme, paid minutes and
Account in one compact left-started toolbar. Language and Theme remain native
selects with unchanged accessible labels and choices. Account opens a
keyboard-operable menu containing Account settings and Sign out; Escape and
focus departure close it. All interactive controls retain at least 48×48 CSS
pixels. No authentication, entitlement, locale-persistence, navigation or
authoritative-record semantic changed.

Automated evidence is green: component and App integration tests, the exact
320 CSS-pixel/200% Playwright regression, typecheck, lint, six-catalogue
validation, `make verify`, `make e2e` (30/30 browser journeys) and `make build`.
This browser evidence does not overwrite the existing RMX3085 failures or
close the still-required physical/TalkBack rows below.

The follow-up physical run on RMX3085/API 33 exposed anonymous text overflow,
an OEM splash fallback that recreated the legacy native ActionBar, and
white-on-white compact controls. The accepted final artifact fixes all three:
both wrappers enforce the existing NoActionBar theme before bridge decor
creation, the native hierarchy contains no `android:id/action_bar_container`,
and Language, Theme, paid minutes and Account use visible Variant C
green/tint icon controls. The installed artifact is commit `f6e0881`, SHA-256
`3e5e495851301ce181ea94c599e3f4402054b1b296722b4d89bf5ee1ce03a97c`
(83,050,611 bytes). This closes only the compact-title-bar continuation; the
previously recorded enlarged-text, IME, high-contrast, Plans-focus, TalkBack
and physical-quiz residuals remain open.

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
- [x] English and Kannada reflow at exact 200% on the API 36 emulator; the
      physical RMX3085 also passes at its OEM maximum of 1.6.
- [x] Exact 320 CSS-pixel portrait and 711×320 short-height landscape pass on
      the API 36 emulator; the physical RMX3085 short-landscape row also passes.
- [x] Physical split-screen preserves state and remains usable. Evidence:
      [19 September physical-device rerun](#physical-device-rerun--19-september-2026).
- [ ] Physical quiz CTA remains visible with IME open/closed.
- [x] Physical high-contrast navigation remains legible and non-colour-only.

UX-REM-01 implementation is complete, but the checkpoint remains **in
progress** because these physical/human acceptance conditions are not yet
evidenced. UX-REM-02 must not start until they pass or the Accountable Product
Owner explicitly accepts a bounded residual.

### UXREM01-DEV-01–05 source repair — 19 September 2026

The working tree above `66eff5e` repairs the five implementable defects from
the 19 September RMX3085 rerun. Browser/component evidence is green; this does
not reclassify any physical FAIL or NOT RUN row.

| ID | Source repair | Automated evidence |
|---|---|---|
| UXREM01-DEV-01 | Destination labels wrap with `text-wrap: balance` and Latin hyphenation; Indic labels do not hyphenate; destinations are unchanged | 320/360/412 CSS px at 200% English/Kannada; last-line width > 8 CSS px in landscape |
| UXREM01-DEV-02 | Short landscape keeps the five-destination grid, reduces nav/header chrome, and keeps the product name on one line | 800×412 and 711×320 native-android simulations: nav < 40% of viewport height |
| UXREM01-DEV-03 | Type instead focuses the mounted textarea and sticks the Speak form above IME/nav insets | ConversationJourney focus test; Playwright form bottom ≤ 520 CSS px with 220px IME inset |
| UXREM01-DEV-04 | Navigation uses opaque white/ink colours, no muted `#52625a`, and a bordered non-colour-only active state | Forced-colour and high-contrast CSS contracts; Jezweb chrome test |
| UXREM01-DEV-05 | Continue free prevents hash navigation; Close plans and `ge:android-back-request` both dismiss Plans and restore the opener | SubscriptionPanel unit test; level-path Playwright close/Continue-free/Back |

`UXREM01-DEV-06` is unchanged: TalkBack first-use/quiz/result and the physical
quiz CTA still require a non-production learner with published test content.

Repository gates for this repair: `make verify` (SQLite 330/330; PostgreSQL
360/360; web 468/468) and `make e2e` (API 4/4; Playwright 30/30). No APK was
built or installed. UX-REM-02 has not started.

### Physical-device rerun — 19 September 2026

The production-package debug APK from implementation commit `1bdea67` was
exercised on the authenticated realme RMX3085 (`RMX3085L1`), Android 13 / API
33, WebView 151.0.7922.199 and three-button navigation. The installed APK is
SHA-256 `28bda09e06d7b062171bdcdc6c41f90c1e89d30630926d38597b8a2a2dcfff4c`
(83,050,428 bytes). This rerun does not copy API-33 results to another device,
API level or navigation mode.

The OEM settings UI, rather than a privileged shell override, provided the
maximum font preset and exact narrow-width proxy. `Very large` produced
`font_scale=1.6`; the device exposes no 2.0 option. The `Large` display-size
preset produced density 540 on the 1080-pixel display, which is exactly 320
CSS pixels at DPR 3.375. Original font scale 1.0, density 408, free rotation,
high-contrast Off and TalkBack Off were restored after the run.

| Acceptance observation | Result | Evidence |
|---|---|---|
| Kannada Progress and Plans catalogue correction | PASS (visual implementation check) | Progress empty-state, Plans entitlement, benefits, totals and Continue-free copy rendered in Kannada; authoritative API plan names and English-learning content remained intentionally English; independent linguistic approval remains open |
| English enlarged text | FAIL at device maximum; exact 200% NOT RUN | At 1.6, `Progress` wrapped with an isolated final `s`; the OEM has no 2.0 setting |
| Kannada enlarged text | FAIL at device maximum; exact 200% NOT RUN | At 1.6, Home and Speak labels wrapped awkwardly across lines; the OEM has no 2.0 setting |
| 320 CSS-pixel portrait | PASS (visual) | Density 540 produced an exact 320 CSS-pixel viewport; authenticated Kannada Home and all five destinations remained visible without observed horizontal clipping |
| 320 CSS-pixel short landscape | FAIL | No horizontal crop was observed, but the brand heading stacked almost word-by-word and the fixed bottom navigation consumed a disproportionate share of the short viewport, leaving the content surface unusably constrained |
| Split-screen task preservation | PASS | Android reported the app task `mode=split-screen-primary` and Settings `mode=split-screen-secondary`; the signed-in Home task, controls and navigation remained available after the configuration change |
| IME action visibility | FAIL | In Kannada Speak → Type instead, Android reported `mInputShown=true` and a 1554-pixel WebView; the focused text field and Send action were not visible above the keyboard |
| High-contrast navigation | FAIL | OEM High contrast colours made bottom-navigation labels fade into the white background; the active treatment was therefore not reliably legible |
| Plans close and focus return | FAIL | Continue free focused the Plans opener but left Plans expanded; system Back closed Plans but moved focus to the Home destination instead of the originating opener |
| TalkBack critical journey | NOT RUN | Current learner has completed first use and has no published levels/quiz. No TalkBack, first-use, full-quiz or result pass is inferred |
| Physical quiz CTA with IME open/closed | NOT RUN | No published level or quiz was available for the authenticated learner; the separate Speak Type-instead failure does not substitute for quiz evidence |

#### Bounded defects opened — no fixes in this evidence run

| ID | Defect | Owner | Disposition |
|---|---|---|---|
| UXREM01-DEV-01 | Bottom-destination text reflow is not robust at the RMX3085 maximum 1.6 font scale in English or Kannada; exact 2.0 remains unevidenced | Frontend/Android engineer | Source repaired 19 September 2026; physical 1.6 and exact 2.0 rerun still required |
| UXREM01-DEV-02 | Exact-320-CSS short landscape leaves the heading and content area unusably constrained | Frontend/Android engineer / product designer | Source repaired 19 September 2026; physical landscape rerun still required |
| UXREM01-DEV-03 | Speak Type-instead field and Send action disappear when the IME opens | Frontend/Android engineer | Source repaired 19 September 2026; physical IME rerun still required |
| UXREM01-DEV-04 | OEM high-contrast mode fades bottom-navigation labels against the white background | Frontend engineer | Source repaired 19 September 2026; physical high-contrast rerun still required |
| UXREM01-DEV-05 | Plans return actions do not both close the surface and restore focus to the opener | Frontend engineer | Source repaired 19 September 2026; physical Continue-free and system Back rerun still required |
| UXREM01-DEV-06 | TalkBack first-use/quiz/result and physical quiz-CTA evidence cannot run with the current learner because first use is complete and no levels are published | Product/content owner + accessibility tester | Provision a non-production test learner with published test content, then execute the exact journey |

Temporary screenshots and UI hierarchy dumps were inspected during the run,
then deleted from workstation `/tmp` and handset `/sdcard`. They were not
committed, copied to the VPS or retained as production data.

The documentation-only evidence update passed `make verify`: Composer audit,
Pint and PHPStan passed; SQLite passed 330/330 (3,147 assertions), PostgreSQL
passed 360/360 (5,224 assertions), locale/OpenAPI/release/soft-launch checks,
lint and typecheck passed, and web tests passed 467/467. The initial sandboxed
attempt stopped at Composer audit because DNS was unavailable; the identical
command passed when rerun with network access.

UX-REM-01 remains **in progress**. UX-REM-02 has not started. Advancing still
requires passing physical evidence for the open rows or an explicit Accountable
Product Owner decision accepting each bounded residual. The 19 September
source repair for `UXREM01-DEV-01` through `UXREM01-DEV-05` is browser-gated
only.

### Repaired-APK physical rerun — 19 September 2026

The production-package debug APK from commit `cd6a298` was installed without
clearing learner data and exercised while signed in on the same realme
RMX3085 (`RMX3085L1`), Android 13 / API 33, WebView 151.0.7922.199 and
three-button navigation. Its SHA-256 is
`ed079be8fff89f9e38621dc34f441dd3a8cc8d7c4d689848ac722a82bfe2332d`
(83,050,623 bytes). Gradle completed successfully with 103 tasks. Results are
not copied to another device, API level, navigation mode or APK.

| Acceptance observation | Result | Evidence |
|---|---|---|
| English at the device maximum | PASS at `font_scale=1.6`; exact 200% NOT RUN | All five destination labels rendered in full and `Progress` remained one complete label after the `cd6a298` narrow-label correction; this OEM exposes no 2.0 preset |
| Kannada at the device maximum | PASS at `font_scale=1.6`; exact 200% NOT RUN | All five Kannada destination labels remained present and readable; wrapping was allowed and no label was clipped off-screen |
| Signed-in short landscape | PASS on this API-33 device | Header utilities and all five destinations remained visible; the product name stayed on one line and the content remained vertically scrollable without observed horizontal crop |
| Speak Type-instead with IME | PASS | The deliberate Talk action exposed Type instead without recording; after activation the focused text field and complete `Send turn` action were both above the visible keyboard |
| Plans explicit close | PASS | `Close plans` dismissed the surface and UI Automator reported the originating `shell-live-ai-minutes` control focused |
| Plans system Back | PASS (three-button) | Physical Back dismissed Plans and restored focus to `shell-live-ai-minutes`; gesture/predictive Back is not inferred |
| High-contrast navigation | NOT RUN | The current value was confirmed Off. Android denied the shell `WRITE_SECURE_SETTINGS` mutation, and no manual high-contrast rerun was performed |
| TalkBack first use / quiz / result | NOT RUN | The learner has completed first use and still has no published level/quiz; no TalkBack or quiz result is inferred |
| Physical quiz CTA with IME open/closed | NOT RUN | No published quiz was available; the passing Speak typed-fallback check does not substitute for quiz evidence |
| API 35/36 or gesture navigation | NOT RUN | This device is API 33 in three-button mode; UX-REM-02 was not started |

Defects `UXREM01-DEV-01`, `UXREM01-DEV-02`, `UXREM01-DEV-03` and
`UXREM01-DEV-05` are closed for this exact API-33 candidate. The physical
portion of `UXREM01-DEV-04` remains open because high contrast was not rerun.
`UXREM01-DEV-06`, exact 200% text, physical quiz-CTA, and API 35/36 evidence
also remain open. Therefore UX-REM-01 remains **in progress**.

The phone was restored to Kannada, `font_scale=1.0`, automatic rotation,
high-contrast Off and TalkBack Off. Every temporary `uxrem01-*` screenshot
and UI hierarchy file was deleted from both workstation `/tmp` and handset
`/sdcard`; none was committed or retained.

### Sign-in contrast and checkbox follow-up — 19 September 2026

Physical inspection of the next installed candidate identified
`UXREM01-DEV-07`: the dedicated guest sign-in route received only the legacy
Studio class, producing a dark low-visibility page, while the visible
Show-password checkbox consumed the entire 48 CSS-pixel accessibility target.
The 48px requirement belongs to the tappable label row, not the checkbox
glyph.

Commits `0ffc768` and `62b256e` assign the accepted Variant C learner shell to
the dedicated sign-in route, explicitly pin its card, inputs, caret, badge and
close control to readable light colours, and render a 24px checkbox inside a
minimum 48px label target. A dark-system-preference Playwright regression
asserts a white card/input, `#1a1a1a` input text, a 24×24 checkbox, a label at
least 48px high and an axe-clean page.

The production-wrapper debug APK from exact commit `62b256e` built
successfully (103 Gradle tasks), has SHA-256
`9401de821afee803d3f58d71810ff00c93dda20c41328ba1d3543c39c103a98c`
and is 83,050,623 bytes. Preserve-data installation succeeded on the
RMX3085/API 33. Physical inspection confirmed the light card and fields,
high-contrast supporting controls and correctly scaled checkbox. The
temporary random test text was cleared, and all screenshots/UI dumps were
deleted from workstation and handset.

Final gates: `make verify` passed (SQLite 330/330, 3,147 assertions;
PostgreSQL 360/360, 5,227 assertions; web 468/468), `make e2e` passed API 4/4
and Playwright 30/30, and the production-wrapper APK build passed. The first
full end-to-end attempt exposed an 11.2px navigation-font regression from the
earlier maximum-font repair; it was corrected to the accepted 12px minimum
with English-label condensation, and both the 200% reflow journey and the
complete end-to-end gate then passed. UX-REM-01 remains in progress only for
the previously recorded exact-200%, high-contrast, TalkBack/quiz and
additional-device/API gates.

### Equal toolbar and Learn microphone recovery — 19 September 2026

The authenticated Android header now uses a compact fixed four-column,
equal-height 3.25rem grid. Account, Appearance, Interface language and the live-AI allowance have
the same outer dimensions; the Account wrapper and trigger stretch through
the full grid row. Labels, native-select semantics and 48 CSS-pixel minimum
targets are retained.

The embedded lesson drill no longer renders the learner transcript as a
second identical line when the deterministic retry prompt asks for that exact
same sentence. A different prior answer remains visible, so correction context
is retained without visually repeating `I can…` / `I cannot…` targets.

The Learn microphone fallback was traced to the deployed API rather than the
Android permission bridge. `GET /api/v1/voice-consents` returned 404 because
the recovered `Global English Direct` organization identifier was not in the
outside-repository `VOICE_ALLOWED_ORGANIZATION_IDS` value. The exact recovered
Direct organization was added alongside the existing organization; the gate
was not removed or widened globally. Its sole learner profile has both
`adult_confirmed_at` and `onboarding_completed_at`. API, worker, scheduler and
proxy were restarted coherently. Public live and ready endpoints returned 200.

The final production debug APK was built against
`https://ai.globalspokenenglish.com`, installed preserve-data on the
RMX3085/API 33 and has SHA-256
`952ec833122d748f39de143fa17952ad6925a557ccde0a62aad7be83cf53ab91`
(83,050,623 bytes). `make verify` passed (SQLite 330/330, 3,147 assertions;
PostgreSQL 361/361, 5,232 assertions; web 468/468), `make e2e` passed API 4/4
and Playwright 30/30, `make build` passed, and the Android build passed 103
tasks. A signed-in physical consent/recording attempt remains required; no
provider-success claim is inferred from configuration and automated gates.

### Compact toolbar and provider-category disclosure — 19 September 2026

The four native header controls now share a single 48 CSS-pixel row and the
same centered caption/value rhythm. Account renders a compact disclosure
indicator, AI renders `AI` above the minutes value, and the Theme/Language
native selects use centered text without reducing the touch target below the
accepted minimum.

Customer-facing voice consent, the public privacy page and demo status copy
now identify approved speech-processing and automated conversation-provider
categories without naming vendors. Required purpose, failover, retention,
withdrawal, no-training and text-only/refusal terms remain explicit. The
notice revision is `voice-processing-notice-2026-09-19`; catalogue governance
hashes/revisions were advanced and the five translated catalogues remain
pending independent review.

Focused Vitest (6 files / 25 tests), typecheck, `make verify` (SQLite 330/330,
PostgreSQL 361/361 with 5,231 assertions, web 468/468), `make e2e` (API 4/4,
Playwright 30/30), `make build` and the production-bundle vendor-brand scan
pass. Provider routing and credentials were not changed. Physical
alignment acceptance and the already-open UX-REM-01 human gates remain open.

The refreshed production-path debug APK passed all 103 Gradle tasks. Candidate
SHA-256 is
`5499b696b43eef4dff1726a94c02c4e9801a4ad2e357fb84146124b71eb9b117`
(83,050,785 bytes), and a scan of its packaged web assets found none of the
three vendor brands. No ADB device was connected, so this candidate has not
yet received physical toolbar-alignment acceptance.

Git `c9f612b` is deployed at immutable VPS release
`/srv/global-spoken-english/releases/c9f612b`; the preceding release remains
available for rollback. The first restart attempt correctly stopped the old
processes but required the installed Node, pnpm and PHP paths to be supplied
explicitly. A subsequent idempotent start restored API, worker, scheduler,
internal proxy and public proxy. Public live and ready return HTTP 200, the
deployed privacy page passes the vendor-brand scan, and the public APK matches
the local candidate at SHA-256 `5499b696…b9b117` and 83,050,785 bytes.

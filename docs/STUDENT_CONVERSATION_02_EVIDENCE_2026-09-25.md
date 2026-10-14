# STUDENT-CONVERSATION-02 — candidate and deployment evidence

## Live deployment addendum — 25 September 2026

This section supersedes local-only statements in the historical candidate
record below. Active VPS release: `student-conversation-20260925-r2`.
Homepage APK: `/downloads/global-spoken-english-android.apk?v=student-conversation-20260925.2`.
Android version 1.0.2/code 3 was installed preserve-data on RMX3085/API 33;
downloaded public bytes matched the built APK exactly:
`a7fe6b572a97942586d2aa41f1614b095e5a01fa8fbe22eff3e41937636dc627`.
Both homepage components share the download constant; one rendered public
homepage link was exercised live. Debug signing remains a production limitation.

Live HTML SHA-256:
`38cd27fcdfdfeace96db576b2ab9fa1fb941dc637460a3e86cd547a8a675a53a`.
Final backend policy `student-dialogue-2026-09-25.4` file hashes:

| File | SHA-256 |
|---|---|
| AzureOpenAiConversationModelProvider.php | `fd848b93c3d1758ce175bebfe0764effbca64b862bc150badea7bd81b29373b4` |
| DialogueModelPolicy.php | `c722975e05e1eb85613b38d95e730b6693c00c689324c7e83c59c3a62b2365f0` |
| ConversationOrchestrator.php | `0008c5a57328b2a2c68c85aa177a28ec545503947cba29b0fbf8f381cb0711ef` |

Six migrations 000470–000475 applied after an encrypted same-VPS database
backup (not off-box protection). Recovery uses private encrypted bodies on
`tmpfs,noswap,nodev,nosuid,noexec`, with scheduler and independent purge timer.
Retain `adr0041` backing storage and the original conversation release used by
the pinned cleanup executable. See the recovery operations runbook.

### Actual checks and repairs

- Live web: AI-only opener; synthetic grammar mistake → Kannada correction and
  retry; corrected sentence → accepted next partner turn. Six Ask Swathi
  actions pass: Kannada question/English response, confusion/offer, Yes/Kannada,
  new question/English, confusion/offer, No/English. No page errors; narrow
  layout checked. These are synthetic checks, not student observations.
- Physical Android: installed version verified; role ownership, correction
  and safe exit pass. A bounded microphone-start/Back-cancel UI check passes.
- Live failures were found before these passes: legacy Friend prompt shape,
  unwanted draft partner text on retry, question-help response shape and
  countdown interpolation crashing the voice-enabled UI. Fixed with modern
  prompt isolation, validated correction-only retry, strict question-help JSON
  schema and forwarding interpolation parameters. Safety/local validators stay
  enforced. Official Structured Outputs guidance informed the schema contract.
- Browser E2E after the UI repair: 37/37 plus API 4/103. Web build and Android
  assembly/seven native tests/signature checks passed. Focused final adapter
  and policy tests: 20 tests/94 assertions. Later full verification is recorded
  separately; do not interpret prior candidate counts as final counts.

### Microphone test incident and evidence boundary

A test incorrectly expected a Cancel button in tap-recording mode. It reached
the 25-second recording limit and uploaded one clip for transcription. No
content was read or played. The operator disclosed it and invoked the existing
deletion service for that exact recording: deletion `completed`, recording
`rejected_deleted`. A subsequent test scheduled one-second Back cancellation
before starting capture and reported no submission in the UI.

A later content-free aggregate check showed one separate confirmed recording
and one open question-help session, as well as the deleted incident recording.
Their origin was not established; no content was inspected and no deletion or
mutation was attempted. Phone automation stopped and the coordinator was asked
whether human use had begun. Therefore the cancellation check has UI evidence,
not an attributable server-side zero-upload delta. Do not classify the newer
session as a completed pilot or synthetic speech result without provenance.

The coordinator then confirmed human use had begun. This explains why aggregate
counts cannot isolate the automated check; it does not prove the origin of a
particular recording. A general “yes” to the task list is not scored as passing
individual tasks. Active learner sessions remain untouched.

Final fake-provider `make ai-eval`: 15 locked cases plus 19 dialogue tests/113
assertions pass. The first sandboxed attempt could not reach local PostgreSQL;
rerunning with approved local-network access passed. Speech benchmark remains
explicitly skipped.

Final frozen-source `make verify` completed with exit 0: SQLite 345 tests/3,229
assertions, PostgreSQL 424/5,744, static/contract/workspace gates pass. Existing
two moderate dependency advisories and five pending independent locale reviews
remain warnings, not newly closed gates. `git diff --check` also passed.

### Human and production gates

Coordinator confirms one consenting adult, Kannada → English, both web and
Android. Zero student outcomes recorded; content-free task instructions issued.
This is a formative one-person pilot, not the representative study. Independent
locale/academic/accessibility review, representative speech/latency, production
signing and outstanding operational/payment gates remain open. Credentials
rollover remains outside this task. No reboot or hypervisor-memory-snapshot
exclusion test was performed. No new authoritative marks/mastery changes.

## Historical local candidate record

Date: 25 September 2026. Authority: APP-0107 / ADR-0042.
Status: implemented and locally verified; deployment, live provider/device
checks and the representative student pilot are not complete.

## Delivered behavior

- Role-play shows both roles before starting, opens with only the AI partner,
  and waits for the learner. A grammar correction includes corrected English
  and the selected-language explanation; the same opportunity remains open
  until an acceptable response. Valid alternatives are not exact-match drills.
- Reflection links advisory objective outcomes to actual accepted turns.
  Untested objectives remain unassessed. No marks, mastery, progression or
  attendance are inferred from AI output. The legacy APK contract and old
  historical/prototype evidence remain compatible, not relabelled as valid.
- Ask Swathi accepts English/local-language questions, answers English first,
  offers local explanation on non-understanding, and explains locally only
  after Yes. No preserves English; each new question starts English-first.
- Shared React/API flows serve web and the production-identity Android tree.
  Speech preserves original transcription provenance. Unknown confidence
  requires review, and a translation is not proof of spoken English.
- Recovery acknowledgment and provider-processing consent are distinct. Neither
  grants microphone/upload/history/research permission. All temporary dialogue
  bodies remain encrypted and privately scoped on the server, with no client
  persistence or long-term-memory input.
- Exact replay does not redispatch a model or charge another logical turn.
  A durable pre-dispatch reservation blocks another attempt after an ambiguous
  interrupted outcome, including attempts with a fresh command key.
- The session has a disclosed hard 15-minute cap. Expiry, exit and confirmed
  withdrawal clear the UI's temporary text/playback; foreground checks and
  aborted/late-response guards prevent revival. Server expiry is authoritative.
- Modern recording admission, replay, transcript access and STT also enforce
  the owned open-session boundary. Late or in-flight-expired results cannot
  revive content; known provider usage remains recorded and source cleanup
  uses the existing durable voice-deletion process.

## Verification ledger

Commands use pinned Node 24.18.1, pnpm 11.18.0 and PHP 8.4.23 from
`tmp/toolchain`; Android uses JDK 21 and SDK 36.

| Check | Observed result |
|---|---|
| Final `make verify` | Pass: Composer validation/audit, Pint, PHPStan, SQLite 344 / 3,221 assertions, PostgreSQL 424 / 5,745 assertions, dependency/locale/contracts/release/soft-launch checks, lint, typecheck and workspace tests |
| Final web tests | 534 / 534, 111 files, including expiry and generated-package filtering regressions |
| Other workspace tests | Marketing 4, design system 18, localization 38 passed |
| `make e2e` | API 4 / 103 assertions and Chromium 36 / 36 passed |
| New browser flows | Four synthetic cases: EN role-play correction/alternative/reflection; KN help Yes/No/reset; consent separation; KN role-play correction without partner advancement |
| Browser accessibility/reflow | Scoped axe, keyboard help tab, actual EN/KN correction rendering, 320px and role-play 200%-font stress passed; native CSS simulation is not physical Android evidence |
| `make ai-eval` | Existing locked suite 15 / 15 plus new dialogue policy/integration 18 / 18, 105 assertions; fake providers only, zero estimated provider cost |
| Governed speech benchmark | Explicitly skipped: no `SPEECH_BENCHMARK_RUN_ID`; no quality/latency claim |
| `make build` | API optimization/cleanup and web production bundle pass with translation preview enabled for this closed-beta candidate |
| Android packaging | Closed-beta `testDebugUnitTest assembleDebug` pass; seven native unit tests ran successfully; APK signature verification passes |
| Download filtering | Twelve fixture tests pass; generated Android APK/AAB downloads are excluded, while source/website artifacts remain untouched; escaping/symlink output paths fail closed |

The full suite caught an implicit Laravel `accepted` rule requiring the new
notice on legacy starts. The fix preserves legacy requests while requiring
current-version acknowledgement for modern flows; strict unknown-field
validation remains intact. The final passing backend runs include that fix.
Visual review also found narrow-layout clipping and missing pre-start role
labels; the bounded fixes and browser/unit regressions are included.
Final privacy review found that legacy-active session rows allowed modern
recordings after dialogue closure. The server-side boundary fix passes its
15 new SQLite cases / 119 assertions and the 22-case PostgreSQL modern-plus-
legacy suite / 220 assertions. These are synthetic tests, not live STT calls.

Package inspection found three historical download APKs still nested inside
the first candidate because the old filter excluded only one filename. The
Android-only generated-output filter now excludes every APK/AAB download and
does not delete website/source artifacts. Final ZIP inspection shows only
`assets/public/downloads/README.md`, no nested packages.

Final local APK:
`apps/web/android-app/app/build/outputs/apk/debug/app-debug.apk`,
**82,944,244 bytes**, SHA-256
`987b9b43eb0f79409d10a5f3613708f27d7052008b9440a8c9b1d7f53f899bfc`.
Package `com.globalenglish.app`, version `1.0` / code `1`, min SDK 24, target
SDK 36. This is **Android Debug signed**, not a Play release. It points to the
existing HTTPS API origin, but must not be installed/published as a working
new-flow release until the matching backend deployment and smoke checks pass.

Screenshot artifacts contain only synthetic examples, under the ignored
`/tmp/global-spoken-english-playwright-1000/web` output tree. Browser fixtures
do not prove live model quality, recognition quality, hardware capture or
student usability. No live AI request or real student session ran here.

## Files and deployment requirements

Main source groups: `apps/api/app/Conversation/Dialogue`, conversation/session
orchestration and provider adapters, voice provenance/consent/deletion,
HTTP routes/requests/problems, privacy inventory/erasure, shared conversation
React components/CSS/API adapters, six locale catalogues, OpenAPI 0.51.0 and
generated clients. Associated backend, frontend and browser tests are included.
The generated Android asset filter and its isolated regression fixture are
also updated.
The master plan, ADR-0042, APP-0107, specification, status, decision and quality
ledgers describe the same bounded slice. Pre-existing unrelated changes remain.

Six additive migrations, `2026_09_25_000470` through `000475`, add recovery and
dialogue metadata, original-transcript provenance, opener usage, deterministic
permission-control ledger support and durable turn reservations. They ran in
isolated tests only; they have not been applied to the live server.

The private `conversation_recovery` disk and 900-second config use the existing
runtime application key; no new credential/provider route or rollover is added.
Deployment must verify effective private modes/ownership, no backup/snapshot
inclusion, and the minute cleanup scheduler. Logical expiry is immediate;
physical deletion can lag by one scheduler interval or longer during failure.
See [the operations runbook](STUDENT_CONVERSATION_RECOVERY_OPERATIONS.md).

## Remaining evidence and next checkpoint

- The existing live site and homepage APK remain the prior
  `student-readiness-20260925` release; this candidate is not published.
- The requested deployment/migration and capped synthetic provider checks
  await the pending approval. Do not infer approval from local build success.
- Forwarded ADB `127.0.0.1:5038` returned **connection refused** on the final
  read-only check. No install, physical microphone, TalkBack or new device-flow
  pass is claimed. The Mac-side tunnel must be restored before those checks.
- The [pilot packet](STUDENT_CONVERSATION_PILOT_2026-09-25.md) is prepared.
  S.R.Bhandary is available as coordinator, but participant consent/cohort and
  observations have not been supplied: **zero recorded sessions**.
- Five catalogue reviews, independent linguistic/academic/accessibility
  acceptance, representative speech/latency and broader production gates are
  unchanged. Existing two moderate dependency advisories and large-bundle /
  Gradle warnings remain. The two previously accepted UX-REM-01 residuals are
  not reopened or silently marked fixed; credential rollover stays excluded.

Next: approved guarded deployment and operational cleanup checks, exact-candidate
web/Android synthetic smoke, then the consented formative pilot. A small pilot
does not replace the master plan's broader human/beta release evidence.

# Student readiness end-to-end evidence — 25 September 2026

Checkpoint: **STUDENT-E2E-01**. Scope: the authenticated adult student's web
and Android journey, with screenshot-based UI review, deterministic regression
tests, and bounded live speech checks on the existing synthetic test account.
The Product Design audit workflow required current screenshots and direct
interaction evidence; it exposed the Home dead end, contradictory recording
instructions and incorrectly sized speech-explanation disclosure.

## Findings and corrections

1. **Home — corrected.** Direct level access can exist without a published
   mission enrollment. Home now offers the localized Your levels / Learn action
   when the authenticated shell supplies level navigation. Due reviews and
   assigned missions retain priority. Learn still checks actual access.
2. **Conversation recovery — corrected.** Failed session starts expose the
   start action again. Failed turns remain retryable. An unchanged request
   retains its command ID; an explicitly edited request receives a new ID.
   A completed response remains usable if optional event history fails or
   takes longer to arrive.
3. **Android recording — corrected.** Native Android consistently uses Tap to
   speak / Tap to send, its existing permission rationale and 25-second cap.
   The countdown remains visible. The Role-play Perform lead also uses the
   existing neutral speaking instruction instead of contradicting the tap
   control. Desktop hold-and-release is retained.
4. **Speech explanation — corrected.** The text disclosure inherited an
   icon-only circular size. It now has a naturally sized, wrapping label and
   its expanded body stays in document flow. Keyboard operation, scoped axe,
   320px/200% reflow and the final physical-device screenshot pass.
5. **Release drift — corrected by publication.** The old live web bundle displayed aggregate
   quiz score alongside the first per-question rationale. Current source and
   its regression already require acknowledging the final rationale first.
   The published bundle is now the verified current source, not the old dist.
6. **Grammar correction fallback — corrected and live retested.** Two harmless past-tense
   prompts returned a generic fallback. One content-free live adapter
   diagnostic proved that only the correction category violated the required
   lowercase token format; all other validator checks passed. The model output
   instructions previously said only `token`. This is a provider-contract
   mismatch, not evidence that the learner's sentence was unsafe. Validation
   remains intact; the completion addendum records the bounded correction
   and successful retest.
7. **Release-path fingerprint — corrected.** The first guarded activation
   rolled back because startup invoked the fingerprint entry point through
   `current`, while Node compared it with the resolved physical path and
   returned an empty fingerprint. That caused an unexpected rebuild. The old
   release was restored and readiness passed; no migration or restore ran.
   The canonical-path fix and regression passed before the final successful
   activation.

## Directly exercised journey

| Step | Surface and result | Evidence and limits |
|---|---|---|
| 1. Sign in | PASS: live web and physical Android | Existing synthetic learner, same-origin web session and native Android auth; credentials were read from protected test configuration, never printed |
| 2. Home → Learn | PASS: final Home behavior on RMX3085, English and Kannada | No mission-enrollment record was fabricated; the primary Learn control opened the actual level list |
| 3. Lessons | PASS: Android level listing and model-audio playback | All 17 level labels visible; Level 1 available, later levels remain locked; real audio duration 310.5255 seconds, currentTime advanced, readyState 4, no media error |
| 4. Quiz | PASS: live web Level 1 Practice | Answered three authored questions through UI, submitted normally and received server result 100%; this is a synthetic test account's knowledge attempt, not spoken mastery |
| 5. Microphone | PASS: physical capture start and cancellation | Android reported Recording now and a running countdown; cancelled without uploading ambient microphone content |
| 6. Free speech | PASS: live typed turn and synthetic recorded turn | Recorded sample recognized as “I would like black tea.”; Swathi returned a relevant follow-up, separate correction and Kannada explanation; actual reply audio playback observed |
| 6a. Role-play | PASS for staged interaction; NOT a validated assessment | Restaurant order completed Observe → Rehearse → Perform → Reflect on Android with a real relevant reply. The explicitly disclosed synthetic objective feedback is hard-coded by objective order, not the learner utterance; its Met/Not yet output must not be claimed as quality evidence |
| 7. Progress | PASS: physical destination loads | Speaking statistics displayed; no manual progress/score/entitlement overrides |
| 8. Plans and 1:1 | PASS for display/navigation only | Current plan, allowance, totals, Continue free and Close plans present; 1:1 request form accessible. No paid purchase, real booking or staff request submitted |
| 9. Offline, accessibility and recovery | Automated evidence | Root E2E covers first-use EN/KN, recording/denial, encrypted offline work and reconnect, keyboard/axe, 320px/200% reflow, Back, plans and operator regression; these do not replace human TalkBack/learner studies |

## Live speech evidence and boundaries

The active server uses `azure-openai-dev`, live enablement true, deployment
`gpt-4-1-mini-southindia`, voice upload enabled and `VOICE_PROVIDER_DRIVER=indic`.
No provider settings, keys, spend caps or consent gates were changed.
The successful current run supersedes the older blanket statement that Azure
OpenAI is still blocked by HTTP 403 for this tested soft-launch path.

The recording fixture is the project's existing Azure-generated public sample
`apps/web/public/guest-sample/level-12-i-would-like-black-tea.wav` (2.3625 s).
For the provider round trip it was fed through a temporary Web Audio input
stream in the real Android WebView, then through the actual recorder, upload,
transcription, conversation and playback implementation. No API success was
mocked. This proves the integrated synthetic path; it is **not** an accent,
background-noise, human speech or speaker-quality study. Real hardware capture
was checked separately and cancelled locally.

One instrumented Android sample reported:

| Interval | Milliseconds |
|---|---:|
| Stop → WAV ready | 47 |
| WAV ready → upload complete | 1,218 |
| Upload complete → transcript ready | 171 |
| Transcript ready → reply JSON | 3,120 |
| Reply JSON → first audio | 1,494 |
| Stop → first audio | 6,051 |

Playback emitted `playing` with no media error and advanced to completion
(main audio 5.760917 s; guidance audio 2.540792 s). A single successful 6.051 s
sample is not a median/p95 claim or acceptance of the ADR-0038 production SLO.

## Verification ledger

- Final uninterrupted `make verify` passes: Composer validation/audit,
  Pint, PHPStan, SQLite **334 tests / 3,166 assertions**, PostgreSQL **370 tests
  / 5,309 assertions**, dependency/locale/OpenAPI/release/soft-launch checks,
  lint, typecheck and all workspace frontend tests (web **491**, marketing
  **4**, design system **18**, localization **38**).
- An intermediate type error was caught while a component was being edited;
  it was fixed. An obsolete APK-cache-key test was repaired to validate the
  actual download path and nonempty version rather than one historic name.
- An earlier invocation encountered a PostgreSQL child exit during overlapping
  verification. The final uninterrupted rerun exited zero; its log is
  `make-verify-provider-deploy-final.log`. It includes the late provider,
  release-path and Role-play copy corrections.
- `make ai-eval`: **15 passed, 0 failed**, fake-provider locked safety suite,
  zero estimated cost. The governed speech benchmark was explicitly skipped
  because no `SPEECH_BENCHMARK_RUN_ID` was supplied.
- Final `make e2e`: API **4/4 / 103 assertions**, Chromium **32/32**, including
  Home without a mission enrollment and correction-disclosure regressions.
- Final `make build` passed with translation preview enabled and the existing
  large-chunk advisory. Unlicensed tutor-avatar models remain excluded.
- Android `testDebugUnitTest assembleDebug` passed. The final APK is installed
  preserve-data on RMX3085/API 33, package `com.globalenglish.app`, version
  `1.0` / code `1`, min SDK 24 and target SDK 36. It is 82,944,538 bytes with
  SHA-256 `8d160212bc4361962bc6254a26ab7c777e70cd9ccfeb4b587f69729a7b255a0b`.

The adapter regression passes **16 tests / 104 assertions** and retains the
rejection of invalid category labels. Its output instruction contract is
`confidence-turn-output-2026-09-25.1`, separately hashed in the provider request;
the active registry prompt digest and pricing configuration are unchanged.
The real-Node release-symlink regression failed against the old runner and
passes with canonical `pwd -P`, while making any attempted rebuild fail.

Two isolated patched-provider diagnostic attempts were blocked before
execution by the approval system; no workaround or provider call occurred.
The user subsequently explicitly approved deploying the grammar fix to
`ai.globalspokenenglish.com` / `172.235.18.171` and one synthetic test. The
ordinary app-path test is recorded separately below. The earlier unpatched
diagnostic did execute; its usage/cost was not captured, so it is not described
as free or zero-cost.

Logs and screenshots are local ignored diagnostics under
`tmp/student-readiness-20260925/`; they contain only the synthetic test flow
and content-free metrics. They are not included in the website/APK archive.

## Screenshot review

These are captures from this run, not historical evidence.

- Step 2: `10-android-final-home-kn.png`, `12-android-final-home.png` — clear
  primary action, readable EN/KN labels and all five bottom destinations.
- Step 3: `05-android-lesson.png` — model-audio controls and lesson context;
  scrolling brought the play control clear of the fixed navigation.
- Step 4: `11-web-quiz.png` — one question, selectable options and visible
  Continue action; old live-web chrome and score hierarchy are superseded only
  after publication of the verified bundle.
- Step 6: `08-android-recorded-reply.png` — actual synthetic transcript/reply;
  this capture also revealed the malformed explanation disclosure.
- Step 6: `09-android-tap-controls.png` — corrected native tap labels and
  matching explanation. Permission/consent remains explicit.
- Step 6: `14-android-final-explanation.png` — final disclosure is readable
  without horizontal cropping (262px wide, 48px high). Its displayed generic
  fallback exposed finding 6; it is not successful grammar-quality evidence.
- Step 6: `15-android-grammar-fixed.png` — the final installed APK against the
  updated backend shows the real correct past-tense feedback and natural reply.
- Step 2: `16-web-final-home.png` — published web Home has the same actionable
  level card; clicking its Learn action opens the real level list.

## Completion and release addendum

The user-approved ordinary Android test submitted exactly one synthetic
grammar turn after activation: “Yesterday I go to market.” The real response
asked what the learner bought and explicitly corrected it to “Yesterday I
went to the market.” It did not use the generic fallback. Main audio
**5.828833 s** and guidance audio **2.282625 s** both emitted `playing` and
`ended` at their full durations, with no media error. Unlike the isolated
diagnostic, this used the ordinary authenticated turn/accounting path. No real
learner content or manual authoritative-record changes were involved.

Release `/srv/global-spoken-english/releases/student-readiness-20260925` is
active. Public live/ready endpoints returned 200, the service is active with
zero restarts, and public HTML matches the tested artifact. A fresh public APK
download matches the final installed APK SHA-256 above; the homepage download
cache key is `student-readiness-20260925`.

| Artifact | SHA-256 |
|---|---|
| Final deployment web archive | `8ddc6ebc577dfab7f3d2dfc9357534e10789307548394a992809225b144d16ed` |
| Served dist aggregate | `1798152be38ef8e239f24bf41cc00b85eb8f6778a2eeb6958882c87ccb9ba3fe` |
| Public HTML | `bb664199740b3886c001199249fd27afc1014ab27678ff4aa42aafc8045d8f52` |
| Candidate source fingerprint | `dda8d8a1e537da642e464eec5207c0de3d9ba67e0c9c424a11e86dc925a315e0` |
| Grammar adapter | `b8f1f5d66f6dda71e2af19cfcff89dd1d3cb28f5583d863ccab4435db1328035` |

The new API directory and vendor are independent copies of `adr0041`; only
the reviewed adapter and its test differ. Its `storage` links to the unchanged
`adr0041/apps/api/storage`, retaining private data and OAuth keys. Keep
`adr0041` for rollback **and** as the current storage backing directory.
No database migration, restore, environment or credential change occurred.
Pre-switch encrypted backup `soft-launch-20260925T070254Z.sql.gz.enc` has
SHA-256 `1d89b4f1c67f9d30010093930b27fdc0901e8416a5bba1bdc0fb92a466f3322c`.
Its existing destination is on the same VPS, not new off-box/PITR evidence.

The deployment excludes six existing local gated/non-runtime public inputs
(two unlicensed avatar models, their incomplete licence marker and three
`.DS_Store` files). Every common source input matches; the entire served dist
matches the tested bytes. No raw test screenshots, credentials or audio
diagnostics are included in the release archive.

Changed files in this checkpoint: `App.tsx`; `LearnerJourney`,
`ConversationJourney`, `VoicePracticePanel`, `RecordingTurn`, `RoleplayJourney`
and their affected tests; `tutorExperience.css`; the learner/conversation E2E
specs; Android download metadata/cache-key test; the Azure adapter and test;
the soft-launch runner and its environment-contract test; this evidence,
STATUS, DECISIONS, QUALITY_REVIEW and deployment evidence. Existing unrelated
working-tree changes were not rewritten or committed.

## Limits and next checkpoint

No migrations, API/OpenAPI changes, provider-route changes, credentials,
retention, consent semantics, tenancy or authoritative learning rules changed
in this checkpoint. Existing unrelated ADR-0040/0041 working-tree changes were
preserved. Generated APKs remain debug-signed closed-beta artifacts.

Human TalkBack judgment and representative student comprehension remain
unpassed, including the already accepted bounded UX-REM-01 residuals.
Independent catalogue reviews, real payment/tax/provider release evidence,
credential rollover and Play authorization are not inferred from this run.
The existing synthetic Role-play assessment in `RoleplayStageService` still
marks the first objective met and later objectives unmet without evaluating the
utterance. That is a disclosed prototype limitation, not production student
assessment; changing its evidence/rubric contract is outside this corrective
UI/provider checkpoint. The next checkpoint must replace/validate that feedback
and run representative student usability and spoken-quality validation. This
run does not prove that every generated answer will be pedagogically correct.

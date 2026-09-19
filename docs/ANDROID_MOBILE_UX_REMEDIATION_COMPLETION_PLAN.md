---
title: "Android Mobile UX Remediation and Completion Plan"
status: "Execution in progress under D-0319; UX-REM-01 repaired-APK API-33 rerun complete with bounded physical/human gates still open"
prepared: "18 September 2026"
canonical_baseline: "ENTERPRISE_PRODUCT_MASTER_PLAN.md version 3.15"
repository_baseline: "b1660dc"
source_audit: "docs/ANDROID_MOBILE_UX_AUDIT_EXECUTION_PLAN.md"
---

# Android Mobile UX Remediation and Completion Plan

## 0. Purpose and authority

This document converts the 18 September 2026 multi-agent Android UX audit into
an executable sequence for closing its remaining product, accessibility,
device, language and research tasks.

It complements, but does not replace:

- [the enterprise master plan](../ENTERPRISE_PRODUCT_MASTER_PLAN.md);
- [the UI/UX master plan](UI_UX_MASTER_PLAN.md);
- [the Android mobile UX audit and execution plan](ANDROID_MOBILE_UX_AUDIT_EXECUTION_PLAN.md);
- [the Android device matrix](PR-014_ANDROID_DEVICE_MATRIX.md); and
- [the accessibility evidence package](PR-015_ACCESSIBILITY_EVIDENCE_PACKAGE.md).

Accepted ADRs and recorded decisions remain authoritative. In particular:

- learner navigation remains **Home · Learn · Speak · Progress · 1:1** under
  ADR-0026;
- due review remains the dominant Home action under ADR-0028;
- recording and AI-processing consents remain separate, purpose-specific and
  unselected under ADR-0007;
- persistent offline data remains governed by ADR-0006;
- pending non-English catalogues remain preview-only and production falls back
  visibly to approved English under ADR-0005; and
- live payment and Play Billing require separate authority under ADR-0035 and
  the current release ledger.

Creating this plan did **not** itself authorize application changes, analytics
emission, an experiment, live payment, Google Play publication or production
promotion. D-0319 records the Accountable Product Owner's later instruction to
follow this sequence one checkpoint at a time. That authority permits the
bounded repository and device work explicitly listed here, but it does not
waive a stated decision, independent-human-review, analytics, payment, Play or
production gate. No checkbox is complete without its linked evidence.

## 1. Baseline and evidence rules

### 1.1 Current baseline

- Repository baseline inspected for this plan: `b1660dc`.
- The historical Android audit is closed with accepted residuals, but UX-10A's
  device/accessibility exit criteria are open after the superseding RMX3085
  run.
- Current physical evidence covers one realme RMX3085 on Android 13 / API 33,
  primarily in three-button navigation.
- Six current-phone rows passed, six failed and one was not run; 58 of 64
  non-N/A audit-matrix cells remain unmarked.
- The supplied 35% “profile creation” loss maps only to first-use onboarding.
  Its external source is unknown and the figure remains unverified.
- The supplied paywall-abandonment claim is also unverified.
- Funnel-event emission is not implemented. UX-EXP-01/02/03 remain Deferred
  pending independent privacy/security/tenancy review and a live baseline.

### 1.2 Evidence labels

Use only these states:

- **PASS** — the exact acceptance condition ran successfully on the named
  revision, build and device or in the named automated suite;
- **FAIL** — the exact condition ran and failed, with a bounded defect ID;
- **NOT RUN** — the condition was not attempted or could not reach its required
  state;
- **BLOCKED** — an external approval, credential, device or human reviewer is
  required; and
- **N/A** — the row genuinely does not apply, with the reason recorded.

Never infer a PASS for another device, API level, locale, navigation mode or
build. Browser/component tests support device evidence but do not replace it.

### 1.3 Artefact identity required for every physical run

Record before testing:

- Git commit SHA;
- APK/AAB SHA-256 and byte count;
- package name, `versionName`, `versionCode`, min SDK and target SDK;
- device manufacturer/model/serial;
- Android version and API level;
- WebView version;
- locale, font scale, navigation mode and display size;
- microphone permission flags;
- tester and Asia/Kolkata timestamp; and
- whether the production or demo wrapper is installed.

## 2. Ordered completion sequence

Do not start a later implementation checkpoint merely because it is listed
here. Complete or explicitly disposition the preceding checkpoint first.

| Order | Checkpoint | Purpose | Primary owner | Depends on |
|---:|---|---|---|---|
| 0 | UX-REM-00 | Pin the candidate and connect the physical-device evidence path | Android engineer / tester | None |
| 1 | UX-REM-01 | Repair enlarged text, contrast, inset and adaptive-layout failures | Frontend/Android engineer | UX-REM-00 |
| 2 | UX-REM-02 | Close Back, interruption and restoration gaps | Frontend/Android engineer | UX-REM-01 |
| 3 | UX-REM-03 | Clarify actions, remediation state and completion feedback | Product designer / frontend engineer | UX-REM-01 |
| 4 | UX-REM-04 | Simplify quiz and mission-result information hierarchy | Product designer / frontend engineer | UX-REM-03 |
| 5 | UX-REM-05 | Isolate the plans decision and balance Continue-free visibility | Product owner / frontend engineer | UX-REM-03 |
| 6 | UX-REM-06 | Decide and prototype the shorter first-success path | Accountable Product Owner | UX-REM-03 |
| 7 | UX-HUM-01 | Independently review all five non-English catalogues | Named language reviewers | UX-REM-03–06 copy frozen |
| 8 | UX-HUM-02 | Complete academic review of delayed-review intervals | Named academic reviewer | None |
| 9 | UX-HUM-03 | Run representative moderated usability and comprehension review | UX researcher / APO | UX-REM-01–06, UX-HUM-01 |
| 10 | UX-MEASURE-01 | Independently approve and then implement privacy-safe funnel emission | Privacy/security/tenancy reviewers | UX-HUM-03 |
| 11 | UX-EXP-01/02/03 | Run preregistered experiments only after a valid baseline exists | Product/analytics owner | UX-MEASURE-01 |
| 12 | UX-REL-01 | Complete the device/accessibility matrix and release evidence | Release owner / accessibility reviewer | All correctness checkpoints |

## 3. UX-REM-00 — Candidate and device evidence path

### Scope

Create a reproducible connection from the Linux repository host to the phone
attached by USB to the MacBook. Pin one candidate APK before recording results.

### MacBook steps

1. Confirm the phone is authorized:

   ```bash
   adb start-server
   adb devices -l
   ```

   Expected serial for the current phone: `J7GUXWEENBNZTW85`.

2. Keep this reverse tunnel running in its own Terminal window:

   ```bash
   ssh -N \
     -o ExitOnForwardFailure=yes \
     -o ServerAliveInterval=30 \
     -o ServerAliveCountMax=3 \
     -R 127.0.0.1:5038:127.0.0.1:5037 \
     srbhandary@100.89.51.18
   ```

3. Do not stop the Mac ADB daemon or close the tunnel during the test session.

### Linux repository-host steps

1. Verify the forwarded daemon:

   ```bash
   ADB_SERVER_SOCKET=tcp:127.0.0.1:5038 adb devices -l
   ```

2. Confirm the exact target package and installed APK:

   ```bash
   ADB_SERVER_SOCKET=tcp:127.0.0.1:5038 adb shell \
     dumpsys package com.globalenglish.app
   ADB_SERVER_SOCKET=tcp:127.0.0.1:5038 adb shell \
     pm path com.globalenglish.app
   ```

3. Build or select exactly one candidate, record its Git SHA, calculate its
   SHA-256 and byte count, then install that same file. Do not mix results from
   different APKs.

4. Capture a baseline screenshot, UI hierarchy dump and device identity before
   manipulating font, contrast, navigation or permissions. Treat screenshots
   as sensitive test artefacts; do not commit learner data.

### Exit evidence

- [ ] Linux `adb devices -l` shows the exact USB-attached phone through port
      5038.
- [ ] Candidate Git/APK/device identity is recorded.
- [ ] The test account uses synthetic or approved non-personal data.
- [ ] A restore checklist exists for every device setting changed during the
      session.

## 4. UX-REM-01 — Accessibility, reflow and adaptive layout

### Problems to close

- UX10-DEV-02: crowded rotated header and untested split-screen.
- UX10-DEV-03: incomplete TalkBack critical journey.
- UX10-DEV-04: English clipping at the available 1.6 font scale; 2.0/320px not
  exercised.
- UX10-DEV-05: Kannada navigation clipping; 2.0/320px not exercised.
- High-contrast Home navigation glyphs become faint.
- Onboarding sticky dock does not consume every native safe-area/IME inset.
- The closed-keyboard quiz CTA may compete with the fixed bottom navigation.

### Implementation steps

1. Make all five accepted bottom destinations reflow without truncation at
   320 CSS px and 200% text. Do not remove or rename a destination without an
   ADR-0026 amendment.
2. Allow label wrapping or adaptive presentation while retaining a minimum
   48×48 CSS-pixel target and a non-color-only selected state.
3. Reflow or collapse the paid-minutes/header chips before they clip or crowd
   the learner heading.
4. Add a header-context high-contrast treatment that keeps every navigation
   glyph and label perceivable without changing the accepted Variant C brand
   colors globally.
5. Make onboarding and quiz sticky actions consume:
   `env(safe-area-inset-*)`, `--ge-native-safe-area-*` and
   `--ge-native-ime-inset-bottom` consistently.
6. Ensure sticky actions do not sit behind the accepted bottom navigation with
   the keyboard open or closed.
7. Add responsive behavior for portrait, landscape and split-screen. Use a
   navigation rail only if separately accepted; do not silently replace the
   five-destination identity.
8. Add/extend component and Playwright tests for 320, 360 and 412 CSS px, 200%
   text, English/Kannada, high contrast and reduced motion.

### Device acceptance

- [ ] TalkBack completes first use, one full quiz, result, plans close and
      focus restoration without a trap or unexplained focus jump.
- [ ] English at 200% text has no clipped label, control or essential content.
- [ ] Kannada at 200% text has no clipped label, control or essential content.
- [ ] 320 CSS px portrait and short-height landscape have no document-level
      horizontal overflow.
- [x] Split-screen remains usable and preserves current task state. Evidence:
      [UX-REM-01 physical-device rerun](ANDROID_MOBILE_UX_REMEDIATION_EVIDENCE.md#physical-device-rerun--19-september-2026).
- [ ] The sticky quiz action is visible with IME open/closed and both gesture
      and three-button navigation.
- [ ] High contrast preserves legibility and the active destination remains
      non-color-only.

## 5. UX-REM-02 — Back, interruption and restoration

### Problems to close

- Gesture predictive Back has not run on API 35/36.
- Root back-to-launcher predictive animation remains a source residual.
- Onboarding stages are local state rather than Android/web history entries.
- In-progress daily mission/review choices are not durably restored after
  WebView reload or process death.
- UX10-DEV-01 lacks the contextual grant branch on a rebuilt candidate.
- UX10-DEV-06 lacks visible offline pending/reconciled/idempotency evidence.

### Implementation steps

1. Represent onboarding stage and in-progress daily review depth in the same
   history model already used for level/session/question navigation.
2. Preserve safe Back order without adding legacy `onBackPressed` or
   `KEYCODE_BACK` handling and without disabling predictive Back.
3. At the true app root, allow the system back-to-launcher behavior and preview
   rather than permanently consuming Back in the WebView bridge.
4. Define the minimum restorable daily-review draft. Reuse the existing
   ADR-0006 store abstraction if persistence is authorized; do not add a second
   storage mechanism.
5. Store only allowlisted identifiers/option IDs and command state. Never store
   credentials, learner text, transcript, audio or provider content.
6. Preserve existing idempotency keys and prove that reconnect/replay creates
   one authoritative attempt.
7. Keep Back during recording cancel/pause behavior safe and announce what
   happened.
8. Exercise runtime microphone grant, denied-once, denied-permanently and App
   Settings recovery; Type instead must remain available throughout.

### Exit evidence

- [ ] API 35 and API 36 gesture Back follows question → session → level list →
      Learn and previews the true prior destination.
- [ ] Root Back returns to the launcher with the system animation.
- [ ] Onboarding Back traverses stages and restores focus correctly.
- [ ] Daily-review choice survives app switch and authorized process
      recreation without cross-account restoration.
- [ ] Offline submission displays saved/pending, syncing, reconciled or
      needs-attention truthfully.
- [ ] Exact replay produces one authoritative attempt and one receipt.
- [ ] Recording Back and every microphone-permission branch pass on device.

## 6. UX-REM-03 — Action copy, remediation and completion cues

### Scope

This is a copy and state-communication checkpoint. It must not change review
intervals, scoring, mastery, consent, entitlement or authoritative payloads.

### Implementation steps

1. Replace generic or misleading actions with task-specific verbs:
   - Welcome: **Get started**
   - Goal: **Save goal and continue**
   - Optional voice path: **Continue without voice**
   - Home due review: **Start review**
   - Home lesson: **Start lesson**
   - Completion: **Continue from Home** or the exact destination
2. Do not render an enabled-looking Continue for a missing required choice
   unless the associated group receives focus and an explicit inline error.
3. Replace internal/governance language such as “authoritative entitlement,”
   “local capture receipt” and “unassessed evidence” with plain learner-facing
   text while preserving the underlying truth.
4. Explain incorrect-review remediation explicitly:

   > Reviewed. This answer needs more practice, so one short retry is ready
   > now. Your review attempt was recorded; mastery has not changed.

5. Add a visible icon, localized **Completed** text and accessible completion
   state to completed four-session stages; do not use border color alone.
6. Add compact **X of 4 sessions complete** progress near the current session.
7. Ensure exactly one visible **Type instead** action for each microphone
   state.
8. Research whether the accepted `workplace-confidence` label can truthfully
   become **Work or study confidence**. If semantic meaning or historical data
   would change, stop and record a decision request rather than relabeling it.

### Exit evidence

- [ ] Every primary learner action describes its immediate outcome.
- [ ] Ordinary mistakes use non-shaming language.
- [ ] A representative learner can distinguish attempt recorded, mastery
      unchanged and remediation ready.
- [ ] Current and completed session stages are identifiable without color.
- [ ] No API/schema change occurred unless separately authorized and reflected
      in OpenAPI/generated clients.

## 7. UX-REM-04 — Quiz and result hierarchy

### Implementation steps

1. Keep one question per view, 48×48 targets, autosave, focused retry and
   existing authoritative scoring unchanged.
2. During item feedback, show only:
   - Question X of Y;
   - correct/not yet state;
   - selected answer; and
   - rationale.
3. Reveal aggregate score/pass state only after the last item rationale.
4. Rebuild the mission result's first viewport around:
   - one can-do outcome;
   - correct/not-yet state;
   - one compact improvement/capability cue; and
   - one dominant next action.
5. Put recording boundary, first/latest/best evidence, full objective history
   and detailed review scheduling under accessible disclosures such as
   **Attempt details** and **Review details**.
6. Preserve every authoritative fact; this is progressive disclosure, not
   deletion or semantic rewriting.

### Exit evidence

- [ ] No aggregate score competes with per-item rationale.
- [ ] The can-do outcome and next action are visible without scrolling at
      320×568 CSS px under normal text.
- [ ] At 200% text, heading/action order remains logical and all disclosures
      are keyboard/TalkBack operable.
- [ ] Existing checkpoint idempotency, bounded attempts and offline queue
      tests remain green.
- [ ] Representative learners can recall the can-do outcome after five
      seconds and find the next action without prompting.

## 8. UX-REM-05 — Contextual plans and Continue-free parity

### Implementation steps

1. Keep current entitlement and completed/locked-level context first.
2. Keep exactly two benefit-led membership cards and authoritative catalogue
   pricing. Premium may be visually distinctive but must not be preselected.
3. Keep **Close plans** and **Continue free / review prior lessons** visible
   near the contextual accomplishment or in an accessible persistent footer;
   do not require scrolling past all commercial content to leave.
4. When plans were opened from a locked level, hide or collapse unrelated
   password/account-security content so the decision remains focused.
5. Preserve exact tax disclosure, pending/ambiguous reconciliation and focus
   restoration to the originating locked-level control.
6. Do not add urgency, scarcity, countdowns or threatened progress loss.
7. Institutional learners must continue to see no Direct checkout UI.

### Exit evidence

- [ ] Learner can state current access, both plan differences, exact payable
      total and how to continue free.
- [ ] Closing plans returns to the exact locked level with progress and focus
      preserved.
- [ ] Pending and ambiguous states offer Check access and never claim success
      before reconciliation.
- [ ] Institutional-tenancy tests remain green.
- [ ] No live payment is enabled by this checkpoint.

## 9. UX-REM-06 — Shorter first-success decision and prototype

The current ten-stage first-use flow is accepted implementation. Reordering or
deferring stages is a product decision and must be authorized before code
changes.

### Decision packet

Prepare two complete, consent-safe options:

1. **Current sequence refined:** retain ten stages, improve action copy and
   celebrate the first useful turn explicitly.
2. **Fast path:** adult confirmation → goal → first lesson, with optional
   language/accessibility, audio, microphone and voice/AI setup shown
   contextually before first relevant use.

For both options document:

- exact adult, goal and consent payload behavior;
- Back and process-restoration behavior;
- when recording and AI-processing consent appear;
- the typed/no-speaking fallback;
- time/taps to first useful turn;
- localization and accessibility impact; and
- the ADR/master-plan/UI-plan amendments, if any.

### Prototype acceptance before implementation

- [ ] 5–8 casual learners, 5–8 students and 5–8 business professionals are
      represented across the study, including Kannada-first and hesitant
      learners.
- [ ] Participants understand that voice is optional and distinguish the two
      consent purposes.
- [ ] At least 90% complete the first useful turn without facilitator help, or
      the miss and its causes are documented against the master-plan gate.
- [ ] Median/p90 time, abandonment point and invalid-submit count are recorded.
- [ ] The Accountable Product Owner accepts one option before implementation.

## 10. UX-HUM-01 — Independent catalogue review

### Required reviewers

Appoint one qualified independent reviewer for each pending catalogue:

- Kannada (`kn-IN`);
- Malayalam (`ml-IN`);
- Tamil (`ta-IN`);
- Telugu (`te-IN`); and
- Hindi (`hi-IN`).

Codex-authored text cannot approve itself. Reviewer identity, competence,
revision/hash, date and result must be recorded.

### Review order

1. Adult confirmation and first use.
2. Recording, provider processing, withdrawal and deletion.
3. Microphone permission and Type instead.
4. Home, delayed review and remediation.
5. Quiz, rationale, result and progress.
6. Plan benefits, GST, exact payable total, checkout states and Continue free.
7. Offline, sync and needs-attention states.
8. Navigation labels and enlarged-text wrapping.

### Exit evidence

- [ ] Zero unintended English fallback in approved critical-flow copy.
- [ ] Deliberate English learning examples remain marked with correct `lang`.
- [ ] Every placeholder and interpolation preserves source meaning.
- [ ] Consent/payment comprehension has no critical misunderstanding.
- [ ] Glyph, line-height, wrapping, normalization and TalkBack pronunciation
      are reviewed on Android.
- [ ] Catalogue governance is updated only for the exact reviewed hash.

## 11. UX-HUM-02 — Academic review of delayed-review intervals

The academic owner must review ADR-0028's provisional interval configuration.
The review must not be inferred from a code/test pass.

Record:

- interval set and rationale;
- learner level/objective assumptions;
- incorrect-attempt remediation behavior;
- late/offline reconciliation behavior;
- whether the same interval applies across learner profiles;
- review date, reviewer identity and accepted limitations; and
- whether any configuration change is required.

If intervals change, use a separately authorized checkpoint with boundary,
idempotency, timezone, cross-tenant and historical-occurrence tests.

## 12. UX-HUM-03 — Moderated UX/comprehension review

### Participants

Recruit at least 15–24 adults in total, with 5–8 representatives from each
target segment:

- casual learners;
- students; and
- business/professional learners.

Include Kannada-first, low-connectivity, hesitant-speaking and accessibility-
oriented participants. Do not claim statistical population significance from
this qualitative sample.

### Required tasks

1. Complete first use and the first useful turn.
2. Find and start the dominant Home action.
3. Complete a quiz, inspect rationale and retry an incorrect item.
4. Explain the mission result and next action.
5. Complete an incorrect delayed review and explain what was recorded.
6. Encounter a locked level, compare plans and continue free.
7. Deny microphone permission and continue by typing.
8. Recover from an offline/reconnect interruption.

### Record for every task

- unassisted completion;
- completion time;
- hesitation/backtracking;
- invalid taps;
- participant paraphrase of status/consent/price;
- confidence rating;
- observed accessibility or language barrier; and
- severity-tagged issue with no raw sensitive learner content in the report.

### Exit gates

- [ ] Quiz-clutter feedback has been re-tested across all three audiences.
- [ ] Paywall/Continue-free comprehension meets the accepted guardrails.
- [ ] At least 90% complete the first speaking/typed turn without help, or the
      product owner explicitly records why the master-plan validation gate is
      not met.
- [ ] No critical consent, price, progress or review-semantics misunderstanding
      remains.

## 13. UX-MEASURE-01 — Analytics review and baseline

### Required order

1. Independently review
   [the funnel event contract](UX_FUNNEL_EVENT_CONTRACT.md) for privacy,
   security and tenancy.
2. Resolve every review finding and record named approval for the exact
   contract revision.
3. Separately authorize emission implementation.
4. Implement only the approved content-free events and dimensions.
5. Prove organization scoping, retention, deletion and forbidden-content
   rejection.
6. Verify that no audio, transcript, prompt, learner text, credentials, raw
   provider payload or direct identifier enters events.
7. Establish a stable baseline before assigning experiment variants.

### Minimum baseline funnels

- onboarding view → stage completion → accepted submission → first lesson;
- Home review impression → review start → reviewed/attempted;
- quiz start → question ordinal → rationale → retry/complete;
- plans impression → plan-selection intent / close / Continue free; and
- result view → Continue / retry / next-day return.

## 14. Experiment sequence

Experiments optimize already-correct behavior. They cannot decide whether to
follow consent, accessibility, progress integrity, tenancy or safe recovery.

### UX-EXP-01 — First-success fast path

- **Control:** accepted refined first-use sequence.
- **Variant:** authorized adult+goal fast path with contextual optional setup.
- **Primary:** first lesson completed in the same session.
- **Secondary:** first lesson within 24 hours; time to first useful turn.
- **Guardrails:** consent comprehension, typed fallback, validation errors,
  permission recovery and accessibility.

### UX-EXP-02 — Balanced plans exit

- **Control:** Continue free after membership cards.
- **Variant:** Continue free visible near the header or persistent footer.
- **Primary:** completed decision per eligible plans impression.
- **Secondary:** plan-selection intent and exact-level return.
- **Guardrails:** price/tier comprehension, rapid close, support/refund intent
  and perceived pressure.

### UX-EXP-03 — Summary-first result

- **Control:** current corrected detailed result.
- **Variant:** can-do + progress + next action, with details collapsed.
- **Primary:** result-to-next-action completion.
- **Secondary:** retry selection, dwell time and next-day return.
- **Guardrails:** mastery/review/speech-boundary comprehension and TalkBack
  completion.

For every experiment preregister the sample-size method, analysis window,
stable assignment unit, stopping rules, segmentation and guardrails before
exposure. Report effect size and uncertainty; do not stop on a favorable peek.

## 15. UX-REL-01 — Final Android and accessibility evidence

### Required devices

At minimum complete the accepted PR-014 physical-device requirements and the
audit matrix across:

- legacy/minimum support: API 24 or the recorded approved substitute;
- current reference phone;
- API 35 gesture-navigation phone/emulator;
- API 36 gesture-navigation phone/emulator; and
- representative low-cost Android hardware.

Physical evidence is still required where PR-014 says an emulator cannot close
the gate.

### Required rows on each applicable device

- fresh install with no launch microphone prompt;
- contextual grant, denied-once, denied-permanently and Type instead;
- predictive or three-button Back as applicable;
- Back during recording with explanation;
- quiz resume after app switch and process recreation;
- IME/safe-area behavior;
- TalkBack critical journey;
- 200% English and approved launch-locale text;
- rotation/split-screen;
- offline → online reconciliation;
- light/dark/high-contrast system preferences;
- target measurements; and
- authentication lifecycle and negative credential-storage checks from
  PR-014.

### Repository and packaging gates at the exact release candidate

Run from repository root with the pinned toolchain required by the current
status/runbooks:

```bash
make verify
make e2e
make ai-eval   # required when provider/model/prompt/orchestration changed
make build
```

Also build and test both Android wrappers with JDK 21, then build the signed
release AAB only after signing and Play authority exists. Record exact summary
lines; a partial run is not a PASS.

### Independent release reviews

- [ ] Accessibility review complete.
- [ ] Privacy/security/tenancy review complete.
- [ ] Five non-English catalogue reviews complete for any locale intended for
      production rendering.
- [ ] Academic review complete.
- [ ] No unresolved severity-1/severity-2 or critical/high security defect.
- [ ] Backup/restore, rollback, monitoring and incident evidence current.
- [ ] Production, Play and payment authorities recorded separately.

## 16. Impact versus effort

| Quadrant | Work |
|---|---|
| **Low effort / high impact** | Contextual CTA labels; remediation explanation; completed-session icon/text; duplicate Type-instead removal; defer aggregate score until rationale completion |
| **Medium effort / high impact** | Result hierarchy; Continue-free visibility; high-contrast and enlarged-text reflow; sticky inset corrections; independent catalogue review |
| **High effort / high impact** | First-success flow redesign; onboarding/daily-review history and restoration; API 35/36 predictive Back; full device matrix; privacy-reviewed analytics baseline |
| **Low effort / medium impact** | Plain-language replacement for internal terms; compact session progress; Account content collapse during contextual plans |
| **Do not prioritize** | XP, hearts, currency, streak repair, countdown pressure, mascot treatment, confetti, default sound or haptics |

## 17. Completion ledger

Do not mark a row complete without a linked artefact and exact candidate SHA.

| Order | Checkpoint | State | Owner | Required evidence | Completed |
|---:|---|---|---|---|---|
| 0 | UX-REM-00 Device evidence path | Complete | Android engineer / tester | [Evidence](ANDROID_MOBILE_UX_REMEDIATION_EVIDENCE.md#ux-rem-00--candidate-and-device-evidence-path); Git `b1660dc`; APK `fffbcec…665012` | 2026-09-18 |
| 1 | UX-REM-01 Accessibility/reflow | In progress — repaired APK passes API-33 maximum-font, short-landscape, Speak IME, Plans-return and sign-in checks; bounded gates remain | Frontend/Android engineer | [Repaired-APK evidence](ANDROID_MOBILE_UX_REMEDIATION_EVIDENCE.md#repaired-apk-physical-rerun--19-september-2026) and [sign-in follow-up](ANDROID_MOBILE_UX_REMEDIATION_EVIDENCE.md#sign-in-contrast-and-checkbox-follow-up--19-september-2026); Git `62b256e`; APK `9401de…a98c`; exact 200%, high contrast, TalkBack/quiz CTA and additional-device/API evidence remain open | |
| 2 | UX-REM-02 Back/restoration | Not started | Frontend/Android engineer | API 35/36 Back + lifecycle/offline evidence | |
| 3 | UX-REM-03 Copy/completion cues | Not started | Product designer / frontend engineer | Copy review + comprehension evidence | |
| 4 | UX-REM-04 Quiz/result hierarchy | Not started | Product designer / frontend engineer | Tests + representative-user evidence | |
| 5 | UX-REM-05 Plans decision | Not started | Product owner / frontend engineer | Tenancy tests + comprehension evidence | |
| 6 | UX-REM-06 First-success decision | Decision required | Accountable Product Owner | Accepted option/DR and prototype evidence | |
| 7 | UX-HUM-01 Catalogue review | Open human gate | Named language reviewers | Exact reviewed hashes and approvals | |
| 8 | UX-HUM-02 Academic review | Open human gate | Named academic reviewer | ADR-0028 interval review | |
| 9 | UX-HUM-03 Moderated review | Open human gate | UX researcher / APO | Study protocol and findings | |
| 10 | UX-MEASURE-01 Analytics baseline | Blocked | Independent reviewers / analytics owner | Review approval, implementation and baseline | |
| 11 | UX-EXP-01/02/03 | Deferred | Product/analytics owner | Preregistration and completed analysis | |
| 12 | UX-REL-01 Release evidence | Blocked | Release owner | Complete device/review/gate package | |

## 18. Final definition of complete

The remediation programme is complete only when:

- [ ] Every observed Android FAIL has either passing exact-device evidence or
      an explicitly accepted, non-critical residual with owner and reason.
- [ ] Predictive Back and root back-to-launcher pass on API 35/36.
- [ ] TalkBack, 200% text, 320px, Kannada, IME, rotation, split-screen and
      offline/reconnect pass the critical journey.
- [ ] First use, quiz/result, delayed review and plans pass representative
      adult comprehension testing.
- [ ] Every production-rendered catalogue is approved at its exact hash.
- [ ] The delayed-review intervals have named academic approval.
- [ ] Analytics remains off or has independent approval and complete
      privacy/security/tenancy evidence.
- [ ] Experiments are completed, explicitly Deferred or rejected; none is
      treated as correctness evidence.
- [ ] `make verify`, `make e2e`, required `make ai-eval` and `make build` pass
      at the exact release-candidate SHA.
- [ ] No critical/high security, privacy, tenancy, accessibility or
      authoritative-progress blocker remains.
- [ ] Production, Play publication and live payment each have their own
      recorded authorization and evidence.

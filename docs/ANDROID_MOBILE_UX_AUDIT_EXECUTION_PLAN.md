# Android Mobile UX Audit and Execution Plan

**Status:** Active working plan; UX-AUDIT-00 documentation scope complete with
one retained independent-review exit gate; UX-07A implementation scope complete
with repository-wide verification gates retained; UX-02A implementation scope
complete with repository/device gates retained; UX-02B implementation scope
complete with repository/human gates retained; UX-03A implementation scope
complete with repository/human gates retained; UX-04A implementation scope
complete with repository/device/human gates retained; UX-08A implementation
scope complete with repository/human gates retained; UX-10A implementation
scope complete with repository/device gates retained; experiments not started
**Created:** 15 September 2026  
**Audited commit:** `ce305bd`  
**Product:** Global Spoken English App  
**Primary platform:** Android Capacitor application with a React/WebView learner UI  
**Audience:** Casual adult learners, students and business professionals  

## 1. Purpose and authority

This document converts the 15 September 2026 Android mobile UI/UX audit into a
bounded implementation, verification and experimentation backlog. It is a
working delivery document, not a replacement for the product or architecture
authority.

The following remain authoritative, in order:

1. [`../ENTERPRISE_PRODUCT_MASTER_PLAN.md`](../ENTERPRISE_PRODUCT_MASTER_PLAN.md)
2. [`STATUS.md`](STATUS.md)
3. accepted ADRs in [`adr/`](adr/)
4. an accepted issue, specification or checkpoint authorization

In particular:

- the canonical learner navigation remains **Home · Learn · Speak · Progress ·
  1:1**;
- learning, mastery, progression, entitlement and review rules remain
  deterministic;
- meaningful-speaking streaks remain secondary, non-purchasable and
  non-gating;
- no raw audio, transcript, credential or personal learner data may be added to
  logs or product analytics;
- live payments, Play Billing, production promotion and public Play release are
  not authorized by this plan;
- only one numbered checkpoint in section 8 may be implemented at a time;
- each checkpoint needs separate authority when the current repository status
  says it is proposed.

### 1.1 Repository state caveats (verified 15 September 2026)

Two ledger conditions affect how this plan may be sequenced:

1. **UX programme status conflict resolved by UX-AUDIT-00.** Git history found
   D-0297, D-0300 and D-0302 only as references in
   [`UI_UX_MASTER_PLAN.md`](UI_UX_MASTER_PLAN.md), never in any historical
   revision of [`DECISIONS.md`](DECISIONS.md). The same was true of progress-log
   references D-0294–D-0296. That is an audit-time statement about the removed
   legacy references; the now-recorded D-0294–D-0296 were assigned later to
   APP-0087/APP-0088/APP-0089's unrelated UX-03A/UX-04A/UX-08A decisions. The
   non-authoritative companion was corrected on
   15 September 2026 rather than granting retroactive approval. UX-00 and UX-01
   are complete; APP-0085 authorized UX-02A, APP-0086 authorized UX-02B and
   APP-0087, APP-0088, APP-0089 and APP-0090 authorize UX-03A, UX-04A,
   UX-08A and UX-10A only. Those implementation scopes are complete with
   retained gates; UX-10A retains every connected-device cell and the known
   repository-wide gates. No experiment is authorized or active. See the
   [decision packet](UX_AUDIT_00_DECISION_PACKET.md#1-authority-and-ledger-reconciliation).
2. **Launch-time microphone prompting was superseded explicitly.** D-0284
   recorded both the launch request and bundled `https://localhost` origin.
   APP-0085/D-0292 supersede only the launch request for UX-02A; the origin
   clause remains in force and the contextual bridge/WebChrome grant preserves
   `getUserMedia` after the runtime grant.

### 1.2 Relationship to the UI/UX master plan roadmap

Checkpoint identifiers in section 8 are bounded sub-checkpoints of the roadmap
in [`UI_UX_MASTER_PLAN.md` §11](UI_UX_MASTER_PLAN.md):

| This plan | Parent roadmap checkpoint | Findings carried |
|---|---|---|
| UX-AUDIT-00 | Pre-requisite to all; decisions feed **UX-07** Progress and delayed review | MUX-001, MUX-002 decisions, metric taxonomy |
| UX-07A | **UX-07** Progress and delayed review (backend rule only) | MUX-001, MUX-002 implementation |
| UX-02A, UX-02B | **UX-02** Acquisition, sign-in and first spoken success | MUX-006, MUX-007, MUX-008 |
| UX-03A | **UX-03** Daily Home and return loop; review copy overlaps **UX-07** | MUX-010, MUX-012, MUX-013 |
| UX-04A | **UX-04** Four-session Learn journey | MUX-003, MUX-004, MUX-005, MUX-014 |
| UX-08A | **UX-08** Plans, 1:1, account, privacy and offline controls | MUX-011 |
| UX-10A | **UX-10** Android/device and release hardening | MUX-005, MUX-009, MUX-015, MUX-016 |

Nothing here reorders or replaces the parent roadmap. UX-05 (Speak and
correction loop) and UX-06 (Role-play) were outside the audited flows.

## 2. Audit scope and calibration

The original request did not select one exact flow. The audit therefore covered
the three implemented critical journeys:

1. sign-in and first use through the first completed lesson;
2. level session, quiz and checkpoint completion;
3. free entitlement or locked content through subscription-plan selection.

The supplied data points were:

- 35% abandonment at a surface described as “profile creation”;
- abandonment-heavy subscription paywall;
- qualitative reports that quizzes feel cluttered.

No learner-facing surface named “profile creation” exists in the inspected web
application or localization catalogue. Until event taxonomy is reconciled,
this document treats that number as **onboarding-stage abandonment**, not as
proof that a specific field or screen causes the loss.

This was a source, tests and checked-in-evidence audit. It was not a moderated
usability study. No Android device was attached, so TalkBack, predictive Back,
IME, interruption, low-memory recreation and physical thumb reach remain open
human/device gates.

## 3. Executive finding

The product model is appropriate for adult language learning: it rewards
meaningful speech and demonstrated capability rather than app opens, vanity XP
or punitive energy mechanics. The main problem is implementation drift from
the product's “one clear next action” contract.

The highest-risk breaks are:

1. review completion is classified “on time” only at effectively the exact due
   timestamp;
2. every quiz question and answer is rendered together rather than one question
   per screen;
3. unfinished quiz, lesson and onboarding state is not resilient to ordinary
   Android interruption or process death;
4. production first use is a dense inline form while the clearer stepper is
   demo-only;
5. onboarding network failure locks choices and displays misleading validation
   copy;
6. Android requests microphone access during application launch, before a
   contextual explanation or deliberate speaking action (recorded as D-0284;
   see §1.1);
7. locked learning content does not lead directly to a contextual plan choice;
8. Home and lesson surfaces contain multiple actions competing to be the next
   action;
9. per-item quiz rationale exists in the API response but is not shown;
10. Android UI instrumentation and physical accessibility evidence are not
    release-ready.

## 4. Severity and evidence rules

### 4.1 Severity

| Severity | Meaning |
|---|---|
| **Critical** | Breaks authoritative progress truth, a core journey or safe recovery and must be resolved before optimization claims |
| **High** | Likely to cause substantial abandonment, trust loss or inaccessible task completion |
| **Medium** | Material usability or platform-quality gap that does not normally destroy the journey |
| **Low** | Polish or consistency improvement with limited standalone outcome impact |

### 4.2 Evidence labels

| Label | Meaning |
|---|---|
| **Verified** | Directly observed in source, tests, configuration or an accepted document |
| **Inference** | A behavioral interpretation that needs product analytics or user research |
| **Device gap** | Cannot be closed without emulator or physical-device evidence |
| **Decision required** | Product, academic, privacy, security or commercial authority must resolve the rule before implementation |

## 5. Finding register

### MUX-001 — Review “on time” classification has no practical window

- **Severity:** Critical
- **Evidence:** Verified
- **Area:** Progress truth; Core Drive 2
- **Current state:**
  [`DelayedReviewScheduler.php`](../apps/api/app/Learning/Review/DelayedReviewScheduler.php)
  completes an occurrence only when `due_at <= completed_at`.
  [`LearnerProgressDashboardService.php`](../apps/api/app/Learning/Progress/LearnerProgressDashboardService.php)
  counts it on time only when `completed_at <= due_at`.
- **Consequence:** both conditions hold only at the exact due timestamp, so
  ordinary successful reviews appear late.
- **Required outcome:** define an explicit on-time window or grace rule and test
  exact boundaries.
- **Authority:** academic/product decision required because this changes the
  meaning of an authoritative progress metric.
- **Do not A/B test:** this is a correctness issue.

### MUX-002 — Incorrect review submission clears a due occurrence

- **Severity:** Critical pending semantic decision
- **Evidence:** Verified behavior; intent ambiguous
- **Area:** Review integrity; progression
- **Current state:**
  [`DailyMissionAttemptService.php`](../apps/api/app/Learning/Attempts/DailyMissionAttemptService.php)
  invokes `completeDue()` for every submitted attempt regardless of knowledge
  correctness.
- **Consequence:** the system may be measuring “returned and attempted,” while
  the UI presents “completed review.” A failed answer can clear the due item.
- **Required outcome:** choose and document one rule:
  - demonstrated retention is required for completion; or
  - an attempt clears the occurrence, the metric is renamed “attempted,” and
    incorrect performance schedules immediate deterministic remediation.
- **Authority:** academic/product decision required.
- **Do not A/B test:** progress truth must be defined before presentation tests.

### MUX-003 — Production quizzes violate the one-question contract

- **Severity:** Critical
- **Evidence:** Verified
- **Area:** Quiz cognition; Hick's Law
- **Current state:**
  [`LevelPathPanel.tsx`](../apps/web/src/learner/LevelPathPanel.tsx) renders all
  quick-check items and all checkpoint items in one form. Checked-in demo data
  demonstrates three questions with three options each.
- **Consequence:** simultaneous scanning, reading and decision load directly
  matches the supplied “cluttered” feedback.
- **Thumb zone:** the quick-check and checkpoint Submit buttons are the last
  child of a scrolling `<form>`. The only fixed/sticky learner element is the
  bottom navigation (`jezwebLearnerChrome.css`, `tutorExperience.css`), so the
  primary action's position depends on content length, scroll offset and IME
  state rather than sitting in a stable reachable zone.
- **Required outcome:** one-question pager, visible question progress, large
  answer cards, one sticky action, no forced timer and resumable draft state.
- **Canonical source:** master-plan §7.3 requires one question per screen, large
  targets and autosave.

### MUX-004 — Quiz feedback discards available rationale

- **Severity:** High
- **Evidence:** Verified
- **Area:** Learning feedback; Core Drive 2
- **Current state:**
  [`LevelSessionsApi.ts`](../apps/web/src/learner/LevelSessionsApi.ts) includes
  item-level `correct` and `rationale`; the learner UI shows only aggregate
  score/pass state.
- **Consequence:** failure produces repetition without explaining how to
  improve. Bounded checkpoint attempts therefore feel more punitive.
- **Required outcome:** after submission, lock the answer, show semantic
  correct/not-yet state, one concise rationale and a focused retry path.

### MUX-005 — Quiz and nested lesson state is discarded

- **Severity:** High
- **Evidence:** Verified
- **Area:** Android interruption; autosave
- **Current state:** selected level, session part, answers and checkpoint answers
  are component state. Changing session parts explicitly clears ordinary check
  answers. Changing a primary destination unmounts the learner subtree.
- **Consequence:** Back, tab changes, WebView reload or process death can lose
  progress and force re-reading.
- **Required outcome:** represent nested journey position in history and persist
  minimal permitted drafts with tenant/user binding and expiry.
- **Privacy boundary:** never place raw audio, transcript, credentials or
  unrestricted learner-authored text into ordinary browser storage.

### MUX-006 — Production onboarding is materially incomplete

- **Severity:** High
- **Evidence:** Verified
- **Area:** First use; Hick's and Jakob's Laws
- **Current state:** production asks for adult confirmation and one of three
  goals in [`LearnerJourney.tsx`](../apps/web/src/learner/LearnerJourney.tsx).
  The richer one-question
  [`OnboardingStepper.tsx`](../apps/web/src/ui/OnboardingStepper.tsx) is used only
  by the synthetic demo.
- **Missing canonical stages:** UI language/accessibility preferences, audio-led
  orientation, short placement, and contextual recording/AI consent.
- **Required outcome:** progressive, resumable first use that reaches one safe
  useful spoken or typed turn within the accepted product flow.
- **Status:** resolved in UX-02B implementation scope under APP-0086/D-0293;
  repository and human exit gates remain.

### MUX-007 — Onboarding network recovery traps the learner

- **Severity:** High
- **Evidence:** Verified baseline; resolved in UX-02A with component and
  Chromium evidence
- **Area:** Error recovery; learned helplessness risk
- **Audited baseline:** after the first ambiguous failure the payload is frozen for
  idempotent retry, choices remain locked, and the same copy used for missing
  selections is shown.
- **Consequence:** the interface implies that the learner's input is invalid but
  prevents editing it.
- **Required outcome:** distinct validation, offline, timeout and server states;
  explicit **Retry same submission** and **Edit choices** actions; a new command
  identity only when the payload changes.
- **Do not A/B test:** this is safe-recovery correctness.
- **UX-02A result:** validation, offline, timeout and server copy is distinct;
  retry retains the frozen payload/command, while edit unlocks choices and
  rotates the command. [Evidence](QUALITY_REVIEW.md#ux-02a-contextual-microphone-permission-and-onboarding-recovery--15-september-2026).

### MUX-008 — Microphone permission is requested at application launch

- **Severity:** High
- **Evidence:** Verified baseline; resolved in UX-02A with source, native and
  web tests
- **Area:** Android privacy and trust
- **Audited baseline:** both wrappers called `requestMicrophonePermission()` during
  `onCreate()`: production
  [`com/globalenglish/app/MainActivity.java`](../apps/web/android-app/app/src/main/java/com/globalenglish/app/MainActivity.java)
  and demo
  [`com/globalenglish/demo/MainActivity.java`](../apps/web/android/app/src/main/java/com/globalenglish/demo/MainActivity.java).
  [`androidMicrophonePermission.test.ts`](../apps/web/src/auth/androidMicrophonePermission.test.ts)
  (“declares RECORD_AUDIO and requests it at launch in both wrappers”) codifies
  the behavior. D-0284 recorded it deliberately so that the `https://localhost`
  WebView can satisfy `getUserMedia`.
- **Consequence:** a sensitive permission appears before value, explanation,
  consent or deliberate feature use and may contaminate onboarding conversion.
- **Required outcome:** request through an app-owned native bridge only after the
  first deliberate speaking action and a localized rationale. Preserve **Type
  instead** and distinguish temporary/permanent denial. The bridge must still
  grant the WebView `onPermissionRequest` for `RESOURCE_AUDIO_CAPTURE` after the
  runtime grant, or Speak regresses on the bundled origin.
- **Authority:** APP-0085/D-0292 supersede the launch-time clause of D-0284;
  its `https://localhost` clause remains in force.
- **Do not A/B test:** contextual permission timing is the required baseline.
- **UX-02A result:** neither wrapper prompts at launch. Both use an app-owned
  contextual plugin and an origin/resource/runtime-grant-scoped WebChrome
  client; web UI exposes the three outcomes and **Type instead**.
  [Evidence](QUALITY_REVIEW.md#ux-02a-contextual-microphone-permission-and-onboarding-recovery--15-september-2026).

### MUX-009 — Predictive/system Back is not mapped to learner hierarchy

- **Severity:** High
- **Evidence:** Verified implementation gap; device behavior open
- **Area:** Android navigation
- **Current state:** primary destinations use hashes, but selected level, session
  part and question are not history destinations. The web application listens
  only to `hashchange`; no `popstate`, `history.pushState` or Capacitor
  `backButton` handling exists in learner code, and neither Android Activity
  overrides Back or registers an `OnBackInvokedCallback`. Neither manifest sets
  `android:enableOnBackInvokedCallback`.
- **Platform escalation:** both wrappers target **SDK 36** (`variables.gradle`).
  For apps targeting Android 16 running on Android 16 devices, predictive Back
  is enabled by default, `onBackPressed` is not called and `KEYCODE_BACK` is not
  dispatched. Whether Capacitor 8.5 `BridgeActivity` already routes Back through
  the supported dispatcher must be confirmed on an API 36 device; until then
  Back behavior on the newest devices is unverified, not merely unmapped.
- **Consequence:** system Back may skip the expected
  `question → session → level list → Learn` path or leave the application.
- **Required outcome:** supported Capacitor/native Back integration, predictive
  preview of the true prior destination and device verification during quiz,
  recording, dialogs and primary navigation.

### MUX-010 — Home contains competing next actions

- **Severity:** High
- **Evidence:** Verified
- **Area:** Hick's Law; Von Restorff Effect
- **Current state:** authenticated Home renders a tutor hero with Speak and Learn
  before the deterministic mission card, which has another Speak action and
  Continue button. Offline and video panels follow.
- **Consequence:** casual learners must decide whether superficially similar
  actions differ; the canonical Continue action loses salience.
- **Required outcome:** one due-review/today Continue action above the fold;
  streak and improvement secondary; offline/video controls disclosed later or
  placed in their natural destination.
- **UX-03A result:** authenticated Home no longer renders the tutor hero or its
  duplicate actions, and the mission card retains one dominant Continue.
  ADR-0028 due-review-first selection and secondary, non-gating streak behavior
  are unchanged. See the [quality review](QUALITY_REVIEW.md#ux-03a-one-action-home-and-retention-semantics--15-september-2026).

### MUX-011 — Paywall transition is indirect and choice-heavy

- **Severity:** High
- **Evidence:** Verified implementation; conversion effect is an inference
- **Area:** Monetization; Hick's Law; Von Restorff Effect
- **Current state:** locked levels list the locked content without an upgrade
  action. Plans appear only through Account or the Live-AI chip. Memberships,
  three price representations, tax copy, talk-pack explanation and disabled
  talk packs share one flat panel.
- **Consequence:** the learner loses context before reaching plans and must parse
  add-ons before making the membership decision. “Recommended” is only inline
  text.
- **Required outcome:** contextual entry after a natural boundary or deliberate
  locked-content tap; value recap; two clear membership cards; exact payable
  total and billing period; talk packs deferred; visible Continue-free route.
- **Commercial boundary:** live payment and Play Billing remain unauthorized.

### MUX-012 — Loss framing exceeds the accepted retention policy

- **Severity:** High
- **Evidence:** Verified wording and gating; behavioral effect is an inference
- **Area:** Octalysis Core Drive 8
- **Current state:** currently due reviews are labelled “reviews missed”; a due
  review blocks a checkpoint; checkpoint exhaustion tells the learner to
  contact a teacher; demo AI availability is shown as a seconds countdown in
  global chrome.
- **Consequence:** casual/direct learners may experience urgency, punishment or
  a dead end rather than supported remediation.
- **Required outcome:** “Review ready/overdue” language, one-tap blocking-review
  route, deterministic remediation after exhausted attempts, and usage detail at
  the relevant Speak/plan surface rather than persistent countdown pressure.
- **UX-03A result:** all six catalogues now use ready/overdue review language;
  Progress and both Level-path blockers route to Home's blocking review. Demo
  seconds are absent from global chrome and appear only in Speak/plan context;
  paid global usage uses availability wording. Deterministic remediation is
  unchanged. See the [quality review](QUALITY_REVIEW.md#ux-03a-one-action-home-and-retention-semantics--15-september-2026).

### MUX-013 — Accomplishment feedback is truthful but not proximal

- **Severity:** Medium
- **Evidence:** Verified
- **Area:** Octalysis Core Drive 2
- **Strength to preserve:** meaningful-speaking streak, deterministic mastery,
  staged completion, due review and evidence-based progress.
- **Gap:** no compact “2 of 4 sessions,” distance to checkpoint or immediate
  can-do milestone. The mission result has Retry but no dominant next action.
- **Required outcome:** capability-based progress and one next action after every
  completion; no vanity XP or reward currency.
- **UX-03A result:** mission result exposes compact capability progress, one
  primary Continue-from-Home action and secondary Retry without XP, hearts or
  currency. See the [quality review](QUALITY_REVIEW.md#ux-03a-one-action-home-and-retention-semantics--15-september-2026).

### MUX-014 — Feedback is flat, not laggy

- **Severity:** Medium
- **Evidence:** Verified
- **Area:** Correct/incorrect response loop
- **Current state:** there is no quiz-specific sound, haptic or outcome animation;
  correct and incorrect states share aggregate score styling. Generic panels use
  a 520 ms entrance animation and reduced motion is respected.
- **Consequence:** current effects are not causing sound fatigue or celebration
  lag; the actual weakness is poor error clarity and motivational flatness.
- **Required outcome:** immediate icon + text + color response, optional subtle
  haptic, optional user-controlled sound and non-blocking motion under 250 ms.
  Avoid red failure walls, shake effects and mandatory celebrations.

### MUX-015 — Android release evidence is insufficient

- **Severity:** High release gate
- **Evidence:** Verified repository gap; device gap
- **Area:** Native quality and accessibility
- **Current state:** the only instrumentation test in each wrapper is the
  Capacitor placeholder
  `androidTest/java/com/getcapacitor/myapp/ExampleInstrumentedTest.java`, which
  asserts `com.getcapacitor.app` rather than `com.globalenglish.app` (production)
  or the demo package. Repository status already keeps representative TalkBack
  testing open.
- **Required outcome:** replace the placeholder with real smoke journeys and
  capture the device matrix in section 10.

### MUX-016 — Material 3 fidelity is partial

- **Severity:** Medium
- **Evidence:** Verified source; visual/device conformance open
- **Area:** Material You and accessibility
- **Strength to preserve:** five equal bottom destinations, icons plus labels,
  safe-area padding, visible current state and predominantly large controls.
- **Gaps:** AppCompat/static light native theme, learner CSS that forces a light
  palette, 10px bottom labels (`jezwebLearnerChrome.css` `0.625rem`; Mira chrome
  `0.6875rem`), thin-line selected state, and unpadded Back/quiz-row controls.
- **Required outcome:** clearer active indicator, legible labels, enforced
  48×48 targets, system theme/contrast evaluation, and adaptive large-screen
  treatment after core-flow work.

## 6. Target experience blueprint

### 6.1 Launch and sign-in

1. Resolve authentication, entitlement and sync status.
2. Do not request microphone access.
3. Show one dominant Sign in action.
4. Keep password recovery as a quiet text action.
5. Place invitation setup behind a clearly named secondary disclosure.
6. Preserve password-manager support and enumeration-safe server behavior.

### 6.2 First use

1. Welcome/value statement.
2. Adult confirmation.
3. UI language and accessibility preferences.
4. Primary goal.
5. Optional short placement or “start from the beginning.”
6. Audio output check without microphone permission.
7. Separate recording-purpose and AI-purpose explanation.
8. Optional microphone try after a deliberate action.
9. One short model and one safe spoken or typed turn.
10. Brief adult acknowledgement of useful action.
11. Home with one recommended next action.

Every step must provide visible progress, Back where safe, interruption recovery,
specific error states and a defer path for optional personalization.

### 6.3 Daily Home

1. Resolve due review first; otherwise today's practice.
2. Show mode, estimated time and concise progress.
3. Present one large Continue action.
4. Keep meaningful-speaking streak and recent improvement secondary.
5. Move account/settings into a profile action.
6. Move offline/video management behind progressive disclosure.

### 6.4 Lesson and quiz

1. Show a compact `Step 2 of 4 — Practice` indicator.
2. Keep only the current can-do objective and required material visible.
3. Present one question and one instruction at a time.
4. Use 48×48 minimum answer targets with a strong selected state.
5. Autosave after every selection.
6. Make Back navigate questions without losing answers.
7. Submit one answer and immediately show item-level rationale.
8. Offer a focused retry for incorrect items.
9. Never force a timer.
10. Complete with one capability statement, one priority improvement and one
    next action.

### 6.5 Upgrade

1. Trigger only at a natural learning boundary or deliberate locked-content tap.
2. Preserve accomplishment: “You completed Level 2.”
3. Explain exactly what becomes available.
4. Compare the two current membership tiers in distinct cards.
5. Show billing period, ex-GST amount, GST and exact payable total without
   presenting them as three competing prices.
6. Make Premium distinctive but never use coercive preselection.
7. Hide talk packs until the learner has an eligible paid membership and they
   are contextually relevant.
8. Provide Continue free/review prior lessons.
9. Distinguish loading, unavailable, checkout pending, failed, reconciled and
   active states.

## 7. Impact versus effort

| Quadrant | Items |
|---|---|
| **Low effort / high impact** | MUX-004 existing rationale display; MUX-007 onboarding error recovery; MUX-008 removal of the launch-time prompt and its source test (the contextual bridge itself is medium effort); MUX-010 duplicate Home actions; MUX-012 “missed” copy and review route; MUX-013 result next action; contextual upgrade action and talk-pack deferral from MUX-011 |
| **High effort / high impact** | MUX-001/002 review-rule correction (UX-07A); MUX-003/005 one-question autosaved quiz; MUX-006 full production first use; MUX-009 Back/history/restoration (escalated by the SDK 36 predictive-Back default); MUX-011 complete plans flow; MUX-015 Android device/TalkBack matrix |
| **Low effort / medium impact** | MUX-014 semantic feedback; MUX-016 label, active-state and isolated target corrections; focus placement/restoration |
| **High effort / lower immediate impact** | Full dynamic Material You palette and large-screen navigation rail after core journeys are stable |
| **Rejected** | XP-first progress, hearts/energy, purchasable streak repair, guilt notifications, blocking celebrations, fake scarcity and persistent countdown pressure |

## 8. Ordered implementation checkpoints

Only one checkpoint may be in progress at a time. A checkbox is evidence of
completion only when its linked tests or review artifacts exist.

### UX-AUDIT-00 — Metric taxonomy and review decision packet

**Status:** Documentation scope complete on 15 September 2026 under APP-0084,
D-0289 and D-0290. UX-07A subsequently closed the completion-labelled UI/API
gate under D-0291. Independent privacy/security/tenancy review remains an
explicit pre-emission exit gate and is not falsely marked complete.

**Purpose:** establish trustworthy baselines and resolve authoritative review
semantics before UI optimization.

#### Scope

- [x] Reconcile the UX programme ledgers (§1.1): either record D-0297–D-0302
      and the UX-05 in-progress state in `DECISIONS.md`/`STATUS.md`, or correct
      `UI_UX_MASTER_PLAN.md` front matter and progress log so one checkpoint
      state is authoritative. [Evidence](UX_AUDIT_00_DECISION_PACKET.md#1-authority-and-ledger-reconciliation).
- [x] Map the supplied “profile creation” metric to an exact route, screen ID,
      entry denominator, submit event and success event.
      [Evidence](UX_AUDIT_00_DECISION_PACKET.md#2-supplied-research-claims-and-repository-mapping).
- [x] Document existing onboarding and paywall event sources, if external to the
      repository. Both supplied sources are unknown and therefore unverified.
      [Evidence](UX_AUDIT_00_DECISION_PACKET.md#2-supplied-research-claims-and-repository-mapping).
- [x] Prepare and accept the MUX-001 on-time-window decision with boundary
      examples. [D-0289 and examples](UX_AUDIT_00_DECISION_PACKET.md#3-d-0289--mux-001--on-time-delayed-review).
- [x] Prepare and accept the MUX-002 completion-versus-attempt decision.
      [D-0290 and code confirmation](UX_AUDIT_00_DECISION_PACKET.md#4-d-0290--mux-002--review-attempt-semantics).
- [x] Define privacy-safe funnel events from section 9.
      [Contract](UX_FUNNEL_EVENT_CONTRACT.md#5-event-catalogue).
- [x] Define event retention, access, organization scope and deletion behavior.
      [Contract](UX_FUNNEL_EVENT_CONTRACT.md#7-organization-scoping-and-tenancy).
- [x] Assess whether an ADR or explicit master-plan amendment is needed. It is
      not: the accepted definitions clarify ADR-0028's read model and vocabulary
      without changing deterministic learning authority or architecture.
      [Rationale](UX_AUDIT_00_DECISION_PACKET.md#5-architecture-and-authority-conclusion).

#### Exit criteria

- [x] Named product/technical authority accepts the review definitions.
      [APP-0084](APPROVALS.md#app-0084).
- [x] No UI label claims a meaning different from the accepted calculation.
      UX-07A atomically migrated surfaced API fields, generated clients and
      learner/operator labels to reviewed/attempted vocabulary.
      [Evidence](QUALITY_REVIEW.md#ux-07a-accepted-review-semantics-implementation--15-september-2026).
- [x] Funnel denominator and success definitions are reproducible.
      [Contract](UX_FUNNEL_EVENT_CONTRACT.md#51-first-use).
- [ ] Privacy/security/tenancy review confirms that no sensitive content enters
      analytics. The fail-closed contract is complete, but named independent
      review remains required before emission; APP-0084 exercised product and
      technical decision roles only.
- [x] `docs/STATUS.md`, `docs/DECISIONS.md` and `docs/QUALITY_REVIEW.md` are
      updated for the documentation scope. [Status](STATUS.md#ux-audit-00-metric-taxonomy-and-review-decisions--15-september-2026),
      [decisions](DECISIONS.md) and
      [quality evidence](QUALITY_REVIEW.md#ux-audit-00-metric-taxonomy-and-review-decisions--15-september-2026).

### UX-07A — Accepted review semantics implementation

**Status:** Implementation scope complete on 15 September 2026 under D-0291;
checkpoint closure remains open because the required repository-wide
`make verify` and Progress-journey `make e2e` gates expose pre-existing,
out-of-scope failures

**Findings:** MUX-001, MUX-002

#### Scope

- [x] Implement the accepted on-time window in
      `LearnerProgressDashboardService` and any other reader of
      `completed_at`/`due_at`, evaluated in the occurrence's persisted timezone.
      [Evidence](QUALITY_REVIEW.md#ux-07a-accepted-review-semantics-implementation--15-september-2026).
- [x] Add exact boundary tests: one second before `due_at`, at `due_at`, one
      second before window end, at window end, one second after window end.
      [Boundary evidence](QUALITY_REVIEW.md#exact-boundary-matrix).
- [x] Implement the accepted completion-versus-attempt rule in
      `DailyMissionAttemptService` and `DelayedReviewScheduler`. If
      attempt-clears is accepted, rename API fields and UI labels so nothing
      claims "completed"/"mastered" for an incorrect attempt, and confirm an
      incorrect attempt schedules deterministic remediation.
      [Evidence](QUALITY_REVIEW.md#ux-07a-accepted-review-semantics-implementation--15-september-2026).
- [x] Update OpenAPI and generated client types for renamed fields.
      [Evidence](QUALITY_REVIEW.md#commands-and-results).
- [x] Preserve idempotency on replayed submissions and cross-tenant isolation;
      extend existing tests rather than adding parallel suites.
      [Evidence](QUALITY_REVIEW.md#ux-07a-accepted-review-semantics-implementation--15-september-2026).
- [x] Do not change review intervals; academic review of intervals remains the
      separate ADR-0028 follow-up.
      [Evidence](DECISIONS.md).
- [x] Assess whether an ADR amendment is required. It is not: D-0289/D-0290
      clarify the previously undefined calendar/read-model vocabulary without
      changing ADR-0028's deterministic scheduling semantics.
      [Evidence](DECISIONS.md).

#### Exit criteria

- [x] Every boundary test passes at the exact edge.
      [Boundary matrix](QUALITY_REVIEW.md#exact-boundary-matrix).
- [x] No API field or UI label claims a meaning different from the calculation.
      [Evidence](QUALITY_REVIEW.md#ux-07a-accepted-review-semantics-implementation--15-september-2026).
- [x] A replayed submission neither clears twice nor duplicates an
      occurrence.
      [Evidence](QUALITY_REVIEW.md#ux-07a-accepted-review-semantics-implementation--15-september-2026).
- [x] Cross-tenant read/write tests pass.
      [Evidence](QUALITY_REVIEW.md#ux-07a-accepted-review-semantics-implementation--15-september-2026).
- [ ] `make verify` passes; `make e2e` passes if the Home or Progress journey
      changed. Both were run; unrelated pre-existing failures retain this gate.
      [Exact results](QUALITY_REVIEW.md#commands-and-results).
- [x] `docs/STATUS.md`, `docs/DECISIONS.md` and `docs/QUALITY_REVIEW.md` are
      updated. [Status](STATUS.md#ux-07a-accepted-review-semantics-implementation--15-september-2026),
      [decision](DECISIONS.md) and
      [quality review](QUALITY_REVIEW.md#ux-07a-accepted-review-semantics-implementation--15-september-2026).

### UX-02A — Safe first-use baseline

**Status:** Implementation scope complete under APP-0085/D-0292; repository
verification and physical-device exit gates retained
**Findings:** MUX-007, MUX-008

#### Scope

- [x] Record a decision superseding the launch-time `RECORD_AUDIO` clause of
      D-0284 before changing code. [D-0292](DECISIONS.md).
- [x] Remove launch-time microphone prompting from both Android wrappers
      (`android-app` production and `android` demo).
      [Evidence](QUALITY_REVIEW.md#ux-02a-contextual-microphone-permission-and-onboarding-recovery--15-september-2026).
- [x] Replace `androidMicrophonePermission.test.ts` with a test that asserts
      the manifest declaration and the absence of launch-time prompting.
      [Evidence](QUALITY_REVIEW.md#ux-02a-contextual-microphone-permission-and-onboarding-recovery--15-september-2026).
- [x] Add an app-owned contextual permission bridge or approved equivalent that
      also grants WebView `onPermissionRequest` audio capture after the runtime
      grant. [Evidence](QUALITY_REVIEW.md#ux-02a-contextual-microphone-permission-and-onboarding-recovery--15-september-2026).
- [x] Confirm Speak `getUserMedia` still works on the bundled
      `https://localhost` origin after a contextual grant.
      [Evidence](QUALITY_REVIEW.md#ux-02a-contextual-microphone-permission-and-onboarding-recovery--15-september-2026).
- [x] Preserve Type instead and correct non-spoken mastery boundaries.
      [Evidence](QUALITY_REVIEW.md#ux-02a-contextual-microphone-permission-and-onboarding-recovery--15-september-2026).
- [x] Separate onboarding validation, timeout/offline and server errors.
      [Evidence](QUALITY_REVIEW.md#ux-02a-contextual-microphone-permission-and-onboarding-recovery--15-september-2026).
- [x] Add Retry same submission and Edit choices behavior without breaking
      idempotency. [Evidence](QUALITY_REVIEW.md#ux-02a-contextual-microphone-permission-and-onboarding-recovery--15-september-2026).

#### Exit criteria

- [ ] Fresh install reaches sign-in/first use without a permission prompt.
      Source/native launch contracts pass, but no Android device was attached
      for the installed fresh-launch smoke.
      [Evidence](QUALITY_REVIEW.md#ux-02a-contextual-microphone-permission-and-onboarding-recovery--15-september-2026).
- [x] First deliberate recording action provides localized rationale before the
      system prompt. [Evidence](QUALITY_REVIEW.md#ux-02a-contextual-microphone-permission-and-onboarding-recovery--15-september-2026).
- [x] Denial never blocks non-recording learning.
      [Evidence](QUALITY_REVIEW.md#ux-02a-contextual-microphone-permission-and-onboarding-recovery--15-september-2026).
- [x] Ambiguous submission retry reuses the same command and payload.
      [Evidence](QUALITY_REVIEW.md#ux-02a-contextual-microphone-permission-and-onboarding-recovery--15-september-2026).
- [x] Editing after failure creates the correct new command identity.
      [Evidence](QUALITY_REVIEW.md#ux-02a-contextual-microphone-permission-and-onboarding-recovery--15-september-2026).
- [x] Unit, browser, Android and localization tests cover every branch.
      [Evidence](QUALITY_REVIEW.md#ux-02a-contextual-microphone-permission-and-onboarding-recovery--15-september-2026).
- [ ] `make verify` passes. It was run and reached the pre-existing
      soft-launch fixed-string failure after all earlier stages passed.
      [Exact result](QUALITY_REVIEW.md#ux-02a-contextual-microphone-permission-and-onboarding-recovery--15-september-2026).
- [x] Critical first-use E2E passes in English and Kannada preview/approved mode
      as applicable. [Evidence](QUALITY_REVIEW.md#ux-02a-contextual-microphone-permission-and-onboarding-recovery--15-september-2026).

### UX-02B — Progressive first use and first success

**Status:** Implementation scope complete on 15 September 2026 under
APP-0086/D-0293; checkpoint closure remains open on repository-wide and human
accessibility/usability gates

**Findings:** MUX-006

#### Scope

- [x] Move the one-question stepper into the production flow.
      [Evidence](QUALITY_REVIEW.md#ux-02b-progressive-first-use-and-first-success--15-september-2026).
- [x] Add accepted language/accessibility, audio check, placement/defer and
      purpose-specific consent stages.
      [Evidence](QUALITY_REVIEW.md#ux-02b-progressive-first-use-and-first-success--15-september-2026).
- [x] Persist minimal safe onboarding progress.
      [Evidence](QUALITY_REVIEW.md#ux-02b-progressive-first-use-and-first-success--15-september-2026).
- [x] Provide progress, Back, resume and optional deferral.
      [Evidence](QUALITY_REVIEW.md#ux-02b-progressive-first-use-and-first-success--15-september-2026).
- [x] Reach one safe spoken or typed turn before the full Home dashboard.
      [Evidence](QUALITY_REVIEW.md#ux-02b-progressive-first-use-and-first-success--15-september-2026).
- [x] Keep adult, professional language across all three audience segments.
      [Evidence](QUALITY_REVIEW.md#ux-02b-progressive-first-use-and-first-success--15-september-2026).

#### Exit criteria

- [ ] A new adult can complete the canonical first-use sequence without guessing
      the next action. Automated paths pass; representative-adult comprehension
      testing remains open.
- [x] Interruption and process recreation restore the accepted safe step.
      [English/Kannada preview and unit evidence](QUALITY_REVIEW.md#ux-02b-progressive-first-use-and-first-success--15-september-2026).
- [x] Consent is purpose-specific and never preselected.
      [Evidence](QUALITY_REVIEW.md#ux-02b-progressive-first-use-and-first-success--15-september-2026).
- [x] First spoken/typed success is represented honestly.
      [Evidence](QUALITY_REVIEW.md#ux-02b-progressive-first-use-and-first-success--15-september-2026).
- [ ] TalkBack, enlarged text, keyboard/IME and 320px reflow pass.
- [x] Experiment UX-EXP-01 is protocol-ready but was not deployed or used to
      validate correctness. [Registry](#ux-exp-01--progressive-onboarding-density).
- [ ] `make verify` and the critical first-use `make e2e` journey pass. All
      first-use journeys pass, but the repository-wide commands retain the
      documented soft-launch-contract and shared contrast failures.
      [Exact results](QUALITY_REVIEW.md#ux-02b-progressive-first-use-and-first-success--15-september-2026).

### UX-03A — One-action Home and retention semantics

**Status:** Implementation scope complete under APP-0087/D-0294; repository
and representative-learner comprehension gates retained

**Findings:** MUX-010, MUX-012, MUX-013

#### Scope

- [x] Remove duplicate hero Speak/Learn actions for authenticated learners.
  Evidence: [quality review](QUALITY_REVIEW.md#ux-03a-one-action-home-and-retention-semantics--15-september-2026).
- [x] Preserve due-review-first deterministic selection. Evidence:
  [quality review](QUALITY_REVIEW.md#ux-03a-one-action-home-and-retention-semantics--15-september-2026).
- [x] Replace “reviews missed” with the accepted review terminology. Evidence:
  [quality review](QUALITY_REVIEW.md#ux-03a-one-action-home-and-retention-semantics--15-september-2026).
- [x] Add one-tap navigation from every blocking-review message. Evidence:
  [quality review](QUALITY_REVIEW.md#ux-03a-one-action-home-and-retention-semantics--15-september-2026).
- [x] Add concise session/capability progress and one result next action.
  Evidence: [quality review](QUALITY_REVIEW.md#ux-03a-one-action-home-and-retention-semantics--15-september-2026).
- [x] Keep streak secondary and non-gating. Evidence:
  [quality review](QUALITY_REVIEW.md#ux-03a-one-action-home-and-retention-semantics--15-september-2026).

#### Exit criteria

- [x] Exactly one dominant learning action appears above the fold. Evidence:
  [quality review](QUALITY_REVIEW.md#ux-03a-one-action-home-and-retention-semantics--15-september-2026).
- [ ] Learners can explain what Continue will open in comprehension testing.
- [x] No guilt, global countdown or punitive streak copy exists. Evidence:
  [quality review](QUALITY_REVIEW.md#ux-03a-one-action-home-and-retention-semantics--15-september-2026).
- [x] Direct learners have an actionable review-remediation path. Evidence:
  [quality review](QUALITY_REVIEW.md#ux-03a-one-action-home-and-retention-semantics--15-september-2026).
- [ ] `make verify` and Home/review E2E pass.

### UX-04A — One-question quiz and useful feedback

**Status:** Implementation scope complete under APP-0088/D-0295; repository,
physical accessibility and experiment gates retained

**Findings:** MUX-003, MUX-004, MUX-005, MUX-014

#### Scope

- [x] Render one question per view for quick checks and checkpoints. Evidence:
  [quality review](QUALITY_REVIEW.md#ux-04a-one-question-quiz-and-resumable-drafts--15-september-2026).
- [x] Add `Question X of Y` progress. Evidence: [quality review](QUALITY_REVIEW.md#ux-04a-one-question-quiz-and-resumable-drafts--15-september-2026).
- [x] Implement 48×48 answer-card targets and non-color-only selected state.
  Evidence: [quality review](QUALITY_REVIEW.md#ux-04a-one-question-quiz-and-resumable-drafts--15-september-2026).
- [x] Add a sticky, IME-safe primary action. Evidence: [quality review](QUALITY_REVIEW.md#ux-04a-one-question-quiz-and-resumable-drafts--15-september-2026).
- [x] Autosave permitted answer drafts with tenant/user/attempt binding and TTL.
  Evidence: [quality review](QUALITY_REVIEW.md#ux-04a-one-question-quiz-and-resumable-drafts--15-september-2026).
- [x] Restore drafts after Back, tab switches, backgrounding and process death.
  The persisted mechanism and browser reload/remount paths are covered; the
  physical Android lifecycle exit gate remains open. Evidence: [quality review](QUALITY_REVIEW.md#ux-04a-one-question-quiz-and-resumable-drafts--15-september-2026).
- [x] Present existing item-level correctness and rationale. Evidence: [quality review](QUALITY_REVIEW.md#ux-04a-one-question-quiz-and-resumable-drafts--15-september-2026).
- [x] Add focused incorrect-item retry. Evidence: [quality review](QUALITY_REVIEW.md#ux-04a-one-question-quiz-and-resumable-drafts--15-september-2026).
- [x] Evaluate optional sensory feedback: use 180 ms non-blocking motion with
  reduced-motion removal; add no sound or haptic without a separate accepted
  decision. Evidence: [D-0295](DECISIONS.md) and [quality review](QUALITY_REVIEW.md#ux-04a-one-question-quiz-and-resumable-drafts--15-september-2026).
- [x] Preserve authoritative idempotent submission and offline checkpoint rules.
  Evidence: [quality review](QUALITY_REVIEW.md#ux-04a-one-question-quiz-and-resumable-drafts--15-september-2026).

#### Exit criteria

- [x] No quiz surface shows multiple questions at once. Evidence: [quality review](QUALITY_REVIEW.md#ux-04a-one-question-quiz-and-resumable-drafts--15-september-2026).
- [x] No automated ordinary-interruption path loses a selected answer; physical
  Android lifecycle validation remains open. Evidence: [quality review](QUALITY_REVIEW.md#ux-04a-one-question-quiz-and-resumable-drafts--15-september-2026).
- [x] A submitted answer receives useful feedback without waiting for animation.
  Evidence: [quality review](QUALITY_REVIEW.md#ux-04a-one-question-quiz-and-resumable-drafts--15-september-2026).
- [x] Correctness is expressed with icon, text and color. Evidence: [quality review](QUALITY_REVIEW.md#ux-04a-one-question-quiz-and-resumable-drafts--15-september-2026).
- [x] No raw audio/transcript or unrestricted learner text is added to draft
  storage. Evidence: [quality review](QUALITY_REVIEW.md#ux-04a-one-question-quiz-and-resumable-drafts--15-september-2026).
- [ ] TalkBack, enlarged text, keyboard, reduced motion and offline paths pass.
- [ ] `make verify` and critical quiz/checkpoint `make e2e` pass.
- [ ] Experiment UX-EXP-03 is technically ready.

### UX-08A — Contextual plans and recoverable checkout presentation

**Status:** Implementation scope complete under APP-0089/D-0296; repository
and representative-learner comprehension gates retained; live payment remains
separately unauthorized
**Findings:** MUX-011

#### Scope

- [x] Add a contextual upgrade action to entitlement-locked content. Evidence:
      [quality review](QUALITY_REVIEW.md#ux-08a-contextual-plans-and-recoverable-checkout-presentation--15-september-2026).
- [x] Preserve the completed-learning context before presenting plans. Evidence:
      [quality review](QUALITY_REVIEW.md#ux-08a-contextual-plans-and-recoverable-checkout-presentation--15-september-2026).
- [x] Stage current entitlement, membership comparison and tax detail. Evidence:
      [quality review](QUALITY_REVIEW.md#ux-08a-contextual-plans-and-recoverable-checkout-presentation--15-september-2026).
- [x] Present the two current membership choices as benefit-led cards. Evidence:
      [quality review](QUALITY_REVIEW.md#ux-08a-contextual-plans-and-recoverable-checkout-presentation--15-september-2026).
- [x] Make the exact payable total and plan period unambiguous. Evidence:
      [quality review](QUALITY_REVIEW.md#ux-08a-contextual-plans-and-recoverable-checkout-presentation--15-september-2026).
- [x] Defer talk packs until an eligible paid state. Evidence:
      [quality review](QUALITY_REVIEW.md#ux-08a-contextual-plans-and-recoverable-checkout-presentation--15-september-2026).
- [x] Add Continue free/review prior lessons. Evidence:
      [quality review](QUALITY_REVIEW.md#ux-08a-contextual-plans-and-recoverable-checkout-presentation--15-september-2026).
- [x] Distinguish load, unavailable, pending, ambiguous, failed, reconciled and
      active checkout states. Evidence: [quality review](QUALITY_REVIEW.md#ux-08a-contextual-plans-and-recoverable-checkout-presentation--15-september-2026).
- [x] Preserve institutional learners' exclusion from Direct paywalls. Evidence:
      [quality review](QUALITY_REVIEW.md#ux-08a-contextual-plans-and-recoverable-checkout-presentation--15-september-2026).

#### Exit criteria

- [x] Every deliberate premium lock has a contextual, recoverable route.
      Evidence: [component/browser review](QUALITY_REVIEW.md#ux-08a-contextual-plans-and-recoverable-checkout-presentation--15-september-2026).
- [ ] The learner can state what each tier unlocks and what is payable.
- [x] Closing the plans surface never loses lesson progress. Evidence:
      [focused browser journey](QUALITY_REVIEW.md#ux-08a-contextual-plans-and-recoverable-checkout-presentation--15-september-2026).
- [x] Institutional memberships never receive Direct checkout UI. Evidence:
      [service/UI tenancy tests](QUALITY_REVIEW.md#ux-08a-contextual-plans-and-recoverable-checkout-presentation--15-september-2026).
- [x] Dummy/disabled provider behavior remains honest and fail-closed. Evidence:
      [checkout-state tests](QUALITY_REVIEW.md#ux-08a-contextual-plans-and-recoverable-checkout-presentation--15-september-2026).
- [x] Subscription unit, tenancy and E2E tests pass. The required full
      repository browser command retains unrelated shared-chrome failures.
      [Exact results](QUALITY_REVIEW.md#ux-08a-contextual-plans-and-recoverable-checkout-presentation--15-september-2026).
- [x] Experiment UX-EXP-02 is protocol-ready using only plan-selection intent
      and authorized dummy/disabled checkout behavior; it is not activated.
      [Protocol](#ux-exp-02--contextual-value-first-plans).

### UX-10A — Android Back, lifecycle and accessibility hardening

**Status:** Implementation scope complete under APP-0090/D-0297; device and
repository gates retained
**Findings:** MUX-005, MUX-009, MUX-015, MUX-016

#### Scope

- [x] Represent destination, selected level, session part and quiz question in
      app navigation history. Evidence: [content-free history contract](../apps/web/src/learner/LearnerNavigationHistory.ts)
      and [focused tests](../apps/web/src/learner/LearnerNavigationHistory.test.ts).
- [ ] Confirm on an API 36 device whether Capacitor 8.5 `BridgeActivity`
      dispatches Back through `OnBackInvokedCallback`/`OnBackPressedCallback`;
      record the result before choosing the integration point. No device was
      attached on 2026-09-15. The mandatory source inspection found no
      Capacitor-owned Back policy; see [D-0297](DECISIONS.md).
- [x] Integrate supported system/predictive Back handling; do not rely on
      `onBackPressed` or `KEYCODE_BACK`, and do not ship
      `enableOnBackInvokedCallback="false"` as a permanent opt-out.
      Evidence: both app-owned `GlobalEnglishAndroidShell` implementations and
      their successful application/instrumentation compile recorded in the
      [quality review](QUALITY_REVIEW.md#ux-10a-android-back-lifecycle-and-accessibility-hardening--16-september-2026).
- [x] Restore focus to the originating control after Back. Evidence:
      [history focus contract](../apps/web/src/learner/LearnerNavigationHistory.ts)
      and [browser/component evidence](QUALITY_REVIEW.md#ux-10a-android-back-lifecycle-and-accessibility-hardening--16-september-2026).
- [x] Announce level, part and question transitions to assistive technology.
      Evidence: [Level-path live-region implementation](../apps/web/src/learner/LevelPathPanel.tsx).
- [ ] Validate WebView safe-area, edge-to-edge and IME behavior.
      AndroidX/CSS integration and browser simulation pass, but physical
      WebView validation remains open under §10.1.
- [x] Replace the placeholder Android instrumentation test. Evidence:
      [production suite](../apps/web/android-app/app/src/androidTest/java/com/globalenglish/app/CriticalLearnerJourneyTest.java)
      and [demo suite](../apps/web/android/app/src/androidTest/java/com/globalenglish/demo/CriticalLearnerJourneyTest.java).
- [x] Add Android UI smoke coverage for first use, lesson, quiz, Speak denial,
      plans and resume. Both suites compile; connected execution remains open.
- [ ] Evaluate system light/dark/contrast preferences and Material active-state
      treatment without changing the accepted brand identity. Browser
      evaluation is implemented; real system/WebView evaluation remains open.
- [ ] Complete the device matrix in section 10.

#### Exit criteria

- [ ] Predictive Back previews and returns to the expected prior destination.
- [x] Back during recording cancels safely and explains the result. Evidence:
      [focused component test](../apps/web/src/learner/RecordingTurn.test.tsx)
      and [quality review](QUALITY_REVIEW.md#ux-10a-android-back-lifecycle-and-accessibility-hardening--16-september-2026).
- [ ] Low-memory relaunch returns to the last safe recoverable state.
- [ ] No task target is smaller than 48×48 CSS-pixel equivalent on the tested
      WebView configuration.
- [ ] English and Kannada labels remain usable at 200% font scale.
- [ ] Physical TalkBack and device evidence is recorded.
- [ ] `make verify`, critical `make e2e` and `make build` pass.

## 9. Measurement contract

The current operator metrics are aggregate counts and do not provide the
denominators, step timing or failure categorization required for funnel
analysis. Subject to an accepted privacy/retention design, use content-free
events such as:

| Journey | Event sequence | Allowed dimensions |
|---|---|---|
| First use | `onboarding_view → onboarding_step_complete → onboarding_submit_result → first_lesson_start → first_lesson_complete` | step ID, result category, elapsed bucket, locale, app version, network-state category |
| Permission | `permission_rationale_view → permission_action → permission_result` | permission type, temporary/permanent result, typed-fallback selection |
| Quiz | `quiz_start → question_view → answer_submit → rationale_view → retry_or_continue → quiz_complete` | level/part IDs, question ordinal, correctness boolean, attempt number, elapsed bucket, restore boolean |
| Paywall | `paywall_trigger → plan_impression → plan_select → checkout_result OR continue_free` | trigger context, plan code, entitlement state, result category; never payment credentials |
| Review | `review_impression → review_start → review_result → remediation → review_complete` | due-age bucket, correctness boolean, accepted completion classification |

### Measurement prohibitions

- [x] No raw audio or derived audio payload. [Contract §4](UX_FUNNEL_EVENT_CONTRACT.md#4-forbidden-content).
- [x] No transcript or learner-authored response text. [Contract §4](UX_FUNNEL_EVENT_CONTRACT.md#4-forbidden-content).
- [x] No credentials, tokens, recovery contacts or payment credentials. [Contract §4](UX_FUNNEL_EVENT_CONTRACT.md#4-forbidden-content).
- [x] No cross-organization identifiers or unscoped exports. [Contract §§7–8](UX_FUNNEL_EVENT_CONTRACT.md#7-organization-scoping-and-tenancy).
- [x] No accent, pronunciation or employability inference. [Contract §4](UX_FUNNEL_EVENT_CONTRACT.md#4-forbidden-content).
- [x] No analytics event may become an authoritative mastery/progression record. [Contract §1](UX_FUNNEL_EVENT_CONTRACT.md#1-purpose-and-non-authoritative-boundary).

## 10. Android device and accessibility matrix

| Scenario | API 24 | API 35 | API 36 | Physical low-cost phone | Current phone | Evidence |
|---|---:|---:|---:|---:|---:|---|
| Fresh install; no launch mic prompt | [ ] | [ ] | [ ] | [ ] | [ ] | Open — no attached device; §10.1 |
| Contextual grant/deny/permanent deny | [ ] | [ ] | [ ] | [ ] | [ ] | Open — no attached device; §10.1 |
| Gesture predictive Back | N/A | [ ] | [ ] | [ ] | [ ] | Open — no attached device; §10.1 |
| Three-button Back | [ ] | [ ] | [ ] | [ ] | [ ] | Open — no attached device; §10.1 |
| Quiz resume after app switch | [ ] | [ ] | [ ] | [ ] | [ ] | Open — no attached device; §10.1 |
| Low-memory/process recreation | [ ] | [ ] | [ ] | [ ] | [ ] | Open — no attached device; §10.1 |
| Rotation and split-screen | [ ] | [ ] | [ ] | [ ] | [ ] | Open — no attached device; §10.1 |
| IME does not obscure primary action | [ ] | [ ] | [ ] | [ ] | [ ] | Open — no attached device; §10.1 |
| TalkBack critical journey | [ ] | [ ] | [ ] | [ ] | [ ] | Open — no attached device; §10.1 |
| 200% font scale, English | [ ] | [ ] | [ ] | [ ] | [ ] | Open — no attached device; §10.1 |
| 200% font scale, Kannada | [ ] | [ ] | [ ] | [ ] | [ ] | Open — no attached device; §10.1 |
| Slow/offline transition and recovery | [ ] | [ ] | [ ] | [ ] | [ ] | Open — no attached device; §10.1 |
| Light/dark/high-contrast evaluation | [ ] | [ ] | [ ] | [ ] | [ ] | Open — no attached device; §10.1 |

### 10.1 Human device execution checklist — added after empty device probe

`adb devices -l` was run on 15 September 2026 and returned only
`List of devices attached`; no emulator or physical device was attached. No
cell above is inferred from browser, unit, compile or APK-build evidence. Every
non-N/A cell therefore remains open: 64 cells in total (five cells for each of
the 12 non-gesture rows, plus four predictive-Back cells because API 24 is
N/A).

Run the following sequence for **each applicable column** and record device
manufacturer/model, Android release, API level, WebView version, navigation
mode, font scale, locale, wrapper/package, build SHA, tester, timestamp and an
evidence link before marking a cell:

1. Connect only the intended device, unlock it, enable USB debugging and run
   `adb devices -l`; verify its serial, model and transport are unambiguous.
2. Install the matching freshly built APK with `adb install -r <absolute-apk>`.
   Use `com.globalenglish.app` for production-path evidence and repeat the
   synthetic-only checks with `com.globalenglish.demo`; never treat the demo
   identity as production evidence.
3. Clear app data for a fresh-install row, then launch from the device launcher.
   Do not pre-grant microphone permission. Record that no launch-time prompt
   appears.
4. Provision the documented synthetic/seeded learner states: onboarding
   incomplete; Level 1 session with a two-item quick check; an entitlement-
   locked level; and an eligible plans catalogue. Do not place credentials or
   learner-authored text in screenshots, logs or test arguments.
5. Run the package-correct connected suite:
   `./gradlew connectedDebugAndroidTest` from `apps/web/android-app`, then from
   `apps/web/android`. Preserve the XML result and logcat excerpt; a compiled
   suite that was not connected is not a pass.
6. Execute the manual rows below in both gesture and three-button navigation
   where applicable. Capture screen recording for Back/IME/rotation behavior
   and screenshots for static layout; redact status-bar identifiers.
7. Repeat target/font/contrast checks in English and Kannada preview. Treat the
   five non-English catalogues as implementation previews until independent
   review; this does not prevent layout evidence but does prevent language-
   quality sign-off.
8. After each interruption/relaunch, verify the organization/member/level/
   part/attempt binding and option-only draft restoration. Inspect no raw
   learner text, audio, transcript, token or credential in WebView or Android
   ordinary storage.

| Matrix row | Manual steps and pass condition |
|---|---|
| Fresh install; no launch mic prompt | Clear app data, launch, traverse value/adult/goal steps without tapping a speaking control; pass only if no runtime microphone dialog appears |
| Contextual grant/deny/permanent deny | From a deliberate speaking action, test grant; clear/revoke and test deny once with rationale; deny with **Don't ask again** (where offered) and confirm permanent-denial copy plus **Type instead**; all non-recording learning remains usable |
| Gesture predictive Back | On API 35/36 gesture mode, traverse Learn → level → part → question 1 → question 2; slowly swipe Back and verify the preview label names the actual prior question/session/list destination before committing or cancelling each gesture |
| Three-button Back | Repeat the same hierarchy with three-button navigation; verify question → session → level list → prior primary destination and no app exit while an in-app entry remains |
| Quiz resume after app switch | Select an option, background for at least 30 seconds, return through Recents and verify exact question, selected option and stable attempt are restored |
| Low-memory/process recreation | Enable **Don't keep activities** or terminate the process after backgrounding, relaunch from Recents/launcher and verify the safe content-free route plus bound option-only draft; repeat with personal-device persistence disabled and verify no durable draft |
| Rotation and split-screen | At question, rationale, recording rationale and plans states, rotate both ways and enter/leave split screen; pass with no duplicate submission, lost draft, clipped action or focus trap |
| IME does not obscure primary action | Open an input-bearing learning state, show the real IME, enter the quiz and inspect the sticky Continue/Submit action above the IME and system bar at 320 CSS-pixel equivalent width |
| TalkBack critical journey | With TalkBack, complete first use, lesson selection, both quiz questions, rationale/retry, Speak denial, plans close and resume; verify transition announcements, checked state, result icon+text, logical order and focus return |
| 200% font scale, English | Set system font to 200%, English UI and 320 CSS-pixel equivalent width; repeat the critical journey with no clipping, overlap, horizontal task scroll or target loss |
| 200% font scale, Kannada | Repeat the preceding enlarged-text journey in Kannada preview and record layout separately from still-pending linguistic review |
| Slow/offline transition and recovery | Throttle/disable network during a selected quiz draft and checkpoint submission, resume/reconnect and verify the same idempotency key, one authoritative submission and truthful pending/reconciled states |
| Light/dark/high-contrast evaluation | Compare system light, dark and high-contrast/text settings. Pass only if accepted light Variant C remains readable, system bars/icons and WebView content retain contrast, active navigation has shape/weight plus colour, and no unapproved dark product theme appears |

For API 24, predictive gesture Back remains N/A, but three-button Back,
lifecycle, permission, IME, accessibility and appearance rows still apply. API
35 and API 36 must cover both navigation modes where the system image supports
them. Low-cost/current-phone columns require physical hardware; emulator
results belong only in the API columns.

## 11. A/B experiment registry

Experiments optimize already-correct behavior. They must not be used to decide
whether to follow privacy, accessibility, progress-integrity or safe-recovery
requirements.

### UX-EXP-01 — Progressive onboarding density

**Status:** Protocol ready under the UX-AUDIT-00 event taxonomy; not deployed
or used as correctness evidence. Activation remains blocked on independent
analytics privacy/security/tenancy review and green UX-02 repository/human gates.

**Hypothesis:** one decision per screen will reduce onboarding abandonment and
increase first-lesson completion compared with the current combined form.

| Element | Definition |
|---|---|
| Control | Archived pre-UX-02B combined adult-confirmation and goal form, with corrected error/permission behavior |
| Variant | Same choices and authoritative payload split into progressive steps with progress, Back and sticky Continue |
| Unit | Learner, assigned persistently before first onboarding view |
| Primary metric | Successful onboarding submissions / onboarding entrants |
| Activation metric | First lesson completed in the same session and within 24 hours |
| Guardrails | Median completion time, transport-error rate, consent comprehension, accessibility failures, typed-fallback availability |
| Segments | Goal, locale, Android version, viewport bucket and network category; never sensitive content |

### UX-EXP-02 — Contextual value-first plans

**Status:** Protocol ready under UX-08A for plan-selection intent and authorized
dummy/disabled checkout behavior only; activation remains blocked on independent
analytics review and repository/human UX-08 gates. Any live-checkout arm would
additionally require separate authority.

**Hypothesis:** preserving the learner's accomplishment and presenting only the
membership decision at the point of intent will improve plan selection without
increasing pressure or comprehension failures.

| Element | Definition |
|---|---|
| Control | Corrected current Account-based plan surface |
| Variant | Natural-boundary/locked-content entry, accomplishment recap, two benefit cards, expandable tax details and no talk packs |
| Unit | Learner, assigned persistently before first eligible trigger |
| Primary metric | Plan selected / eligible plans impression |
| Secondary metric | Authorized checkout start and successful completion |
| Guardrails | Continue-free rate, rapid close, plan comprehension, support/refund intent, return to learning |
| Trigger segments | Free-level boundary, locked level, Role-play and AI allowance exhaustion |

Until live payment is separately authorized, this experiment may measure only
plan-selection intent and dummy/disabled-provider behavior permitted by the
accepted billing ADRs.

### UX-EXP-03 — One-question quiz

**Status:** Implementation baseline delivered; blocked on repository/device
gates, measurement emission and experiment preregistration

**Hypothesis:** a one-question pager with autosave and immediate rationale will
reduce quiz abandonment and improve focused retry performance.

| Element | Definition |
|---|---|
| Control | Current batch form with corrected restoration and feedback baseline |
| Variant | `Question X of Y`, large answer cards, sticky action, item rationale and focused retry |
| Unit | Learner attempt; assignment remains stable for the entire attempt |
| Primary metric | Quiz completed / quiz started |
| Secondary metrics | Abandonment by ordinal, retry improvement, next-session continuation |
| Guardrails | Total task time, pass-rate distortion, resume failures, TalkBack completion and enlarged-text reflow |

### Experiment operating rules

- [ ] Pre-register primary metric, guardrails and analysis window.
- [ ] Calculate sample size from the measured baseline rather than choosing an
      arbitrary uplift.
- [ ] Use one stable assignment per learner or attempt as specified above.
- [ ] Run through at least one complete weekly usage cycle unless a safety
      guardrail requires earlier termination.
- [ ] Do not peek and stop only when the desired result appears.
- [ ] Report effect size and uncertainty, not only statistical significance.
- [ ] Check whether benefit or harm differs by goal, locale, screen size,
      network quality and new/returning status.
- [ ] Never ship a winning variant that violates a guardrail or authoritative
      product rule.

## 12. Required verification commands

Run from the repository root as applicable to the completed checkpoint:

```text
make verify
make e2e        # critical learner journey changed
make ai-eval    # prompt, model, provider or orchestration changed
make build      # Android/web packaging or deployment changed
```

Checkpoint-specific tests must be listed in that checkpoint's quality-review
entry. Never record a pass unless the command actually completed successfully.

## 13. Audit baseline evidence

The read-only audit ran:

```text
env PATH="$PWD/tmp/toolchain/node/bin:$PATH" \
  tmp/toolchain/node/bin/pnpm --filter @global-english/web exec vitest run \
  src/learner/LevelPathPanel.test.tsx \
  src/learner/LearnerJourney.test.tsx \
  src/subscriptions/SubscriptionPanel.test.tsx
```

Result on 15 September 2026:

- 3 test files passed;
- 26 tests passed;
- no files were changed by the audit;
- `adb devices -l` found no attached device;
- full `make verify`, `make e2e` and `make build` were not run because the audit
  made no implementation change.

These tests confirm current behavior; they do not constitute usability,
TalkBack, lifecycle or physical-device evidence.

### 13.1 Independent re-verification — 15 September 2026

A second read-only pass at the same commit `ce305bd` re-checked every source
citation in section 5 against the working tree and re-ran the baseline with the
microphone source test added:

```text
env PATH="$PWD/tmp/toolchain/node/bin:$PATH" \
  tmp/toolchain/node/bin/pnpm --filter @global-english/web exec vitest run \
  src/learner/LevelPathPanel.test.tsx \
  src/learner/LearnerJourney.test.tsx \
  src/subscriptions/SubscriptionPanel.test.tsx \
  src/auth/androidMicrophonePermission.test.ts
```

Result: 4 test files passed; 27 tests passed. All MUX-001 to MUX-016 source
claims were confirmed. Corrections made in this pass: §1.1 ledger caveats,
§1.2 roadmap mapping, MUX-003 thumb-zone note, MUX-008 D-0284 lineage and both
wrappers, MUX-009 SDK 36 predictive-Back escalation, MUX-015 both wrappers,
MUX-016 CSS sources, MUX-008 added to the §7 quadrant table, and the UX-AUDIT-00,
UX-02A and UX-10A scope additions. No application code was changed.

### 13.2 Independent post-implementation audit — 16 September 2026

Audited the uncommitted working tree on `cursor/learner-variant-c-jezweb-3f88`
(HEAD `132a847` = plan commit; 76 modified and 20 new files uncommitted). Every
MUX-001 to MUX-016 claim was re-checked against source by four independent
reviewers plus direct command runs with the pinned toolchain
(`tmp/toolchain`).

| Command | Result |
|---|---|
| `make verify` | Not green. Composer, Pint, PHPStan, SQLite 316/316, PostgreSQL 360/360, install, audit, locale, OpenAPI/contracts, release contract passed; stopped at `soft-launch-contract-test` (`grep -Fq 'ai.globalspokenenglish.com,127.0.0.1:9080'` in unchanged `scripts/`) — **pre-existing at `ce305bd`** |
| `pnpm run typecheck` | Passed |
| `pnpm run lint` | 3 errors / 2 warnings in `VoicePracticePanel.tsx`, `browserSpeech.ts`, `RecordingTurn.tsx` — **identical at `ce305bd`** (verified in a baseline worktree) |
| `pnpm run test` | 459/460; sole failure `jezwebLearnerChrome.test.ts` expects `?v=jezweb-c4` against `c8` — **pre-existing at `ce305bd`** |
| `make build` | Passed |
| `make e2e` | 22/29; all 7 failures are the axe `color-contrast` violation on the kn-IN pending-review notice (`#3d5348` on `#006b3f`, both from `miraGescGreenPin.css`, untouched; kn-IN already `pending-independent-review` at baseline) — **pre-existing** |
| Gradle `:app:compileDebugJavaWithJavac`, `:app:compileDebugAndroidTestJavaWithJavac`, `:app:testDebugUnitTest` (production wrapper, JDK 21, `--rerun-tasks`) | BUILD SUCCESSFUL; 6 unit tests, 0 failures |
| `adb devices -l` | No device; §10 remains 0/64 cells |

Source verdicts: MUX-001–004, 006–015 resolved in source; MUX-005 resolved
for quiz drafts; MUX-016 resolved in browser evidence only. D-0297's Capacitor
8.5.0 `BridgeActivity` claim confirmed against installed source. No over-grant
in `GlobalEnglishWebChromeClient`; no prohibited Back API.

Defects found (not yet fixed; owner decides):

1. **Uncommitted work.** All nine checkpoints are in one uncommitted tree with
   no per-checkpoint commits; every APP-0084–0090 record says the SHA was
   unavailable. Prompts 8b (device evidence) and 9 (closure pass) were not
   executed: §14 has no completion dates for orders 1–7 and §15 is unchecked.
2. **`FirstUseOnboardingResume.ts`** persists `step`, `adultConfirmed`, `goal`,
   `placement` in plaintext `localStorage` under a fixed key with no
   learner/organization binding and no TTL; cleared only on completion or
   explicit logout. On a shared device a later learner inherits the prior
   learner's adult confirmation and goal, and `onboardingSaved: true` skips
   their authoritative goal submission. Inconsistent with the ADR-0006
   standard applied to quiz drafts.
3. Residual raw `location.hash = '#home' | '#sign-in'` assignments in
   `App.tsx` create history entries with `index: 0`, so system Back from
   `#sign-in` exits the app rather than returning through web history.
4. `GlobalEnglishAndroidShell` keeps its callback permanently enabled and the
   JS round-trip is asynchronous, so the system predictive back-to-launcher
   animation never runs; if the WebView dies mid-dispatch `dispatchPending`
   sticks and Back is swallowed until restart. `MainActivity.onCreate`
   dereferences `bridge` without the `no_webview` guard in both wrappers.
5. `SubscriptionPanel` `pending` checkout state disables all actions and
   offers no reconcile/check-access action (only `ambiguous` does).
6. `LearnerProgressDashboardService` throws `LearningOperationRejected` on any
   row with `completed_at < due_at`; unreachable via current writers, but one
   corrupt row would fail-close the whole dashboard for that learner.
7. Instrumentation tests are DOM-scripted smoke tests needing seeded content;
   the Speak-denial test never presses Speak; demo copy is weaker than
   production; stale `ExampleUnitTest.java` remains in both wrappers.
   `androidBackNavigation.test.ts` hard-codes the pnpm store path.
8. OpenAPI 0.47.0 documents the whole operator `learning_metrics` object
   (previously undocumented) alongside the one required rename; correct, but
   beyond UX-07A's stated scope. `LearnerSubscriptionService` changes belong to
   UX-08A, not UX-07A.

## 14. Completion ledger

Update this table only after the checkpoint's exit criteria and required
repository ledgers are complete.

| Order | Checkpoint | Status | Owner | Decision/evidence | Completed |
|---:|---|---|---|---|---|
| 0 | UX-AUDIT-00 Metric taxonomy and review decisions | Documentation scope complete; independent-review exit gate retained | Accountable Product Owner / Technical Decision Owner | Commit `afc5b2d`; [APP-0084](APPROVALS.md#app-0084); [D-0289/D-0290 packet](UX_AUDIT_00_DECISION_PACKET.md); [event contract](UX_FUNNEL_EVENT_CONTRACT.md); [quality review](QUALITY_REVIEW.md#ux-audit-00-metric-taxonomy-and-review-decisions--15-september-2026) | 2026-09-15 |
| 1 | UX-07A Accepted review semantics implementation | Implementation scope complete; repository verification gates retained | Technical Decision Owner | Commit `074f31f`; [D-0291](DECISIONS.md); [quality review](QUALITY_REVIEW.md#ux-07a-accepted-review-semantics-implementation--15-september-2026) | |
| 2 | UX-02A Safe first-use baseline | Implementation scope complete; repository/device exit gates retained | Accountable Product Owner / Technical Decision Owner | Commit `7b7afb2`; [APP-0085](APPROVALS.md#app-0085); [D-0292](DECISIONS.md); [quality review](QUALITY_REVIEW.md#ux-02a-contextual-microphone-permission-and-onboarding-recovery--15-september-2026) | |
| 3 | UX-02B Progressive first use | Implementation scope complete; repository/human exit gates retained | Accountable Product Owner / Technical Decision Owner | Commit `67c16f9`; [APP-0086](APPROVALS.md#app-0086); [D-0293](DECISIONS.md); [quality review](QUALITY_REVIEW.md#ux-02b-progressive-first-use-and-first-success--15-september-2026) | |
| 4 | UX-03A One-action Home | Implementation scope complete; repository/human exit gates retained | Accountable Product Owner | Commit `406fe21`; [APP-0087](APPROVALS.md#app-0087); [D-0294](DECISIONS.md); [quality review](QUALITY_REVIEW.md#ux-03a-one-action-home-and-retention-semantics--15-september-2026) | |
| 5 | UX-04A One-question quiz | Implementation scope complete; repository/device/human gates retained | Accountable Product Owner | Commit `7145e89`; [APP-0088](APPROVALS.md#app-0088); [D-0295](DECISIONS.md); [quality review](QUALITY_REVIEW.md#ux-04a-one-question-quiz-and-resumable-drafts--15-september-2026) | |
| 6 | UX-08A Contextual plans | Implementation scope complete; repository/human gates retained | Accountable Product Owner | Commit `57166d6`; [APP-0089](APPROVALS.md#app-0089); [D-0296](DECISIONS.md); [quality review](QUALITY_REVIEW.md#ux-08a-contextual-plans-and-recoverable-checkout-presentation--15-september-2026) | |
| 7 | UX-10A Android hardening | Implementation scope complete; repository/device gates retained | Accountable Product Owner | Commit `42e8393`; [APP-0090](APPROVALS.md#app-0090); [D-0297](DECISIONS.md); [quality review](QUALITY_REVIEW.md#ux-10a-android-back-lifecycle-and-accessibility-hardening--16-september-2026) | |
| 8 | UX-EXP-01 Onboarding experiment | Blocked | Unassigned | | |
| 9 | UX-EXP-02 Plans experiment | Protocol ready; activation blocked | Accountable Product Owner | [UX-08A evidence](QUALITY_REVIEW.md#ux-08a-contextual-plans-and-recoverable-checkout-presentation--15-september-2026) | |
| 10 | UX-EXP-03 Quiz experiment | Blocked | Unassigned | | |

## 15. Definition of audit closure

This audit is complete only when all of the following are true:

- [ ] MUX-001 through MUX-016 have an accepted resolution or explicitly
      accepted residual limitation.
- [ ] Every implementation checkpoint in section 8 is complete in order.
- [ ] All three experiments are completed or explicitly rejected with a recorded
      reason.
- [ ] The 35% “profile creation” metric is mapped to a reproducible funnel.
- [ ] Quiz-clutter qualitative feedback is re-tested with representative casual,
      student and business learners.
- [ ] Paywall comprehension and Continue-free behavior meet accepted guardrails.
- [ ] Android device and accessibility matrix is complete.
- [ ] No critical TalkBack, Back, process-restoration, permission, privacy,
      tenancy or progress-truth blocker remains.
- [ ] `make verify`, critical `make e2e` and `make build` pass at the final
      candidate commit.
- [ ] `docs/STATUS.md`, `docs/DECISIONS.md` and `docs/QUALITY_REVIEW.md` record the
      exact completed state and known limitations.
- [ ] Production, Play release and live payment remain blocked unless separately
      authorized and evidenced.

## 16. External platform and benchmark references

- [Android layouts and navigation patterns](https://developer.android.com/design/ui/mobile/guides/layout-and-content/layout-and-nav-patterns)
- [Android runtime-permission workflow](https://developer.android.com/training/permissions/requesting)
- [Android UI state preservation](https://developer.android.com/topic/libraries/architecture/saving-states)
- [Android predictive Back](https://developer.android.com/guide/navigation/custom-back/predictive-back-gesture)
- [Behavior changes for apps targeting Android 16 — predictive Back enabled by default](https://developer.android.com/about/versions/16/behavior-changes-16)
- [Android runtime permissions — request in context](https://developer.android.com/training/permissions/requesting#principles)
- [Android accessibility guidance](https://developer.android.com/guide/topics/ui/accessibility/apps)
- [Android themes and system preferences](https://developer.android.com/design/ui/mobile/guides/styles/themes)
- [Duolingo learning-flow overview](https://blog.duolingo.com/duolingo-101-how-to-learn-a-language-on-duolingo/)
- [Babbel learning structure](https://support.babbel.com/hc/en-us/categories/19329071910290-Learning-with-Babbel)
- [Memrise progress and activity recommendation model](https://www.memrise.com/blog/new-progress-tracking-for-language-learning)
- [Octalysis Core Drive 2](https://yukaichou.com/gamification-study/8-core-drives-gamification-2-development-accomplishment/)
- [Octalysis Core Drive 8](https://yukaichou.com/gamification-study/8-loss-and-avoidance/)

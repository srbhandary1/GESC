# DARK-TALK-05 review — 25 September 2026

## Delivered behaviour

Everyday Talk headings, notices, coaching selectors, correction cards and Speak
mode tabs use matched theme foreground/background tokens. Dark secondary actions,
disabled lesson controls, allowance focus states and sign-in controls remain
readable. Light-mode surfaces receive regression coverage.

Free Speech offers “Read in my selected language” beneath English AI bubbles when
the dialogue uses a supported non-English guidance locale. Clicking explicitly
requests a text-only explanation of that exact opening/reply. The English source
stays visible; reading neither advances the conversation nor generates TTS. It
preserves an unsent draft, can recover with the English source, and clears with
session expiry/exit. Listening again still targets the English audio.

## Files changed in this slice

- `apps/web/src/theme/{jezwebLearnerChrome,themeTokens}.css` and
  `apps/web/src/conversation/{InteractiveDialogue.tsx,ConversationJourney.css}`.
- `apps/api/app/Conversation/Dialogue/{ConversationDialogueService,DialogueModelPolicy,DialogueSpeechService,FakeDialogueResponder}.php`,
  `ConversationTurnService.php`, the orchestrator, Azure model response schema and turn request
  validation.
- `docs/api/openapi.yaml`, generated `packages/contracts/src/generated/types.gen.ts`,
  six interface catalogues and their governance hashes.
- API policy/request/integration tests, dialogue component tests, and learner,
  Speak, lesson/subscription and sign-in browser tests.
- Android version/download metadata, master v3.19, ADR-0042 clarification,
  `DARK_TALK_05_SPEC.md`, this report, STATUS, DECISIONS and QUALITY_REVIEW.

Earlier authorized changes in the dirty workspace remain intact.

## Visual audit steps

Current-session captures are outside the repository in
`/tmp/gse-dark-talk-audit/`. No learner content is committed.

1. **Home** — heading, daily streak and all five navigation labels readable.
   `01-before-home.png` captured the installed baseline.
2. **Learn** — levels, Introduction and Practice captured; lesson/checkpoint and
   subscription variants also exercised by browser fixtures. Candidate captures
   `4-candidate-learn.png`, `08-candidate-lesson.png`, `09-candidate-practice.png`.
3. **Speak** — baseline `02-before-everyday.png` proves white text on white tiles
   and faint unselected tabs. Candidate `5-candidate-speak.png` shows the matched
   dark surfaces. Browser checks cover Everyday, A situation and Ask Swathi,
   consent, active chat and reading aid in both light and dark themes.
4. **Progress** — readable cards and totals (`6-candidate-progress.png`).
5. **Tuition** — readable labels, fields and action (`7-candidate-tuition.png`).
6. **Account/subscription** — bounded account menu, allowance, plan/tax text and
   focus state checked. The initial `10-candidate-subscription.png` and `11-candidate-subscription-visible.png` did not show
   the opened panel and was rejected as subscription evidence.
7. **Sign-in** — synthetic signed-out browser checks cover light and dark forms;
   the live phone's signed-in state is preserved.

Axe runs in-place (`setLegacyMode`) for the new contrast journeys. Android CDP
cannot create the secondary page used by the normal runner. The checks wait for the selected theme, loaded fonts and a rendered frame where
needed; early reads during painting produced transient foreground measurements.
In-place checks validate the themed DOM, and reduced motion prevents measuring
an intermediate theme transition. This is contrast regression evidence, not a claim
of complete accessibility compliance or every possible content/account state.

## Accounting answer and remaining gap

Student time already includes validated submitted speaking duration and actual
AI playback duration (ADR-0043). STT and TTS do not add arbitrary second charges;
model tokens are recorded separately. Text-only explanation uses model tokens
but no speaking/listening time.

Provider expense is a different ledger: Sarvam reports audio milliseconds and
Azure TTS reports Unicode characters; model input/output tokens are separate.
The live read-only audit found **zero installed speech rate cards** and
`rate_missing`/`usage_unclassified` observations. Adapter estimates do not replace
invoice reconciliation; Sarvam's legacy estimate field is zero even when audio
usage is billable. Do not interpret that as free usage. Hidden failed/fallback
attempts still require the previously recorded reconciliation work.

Next accounting checkpoint: install source-backed immutable rate cards, preserve
all physical provider attempts and reconcile recorded usage against invoices.
The Azure quota/MFA and 1,000-speaker capacity limitations remain open.

## Security, tenancy and configuration

No migration, secret, price, allowance, authentication, permission or retention
change. New optional request/response fields select only an opaque opening/turn
reference; the server resolves the original content within organization, learner
and session scope. Explicit permission, original-language/script validation,
safety, quota and idempotency checks remain in place. Translations/source copies
use existing encrypted private recovery and volatile UI state; no ordinary cache,
log, browser storage or permanent transcript history is introduced. Locale copy
and generated explanations still require independent language review.

## Verification and release evidence

- Final `make verify` after v16 passed: SQLite **358 tests/3,302 assertions**,
  PostgreSQL **437/5,868**, **545 web tests**, localization (38), design-system
  (18), marketing (4), static analysis, contracts, security and runtime checks.
  The focused adapter/policy check passed 27 tests/143 assertions. The first v16
  verify stopped on docblock formatting; Pint fixed it and the full rerun passed.
- `make e2e`: 44 browser journeys and four API tests/103 assertions passed.
- `make ai-eval` after prompt v16: 15 locked cases and 30 dialogue tests/244
  assertions passed. Physical speech benchmark was not run.
- Production `make android-closed-beta`, seven native tests, APK verification,
  `make build` and final web lint passed. Existing large-JS warning and omitted
  unlicensed 3D avatar asset remain.
- Android **1.0.4/code 5** installed on the connected RMX3085 with application data
  preserved. APK: 15,270,043 bytes, SHA-256
  `c96865641d1c9b4da08fe0e57efa44ecea74e5f35a88df3d9b04d65a30877da8`.
- Active invite-only release: `dark-talk-20260925-r3`; shared web/APK unchanged from
  r1. Public JSON readiness, web/APK hashes and non-swappable private recovery
  mount checks passed. No migration or configuration change. The first r1
  cutover rolled back because the verification script expected `ok` instead of
  the endpoint's correct `ready`; corrected verification and subsequent cutovers
  passed. r2 added source-isolated translation; r3 also removes duplicate correction
  teaching clauses before persistence/audio, with v16 policy and focused regression coverage.
- Published download:
  `https://ai.globalspokenenglish.com/downloads/global-spoken-english-android.apk?v=contrast-language-20260925.1`.
  Web index SHA-256:
  `a1b0a79ea32eb90c07f7270ceb627776128dc10e7c10c6542ff4670e7c44a136`;
  v16 backend patch archive SHA-256:
  `410390184fdf73065394ba17fcbc8fb04eed781f39f75984047c6790e97fa7b2`.
- Installed captures `20-installed-home.png`, `21-installed-learn.png`,
  `22-installed-progress.png`, `23-installed-tuition.png` were inspected and
  accepted. `24-installed-speak.png` showed the corrected colors but a transient
  API error during cutover, so it is not clean final Speak evidence. Final
  `32-installed-speak-clean.png` was inspected and accepted: Everyday Talk title,
  coaching selector, all mode tabs and lower navigation are readable, with zero
  alerts. Session exit cleared the reading UI; the phone was reloaded and restored
  to Kannada/dark Home, removing temporary inspection instrumentation.

### Live reading check and repair

The first real reading request exposed a semantic defect despite synthetic tests:
AI acknowledged the translated button label instead of translating only the
English source. The v15 adapter now sends only the server-resolved source bubble
and target locale for this operation, excluding button text, unrelated history
and memory. A payload regression verifies that exclusion and strict no-correction
schema. Live v15 opening translation preserves the greeting and question in
Kannada without the consent acknowledgement. Native HTTP calls are not visible
as browser CDP response events; the initial automation timeout was corrected by
waiting for UI completion and auditing server ledger metadata.

The accepted installed opener capture is `30-reading-1.png`. The initial
`30-reading-3.png` clipped the wrong region while the Android keyboard was open
and was rejected as visual evidence. Seven v15 interactions completed without
transport errors: three readings
(opening and two distinct multi-sentence replies), topic introduction, a word
memory question, successful shorter topic recall, and an intentional grammar
error. The seventh exposed duplicate teaching in the main reply and correction
card. v16 adds a narrow removal of an explicit repeated correction clause before
persistence/TTS; remaining topic text is preserved, and unresolvable duplicates
fail the existing safe-output gate. It never changes assessment or provider usage.
Unit cases cover unsafe/empty outputs and roleplay isolation; the wrong-then-fixed
integration fixture now injects the duplicate and proves its removal. A live v16
new mistake produced one separate correction card and a topic-only AI reply.
The next request and its replay were rejected before any new turn/provider row:
existing session ceilings are eight AI requests and 12,000 cumulative model tokens
checked before dispatch. This session had eight completed requests/12,949 model
tokens. Reading currently counts toward those resource ceilings despite preserving
conversational position and charging no audio minutes. The generic UI error does
not explain the ceiling; a clear limit/new-session path and separate translation
request budget are recorded for the next checkpoint. No limit was raised or
bypassed for this verification. A fresh v16 session then completed the intentional
mistake and correct retry: one correction card for the error, followed by a normal
English topic reply and zero correction cards on the corrected turn. Across the
two sessions, ten model-backed interactions completed (three readings/seven typed
replies); two additional submissions hit the existing session ceiling before any
new provider call. These are live model/TTS and UI checks, not microphone/STT or
independent pronunciation/language-quality benchmarks. Server ledger metadata
confirms all three reading requests used model tokens and no TTS provider stage;
ordinary replies used Azure TTS, and typed input used the local confirmed-text
adapter rather than a paid STT call. Only synthetic QA text
is retained in local temporary evidence; no transcript is added to this repo.


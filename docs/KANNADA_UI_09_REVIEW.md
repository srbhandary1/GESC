# KANNADA-UI-09 review — 26 September 2026

Status: implemented and verified for the invite-only pilot.

## Behaviour and files

- Kannada catalogue copies on Home/onboarding, coach labels and marketing plans
  now contain Kannada. A regression check excludes only deliberate brand names,
  language autonyms, prices, numbers and abbreviations from copied-English checks;
  the shared account-security messages are included, while the optional English
  operator console is outside this learner-facing scope.
- `learnerLabelKeys`, `LevelPathPanel` and `ProgressDashboard` resolve descriptions
  for all 17 known concepts through the selected-language UI catalogue. English
  grammar tokens such as Can and Should remain visible as the learning content.
- `LevelPathPanel` uses already-attached Kannada guidance as its primary lesson
  heading and tutor-entry context for Kannada. It does not edit published content,
  invent a translation or change what the model receives. English fallback remains
  when guidance is absent. Other locale selections retain source lesson guidance.
- `roleplayCatalog`, `RoleplayJourney` and `InteractiveDialogue` localize the 12
  known scene names and role pairs, including the active conversation header.
  Active roles are translated only when the exact server pair matches the known
  UI label; an unknown pair preserves the server's English text.
- `SubscriptionPanel` localizes known plan names by stable plan code, including
  current subscription, selection buttons and talk packs. It preserves identifiers,
  prices, allowance, checkout authorization and unknown custom plan names.
- `LearnerJourney` marks the translated Home instruction with its resolved locale
  instead of an English screen-reader language tag.
- Six catalogue files and governance hashes/revisions are updated to
  `ui-*-2026-09-26.3`; every new interface key has translated variants. All remain
  honestly pending independent review under existing ADR-0005 pilot-preview policy.
- Android version and homepage download metadata are updated. No API, contract,
  schema, migration, environment variable, provider prompt or retention change.

## Verification

- Focused component/catalogue tests: 65 tests across six files passed, covering
  Kannada labels, primary lesson guidance, English switching, preserved checkout
  identity and copied-English regression detection.
- Six-locale validation passed with the five expected pending-review warnings.
- Browser suite: 47 journeys and four API tests/103 assertions passed. Added
  Kannada roleplay coverage before/after start, with the English practice reply
  intact. An initial Home assertion matched several translated Swathi strings;
  the test now targets the exact name. The full rerun passed.
- Final verification, builds, signatures, release hashes and physical phone checks
  are recorded below. AI evaluation is not rerun: provider prompts,
  models and orchestration are unchanged.

## Boundaries and next checkpoint

This repairs learner interface language and promotes existing Kannada guidance.
English practice examples, model replies, learner input, proper names and some
published scenario/check instructions remain source English. It does not claim
that every curriculum instruction has an approved Kannada counterpart. No
published record, marks, progression, tenant scope or consent behavior changes.

Recommended next checkpoint: source-linked Kannada translations of the remaining
curriculum scenario/check instructions through the governed content workflow.
Independent linguistic review remains required; engineering completion is not
language-review approval. Previously open speech-provider reliability, usage-cost
reconciliation and 1,000-speaker capacity work remain unchanged.

## Release validation

Final candidate: `kannada-ui-20260926-r2`, Android **1.0.8/code 9**.
APK size: 15,295,336 bytes; SHA-256:
`15617cbb7a1b39b41c123d9045a8da40d7e274dba64d6051cd943766ff6d0a0a`.
Public index SHA-256:
`617b93c1161a55afb74546d2d4bb16afa4d06ff9ada21e3a62cf129db84f84f1`.
Web source fingerprint:
`49a513e5f09bc9404b8b157406579666913aeb0943e1734b8e09e560a40d0a1a`.

Final Android build/signature and web build passed; all 13 native JVM tests passed.
The first full verify run passed PHP/static/contracts/security/type checks but
found one stale demo test expecting the untranslated English button. That selector
was corrected; the complete workspace rerun passed (561 web, 38 localization,
18 design-system and four marketing tests). Final `make verify` passed for the final catalogue revision: SQLite 363 tests /
3,360 assertions; PostgreSQL 441 / 6,442; 561 web tests plus all static, security,
contracts, locale, lint and type checks.

Physical Android checks showed Kannada descriptions for Levels 1 and 2, the
existing Kannada Level 2 guidance with `lang=kn-IN`, all 12 localized roleplay
scene buttons, Kannada receptionist/patient roles, Kannada Home guidance and
localized current/available plan names. Account menu, plan and privacy labels
were Kannada. The ordinary password-security panel is web-only under the existing
authentication abstraction; a phone check for that panel timed out because it is
not rendered on Android. Its translated messages are covered by catalogue and web
checks, not claimed as phone-verified. No credentials or consent settings changed.
The update preserved account data, Kannada selection and dark theme.

The final homepage link is:
https://ai.globalspokenenglish.com/downloads/global-spoken-english-android.apk?v=kannada-ui-20260926.2

Final cutover to `kannada-ui-20260926-r2` passed public readiness, exact index and
APK hashes, and the recovery-memory mount check. Transient readiness requests
retried during service restart before passing. The public homepage's Android
anchor resolves to the final version URL above. Installed phone APK hash matches
the published artifact. The phone was left on the Kannada Learn level picker;
its Mac ADB tunnel remains available. No synthetic conversation turns, provider
calls, checkout or learning-completion operations were needed for this checkpoint.

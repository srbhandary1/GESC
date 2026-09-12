# Repository status

## Current checkpoint

| Field | Value |
|---|---|
| Checkpoint | Pre-F0 Phase 1 evidence-closure under APP-0034 |
| Status | Pre-F0 evidence-closure authorized ([APP-0034](APPROVALS.md#app-0034) / D-0114); Active step **F0** (evidence work, not DoD sign-off) |
| Canonical baseline | `ENTERPRISE_PRODUCT_MASTER_PLAN.md` version 3.1 |
| Updated date | 12 September 2026 |
| Git branch | `codex/confidence-conversation` |
| Product code | PR-008 through PR-015 bounded ops accepted; pre-F0 evidence in progress; F0 DoD / Play unauthorized |
| Next checkpoint | Execute APP-0034 evidence closure (Android assemble/matrix, drills, §27 matrix). F0 Exact-commit DoD accept remains unauthorized |
| Professional client path | APP-0016–0034; production/Play not claimed ([PROFESSIONAL_CLIENT_SETUP.md](PROFESSIONAL_CLIENT_SETUP.md)) |

## Checkpoint authorization

[APP-0005](APPROVALS.md#app-0005) records `S R Bhandary`'s explicit
Accountable Product Owner acceptance of PR-005 at
`daeee1df7a7e4d31427001aedece76d8121413d6`, with its recorded limitations,
and authorizes only PR-006 — Deterministic daily-speaking slice.

PR-006 does not authorize PR-007 offline bundle/outbox work, AI or
speech-provider orchestration, production deployment or a later checkpoint.

[APP-0007](APPROVALS.md#app-0007) records `S R Bhandary`'s explicit
Accountable Product Owner acceptance of PR-006 at
`598ad387e65b94707564dce1e199c97deb411d82`, with its recorded limitations,
and authorizes only PR-007 decision-resolution work.

[APP-0008](APPROVALS.md#app-0008) records `S R Bhandary`'s explicit approval
of [ADR-0006](adr/0006-offline-retention-isolation-and-conflict-policy.md) in
the consolidated Technical Decision Owner, Security Reviewer, Privacy
Reviewer and Tenancy Reviewer development roles. DR-0004 is resolved and only
PR-007 implementation is authorized. The consolidated roles are not
independent production review.

[APP-0009](APPROVALS.md#app-0009) records `S R Bhandary`'s Accountable
Product Owner acceptance of PR-007 at the exact implementation commit
`e0e2f1b90e0eb4ecf680f5243b57ec0731c32648`, with every recorded limitation.
It does not authorize PR-008, production or any later checkpoint.

[APP-0010](APPROVALS.md#app-0010) separately authorizes only PR-008 —
Conversation domain with deterministic fake providers. Real providers,
provider SDKs, audio upload/persistence, PR-009, production and AI authority
over deterministic learning records remain unauthorized.

[APP-0011](APPROVALS.md#app-0011) records `S R Bhandary`'s exact-artifact
acceptance of
[ADR-0007](adr/0007-recording-consent-retention-provider-processing-and-deletion-policy.md)
in the consolidated Technical Decision Owner, Security Reviewer, Privacy
Reviewer and Tenancy Reviewer development roles. It resolves DR-0005 without
accepting PR-008, authorizing PR-009 implementation, selecting a real provider
or approving production.

[APP-0012](APPROVALS.md#app-0012) records `S R Bhandary`'s Accountable
Product Owner acceptance of PR-008 at
`11f7b597ad2db3b8cf65a1394c1171a8280045d1` with its recorded limitations and
authorizes only PR-009 implementation within accepted ADR-0007. It does not
approve a provider, production, PR-010 or any later checkpoint.

[APP-0013](APPROVALS.md#app-0013) records `S R Bhandary`'s exact-artifact
approval of [ADR-0008](adr/0008-azure-speech-development-provider-and-benchmark-policy.md)
and the [locked PR-009 benchmark plan](PR-009_SPEECH_PROVIDER_BENCHMARK_PLAN.md)
in the consolidated Technical Decision Owner, Security Reviewer, Privacy
Reviewer and Tenancy Reviewer roles. DR-0011 is resolved and only the remaining
PR-009 Azure adapters and benchmark implementation are authorized. No
credential, live request or provider spend is approved.

[APP-0014](APPROVALS.md#app-0014) records `S R Bhandary`'s exact-artifact
approval of
[ADR-0009](adr/0009-synthetic-tts-listener-fixture-review-workflow.md) in the
consolidated Technical Decision Owner, Security Reviewer, Privacy Reviewer and
Tenancy Reviewer roles. DR-0012 is resolved and only its bounded
prepare/review/complete/purge implementation within PR-009 is authorized. No
credential, provider spend, live request, benchmark success, PR-009
acceptance, learner data, PR-010 or production is authorized.

[APP-0017](APPROVALS.md#app-0017) records `S R Bhandary`'s Accountable Product
Owner acceptance of PR-009 with its recorded limitations and live synthetic
benchmark pass `e1dc9d3c8daff9e33c20bdd00d2dd4d0`, and authorizes only
PR-010 — AI orchestration, correction and Tutor beta. It does not authorize
PR-011, production or Google Play release.

[APP-0018](APPROVALS.md#app-0018) records `S R Bhandary`'s Accountable Product
Owner acceptance of PR-010 at
`13eb5ac56f04402a2571346e9fd8e24f1a05ea80` with every recorded limitation. It
does not authorize PR-011, real model providers, production or Google Play
release.

[APP-0019](APPROVALS.md#app-0019) records `S R Bhandary`'s Accountable Product
Owner authorization to begin only PR-011 — Evaluation harness and memory
controls. It is not acceptance of completed PR-011 work and does not authorize
PR-012, production or Google Play release.

[APP-0020](APPROVALS.md#app-0020) records `S R Bhandary`'s Accountable Product
Owner acceptance of PR-011 at
`220628f4d4c69284af0b1f2534e8902f3b272088` with every recorded limitation. It
does not authorize PR-012 implementation, real model providers, production or
Google Play release.

[APP-0021](APPROVALS.md#app-0021) records `S R Bhandary`'s Accountable Product
Owner authorization to begin only the Confidence Conversation enterprise
slice under
[ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) Steps A1–A7
(before PR-012). It is not acceptance of completed A1–A7 work. Nested A4/A5
ADR+APP gates are still required before real conversation-model DI or
ordinary multi-locale TTS spend. Demo D-0092/D-0094 paths are not enterprise
evidence. PR-012, production and Play remain unauthorized.

[APP-0025](APPROVALS.md#app-0025) records `S R Bhandary`'s Accountable Product
Owner acceptance of the Confidence Conversation enterprise slice at
`5582c9ab69d6c6b4409ffade6f0f5b9d94fc4b7b` with every recorded limitation. It
does not authorize PR-012 implementation, live multi-locale TTS spend,
production or Google Play release.

[APP-0026](APPROVALS.md#app-0026) records `S R Bhandary`'s Accountable Product
Owner authorization to begin only PR-012 bounded synthetic role-play under
[PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md](PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md)
and [PR-012_ROLEPLAY_VERIFICATION_SCOPE.md](PR-012_ROLEPLAY_VERIFICATION_SCOPE.md).
It is not acceptance of completed PR-012 work and does not authorize approved
curriculum, expanded live providers, production or Google Play release.

[APP-0027](APPROVALS.md#app-0027) records `S R Bhandary`'s Accountable Product
Owner acceptance of PR-012 bounded synthetic role-play at
`0fb6f2953580ed6154e4d895e128c46167c6f5d4` with every recorded limitation. It
does not authorize approved curriculum, PR-013, production or Google Play
release.

[APP-0028](APPROVALS.md#app-0028) records `S R Bhandary`'s Accountable Product
Owner authorization to begin only PR-013 bounded operator content-workflow and
audit under
[PR-013_OPERATOR_AUTHORIZATION_REQUEST.md](PR-013_OPERATOR_AUTHORIZATION_REQUEST.md)
and [PR-013_OPERATOR_VERIFICATION_SCOPE.md](PR-013_OPERATOR_VERIFICATION_SCOPE.md).
It is not acceptance of completed PR-013 work and does not authorize sampled
speaking review, privacy ops, production or Google Play release.

[APP-0029](APPROVALS.md#app-0029) records `S R Bhandary`'s Accountable Product
Owner acceptance of PR-013 bounded operator content-workflow and audit at
`9220ab27baa6dc29d82caf8e9e97e94f7df7219d` with every recorded limitation. It
does not authorize sampled speaking review, privacy ops, PR-014, production or
Google Play release.

[APP-0030](APPROVALS.md#app-0030) records `S R Bhandary`'s Accountable Product
Owner authorization to begin only PR-014 Capacitor Android closed-beta
foundations under
[PR-014_ANDROID_AUTHORIZATION_REQUEST.md](PR-014_ANDROID_AUTHORIZATION_REQUEST.md)
and [PR-014_ANDROID_VERIFICATION_SCOPE.md](PR-014_ANDROID_VERIFICATION_SCOPE.md).
It is not acceptance of completed PR-014 work and does not authorize Google
Play public release, PR-015, remaining PR-013 sampled review/privacy ops or
real learner production data. Demo APK `com.globalenglish.demo` remains
presentation-only (D-0078).

[APP-0031](APPROVALS.md#app-0031) records `S R Bhandary`'s Accountable Product
Owner acceptance of PR-014 Capacitor Android closed-beta foundations at
`44b0e672e004cf3846cddabc456c21153fa4607e` with every recorded limitation. It
does not authorize Google Play public release, PR-015, remaining PR-013
sampled review/privacy ops or real learner production data. Physical device
matrix, Gradle assemble and `make verify` remain unclaimed.

[APP-0032](APPROVALS.md#app-0032) records `S R Bhandary`'s Accountable Product
Owner authorization to begin only PR-015 bounded operations hardening and
closed-beta evidence closure under
[PR-015_PRODUCTION_HARDENING_AUTHORIZATION_REQUEST.md](PR-015_PRODUCTION_HARDENING_AUTHORIZATION_REQUEST.md)
and
[PR-015_PRODUCTION_HARDENING_VERIFICATION_SCOPE.md](PR-015_PRODUCTION_HARDENING_VERIFICATION_SCOPE.md).
It is not acceptance of completed PR-015 work and does not authorize F0 Phase 1
DoD, Google Play public release, remaining PR-013 sampled review/privacy ops or
real learner production data.

[APP-0033](APPROVALS.md#app-0033) records `S R Bhandary`'s Accountable Product
Owner acceptance of PR-015 bounded operations hardening and closed-beta
evidence closure at `5eca7515291ea571a7f697dcdc2da6197535f839` with every
recorded limitation. It does not authorize F0 Phase 1 DoD, Google Play public
release, remaining PR-013 sampled review/privacy ops or real learner production
data. Physical device matrix, Gradle assemble, `make verify`, and live
load-soak/backup execution remain unclaimed.

[APP-0034](APPROVALS.md#app-0034) records `S R Bhandary`'s Accountable Product
Owner authorization to begin only pre-F0 Phase 1 evidence-closure under
[F0_PHASE1_DOD_GAP_ASSESSMENT.md](F0_PHASE1_DOD_GAP_ASSESSMENT.md). It is not
acceptance of Phase 1 Definition of Done and does not authorize Google Play
public release, specialist-review waiver, remaining PR-013 sampled
review/privacy ops or real learner production data.

[APP-0006](APPROVALS.md#app-0006) separately authorizes the bounded Phase 1
UI-only expansion in
[ADR-0005](adr/0005-phase-1-multilingual-ui-language-packs.md). It adds
Malayalam, Tamil, Telugu and Hindi implementation previews without accepting
PR-006 or expanding curriculum, speech recognition, model audio, AI
explanations, production deployment or a later checkpoint.

## PR-008 implemented boundary

- The active boundary is
  [the PR-008 conversation fake-provider verification scope](PR-008_CONVERSATION_FAKE_PROVIDER_VERIFICATION_SCOPE.md).
- The slice is limited to organization-scoped versioned prototype
  personas/scenarios, learner sessions/turns, project-owned STT/model/TTS
  ports, deterministic fakes, a state/event stream and privacy-safe zero-cost
  telemetry skeletons.
- Prototype utterance/reply text is request-memory-only. Persistent records
  contain digests and safe state/usage metadata, not conversation content or
  audio.
- Fake adapters are local/testing-only and production must fail closed.
- The authenticated API starts a version-pinned Friend session, runs a bounded
  typed turn through project-owned fake STT/model/text-only-TTS ports and
  returns a safe bounded SSE status history.
- PostgreSQL constraints and services enforce organization plus exact learner
  membership ownership, immutable persona/scenario versions, deterministic
  transitions, actor-bound idempotency, bounded retry and append-only
  status/usage evidence.
- The learner Practice view visibly discloses the AI/fake boundary and
  request-memory-only text handling. It creates no recording, upload, audio
  playback or provider-network path.
- Repository verification passes with 42 SQLite tests/277 assertions, 169
  PostgreSQL tests/1,981 assertions, 57 web tests, 31 localization tests, 9
  design-system tests and 9 Chromium journeys. Dependency audits, formatting,
  PHPStan, locale governance, OpenAPI/generated-client drift, lint, recursive
  type checking and production builds pass.
- GNU Make is not installed on this workstation, so the documented
  `make verify` and `make build` recipes were executed command-by-command from
  the Makefile with the pinned native toolchain. `pnpm run e2e` ran directly.
- PR-009, real provider calls, audio upload/persistence and production remain
  unauthorized.

## Accepted DR-0005 recording policy

[ADR-0007](adr/0007-recording-consent-retention-provider-processing-and-deletion-policy.md)
is the accepted recording-consent, retention, provider-processing and deletion
policy under APP-0011. It defines:

- separate, versioned upload/provider/history purposes and per-turn learner
  submission;
- fail-closed adult eligibility, private quarantine, validation and
  organization plus exact-membership ownership;
- short raw-audio, transcript and generated-media lifetimes with tombstones,
  deletion receipts and provider-deletion reconciliation;
- reviewed, minimized provider requests with training, human review and
  secondary use prohibited in PR-009;
- learner transcript confirmation, correction, discard, re-record and
  text-only recovery without authority over deterministic learning records;
  and
- legal, vendor, independent specialist and explicit release gates before
  production.

The approved proposal SHA-256 is
`e831e09092256d18804e5c6c7292a432ecfb71ac7d52544d16fde8d751a12ff9`.
DR-0005 is resolved. The consolidated development approval is not independent
production review and does not accept PR-008, authorize PR-009 implementation,
select a provider, permit learner-audio processing or approve production.

## PR-009 provider-neutral implementation state

- The active boundary is
  [the PR-009 secure speech verification scope](PR-009_SECURE_SPEECH_VERIFICATION_SCOPE.md).
- Upload and consent collection are disabled by default. They resolve only in
  `local`/`testing` when `VOICE_UPLOAD_ENABLED=true`; the deterministic
  recorded-STT adapter additionally requires
  `VOICE_PROVIDER_DRIVER=deterministic-test`. Every other environment binds a
  disabled adapter and fails closed.
- Adult learners grant the upload/transcription and provider-processing
  purposes separately from a versioned notice. Consent events are append-only,
  actor-, organization- and exact-membership-bound, and replay protected.
- The browser keeps capture volatile, provides local playback, then converts an
  explicitly submitted recording to mono 16-bit 16 kHz PCM/WAV. The server
  verifies RIFF length, chunks, codec, channels, sample rate, byte rate,
  duration and size before using a random private object key and Laravel
  application encryption.
- The testing-only STT port returns a deterministic English candidate and
  confidence. Successful source audio is deleted immediately; provider failure
  retains only encrypted source for at most 24 hours. A scheduled
  `voice:purge-expired` command deletes due source/transcript content and writes
  deletion evidence.
- Learners can accept, correct, discard or explicitly delete a transcript.
  History is off by default. Confirmation never updates marks, attempts,
  mastery, progression or review schedules.
- The Practice UI displays the exact implementation-draft notice and two
  separate consent actions before exposing microphone controls. Text remains
  available on refusal or failure. New voice UI strings in `kn-IN`, `ml-IN`,
  `ta-IN`, `te-IN` and `hi-IN` remain pending independent review; approved-only
  production resolution falls back to English.
- OpenAPI 3.1.1 version 0.7.0 and generated TypeScript contracts cover consent,
  multipart upload, transcript review, recording state and deletion status.
- `make verify` passes with 70 SQLite tests/701 assertions, 173 PostgreSQL
  tests/2,016 assertions, 31 localization tests, 9 design-system tests and 59
  web tests. `make build` passes with 76 Vite modules, and 10 Chromium
  journeys pass against a temporary `127.0.0.1:4173` listener that stops after
  the run.
- PR-009 is not complete or acceptable yet. ADR-0008 resolves the provider
  policy, and the exact STT/TTS adapter plus benchmark harness are now
  implemented. Operational vendor evidence, a separate spend approval and live
  locked benchmark evidence remain outstanding. There is no live provider
  result, generated Azure TTS fixture, streaming quarantine worker, reviewed
  malware scanner, production private object store, provider deletion
  reconciliation or production authorization.
- [Accepted ADR-0008](adr/0008-azure-speech-development-provider-and-benchmark-policy.md)
  selects Azure Speech Fast Transcription REST `2025-10-15` and real-time
  prebuilt TTS in `centralindia` for a development-only benchmark. The
  [locked plan](PR-009_SPEECH_PROVIDER_BENCHMARK_PLAN.md) uses only
  project-authored strings and generated synthetic audio, with no learner data.
- ADR-0008 is accepted and DR-0011 is resolved under APP-0013. No Azure
  credential, subscription contract, DPA/subprocessor snapshot, spend approval
  or benchmark result exists in the repository. The application provider
  binding remains disabled. `make ai-eval` fails before HTTP unless the named
  non-production environment, matching external mode-`0600` vendor/spend
  evidence records, secret reference, committed runner revision and approved
  spend cap all validate.
- The exact non-secret JSON shapes, permissions, environment mappings and
  no-call sequence are documented in the
  [PR-009 benchmark evidence handoff](PR-009_SPEECH_BENCHMARK_EVIDENCE_HANDOFF.md).
  The document is a template only and supplies no evidence or authorization.
- DR-0012 is resolved under APP-0014 by
  [accepted ADR-0009](adr/0009-synthetic-tts-listener-fixture-review-workflow.md).
  Its bounded implementation now provides an authenticated encrypted
  prepare/review/complete bundle, token-protected `127.0.0.1` browser-memory
  review, fixed 24-hour expiry and content-free deletion evidence while
  preserving the 544-operation cap. This decision does not weaken any
  credential, evidence, spend or live-request gate.
- `speech:benchmark` defaults to no-call preflight and exposes explicit
  `prepare`, `review`, `complete` and `purge` phases. The first 96 TTS
  operations are encrypted individually outside repository/web roots and are
  reused by completion, which restores their attempts/spend before the
  remaining 448 operations. Partial preparation and completion replay fail
  closed.
- Review binds only an operating-system-assigned `127.0.0.1` port and uses a
  256-bit URL-fragment token, authorization headers, `no-store`, restrictive
  CSP and memory-only browser blobs. It stores two anonymous disposition slots
  but no reviewer identity, IP, browser identifier or free text.
- Targeted HTTP-fake verification passes with 21 tests/413 assertions,
  including exact 544-operation accounting, no double billing, encryption,
  token/proxy/public-host refusal, tamper/expiry/replay rejection, failed
  deletion visibility, immutable successful receipts and zero database
  queries. No Azure request was made.

## Accepted PR-007 implementation state

- Server-issued bundles bind the exact user, membership, organization,
  enrollment, immutable publication, mission hashes and deterministic policy
  to a signed maximum-24-hour lease. Bundle and problem responses are
  `private, no-store`.
- One atomic `POST /api/v1/offline-completions` command revalidates current
  authorization and publication state, composes the ordinary PR-006
  start/respond/submit services in one PostgreSQL transaction and returns the
  ordinary authoritative result. Exact replay is stable and changed replay
  conflicts.
- Persistent learning remains off until localized personal-device
  confirmation. Protected metadata, verified model audio and pending commands
  use opaque per-account IndexedDB partitions with non-exportable AES-GCM
  keys; credentials, raw audio and display identifiers are excluded.
- The service worker caches only the same-origin public shell and never
  intercepts `/api/`, `/sanctum/` or `/oauth/`. Reconnect uses an exclusive
  browser lock while server idempotency remains final authority.
- Web and Android logout adapters delete offline partition keys before server
  revocation. The current product still has no learner-facing logout/account
  management screen.
- [ADR-0006](adr/0006-offline-retention-isolation-and-conflict-policy.md)
  requires explicit personal-device opt-in, shell-only caching on shared
  devices, encrypted per-account IndexedDB partitions, a maximum 24-hour
  offline authorization lease and key-first logout purge.
- The accepted retention policy permits mission bundles for at most 30 days
  after online revalidation. Pending learner commands remain active for 30
  days and then become read-only until same-account reauthentication and sync
  or explicit discard; saved work is not silently deleted.
- PostgreSQL remains authoritative. Offline commands use exact idempotent
  replay, atomic acceptance and visible conflicts; last-write-wins is
  prohibited.
- The active implementation and verification boundary is
  [the PR-007 offline bundle verification scope](PR-007_OFFLINE_BUNDLE_VERIFICATION_SCOPE.md).
- PR-007 is accepted at
  `e0e2f1b90e0eb4ecf680f5243b57ec0731c32648` under APP-0009. APP-0010 now
  authorizes the separate fake-only PR-008 boundary; production,
  recording upload/persistence and real AI/provider paths remain
  unauthorized.
- Repository verification passed: 39 SQLite tests/264 assertions, 164
  PostgreSQL tests/1,883 assertions, 54 web tests, 31 localization tests,
  9 design-system tests and 8 Chromium journeys. Formatting, PHPStan,
  dependency audits, locale governance, OpenAPI/generated-client drift,
  lint, type checking and production builds passed.
- The `make verify`, `make e2e` and `make build` wrappers could not run because
  this workstation currently has no `make` executable. Their exact Makefile
  constituent commands were run successfully with the pinned toolchain.

## PR-006 bounded implementation baseline

- Learner profiles, goals, attempts, evidence summaries, mastery and review
  records remain organization-owned and self-only. Learning starts only from
  the authenticated learner's active membership and active enrollment with an
  exact immutable PR-005 publication pin; a learner cannot assign or change
  that pin.
- First/latest/best is calculated across submitted formative attempts for the
  exact learner membership, enrollment, publication and mission. First is the
  lowest server attempt number, latest is the highest, and best is the highest
  deterministic score with the earliest attempt number winning a tie.
- PR-006 accepts only an immutable publication containing exactly one mission;
  zero, multiple or guessed additional missions fail closed.
- Knowledge mastery and spoken mastery are distinct. A deterministic check may
  advance knowledge mastery and schedule review. A browser local-capture
  receipt is non-authoritative and cannot award spoken mastery.
- Raw learner audio remains only in volatile browser memory for playback and
  retry. It is not uploaded, cached offline, persisted or logged. Recording
  upload, consent history, retention, deletion and provider processing remain
  governed by ADR-0007 and gated by separate PR-009 authorization.
- PR-007 offline bundle/outbox behavior and every AI or speech-provider path
  remain excluded.
- APP-0005 approves implementation scope, not curriculum content or media. The
  canonical synthetic seed remains unpublished. Browser golden-path coverage
  may use contract stubs and synthetic test fixtures, while backend authority
  is verified against PostgreSQL.
- Detailed implementation rules, verification evidence and the exit gate are in
  [the PR-006 verification scope](PR-006_DETERMINISTIC_DAILY_SPEAKING_VERIFICATION_SCOPE.md).

## PR-006 verification state

PR-006 implementation and repository-controlled verification are complete.
Backend authority passed PostgreSQL integration and concurrency coverage; the
browser contract journey passed in Chromium; contracts, builds, migration
exercise and local Markdown links passed. APP-0007 accepts the exact
implementation commit `598ad387e65b94707564dce1e199c97deb411d82`. PR-007
implementation is accepted separately at
`e0e2f1b90e0eb4ecf680f5243b57ec0731c32648` under APP-0009.

## Accepted PR-005 authorization history

[APP-0004](APPROVALS.md#app-0004) accepts
[ADR-0004](adr/0004-content-ownership-publication-and-curriculum-sharing.md)
at `d9d7cba4b2ccc0d9f2e8656e5fd19bab9ce9dc99`, resolves DR-0003 and authorizes
implementation of only PR-005. `S R Bhandary` exercised every recorded
development role. Those consolidated roles are explicitly non-independent
review and do not waive pre-production specialist gates.

## PR-005 implementation

- Four additive migrations create organization-owned curriculum structure,
  append-only source/localized/graph revisions, appointments, reviews,
  media-rights evidence, immutable publication components and events, sharing,
  clone lineage and atomic enrollment pins.
- Content authors and Kannada translators use project-owned append commands
  with exact heads, hashes, permissions, transactions, idempotency, audit and
  outbox evidence. Published versions are sealed.
- Publication derives a closed, canonical manifest from the exact graph,
  revisions, reviews and rights records. Immediate publish, future scheduling,
  activation, retirement and rollback use aggregate locks and optimistic head
  versions. Immediate publication records both scheduled and activated events;
  a scheduled-only snapshot cannot become a rollback target.
- Activation rechecks reviews, authority invalidation and rights at the
  effective instant. Missing, stale, rejected or withdrawn graph/content
  evidence and rights expiry/withdrawal fail without command, audit, outbox or
  active-head residue.
- Exact source identity, immediately preceding revision lineage and accepted
  authority kind/event are enforced in PostgreSQL as well as domain commands.
  Program-scoped successor rights can support a new publication without
  rewriting the evidence pinned by an earlier one.
- Explicit sharing distinguishes preview, enrollment and clone uses. Ordinary
  withdrawal prevents new use while existing pins remain on their immutable
  publication.
- Owner and named-platform emergency suspension use fresh privileged MFA and
  append-only events, block new and existing delivery, and also cover retained
  pins after retirement. Each event records the authentication session and a
  safe remediation reference; platform events also link the audit record
  created before organization context is established. Signed platform
  authorization nonces are consumed once; an exact command replay returns the
  stored result without creating another privileged audit event. A compliant
  replacement can advance while the program remains suspended and does not
  restore delivery implicitly.
- Invite acceptance and pre-content enrollment migration now use
  `pending_curriculum`; activation assigns exactly one immutable owned or
  shared publication.
- The safe foundation seed is a synthetic unpublished mission draft with no
  Kannada review, academic approval, media or learner data.
- Detailed implementation and recovery evidence is in
  [the PR-005 verification scope](PR-005_CONTENT_PUBLICATION_VERIFICATION_SCOPE.md).

## Accepted PR-004 baseline

- framework-independent flat `kn-IN` and `en-IN` UI catalogues with exact key
  parity, typed keys, source-linked revision/hash evidence and deterministic
  English fallback;
- a strict `approved-only` production default, explicit development/review
  preview policy, visible pending-review/fallback states and privacy-safe
  structured fallback events;
- Unicode NFC normalization that preserves ZWJ/ZWNJ and validator coverage for
  locales, keys, blank messages, placeholders, hashes, source status and honest
  independent-review claims;
- self-hosted Noto Sans Kannada Variable 5.3.0 font assets and a Kannada-first
  font stack with wrapping, line-height, reduced-motion and 44 CSS-pixel target
  tokens;
- `MixedLanguageText`, `ApplicationFrame`, `SkipLink`,
  `PrimaryNavigation`, `AudioControls` and `RecordingControls` presentation
  components;
- an accessible responsive Home/Learn/Practice/Progress shell with semantic
  landmarks, per-span language metadata, ephemeral locale selection, direct
  hash navigation and focus management;
- route-preserving skip-link behavior and a localized, focus-managed
  application error fallback;
- browser checks for both locales, axe, keyboard navigation, 320/360/412/768/
  1280 CSS-pixel widths, enlarged-text stress proxies, 44-pixel targets,
  Kannada font-resource loading, reduced motion and credential-storage
  absence;
- centralized ESLint coverage for the web, localization and design-system
  sources, locale validation in `make verify`, and the accessibility browser
  journey in CI.

## Localization release state

- The `en-IN` catalogue is the governed source at revision
  `ui-en-IN-2026-09-12.2`.
- The `kn-IN`, `ml-IN`, `ta-IN`, `te-IN` and `hi-IN` catalogues are
  attributed Codex implementation drafts at revision `ui-*-2026-09-12.2`.
- Visible product name is **Global English Speaking Course**.
- Interface language picker options use native endonyms (ಕನ್ನಡ, English,
  हिन्दी, മലയാളം, தமிழ், తెలుగు) in every catalogue.
- Demo Phase 1 chrome, notices, rooms, attendance status and AI Teacher
  replies resolve from the selected interface locale (`en`/`kn`/`hi`/`ml`/
  `ta`/`te`); the AI toggle is that locale’s endonym vs English. English quiz
  stems remain English learning content.
- Under D-0092, local `make demo` may synthesize Azure neural TTS for AI
  Teacher, Daily audio, Friend Listen and model audio via `/demo/tts`, with
  voice selected from the UI language map; browser speech is fallback. See
  [DEMO_AZURE_TTS.md](DEMO_AZURE_TTS.md).
- Under D-0094, local `make demo` Practice Friend may call OpenRouter free
  models via `/demo/chat` when `OPENROUTER_API_KEY` is configured; otherwise
  scripted pattern replies remain. See [DEMO_OPENROUTER.md](DEMO_OPENROUTER.md).
  Ordinary app LLM bindings stay unauthorized.
- All five translated catalogues remain `pending-independent-review`: no
  reviewer, approval time or independent-review claim is recorded.
- Ordinary production builds use `approved-only`, set the document language to
  the resolved English locale and show a generic fallback notice while
  retaining the selected draft locale as the requested locale.
- Development, tests and an explicit
  `VITE_UI_TRANSLATION_PREVIEW=true` review build may render a current draft
  only with the visible preview notice. That flag is not authorized for
  production deployment.
- Kannada remains the default requested interface locale. Malayalam, Tamil,
  Telugu, Hindi and English are additional interface selections. Self-hosted
  Noto Sans variable fonts cover Kannada, Malayalam, Tamil, Telugu and
  Devanagari scripts.
- UI catalogues are separate from curriculum, mission, transcript and media
  content. Those belong to the accepted PR-005 versioned publication
  foundation. English remains the learning language; curriculum, speech
  recognition, model audio and AI explanations are unchanged.

## Security, privacy and tenancy state

- Every PR-006 learner write must be bound to the membership-derived
  organization context, the actor's exact learner membership and active
  enrollment, and that enrollment's immutable publication pin. Learner input
  cannot establish organization, learner, curriculum owner, publication,
  attempt number, score or mastery authority.
- Raw capture bytes remain local and volatile. The bounded capture receipt
  contains no audio, transcript, filename, blob URL, media-device detail or
  provider payload and is explicitly ineligible for spoken mastery.
- PR-006 attempts and response evidence are append-only after submission.
  Actor/fingerprint-bound idempotency and transactional serialization must
  protect numbering, finalization, summary, mastery and review updates.
- Curriculum/content ownership is non-null and tenant-scoped. Owner commands
  require active membership plus the exact permission; academic/language/rights
  evidence additionally requires an accepted scoped appointment. Authoritative
  command rechecks lock the selected membership, user, organization, role and
  permission-assignment rows so concurrent revocation cannot pass between the
  check and protected write.
- Recipient access uses a dedicated grant resolver and does not disable tenant
  scopes. Shared and pinned access use a single trusted evaluation instant and
  locked grant/pin, publication, owner, head, activation and evidence state.
  Enrollment rows retain their own organization and separately record the
  curriculum owner.
- Platform emergency authority is deny-by-default, names stable active user
  IDs, and binds the actor, fresh privileged session, target owner, program,
  action, reason and expiry in a signed authorization. Its nonce is unique in
  append-only platform audit history, and PostgreSQL validates the exact
  session/audit relationship on emergency events.
- Audit/outbox records contain safe identifiers and keys only. They contain no
  curriculum body, Kannada text, learner data, media bytes, credentials or
  provider payload.
- Locale selection is memory-only. It is not written to `localStorage`,
  IndexedDB or an ordinary Capacitor preference, and no authentication
  credential path changed.
- Fallback events contain only event type, message key, requested/resolved
  locale, policy, reason and requested catalogue revision. They contain no
  message text, parameters, learner content or provider payload.
- Tenant-supplied organization names use `<bdi lang="">`; the shell does not
  infer a language or rewrite organization data.
- The PR-004 audio and recording controls remain presentation components.
  PR-006 composes them with project-owned browser adapters: model audio is
  fetched only from the authenticated exact-pin route, while raw learner
  recording uses `MediaRecorder` only after an explicit action and remains in
  volatile browser memory for local playback/retry. No provider SDK or
  persistent learner-audio path was added.
- No real learner data, captured audio, unlicensed media, provider SDK or
  later-checkpoint authoritative record was added.

## Student-facing IA (presentation)

The disclosed demo learner shell was decluttered for day-to-day student use.
This is a UI/UX refinement of the existing D-0081 Phase 1 presentation
surface. It does not authorize production, Play, real providers or F0 DoD.

What moved:

- **Home** shows one next-lesson CTA, a short level/streak summary, and
  secondary links (Speak, Levels, AI Teacher, Profile). The six-module
  dashboard dump and Phase 1 pill nav are gone.
- **Learn** still runs the deterministic mission first, then a compact
  current-level room list.
- **Profile / About** holds institute orientation, attendance reports and
  notices.
- **AI Teacher** keeps today’s task, current-level reminder and next-step
  motivation. Course-structure copy lives on Profile.
- Student chrome now uses design-system dark mode, 48px targets, and
  skeleton/error/retry on Learn, Progress and Practice async states.
  Presentation-only; not F0 or Play evidence.

## Local synthetic demo

- `make demo` starts a Vite-development-only preview on `127.0.0.1:5173`.
  It does not start or require Laravel, PostgreSQL, Redis, object storage or a
  provider.
- The preview uses explicit in-memory learner identity, mission, result,
  progress and deterministic Friend fixtures adapted from the already
  disclosed contract-test journeys. It writes no authoritative learning or
  tenant record and resets on reload.
- The demo opens on Home with the recommended synthetic mission, includes a
  generated local WAV tone, supports local browser recording/playback and the
  no-recording path, shows synthetic objective progress on the Progress
  destination, and returns deterministic zero-cost text-only Friend replies
  (rotating follow-up questions plus a tiny curated set of gentle pattern
  tips that are scripted demo content, not grammar analysis).
- A visible localized notice states that the fixtures are synthetic and use
  no learner data, real AI, approved curriculum or production service. All
  five non-English notices remain implementation drafts pending independent
  human review.
- `VITE_LOCAL_DEMO=true` is effective only in a Vite development runtime or
  in the explicit `vite build --mode demo` distribution build (D-0077). The
  ordinary production build mode cannot activate the demo adapters; unit and
  configuration tests enforce the boundary in both directions.
- This preview is presentation evidence only. It is not a full-stack
  published-content journey, human curriculum approval, Azure benchmark,
  checkpoint acceptance or production authorization.

## Synthetic demo distribution (web and Android)

- Under D-0077, `make demo-build` produces the disclosed synthetic demo as a
  static bundle in `apps/web/dist-demo` (relative asset paths, no service
  worker, no device storage, no API or provider request, visible demo
  notice). `make demo-serve` serves it on `127.0.0.1:4175`, and
  `make demo-dist-e2e` verifies the built artifact in Chromium.
- Opt-in remote client review uses `make demo-client` on `0.0.0.0:8080`
  advertising static IP `117.254.108.143` (port 8000 is reserved by another
  local project). See [CLIENT_DEMO_RUNBOOK.md](CLIENT_DEMO_RUNBOOK.md).
  Loopback defaults for `make demo` / `make demo-serve` are unchanged.
- The demo bundle is an explicitly requested review artifact and renders the
  draft interface locales (Kannada first) with the existing visible preview
  notice through `VITE_UI_TRANSLATION_PREVIEW=true` (ADR-0005). Ordinary
  production builds remain `approved-only`.
- Under D-0078, `make demo-android` wraps the same bundle in the Capacitor 8
  project at `apps/web/android` and builds
  `apps/web/android/app/build/outputs/apk/debug/app-debug.apk`
  (`Global English Demo`, placeholder identifier `com.globalenglish.demo`,
  minimum API 24, target API 36). Microphone permission supports the existing
  local-only practice capture; nothing is uploaded or persisted.
- The wrapper is presentation packaging only. It contains no credential,
  authentication path, provider access or learner data, and it is not the
  separately authorized PR-014 production Android application (Passport
  PKCE, native secure storage, device-matrix testing, Play release
  engineering).
- The APK was verified statically (`aapt2 dump badging`: identity, label,
  API levels, permissions and bundled demo assets). It has not been executed
  on a device or emulator in this workstation session because the login
  session has no `/dev/kvm` access; an on-device installation pass remains
  outstanding evidence before the APK is shown beyond a controlled
  presentation.
- The boundary and evidence live in
  [the demo distribution scope](DEMO_DISTRIBUTION_SCOPE.md).

## Verification state

Detailed evidence is recorded in [QUALITY_REVIEW.md](QUALITY_REVIEW.md),
[the PR-008 verification scope](PR-008_CONVERSATION_FAKE_PROVIDER_VERIFICATION_SCOPE.md),
[the PR-007 verification scope](PR-007_OFFLINE_BUNDLE_VERIFICATION_SCOPE.md),
[the PR-006 verification scope](PR-006_DETERMINISTIC_DAILY_SPEAKING_VERIFICATION_SCOPE.md),
[the PR-005 verification scope](PR-005_CONTENT_PUBLICATION_VERIFICATION_SCOPE.md)
and [the PR-004 accessibility scope](PR-004_ACCESSIBILITY_VERIFICATION_SCOPE.md).

PR-006 verification results:

- `make verify` passes with 39 SQLite tests/264 assertions, 163 PostgreSQL
  tests/1,848 assertions, 31 localization tests, 9 design-system tests and 51
  web tests. Dependency audits, locale validation, OpenAPI/generated-client
  drift, formatting, PHPStan, lint and recursive TypeScript checks pass.
- `make openapi` passes with OpenAPI 3.1.1 document version 0.4.0 and matching
  generated clients.
- `make e2e` passes all seven Chromium scenarios, including the explicitly
  contract-stubbed learner golden path and microphone-denial path.
- `make build` passes Laravel production optimization/cleanup and a strict
  production Vite build of 67 modules.
- Fresh migration, repeat seed, empty PR-006 rollback, forward reapply and
  repeated seed pass on the explicitly verified `global_english_test`
  PostgreSQL 14.19 database. Final state has migration `000024` once, 21
  permissions and zero publications, learner profiles or learner attempts.
- Twenty-five in-scope Markdown files pass structure and local-link checks.
  External URLs were not live-checked.
- `git diff --check` and the PR-006 security/scope audit pass.

## Known limitations

- PR-006 has no approved production mission, Kannada content, transcript or
  model-audio rights evidence. The canonical seed remains unpublished.
- Browser E2E may exercise a contract-stubbed synthetic mission. That is not a
  true full-stack published-media journey or accountable content approval.
- PR-006 deliberately accepts exactly one manifest mission. Multiple missions
  contributing to an objective require a later persisted result snapshot or
  global objective-evidence order before this boundary can expand.
- Raw learner audio is local-only and unavailable for transcription, trainer
  review or spoken mastery. Its non-authoritative capture receipt is not
  evidence of intelligible speech.
- DR-0005 is resolved by accepted ADR-0007 under APP-0011, but no recording
  upload, persistent consent state, retention/deletion implementation or
  real-provider processing is authorized.
- PR-007 offline behavior is accepted at its exact implementation commit.
  PR-008 adds only project-owned deterministic fake-provider paths; every real
  AI/speech provider and provider SDK remains excluded.
- ADR-0004's development roles are consolidated in one human and therefore
  non-independent. Independent tenancy, English-academic and Kannada-language
  review remains required before production.
- The human reviewer appointments still require active organization
  memberships, permissions and scoped runtime authority-appointment records
  before any product-data approval is valid.
- No curriculum publication has human runtime approval. The one seeded mission
  is a synthetic unpublished draft.
- Public content/publication HTTP operations are not part of PR-005; the
  implemented write paths are domain commands.
- Automatic scheduled activation and schedule cancellation are not included.
- Media-bearing cloning fails closed until reviewed object-copy, retention and
  derivative-rights behavior exists.
- Media asset import and initial pending-rights creation do not yet have a
  public or dedicated domain import command. Program-scoped confirmation,
  rejection and withdrawal are implemented; production ingest remains a
  reviewed later workflow.
- Evidence operations use PostgreSQL table-level share locks and require
  representative production-volume concurrency benchmarking.
- The APP-0004 self-appointment exception remains disabled until deployment
  binds its exact evidence to `S R Bhandary`'s stable application-user ULID.
  It does not make the consolidated reviews independent.
- Kannada, Malayalam, Tamil, Telugu and Hindi wording has not been approved by
  independent accountable reviewers. The current drafts and visual rendering
  evidence are not linguistic approval.
- The 200% and 400% automated checks enlarge root text and test reflow; they
  are stress proxies, not evidence of actual browser-zoom testing.
- Manual screen-reader, real browser-zoom and representative Android TalkBack
  testing remain outstanding. Automated axe results do not replace them.
- Docker Engine 29.6.2 and Compose 5.3.1 are installed, but this Codex login
  session cannot access the Docker socket until group membership is refreshed.
  PostgreSQL verification used the available loopback version 14 service.
- The fresh native verification toolchain used PHP 8.4.23, Node.js 24.18.1,
  pnpm 11.18.0, Composer 2.10.2 and GNU Make 4.4.1.
- The GitHub Actions workflow has not run on a remote runner.
- PR-006 is accepted at its exact implementation commit under APP-0007; every
  recorded limitation remains in force.

## PR-010 implemented boundary

- Context allowlist, versioned prompts (`prompt_versions`), structured output
  validation, curated safety fallback, advisory correction digests and
  learner error-pattern counters are implemented behind the existing
  conversation turn pipeline.
- Gentle / Tutor / Fluency coaching modes are accepted for Friend sessions.
- Priority correction is returned as a separate API field and rendered as a
  separate correction card; it is never merged into the Friend reply text.
- Deterministic fake model adapters remain the only local/testing path.
  Production remains fail-closed for conversation providers.
- Authoritative learning records (`learner_attempts`, `mastery_states`, and
  related tables) are not written by the AI orchestration path.
- OpenAPI and generated contracts include `priority_correction` and
  `safety_state`.

## PR-011 implemented boundary

- The active boundary is
  [the PR-011 evaluation and memory verification scope](PR-011_EVALUATION_MEMORY_VERIFICATION_SCOPE.md).
- Learner-visible AI memory facts are purpose-bound plaintext (`conversation_context`
  / `learner_explicit`), listed completely when active, and soft-deleted with
  immutable `memory_events` audit digests. Clearing memory does not delete
  learning or account records.
- `/api/v1/me/ai-memory` supports GET/POST/PATCH/DELETE with membership ownership
  and idempotent writes. Active facts are allowlisted into conversation context
  with hard caps (5 × 200 characters).
- The Practice UI exposes list, create, edit and delete for learner-visible
  facts.
- Locked deterministic AI evaluation suite lives under
  `apps/api/resources/ai-evaluation/v1/` and runs via `php artisan ai:evaluate`
  / `make ai-eval` (speech half runs only when `SPEECH_BENCHMARK_RUN_ID` is set).
- Human-review export/import packages bind the exact suite key and hash,
  accept `pending`/`accept`/`reject` dispositions, fail closed on mismatch or
  unknown cases, and keep prompt/cost rollups aggregate-only and
  synthetic-fixture based.
- Real LLM providers, PR-012, production and Play remain unauthorized.

## Next checkpoint gate

PR-011 is accepted under [APP-0020](APPROVALS.md#app-0020) at
`220628f4d4c69284af0b1f2534e8902f3b272088` with its recorded limitations
([PR-011_APO_ACCEPTANCE_PACKAGE.md](PR-011_APO_ACCEPTANCE_PACKAGE.md)).

[APP-0021](APPROVALS.md#app-0021) authorized beginning the Confidence Conversation
enterprise slice. [APP-0025](APPROVALS.md#app-0025) records Accountable Product
Owner acceptance of that slice at
`5582c9ab69d6c6b4409ffade6f0f5b9d94fc4b7b` with every recorded limitation
([CONFIDENCE_CONVERSATION_APO_ACCEPTANCE_PACKAGE.md](CONFIDENCE_CONVERSATION_APO_ACCEPTANCE_PACKAGE.md)).
Nested A4/A5 gates remain as accepted under
[APP-0022](APPROVALS.md#app-0022) / [ADR-0010](adr/0010-conversation-model-provider-policy.md)
and [APP-0024](APPROVALS.md#app-0024) / [ADR-0011](adr/0011-confidence-conversation-guidance-tts-policy.md).
Live multi-locale Azure TTS spend and ordinary Azure Speech DI expansion
remain unauthorized. Live Azure OpenAI calls remain operator-gated
(outside-repo evidence + ₹500 cap); production DI stays `disabled`.
Demo-local Azure TTS / OpenRouter (D-0092 / D-0094) are presentation-only
and do not count as enterprise verification evidence.

[APP-0031](APPROVALS.md#app-0031) accepted PR-014 Capacitor Android
closed-beta foundations at `44b0e672e004cf3846cddabc456c21153fa4607e` with
recorded limitations
([PR-014_APO_ACCEPTANCE_PACKAGE.md](PR-014_APO_ACCEPTANCE_PACKAGE.md)).
Production package identity is `com.globalenglish.app` at
`apps/web/android-app`; the demo APK (`com.globalenglish.demo`, D-0078) stays
presentation-only.
[APP-0033](APPROVALS.md#app-0033) accepted PR-015 bounded operations hardening
and closed-beta evidence closure at `5eca7515291ea571a7f697dcdc2da6197535f839`
with recorded limitations
([PR-015_APO_ACCEPTANCE_PACKAGE.md](PR-015_APO_ACCEPTANCE_PACKAGE.md)).
Active plan step is **F0** pre-F0 evidence-closure under
[APP-0034](APPROVALS.md#app-0034)
([F0_PHASE1_DOD_GAP_ASSESSMENT.md](F0_PHASE1_DOD_GAP_ASSESSMENT.md)).
F0 Definition of Done sign-off and Google Play public release remain
unauthorized.
[APP-0031](APPROVALS.md#app-0031) accepted PR-014 Capacitor Android
closed-beta foundations at `44b0e672e004cf3846cddabc456c21153fa4607e` with
recorded limitations
([PR-014_APO_ACCEPTANCE_PACKAGE.md](PR-014_APO_ACCEPTANCE_PACKAGE.md)).
Production package identity is `com.globalenglish.app` at
`apps/web/android-app`; the demo APK (`com.globalenglish.demo`, D-0078) stays
presentation-only.
[APP-0029](APPROVALS.md#app-0029) accepted PR-013 bounded operator
content-workflow + audit at `9220ab27baa6dc29d82caf8e9e97e94f7df7219d` with
recorded limitations
([PR-013_APO_ACCEPTANCE_PACKAGE.md](PR-013_APO_ACCEPTANCE_PACKAGE.md)).
Sampled speaking review, privacy ops, learner PII CSV export, full content
studio, F0 Phase 1 DoD, Google Play public release and real learner production
data remain unauthorized until distinct APPs.
Live multi-locale Azure TTS spend and ordinary Azure Speech DI expansion
remain unauthorized. Live Azure OpenAI calls remain operator-gated
(outside-repo evidence + ₹500 cap); production DI stays `disabled`.
Demo-local Azure TTS / OpenRouter (D-0092 / D-0094) and the demo APK
(`com.globalenglish.demo`, D-0078) are presentation-only and do not count as
enterprise verification evidence.

Production additionally requires qualified legal review and independent
security, privacy, tenancy, accessibility, language/content and release
review.

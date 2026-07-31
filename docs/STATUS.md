# Repository status

## Current checkpoint

| Field | Value |
|---|---|
| Checkpoint | PR-008 — Conversation domain with deterministic fake providers |
| Status | Implemented and repository-verified under APP-0010; exact-commit acceptance pending; DR-0005 decision proposal drafted |
| Canonical baseline | `ENTERPRISE_PRODUCT_MASTER_PLAN.md` version 3.1 |
| Updated date | 31 July 2026 |
| Git branch | `codex/dr-0005-recording-policy` |
| Product code | Accepted PR-007 baseline plus the implemented PR-008 deterministic fake-provider conversation slice; no real provider, audio persistence, production or later checkpoint approved |
| Next checkpoint | Review proposed ADR-0007; PR-009 remains unauthorized and PR-008 exact-commit acceptance is pending |

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

## DR-0005 decision proposal

[ADR-0007](adr/0007-recording-consent-retention-provider-processing-and-deletion-policy.md)
is a proposed recording-consent, retention, provider-processing and deletion
policy. It defines:

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

The proposal has no approval evidence. DR-0005 remains open until the
Technical Decision Owner, Security Reviewer, Privacy Reviewer and Tenancy
Reviewer accept the exact artifact and trade-offs through a durable approval
record. Drafting it does not accept PR-008, authorize PR-009, select a
provider, permit learner-audio processing or approve production.

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
  gated by DR-0005 and PR-009.
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
  `ui-en-IN-2026-07-30.7`.
- The `kn-IN`, `ml-IN`, `ta-IN`, `te-IN` and `hi-IN` catalogues are
  attributed Codex implementation drafts at revisions ending in `.7` for
  Kannada and `.3` for each newly added locale.
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
- DR-0005 remains open. ADR-0007 is a proposed, unapproved decision artifact;
  no recording upload, persistent consent state, retention/deletion workflow
  or real-provider processing is authorized.
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
  pnpm 11.18.0 and Composer 2.10.2. GNU Make remains unavailable.
- The GitHub Actions workflow has not run on a remote runner.
- PR-006 is accepted at its exact implementation commit under APP-0007; every
  recorded limitation remains in force.

## Next checkpoint gate

PR-008 is implemented and repository-verified under APP-0010. It is not yet
accepted at an exact implementation commit. DR-0005 remains open with
ADR-0007 proposed for exact-artifact review. PR-009, production and every
later implementation checkpoint remain unauthorized. Production additionally
requires qualified legal review and independent security, privacy, tenancy,
accessibility, language/content and release review.

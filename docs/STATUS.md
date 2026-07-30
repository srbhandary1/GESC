# Repository status

## Current checkpoint

| Field | Value |
|---|---|
| Checkpoint | PR-006 — Deterministic daily-speaking slice |
| Status | Authorized — implementation in progress |
| Canonical baseline | `ENTERPRISE_PRODUCT_MASTER_PLAN.md` version 3.0 |
| Updated date | 30 July 2026 |
| Git branch | `codex/pr-006-deterministic-daily-speaking-slice` |
| Product code | PR-005 accepted; PR-006 implementation authorized and bounded to one deterministic no-AI learner journey |
| Next checkpoint | PR-007, blocked until explicit PR-006 acceptance at its exact implementation commit |

## Checkpoint authorization

[APP-0005](APPROVALS.md#app-0005) records `S R Bhandary`'s explicit
Accountable Product Owner acceptance of PR-005 at
`daeee1df7a7e4d31427001aedece76d8121413d6`, with its recorded limitations,
and authorizes only PR-006 — Deterministic daily-speaking slice.

PR-006 does not authorize PR-007 offline bundle/outbox work, AI or
speech-provider orchestration, production deployment or a later checkpoint.

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
  `ui-en-IN-2026-07-30.2`.
- The `kn-IN` catalogue is an attributed Codex implementation draft at
  revision `ui-kn-IN-2026-07-30.2`.
- Kannada remains `pending-independent-review`: no reviewer, approval time or
  independent-review claim is recorded.
- Ordinary production builds use `approved-only`, set the document language to
  the resolved English locale and show a generic fallback notice while
  retaining `kn-IN` as the requested locale.
- Development, tests and an explicit
  `VITE_UI_TRANSLATION_PREVIEW=true` review build may render draft Kannada only
  with the visible preview notice. That flag is not authorized for production
  deployment.
- UI catalogues are separate from curriculum, mission, transcript and media
  content. Those belong to the versioned publication foundation after DR-0003
  is resolved.

## Security, privacy and tenancy state

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
- Audio and recording controls are inert presentation contracts. They do not
  access microphones, play media, make network/provider calls or persist
  learner data.
- No real learner data, captured audio, unlicensed media, provider SDK or
  later-checkpoint authoritative record was added.

## Verification state

Detailed evidence is recorded in [QUALITY_REVIEW.md](QUALITY_REVIEW.md),
[the PR-005 verification scope](PR-005_CONTENT_PUBLICATION_VERIFICATION_SCOPE.md)
and [the PR-004 accessibility scope](PR-004_ACCESSIBILITY_VERIFICATION_SCOPE.md).

- `make verify` passes with 36 SQLite backend tests/254 assertions, 154
  PostgreSQL tests/1,671 assertions, 56 frontend/package unit tests, dependency
  audits, locale validation, OpenAPI/generated-client drift, formatting,
  static analysis, centralized lint and recursive TypeScript checks.
- `make openapi` passes with OpenAPI 0.3.0 and matching generated clients.
- `make e2e` passes all five Chromium scenarios.
- `make build` passes Laravel production optimization/cleanup and a strict
  production Vite build of 54 modules.
- Fresh migration, repeat seed, four-step empty-data rollback, forward re-apply
  and seed pass on PostgreSQL 14. The enrollment-pin migration refuses rollback
  when any enrollment exists, so authoritative adoption requires a reviewed
  forward fix and data reconciliation.
- Local browser visual inspection confirms Kannada glyph rendering at 1280 CSS
  pixels and no horizontal overflow at 320 CSS pixels; a strict production
  build visibly falls back to English and reports `lang="en-IN"` with
  `data-requested-locale="kn-IN"`.

## Known limitations

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
- Kannada wording has not been approved by an independent accountable Kannada
  reviewer. The current draft and visual rendering evidence are not linguistic
  approval.
- The 200% and 400% automated checks enlarge root text and test reflow; they
  are stress proxies, not evidence of actual browser-zoom testing.
- Manual screen-reader, real browser-zoom and representative Android TalkBack
  testing remain outstanding. Automated axe results do not replace them.
- Docker/Compose and a fresh PostgreSQL 18.4 container remain unavailable on
  this host. PostgreSQL verification used the available local version 14
  service.
- Local PHP is 8.4.17 rather than the declared 8.4.23 patch. The pinned Node.js
  24.18.1, pnpm 11.18.0 and Composer 2.10.2 toolchain was used.
- The GitHub Actions workflow has not run on a remote runner.
- No playable learner mission, attempt, mastery, audio playback/capture,
  speech/AI provider, offline behavior or Android product UI is authorized in
  this checkpoint.

## Next checkpoint gate

PR-006 is authorized under APP-0005. PR-007 stays blocked until the
Accountable Product Owner explicitly accepts PR-006 at its exact implementation
commit, with its recorded limitations, and authorizes only PR-007.

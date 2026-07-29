# Repository status

## Current checkpoint

| Field | Value |
|---|---|
| Checkpoint | PR-004 — Localization and accessible design system |
| Status | Implementation complete — acceptance pending |
| Canonical baseline | `ENTERPRISE_PRODUCT_MASTER_PLAN.md` version 3.0 |
| Updated date | 30 July 2026 |
| Git branch | `pr-004-localization-accessible-design-system` |
| Product code | Governed UI localization, accessible shell and inert audio/recording presentation contracts only |
| Next checkpoint | PR-005 — Versioned content and publication foundation, only after explicit PR-004 acceptance and resolution of DR-0003 |

## Checkpoint authorization

[APP-0002](APPROVALS.md#app-0002) records the product owner's contextual
instruction accepting PR-003 at
`457bd0b5e9486cb207882af38a5a65479534d55e` with its stated limitations and
authorizing only PR-004. No new ADR was required because this checkpoint
implements localization and accessibility defaults already approved in the
enterprise master plan.

## Delivered in PR-004

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

- PR-003 authentication, membership-derived organization context,
  cross-tenant protections and generated API contracts are unchanged.
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
- No real learner data, captured audio, curriculum content, unlicensed media,
  provider SDK or later-checkpoint authoritative record was added.

## Verification state

Detailed evidence is recorded in [QUALITY_REVIEW.md](QUALITY_REVIEW.md) and
[the PR-004 accessibility scope](PR-004_ACCESSIBILITY_VERIFICATION_SCOPE.md).

- `make verify` passes with 36 SQLite backend tests/254 assertions, 93
  PostgreSQL tests/1,217 assertions, 56 frontend/package unit tests, dependency
  audits, locale validation, OpenAPI/generated-client drift, formatting,
  static analysis, centralized lint and recursive TypeScript checks.
- `make openapi` passes with no API or generated-client change.
- `make e2e` passes all five Chromium scenarios.
- `make build` passes Laravel production optimization/cleanup and a strict
  production Vite build of 54 modules.
- Local browser visual inspection confirms Kannada glyph rendering at 1280 CSS
  pixels and no horizontal overflow at 320 CSS pixels; a strict production
  build visibly falls back to English and reports `lang="en-IN"` with
  `data-requested-locale="kn-IN"`.

## Known limitations

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
- No curriculum publication, playable mission, learner attempt, audio
  playback/capture, speech/AI provider, offline behavior or Android UI is
  implemented in PR-004.

## Next checkpoint gate

PR-004 is not self-accepted. PR-005 must not begin until:

1. the Accountable Product Owner explicitly reviews and accepts PR-004 with
   its recorded evidence and limitations; and
2. DR-0003 is resolved through an accepted ADR and durable approval evidence
   from the required product, technical, tenancy and academic/language-review
   authorities.

## Recommended PR-005 task

```text
Read AGENTS.md and ENTERPRISE_PRODUCT_MASTER_PLAN.md completely. Then read
docs/STATUS.md, docs/DECISIONS.md, docs/QUALITY_REVIEW.md,
docs/APPROVALS.md, the relevant accepted ADRs in docs/adr/, and the active
issue or specification. Inspect the repository before editing.

Confirm and record that PR-004 has been reviewed and accepted. If acceptance is
not explicit, stop and request it; do not infer checkpoint approval.

Before implementing PR-005 schema or authoritative publication behavior,
resolve DR-0003 — content ownership, approval roles and curriculum-sharing
rules. Record an accepted ADR and durable APP-NNNN evidence defining:

- which curriculum and content records are platform-global,
  organization-owned or explicitly shared;
- publisher, English academic reviewer and Kannada reviewer authority;
- translator/reviewer separation;
- cross-organization sharing, withdrawal, cloning and immutable-version
  semantics;
- enrollment-to-curriculum-version pinning; and
- tenant scoping, authorization, audit and rollback behavior.

Because this decision affects tenancy, authoritative records and foundation
schema, do not infer it from implementation convenience. Obtain the applicable
Accountable Product Owner, Technical Decision Owner, Tenancy Reviewer and
appointed academic/language-review authority before implementation.

Then execute only PR-005 — Versioned content and publication foundation. Do not
implement the playable deterministic daily-speaking journey, learner attempts,
mastery, review scheduling, offline sync, conversation, speech or AI providers,
a broad operator content studio, payments, reporting or any later-checkpoint
feature.

Deliver:

- generic program, curriculum-version, objective, module, lesson and content-
  revision concepts without hard-coded public level labels;
- localized variants and governed review states, including source revision,
  staleness, translator/reviewer separation and approval evidence;
- media asset and rights metadata only, with no unlicensed media;
- one safe synthetic seeded mission definition, without learner delivery,
  attempts or completion behavior;
- an immutable, transactional and replay-safe publish operation with audit,
  outbox and rollback metadata; and
- the minimum enrollment/version pinning needed to demonstrate the design.

Keep PR-004 UI message catalogues separate from curriculum-content publication.
Every organization-owned resource must use membership-derived organization
context, authorization, database constraints and cross-tenant tests. A
published revision must never be edited in place; rollback selects a prior
immutable publication rather than rewriting history. Unapproved or stale
Kannada and self-approved translations must fail publication.

Before material changes, state a brief plan. Update OpenAPI and generated
clients for API changes, add migrations with rollback or mitigation notes, and
add tests for publication transitions, immutability, idempotency, concurrency,
version pinning and cross-tenant denial. Run make openapi, make verify, make e2e
and make build as applicable. Update docs/STATUS.md, docs/DECISIONS.md and
docs/QUALITY_REVIEW.md at the checkpoint boundary.

Conclude with files changed, behavior delivered, commands and exact results,
migrations/configuration, security/privacy/tenancy considerations, known
limitations and the exact prompt recommended for PR-006.
```

# Repository status

## Current checkpoint

| Field | Value |
|---|---|
| Checkpoint | PR-005 — Versioned content and publication foundation |
| Status | Implementation authorized — in progress |
| Canonical baseline | `ENTERPRISE_PRODUCT_MASTER_PLAN.md` version 3.0 |
| Updated date | 30 July 2026 |
| Git branch | `pr-005-versioned-content-publication-foundation` |
| Product code | No PR-005 product or schema change at the approval checkpoint; implementation is now authorized |
| Next checkpoint | PR-006, only after PR-005 implementation, verification and explicit acceptance |

## Checkpoint authorization

[APP-0004](APPROVALS.md#app-0004) accepts
[ADR-0004](adr/0004-content-ownership-publication-and-curriculum-sharing.md)
at `d9d7cba4b2ccc0d9f2e8656e5fd19bab9ce9dc99`, resolves DR-0003 and authorizes
implementation of only PR-005. `S R Bhandary` exercised every recorded
development role. Those consolidated roles are explicitly non-independent
review and do not waive pre-production specialist gates.

## PR-005 decision phase

- Repository inspection confirms there is no curriculum, content, media-rights,
  publication or curriculum-sharing implementation.
- [ADR-0004](adr/0004-content-ownership-publication-and-curriculum-sharing.md)
  establishes organization ownership, review/publisher authority, sharing,
  withdrawal, cloning, immutable publication, rollback and version-pinning
  rules.
- The ADR is `Accepted` under APP-0004 and DR-0003 is resolved.
- The policy keeps Global English curriculum inside the ordinary Global
  English Direct tenant and uses explicit immutable-publication grants for
  cross-organization availability.
- It defines a closed publication manifest, deterministic Kannada and
  visible/hidden academic-review coverage, scoped authority appointments,
  media-rights evidence, successor-release rules and owner/recipient permission
  boundaries.
- Existing invite acceptance creates unpinned active foundation enrollments.
  The proposal therefore introduces `pending_curriculum`, migrates pre-content
  unpinned active rows to that state, permits pre-activation withdrawal,
  preflights inconsistent completed rows and makes activation atomic with a
  valid immutable publication pin.
- At the governance-acceptance checkpoint, no migration, route, OpenAPI
  operation, generated client, model, permission, seed or product behavior had
  been added.
- Repository Markdown/link validation, `git diff --check` and the existing
  full `make verify` gate pass after temporarily starting and initializing the
  local PostgreSQL 14 test service; the service was stopped afterward.

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

- ADR-0004's development roles are consolidated in one human and therefore
  non-independent. Independent tenancy, English-academic and Kannada-language
  review remains required before production.
- The human reviewer appointments still require active organization
  memberships, permissions and scoped runtime authority-appointment records
  before any product-data approval is valid.
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
- PR-005 implementation and acceptance verification are not yet complete. No
  curriculum publication has been human-approved, and no playable learner
  mission, attempt, audio playback/capture, speech/AI provider, offline
  behavior or Android UI is authorized in this checkpoint.

## Next checkpoint gate

APP-0004 satisfies the PR-005 implementation gate. Work may proceed only
within PR-005 and the accepted ADR. PR-006 remains blocked until PR-005 is
implemented, fully verified, documented and explicitly accepted by the
Accountable Product Owner.

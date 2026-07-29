# Repository status

## Current checkpoint

| Field | Value |
|---|---|
| Checkpoint | PR-003 — Tenancy and identity foundation |
| Status | Implementation complete — acceptance pending |
| Canonical baseline | `ENTERPRISE_PRODUCT_MASTER_PLAN.md` version 3.0 |
| Updated date | 30 July 2026 |
| Git branch | `pr-003-tenancy-identity-foundation` |
| Product code | Identity, tenancy and authentication foundation only |
| Next checkpoint | PR-004 — Localization and accessible design system, only after PR-003 review and acceptance |

## Checkpoint authorization

[APP-0001](APPROVALS.md#app-0001) records the product owner's instruction
accepting PR-002, the required PR-003 decisions and authorization to execute
only PR-003. The accepted decisions are
[ADR-0001](adr/0001-approval-authority-and-evidence.md),
[ADR-0002](adr/0002-authentication-session-token-and-recovery-policy.md) and
[ADR-0003](adr/0003-resource-ownership-and-organization-context.md). None amends
the enterprise master plan.

## Delivered in PR-003

- platform-global organizations, users, verified identities and permission
  keys, with organization-owned memberships, roles, invitations, cohorts and
  enrollments;
- an idempotent Global English Direct foundation tenant and phone-only OTP
  assignment for direct learners;
- invite-plus-OTP identity provisioning and institution membership acceptance
  without unintended Direct membership, plus replay-safe authenticated invite
  acceptance for an existing user;
- fail-closed organization context, tenant-scoped model queries, policies,
  scoped binding, request middleware for selected/single active memberships
  and composite PostgreSQL ownership constraints;
- explicitly authorized platform operations with safe reason keys,
  append-only audit evidence and transactional outbox records;
- same-origin Sanctum web/PWA sessions with CSRF, inactivity and absolute
  lifetimes, session listing, logout and revocation;
- Passport Authorization Code with S256 PKCE for the Android public-client
  foundation, active-public-client enforcement, rotating installation-bound
  token families and revocation;
- a shared frontend authentication interface with web cookie and Android
  system-browser/native-secure-store adapters;
- fake/local OTP delivery, abuse controls and a privileged-MFA extension point;
- canonical OpenAPI changes, regenerated TypeScript contracts, PostgreSQL
  integration coverage and local-development configuration checks.

## Security, privacy and tenancy state

- Global English Direct is an ordinary organization tenant, never an unscoped
  bypass.
- A request field, route identifier or arbitrary organization header cannot
  establish organization authority. Context derives from an active
  authenticated membership or an explicitly authorized platform operation.
- New invite-first institution learners receive only their institution
  membership. Existing users may hold independently accepted memberships in
  multiple organizations and must select an unambiguous current context.
- Identity lookup uses the dedicated `AUTH_IDENTITY_DIGEST_KEY`; routine
  Laravel `APP_KEY` rotation does not alter identity digests. Identity-key
  rotation requires the versioned migration described in
  [the PR-003 verification scope](PR-003_TENANCY_VERIFICATION_SCOPE.md).
- Browser authentication uses encrypted, Secure, HttpOnly, SameSite cookies
  and CSRF protection. Android access credentials remain in memory; persistent
  installation and refresh credentials are permitted only through the reviewed
  native-secure-store interface.
- Raw phone numbers, invitation codes, OTPs, access/refresh tokens and provider
  payloads are absent from authoritative records, audit events and application
  logs. No real provider call or learner record is included.

## Current constraints

- Docker and Docker Compose are unavailable on this host. Compose policy and
  the test-database initializer can be checked statically and against the
  available local PostgreSQL service, but fresh PostgreSQL 18.4 container
  startup remains unverified locally.
- The host has PHP 8.4.17 rather than the declared PHP 8.4.23 patch. Repository
  checks run on the compatible local patch; CI declares the exact patch.
- The GitHub Actions workflow has not run on a remote runner.
- The local PostgreSQL verification service is version 14; CI is configured
  for the digest-pinned PostgreSQL 18.4 image.
- The fake OTP adapter is local/testing-only. A real delivery provider,
  production privileged-MFA verifier and reviewed native secure-storage
  implementation remain intentionally unselected.
- Recovery codes, phone change/reassignment, account closure, admission-ID
  setup and production operational alerts are not exposed in PR-003. ADR-0002
  gates each affected future workflow.
- Independent security, privacy and tenancy specialist review remains required
  before production. APP-0001 records one human exercising multiple
  development roles, not independent review.

## PR-003 verification state

- `make openapi`, `make verify`, `make e2e` and `make build` results are
  recorded in [QUALITY_REVIEW.md](QUALITY_REVIEW.md);
- authoritative schema, tenancy, OTP, session and token-family behavior is
  exercised against PostgreSQL, including cross-tenant denials and concurrent
  refresh behavior;
- local Markdown structure, YAML, repository-local links, generated-contract
  drift and Git hygiene are checked before handoff;
- PR-003 remains pending product-owner review and checkpoint acceptance.

## Recommended PR-004 task

```text
Read AGENTS.md and ENTERPRISE_PRODUCT_MASTER_PLAN.md completely. Then read
docs/STATUS.md, docs/DECISIONS.md, docs/QUALITY_REVIEW.md,
docs/APPROVALS.md, the relevant accepted ADRs in docs/adr/, and the active
issue or specification. Inspect the repository before editing.

Confirm and record that PR-003 has been reviewed and accepted. If acceptance is
not explicit, stop and request it; do not infer checkpoint approval. Then
execute only PR-004 — Localization and accessible design system. Do not
implement versioned curriculum publication, mission journeys, AI or speech
providers, offline sync, payments, reporting, production OTP, account recovery
or any later-checkpoint feature.

Deliver:

- governed `kn-IN` and `en-IN` message catalogues with deterministic English
  fallback and locale-key validation in CI;
- a mixed-language semantic text component suitable for Kannada and English
  spans without breaking assistive technology;
- the reviewed Kannada font stack, Unicode normalization rules and tests;
- an accessible responsive application shell with
  Home/Learn/Practice/Progress navigation;
- project-owned audio and recording component contracts only, without provider
  integration or captured learner audio;
- keyboard, focus, zoom, target-width and automated accessibility coverage for
  the shell.

Preserve the generated OpenAPI/client workflow, same-origin web authentication,
Android native-secure-store boundary, membership-derived organization context
and cross-tenant protections established by PR-003. Do not store credentials
in localStorage, IndexedDB or ordinary Capacitor preferences. Add no real
learner data, unlicensed media or real provider calls.

Before material changes, state a brief plan. Update tests with behavior,
OpenAPI and generated types for any API change, and add an ADR only for a
material architecture change. Run make openapi, make verify, make e2e and make
build as applicable. Demonstrate Kannada rendering at the master-plan target
widths and zoom, keyboard navigation and automated accessibility checks.
Update docs/STATUS.md, docs/DECISIONS.md and docs/QUALITY_REVIEW.md at the
checkpoint boundary.

Conclude with files changed, behavior delivered, commands and exact results,
migrations/configuration, security/privacy/tenancy considerations, known
limitations and the exact prompt recommended for PR-005.
```

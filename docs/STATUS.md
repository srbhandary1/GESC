# Repository status

## Current checkpoint

| Field | Value |
|---|---|
| Checkpoint | PR-002 — Contracts, health and observability |
| Status | Implementation complete — acceptance pending |
| Canonical baseline | `ENTERPRISE_PRODUCT_MASTER_PLAN.md` version 3.0 |
| Updated date | 29 July 2026 |
| Git branch | `pr-002-contracts-health-observability` |
| Product code | Operational platform slice only; no business feature |
| Next checkpoint | PR-003 — Tenancy and identity foundation, after PR-002 acceptance and required decision resolution |

## Checkpoint authorization

On 29 July 2026, after the recorded PR-001 environment limitations were
presented, the product owner instructed Codex to continue. That instruction was
treated as explicit acceptance of the recorded PR-001 limitations and
authorization to execute PR-002. The limitations remain visible rather than
being represented as resolved.

## Delivered in PR-002

- canonical OpenAPI 3.1.1 health contract under `docs/api/`, with RFC 9457-style
  problem details and localized `message_key` values;
- deterministic generated TypeScript types, JSON schemas and anonymous health
  client under `packages/contracts/`, consumed by the web application;
- contract linting, future-operation policy validation and generated-client
  drift detection in `make openapi`, `make verify` and CI;
- versioned `GET /api/v1/health/live` and `GET /api/v1/health/ready`
  endpoints, replacing the PR-001 framework `/up` shell;
- dependency-aware readiness for PostgreSQL and Redis, with bounded Redis
  health connection settings and safe `503` problem responses;
- validated/generated UUID correlation IDs, JSON stderr logging and
  privacy-safe allowlisted request events;
- generic API exception conversion that does not disclose exception messages,
  framework validation text, input values or dependency details;
- an accessible React application error boundary and a typed health-client
  wrapper with generic client failures;
- backend, frontend, browser and contract tests for the operational slice.

## Current constraints

- Docker and Docker Compose are not installed on the current host, so service
  startup, healthy PostgreSQL/Redis readiness and container failure timing
  remain unverified locally. Deterministic tests plus a forced live connection
  refusal prove the required failure behavior without Docker.
- The host provides PHP 8.4.17 rather than the declared PHP 8.4.23 patch. Local
  checks passed on PHP 8.4.17 with a visible warning; CI declares PHP 8.4.23.
- The GitHub Actions workflow has not run on a remote GitHub runner.
- The object store is not a readiness dependency until an implemented serving
  path requires it. Mail/OTP and external providers are also intentionally
  outside the PR-002 readiness contract.
- The React boundary catches descendant render and lifecycle failures, but not
  event-handler, arbitrary asynchronous, boundary-self or pre-mount
  static-import failures.
- Identity, authorization and tenant-owned resources do not exist yet; their
  security and cross-tenant tests begin in PR-003.

## PR-002 verification state

- `make openapi`, `make verify`, `make e2e` and `make build` results are
  recorded in `docs/QUALITY_REVIEW.md`;
- generated-client drift was deliberately introduced and correctly rejected
  before the generated file was restored;
- an injected required-dependency exception returns a generic `503` response
  and tests prove the secret diagnostic is absent from the response and logs;
- PR-002 is implementation-complete but remains pending product-owner review
  and checkpoint acceptance.

## Recommended PR-003 task

```text
Read AGENTS.md, ENTERPRISE_PRODUCT_MASTER_PLAN.md, docs/STATUS.md,
docs/DECISIONS.md and docs/QUALITY_REVIEW.md completely. Then read relevant
accepted ADRs and the active issue or specification, and inspect the repository.

Confirm that PR-002 has been reviewed and accepted. Before material
implementation, resolve and record DR-0001, DR-0002 and DR-0008 with explicit
product-owner approval evidence. If those decisions are not supplied, present
concise options and recommended defaults, then stop for approval rather than
guessing about authentication security, resource ownership or approval
authority.

Once those decisions are accepted, execute only PR-003 — Tenancy and identity
foundation. Do not implement curriculum, learning journeys, AI, speech,
payments, offline sync, reporting or later-checkpoint features.

Create:

- organizations, users, identities, memberships, roles and permissions;
- automatic assignment of direct learners to the platform-owned direct
  organization;
- minimum viable institution invite/code membership plus cohorts and
  enrollments;
- same-origin Sanctum cookie-session flow for web/PWA;
- Passport Authorization Code with PKCE mobile-client foundation behind the
  shared project authentication abstraction;
- fake/local OTP adapter and a privileged-MFA extension point;
- mandatory organization scoping, policies and cross-tenant denial tests for
  every tenant-owned resource;
- transactional authoritative multi-write behavior and idempotency where a
  request can be replayed;
- OpenAPI updates, regenerated TypeScript contracts and frontend authentication
  adapter integration for every API change.

Keep browser credentials out of localStorage, IndexedDB and ordinary Capacitor
preferences. Keep raw personal data, OTPs, tokens, secrets and framework
exception details out of logs. Do not add real OTP/provider calls or real
learner data. Record material architecture, tenancy and authentication
decisions in accepted ADRs. Update the master plan in the same change whenever
an ADR explicitly amends the canonical baseline.

Before editing, state a brief implementation plan. Use PostgreSQL-backed
integration tests for foundation schema and tenancy behavior; do not infer
production readiness from SQLite-only tests. Run make openapi, make verify,
make e2e and make build as applicable. Demonstrate same-origin web
authentication, PKCE client foundations, direct-organization assignment,
institution membership and explicit cross-tenant denials. Do not claim a
command or provider call passed unless it actually ran successfully. Update
docs/STATUS.md, docs/DECISIONS.md and docs/QUALITY_REVIEW.md at completion.

Conclude with files changed, behavior delivered, commands and results,
migrations and configuration added, security/privacy/tenancy considerations,
known limitations and the exact prompt recommended for PR-004.
```

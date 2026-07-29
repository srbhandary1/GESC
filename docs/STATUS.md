# Repository status

## Current checkpoint

| Field | Value |
|---|---|
| Checkpoint | PR-001 — Monorepo and local platform bootstrap |
| Status | Implementation complete — acceptance pending |
| Canonical baseline | `ENTERPRISE_PRODUCT_MASTER_PLAN.md` version 3.0 |
| Updated date | 29 July 2026 |
| Git branch | `pr-001-monorepo-bootstrap` |
| Product code | Framework shells only; no business feature |
| Next checkpoint | PR-002 — Contracts, health and observability, after PR-001 acceptance |

## Delivered in PR-001

- Laravel 13 API shell under `apps/api/`, with the framework health endpoint and
  no default identity/domain model;
- React 19.2 TypeScript/Vite shell under `apps/web/`, with unit and browser
  smoke tests;
- exact Node.js, pnpm, PHP-platform and Composer declarations plus committed
  dependency lockfiles;
- loopback-only PostgreSQL, Redis, S3-compatible SeaweedFS, Mailpit and WireMock
  OTP services in `compose.yaml`;
- stable root `make` commands and toolchain validation;
- CI that runs bootstrap, verification and production builds with the declared
  toolchain;
- reproducible Laravel optimization and React production builds.

## Current constraints

- Docker and Docker Compose are not installed on the current host, so service
  startup, health checks and dependency-backed readiness remain unverified
  locally.
- The host provides PHP 8.4.17 rather than the declared PHP 8.4.23 patch. Local
  checks passed on PHP 8.4.17 with a visible warning; CI declares PHP 8.4.23.
- The GitHub Actions workflow has not run on a remote GitHub runner.
- OpenAPI, generated clients, dependency-aware readiness, correlation IDs,
  structured logging and error boundaries are deliberately deferred to PR-002.
- Identity, authorization and tenant-owned resources do not exist yet; their
  security and cross-tenant tests begin in PR-003.

## PR-001 verification state

- `make bootstrap` passed with the locked dependency sets;
- `make verify` passed formatting, dependency audit, static analysis, backend
  and frontend tests, linting and type checking;
- `make build` passed Laravel production optimization and the Vite production
  build;
- `make e2e` passed the React shell browser smoke test after Chromium was run
  outside the restricted macOS sandbox;
- local Markdown links, YAML/JSON syntax and repository hygiene checks are
  recorded in `docs/QUALITY_REVIEW.md`.

PR-001 remains pending acceptance because the current host cannot demonstrate
the Compose service health gate or a clean run with the exact PHP patch. Do not
begin PR-002 until those acceptance gaps are reviewed and resolved or explicitly
accepted.

## Recommended PR-002 task

```text
Read AGENTS.md, ENTERPRISE_PRODUCT_MASTER_PLAN.md, docs/STATUS.md,
docs/DECISIONS.md and docs/QUALITY_REVIEW.md completely. Then read relevant
accepted ADRs and the active issue or specification, and inspect the repository.

Confirm that PR-001 has been reviewed and accepted. Then execute only PR-002 —
Contracts, health and observability. Do not implement identity, tenancy,
authentication, curriculum, learning journeys or other business features.

Create:

- an OpenAPI 3.1.1 baseline under /api/v1;
- generated TypeScript client types and schemas in packages/contracts;
- a stable RFC 9457-style problem-details contract with localized message keys;
- end-to-end correlation IDs and privacy-safe structured logging;
- separate liveness and dependency-aware readiness endpoints;
- API and web error boundaries;
- contract validation and generated-client drift checks in root commands and CI;
- tests proving readiness fails when required dependencies are unavailable.

Keep provider SDK types, personal data and secrets out of logs. Do not add
feature endpoints merely to populate the contract. Add an ADR only if a
material architecture decision changes the canonical baseline.

Before editing, state a brief implementation plan. Run make openapi,
make verify, make e2e and make build as applicable, and demonstrate generated
client drift detection plus readiness failure with an unavailable required
dependency. Do not claim a command passed unless it actually ran successfully.
Update docs/STATUS.md, docs/DECISIONS.md and docs/QUALITY_REVIEW.md at
completion.

Conclude with files changed, behaviour delivered, commands and results,
configuration added, security/privacy/tenancy considerations, known
limitations and the exact prompt recommended for PR-003.
```

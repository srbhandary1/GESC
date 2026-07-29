# Repository status

## Current checkpoint

| Field | Value |
|---|---|
| Checkpoint | PR-000 — Governance baseline |
| Status | Accepted |
| Canonical baseline | `ENTERPRISE_PRODUCT_MASTER_PLAN.md` version 3.0 |
| Prepared date | 29 July 2026 |
| Git branch | `pr-000-governance-baseline` |
| Product code | Not started |
| Next checkpoint | PR-001 — Monorepo and local platform bootstrap |

## Delivered in PR-000

- canonical master plan at the repository root;
- root `AGENTS.md`;
- repository README and source-of-truth hierarchy;
- ADR template;
- decision and quality-review ledgers;
- checkpoint and decision-request issue templates;
- pull-request template;
- editor and ignore conventions.

## Current constraints

- The repository has no commit or remote configuration yet.
- Root `make` commands do not exist yet and must be created by PR-001.
- No Laravel, React, database, queue, storage or CI scaffold exists.
- External framework, legal and Google Play baselines require verification at
  the checkpoint where they become implementation or release dependencies.

## Next checkpoint entry criteria

PR-000 review confirmed:

- the authority hierarchy is explicit;
- the canonical documents are present and internally linked;
- issue, ADR and pull-request workflows reflect checkpoint discipline;
- no product code was introduced.

Acceptance was recorded on 29 July 2026 through the product-owner instruction
to proceed with project implementation.

## Recommended PR-001 task

```text
Read AGENTS.md, ENTERPRISE_PRODUCT_MASTER_PLAN.md, docs/STATUS.md,
docs/DECISIONS.md and docs/QUALITY_REVIEW.md completely. Then read relevant
accepted ADRs and the active issue or specification, and inspect the repository.

Execute only PR-001 — Monorepo and local platform bootstrap. Do not implement
business features.

Create:

- apps/api as a Laravel 13 application;
- apps/web as a React 19.2 TypeScript Vite application;
- exact Node 24/pnpm and PHP 8.4 runtime declarations;
- local PostgreSQL, Redis, S3-compatible object storage and mail/OTP fake
  services in compose.yaml;
- the stable root Makefile commands required by the master plan;
- basic CI;
- reproducible API and web production builds.

Pin exact supported dependency versions during scaffolding. Keep provider SDKs
and business-domain scaffolding out of this checkpoint.

Before editing, state a brief implementation plan. Validate the checkpoint on a
clean environment by running make bootstrap, make verify and make build. Do not
claim a command passed unless it actually ran successfully. Update
docs/STATUS.md, docs/DECISIONS.md and docs/QUALITY_REVIEW.md at completion.

Conclude with files changed, behaviour delivered, commands and results,
configuration added, security/privacy/tenancy considerations, known limitations
and the exact prompt recommended for PR-002.
```

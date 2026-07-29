# Global English

Global English is a mobile-first, Kannada-guided spoken-English platform for
adult learners. The canonical product, architecture, security and delivery
baseline is [ENTERPRISE_PRODUCT_MASTER_PLAN.md](ENTERPRISE_PRODUCT_MASTER_PLAN.md).

## Repository authority

When repository artefacts conflict, use this order:

1. `ENTERPRISE_PRODUCT_MASTER_PLAN.md`;
2. an accepted ADR in `docs/adr/` that explicitly amends the master plan;
3. an approved specification in `docs/specs/`;
4. the active issue or task;
5. existing implementation behaviour.

Read [AGENTS.md](AGENTS.md) before making changes. The current checkpoint and
known limitations are recorded in [docs/STATUS.md](docs/STATUS.md).

## Current state

The repository is at **PR-001 — Monorepo and local platform bootstrap**. The
Laravel API shell, React web shell, local dependency stack, root commands and CI
are implemented. PR-001 is awaiting checkpoint acceptance; no business feature,
identity, tenancy or API contract has been introduced.

## Prerequisites

- PHP 8.4.23;
- Composer 2.10.2;
- Node.js 24.18.1 LTS;
- pnpm 11.18.0;
- Make;
- Docker Engine with Docker Compose v2 for local services and `make dev`.

Runtime declarations live in `.php-version`, `.node-version`, `.nvmrc`,
`package.json` and the CI workflow. Composer and pnpm lockfiles are the exact
dependency authority.

## Bootstrap and verification

From the repository root:

```text
make bootstrap
make verify
make build
```

`make bootstrap` installs locked dependencies and creates ignored local
environment files without overwriting existing ones. To start PostgreSQL,
Redis, object storage, mail/OTP fakes, the API, web app and queue worker:

```text
make dev
```

Local services bind only to `127.0.0.1`. The API uses port 8000, Vite uses port
5173, Mailpit's browser interface uses port 8025, S3-compatible storage uses
port 8333 and the OTP stub uses port 8089 by default.

## Root commands

| Command | Purpose |
|---|---|
| `make bootstrap` | Install dependencies and prepare the local environment |
| `make dev` | Run the API, web application, workers and local dependencies |
| `make verify` | Run formatting, linting, static analysis, tests and contract checks |
| `make test` | Run all deterministic automated tests |
| `make e2e` | Run critical browser journeys |
| `make ai-eval` | Run the locked AI and speech evaluation suite |
| `make openapi` | Validate OpenAPI and regenerate client types |
| `make migrate` | Apply local migrations |
| `make seed` | Load safe development seed data |
| `make build` | Create production builds |

`make openapi` and `make ai-eval` intentionally exit with a checkpoint message
until PR-002 and PR-011 respectively. They do not report placeholder success.

## Repository layout

- `apps/api/` — Laravel 13 modular-monolith shell and framework health endpoint;
- `apps/web/` — React 19.2 TypeScript/Vite shell;
- `infra/local/` — local-only provider stubs;
- `compose.yaml` — PostgreSQL, Redis, SeaweedFS, Mailpit and WireMock services;
- `.github/workflows/ci.yml` — bootstrap, verification and build workflow.

The example credentials in `.env.example` files are local-development values
only. Do not reuse them outside an isolated development environment.

## Working method

- Work on one numbered checkpoint or explicitly bounded issue at a time.
- State a brief plan before material changes.
- Keep changes within the active checkpoint.
- Update API contracts and generated types whenever an API changes.
- Record material architecture decisions as ADRs.
- Update `docs/STATUS.md`, `docs/DECISIONS.md` and
  `docs/QUALITY_REVIEW.md` at the end of each checkpoint.
- Never commit secrets, real learner data or unlicensed media.

## Documentation

- [Master plan](ENTERPRISE_PRODUCT_MASTER_PLAN.md)
- [Agent instructions](AGENTS.md)
- [Current status](docs/STATUS.md)
- [Decision ledger](docs/DECISIONS.md)
- [Quality review](docs/QUALITY_REVIEW.md)
- [ADR template](docs/adr/0000-template.md)

Archived source plans are historical evidence only and are not authoritative.

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

The repository is at **PR-003 — Tenancy and identity foundation**. The
PostgreSQL-backed organization and identity schema, same-origin web session
flow, Android PKCE foundation, tenant-scoped authorization, direct-learner
assignment and institution-invite acceptance are implemented. PR-003 remains
pending checkpoint review and acceptance; curriculum, learning journeys, AI,
speech, payments, offline sync and reporting have not been introduced.

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

A fresh PostgreSQL Compose volume also creates the isolated
`global_english_test` database and role used by the PostgreSQL integration
suite. For a volume created before PR-003, start the services and apply the
idempotent initializer once:

```text
docker compose exec postgres sh -lc 'psql --set ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" --file /docker-entrypoint-initdb.d/001-create-test-database.sql'
```

After migrating and seeding a local database, create ignored Passport signing
keys and a public Android client with the reviewed application redirect URI:

```text
cd apps/api
php artisan passport:keys
php artisan passport:client --public
```

Never commit Passport private keys or configure a client secret for the
Android public client. Production OTP delivery, privileged MFA and native
secure credential storage require separately reviewed implementations.
Production must also supply an independently generated, backed-up
`AUTH_IDENTITY_DIGEST_KEY`; it is intentionally separate from `APP_KEY` and
must not be rotated in place without the versioned migration described in
`docs/PR-003_TENANCY_VERIFICATION_SCOPE.md`.

## Root commands

| Command | Purpose |
|---|---|
| `make bootstrap` | Install dependencies and prepare the local environment |
| `make dev` | Run the API, web application, workers and local dependencies |
| `make verify` | Run formatting, linting, static analysis, tests and contract checks |
| `make test` | Run all deterministic automated tests |
| `make e2e` | Run critical browser journeys |
| `make ai-eval` | Run the locked AI and speech evaluation suite |
| `make openapi` | Validate OpenAPI policy and check generated-client drift |
| `make migrate` | Apply local migrations |
| `make seed` | Load safe development seed data |
| `make build` | Create production builds |

Use `pnpm run contracts:generate` after an intentional OpenAPI change, then
review the generated diff. `make ai-eval` intentionally exits with a checkpoint
message until PR-011; it does not report placeholder success.

## Repository layout

- `apps/api/` — Laravel 13 modular monolith with operational, tenancy and
  identity foundation APIs;
- `apps/web/` — React 19.2 TypeScript/Vite shell, error boundary and shared
  authentication adapters;
- `docs/api/` — canonical OpenAPI 3.1.1 contract;
- `docs/adr/` and `docs/APPROVALS.md` — accepted architecture decisions and
  durable human approval evidence;
- `packages/contracts/` — generated TypeScript client, types and JSON schemas;
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

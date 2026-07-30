# Global English

Global English is a mobile-first spoken-English platform for adult learners.
English remains the learning language and Kannada remains the default
guidance language. Phase 1 also provides UI-only review previews in Malayalam,
Tamil, Telugu and Hindi. The canonical product, architecture, security and
delivery baseline is
[ENTERPRISE_PRODUCT_MASTER_PLAN.md](ENTERPRISE_PRODUCT_MASTER_PLAN.md).

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

PR-006 — Deterministic daily-speaking slice is implemented, verified and
accepted at its exact implementation commit under APP-0007. PR-007 — Offline
bundle and idempotent outbox is implemented, repository-verified and accepted
at `e0e2f1b90e0eb4ecf680f5243b57ec0731c32648` under APP-0009. PR-008 —
Conversation domain with deterministic fake providers — is implemented and
repository-verified under APP-0010, with exact-commit acceptance still
pending. Real providers, audio upload/persistence, PR-009 and production
remain unauthorized. ADR-0005 additionally brings UI-only Malayalam
(`ml-IN`), Tamil (`ta-IN`), Telugu
(`te-IN`) and Hindi (`hi-IN`) implementation previews into Phase 1 alongside
Kannada (`kn-IN`) and the English (`en-IN`) source/fallback.

All five translated interface catalogues are drafts pending independent human
review. Ordinary production builds use the strict `approved-only` policy and
visibly fall back to English. Local development, tests and an explicitly
requested review build may render current drafts with a visible preview
notice. The review flag must not be enabled for a production deployment:

```text
VITE_UI_TRANSLATION_PREVIEW=true pnpm --filter @global-english/web build
```

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

For the PR-008 local/testing prototype, set
`CONVERSATION_PROVIDER_DRIVER=fake`. Production and every environment other
than `local` or `testing` fail closed even if that value is supplied. The
prototype accepts bounded typed text only, uses no real provider and creates
or persists no audio.

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
Production must independently generate and access-control a minimum 32-byte
`OFFLINE_BUNDLE_SIGNING_KEY` (the `base64:` form is supported). The
local-development example key and test key must never be reused outside their
isolated environments.

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
`pnpm run locale:check` validates catalogue parity, normalization, hashes,
source linkage and review-state claims.

## Repository layout

- `apps/api/` — Laravel 13 modular monolith with operational, tenancy and
  identity foundation APIs;
- `apps/web/` — React 19.2 TypeScript/Vite localized shell, protected offline
  learner store, accessible error boundary and shared authentication adapters;
- `docs/api/` — canonical OpenAPI 3.1.1 contract;
- `docs/adr/` and `docs/APPROVALS.md` — accepted architecture decisions and
  durable human approval evidence;
- `packages/contracts/` — generated TypeScript client, types and JSON schemas;
- `packages/design-system/` — accessible multilingual presentation primitives,
  tokens and self-hosted Indian-script font integration;
- `packages/localization/` — governed UI catalogues, deterministic resolver,
  Unicode normalization and locale validation;
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
- [PR-004 accessibility verification scope](docs/PR-004_ACCESSIBILITY_VERIFICATION_SCOPE.md)
- [ADR template](docs/adr/0000-template.md)

Archived source plans are historical evidence only and are not authoritative.

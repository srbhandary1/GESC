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

The repository is at **PR-000 — Governance baseline**. It contains no product
application scaffold yet. Laravel, React, local services and executable root
commands belong to PR-001.

## Prerequisites

For PR-000:

- Git;
- a Markdown-capable editor.

PR-001 will pin and document the required PHP, Composer, Node.js, pnpm,
container-runtime and platform versions.

## Planned root commands

The master plan requires the following stable repository commands. They are
planned for PR-001 and must not be reported as available until implemented:

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

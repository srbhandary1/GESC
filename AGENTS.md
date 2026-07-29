# AGENTS.md

## Repository authority

Read these files before making changes:

1. `ENTERPRISE_PRODUCT_MASTER_PLAN.md`
2. `docs/STATUS.md`
3. relevant accepted ADRs in `docs/adr/`
4. the active issue or specification

The master plan is the product and architecture source of truth. An accepted ADR may amend it only when the ADR explicitly states the amendment and the master plan is updated.

## Working method

- Work on one numbered checkpoint or explicitly bounded issue at a time.
- Inspect existing code and tests before editing.
- State a short implementation plan before material changes.
- Do not implement later checkpoints opportunistically.
- Do not perform unrelated broad refactors.
- Stop and record a decision request when genuine ambiguity affects security, privacy, authoritative records, tenancy or foundation schema.
- Prefer a complete vertical slice over unused horizontal scaffolding.
- Never claim a command, test or provider call passed unless it actually ran successfully.

## Architecture rules

- Keep the core as a Laravel modular monolith.
- Keep AI and speech providers behind project-owned adapter interfaces.
- Do not expose provider SDK types to domain modules.
- Keep marks, mastery, progression, permissions and retention deterministic.
- Every tenant-owned resource requires organization scoping, authorization and cross-tenant tests.
- Use transactions for authoritative multi-write operations and idempotency for replayable requests.
- Published content and submitted evidence are immutable/versioned.
- Use Sanctum cookie sessions for web/PWA and Passport Authorization Code with PKCE for Android behind the project authentication abstraction.
- Never store authentication credentials in browser localStorage, IndexedDB or ordinary Capacitor preferences; Android credentials belong only in reviewed native secure storage.
- Do not log raw sensitive audio, transcripts, secrets or personal learner data.
- Never commit real learner data or unlicensed media.

## Contracts and documentation

- Update OpenAPI and generated client types whenever an API changes.
- Add or update tests with behaviour changes.
- Add an ADR for material architecture changes.
- Update `docs/STATUS.md`, `docs/DECISIONS.md` and `docs/QUALITY_REVIEW.md` at the end of each checkpoint.
- Keep public interfaces and non-obvious decisions documented.

## Verification

Use repository root commands. Normally run:

```text
make verify
make e2e        # when a critical user journey changed
make ai-eval    # when prompts, models, providers or orchestration changed
make build      # when packaging or deployment changed
```

If the repository does not yet provide these commands, the active bootstrap checkpoint must create them before feature work proceeds.

## Completion report

Conclude each task with:

- files changed;
- behaviour delivered;
- tests and commands run with results;
- migrations/configuration added;
- security, privacy and tenancy considerations;
- known limitations;
- recommended next checkpoint.

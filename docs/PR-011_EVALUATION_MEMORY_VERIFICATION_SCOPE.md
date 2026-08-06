# PR-011 evaluation harness and memory controls

## Authority and boundary

PR-011 is authorized for implementation by
[APP-0019](APPROVALS.md#app-0019) against the accepted PR-010 baseline. It
implements only the master-plan §26 PR-011 checkpoint:

- locked AI/speech evaluation suite (AI half always; speech complete only when
  `SPEECH_BENCHMARK_RUN_ID` is set under ADR-0008 gates);
- human-review export/import for evaluation dispositions;
- model/prompt comparison report and AI cost ledger rollup;
- learner-visible memory list/edit/delete; and
- memory policy and immutable audit digests.

PR-011 does not authorize real LLM providers, PR-012 role-play work, production
deployment, Google Play release or AI authority over deterministic learning
records.

## Vertical slice

The slice proves:

1. an authenticated learner lists, creates, updates and soft-deletes
   purpose-bound `conversation_context` / `learner_explicit` memory facts
   through `/api/v1/me/ai-memory`;
2. every active fact is returned to the owning membership (no hidden active
   facts);
3. active facts are allowlisted into Friend conversation context with hard
   caps (5 facts × 200 characters);
4. soft-delete writes immutable `memory_events` digests and does not delete
   learning or account records;
5. the Practice UI exposes list, create, edit and delete for learner-visible
   facts;
6. `php artisan ai:evaluate` / `make ai-eval` runs the hash-locked synthetic
   suite on deterministic fake providers and writes a comparable prompt and
   cost report; and
7. human-review packages export pending dispositions and import
   `pending`/`accept`/`reject` against the exact suite key and hash, failing
   closed on missing, mismatched or unknown-case packages.

## Persistence and privacy boundary

- Memory fact plaintext is learner-visible by design and stored only for the
  owning organization membership.
- Audit events store digests and safe metadata, not post-delete plaintext
  trails beyond the soft-deleted fact row lifecycle defined by the service.
- Evaluation fixtures are synthetic. Reports store aggregate digests and
  zero fake estimated cost.
- Conversation utterance/reply text remains request-memory-only as in PR-008.
- Memory and evaluation paths never mutate `learner_attempts`,
  `mastery_states`, review schedules or curriculum publications.

## Tenancy and authority

- Memory routes use existing first-party authentication and
  `LearnerAuthority`.
- Facts and events are organization-scoped and bound to the exact active
  learner membership.
- Same-organization other-learner and cross-organization access fail closed.
- The learner cannot supply organization, membership, purpose outside the
  allowlist, or hidden facts.

## API surface

```text
GET    /api/v1/me/ai-memory
POST   /api/v1/me/ai-memory
PATCH  /api/v1/me/ai-memory/{fact}
DELETE /api/v1/me/ai-memory/{fact}
```

Mutations require an idempotency key, strict allowlisted JSON and
`private, no-store` responses. OpenAPI document version 0.8.0 and generated
TypeScript contracts cover these routes.

## Evaluation commands

```text
php artisan ai:evaluate
php artisan ai:evaluate --export-review=path
php artisan ai:evaluate --import-review=path
php artisan ai:evaluate --report=path
make ai-eval
```

`make ai-eval` always runs the AI evaluation half. The PR-009 speech complete
phase runs only when `SPEECH_BENCHMARK_RUN_ID` is set and ADR-0008 evidence
gates pass.

## Verification

Required automated evidence:

- PostgreSQL isolation, ownership and learning-freeze tests for memory;
- evaluation suite hash stability and runner pass on locked fixtures;
- human-review import accept/pending success, reject failure and fail-closed
  mismatch/unknown-case/missing-file tests;
- HTTP auth boundary coverage for memory routes;
- orchestration allowlist coverage for active memory facts;
- Practice UI unit coverage for list/create/edit/delete;
- OpenAPI and generated-client drift checks;
- locale governance for catalogue key parity; and
- PHPStan on changed PHP paths.

## Explicit non-goals

- Real conversation-model or LLM providers
- Ordinary application binding of live Azure Speech
- PR-012 Observe/Rehearse/Perform/Reflect role-play
- Content studio, approved curriculum modules or production/Play release
- Auto-ingestion of memory from raw conversation turns

## Exit gate

PR-011 is ready for Accountable Product Owner exact-commit acceptance only
when:

- learner-visible memory list/edit/delete works end to end in API and Practice
  UI;
- no active memory fact is hidden from the learner;
- prompt/model changes produce a comparable evaluation report on the locked
  fake suite;
- human-review import is fail-closed and tested;
- repository-controlled checks recorded in [QUALITY_REVIEW.md](QUALITY_REVIEW.md)
  pass within recorded limitations; and
- STATUS/DECISIONS/APPROVALS preserve that PR-012 and production remain
  unauthorized until separate APP records exist.

Implementation and successful tests do not themselves accept PR-011 or
authorize PR-012.

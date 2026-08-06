# PR-011 APO acceptance package

This package prepared exact-artifact Accountable Product Owner acceptance of
PR-011. **Accepted** under [APP-0020](APPROVALS.md#app-0020) / D-0093 at
`220628f4d4c69284af0b1f2534e8902f3b272088` with the recorded limitations
below. APP-0019 only authorized beginning implementation.

## Artifact accepted

| Field | Value |
|---|---|
| Checkpoint | Master-plan §26 PR-011 — Evaluation harness and memory controls |
| Branch | `codex/pr-011-evaluation-memory` |
| Governing implementation authorization | [APP-0019](APPROVALS.md#app-0019) |
| Acceptance record | [APP-0020](APPROVALS.md#app-0020) |
| Verification scope | [PR-011_EVALUATION_MEMORY_VERIFICATION_SCOPE.md](PR-011_EVALUATION_MEMORY_VERIFICATION_SCOPE.md) |
| Quality evidence | [QUALITY_REVIEW.md](QUALITY_REVIEW.md#pr-011--evaluation-harness-and-memory-controls) |
| Exact commit | `220628f4d4c69284af0b1f2534e8902f3b272088` |

## Delivered behaviour

- Learner-visible AI memory list, create, edit and delete via
  `/api/v1/me/ai-memory` and the Practice `AiMemoryPanel`.
- No active memory fact is hidden from the owning learner membership.
- Soft-delete retains immutable `memory_events` digests and does not delete
  learning or account records.
- Locked deterministic AI evaluation suite with comparable prompt/cost report.
- Human-review export/import bound to exact suite key/hash; reject fails the
  case; mismatch/unknown/missing packages fail closed.
- Active memory facts allowlisted into Friend conversation context with hard
  caps (5 × 200 characters).

## Verification evidence (this session)

- PostgreSQL filter suite via Docker PHP 8.4.24 + `pdo_pgsql`: **13 tests /
  204 assertions** (`AiEvaluationRunnerTest`, `LearnerMemoryTest`,
  `ConversationOrchestrationTest`, `LearnerHttpBoundaryTest`).
- Host unit: `AiEvaluationSuiteTest` passed; PHPStan 0 errors on PR-011 paths;
  Pint clean on touched PHP.
- Web: `AiMemoryPanel` vitest **1 passed** (list/create/edit/delete).
- OpenAPI/contracts: `pnpm run openapi:check` passed.
- Locale governance: Node 24.18.1 container — **6 catalogues, 5
  pending-independent-review warnings**.
- `php artisan ai:evaluate`: status `completed`, 4 passed / 0 failed,
  suite_hash `9d1f8d6069af09abbc9c20dd5048af6c640a4c77c927f0f43eed3364c13dc5e6`,
  report_digest `407d67cc39091c5703a291c042da3ba7bb14b1f1f67f236b70a305ab883cd318`,
  estimated_cost_microunits `0`. Speech half skipped (no
  `SPEECH_BENCHMARK_RUN_ID`).

## Recorded limitations (must accompany acceptance)

1. Deterministic fake conversation-model providers only; production remains
   fail-closed for real LLM / conversation-model providers.
2. Evaluation cost rollup is aggregate/zero on fakes and is not a live spend
   ledger.
3. Speech half of `make ai-eval` remains PR-009-gated and was not run in this
   acceptance evidence set.
4. UI locale memory strings (and other pending catalogues) remain pending
   independent human review; ordinary production stays `approved-only` English
   fallback.
5. Host PHP 8.5.4 lacks `pdo_pgsql`; full host `make verify` and remote GitHub
   Actions were not claimed as green in this session. PostgreSQL evidence used
   Docker PHP 8.4.24 against the local Compose Postgres service.
6. This acceptance does **not** authorize PR-012, real LLM providers,
   production deployment, Google Play release or real learner production data.
7. AI remains advisory and must not mutate authoritative learning records.

## APO statement (recorded)

```text
APO accept PR-011 at 220628f4d4c69284af0b1f2534e8902f3b272088 with its recorded limitations
```

Interpretation for the recorder (Codex is never an approver):

- Accept the exact commit only, with every recorded limitation above.
- Authorize **consideration** of a separate PR-012 APP only; do not treat this
  as PR-012 implementation authorization.
- Do not waive independent legal, security, privacy, tenancy, accessibility,
  language/content, vendor or release review before production.

## Recorder checklist after APO statement

1. Appended [APP-0020](APPROVALS.md#app-0020) to [APPROVALS.md](APPROVALS.md).
2. Appended D-0093 to [DECISIONS.md](DECISIONS.md).
3. Updated [STATUS.md](STATUS.md) current checkpoint to accepted PR-011.
4. Appended acceptance note to [QUALITY_REVIEW.md](QUALITY_REVIEW.md).
5. Do **not** start PR-012 code until a distinct APP authorizes the bounded
   synthetic role-play slice in
   [PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md](PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md).

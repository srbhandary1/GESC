# MAINT-01 maintenance review

## Scope

MAINT-01 is a behavior-preserving maintenance checkpoint under
[the ordered remediation plan](CODEBASE_GAP_REMEDIATION_PLAN.md). It covers
only three measured risks:

1. repeated HTTP `Idempotency-Key` validation that could drift between
   replay-safe commands;
2. repeated integration-test permission and privileged-session fixtures; and
3. support-diagnostics queries whose tenant indexes did not provide their
   required order.

It also makes `docs/STATUS.md` a current-state document again. Historical
checkpoint evidence remains in [QUALITY_REVIEW.md](QUALITY_REVIEW.md), the
[decision ledger](DECISIONS.md), the approval ledger and Git history.

MAINT-01 does not add an endpoint, response field, product capability,
provider, prompt, tenant relationship or authoritative write. It does not
begin ADMIN-GOV-01.

## Deduplication boundary

### Replay-safe request validation

Twenty-two existing command request classes now inherit the documented
`App\Http\Requests\IdempotentRequest` boundary. That class:

- adds exactly one required UUID-or-ULID rule;
- always obtains the value from `Idempotency-Key` after payload/route data is
  assembled, so a body field cannot replace a missing or invalid header; and
- leaves each request responsible for its existing payload, route and
  unsupported-field rules.

The accepted request and API behavior is unchanged. A focused regression test
covers UUID and ULID acceptance, invalid-header rejection, body-value
non-authority and custom route-derived validation data.

### Integration fixtures

`TenancyIntegrationTestCase` now owns the common synthetic permission grant,
MFA factor, privileged-session creation and existing-session promotion
fixtures. Six previously duplicated permission helpers delegate to the common
fixture, and curriculum/operator tests reuse the common privileged-session
contract.

The fixture remains test-only. It creates organization-owned roles and grants,
uses only seeded global permission keys and synthetic encrypted TOTP material,
and does not weaken production authorization.

The shared Laravel test base also creates the minimum isolated storage
directories whenever `LARAVEL_STORAGE_PATH` isolates a suite under `/tmp`.
Missing directories request mode 0700, and creation occurs before Laravel
resolves its compiled-view path, so verification no longer depends on a
workstation having pre-seeded test directories. Existing caller-supplied root
permissions are not rewritten.

## PostgreSQL query-plan review

The review used the exact three queries in `SupportDiagnosticsQuery`, the
migrated PostgreSQL test schema and `enable_seqscan=off`. Disabling sequential
scans is deliberate here: the isolated schema has no representative row
cardinality, and the checkpoint needs deterministic evidence that an eligible
tenant-prefixed index can satisfy both filtering and ordering. This is index
eligibility evidence, not a production latency benchmark.

| Query | Before migration | Added index | After migration |
|---|---|---|---|
| stale in-progress learner attempts | `learner_attempts_one_open_unique` plus explicit `Sort(updated_at, id)` | `(organization_id, status, updated_at, id)` | index-only scan; no `Sort` |
| recoverable voice-provider failures | lifecycle index plus explicit descending sort | `(organization_id, status, updated_at, id)` | backward index-only scan; no `Sort` |
| open voice-deletion timeline | recording-identity index, status filter and explicit descending sort | `(organization_id, requested_at, id, status)` | backward index-only scan; no `Sort` |

The deletion status column is intentionally last. The read model combines
three open states into one requested-time timeline; putting status before time
would split the ordering into separate status ranges and retain a sort.

`SupportDiagnosticsQueryPlanTest` executes PostgreSQL `EXPLAIN (FORMAT JSON)`
for all three query builders and fails if the expected index disappears or a
`Sort` node returns.

## Migration and rollback

Migration `2026_08_09_000110_add_support_diagnostics_indexes.php` adds only the
three indexes above. Every leading key is `organization_id` in accordance with
ADR-0003. Rollback drops only those named indexes; no column, constraint,
record or API contract changes.

## Verification evidence

- Request baseline before refactoring: 9 tests / 32 assertions passed.
- Focused request regression after refactoring: 12 tests / 39 assertions
  passed.
- Focused PostgreSQL fixture/query-plan regression: 53 tests / 732 assertions
  passed.
- Query-plan test alone: 3 tests / 18 assertions passed.
- PostgreSQL rollback and reapply of the diagnostics-index migration passed;
  the three plan contracts passed again after reapply.
- PHPStan level 8: passed with zero errors after removing one unreachable
  defensive branch identified by the analyzer.
- PostgreSQL integration rerun from the previously incomplete isolated storage
  root: 206 tests / 2,777 assertions passed.
- Final `make verify`: passed — Composer/pnpm audits; Pint; PHPStan level 8;
  123 SQLite tests / 939 assertions; 206 PostgreSQL tests / 2,777 assertions;
  locale governance; OpenAPI/generated-client drift; release-contract
  validation; ESLint; recursive TypeScript; and 182 package/web tests.

The first isolated-storage gate attempts failed during Blade path resolution,
before product assertions, because the externally selected SQLite root and the
PostgreSQL-forced root lacked `framework/views`. The shared pre-boot fixture
was added, the incomplete PostgreSQL root then passed, and the complete gate
passed on rerun.

## Security, privacy and tenancy

- Header-only replay-key authority remains fail closed; request bodies cannot
  establish an idempotency key.
- All new database indexes begin with tenant ownership and expose no data.
- Query-plan tests use a synthetic organization identifier and inspect plan
  metadata only.
- Shared test fixtures use synthetic identities/roles and never contain real
  learner, audio, transcript, credential or provider data.

## Limitations

- Empty-schema `EXPLAIN` proves eligible ordering, not production selectivity,
  cache behavior or latency. Representative pilot-volume `EXPLAIN ANALYZE`
  remains a pre-production capacity activity.
- The three indexes add ordinary write/storage overhead; production index
  usage and bloat still require operational monitoring.
- Broader curriculum value-reader duplication and large historical test files
  were deliberately not refactored in this bounded checkpoint.
- The existing Vite chunk-size warning, optional Rolldown peer mismatch,
  physical-device evidence and production gates remain open.

## Next checkpoint

With MAINT-01 closed, the approved roadmap permits only ADMIN-GOV-01 next.
That checkpoint must freeze capability, permission, ownership, retention and
route matrices before any ADMIN feature schema.

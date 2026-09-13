# REL-01 release gates

## Required CI graph

One stable `release-gate` check depends on all of these jobs:

| Job | Required behavior |
|---|---|
| `verify` | Digest-pinned PostgreSQL integration, dependency audits, formatting, static analysis, OpenAPI/client drift, package tests, critical Chromium E2E and production build |
| `android` | JDK 21, API/target 36 production Capacitor sync, `testDebugUnitTest` and `assembleDebug` |
| `operations` | Migrated non-production PostgreSQL/Redis, readiness load evidence and logical backup/restore evidence |
| `release-gate` | Runs with `always()` and succeeds only when every upstream result is exactly `success` |

`pnpm run release:check` parses `.github/workflows/ci.yml` and fails when a
required job/command/dependency disappears, PostgreSQL loses its image digest,
an action loses its full-SHA pin or the aggregate gate stops failing closed.
Repository branch protection should require the stable `release-gate` check.

## Load drill

`make load-soak` sends bounded concurrent requests to `/health/ready` by
default. It records successful/failed requests, p50/p95/max latency, threshold,
commit and working-tree cleanliness. It exits non-zero on any failed/non-200
request or when p95 exceeds `LOAD_SOAK_MAX_P95_MS`.

REL-01 local working-tree evidence used 100 requests at concurrency 10 against
the real Laravel readiness route: 100 succeeded, none failed, p95 was 202 ms
against the explicit 1,000 ms drill threshold. CI repeats the same bounded
drill from a clean checkout. This is dependency/readiness evidence, not the
master-plan authenticated normal-API SLO, the 2× pilot capacity test or a
production soak.

## Failure and promotion rules

- Failed, cancelled and skipped upstream jobs all fail the aggregate gate.
- Missing/stale/dead-lettered voice deletion work fails its operational health
  command and the scheduled worker result.
- A drill writes evidence but returns non-zero when its threshold fails;
  existence of a JSON file is never sufficient.
- Local dirty-working-tree evidence is diagnostic only. Release-candidate
  promotion requires a clean CI run on the exact candidate commit.
- Physical Android device matrix, signed AAB, production PITR, representative
  2× pilot load, independent reviews and explicit production/public-release
  approval remain separate gates.

## AUTH-PASSWORD-01 security exception

ADR-0021 deliberately replaces privileged MFA with re-entry of the same
password. Release evidence must label this as one-factor freshness and retain
APP-0055's explicit accepted risk; a passing automated gate must not relabel it
as MFA or claim independent-factor protection. Production promotion additionally
requires the password blocklist source/licence/freshness record, recovery
delivery and privileged identity-proofing procedure, a synthetic cutover drill,
independent security/privacy/tenancy review and explicit production approval.

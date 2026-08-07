# Backup and restore drill (non-production)

**Authority:** [APP-0032](APPROVALS.md#app-0032) / PH-07  
**Planning targets:** RPO ≤ 15 minutes; RTO ≤ 2 hours (master plan §20.1)  
**Scope:** Local/staging Postgres only. Not a production cutover.

## Preconditions

1. Docker Compose Postgres is reachable (`make services-up`).
2. No real learner production data is present in the drill database.
3. Operator records the Exact commit SHA under test.

## Procedure

```bash
# From repository root
make backup-restore-drill
```

The script:

1. Creates a logical `pg_dump` of the configured local API database.
2. Restores into a temporary database `ge_restore_drill_*`.
3. Verifies migration table presence and a simple `SELECT 1`.
4. Drops the temporary database.
5. Writes a summary JSON under `artifacts/ops/` (gitignored).

## Evidence fields to record

| Field | Value |
|---|---|
| Commit SHA | `21f577cb2b1ac032124f65d3774b46e45e665196` |
| Environment | local Docker Compose Postgres |
| Dump started at | 2026-08-07T16:18:01Z |
| Restore completed at | 2026-08-07T16:18:02Z |
| Measured RPO gap | Planning target ≤ 15 minutes (WAL/PITR not claimed) |
| Measured RTO | 2 seconds |
| Result | Pass — `artifacts/ops/backup-restore-20260807T161800Z.json` |

## Limitations

- Continuous WAL/PITR is not claimed by this drill.
- Production backup tooling and offsite retention remain separately gated.
- Do not log connection passwords or learner row contents.

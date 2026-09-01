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
| Commit SHA | |
| Environment | local / staging-like |
| Dump started at | |
| Restore completed at | |
| Measured RPO gap | |
| Measured RTO | |
| Result | pass / fail + notes |

## Soft-launch gzip dump (Wave 4)

The live invite-only database is also dumped by `make soft-launch-backup` to
`~/ge-soft-launch/backups/soft-launch-YYYYMMDDTHHMMSSZ.sql.gz` (mode `0600`),
then wrapped with AES-256-CBC (`pbkdf2`, 200000 iterations). Ciphertext is
copied to `$HOME/ge-offsite-backups/` (override with
`GE_SOFT_LAUNCH_OFFSITE_BACKUP_DIR`). The passphrase file is
`$HOME/ge-soft-launch/secrets/backup.pass` (mode `0600`, outside git).

`make soft-launch-restore-drill` restores that gzip into a **temporary**
database, compares migration/schema/deletion aggregates with live, then drops
the temp database. It never `DROP`s `POSTGRES_DB`.

This host currently has a single NVMe volume. The encrypted copy is therefore
same-machine until you copy the `.enc` file to USB or another box.

Incident restore procedure: [SOFT_LAUNCH_ROLLBACK.md](SOFT_LAUNCH_ROLLBACK.md).

## Limitations

- Continuous WAL/PITR is not claimed by this drill.
- Production backup tooling and offsite retention remain separately gated.
- Do not log connection passwords or learner row contents.

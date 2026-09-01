# Backup and restore drill (non-production)

**Authority:** REL-01, D-0128, D-0132, D-0134, D-0135 and master plan §§20.1,
20.3 and 21.1

**Planning targets:** RPO ≤ 15 minutes; RTO ≤ 2 hours

**Scope:** Local/CI/staging PostgreSQL only. Not a production cutover or PITR
claim.

## Preconditions

1. Docker Compose PostgreSQL is reachable (`make services-up`, or start only
   `postgres`).
2. The configured database contains no real learner production data.
3. All application migrations, including outbox dead-letter state, are
   applied. For ADMIN-ORG-01 this includes administration receipts/events,
   selected-membership session binding and durable session-storage cleanup.
   For ADMIN-LEARNER-01 it also includes the governed current-goal projection,
   append-only correction history, enrollment version/current-row constraints
   and tenant-bound learner/profile/enrollment event links.
4. `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_SERVICE` and
   `BACKUP_RESTORE_RTO_SECONDS` use the documented safe values.

## Procedure

```bash
# From repository root; defaults to the local global_english database.
make backup-restore-drill

# Isolated test-database example.
POSTGRES_DB=global_english_test make backup-restore-drill
```

The fail-closed script:

1. validates the Compose service and database identifiers;
2. requires migrations plus the voice/privacy/outbox deletion and learner
   goal-authority tables;
3. creates a mode-0600 custom-format logical dump;
4. proves the archive can be listed and restores it into a uniquely named
   temporary database;
5. compares the ordered application column schema, migration ledger and
   aggregate deletion/tombstone plus learner-goal authority state between
   source and restore without printing row content;
6. fails when any comparison, dump/restore command or the two-hour RTO target
   fails;
7. drops the temporary database and removes the dump on success or failure;
8. writes safe JSON under `artifacts/ops/` (gitignored), including whether the
   working tree was clean.

The CI `operations` job runs this drill after a clean migration and the
`release-gate` job requires it to succeed.

## ADMIN-ORG restore overlay requirement

No restored environment may serve traffic until an independently current,
access-controlled deletion/revocation overlay has been applied and reconciled.
That overlay must cover at least terminal invitation digest clearing,
membership-selected credential revocation, completed session-storage cleanup,
and purged ADMIN-ORG receipts/events. It must not be sourced solely from the
same older backup being restored: doing so could revive authority or secrets
removed after the recovery point.

Encrypted backups containing ADMIN-ORG records have a maximum 30-day age-out
ceiling. Production enablement therefore requires documented overlay custody,
replay idempotency, reconciliation evidence, restore audit, and proof that no
backup older than that ceiling remains recoverable. The local logical drill
checks database preservation only; it does not claim this external overlay or
production retention control exists.

## ADMIN-LEARNER restore extension

The same current overlay must include terminal learner-invitation digest
clearing, learner membership/enrollment lifecycle state, selected-credential
revocation and purged learner-administration receipts/events. A restored backup
must not revive a withdrawn enrollment, suspended membership, terminal invite
secret or post-recovery credential selection.

Original onboarding goals, the versioned current-goal projection and
append-only attributed corrections are retained authority, not purge targets.
The drill therefore compares the full schema/migration ledger and count-only
goal projection/correction aggregates without printing goal keys, actor links
or learner identifiers.

## Latest local ADMIN evidence

| Field | Result |
|---|---|
| Environment | Local Docker Compose PostgreSQL 18.4 test database; synthetic/test data only |
| Logical dump/list/restore | Pass |
| Application column schema and migration ledger | Exact match |
| Deletion/tombstone and learner-goal authority aggregate state | Exact match |
| Measured RTO | 1 second; target 7,200 seconds |
| Evidence | `artifacts/ops/backup-restore-20260809T144636Z.json` (gitignored working-tree evidence) |

CI must create fresh clean-commit evidence; this local result is not attached
to a committed release candidate.

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

- The RPO value remains a planning target. This logical drill does not test
  continuous WAL archiving, point-in-time selection or a 15-minute production
  recovery point.
- Production encryption, backup access controls, offsite retention and restore
  audit, the current revocation/purge overlay and the 30-day age-out control are
  separate pre-production gates.
- No connection password, learner row, object key, audio, transcript or export
  body may enter the evidence JSON or logs.

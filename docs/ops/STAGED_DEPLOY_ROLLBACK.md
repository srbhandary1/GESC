# Staged deploy and rollback dry-run

**Authority:** [APP-0032](APPROVALS.md#app-0032) / PH-09  
**Scope:** API + web build rehearsal. Not production cutover. Not Play public
release.

## Dry-run command

```bash
make staged-deploy-dry-run
```

The script:

1. Runs `make build` (API optimize + web production bundle).
2. Confirms `apps/web/dist` and API bootstrap artifacts exist.
3. Records a rollback checklist (previous artifact retention note).
4. Writes `artifacts/ops/staged-deploy-dry-run.json` (gitignored).

## Rollback rehearsal checklist

1. Keep the previous `dist` tarball / image tag until the new build is healthy.
2. Re-point the staging reverse proxy / static host to the previous artifact.
3. Re-run `/health/live` and `/health/ready`.
4. Spot-check Learn home + operator console load (no real learner production
   data required).
5. Record correlation ids for any failures.

## Explicit non-claims

- No Kubernetes/Helm production manifests are authorized by this dry-run.
- Google Play track promotion remains unauthorized.
- Database migrate/rollback against production is out of scope here — use the
  [backup/restore drill](BACKUP_RESTORE_DRILL.md) for data recovery practice.

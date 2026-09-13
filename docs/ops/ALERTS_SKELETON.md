# Monitoring and alert stubs

**Authority:** [APP-0032](APPROVALS.md#app-0032) / PH-08  
**Status:** Skeleton only — not a production SRE contract.

## Signals to wire (safe metadata)

| Signal | Source | Suggested threshold (pilot planning) |
|---|---|---|
| API liveness fail | `/health/live` | 2 consecutive fails / 1 min |
| API readiness fail | `/health/ready` | 2 consecutive fails / 1 min |
| Auth HTTP 5xx rate | structured `http.request_completed` | > 5% of auth routes / 5 min |
| Auth HTTP 401 spike | same | Investigate OTP/provider; no PII |
| Provider cost ceiling | conversation/speech cost rollups (aggregate) | Approach existing APP spend caps |
| Deploy rollback | staged deploy dry-run / release notes | Manual page on failed dry-run |

## Non-signals

- Raw request bodies containing utterances
- Audio blobs or transcript text
- Access/refresh tokens
- Full learner profiles

## Soft-launch local observe (Wave 4)

User systemd timer `global-english-soft-launch-observe.timer` runs
`scripts/soft-launch-observe.sh` every minute. It records **counts only** in
`~/ge-soft-launch/alerts/observe.jsonl` and the user journal (`ge-soft-launch-observe`).

| Signal | Threshold |
|---|---|
| API down | `/health/live` fails twice in a row |
| Queue stuck | worker or scheduler pid dead, Redis unreachable, or Redis `*queues*` LLEN ≥ 25 |
| Provider 5xx | ≥ 3 non-health `http.request_completed` status 5xx **or** ≥ 3 `provider_http_failure` / connection / transport failures in 5 minutes of `laravel.log` |

Optional delivery (gitignored runtime, not invented here):
`GE_SOFT_LAUNCH_ALERT_MAILTO` (if `mail` exists) or `GE_SOFT_LAUNCH_ALERT_COMMAND`.
Named SMS/Telegram recipients remain APO.

Rollback: [SOFT_LAUNCH_ROLLBACK.md](SOFT_LAUNCH_ROLLBACK.md).

## Implemented repository signals

- `voice:deletion-status` emits aggregate counts/ages only and exits non-zero
  for stale work or dead letters.
- The scheduled deletion command carries that health result in its exit code.
- The hourly organization-administration maintenance command emits only
  aggregate expiry, digest-clear, session-cleanup and history-purge counts.
- CI validates the gate graph itself and an `always()` aggregate job fails
  unless every required job reports `success`.

## Next production wiring

- Prometheus / cloud metrics exporters
- PagerDuty / Opsgenie routing
- Dashboards with tenant-safe labels only (`organization_id` digests if needed)

# Monitoring and alert stubs

**Authority:** [APP-0032](APPROVALS.md#app-0032) / PH-08  
**Status:** Repository health/release signals implemented; external exporter,
routing and production SRE contract remain pending.

## Signals to wire (safe metadata)

| Signal | Source | Suggested threshold (pilot planning) |
|---|---|---|
| API liveness fail | `/health/live` | 2 consecutive fails / 1 min |
| API readiness fail | `/health/ready` | 2 consecutive fails / 1 min |
| Auth HTTP 5xx rate | structured `http.request_completed` | > 5% of auth routes / 5 min |
| Auth HTTP 401 spike | same | Investigate password/session abuse controls; no PII |
| Voice deletion outbox unhealthy | `php artisan voice:deletion-status` / scheduled `voice:purge-expired` exit code | Any dead letter or active event older than 3,600 seconds |
| Voice deletion retries | Same safe aggregate snapshot | Trend non-zero retry count; inspect stable error codes without object keys |
| ADMIN-ORG retention/cleanup backlog | Scheduled `operator:maintain-organization-administration` aggregate output | Any exhausted session cleanup or terminal invite digest beyond its 60-minute discard deadline |
| Release gate | GitHub Actions `release-gate` | Any skipped, cancelled or failed PostgreSQL/web, Android or operations job |
| Provider cost ceiling | conversation/speech cost rollups (aggregate) | Approach existing APP spend caps |
| Deploy rollback | staged deploy dry-run / release notes | Manual page on failed dry-run |

## Non-signals

- Raw request bodies containing utterances
- Audio blobs or transcript text
- Access/refresh tokens
- Staff invitation codes/digests, credential IDs or ADMIN-ORG target IDs
- Full learner profiles

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
- Dashboards with no tenant label unless a separately reviewed operational
  need establishes a safe cardinality and access boundary

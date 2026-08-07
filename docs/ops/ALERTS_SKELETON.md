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

## Next wiring (separate ops APP optional)

- Prometheus / cloud metrics exporters
- PagerDuty / Opsgenie routing
- Dashboards with tenant-safe labels only (`organization_id` digests if needed)

# Incident and support runbooks (bounded)

**Authority:** [APP-0032](APPROVALS.md#app-0032) / PH-08  
**Logging rule:** Never paste raw audio, transcripts, secrets, OTP codes, refresh
tokens, or learner PII into tickets or chat.

## Severity guide

| Severity | Meaning | Initial response |
|---|---|---|
| Sev-1 | Auth or data-integrity outage for all tenants | Page on-call; freeze deploys |
| Sev-2 | Single critical journey broken for many learners | Mitigate; consider rollback |
| Sev-3 | Degraded AI/speech with fallback available | Monitor; provider ticket |
| Sev-4 | Cosmetic / single-user | Track; no freeze |

## Health checks

1. `GET /api/v1/health/live` — process up.
2. `GET /api/v1/health/ready` — Postgres/Redis readiness.
3. Correlate with `X-Correlation-ID` / `http.request_completed` structured logs.

Alert stubs: [ALERTS_SKELETON.md](ALERTS_SKELETON.md).
Soft-launch rollback card: [SOFT_LAUNCH_ROLLBACK.md](SOFT_LAUNCH_ROLLBACK.md).

On this host, `make soft-launch-observe` (or the user timer) checks API liveness,
queue worker/scheduler, Redis backlog, and provider 5xx **counts**. Do not
paste `laravel.log` lines into chat.

STT rollback for Speak: set `VOICE_PROVIDER_DRIVER=azure` in the gitignored
soft-launch env and restart API/worker.

## Auth / PKCE incidents (web + Android closed beta)

1. Confirm health ready.
2. Check Passport/Sanctum configuration and redirect URI for
   `com.globalenglish.app:/oauth/callback` (Android) or web origin.
3. Ask the reporter for correlation id only — not tokens.
4. If refresh families are suspect, revoke the installation family via
   existing auth admin path (do not dump tokens into logs).

## ADMIN-ORG invitation or selected-session incidents

1. Treat an exposed or lost staff invitation code as a bearer-secret incident.
   Do not search logs, database digests, browser storage or analytics to recover
   it. Revoke the pending invitation and issue a new one with a new idempotency
   key.
2. An exact issuance replay proves the invitation already exists but returns no
   code. Do not fabricate, mask or re-display a value.
3. A membership-session revocation may leave retryable server-session storage
   cleanup after the database credential has been irrevocably marked revoked.
   Run `php artisan auth:cleanup-revoked-sessions` and record only aggregate
   completed/retried/exhausted counts.
4. If Laravel session selection and the protected authentication-session row
   diverge, preserve the fail-closed response and require a new explicit
   selection or sign-in. Never repair the pair by editing only browser state.
5. Escalate cross-organization effects, final-manager loss, authority revival
   after reactivation, or code/credential identifiers in logs as Sev-1
   tenancy/security incidents. Freeze ADMIN-ORG mutations until reconciled.

The complete data boundary and retention procedure is in
[ADMIN-ORG staff privacy and retention](../ADMIN_ORG_PRIVACY_RETENTION.md).

## Voice deletion backlog or dead letter

1. Run `php artisan voice:deletion-status`; do not query or copy raw objects.
2. Treat any dead letter or event older than the configured stale threshold as
   a privacy/reliability incident. Freeze voice enablement if the active-store
   deletion objective is at risk.
3. Repair the private-store deletion capability. Do not clear object keys,
   publish outbox events or complete receipts manually.
4. Requeue an exact dead-letter event with
   `php artisan voice:deletion-requeue <outbox-event-ulid>`, then run
   `php artisan voice:purge-expired`.
5. Close only after `voice:deletion-status` returns zero and the linked receipt
   reconciles. Tickets may contain aggregate counts, UTC window, correlation
   id and stable error code only.

## Support intake (pilot)

Collect only:

- organization display name / id (if known);
- approximate time window (UTC);
- journey name (Learn / Conversation / Role-play / Operator);
- correlation id;
- device matrix id for Android (DM-01…);
- whether mic permission was denied.

Do **not** collect full transcripts or audio files for this bounded console.

## Escalation

1. On-call engineer → product owner for Sev-1/2.
2. Provider outages → vendor channel with synthetic fixtures only.
3. Privacy/deletion requests follow the fresh-password-confirmation operator workflow and
   [privacy inventory runbook](../PRIVACY_DATA_INVENTORY.md); production legal
   and independent review gates remain open.

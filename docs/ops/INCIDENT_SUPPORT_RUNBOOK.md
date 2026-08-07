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

## Auth / PKCE incidents (web + Android closed beta)

1. Confirm health ready.
2. Check Passport/Sanctum configuration and redirect URI for
   `com.globalenglish.app:/oauth/callback` (Android) or web origin.
3. Ask the reporter for correlation id only — not tokens.
4. If refresh families are suspect, revoke the installation family via
   existing auth admin path (do not dump tokens into logs).

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
3. Privacy/deletion requests remain unauthorized under remaining PR-013 /
   ADR-0007 until a separate APP.

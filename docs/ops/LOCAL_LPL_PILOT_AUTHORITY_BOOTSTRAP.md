# Local LPL pilot authority bootstrap

**Scope:** APP-0051 / D-0150 / ADR-0019 local non-production exception only
**Status:** Executed on 10 August 2026; persistent enable switch absent

## Purpose and boundary

This command exists only to create the exact private LPL-01 owner program,
least-privilege role assignment and pending program-scoped rights-confirmer
appointment approved by DR-0024. It is not a general onboarding, support,
production or authority-recovery mechanism.

The command creates no curriculum version, publication, enrollment, learner
entitlement, media rights revision, transcript, provider job or content. It
never accepts the appointment. Those later actions must use ordinary
first-party web authentication, selected membership, fresh TOTP and the
existing HTTP command paths.

## Preconditions

Before execution, verify all of the following:

1. the runtime environment is `local` and the OTP adapter is `local`;
2. APP-0051, APP-0047 and APP-0004 remain current and unrevoked;
3. the exact Direct organization, user and membership identifiers in DR-0024
   are active and the membership retains its system administrator assignment;
4. the four exact DR-0024 assets are active `lesson_audio` records with their
   exact byte counts and selected `passed`/`clean` security scans;
5. neither `global-spoken-english-lpl-pilot` nor
   `lpl-pilot-commissioner` exists, unless this is an exact idempotent replay;
6. the invocation uses a new UUID/ULID idempotency key for first execution;
   and
7. no credential, raw media, transcript or learner data will enter command
   arguments, output, audit or outbox evidence.

## Execution

Supply the switch only to the one command process; do not persist it in an
environment file:

```text
APP_ENV=local \
AUTH_OTP_DRIVER=local \
CONTENT_GOVERNANCE_APP_0004_ACTOR_USER_ID=01KZGE9QRTCQE3PQ3RN31AXQSE \
LPL01_PILOT_AUTHORITY_BOOTSTRAP_ENABLED=true \
php apps/api/artisan lpl:bootstrap-local-pilot-authority \
  --organization=01KYS97G7MX374P303XJHDXT72 \
  --user=01KZGE9QRTCQE3PQ3RN31AXQSE \
  --membership=01KZGE9QRTCQE3PQ3RN31AXQSF \
  --approval=APP-0051 \
  --owner-evidence=APP-0047 \
  --idempotency-key=<new UUID or ULID>
```

A successful first execution returns only `created`, safe identifiers,
evidence references, environment and completion time. Reusing the same key
and exact arguments returns `replayed` only when all authoritative state still
matches. Any mismatch returns the generic
`local_lpl_pilot_authority_bootstrap_rejected` code and must be investigated;
do not repair it with SQL or a broader grant.

## Mandatory post-execution sequence

1. Confirm that `LPL01_PILOT_AUTHORITY_BOOTSTRAP_ENABLED` is absent from
   persistent environment files or explicitly false.
2. Sign in through ordinary OTP as the exact actor and select membership
   `01KZGE9QRTCQE3PQ3RN31AXQSF`.
3. Establish a fresh TOTP assertion through the normal MFA challenge.
4. Read and accept the exact pending appointment through the existing
   appointee HTTP routes with its current state fingerprint and a unique
   `Idempotency-Key`.
5. Submit one initial rights confirmation per exact DR-0024 asset through
   `POST /api/v1/operator/media/rights-decisions`, each with a unique
   idempotency key and the APP-0047 evidence hash and approved scope.
6. Prove there are exactly four approved initial revisions, one accepted
   appointment, no curriculum version/publication/enrollment and one
   content-free bootstrap audit/outbox event.

## Stop and recovery rules

- A failed bootstrap transaction creates no partial authority state.
- A failed HTTP rights command must leave that asset without a new revision;
  correct only the request's exact approved identifiers or state and retry
  with a new idempotency key.
- Do not delete the role or accepted appointment after use; they are visible,
  revocable authoritative records.
- Do not invoke a transcription/AI provider until a separate finite run
  ceiling, kill switch, no-training/minimum-retention configuration,
  project-owned adapter provenance and human reconciliation path are fixed.
- Never use this path in production or as precedent for general first-
  authority administration.

# Privileged MFA runbook — superseded after password cutover

> AUTH-PASSWORD-01 supersedes this workflow when the global password-only
> cutover marker is written. It remains historical migration evidence only.
> Current password establishment, confirmation, recovery and cutover procedures
> are in [the password authentication runbook](ops/PASSWORD_AUTHENTICATION_RUNBOOK.md).
> Password confirmation is one-factor freshness and must never be described as
> MFA. Before cutover, `legacy_otp`/`credential_migration` behavior continues to
> fail closed as configured.

**Status:** MFA-01 repository implementation complete; production review open
**Authority:** DR-0013 Option A, APP-0038, D-0123 and ADR-0002
**Applies to:** privileged web sessions and operator/security mutations

## Security boundary

Phase 1 privileged MFA is available only through a Sanctum cookie-backed web
session. Every operator endpoint is web-only. Mutable operator content,
publication and privacy commands additionally require a TOTP assertion no more
than 15 minutes old. Passport bearer requests are denied at the operator route
boundary; ordinary learner Android routes are unchanged.

Logout and user-initiated web-session revocation deliberately remain available
without fresh MFA as security escape paths. MFA does not grant an organization,
membership, role or permission: the existing tenant context and authorization
checks still run independently.

## Factor profile

- RFC 6238 TOTP with a 160-bit random secret, SHA-1, six digits and 30-second
  steps.
- Verification accepts the current step plus or minus one step. The factor's
  transactionally locked last-used step rejects replay, including a code reused
  by another session.
- The setup URI is returned only by the enrollment-start response. The secret
  is encrypted at rest and is absent from status, audit and later responses.
  Its ciphertext is cleared when a pending factor is abandoned or an active
  factor is replaced/revoked.
- Confirmation issues exactly ten recovery codes. Each contains 128 random
  bits, is returned once and is retained only as a factor-domain-separated
  keyed digest.

## Operator workflow

1. Sign in through the ordinary web OTP flow using an account whose current
   session is privileged.
2. In the operator console, choose **Set up authenticator**. Add the displayed
   `otpauth` URI to an authenticator application within five minutes.
3. Enter the current six-digit code. Enrollment remains pending and privileged
   mutations remain blocked until this confirmation succeeds.
4. Store all ten recovery codes outside the browser, then acknowledge the
   one-time display. The UI keeps the URI and raw recovery codes only in React
   component memory; it does not use localStorage or IndexedDB.
5. Before a mutable operator action, choose **Verify for a privileged action**
   and enter a current authenticator code. The resulting assertion is valid for
   15 minutes on that exact web authentication session.

The status endpoint may report `none`, `pending`, `active` or
`support_required`. An expired pending enrollment can be superseded by starting
a new enrollment. The scheduler runs `auth:mfa-expire-pending` every minute to
mark expired pending factors abandoned, clear their encrypted secrets and
supersede their confirmation challenges in bounded batches.

## Recovery and factor changes

A recovery code is single-use and grants only a 15-minute
`factor_replacement` assertion. It cannot authorize content, publication,
privacy, recovery-code regeneration or factor revocation. Use that assertion to
start a replacement enrollment and confirm the new factor with its own TOTP.

These operations invalidate existing recovery material and revoke every web
session and Android token family after the authoritative transaction commits:

- confirmed factor replacement;
- recovery-code regeneration; and
- factor revocation.

New recovery codes are shown once before the client transitions to its
signed-out state. After revocation, SMS/primary OTP cannot enroll a replacement; the
account fails closed with `support_required`. A reviewed support-assisted reset
workflow is not implemented in this checkpoint. Losing both the active factor
and every recovery code therefore requires external reviewed support rather
than an SMS bypass.

## HTTP contract

| Method and path | Purpose | Additional gate |
|---|---|---|
| `GET /api/v1/auth/mfa` | Current user's factor/assertion status | Active web session; non-privileged sessions cannot enroll |
| `POST /api/v1/auth/mfa/enrollments` | Begin or replace pending enrollment | First enrollment, or fresh TOTP/recovery replacement assertion |
| `POST /api/v1/auth/mfa/enrollments/{factorId}/confirm` | Confirm pending TOTP and issue recovery codes | Bound five-minute enrollment challenge |
| `POST /api/v1/auth/mfa/challenges` | Issue an assertion challenge | Active factor and privileged web session |
| `POST /api/v1/auth/mfa/challenges/{challengeId}/verify` | Consume TOTP or recovery code | Bound five-minute challenge; five attempts maximum |
| `POST /api/v1/auth/mfa/recovery-codes/regenerate` | Replace all recovery codes | Fresh TOTP privileged-action assertion |
| `DELETE /api/v1/auth/mfa/factor` | Revoke the active factor | Fresh TOTP privileged-action assertion |

All responses carrying factor state or one-time material use `Cache-Control:
private, no-store`. Mutations use cookie credentials plus CSRF protection and
are rate-limited by user, authentication session and IP; verification also has
a per-challenge limit.

Expected safe failures are `401` for an unusable session, `403` for a missing
fresh TOTP or a disallowed transport, `409` when enrollment/challenge state
cannot proceed, `422` for a generic invalid/expired assertion and `429` for a
rate limit. Repeated TOTP failures should first prompt confirmation that the
server and authenticator device clocks are synchronized.

## Audit and sensitive-data handling

Append-only platform audit events record only lifecycle categories and opaque
record identifiers: enrollment start/confirmation, challenge issue,
verification success/failure category, recovery use/regeneration, replacement
and revocation. They contain no TOTP secret, entered code, recovery code, raw
request body or personal learner content.

`APP_KEY` protects both encrypted factor secrets and keyed recovery-code
digests. Key rotation needs a reviewed credential migration/revocation plan;
the current recovery digest lookup does not try historical keys. Never copy a
production factor table, setup URI or recovery code into tickets, logs or test
fixtures.

## Verification

The primary automated coverage is in
`PrivilegedMfaLifecycleTest`, `PrivilegedMfaPolicyTest`,
`WebSessionAuthenticationAdapter.test.ts` and
`PrivilegedMfaPanel.test.tsx`. PostgreSQL coverage is required because the
one-active-factor, assertion-shape and actor-binding constraints are database
enforced there. Current checkpoint evidence is recorded in
[QUALITY_REVIEW.md](QUALITY_REVIEW.md).

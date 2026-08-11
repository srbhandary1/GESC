# DR-0013 — Privileged MFA factor, platform and recovery request

**Status:** Resolved — Option A accepted under APP-0038 / D-0123; MFA-01 implementation may proceed as a separate bounded task
**Needed by:** MFA-01
**Authority:** Master plan §§11.2 and 18.2; ADR-0002; D-0006
**Opened:** 8 August 2026
**Resolved:** 8 August 2026

## Problem

The repository has a fail-closed `PrivilegedMfaVerifier`, a 15-minute
freshness check on web authentication sessions and a middleware alias, but no
factor enrollment, challenge, recovery or revocation lifecycle. The accepted
authority requires a factor independent of the primary SMS OTP and prohibits
SMS-only recovery for privileged accounts. It does not select the factor,
platform boundary or reset semantics.

Choosing silently would change security guarantees and create authoritative
credential schema. In particular, current operator routes accept either a web
session or an Android bearer, while the only existing MFA freshness record is
attached to a web `authentication_session`.

## Decision profiles

| Option | Factor and platform | Recovery | Trade-off |
|---|---|---|---|
| **A — TOTP, web-only privileged operations (recommended)** | RFC 6238 authenticator-app TOTP for privileged web sessions; deny Android bearer access to operator/privileged mutations | Ten single-use ≥128-bit recovery codes, shown once and stored only as keyed digests; no SMS fallback | Complete Phase 1 vertical slice with broad authenticator compatibility and no new mobile privileged credential surface; TOTP is not phishing resistant |
| B — WebAuthn/passkey, web-only privileged operations | WebAuthn platform or roaming authenticator; deny Android bearer access to operator/privileged mutations | Single-use recovery codes plus reviewed credential replacement | Stronger phishing resistance, but requires attestation/origin/RP-ID policy, browser/device compatibility work and a reviewed WebAuthn dependency |
| C — Cross-platform privileged MFA | A reviewed factor lifecycle for both Sanctum sessions and Passport token families | Cross-platform recovery/reset and device replacement | Largest scope: mobile freshness binding, Android UX/secure-state changes and physical device evidence; not a bounded Phase 1 operator-console slice |

## Recommended Option A policy

Approval of Option A means all of the following:

1. TOTP uses a random secret with at least 160 bits of entropy, six digits,
   30-second steps and at most one step of clock skew. A transactionally stored
   last-used time step prevents replay.
2. Enrollment requires a current privileged web session. The secret is shown
   once as an `otpauth` URI, remains pending until one valid code confirms it,
   and is encrypted at rest. Privileged actions remain blocked before
   confirmation.
3. Verification challenges expire after five minutes, permit at most five
   failed attempts, are rate-limited by user/session/IP and set
   `mfa_verified_at` only in the same locked transaction that consumes the
   challenge/code.
4. Ten recovery codes are generated at confirmation, contain at least 128 bits
   of entropy each, are shown once, domain-separated/keyed-digested at rest and
   consumed once. Raw secrets/codes never enter logs, audit payloads or API
   responses after initial issuance.
5. A recovery code may establish one fresh assertion for factor replacement.
   Regeneration, factor replacement or factor revocation consumes existing
   recovery material and globally revokes web sessions and Android token
   families. SMS/primary OTP alone can never reset privileged MFA.
6. Fresh MFA is required for mutable authoritative operator actions: graph and
   content review decisions, authority appointment/revocation, rights
   confirmation, publication/scheduling/rollback/emergency suspension, privacy
   export/erasure commands, and MFA factor/recovery changes. Read-only learner
   paths, logout and user-initiated session revocation remain available without
   a fresh assertion so users retain a security escape path.
7. Phase 1 privileged/operator endpoints are web-session-only. Passport bearer
   requests to those routes are denied rather than manufacturing mobile MFA
   freshness. A later cross-platform privileged console requires a separate
   decision and device evidence.
8. Enrollment, successful/failed verification categories, recovery use,
   regeneration, replacement and revocation produce content-free append-only
   audit evidence. Enumeration-safe responses disclose no factor enrollment
   for another user.

## Schema and API consequence after approval

The bounded implementation will require user-owned MFA factor, challenge and
recovery-code records; row locks and uniqueness constraints for one active
TOTP factor and one-time code/time-step consumption; OpenAPI endpoints for
status, enrollment, confirmation, challenge verification, recovery-code
regeneration and factor revocation; generated TypeScript contracts; and web
authentication-adapter/UI support.

No schema or endpoint should be implemented until this decision is resolved.

## Resolution requested

Reply with one of:

```text
Approve DR-0013 Option A as written.
```

```text
Approve DR-0013 Option B; WebAuthn dependency and RP/attestation policy require review before implementation.
```

```text
Hold MFA-01 for a cross-platform Option C design.
```

Any change to recovery, protected-action coverage or the web-only boundary
should be stated explicitly because it materially changes the implementation.

## Resolution

Option A was accepted as written on 8 August 2026 under
[APP-0038](APPROVALS.md#app-0038) and [D-0123](DECISIONS.md). The exact
approved proposal, before this resolution note was added, has SHA-256
`78697cffea182a9aafde9eea84742883ffb4e71f616a0789b5f0f9021601013f`.

This approval resolves the credential-security decision gate. It does not
claim that MFA-01 schema, API, client behavior or tests have been implemented
or accepted, and it does not authorize production. Independent security and
privacy review remains a pre-production requirement under ADR-0001.

## Subsequent implementation status

MFA-01 was subsequently implemented as the bounded repository vertical slice
described in [PRIVILEGED_MFA_RUNBOOK.md](PRIVILEGED_MFA_RUNBOOK.md) and
[QUALITY_REVIEW.md](QUALITY_REVIEW.md). That implementation does not amend the
approved Option A text, does not constitute Accountable Product Owner
acceptance and does not authorize production.

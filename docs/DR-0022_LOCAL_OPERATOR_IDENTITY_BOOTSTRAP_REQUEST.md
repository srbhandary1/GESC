# DR-0022 — Local operator identity bootstrap for governed ingestion

**Status:** Resolved — Option A accepted under APP-0049 / D-0146 / ADR-0017

**Needed by:** LPL-01 acceptance of the four APP-0047 `lesson_audio` objects

**Prepared:** 10 August 2026

**Resolved:** 10 August 2026

## 1. Decision needed

Choose whether and how the existing identity-free synthetic Direct
organization administrator may receive a sign-in identity in this local-only
non-production environment so the four authorized assets can pass the normal
web-session, selected-membership and fresh-TOTP Media Library path.

This is an authentication and authoritative-tenancy decision. Codex may not
invent a bootstrap identity, bypass MFA, mutate the role graph directly or
represent a CLI/importer as human quarantine acceptance without an exact human
decision.

## 2. Discovery and stop condition

LPL-01 has completed every safe prerequisite before this decision:

- DR-0021 Option A is accepted and its `lesson_audio` Media Library extension
  passes repository verification, PostgreSQL, Chromium E2E and build gates;
- the pinned official FFmpeg 8.1.2 source signature verified against fingerprint
  `FCF986EA15E6E293A5644F10B4322F04D67658D8`;
- official ClamAV 1.5.4 image digest
  `sha256:a3c78b2f808ea797f842c6bca1c5f0a54a3d1182ab2efac1884510f667e44f56`
  is healthy with a current signature database;
- all four reacquired upstream objects match APP-0047's exact SHA-256 and byte
  count, pass the project ffprobe adapter as `audio/mpeg`, and pass the project
  ClamAV adapter as clean; and
- the backed-up local PostgreSQL database has applied migrations 70–160 and
  the idempotent foundation seed.

No current Media Library upload, scan, asset or acceptance row was created.
The database has one active Direct organization-admin membership with the
reviewed administrator role, but that synthetic user's global identity has no
`user_identities` row. The ordinary phone-OTP path cannot claim that existing
user: an unknown verified phone provisions a different Direct learner. There
is no accepted recovery or initial-admin binding workflow that can safely
bridge the existing row.

ADMIN-GOV-01 requires every Media Library mutation to use a first-party web
session, active selected membership, current permission and fresh TOTP. A
commissioner, direct SQL role grant, test authentication or test media fake
would violate that boundary and cannot produce LPL-01 evidence.

## 3. Options

| Option | Decision | Consequence |
|---|---|---|
| **A — One-shot local identity binding (recommended)** | Add a disabled-by-default Artisan bootstrap command that can bind one protected-input phone identity only to the exact existing identity-free active Direct administrator, then require ordinary OTP sign-in, selected membership and authenticator-app TOTP for every media command | Preserves the real operator/media path with a narrowly disclosed local bootstrap exception; adds bounded security-sensitive code and tests |
| **B — Defer to a full initial-admin/recovery product flow** | Design invitation/recovery and identity reassignment as a new product checkpoint before ingestion | Strongest reusable lifecycle, but materially expands LPL-01 into global identity/recovery scope and delays the lesson work |
| **C — Use a commissioner or direct database mutation** | Attribute ingestion to the synthetic admin without a web session/TOTP, or attach authority/identity with ad hoc SQL | Smallest effort but violates accepted MFA, tenancy, audit and human-acceptance controls; not recommended |

## 4. Recommended Option A contract

If accepted exactly as written, implement one bounded command named
`auth:bootstrap-local-operator-identity` with all of these controls:

1. It runs only when `APP_ENV=local`, `AUTH_OTP_DRIVER=local` and the separate
   `LOCAL_OPERATOR_IDENTITY_BOOTSTRAP_ENABLED=true` kill switch are all true.
   The example and every normal environment default remain false/absent.
2. It accepts the raw normalized E.164 value only from an owner-readable
   temporary file, never a command argument, committed file, log, audit field,
   exception, shell output or documentation artifact.
3. The caller supplies the expected existing user ULID and membership ULID.
   Under one transaction and row locks, the command requires exactly that:
   - the organization has immutable system key `global-english-direct`;
   - user, membership and organization are active;
   - the membership is `organization_admin` and currently selects the seeded
     immutable `organization_admin` role;
   - the user has no identity row of any type;
   - the normalized phone digest is not already selected by another identity;
     and
   - the exact APP-0049 approval reference is supplied.
4. It creates only one normal verified `phone` identity row through a
   project-owned service using the existing dedicated identity digest key.
   It creates or changes no user, organization, membership, role, permission,
   appointment, media, review, rights or publication record.
5. It emits only content-free success evidence: user/membership/identity ULIDs,
   environment, approval reference and time. It never returns the raw phone,
   digest, OTP, TOTP secret or recovery codes.
6. It is one-shot and fail-closed: any existing identity, mismatch, duplicate,
   non-local environment, non-local OTP driver or disabled kill switch aborts
   without a write. PostgreSQL concurrency tests must prove one winner.
7. After binding, the kill switch is removed from the process. The operator
   must still use the ordinary local OTP flow, select the exact membership,
   enroll TOTP and make each upload/finalize/accept command through the current
   HTTP API. Test adapters and direct domain invocation remain prohibited.
8. The resulting local database and accepted media remain non-production and
   non-exportable. This exception does not define production initial-admin,
   phone-change, reassignment or recovery behavior and cannot be used as
   production identity evidence.

The implementation requires focused command/service tests, a PostgreSQL race
test, safe-log assertions and a short local operator bootstrap runbook. It
requires no schema, public API, OpenAPI, generated-client, Android or learner
change. The accepted decision will be recorded as APP-0049 / D-0146 /
ADR-0017 and will not amend the master plan.

## 5. Security, privacy and tenancy impact

Option A makes an already-authorized synthetic local membership reachable; it
does not grant authority to another membership. Its risk is that identity
binding normally requires a verified onboarding/recovery ceremony. The strict
local/driver/kill-switch/expected-ID/identity-free checks, protected input,
one-shot behavior and subsequent real OTP+TOTP flow bound that exception.

Raw phone numbers, OTPs, TOTP secrets and recovery codes remain excluded from
Git, command history, process arguments, logs, audit payloads and evidence.
No cross-tenant choice is accepted from input. Independent specialist review
and a real initial-admin/recovery design remain mandatory before production.

## 6. Exact approval phrase

To accept the recommended boundary, reply exactly:

```text
Approve DR-0022 Option A as written.
```

Any other response will be treated as revision or rejection, not approval.

## 7. Resolution

Option A was accepted exactly as written on 10 August 2026 under APP-0049,
D-0146 and ADR-0017. The exact approved proposal, before its status, resolved
date and this resolution section were added, has SHA-256
`0ecc159512ec28e507995eb01fe5f116221e78c8710a03b9de3269e67f3f8cb8`.

LPL-01 may implement and execute only the one-shot local identity binding
defined above. Every media mutation must still use ordinary OTP, selected
membership and fresh TOTP through the current first-party HTTP API. This does
not authorize a production initial-admin/recovery flow, SQL/test-auth bypass,
global identity administration, LPL-02 or production/public release.

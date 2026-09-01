---
status: accepted
date: 2026-08-11
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
  - S R Bhandary as consolidated Security, Privacy and Tenancy Reviewer
consulted:
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: true
approval_evidence:
  - APP-0055
approval_reference: Exact human instruction in the active Codex conversation
---

# ADR-0021: Username/password-only authentication for every role

## Status

Accepted on 11 August 2026 under
[APP-0055](../APPROVALS.md#app-0055) / D-0163. This ADR resolves
[DR-0026](../DR-0026_PASSWORD_ONLY_AUTHENTICATION_REQUEST.md) by accepting
Option B exactly as written. The approved pre-resolution request has SHA-256
`847486461ffbf92610427744539bea5a3fd607b2988d8aea8a9ff54fbcd0576d`.
Amended on 1 September 2026 by
[ADR-0034](0034-federated-google-facebook-sign-in.md): Google and Facebook are
extra sign-in methods for already-enrolled users. Username/password remains
the enrollment credential and the only privileged freshness factor.

Acceptance authorizes this ADR, its same-change master-plan amendment and the
bounded AUTH-PASSWORD-01 specification. It does not directly authorize runtime
implementation. Existing OTP and TOTP behavior remains fail closed until a
separately authorized, tested cutover completes.

## Context

The product owner requires a simpler, uniform sign-in experience: username and
password for learners, staff and administrators, with no authenticator app or
other second factor. The existing implementation instead uses phone OTP for
web and Android identity verification and TOTP for sensitive privileged
mutations.

This changes a security foundation rather than only a form. OTP, TOTP,
sessions, Android authorization, identity migration, recovery, PostgreSQL
constraints and protected-command evidence are linked throughout the current
system. The replacement must preserve tenant authorization, authoritative
transactions, credential transport, revocation and audit while acknowledging
that one stolen password can now authorize privileged actions.

## Decision

Implement the complete normative contract in DR-0026 Option B.

### Identity and credential

- Every user has one platform-global, case-insensitive username in canonical
  lower-case ASCII form matching `^[a-z][a-z0-9._-]{3,63}$`. It remains
  independent of memberships, mobile number, display name and immutable user
  ID.
- Every role uses that username and one password as its enrollment credential
  and privileged-freshness factor. Invitations authorize enrollment or staff
  authority, not continuing authentication, and no shared or retrievable
  default password exists. After enrollment, Google and Facebook may sign
  in the same user when that IdP subject is linked (ADR-0034). They never
  create accounts or satisfy password confirmation.
- Passwords contain 15–128 Unicode code points, accept ordinary Unicode and
  spaces, use NFC consistently, are never silently truncated and have no
  composition or periodic-rotation rule.
- New passwords are checked in full against a versioned common/contextual/
  compromised-password blocklist and stored only as individually salted,
  versioned Argon2id hashes behind a project-owned credential service.
- Raw passwords never enter logs, audit, traces, queues, analytics, browser
  storage, ordinary Capacitor preferences, support tools or administrator
  views.

### Transport, sessions and privileged freshness

- Web/PWA retains Secure, HttpOnly, same-origin Sanctum cookie sessions and
  CSRF protection. Android first issue is a first-party in-app
  username/password form that issues the Passport token family (ADR-0033).
  Linked Google or Facebook sign-in issues that same web session or Passport
  family (ADR-0034). Refresh remains Passport `refresh_token`. Access tokens
  stay short-lived, refresh families rotate, and credentials stay only in
  reviewed native secure storage. `/oauth/token` still rejects
  `grant_type=password`.
- Login responses and materially observable behavior are enumeration-safe and
  throttled across normalized account, source/network and global abuse keys.
- The existing session lifetimes, listing/revocation, Android installation
  binding and refresh-reuse response remain authoritative.
- Every command currently protected by fresh TOTP instead requires successful
  re-entry of the same current password no more than five minutes earlier,
  bound server-side to the exact web authentication session.
- Password confirmation is one-factor freshness evidence, not MFA. It never
  grants or freezes a tenant, membership, role, permission, appointment or
  other authority; all existing command-time checks still apply.

### Enrollment, migration and recovery

- Direct learners choose a username/password during setup. Institutional
  invitations and staff appointments lead to one-time password establishment.
- Existing users complete a bounded claim/assignment ceremony. Current OTP or
  TOTP may authenticate only this pre-cutover transition and has no
  post-cutover sign-in role.
- Ordinary recovery uses a separately verified contact and a random,
  single-use, short-lived reset token/code stored only as a keyed digest.
- Privileged recovery cannot rely on mobile/SMS possession alone. It requires
  a delayed, audited support case approved by two authorized operators with
  privacy-safe evidence and notification through all available verified
  channels.
- Password change, recovery, username change and security reset revoke all web
  sessions and Android token families and clear password-confirmation
  freshness.

## Alternatives considered

### Username/password plus privileged MFA

Recommended in DR-0026 because it simplifies routine sign-in without exposing
high-impact commands to a single stolen password. Rejected by the accountable
product owner in favor of no second factor for any role.

### Retain OTP and TOTP

Rejected because it preserves the authenticator-app burden and does not provide
the requested uniform username/password experience.

### Direct credential mutation or temporary bypass

Rejected because it would leave undocumented mixed authentication policies,
break evidence constraints and create account-takeover and rollback risks.

## Consequences

### Positive

- One consistent sign-in model is easier to teach and support.
- Routine login no longer depends on mobile delivery or an authenticator app.
- Sanctum and Passport transport, tenant authorization and session revocation
  remain stable behind the project authentication abstraction.

### Negative and trade-offs

- A phished, reused or stuffed password can authorize privileged actions after
  re-entry; there is no independent-factor defense.
- Password setup, blocklist maintenance, recovery, migration, support and
  compromise response become larger operational responsibilities.
- Every current OTP/TOTP client, endpoint, test, constraint and runbook must be
  migrated coherently, increasing implementation and cutover risk.
- The product's explicit privileged-MFA security baseline is removed. This
  exception must remain visible to independent reviewers and cannot be
  described as ASVS or MFA equivalence.

## Security, privacy and tenancy impact

Credential stuffing, spraying, phishing and offline guessing become primary
threats. Argon2id, long-password policy, compromised-password blocking,
throttling, generic responses, content-free monitoring, fresh password
confirmation and global revocation reduce but do not eliminate the risk.

Username and credential records are platform-global. Organization membership,
role and permission authority remains organization-scoped and is checked after
authentication. Same-tenant and cross-tenant tests must demonstrate that
password confirmation cannot create or preserve authority.

Passwords and recovery material are secrets. Mobile numbers retained as
recovery contacts require explicit purpose, retention, export and erasure
treatment rather than silent reuse of the old login identity purpose.

## Authoritative records and consistency impact

Credential establishment/change, username assignment/change, recovery,
cutover, revocation and password confirmation are authoritative operations.
They require database transactions, deterministic locks, uniqueness,
idempotency and content-free audit/outbox evidence.

The implementation is additive before cutover. Existing OTP/TOTP tables and
evidence are revoked/deprecated only after every eligible account has a usable
credential or an explicit exception state. Historical security evidence is
retained under its existing policy; raw factor secrets and active recovery
material are destroyed through a separately verified retention migration.

## API, client and operational impact

OpenAPI and generated TypeScript contracts must add password establishment,
sign-in, confirmation, change and recovery and remove OTP/TOTP routes only at
the verified cutover. Web and Android system-browser clients must use the same
project authentication abstraction. Login and recovery copy must be accessible
and localized, with approved-English fallback until independent language
review.

Operators need audited recovery procedures and compromise-response runbooks.
Rollout must support a reversible pre-cutover phase and one explicit cutover;
it must not operate indefinitely with undocumented dual sign-in.

## Master-plan amendment

This ADR amends master-plan version 3.2 in the same accepted documentation
change and advances it to version 3.3. It changes:

- §1.1 and §32 authentication defaults;
- §7.3 sign-in experience;
- §11 and §11.2 authentication/privileged-freshness architecture;
- §17 connectivity wording;
- §18.2 privileged security controls; and
- Step 1 plus PR-001/PR-003 identity-foundation descriptions.

The new baseline is username/password-only for every role, with fresh
same-password confirmation for sensitive privileged actions. Sanctum,
Passport token-family transport, secure storage, session/token lifecycles
and tenant authorization remain. ADR-0033 later replaced Android first
issue (system-browser PKCE) with an in-app password form.

## Approval evidence

[APP-0055](../APPROVALS.md#app-0055) records the exact human statement,
approved request/hash, roles, risk acceptance, master-plan impact and Codex's
recorder-only role. One human exercised consolidated development-review roles;
independent security, privacy, tenancy, accessibility and language/content
review remains mandatory before production.

## Verification

ADR acceptance requires:

- exact APP-0055/DR-0026 hash and link consistency;
- master-plan authentication references reconciled to this decision;
- a bounded AUTH-PASSWORD-01 specification with migration, rollback, threat and
  test contracts;
- changed-document structure/local-link validation and `git diff --check`; and
- repository `make verify`.

Runtime acceptance later requires the full AUTH-PASSWORD-01 verification and
critical E2E gates; this ADR does not claim them.

## Follow-up

1. Review and explicitly authorize AUTH-PASSWORD-01 implementation.
2. Implement only that bounded checkpoint with OpenAPI/generated clients,
   database migrations, web/Android journeys and security tests.
3. Complete verified cutover and update the obsolete MFA runbook without
   erasing historical evidence.
4. Resume LPL-01 human reconciliation using fresh password confirmation.

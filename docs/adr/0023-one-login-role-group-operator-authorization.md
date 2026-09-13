---
status: accepted
date: 2026-08-14
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
  - S R Bhandary as consolidated Security, Privacy and Tenancy Reviewer
consulted:
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: true
approval_evidence:
  - APP-0060
approval_reference: Exact human instruction in the active Codex conversation
---

# ADR-0023: One-login role-group operator authorization

## Status

Accepted on 14 August 2026 under
[APP-0060](../APPROVALS.md#app-0060) / D-0193. This ADR resolves and broadens
[DR-0031](../DR-0031_LPL01_RECONCILIATION_AUTHENTICATION_SIMPLIFICATION_REQUEST.md).
The pre-resolution request has SHA-256
`da8f04370560bf7c8309a33c04b646e6ca0ef4951fcf3376ba34bdff893b739b`.

## Context

ADR-0021 replaced OTP/TOTP with one platform-global username/password, but it
also replaced every previously protected command with a five-minute re-entry
of that same password. A successful sign-in deliberately created no freshness
proof. Routine administrator work therefore required a separate Account
Security visit and sometimes repeated password entry during one continuous
task.

The completed four-part LPL-01 transcript review made the cost concrete: its
19-minute-32.513-second source exceeded the freshness window, while candidate
read and reconciliation both required the same additional ceremony. This was
one-factor repetition, not MFA, and did not create tenant, role, permission,
hash, version or audit authority.

The product owner requires a simpler model matching the visible legacy-site
expectation: sign in once with username/password, then manage the project
through role-based groups.

## Decision

- One successful username/password sign-in establishes the Web session used
  for routine learner, trainer, reviewer and administrator work.
- Organization selection, role groups, permission grants, appointments and
  resource scope are resolved after authentication and rechecked by every
  command. Signing in as an administrator is not a tenant or permission
  bypass.
- Routine operator mutations no longer require `auth.password-confirmed`.
  This includes learner/enrollment work, branch/cohort management, trainer
  assignments and appointments, content authoring/review/publication, media
  upload/review/rights/metadata/reconciliation, privacy requests and session
  revocation.
- Current-password confirmation remains limited to:
  1. changing the signed-in user's password or username;
  2. issuing migration setup or replacing a verified recovery contact through
     the exact `platform.account_recovery.manage` capability; and
  3. changing administrator authority through staff invitations, role-group
     definitions, staff role assignments/revocations or staff membership
     activation/deactivation.
- The completed standalone LPL-01 reconciliation page is removed. Future
  reconciliation belongs in the ordinary authenticated Media/Content workflow
  and uses the same selected organization and permission model.
- Account Security becomes a small, optional Account settings surface for
  credential and signed-in-device management. It is not a prerequisite page
  for routine commands.
- Password hashing, compromised-password checks, HTTPS, Secure/HttpOnly/
  SameSite cookies, CSRF, throttling, credential/session revocation, tenant
  isolation, permission enforcement, idempotency, optimistic concurrency and
  content-free audit remain mandatory and normally invisible.
- Provider candidates may remain encrypted while retained. Encryption is an
  implementation protection, not a user step; the completed campaign's four
  candidates are already deleted under their retention receipts.

This ADR amends ADR-0020 by removing fresh-password requirements from governed
candidate read and reconciliation. It amends ADR-0021 and ADR-0022 by replacing
their blanket sensitive-command freshness classification with the exact
three-part confirmation boundary above.

## Consequences

Routine administrators can sign in once, select an organization and complete
their authorized project work without repeated authentication. Role groups
remain the understandable source of access, and the separate LPL commissioning
aid no longer appears as product UI.

The accepted negative consequence is that theft of an active authorized
administrator session can permit more high-impact routine commands until
expiry or revocation. Command-time permissions, tenant scoping, immutable or
versioned records, idempotency, audit, session expiry/revocation and browser
transport controls reduce but do not eliminate that risk. This remains a
deliberately accepted one-factor product model and is not represented as
enterprise MFA.

## Security, privacy and tenancy impact

No raw password, recovery token, audio, transcript or learner data may enter
logs, audit, analytics or browser persistence. Removing repeated password
entry does not change organization ownership or cross-tenant denial. Privacy
export/erasure requests still require their explicit role permissions and
governed workflows. Administrator-authority and recovery-contact changes keep
fresh confirmation because they can expand durable access or recover an
account.

## Authoritative records and consistency impact

This decision changes route authentication classification and UI only. It
does not change domain transactions, idempotency, expected-version checks,
immutability, review separation, audit/outbox records or foundation schema.

## Master-plan amendment

This ADR amends master-plan version 3.4; the resulting canonical revision is
version 3.5. Sections 2.2, 11, 11.2, 18.2, 26 and 32 now define one-login
routine operator work with narrowly scoped password confirmation.

## Approval evidence

[APP-0060](../APPROVALS.md#app-0060) records the exact human statement, its
immediately preceding one-login proposal, accepted risk, scope and Codex's
recorder-only role. Independent security, privacy and tenancy review remains
mandatory before production.

## Verification

Route-policy and integration tests must prove routine commands work without a
freshness timestamp while administrator-authority and recovery-contact routes
remain protected. Browser tests must prove the routine workflow has no Account
settings detour and the obsolete LPL page is unavailable. Run `make verify`,
`make e2e` and `make build`; no AI/provider evaluation is required.

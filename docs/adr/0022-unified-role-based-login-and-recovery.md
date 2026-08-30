---
status: accepted
date: 2026-08-13
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
  - S R Bhandary as consolidated Security, Privacy and Tenancy Reviewer
consulted:
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: true
approval_evidence:
  - APP-0059
approval_reference: Exact human instruction in the active Codex conversation
---

# ADR-0022: Unified role-based login and verified-contact recovery

## Status

Accepted on 13 August 2026 under
[APP-0059](../APPROVALS.md#app-0059) / D-0186. This ADR resolves
[DR-0030](../DR-0030_UNIFIED_ROLE_BASED_LOGIN_AND_RECOVERY_REQUEST.md) by
accepting Option A exactly as written. The approved pre-resolution request has
SHA-256
`3a671fbf56fa482d1d950668ed0ef76744dbcfe90931b42e1d753667a8a51534`.

## Context

ADR-0021 already gives every role one platform-global username/password
credential. AUTH-PASSWORD-01 nevertheless retained a distinct privileged
recovery system requiring two configured actors, two fresh confirmations and
a 24-hour delay. That distinction blocked credential establishment for the
only local organization administrator and contradicted the product owner's
requirement for one simple production authentication model.

Learner data, tenant authority, privacy requests, content rights and published
records still need protection. Simplicity changes the user journey, not the
secure session, hashing, revocation, tenant authorization or audit boundary.

## Decision

- Every role uses the same username/password sign-in and verified-contact
  recovery path.
- Role, organization, level, tier, cohort, program and curriculum state are
  evaluated after authentication and never select a credential form.
- Recovery uses one 256-bit, 15-minute, single-use token delivered only to an
  already verified contact, with enumeration-safe responses, rate limits and
  global Web/Android revocation.
- A reviewed credential-unready migration exclusion may receive the same
  immediate one-time setup token. The target chooses the username/password;
  no operator sees or sets either.
- The two-actor/24-hour privileged recovery API and commissioning workflow are
  retired. Existing case/approval rows remain restricted historical evidence.
- One freshly confirmed platform administrator with the exact
  `platform.account_recovery.manage` capability may replace a lost recovery
  contact after recording a bounded support reason and external verification
  reference. Organization roles never grant that capability. Former contact
  notification is attempted when technically possible, every credential is
  revoked and content-free evidence is mandatory.
- Sensitive Web commands retain five-minute confirmation of the same password
  plus independent current tenant, role, permission and appointment checks.
  This is one-factor freshness, not MFA.
- Production activation remains fail-closed until a reviewed recovery
  delivery/notification adapter and its privacy, secret-custody, replay,
  expiry, throttling and redaction evidence pass.

The complete implementation contract is
[AUTH-SIMPLE-01](../AUTH-SIMPLE-01_SPEC.md).

## Consequences

The product gains one teachable login/setup/recovery journey and removes a
special recovery ceremony that cannot be explained as a different learner or
staff login. Migration accounts can establish their own credential without
restoring OTP/TOTP or changing tenant authority.

The accepted negative consequence is greater privileged account-takeover risk:
a stolen administrator password or verified recovery contact can be enough.
Argon2id, compromised-password blocking, rate limits, short single-use tokens,
same-password freshness, contact-change evidence/notification and global
revocation reduce but do not eliminate that risk.

## Security, privacy and tenancy impact

Raw passwords, contacts and reset/setup tokens remain secret. Contacts are
stored only as dedicated keyed digests and masked hints in the repository
model; provider delivery references require a separately reviewed adapter.
Recovery and contact changes never create, copy or preserve membership, role,
permission, appointment, enrollment or curriculum authority. Cross-tenant
tests must compare authority before and after every flow.

## Authoritative records and consistency impact

Challenge issuance/consumption, credential establishment/reset, contact
replacement, notification outcome and global revocation are authoritative
multi-write operations. They use database transactions, deterministic locks,
single-use tokens, uniqueness constraints and content-free append-only
evidence. Historical privileged recovery evidence is versioned/superseded,
never deleted or rewritten.

## Master-plan amendment

This ADR amends master-plan version 3.3; AUTH-SIMPLE-01 records the resulting
canonical master-plan revision as version 3.4. It
clarifies §§7.2, 8.2 and 11.2: one sign-in and verified-contact recovery applies
to every role; learner tier remains enrollment; and exceptional privileged
recovery is retired while secure transport, password confirmation and tenant
authorization remain.

## Approval evidence

[APP-0059](../APPROVALS.md#app-0059) records the exact human statement,
pre-resolution request hash, accepted risk, scope and Codex's recorder-only
role. Independent security, privacy, tenancy, accessibility and language
review remains mandatory before production.

## Verification

AUTH-SIMPLE-01 requires SQLite and PostgreSQL concurrency/security tests,
OpenAPI/generated-client drift checks, accessible browser journeys, Android
authentication-contract checks, production build and backup/restore evidence.
Production delivery requires a later explicit activation gate.

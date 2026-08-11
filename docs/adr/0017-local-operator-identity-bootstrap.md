---
status: accepted
date: 2026-08-10
deciders:
  - Codex task accountable product owner as Accountable Product Owner and Technical Decision Owner
  - Codex task accountable product owner as Security Reviewer, Privacy Reviewer and Tenancy Reviewer
consulted:
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: false
approval_evidence:
  - APP-0049
approval_reference: Exact human instruction in the active Codex conversation
---

# ADR-0017: Bound the local operator identity bootstrap

## Status

Accepted on 10 August 2026 under
[APP-0049](../APPROVALS.md#app-0049) / D-0146. This ADR resolves
[DR-0022](../DR-0022_LOCAL_OPERATOR_IDENTITY_BOOTSTRAP_REQUEST.md) by accepting
Option A exactly as written. The approved pre-resolution DR has SHA-256
`0ecc159512ec28e507995eb01fe5f116221e78c8710a03b9de3269e67f3f8cb8`.

## Context

The local non-production database has one active synthetic Global English
Direct organization administrator with the seeded administrator role but no
sign-in identity. Ordinary phone OTP cannot claim that user because an unknown
verified phone correctly provisions a separate Direct learner. Media Library
mutations require a first-party web session, active selected membership and
fresh TOTP, and no accepted initial-admin/recovery bridge exists.

Direct SQL, test authentication, commissioner attribution or direct domain
invocation would bypass the accepted authentication, tenancy, audit and human
quarantine-acceptance boundary and cannot produce LPL-01 evidence.

## Decision

Add one disabled-by-default command,
`auth:bootstrap-local-operator-identity`, with DR-0022's complete normative
contract:

- run only under `APP_ENV=local`, `AUTH_OTP_DRIVER=local` and an explicit
  `LOCAL_OPERATOR_IDENTITY_BOOTSTRAP_ENABLED=true` kill switch;
- read one normalized E.164 phone only from an owner-readable temporary file,
  never argv, logs, audit or committed configuration;
- require exact expected user and membership ULIDs plus approval reference
  `APP-0049`;
- under transaction and deterministic locks, require the immutable Direct
  organization, active user/membership, organization-admin membership type,
  current active seeded system administrator role, no user identity of any
  type and no duplicate phone digest;
- create only one normal verified phone identity through a project-owned
  service and emit only content-free identifiers/environment/reference/time;
  and
- fail closed on every mismatch or replay, remove the kill switch after use,
  then require ordinary OTP, selected membership and fresh TOTP through the
  existing HTTP API for every media action.

The command does not create or change users, organizations, memberships,
roles, permissions, appointments, media, reviews, rights or publications.

## Alternatives considered

### Full initial-admin and recovery workflow

Deferred because it is a reusable production identity product decision that
materially exceeds the bounded LPL-01 prerequisite.

### Commissioner, direct SQL or test authentication

Rejected because those paths bypass accepted identity, MFA, tenancy and human
acceptance controls and would produce invalid checkpoint evidence.

## Consequences

The existing authorized local membership becomes reachable without granting
authority to a new user or weakening downstream media commands. The trade-off
is a narrow local identity-binding exception where a production system would
require a verified initial-admin or recovery ceremony.

The exception is bounded by environment/driver/kill-switch checks, protected
input, exact IDs, identity-free state, immutable tenant/role checks,
identifier uniqueness, one-shot behavior, safe output and PostgreSQL race
tests. It cannot be reused as production identity evidence.

## Security, privacy and tenancy impact

Raw phone numbers, digests, OTPs, TOTP secrets and recovery codes stay out of
Git, process arguments, ordinary logs, audit payloads and durable evidence.
No organization is accepted from untrusted input: the membership must resolve
to the immutable Direct organization and seeded system administrator role.
Independent security/privacy/tenancy review and a proper initial-admin/recovery
design remain mandatory before production.

## Authoritative records and consistency impact

One existing user gains exactly one verified `phone` identity. The write is
transactional and serializes both expected rows and phone-digest uniqueness;
replay and concurrent attempts fail without further writes. No schema or
public API changes are required.

## Master-plan amendment

This ADR does not amend the master plan. It is a local-only implementation
exception that preserves the master plan's ordinary OTP, Sanctum, selected
membership, privileged MFA and tenant-derived authority rules for runtime
operations.

## Approval evidence

- Evidence ID: `APP-0049`
- Decision date: 2026-08-10, Asia/Kolkata
- Human approver identifier: `Codex task accountable product owner`
- Roles exercised: Accountable Product Owner; Technical Decision Owner;
  Security Reviewer; Privacy Reviewer; Tenancy Reviewer
- Exact human statement: `Approve DR-0022 Option A as written.`
- Approved DR SHA-256:
  `0ecc159512ec28e507995eb01fe5f116221e78c8710a03b9de3269e67f3f8cb8`
- Master-plan impact: none

Codex is the author and evidence recorder only, never the approver. One human
exercised consolidated development roles; this is not independent specialist
or production review.

## Follow-up

1. Implement and verify only the bounded command/service and local runbook.
2. Execute it once for the exact local synthetic administrator and remove the
   kill switch.
3. Use ordinary OTP, membership selection and fresh TOTP HTTP commands for
   the four APP-0047 objects.
4. Do not begin LPL-02 until LPL-01's complete review exit passes.

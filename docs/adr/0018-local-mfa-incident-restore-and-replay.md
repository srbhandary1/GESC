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
  - APP-0050
approval_reference: Exact human instruction in the active Codex conversation
---

# ADR-0018: Recover the local MFA incident by verified restore and replay

## Status

Accepted on 10 August 2026 under
[APP-0050](../APPROVALS.md#app-0050) / D-0148. This ADR resolves
[DR-0023](../DR-0023_LOCAL_MFA_INCIDENT_RECOVERY_REQUEST.md) by accepting
Option A exactly as written. The approved pre-resolution DR has SHA-256
`956d6d6e1513412ce662dbc70fb0f642f4f1e763626b158b76f37e70ad79b79e`.

## Context

The exact DR-0022 identity bootstrap and ordinary staff OTP correction pass,
but routine revocation of an ephemeral confirmed factor correctly invalidated
recovery material and left the only local privileged synthetic operator in
`support_required`. No reviewed support-assisted reset exists. One media upload
also correctly failed closed under unavailable inspection/scanner selectors;
it created no asset or rights record.

A validated pre-LPL PostgreSQL backup exists and predates every affected
identity, MFA and media write. Targeted credential deletion, test
authentication, direct SQL repair or a replacement administrator would bypass
accepted recovery, tenancy and audit boundaries.

## Decision

Recover only this local non-production environment by DR-0023's complete
verified restore-and-replay contract:

- snapshot and validate the locked incident state;
- prove every post-backup runtime delta is expected and stop on any unrelated
  business record;
- restore only the exact named local database from the pinned backup, rerun
  reviewed migrations and the idempotent foundation seed;
- repeat the exact APP-0049 identity binding once in restored state, then use
  ordinary OTP, selected membership and TOTP HTTP controls;
- permit one owner-only temporary TOTP/recovery handoff with no secret content
  in arguments, chat, logs, audit, Git or durable evidence;
- do not revoke the factor as routine cleanup; and
- run the four exact APP-0047 objects only through explicitly selected
  project-owned ffprobe and ClamAV adapters and the normal Media Library HTTP
  lifecycle.

The failed attempt remains preserved in the incident snapshot and repository
evidence. The recovery creates no permission, authority appointment, rights
revision, review, transcript approval, publication or release.

## Alternatives considered

### Support-assisted MFA reset lifecycle

Deferred because a reusable high-assurance reset authority, schema, API/CLI,
audit model and tests materially exceed LPL-01.

### Hold the account

Rejected for this local checkpoint because the exact validated baseline and a
bounded whole-database recovery path exist.

### Direct credential repair or replacement administrator

Rejected because either path bypasses accepted MFA and tenant authority.

## Consequences

The active local database loses its incident-era runtime rows but a validated
pre-restore snapshot and content-free documentation preserve that evidence.
APP-0049 is replayed only because the complete database returns to its exact
identity-free state. The ordinary authentication, TOTP, tenant permission,
quarantine and human acceptance boundaries remain mandatory.

This is a narrow non-production recovery, not a support-reset product design.
Loss of the protected handoff stops work rather than authorizing another reset.

## Security, privacy and tenancy impact

The restore target and baseline are exact; the delta audit must stop on any
unexplained learner or business state. Sensitive phone/MFA material has only
protected local transient handling. Organization context remains derived from
the immutable Direct membership, and no role or permission is created.

Independent security/privacy/tenancy review and a production support recovery
design remain mandatory before production.

## Master-plan amendment

This ADR does not amend the master plan. It is a local incident-recovery
exception that preserves the existing authentication and governed-media
architecture.

## Approval evidence

- Evidence ID: `APP-0050`
- Decision date: 2026-08-10, Asia/Kolkata
- Human approver identifier: `Codex task accountable product owner`
- Roles exercised: Accountable Product Owner; Technical Decision Owner;
  Security Reviewer; Privacy Reviewer; Tenancy Reviewer
- Exact human statement: `Approve DR-0023 Option A as written.`
- Approved DR SHA-256:
  `956d6d6e1513412ce662dbc70fb0f642f4f1e763626b158b76f37e70ad79b79e`
- Master-plan impact: none

Codex is the author and evidence recorder only, never the approver. One human
exercised consolidated development roles; this is not independent specialist
or production review.

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
  - APP-0051
approval_reference: Exact human instruction in the active Codex conversation
---

# ADR-0019: Bootstrap one local LPL pilot and program-scoped rights authority

## Status

Accepted on 10 August 2026 under
[APP-0051](../APPROVALS.md#app-0051) / D-0150. This ADR resolves
[DR-0024](../DR-0024_LPL01_PILOT_PROGRAM_AND_RIGHTS_AUTHORITY_REQUEST.md) by
accepting Option A exactly as written. The approved pre-resolution DR has
SHA-256
`2b52f1c3c09206a33de7b940e2184c473d3095b89388d30fc9830386f9266538`.

## Context

DR-0023 left four exact APP-0047 teaching MP3s as active, clean, private
`lesson_audio` assets in the Direct organization. The only local privileged
operator can manage authority appointments but lacks `curriculum.author` and
`media.rights.approve`; no accepted rights-confirmer appointment or
unambiguous canonical LPL owner program exists. Existing fixture programs and
system role packs cannot be elevated by convenience.

Ordinary role administration correctly prevents an actor from delegating a
permission they do not hold. Direct database mutation, seed grants, test
authentication, a second administrator or treating APP-0047 as an automatic
runtime rights row would bypass accepted authority and evidence controls.

## Decision

Implement DR-0024 Option A's complete normative contract:

- create exactly one standalone, private Direct-owned program with stable key
  `global-spoken-english-lpl-pilot` and internal name
  `Global Spoken English LPL Pilot`, without a curriculum version/head,
  publication, enrollment, learner pin, public route or entitlement;
- create one active organization-owned non-system role with key
  `lpl-pilot-commissioner` and exactly `curriculum.author` plus
  `media.rights.approve`, assigned only to the exact retained membership;
- use one disabled-default, local-only, APP-0051-bound command/service that
  validates the exact tenant, user, membership, APP-0047 references, four
  active clean asset hashes and absent or exact idempotent program/role/
  assignment/appointment state under deterministic locks and transactions;
- create one pending `rights_confirmer` appointment, scoped only to the new
  program, through `AuthorityAppointmentCommands`; APP-0004 remains the
  consolidated self-appointment authority and APP-0047 is retained as the
  owner/qualification basis;
- require ordinary first-party web authentication, selected membership and
  fresh TOTP for explicit appointee acceptance and every rights command;
- disable the persistent bootstrap switch immediately after one successful
  local execution; and
- confirm one initial rights revision per exact asset only through the current
  Media Library HTTP command, allowing derivatives and later gated web/Android
  learner delivery in India, disallowing sharing, retaining current accepted-
  media/deletion policy keys and using no expiry because none was supplied.

The rights rows authorize bounded processing only. They do not attach media,
approve a transcript, answer, blueprint, English/Kannada content or
publication, and they do not make media learner-deliverable in this
checkpoint. Provider transcription remains separately gated and this decision
makes no provider call.

## Alternatives considered

### General initial-authority onboarding

Deferred because production-shaped custody and first-authority administration
materially exceed the exact local LPL-01 prerequisite.

### Hold LPL-01

Not selected because exact owner authorization, clean assets and a bounded
least-privilege local bridge now exist.

### Reuse fixture programs or expand system role packs

Rejected because either would convert smoke history into product authority or
silently broaden every administrator.

## Consequences

The local account receives only the two explicit commissioning permissions in
one separately visible/revocable role. Rights exercise additionally requires
the exact accepted program appointment, clean asset hash and fresh TOTP. The
trade-off is a narrow local first-authority exception that cannot be reused as
production onboarding precedent.

The standalone program deliberately has no draft curriculum version. Later
content authoring must create its governed successor/version through the
accepted LPL checkpoint rather than treating this authority bootstrap as a
curriculum head.

## Security, privacy and tenancy impact

Execution is denied outside `local`, with a non-local OTP driver, without the
persistent switch, on any identifier/evidence/hash/state mismatch, or after
the switch is disabled. The command adds no identity, membership, membership
type, second administrator, platform capability or cross-tenant grant.

Audit/outbox evidence contains stable identifiers and policy references only;
it excludes media bytes, transcripts, learner data, credentials and MFA
material. The consolidated account is non-independent development review and
cannot satisfy later specialist or production staffing gates.

## Authoritative records and consistency impact

The program, role grants, membership-role relationship, authority appointment,
appointment events, rights revisions, idempotency receipts, audit and outbox
records are authoritative. Bootstrap writes use one transaction with
deterministic tenant/authority locks and exact-state replay checks. Appointment
creation and later acceptance reuse existing authority services; each rights
revision reuses the current immutable media-rights service through HTTP.

No schema migration is required. Published content and prior evidence remain
unchanged.

## API, client and operational impact

No new public API or generated client contract is introduced. The existing
same-tenant appointment-detail route must allow the exact organization-admin
appointee to read their pending appointment state fingerprint before invoking
the existing acceptance command; creation/list management remains unchanged.

The command emits only safe JSON identifiers and a replay flag. Operational
execution must remove the enable switch immediately and preserve the role and
accepted appointment for ordinary visibility/revocation.

## Master-plan amendment

This ADR does not amend the master plan. It applies its tenant-scoped roles,
appointed media-rights authority, private media, deterministic records and
local non-production development boundaries to the exact LPL-01 stop.

## Approval evidence

- Evidence ID: `APP-0051`
- Decision date: 2026-08-10, Asia/Kolkata
- Human approver identifier: `Codex task accountable product owner`
- Roles exercised: Accountable Product Owner; Technical Decision Owner;
  Security Reviewer; Privacy Reviewer; Tenancy Reviewer
- Exact human statement: `Approve DR-0024 Option A as written.`
- Approved DR SHA-256:
  `2b52f1c3c09206a33de7b940e2184c473d3095b89388d30fc9830386f9266538`
- Master-plan impact: none

Codex is the author and evidence recorder only, never the approver. One human
exercised consolidated development roles; this is not independent specialist,
academic/Kannada, legal or production review.

## Follow-up

1. **Complete:** implement and verify only the local bootstrap and
   organization-admin own-appointment read/acceptance compatibility.
2. **Complete:** execute it once for the exact local state and leave its
   persistent switch absent.
3. **Complete:** accept the exact appointment and confirm four APP-0047 rights
   revisions through ordinary first-party web/fresh-TOTP APIs.
4. **Complete:** DR-0025 Option A fixed and approved the finite run ceiling,
   kill switch, retention/no-training configuration, immutable provenance and
   human transcript-reconciliation contract under APP-0052 / D-0153 /
   ADR-0020.
5. **Current stop:** implement and verify that contract, then keep live
   dispatch fail closed until every current evidence, price, credential,
   permit, privacy, security, tenancy and test gate passes.

---
status: accepted
date: 2026-07-30
deciders:
  - Codex task accountable product owner
consulted:
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: false
approval_evidence:
  - APP-0001
approval_reference: Codex task 019fae0d-d5d2-7e32-b2c2-ced466b4b0cb
---

# ADR-0001: Approval authority and durable evidence

## Status

Accepted on 2026-07-30 under
[APP-0001](../APPROVALS.md#app-0001).
This ADR resolves DR-0008.

## Context

The canonical plan requires written approval before implementation of material
changes, identifies Codex as an implementation agent rather than an approver,
and requires timely product-owner review. The repository did not yet define
which human roles accept ADRs and checkpoints or the evidence needed to make an
approval durable.

The decision must support a lean development phase without allowing one person
holding several roles to be misrepresented as independent specialist review.

## Decision

### Authority

- The **Accountable Product Owner** approves feature specifications,
  checkpoint exits, recorded limitations and authorization to begin the next
  checkpoint.
- The **Technical Decision Owner** accepts or rejects ADRs.
- A master-plan amendment requires approval from both the Accountable Product
  Owner and Technical Decision Owner, plus every applicable specialist reviewer.
- Authentication and security decisions require the **Security Reviewer**.
- Personal-data and privacy decisions require the **Privacy Reviewer**.
- Organization ownership and isolation decisions require the **Tenancy
  Reviewer**.
- Academic or language-policy decisions require the reviewer roles assigned by
  the master plan and are outside this ADR's current PR-003 scope.

One human may hold multiple declared roles for development decisions. The
approval record must list every role exercised. This role consolidation is
never evidence of independent review, specialist qualification or production
readiness.

Independent security, privacy and tenancy specialist review is a mandatory
pre-production gate. Review findings must be resolved or explicitly accepted
by the applicable authority and recorded in the approval ledger.

Codex may propose, author, implement, test and record decisions. Codex must not:

- approve an ADR, specification, checkpoint exit or production release;
- identify itself as a human decision-maker or specialist reviewer;
- infer a personal name from an account, task or instruction; or
- turn missing approval into approval merely because implementation is
  technically possible.

### Durable evidence

`docs/APPROVALS.md` is the canonical repository ledger for approval evidence.
Each accepted ADR carries one or more `APP-NNNN` identifiers in its
`approval_evidence` front-matter field and links to the corresponding ledger
entry.

The evidence record contains the fields defined in that ledger, including the
exact human statement, bounded context, exercised roles, accepted revisions,
conditions and recorder. A contextual instruction such as `Continue` is only
valid for the immediately preceding bounded proposal; the evidence record must
preserve that proposal's scope and must not expand it.

Approved evidence is append-only in meaning. Corrections use a superseding
entry. Git history supplies tamper evidence; a missing commit SHA may be filled
after the record is committed without changing the approval.

### Source-of-truth boundary

Approval makes an artifact accepted; it does not move that artifact higher in
the repository source-of-truth hierarchy. The hierarchy remains:

1. the enterprise product master plan;
2. an accepted ADR that explicitly amends the plan, with the plan updated in
   the same accepted change;
3. an approved feature specification;
4. the active issue or task; and
5. existing implementation behavior.

The approval ledger is evidence about authority and acceptance, not a product
or architecture source that can silently override those artifacts.

## Alternatives considered

### Product-owner approval for every artifact

Rejected because technical and specialist decisions need explicit accountable
review roles, even when the same human temporarily exercises several roles.

### Codex acceptance based on implementation success

Rejected because tests establish evidence about behavior, not human authority
or acceptance. It also conflicts with the master plan's Codex operating
boundary.

### Issue or pull-request state as sufficient evidence

Rejected because a merge state does not necessarily preserve the exact
statement, scope, exercised roles, limitations or master-plan impact.

## Consequences

### Positive

- Checkpoint, architecture and production authority are explicit.
- Exact approval evidence survives outside transient task context.
- Consolidated development roles are visible without being presented as
  independent review.
- Codex's implementation and governance boundaries are testable.

### Negative and trade-offs

- Every accepted artifact needs a small additional evidence record.
- Production remains blocked until independent specialist reviews are
  completed and recorded.
- Contextual approval statements require more evidence than explicit,
  artifact-specific statements.

## Security, privacy and tenancy impact

Authentication, privacy and tenancy decisions cannot be accepted without the
applicable declared human role. Consolidated development review does not waive
independent pre-production review. Approval records must minimize personal data
and must never contain secrets, credentials or learner data.

## Authoritative records and consistency impact

The Git-tracked ledger is the durable approval record. Entries are append-only
in meaning and corrections identify the superseded record. This ADR adds no
database record, transaction or migration.

## API, client and operational impact

There is no API or generated-client change. Issue, pull-request and ADR
templates must request evidence identifiers and make approval roles visible.

## Master-plan amendment

This ADR does not amend the master plan. It operationalizes the authority,
change-control and Codex boundaries in master-plan §§0, 25, 29 and 30.

## Approval evidence

- Evidence ID: `APP-0001`
- Approval reference: `Codex task 019fae0d-d5d2-7e32-b2c2-ced466b4b0cb`
- Acceptance date: 2026-07-30, Asia/Kolkata
- Human approver identifier: `Codex task accountable product owner`
- Roles exercised: Accountable Product Owner; Technical Decision Owner;
  Security Reviewer; Privacy Reviewer; Tenancy Reviewer
- Exact human statement: `Continue`
- Codex role: author and evidence recorder only; never approver
- Master-plan impact: none

## Verification

- Parse the YAML front matter.
- Validate Markdown heading progression, balanced fences and final newline.
- Resolve every repository-local Markdown link.
- Confirm every accepted ADR references an approval-ledger entry.

## Follow-up

- Record independent security, privacy and tenancy specialist reviews before
  production.
- Add the eventual commit or pull-request reference to APP-0001 if a stable
  reference becomes available.

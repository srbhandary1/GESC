# Approval evidence ledger

This ledger records durable human approval evidence for checkpoint exits,
specifications, Architecture Decision Records (ADRs) and master-plan
amendments. It records evidence; it does not replace the source-of-truth
hierarchy in `ENTERPRISE_PRODUCT_MASTER_PLAN.md`.

Codex may author recommendations, record evidence and verify implementation.
Codex is never an approver, decider, product owner or specialist reviewer.

## Evidence rules

Every accepted approval record must contain:

1. a stable `APP-NNNN` identifier and status;
2. the decision date and time zone, without inventing a time that was not
   captured;
3. an unambiguous human approver identifier and each approval role exercised;
4. the exact human approval statement;
5. the immediately preceding proposal or decision context needed to interpret
   that statement;
6. the precise scope and artifact revisions accepted or authorized;
7. conditions, limitations and required follow-up reviews;
8. the applicable ADR, specification, issue, pull request or checkpoint;
9. whether the master plan is amended and the corresponding impact-note
   reference when it is; and
10. the recorder identity and repository revision that makes the evidence
    durable.

Approval evidence is append-only in meaning. Correct an accepted entry by
adding a new entry that identifies the record it supersedes; do not silently
change the original decision or its exact statement. A later commit may fill a
previously unavailable repository revision without changing the recorded
decision.

An approver may exercise more than one declared role during development. That
does not constitute independent review. Security-, privacy- and tenancy-
relevant behavior still requires independent specialist review before
production, even when one human held those development roles.

## Approval roles

The role authority is defined by
[ADR-0001](adr/0001-approval-authority-and-evidence.md):

- **Accountable Product Owner** — approves specifications, checkpoint
  acceptance and authorization to begin the next checkpoint.
- **Technical Decision Owner** — accepts ADRs.
- **Security Reviewer** — reviews authentication and security decisions.
- **Privacy Reviewer** — reviews personal-data and privacy decisions.
- **Tenancy Reviewer** — reviews organization ownership and isolation
  decisions.

<a id="app-0001"></a>

## APP-0001 — PR-002 acceptance, PR-003 decisions and authorization

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-07-30 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `Codex task accountable product owner` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer |
| Approval reference | `Codex task 019fae0d-d5d2-7e32-b2c2-ced466b4b0cb` |
| Evidence source | Human instruction in the referenced Codex task |
| Exact human statement | `Continue` |
| Recorder | Codex, acting only as author and evidence recorder |
| Repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Context and interpretation

Immediately before the statement, Codex had:

1. presented recommended resolutions for DR-0001, DR-0002 and DR-0008;
2. stated that PR-003 implementation was blocked until those decisions and
   PR-002 acceptance were explicitly authorized;
3. supplied explicit approval wording; and
4. repeated that no files would change without approval.

The human then instructed `Continue`. In that bounded context, this is recorded
as acceptance of PR-002, acceptance of the three immediately preceding
recommended decisions and authorization to begin PR-003. It is not approval of
later checkpoints, production deployment or any unpresented scope.

### Accepted artifacts and scope

- [ADR-0001 — Approval authority and durable evidence](adr/0001-approval-authority-and-evidence.md),
  resolving DR-0008.
- [ADR-0002 — Authentication, session, token and recovery policy](adr/0002-authentication-session-token-and-recovery-policy.md),
  resolving DR-0001.
- [ADR-0003 — Resource ownership and organization-context enforcement](adr/0003-resource-ownership-and-organization-context.md),
  resolving DR-0002.
- PR-002 checkpoint acceptance with its recorded limitations.
- Authorization to execute only PR-003 within the canonical checkpoint scope.

### Conditions and limitations

- One human exercised multiple development roles. This is not independent
  specialist review.
- Independent security, privacy and tenancy specialist review is mandatory
  before production. Findings must be resolved or explicitly accepted by the
  accountable human authority and recorded here.
- This approval does not represent unavailable PR-002 environment checks as
  passed.
- The source conversation has no repository-local issue, pull-request URL,
  timestamp-of-day or personal approver name. The durable Codex task reference
  is recorded above; the approver identifier is intentionally role-based and
  does not infer a personal identity.
- Qualified legal counsel must still validate production privacy and
  regulatory behavior as required by the master plan.

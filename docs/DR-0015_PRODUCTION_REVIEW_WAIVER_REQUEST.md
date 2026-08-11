# DR-0015 — Production legal and independent-review waiver request

**Status:** Resolved — Option A accepted under APP-0040 / D-0127

**Needed by:** Any production, Google Play public-release or production-readiness
claim that would otherwise require qualified legal validation or independent
security, privacy and tenancy review

**Authority:** Master plan §§18, 29 and 30; ADR-0001; D-0021

**Opened:** 9 August 2026

**Resolved:** 9 August 2026

## Request received

The human supplied the exact statement:

```text
You have a blanket waiver for legal and independent privacy/security/tenancy review.
```

This is recorded as a requested governance change, not as evidence that any
legal or independent specialist review occurred.

## Conflict requiring a bounded decision

ADR-0001 currently makes independent security, privacy and tenancy specialist
review a mandatory pre-production gate. The master plan separately requires
qualified legal validation of production notices, consent, retention,
deletion, grievance and vendor arrangements. The approval ledger says a
consolidated development reviewer cannot be represented as independent.

A blanket conversational instruction cannot silently override those accepted
artifacts under ADR-0001's source-of-truth and durable-evidence rules. It also
cannot serve as evidence of external legal compliance, specialist work that
did not occur, a production release decision, or a review finding that was
never produced.

This conflict does not block repository implementation checkpoints. It blocks
only a later claim that the affected production gates were satisfied or
validly removed.

## Decision profiles

| Option | Scope | Trade-off |
|---|---|---|
| **A — Development-continuation waiver only (recommended)** | Continue ordered implementation, testing and staging work without pausing for legal or independent specialist review. Keep those reviews as pre-production gates and do not repeatedly request them during ordinary checkpoints | Preserves accepted governance and lets REL-01/MAINT-01 proceed, but production remains gated |
| B — Remove the internal pre-production review gates | Author a new ADR that explicitly supersedes the affected ADR-0001 rule and amends the master plan's internal legal-review requirement in the same accepted change. Record this as risk acceptance, not as review evidence or legal compliance | Removes internal safeguards and materially increases production, regulatory, security, privacy and cross-tenant risk; external law, platform rules, contracts and regulator requirements remain unaffected |
| C — Retain current policy without a continuation interpretation | Keep every existing gate and treat the statement as having no repository effect | Safest interpretation, but may cause repeated review-gate reminders during development |

## Recommended resolution

Option A matches the existing blanket execution authority: implementation can
continue without pretending that missing review happened. It requires no
architecture or master-plan amendment.

To accept it, reply exactly:

```text
Approve DR-0015 Option A as written.
```

If Option B is intended, first request the exact superseding ADR and
master-plan amendment. Approval must then identify the Accountable Product
Owner, Technical Decision Owner, Security Reviewer, Privacy Reviewer and
Tenancy Reviewer roles and accept the exact artifact revisions. Codex will
still record missing legal/specialist evidence honestly and will not describe
an unperformed review as completed.

## Non-effects while open

- REL-01 and later non-production implementation may continue in approved
  checkpoint order.
- No production deployment, public Play release or real-learner-data use is
  authorized by the quoted statement.
- No independent-review finding, legal opinion, compliance determination or
  production acceptance is inferred.
- Accepted security, privacy, tenancy, consent, retention, deletion and
  authoritative-record behavior remains unchanged.

## Resolution

Option A was accepted as written on 9 August 2026 under
[APP-0040](APPROVALS.md#app-0040) and
[D-0127](DECISIONS.md). The exact approved proposal, before this status,
resolved date and resolution section were added, has SHA-256
`a40c05bc4bd590e83102720b4eb18b1e0f0a7eb12c84c89883faf0cbffc6355d`.

Ordered development, testing and staging checkpoints may proceed without
pausing for or repeatedly requesting legal or independent security, privacy
and tenancy review. Those reviews remain mandatory pre-production gates under
ADR-0001 and the master plan. This resolution is not review evidence, legal
validation, production acceptance, deployment authorization, public Play
release authorization or permission to use real learner data in production.

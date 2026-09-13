# DR-0014 — Reference admin parity scope and policy request

**Status:** Resolved — Option A accepted under APP-0039 / ADR-0014 / D-0125
**Needed by:** ADMIN-GOV-01 and every later ADMIN checkpoint
**Authority:** Master plan §§8, 9, 13, 15, 16, 18, 19 and 31; ADR-0001 through
ADR-0004; ADR-0007; ADR-0012; D-0123
**Opened:** 9 August 2026
**Resolved:** 9 August 2026

## Problem

The authenticated reference portal exposes useful administration for content,
learners, staff, groups, assignments, communications, plans and reports. Some
of those concepts are within the current master plan, while others cross its
explicitly deferred subscription, live-class, peer-chat, broad LMS and public
content boundaries.

A literal clone would also conflict with accepted security and data rules. The
observed interface includes administrator-selected passwords, generic hard
delete actions, catch-all group types, direct personal-data tables and media
paths without the repository's versioning, tenant, rights, consent and audit
guarantees.

Choosing a mapping silently would change product scope, personal-data
processing, tenancy relationships and authoritative schema. The affected path
must stop until an exact policy profile is accepted.

## Decision profiles

| Option | Scope | Trade-off |
|---|---|---|
| **A — Secure functional parity, delivered in governed stages (recommended)** | Implement every observed feature family as the safe domain equivalent in the parity plan. Preserve current architecture and add narrowly bounded optional operator capabilities | Meets the business goal while requiring staged checkpoints, explicit retention/authority rules and no literal legacy behaviour |
| B — Literal portal parity | Reproduce the reference screens, concepts and actions closely, including broad roles, group flags and delete/edit behaviour | Faster visual comparison but conflicts with accepted tenancy, authentication, privacy, immutable-content and authoritative-record rules; not recommended |
| C — Current master-plan core only | Implement only organization administration, governed content, trainer review, support, privacy and audit; omit plans, public CMS, group communication and live-session scheduling | Lowest scope and risk, but does not satisfy the request for all observed feature families |

## Recommended Option A policy

Approval of Option A means all of the following:

1. The complete capability inventory and checkpoint order in
   [ADMIN_REFERENCE_PARITY_PLAN.md](ADMIN_REFERENCE_PARITY_PLAN.md) becomes the
   approved feature specification. Functional intent is approved; reference
   code, design, data, media and unsafe mechanics are not.
2. The master plan will be amended to authorize a staged **operator parity
   expansion** after the already ordered PRIV-DSR-01, REL-01 and MAINT-01
   checkpoints. The expansion may add the safe subsets below without claiming
   they are Phase 1 voice-product prerequisites.
3. Learners and staff remain global identities with organization-owned
   memberships. Staff activation uses invitation/verified recovery, not an
   administrator-chosen password. Roles and reviewer/rights appointments are
   membership scoped; general super-administrator bypass remains prohibited.
4. Reference `delete` actions map to deactivate, withdraw, retire, redact or
   privacy-erasure commands according to record class. Published content,
   submitted evidence, entitlement/financial evidence and privileged audit are
   immutable or append-only and cannot be hard-deleted by ordinary operators.
5. Course, level, lesson, post, blog, template, question and media editing uses
   draft successor revisions, optimistic concurrency and explicit
   review/publish/rollback where released material is involved. Existing
   ADR-0004 academic, localization and media-rights authority cannot be waived.
6. `Prime` status becomes a deterministic entitlement with a plan, effective
   period, source and immutable grant/adjustment/revocation evidence. Option A
   authorizes manual or externally reconciled entitlements, not consumer
   checkout, accounting, a payment provider, webhook secrets or provider spend.
7. A live class means a timezone-aware scheduled facilitated session and a
   validated external meeting link. It does not authorize building or embedding
   a video-conferencing service, recording a class or treating attendance as
   learning engagement/mastery.
8. General, activity, daily, assignment and conversation groups become
   explicit typed aggregates with separate schemas and permissions rather than
   numeric visibility flags. Membership is organization scoped and does not
   grant curriculum, learner-record or recording access by implication.
9. Operator/trainer notices and moderated group communication are authorized
   in principle, but open peer-to-peer social chat is not. Raw messages and
   attachments are private content with purpose, audience, retention,
   report/block and deletion rules fixed before implementation. Audio recording
   remains subject to ADR-0007 consent and private-media controls.
10. Assignment/workshop marks, qualification results and comments are human
    review evidence under an explicit rubric and correction history. AI cannot
    set the result, mastery, progression, attendance or entitlement.
11. Public blog and in-app post capabilities are separate. Public editorial
    content requires licensed media, accessible output, revisions and a publish
    role; learner-specific messages, support notes and learning evidence never
    become public CMS content.
12. Operator-selectable languages are limited to the repository's approved UI
    and content-governance boundaries. The reference portal's broad language
    list is not evidence of reviewed translation, curriculum, speech or support.
13. Configuration is typed and allowlisted. Secrets, credentials, provider
    keys and unrestricted URLs are never operator-readable generic key/value
    records.
14. Every new tenant resource and mutation follows ADR-0003 organization
    context, permission and cross-tenant rules; sensitive mutations require
    fresh web TOTP; API commands use transactions/idempotency/audit/outbox as
    applicable; OpenAPI and generated clients change together.
15. The requester's blanket execution authorization means Codex may proceed
    through the approved, ordered, bounded checkpoints without asking for a
    generic `Continue` between them. It is not advance acceptance of unbuilt
    checkpoint results, an unresolved later ADR, provider spend, production
    data migration, deployment, production release or independent review.

## Master-plan amendment authorized by Option A

When Option A is accepted, the same accepted change will update the master
plan so that:

- §8.3 identifies admin-managed entitlements, moderated notices/groups,
  assignment review, external live-session scheduling and editorial CMS as an
  optional operator-parity expansion;
- §9.2 continues to defer in-product conferencing, open peer-to-peer social
  features, consumer checkout/coupons/accounting and broad attendance/LMS
  semantics;
- §16.5 keeps optional operations records separate from canonical learning
  engagement/mastery; and
- §31 records the ordered parity program without presenting it as completed or
  production approved.

The accepted decision will be recorded in a new ADR and approval-ledger entry
with the plan updated in the same change, as required by ADR-0001.

## Resolution requested

To select the recommended safe parity program, reply exactly:

```text
Approve DR-0014 Option A as written.
```

That statement will be recorded as consolidated development approval by the
Accountable Product Owner, Technical Decision Owner, Security Reviewer,
Privacy Reviewer and Tenancy Reviewer. One human holding those roles is not
independent specialist review or production approval.

Alternatively, select Option C or describe a specific change to Option A.
Option B cannot be implemented while the current accepted architecture and
security/privacy decisions remain authoritative.

## Resolution

Option A was accepted as written on 9 August 2026 under
[APP-0039](APPROVALS.md#app-0039),
[ADR-0014](adr/0014-secure-reference-admin-functional-parity.md) and
[D-0125](DECISIONS.md). The exact approved proposal, before this status and
resolution note were added, has SHA-256
`7a88bc31444b4e99107c5b4def16c42d0efc18a07bcc587a235737934dbd4d10`.
The approved parity specification before its approval and gate-state
annotations has
SHA-256
`caa24f8dc581c1ed755be8c90af53bc45a6ff8d678eaccbd3b0c6278a941294b`.

The same accepted change amends the canonical master plan to version 3.2.
This resolves the overall product-boundary decision and removes DR-0014 as an
ADMIN-GOV-01 blocker. It does not implement or accept an ADMIN checkpoint,
change the earlier PRIV-DSR-01, REL-01 and MAINT-01 ordering, authorize
production-data migration or payment-provider work, waive a retained later
decision gate or approve deployment/production.

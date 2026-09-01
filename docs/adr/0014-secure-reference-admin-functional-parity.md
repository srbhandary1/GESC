---
status: accepted
date: 2026-08-09
deciders:
  - Codex task accountable product owner as Accountable Product Owner and Technical Decision Owner
  - Codex task accountable product owner as Security Reviewer, Privacy Reviewer and Tenancy Reviewer
consulted:
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: true
approval_evidence:
  - APP-0039
approval_reference: Exact human instruction in the active Codex conversation
---

# ADR-0014: Secure reference-admin functional parity

## Status

Accepted on 9 August 2026 under
[APP-0039](../APPROVALS.md#app-0039). This ADR resolves
[DR-0014](../DR-0014_ADMIN_REFERENCE_SCOPE_AND_POLICY_REQUEST.md) by accepting
Option A as written and amends the canonical master plan to version 3.2 in the
same accepted change.

The approved DR proposal, before its resolution annotation, has SHA-256
`7a88bc31444b4e99107c5b4def16c42d0efc18a07bcc587a235737934dbd4d10`.
The approved parity specification, before its approval and gate-state
annotations, has SHA-256
`caa24f8dc581c1ed755be8c90af53bc45a6ff8d678eaccbd3b0c6278a941294b`.

## Context

The authenticated reference operator portal exposes content, people, groups,
assignments, communications, plans, live-session links, editorial content and
reports. Some map to the existing master-plan core; others cross boundaries
that were deferred to keep Phase 1 focused on the voice product.

A literal clone would conflict with accepted authentication, tenancy, privacy,
content-publication and authoritative-record rules. In particular,
administrator-selected passwords, generic hard deletes, numeric group flags,
direct personal-data tables and ungoverned media paths cannot become the
target behavior. Selecting mappings without approval would change product
scope, personal-data processing, tenant relationships and foundation schema.

ADMIN-DISC-01 therefore produced a read-only capability inventory, a safe
functional mapping and an ordered parity specification, then stopped at
DR-0014 before feature schema or implementation.

## Decision

Accept DR-0014 Option A exactly as written. Its fifteen numbered policy rules
are normative and incorporated into this ADR. In particular:

1. [ADMIN_REFERENCE_PARITY_PLAN.md](../ADMIN_REFERENCE_PARITY_PLAN.md) is the
   approved feature specification. Functional intent is approved; reference
   code, visual design, data, media and unsafe mechanics are not.
2. The optional operator-parity program begins only after PRIV-DSR-01, REL-01
   and MAINT-01 and then follows the specification's ADMIN-GOV-01 through
   ADMIN-ACCEPT-01 order. It is not a Phase 1 voice-product prerequisite.
3. Users remain global identities with organization-owned memberships.
   Staff activation uses invitation or verified recovery; roles and
   appointments are membership scoped; a general super-admin bypass remains
   prohibited.
4. Reference deletes become class-specific deactivate, withdraw, retire,
   redact or privacy-erasure commands. Published content, submitted evidence,
   entitlement/financial evidence and privileged audit remain immutable or
   append-only against ordinary operator deletion.
5. Released content and media use governed successor revisions, optimistic
   concurrency, review, publication and rollback. ADR-0004 academic,
   localization and rights authority remains mandatory.
6. `Prime` maps to a deterministic entitlement with a plan, effective period,
   source and immutable grant/adjustment/revocation evidence. Manual and
   externally reconciled entitlements are allowed. ADR-0035 additionally
   authorizes Direct in-app checkout through a project-owned
   `PaymentProvider` port with dummy capture in non-production. Accounting
   packages, webhook secrets, Play Billing, live provider spend and
   production payment credentials are not authorized.
7. A live class is only a timezone-aware facilitated-session schedule with a
   validated external meeting link. This decision adds no conferencing
   service, recording authority or attendance-to-mastery implication.
8. Groups use explicit types, schemas and permissions. Membership remains
   organization scoped and never implies access to curriculum, learner
   records or recordings.
9. Operator/trainer notices and moderated group communication are authorized
   in principle; open peer-to-peer social chat is not. Purpose, audience,
   retention, report/block, attachment and deletion rules remain later gates.
   Audio remains governed by ADR-0007.
10. Assignment and workshop outcomes are human rubric evidence with correction
    history. AI cannot set a result, mastery, progression, attendance or
    entitlement.
11. Public editorial content and in-app posts are separate governed domains.
    Learner messages, support notes and learning evidence never become public
    CMS content.
12. Languages remain limited by accepted UI, curriculum, speech and support
    governance. A reference language list is not approval of translations or
    learning-language support.
13. Configuration is typed and allowlisted. Secrets, credentials, provider
    keys and unrestricted URLs are never generic operator-readable values.
14. Every tenant resource follows ADR-0003. Sensitive mutations require fresh
    web TOTP. Transactions, idempotency, audit, outbox, OpenAPI and regenerated
    clients apply together where relevant.
15. The requester's checkpoint-continuation authority removes a generic
    `Continue` prompt between approved bounded checkpoints. It is not advance
    acceptance, a waiver of a later ADR or review, spend authority, data-import
    authority, deployment authority or production approval.

Where this summary is less detailed than the approved DR or parity
specification, their exact accepted constraints control.

## Master-plan amendment

This ADR explicitly amends the master plan, with the plan updated in the same
accepted change:

- §8.3 authorizes admin-managed entitlements, moderated notices/typed groups,
  assignment review, external facilitated-session scheduling and separate
  editorial CMS as the optional operator-parity expansion;
- §9.2 continues to defer in-product conferencing/class recording, open
  peer-to-peer social features, consumer checkout/coupons/accounting and broad
  attendance/LMS semantics;
- §16.5 adds optional operations concepts while keeping them semantically
  separate from canonical learning engagement, evidence, mastery and
  progression; and
- §31 records the ordered ADMIN program and retained gates without claiming
  implementation or production approval.

The canonical document version becomes 3.2. No other master-plan section is
amended by this ADR.

## Alternatives considered

### Literal portal parity

Rejected because it conflicts with accepted password, tenant, deletion,
publication, rights, privacy and authoritative-record guarantees.

### Current master-plan core only

Not selected because it would omit safe equivalents for observed entitlement,
public editorial, moderated communication, assignment-review and external
facilitated-session capabilities and would not meet the requested parity goal.

## Consequences

### Positive

- The business parity goal has an explicit safe product boundary and order.
- Existing architecture and deterministic learning authority remain intact.
- Unsafe reference mechanics cannot be treated as acceptance criteria.
- Later checkpoints have explicit privacy, tenancy, rights and release gates.

### Negative and trade-offs

- The program is substantially larger than the current operator core and must
  remain staged behind prerequisite remediation.
- Communication, moderation, payment/provider and import policies still need
  later exact decisions before their affected writes.
- Functional parity will intentionally differ from the reference portal's
  data model and destructive behavior.

## Security, privacy and tenancy impact

Every tenant-owned record remains membership-context derived, permission
checked, tenant indexed/constrained and covered by same-tenant and cross-tenant
tests. Fresh web TOTP is an additional security gate, never a source of tenant
or role authority. Passport bearers remain denied on privileged/operator
routes under D-0123.

Messages, attachments, learner submissions, support notes and recordings are
private content. They cannot enter logs, analytics properties, audit payloads
or outbox payloads as raw content. Each affected checkpoint must fix purpose,
audience, access, retention, deletion and moderation semantics before writes.
ADR-0007 continues to control audio consent, private media and deletion.

One human exercised the five consolidated development roles. This is not
independent specialist review. Independent security, privacy and tenancy
review, qualified legal review where applicable and explicit production
authorization remain required.

## Authoritative records and consistency impact

Published content, submitted evidence, entitlement events, human-review
history and privileged audit are immutable or append-only. Authoritative
multi-write operations require transactions, concurrency control, actor- and
request-bound idempotency, safe audit evidence and transactional outbox work
where side effects follow. AI remains advisory and cannot write marks,
mastery, progression, attendance or entitlements.

This decision creates no product table or migration. Exact schemas are added
only by their bounded vertical-slice checkpoints after ADMIN-GOV-01 freezes
ownership, permission, retention and route matrices.

## API, client and operational impact

This decision changes no API or generated client. Each later API checkpoint
must update OpenAPI 3.1.1 and generated clients together and provide complete
accessible operator states. Production-data migration, reference-data copy,
payment-provider integration, provider spend, deployment and production
release remain separately authorized operations.

## Approval evidence

- Evidence ID: `APP-0039`
- Decision date: 2026-08-09, Asia/Kolkata
- Human approver identifier: `Codex task accountable product owner`
- Roles exercised: Accountable Product Owner; Technical Decision Owner;
  Security Reviewer; Privacy Reviewer; Tenancy Reviewer
- Exact human statement: `Approve DR-0014 Option A as written.`
- Master-plan impact: version 3.2 §§8.3, 9.2, 16.5 and 31

Codex is the author and evidence recorder only, never the approver.

## Verification

- Verify the pre-resolution DR and pre-annotation specification SHA-256 values.
- Parse ADR front matter and validate local links, headings, fences and final
  newlines for all affected governance files.
- Confirm APP-0039, ADR-0014 and D-0125 are unique and DR-0014 is absent from
  the open-decision table.
- Confirm the master plan contains all four authorized amendments and no
  product code, schema, API, generated client or configuration change is
  attributed to this approval task.

## Follow-up

- Complete PRIV-DSR-01, REL-01 and MAINT-01 in their existing order.
- Execute ADMIN-GOV-01 as a separately bounded checkpoint to freeze the
  capability, permission, ownership, retention and route matrices before any
  ADMIN feature schema.
- Retain the communication, moderation, provider/payment, legacy-import,
  independent-review and production gates listed by the approved
  specification.

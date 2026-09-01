# Reference admin functional-parity plan

**Status:** Approved specification under
[DR-0014](DR-0014_ADMIN_REFERENCE_SCOPE_AND_POLICY_REQUEST.md) Option A,
[APP-0039](APPROVALS.md#app-0039) and
[ADR-0014](adr/0014-secure-reference-admin-functional-parity.md);
ADMIN-GOV-01 through ADMIN-CONTENT-01 are complete at repository level;
ADMIN-MEDIA-01 is at its APP-0044/D-0138/DR-0019 decision-specification gate
under the fixed order; support-note writes remain excluded and blocked

**Prepared:** 9 August 2026

**Reference inspected:** authenticated, read-only operator portal at
`globalspokenenglish.com/admin`

**Authority:** `ENTERPRISE_PRODUCT_MASTER_PLAN.md`, accepted ADRs,
`CODEBASE_GAP_REMEDIATION_PLAN.md`, and the repository `AGENTS.md`

## Objective

Implement the useful operator capabilities exposed by the reference portal in
this repository, with a clearer and safer experience. Functional intent is the
target; the reference implementation, visual theme, data model and unsafe
behaviour are not.

All work remains in the Laravel modular monolith and React operator web
surface. Existing authentication, organization context, content governance,
deterministic learning authority, privacy, audit and media abstractions remain
authoritative.

The frozen capability, permission, ownership, retention and route contract is
[ADMIN_GOVERNANCE_MATRIX.md](ADMIN_GOVERNANCE_MATRIX.md). Later checkpoints
must cite and narrow that contract rather than infer broader authority from the
reference portal.

## Evidence boundary

The inventory used authenticated read-only page requests. It recorded only
navigation, headings, field names, table-column names and action types needed
to identify capabilities. It does not authorize or perform:

- copying learner, staff, message, assignment, media or report data;
- copying the reference site's source, visual assets or licensed content;
- calling create, update, publish, send, mark, delete or upload actions;
- importing production records into this repository; or
- retaining the supplied reference credential in source, documentation or
  configuration.

The reference portal is discovery evidence, not a product, architecture or
security authority.

## Feasibility result

Every observed feature family can be represented in this product. Several
must be implemented as a safer domain equivalent instead of a literal clone:

| Reference capability | Target capability here | Existing foundation | Main work remaining |
|---|---|---|---|
| Dashboard | Role-specific operations overview, queues, alerts and recent activity | Permission-derived responsive shell, safe search, independent pilot/diagnostic/content/audit panels and audit detail | Add only the domain-specific widgets and filters authorized by each later checkpoint |
| Courses, levels and lessons | Governed programs, curriculum versions, levels/modules, objectives, lessons and learning items | Versioned curriculum and immutable publication model | Complete authoring APIs and accessible studio UI with optimistic concurrency |
| Lesson files and media list | Private media library, variants, transcripts, rights evidence and usage references | Media assets, rights revisions and verified private delivery | Quarantine/validation workflow, metadata UI, replacement/versioning and safe preview |
| Non-prime and prime users | Learner memberships, profiles, enrollments and entitlements | Users, memberships, learner profiles and enrollments | Search/detail workflows, lifecycle commands, plan/entitlement model and operator UI |
| Office and trainer accounts | Organization staff invitations, roles, appointments and session controls | Membership-scoped RBAC, MFA, sessions and audit | Staff lifecycle UI, invite activation, trainer profiles and assignment workflows |
| General groups and members | Explicit cohorts or typed learning groups with scoped membership | Cohorts, trainers and enrollments | Typed group aggregate, membership lifecycle, scheduling and media rules |
| Live-class link | Scheduled facilitated session with an external meeting link | None | Schedule, timezone, attendance-independent status, reminders and audit |
| Activity and daily rooms | Versioned activity templates, questions, options, release schedule and assignments | Missions, items, attempts and deterministic evidence | Authoring/release UI, question media, scheduling and aggregate reports |
| Assignment groups | Assignment definitions, targeted learners and submissions | Learning attempts and evidence primitives | Assignment aggregate, submissions, human review and status workflow |
| Workshop review | Trainer work queue for learner answers and feedback | Support diagnostics and learner evidence | Read/unread queue, review notes, permission checks and learner-visible feedback |
| Marks, result and comments | Rubric-based human review kept separate from deterministic mastery | Mastery and immutable attempt evidence | Versioned rubrics, reviewer decisions, appeals/corrections and audit |
| Conversation groups and chat | Moderated, tenant-scoped conversation cohorts and retained message history | Individual conversation sessions | Group authorization, moderation, retention, report/block controls and safe delivery |
| Send/default messages | Versioned templates and targeted text/media notices with delivery history | Transactional outbox only | Audience resolution, templates, attachments, delivery/read state and privacy policy |
| Posts | In-app learning announcements/resources | None | Draft/review/publish workflow, audiences, attachments and expiry |
| Blog | Public editorial CMS with SEO metadata | None | Editorial roles, revisions, preview, publication, media rights and public routes |
| Configuration links | Typed organization/platform settings and feature flags | Configuration and capability primitives | Schema-backed setting catalogue, validation, audit and safe operator UI |
| Activity reports | Privacy-safe response distributions and operational exports | Pilot analytics and audit | Aggregate read models, minimum-group suppression, filters and CSV jobs |

## Experience improvements

The target console will improve on the reference experience through:

- task-based navigation for Overview, People, Learning, Communications,
  Operations and Safety;
- one responsive shell with keyboard navigation, visible focus, announced
  loading/errors and WCAG 2.2 AA critical journeys;
- server-side cursor pagination, debounced search, filters, saved views and
  explicit empty states rather than unbounded personal-data tables;
- dashboards that prioritize work queues and exceptions instead of an empty
  landing page;
- optimistic-concurrency conflict handling, idempotent commands and guarded
  bulk actions with previews;
- status history and an audit drawer beside every authoritative record;
- resumable, bounded uploads with progress, validation feedback and private
  previews;
- localized operator messages with `en-IN` as the optional operator default
  and governed catalogue fallbacks; and
- role-aware navigation that hides unavailable actions while server-side
  authorization remains decisive.

## Non-negotiable implementation rules

1. Every tenant-owned resource derives organization context from the active
   membership and receives read/list/create/update/delete/relationship and
   mass-assignment cross-tenant tests.
2. Staff accounts use invitation or approved recovery flows. Operators never
   choose, view or transmit another person's password.
3. Privileged mutations require the existing web-only fresh TOTP assertion.
   Passport bearer access to the operator console remains denied.
4. Ordinary deletion becomes deactivation, withdrawal, retirement or a
   governed privacy-erasure workflow. Published content, submitted evidence,
   financial evidence and privileged audit are not hard-deleted.
5. Published content and released templates are immutable versions. Editing
   creates a draft successor and preserves history.
6. Learner marks, assignment outcomes, progression, mastery, attendance and
   entitlements remain deterministic authoritative records. AI may assist with
   drafting or summarization only behind a separately approved boundary and
   never writes those records.
7. Media is private, size/type/signature bounded, malware-scanned where
   applicable, rights-governed and delivered through project-owned adapters.
8. Messages, learner answers, recordings and support notes never enter logs,
   analytics properties, audit payloads or outbox payloads as raw content.
9. Multi-write authoritative commands use transactions, locks or optimistic
   concurrency, request-bound idempotency, append-only audit and the
   transactional outbox where side effects follow.
10. API changes update OpenAPI 3.1.1 and generated clients in the same
    checkpoint. Each UI slice includes accessible loading, empty, error,
    retry, success and conflict states.

## Ordered execution plan

Only one checkpoint is active at a time. The existing remediation order is
preserved because complete privacy inventory and reliable release gates are
prerequisites for a broader operator surface.

| Order | Checkpoint | Vertical slice and exit criteria | State |
|---:|---|---|---|
| 0 | ADMIN-DISC-01 | Read-only reference inventory, local gap map, safe parity rules, ordered plan and material-decision request | **Complete** |
| 1 | PRIV-DSR-01 | Finish the full learner-linked data inventory and deterministic export/erasure outcomes before adding more personal-data surfaces | **Complete** |
| 2 | REL-01 | Enforce PostgreSQL, critical E2E, Android and outbox/recovery gates so later operator mutations cannot outrun release evidence | **Complete** |
| 3 | MAINT-01 | Complete the bounded deduplication and stale-document reconciliation already ordered by the remediation plan | **Complete** |
| 4 | ADMIN-GOV-01 | Record DR-0014 conformance and freeze capability, permission, ownership, retention and route matrices; add no feature schema before the gate | **Complete** — frozen in `ADMIN_GOVERNANCE_MATRIX.md`; no schema or route added |
| 5 | ADMIN-SHELL-01 | Deliver the responsive operator information architecture, permission-aware navigation, independent panel loading, task dashboard, global safe search and audit drill-down | **Complete** — derived `O-READ` workspace, OpenAPI 0.17.0 and 12-journey E2E gate |
| 6 | ADMIN-ORG-01 | Deliver organization, branch, cohort and staff/trainer administration with invitation activation, membership-scoped roles, authority appointments, session revocation and deactivation | **Complete** — bounded staff-only repository slice, OpenAPI 0.18.0, People UI and full exit-gate evidence |
| 7 | ADMIN-LEARNER-01 | Deliver tenant-scoped learner search/detail, profile correction, enrollment, curriculum pin, cohort-derived trainers and account-lifecycle actions; support notes remain excluded | **Complete** — bounded repository slice, OpenAPI 0.19.0, Learners UI and full exit-gate evidence |
| 8 | ADMIN-CONTENT-01 | Deliver program/level/module/objective/lesson/item draft authoring, preview, Kannada/English review queues, publish/rollback actions and complete conflict/history UX | **Complete** — bounded graph-v2 owner workspace, exact review/readiness queues, OpenAPI 0.20.0, Content Studio UI and full exit-gate evidence under D-0136/D-0137 |
| 9 | ADMIN-MEDIA-01 | Deliver private media upload/quarantine, metadata, transcript/alt-text, rights evidence, usage references, replacement revisions and governed preview | **Decision/specification gate open** — owner rights attestation recorded under APP-0044/D-0138; DR-0019 Option A awaits resolution before schema/runtime work |
| 10 | ADMIN-GROUPS-01 | Deliver typed learning groups/cohorts, membership, visibility, scheduled external live-session links and reminders without adding an in-product conferencing engine | Pending |
| 11 | ADMIN-ACTIVITY-01 | Deliver activity/daily-room templates, text/audio questions, normalized choices, correct-answer authority, scheduling, learner assignment and privacy-safe aggregate reports | Pending |
| 12 | ADMIN-REVIEW-01 | Deliver workshop and assignment queues, submissions, read state, rubric-based human review, comments, qualified/disqualified decisions, correction history and learner feedback | Pending |
| 13 | ADMIN-COMMS-01 | Deliver versioned default templates, targeted notices/messages, text and governed media, preview, scheduling, delivery/read history, withdrawal and retention controls | Pending |
| 14 | ADMIN-CONVERSATION-01 | Deliver moderated conversation-group membership, publish lifecycle, retained group history, reporting/blocking and trainer moderation under the approved privacy boundary | Pending |
| 15 | ADMIN-CMS-01 | Deliver separate in-app post and public-blog workflows with revisions, SEO metadata, licensed media, preview, scheduling, rollback and public cache/accessibility behaviour | Pending |
| 16 | ADMIN-ENT-01 | Deliver plan catalogue, dummy in-app checkout, GST settings, demo meter and operator grants for Direct. Live payment-provider credentials remain separately gated | Implemented |
| 17 | ADMIN-CONFIG-01 | Deliver typed, validated organization/platform settings, operational links and feature flags; secrets and credentials remain outside operator-readable storage | Pending |
| 18 | ADMIN-ACCEPT-01 | Close parity matrix, end-to-end operator journeys, accessibility, tenant/adversarial security, privacy, load, backup/restore, runbooks and migration rehearsal. Production data import and deployment remain separately authorized | Pending |

## Checkpoint protocol

Each implementation checkpoint must:

1. publish a bounded specification and inspect the affected code/tests before
   editing;
2. define ownership, permissions, lifecycle, authoritative records, retention,
   audit and failure semantics;
3. implement a complete API-to-UI vertical slice rather than unused schema;
4. add SQLite unit/feature coverage and real PostgreSQL concurrency/tenancy
   coverage where authoritative behavior changes;
5. update OpenAPI, generated clients, localization and operator documentation;
6. run `make verify`, plus `make e2e`, `make build` and any relevant provider or
   migration checks; and
7. update `STATUS.md`, `DECISIONS.md` and `QUALITY_REVIEW.md` before moving to
   the next checkpoint.

## Decision gates retained inside the program

DR-0014 resolves the overall product boundary, and DR-0018 resolves the
ADMIN-CONTENT owner-workspace boundary. APP-0044/D-0138 records the owner-level
media-rights statement without converting it into per-asset approval;
DR-0019 remains open for the ADMIN-MEDIA upload/scanning/accessibility/object
lifecycle. These later choices still require exact reviewed artifacts before
their affected writes are added:

- message/notice purpose, audience, opt-out, attachment, read-tracking and
  retention policy before ADMIN-COMMS-01;
- group-message retention, moderation, reporting and trainer access before
  ADMIN-CONVERSATION-01;
- entitlement authority, adjustment evidence, tax/invoice boundary and any
  payment provider/webhook/spend decision before provider-backed work in
  ADMIN-ENT-01; and
- legacy production-data migration, identity matching, media licensing and
  reconciliation before any import. Feature parity does not imply data-copy
  authorization.

## Program completion definition

The program is complete only when every matrix row is either delivered or has
a recorded approved exclusion, all critical operator tasks can be completed
without direct database access, every privileged action is authorized and
auditable, and the full verification/release evidence is green. Completion is
not production approval, independent specialist review or permission to reuse
reference-site data or media.

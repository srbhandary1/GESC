# ADMIN-GOV-01 governance matrix

**Status:** Frozen repository implementation contract under ADR-0014 and
D-0130; ADMIN-SHELL-01 through ADMIN-CONTENT-01 have realized only their
bounded rows; ADMIN-MEDIA-01 is at APP-0044/D-0138/DR-0019 gate

**Recorded:** 9 August 2026

**Next ordered checkpoint:** ADMIN-MEDIA-01; DR-0019 must resolve before its
feature schema/runtime work and no later row may begin opportunistically

**Production/public release:** Not authorized

## Authority and freeze semantics

This matrix implements the ADMIN-GOV-01 gate in the
[approved parity specification](ADMIN_REFERENCE_PARITY_PLAN.md). Its authority
comes from the canonical master plan, [ADR-0001](adr/0001-approval-authority-and-evidence.md),
[ADR-0002](adr/0002-authentication-session-token-and-recovery-policy.md),
[ADR-0003](adr/0003-resource-ownership-and-organization-context.md),
[ADR-0004](adr/0004-content-ownership-publication-and-curriculum-sharing.md),
[ADR-0005](adr/0005-phase-1-multilingual-ui-language-packs.md),
[ADR-0006](adr/0006-offline-retention-isolation-and-conflict-policy.md),
[ADR-0007](adr/0007-recording-consent-retention-provider-processing-and-deletion-policy.md),
[ADR-0013](adr/0013-lesson-video-learner-delivery.md) and
[ADR-0014](adr/0014-secure-reference-admin-functional-parity.md).

“Frozen” means every later ADMIN checkpoint must select its behavior from
these rows. A checkpoint may narrow a row. Expanding ownership, authority,
visibility, retention, public access or platform capability requires an
explicit matrix update and, when material, a new accepted decision. Existing
accepted ADRs remain higher authority.

Permission names marked **reserved** are documentation only. They are not
seeded permission records, role grants, endpoints or authorization. A later
vertical slice may add only its own reserved definitions and must add
organization-scoped grant, denial and cross-tenant tests at the same time.

Rows marked **blocked** are intentionally complete fail-closed outcomes for
this gate. No table, route, seed or placeholder success behavior may be added
until the named retained decision is recorded.

## Invariants for every ADMIN checkpoint

1. Every operator route is first-party, web-session-only and resolves one
   active membership-derived `OrganizationContext`. Passport bearers remain
   denied.
2. Every operator mutation requires a fresh TOTP assertion. Recovery-code
   assertions remain limited to the approved factor-replacement scope.
3. Tenant equality and permission authorization are separate mandatory
   checks. A guessed identifier from another tenant is indistinguishable from
   a missing resource.
4. Normal ADMIN work uses organization permissions, not `PlatformCapability`.
   There is no general super-admin or scope-disable path.
5. Permission definitions are platform-global. Roles, grants, appointments
   and every exercise of authority are organization-owned.
6. A role pack is navigation/setup convenience, never an authorization
   primitive. Server decisions evaluate atomic permissions and, where
   required, a current human authority appointment.
7. Every organization-owned table has non-null `organization_id`,
   tenant-leading indexes/business uniqueness and composite tenant
   relationships where PostgreSQL can enforce them.
8. Ownership is derived from context and immutable. Request fields cannot
   create, replace or transfer ownership.
9. Operator commands use `Idempotency-Key`, a normalized request fingerprint,
   transactions, concurrency control, safe audit and transactional outbox work
   whenever a side effect follows.
10. Ordinary operator deletion is prohibited. Commands deactivate, withdraw,
    retire, redact or open the governed privacy-erasure path. Released content,
    submitted evidence, entitlement evidence and privileged audit remain
    immutable or append-only.
11. AI may draft or summarize only behind a later approved boundary. It never
    writes marks, review outcomes, mastery, progression, attendance or
    entitlements.
12. Private messages, attachments, learner answers, support notes, recordings
    and provider payloads never enter logs, analytics properties, audit
    payloads or outbox payloads as raw content.
13. Lists use bounded cursor pagination. Exports require both source-read and
    export authority, field allowlists, safe background delivery and no silent
    row cap.
14. Released content/templates use successor revisions and explicit
    publish/retire/rollback events. Draft mutation alone never changes a live
    learner or public view.
15. API changes update OpenAPI 3.1.1 and generated clients in the same
    checkpoint. UI slices include accessible loading, empty, partial-failure,
    retry, success and conflict states.

## Capability checkpoint matrix

| Checkpoint | Capability boundary | Authoritative result | Gate after ADMIN-GOV-01 |
|---|---|---|---|
| ADMIN-SHELL-01 | Task overview, permission-aware navigation, federated safe search and audit drill-down | Read models only; no new domain authority | **Complete**. Panels/search return only domains the actor can already read |
| ADMIN-ORG-01 | Read-only current-organization registry, branches, cohorts, staff-only invitations/memberships/roles, derived trainer profiles and organization-bound session revocation | Membership, role, appointment and lifecycle commands | **Complete** under D-0132. Writable settings, global identity recovery and global credential reset remain outside tenant authority |
| ADMIN-LEARNER-01 | Learner search/detail, profile correction, enrollment/pin, cohort-derived trainers and lifecycle | Organization-owned learner/member/enrollment records; existing deterministic evidence is not rewritten | **Complete** under D-0134 and the bounded specification. Support-note writes remain excluded and blocked |
| ADMIN-CONTENT-01 | Program through item draft authoring, review, publication and rollback | Existing ADR-0004 revisions, appointments, reviews, publications and events | **Complete** under DR-0018 Option A / APP-0043 / D-0136, D-0137 and the bounded owner-workspace specification; no media-management or new permission authority |
| ADMIN-MEDIA-01 | Private upload/quarantine, metadata, rights evidence, usage, replacement and preview | Organization-owned media/revisions; private object lifecycle | Authorized in principle under ADR-0004/0013; APP-0044/D-0138 records owner-level rights only and DR-0019 must resolve exact scan/accessibility/object lifecycle before schema/runtime work |
| ADMIN-GROUPS-01 | Typed groups, scoped membership and external facilitated-session scheduling | Organization-owned group/session lifecycle; no learning evidence or attendance | Authorized. Meeting-link policy and exact operational retention must be fixed in the bounded slice |
| ADMIN-ACTIVITY-01 | Versioned activity templates, questions, answers, scheduling, assignment and aggregate reports | Released deterministic templates and assignments | Authorized. Correct-answer authority is human-authored/reviewed and never disclosed before submission |
| ADMIN-REVIEW-01 | Assignment/workshop submissions, read queues, rubric review, correction and learner feedback | Immutable submission plus append-only human decisions/corrections | Authorized. AI cannot decide or silently draft a committed result |
| ADMIN-COMMS-01 | Versioned templates and targeted notices with delivery/read history | Private communication and delivery evidence | **Blocked** until purpose, audience, opt-out, attachment, read-tracking, withdrawal and retention policy is accepted |
| ADMIN-CONVERSATION-01 | Moderated conversation-group membership, messages, reports and blocking | Private group content and append-only moderation evidence | **Blocked** until retention, moderation, reporting, blocking and trainer-access policy is accepted |
| ADMIN-CMS-01 | Separate in-app posts and public editorial articles | Organization-owned revision/publication records | Authorized. Only an immutable active editorial publication is public; learner/support content can never be promoted |
| ADMIN-ENT-01 | Plan catalogue and manual/externally reconciled entitlement lifecycle | Deterministic entitlement plus immutable event history | Manual/external evidence authorized. Checkout, provider/webhook, invoice/tax and spend paths stay blocked |
| ADMIN-CONFIG-01 | Typed organization settings and organization feature flags | Allowlisted versioned setting changes | Organization settings authorized. Generic key/value storage, secrets and platform-setting mutation stay blocked |
| ADMIN-ACCEPT-01 | Parity reconciliation and release evidence | Evidence only; no new business authority | Runs only after every earlier matrix row is delivered or has an approved exclusion |

## Platform capability matrix

The existing `PlatformCapability` cases remain the complete platform-operation
allowlist at this gate.

| Capability | Scope | Operator-console authority |
|---|---|---|
| `foundation.tenant.bootstrap` | Test-only foundation membership fixture | None |
| `authentication.session.lifecycle` | Internal global authentication-session expiry/revocation audit | None; not a tenant-admin bypass |
| `authentication.mobile_credential.lifecycle` | Internal mobile-family rotation/revocation audit | None; not a tenant-admin bypass |
| `authentication.privileged_mfa.lifecycle` | Internal factor/challenge/recovery audit | Self-service MFA lifecycle only |
| `content.emergency-suspend` | Named, targeted platform legal/rights/safety/security intervention | Separate audited platform operation with target organization and fresh MFA; no edit/publish authority |

No ADMIN role or permission grants a platform capability. Platform-wide
configuration, identity recovery, tenant impersonation, bulk cross-tenant
reporting and public-editorial override have no capability or route. Adding one
requires a separate accepted security/privacy/tenancy decision and a named
reason/audit contract.

## Permission matrix

### Existing permission catalogue

The following 25 keys are already seeded and remain unchanged.

| Domain | Existing keys |
|---|---|
| Membership and organization | `cohorts.manage`, `cohorts.read`, `enrollments.manage`, `enrollments.read`, `institution_invites.manage`, `memberships.manage`, `memberships.read`, `roles.manage` |
| Curriculum and media governance | `curriculum.author`, `curriculum.translate.kn`, `curriculum.review.en`, `curriculum.review.kn`, `curriculum.publish`, `curriculum.share.manage`, `curriculum.shared.read`, `curriculum.shared.clone`, `enrollments.curriculum.assign`, `curriculum.authority.manage`, `media.rights.approve`, `curriculum.suspend` |
| Learner | `learning.participate` |
| Operator read/support/privacy | `audit.read`, `support.diagnostics.read`, `privacy.export.request`, `privacy.erasure.request` |

`curriculum.review.*` and `media.rights.approve` are insufficient without the
applicable valid authority appointment. `curriculum.publish` cannot create or
waive review or rights evidence. `reports.export` below is always conjunctive
with the domain read permission and never grants source access alone.

### Reserved future permission keys

| First checkpoint allowed to seed | Reserved keys | Exact authority boundary |
|---|---|---|
| ADMIN-SHELL-01 | `reports.export` | Export only a separately authorized, allowlisted read model; never source access by itself |
| ADMIN-ORG-01 | Implemented: `organization.settings.read`, `branches.read`, `branches.manage`, `trainer_profiles.read`, `trainer_profiles.manage`, `membership_sessions.revoke`; retained reservation: `organization.settings.manage` | Current organization only. Writable settings remain unseeded. Session revocation is limited to credentials currently selected for the target membership; it is not global recovery/reset authority |
| ADMIN-LEARNER-01 | Implemented: `learners.read`, `learners.profile.correct`, `learners.lifecycle.manage`; retained reservations: `support.notes.read`, `support.notes.write`, `support.notes.redact` | Current organization learner membership only. Support-note keys remain unseeded until the note policy gate passes |
| ADMIN-CONTENT-01 | None | Reuse the accepted ADR-0004 curriculum catalogue; do not create a broad `content.admin` key |
| ADMIN-MEDIA-01 | `media.library.read`, `media.upload`, `media.quarantine.review`, `media.metadata.manage`, `media.replace`, `media.retire` | Owner organization private media only. Rights approval remains a separate existing permission plus appointment |
| ADMIN-GROUPS-01 | `groups.read`, `groups.manage`, `groups.memberships.manage`, `facilitated_sessions.read`, `facilitated_sessions.manage`, `facilitated_sessions.reminders.manage` | Current organization only; group membership grants no learner/content/recording access by implication |
| ADMIN-ACTIVITY-01 | `activities.read`, `activities.author`, `activities.publish`, `activities.assign`, `activities.reports.read`, `activities.reports.export` | Organization-owned template/release/assignment operations; export also requires the matching read permission |
| ADMIN-REVIEW-01 | `assignments.read`, `assignments.manage`, `assignments.assign`, `assignment_submissions.read`, `assignment_reviews.read`, `assignment_reviews.record`, `assignment_reviews.correct` | Current organization submissions only; committed human decisions and corrections are append-only |
| ADMIN-COMMS-01 | `notices.read`, `notices.author`, `notices.publish`, `notices.withdraw`, `notices.delivery.read`, `message_templates.read`, `message_templates.author`, `message_templates.publish` | Reserved but **blocked from seeding** until the communication policy decision |
| ADMIN-CONVERSATION-01 | `conversation_groups.read`, `conversation_groups.manage`, `group_messages.read`, `group_messages.post`, `group_messages.moderate`, `group_moderation.read` | Reserved but **blocked from seeding** until the moderation/retention policy decision |
| ADMIN-CMS-01 | `posts.read`, `posts.author`, `posts.publish`, `posts.retire`, `editorial.read`, `editorial.author`, `editorial.review`, `editorial.publish`, `editorial.retire` | Owner-organization revisions only; public visibility comes from an active publication, not this permission alone |
| ADMIN-ENT-01 | `entitlement_plans.read`, `entitlement_plans.manage`, `entitlements.read`, `entitlements.grant`, `entitlements.adjust`, `entitlements.revoke` | Current organization deterministic manual/external evidence only; no payment/provider authority |
| ADMIN-CONFIG-01 | `organization.feature_flags.read`, `organization.feature_flags.manage` | Typed allowlisted organization flags only. No secret or platform-setting authority |

Later role-pack proposals must be documented separately. They may bundle these
keys for setup/navigation but cannot become policy shortcuts, wildcard grants
or named-role checks in domain code.

## Ownership matrix

| Resource family | Ownership | Required enforcement | Cross-organization/public rule |
|---|---|---|---|
| Organization registry | Platform-global registry only | Explicit platform creation path; organization records are never a tenant bypass | No ordinary operator cross-tenant list |
| Users, authentication identities/sessions/token families/MFA | Platform-global | Identity and credential policy from ADR-0002; organization authorization follows authentication | Tenant operators receive only purpose-minimized membership/session projections |
| Permission definitions | Platform-global stable catalogue | Key uniqueness; no role or tenant ownership on definitions | Grants remain organization-owned |
| Memberships, roles, grants, invites, branches, settings, trainer profiles, cohorts and enrollments | Organization-owned | Non-null tenant, tenant-leading constraints, membership-derived context | No implied access from the user's membership in another organization |
| Learner profiles, goals, support notes and lifecycle requests | Organization-owned and membership-linked | Composite membership/tenant relationship; target remains existence-hidden | Global identity is not duplicated or silently deleted |
| Curriculum, revisions, reviews, appointments, publications, shares and media | Immutable owner organization | ADR-0004 owner/reviewer/rights predicates and successor revisions | Only explicit immutable-publication grants; recipient cannot edit owner records |
| Typed groups, memberships, facilitated sessions and reminders | Organization-owned | Explicit type/schema/permission and tenant relationships | Membership grants no curriculum, learner-record, attendance or recording authority |
| Activities, releases, assignments and targeting | Organization-owned | Immutable release and exact audience/enrollment relationships | Cross-tenant assignment prohibited |
| Assignment submissions and human review decisions | Organization-owned, learner-membership-linked evidence | Submission immutable after submit; review corrections append-only | No cross-tenant reviewer access; AI has no write authority |
| Notices, templates, deliveries, conversation groups/messages and moderation | Organization-owned private content | No schema until retained policies pass; content-free audit/outbox | No open peer-to-peer or cross-tenant audience |
| In-app posts | Organization-owned governed content | Audience-scoped immutable publication | Never public merely because published in-app |
| Public editorial articles/revisions | Organization-owned governed content | Immutable publication, publisher permission, rights and accessibility | Public route exposes only active publication and immutable owner attribution; public visibility does not make the source platform-global |
| Entitlement plans, learner entitlements and events | Organization-owned | Exact membership, plan, effective period, source and immutable events | An external payment reference is evidence, not provider or cross-tenant authority |
| Organization settings/feature flags | Organization-owned typed configuration | Allowlist, type/schema, version, concurrency and audit | Platform settings and secrets are excluded |
| Audit/outbox/processed events | Scope follows the authoritative action | Organization ID explicit for tenant work; minimized safe payload | Workers reconstruct context; no raw private content |
| Dashboard/search/report/export projections | Derived, non-authoritative | Recompute from authorized sources; bounded caches/jobs | Each row/filter/export re-applies source permission and tenant scope |

## Lifecycle and retention matrix

Exact time values must be centralized policy/configuration, visible in the
bounded specification and approved before production. A missing value fails
closed; it is never an invitation to retain indefinitely.

| Data class | Lifecycle and ordinary deletion rule | Retention/disposal freeze |
|---|---|---|
| Authentication and MFA | Expire/revoke under ADR-0002; secret material is never operator-readable | Existing security lifetimes control; tenant ADMIN cannot extend them |
| Offline records | Learner-controlled purge, authorization lease and encrypted partition under ADR-0006 | Existing 24-hour lease and 30-day maxima remain unchanged |
| Voice/audio/transcripts | Consent withdrawal and durable deletion under ADR-0007 | Existing class-specific one-hour/24-hour/30-day/one-year maxima remain unchanged |
| Membership/role/invite/cohort/trainer relationships | Deactivate, revoke, expire or withdraw; preserve authority history | Bounded slice must declare operational-history period and privacy outcome before adding a new personal field |
| Curriculum/media/publication | Draft successor, withdraw review/rights, retire/suspend publication; never mutate released history | Retain while referenced by publications, grants, enrollments, rights or audit; disposal requires a separately reviewed migration |
| Learner profile/enrollment | Correct through attributed change; withdraw lifecycle; do not rewrite immutable pins/evidence | Existing privacy inventory controls erasure/minimization; new fields require inventory update in the same slice |
| Support notes | Redact through attributed event; no generic delete | **Blocked:** purpose, author/learner access, exact retention, legal hold and erasure behavior must be accepted before schema/write routes |
| Group membership and facilitated sessions | Withdraw/cancel/complete; external link is operational data, not attendance/evidence | ADMIN-GROUPS-01 must set link/reminder expiry and history retention before schema; no recording retention is authorized |
| Activity templates/releases | Successor revision and retire; released questions/answers immutable | Follow governed-content retention and preserve exact releases used by evidence |
| Assignment submission and human review | Submitted evidence immutable; corrections/appeals append-only | Follow learning-evidence/privacy inventory; production archival period and correction access must be explicit in ADMIN-REVIEW-01 |
| Notice/message template/delivery content | Withdraw future delivery; preserve minimized delivery evidence | **Blocked:** ADMIN-COMMS policy must fix bodies, attachments, acknowledgements/read state, opt-out and deletion retention |
| Group messages/moderation | Moderation action is append-only; body handling not yet authorized | **Blocked:** ADMIN-CONVERSATION policy must fix message/report/block/trainer access and deletion retention |
| In-app posts/editorial | Successor revision, retire/rollback; invalidate public caches | Preserve publication history and rights evidence; draft/private fields remain subject to privacy/rights disposal policy |
| Entitlement and adjustment events | Revoke/adjust through immutable successor events; never hard-delete financial evidence | Legal/tax/invoice retention remains a later decision; provider-backed work stays blocked |
| Organization settings/feature flags | Versioned allowlisted successor; secret values prohibited | Retain safe change history under audit policy; removed values cannot survive in logs/outbox payloads |
| Read models/search caches/export artifacts | Non-authoritative and reproducible | Short bounded cache/job expiry fixed per slice; export delivery must be encrypted and explicitly purged |
| Privileged audit | Append-only, content-minimized | Ordinary operators cannot delete; production retention/legal overlay remains separately reviewed |

## Route policy profiles

All paths below are relative to the OpenAPI `/api/v1` server unless marked
public web. Reserving a prefix does not register a route.

| Profile | Minimum contract |
|---|---|
| `O-READ` | `GET/HEAD`; `auth.first-party` → `auth.web-only` → `organization.context`; exact read permission; bounded cursor/filter allowlist; private `no-store`; existence-hidden denial |
| `O-COMMAND` | `POST` command subresource, or `PATCH` only for an explicitly mutable draft/typed setting; all `O-READ` guards plus `auth.mfa`; `Idempotency-Key`; expected version/fingerprint; transaction; safe audit; outbox when needed |
| `O-BULK` | Separate `O-READ` preview followed by one `O-COMMAND` with immutable target fingerprint, bounded item count and per-item safe result; no guessed “all” scope |
| `O-EXPORT` | `O-COMMAND` job request plus `O-READ` status/download; source read + `reports.export` or domain export permission; allowlisted fields; encrypted bounded artifact; purge policy |
| `PUBLIC-READ` | `GET/HEAD`; active immutable editorial publication only; no tenant/private joins; rights/accessibility checks; safe cache validators and retirement invalidation |
| `PLATFORM-COMMAND` | No generic HTTP profile. Requires a separately named `PlatformCapability`, target scope, reason, fresh MFA and platform audit before context |
| `BLOCKED` | No route, controller, schema, seed, fake-success response or feature flag until the retained decision is accepted |

Every operator lifecycle change uses an explicit command subresource. Generic
operator `DELETE` routes are prohibited. Read routes do not require MFA by
default, but a later bounded slice may add it for especially sensitive output.

## Route allocation matrix

| Checkpoint | Reserved route families | Profiles and constraints |
|---|---|---|
| ADMIN-SHELL-01 | `/operator/overview`, `/operator/search`, existing `/operator/audit-events` | `O-READ`; each panel/result checks its underlying domain permission; no broad search permission |
| ADMIN-ORG-01 | `/operator/organization`, `/operator/branches`, `/operator/cohorts`, `/operator/memberships`, `/operator/institution-invites`, `/operator/roles`, `/operator/trainer-profiles`, `/operator/membership-sessions` | `O-READ` and `O-COMMAND`; no global-user edit/recovery route |
| ADMIN-LEARNER-01 | Implemented: `/operator/learners`, `/operator/enrollments`, `/operator/learner-invitations`; retained reservation: `/operator/support-notes` | `O-READ` and `O-COMMAND`; no learner export. Support-note family remains `BLOCKED` until policy |
| ADMIN-CONTENT-01 | Existing `/operator/content` | `O-READ`/`O-COMMAND`; successor revisions, review appointments and publication gates |
| ADMIN-MEDIA-01 | `/operator/media` | `O-READ`/`O-COMMAND`; upload quarantine and private preview only |
| ADMIN-GROUPS-01 | `/operator/groups`, `/operator/facilitated-sessions` | `O-READ`/`O-COMMAND`; no attendance, recording or conferencing route |
| ADMIN-ACTIVITY-01 | `/operator/activities`, `/operator/activity-reports` | `O-READ`, `O-COMMAND`, `O-EXPORT`; no answer disclosure before submission |
| ADMIN-REVIEW-01 | `/operator/assignments`, `/operator/assignment-reviews` | `O-READ`/`O-COMMAND`; human reviewer authority only |
| ADMIN-COMMS-01 | `/operator/notices`, `/operator/message-templates` | `BLOCKED` until communication policy; then `O-READ`/`O-COMMAND`/`O-BULK` |
| ADMIN-CONVERSATION-01 | `/operator/conversation-groups`, `/operator/group-messages`, `/operator/group-moderation` | `BLOCKED` until moderation/retention policy |
| ADMIN-CMS-01 | `/operator/posts`, `/operator/editorial`; public `/editorial/articles/{slug}` | Operator `O-READ`/`O-COMMAND`; public endpoint `PUBLIC-READ` only |
| ADMIN-ENT-01 | `/operator/entitlement-plans`, `/operator/entitlements` | `O-READ`/`O-COMMAND`; no checkout/provider/webhook route |
| ADMIN-CONFIG-01 | `/operator/settings`, `/operator/feature-flags` | `O-READ`/`O-COMMAND`; organization typed allowlist only; platform configuration `BLOCKED` |

Exact resource endpoints within a reserved family are finalized in that
checkpoint's OpenAPI change. Moving a capability to another family, adding a
public path or weakening its profile requires a matrix update first.

## Existing operator route conformance baseline

The repository currently registers 15 operator operations across 14 OpenAPI
paths. Every row already carries the common `O-READ` web/tenant middleware;
the mutation rows additionally carry fresh MFA and header-only idempotency.

| Method and path | Atomic authority | Profile |
|---|---|---|
| `GET /operator/overview` | Any accepted content-read permission, `audit.read` or `support.diagnostics.read`; returned entries are independently derived | `O-READ`; permission names and unavailable entries omitted |
| `GET /operator/search` | Each fixed source independently requires its existing content-read, `audit.read` or `support.diagnostics.read` authority | `O-READ`; limit 25; encrypted tenant/query/source-bound cursor; safe descriptor allowlist only |
| `GET /operator/content/drafts` | Any accepted curriculum author/translator/reviewer/publisher read permission | `O-READ` |
| `GET /operator/content/publications` | Any accepted curriculum author/translator/reviewer/publisher read permission | `O-READ` |
| `POST /operator/content/reviews/english` | `curriculum.review.en` plus valid English appointment | `O-COMMAND` |
| `POST /operator/content/reviews/kannada` | `curriculum.review.kn` plus valid Kannada appointment | `O-COMMAND` |
| `POST /operator/content/publications` | `curriculum.publish` plus current review/rights predicates | `O-COMMAND` |
| `POST /operator/content/rollbacks` | `curriculum.publish` plus current target predicates | `O-COMMAND` |
| `GET /operator/audit-events` | `audit.read` | `O-READ`; encrypted tenant-bound `(occurred_at, id)` keyset cursor |
| `GET /operator/pilot-analytics` | `audit.read` or one accepted content-read permission | `O-READ` |
| `GET /operator/support/diagnostics` | `support.diagnostics.read` | `O-READ` |
| `POST /operator/privacy/exports` | `privacy.export.request` | `O-COMMAND` |
| `GET /operator/privacy/exports/{export_request_id}` | `privacy.export.request`, owning organization and request | `O-READ` |
| `POST /operator/privacy/erasures` | `privacy.erasure.request` | `O-COMMAND` |
| `GET /operator/privacy/erasures/{erasure_request_id}` | `privacy.erasure.request`, owning organization and request | `O-READ` |

`AdminGovernanceRoutePolicyTest` fails when an operator route loses first-party
web authentication, membership-derived organization context, fresh MFA on a
mutation or the explicit lifecycle-command rule.

## Retained decision gates

| Affected path | Required artifact before writes | Fail-closed state now |
|---|---|---|
| Support notes | Purpose, author/learner visibility, correction, legal hold, exact retention and privacy-erasure outcome | No support-note schema or write route |
| Facilitated-session links/reminders | URL allowlist, host visibility, cancellation, expiry, reminder delivery and retention | No session schema or route until ADMIN-GROUPS-01 fixes them |
| Notices/default messages | Purpose, audience, opt-out, attachments, acknowledgements/read tracking, withdrawal and retention | Entire ADMIN-COMMS write surface blocked |
| Conversation-group messages | Membership visibility, trainer access, reporting, blocking, moderation, attachments and retention/deletion | Entire ADMIN-CONVERSATION write surface blocked |
| Entitlement provider/payment | Authority, reconciliation, tax/invoice boundary, provider/webhook/secret and spend approval | Manual/external evidence only; no provider route or adapter |
| Platform configuration | Named capability, actor class, target scope, reason allowlist and audit contract | Organization settings only; no platform operator mutation |
| Legacy production data/media | Identity matching, tenant mapping, consent, licensing, reconciliation, rollback and operator approval | No import or reference-data copy |
| Production/public release | Independent review, staging, load, migration, backup/restore, accessibility and explicit release authority | Non-production implementation only |

## Handoff contract for later checkpoints

Before a later ADMIN checkpoint creates a table or route, its bounded
specification must:

1. cite the exact capability, permission, ownership, lifecycle and route rows;
2. resolve every blocked row it needs through the required accepted artifact;
3. inventory all new personal/private fields and their export, correction,
   retention, erasure, backup and log behavior;
4. implement only that checkpoint's vertical slice, including API, accessible
   UI, audit/outbox and failure behavior;
5. add same-tenant and cross-tenant read/list/create/update/lifecycle,
   relationship, route-binding, job and mass-assignment tests;
6. update OpenAPI/generated clients and this matrix when the exact implemented
   contract becomes narrower than the reserved family; and
7. complete the repository verification and checkpoint evidence before the
   next ADMIN checkpoint begins.

ADMIN-ORG-01, ADMIN-LEARNER-01 and ADMIN-CONTENT-01 have passed their
repository exit gates. The learner slice remains bounded by
[DR-0017](DR-0017_ADMIN_LEARNER_PROFILE_ENROLLMENT_LIFECYCLE_REQUEST.md)
Option A / APP-0042 / D-0134 and
[ADMIN-LEARNER-01_SPEC.md](ADMIN-LEARNER-01_SPEC.md). The completed content
slice remains bounded by
[DR-0018](DR-0018_ADMIN_CONTENT_AUTHORING_PUBLICATION_REQUEST.md) Option A /
APP-0043 / D-0136, D-0137 and
[ADMIN-CONTENT-01_SPEC.md](ADMIN-CONTENT-01_SPEC.md). Support-note
writes, communication, entitlement-provider, platform configuration, legacy
import and every still-later schema retain their exact blocked or ordered
state. ADMIN-MEDIA-01 has only the owner-level evidence in
[APP-0044](APPROVALS.md#app-0044) / D-0138; its runtime work remains paused at
[DR-0019](DR-0019_ADMIN_MEDIA_LIBRARY_RIGHTS_LIFECYCLE_REQUEST.md).

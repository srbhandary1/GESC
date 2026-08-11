# DR-0017 — Learner administration profile, enrollment and lifecycle request

**Status:** Resolved — Option A accepted under APP-0042 / D-0134

**Needed by:** ADMIN-LEARNER-01

**Authority:** Master plan §§8.2, 15, 16 and 18; ADR-0002; ADR-0003;
ADR-0004; ADR-0006; ADR-0007; ADR-0014; D-0126; D-0130; D-0132; D-0133

**Opened:** 9 August 2026

**Resolved:** 9 August 2026

## Problem

ADMIN-LEARNER-01 is the next authorized checkpoint, but the accepted
governance rows do not decide several security-, privacy- and
authoritative-record semantics needed before implementation:

- the current learner profile contains only immutable adult-confirmation and
  onboarding evidence plus one append-only selected goal, so “profile
  correction” cannot safely mean an ordinary row update;
- a tenant operator cannot be given a platform-global user directory, phone,
  email, admission identifier, identity correction or account/PIN/MFA reset by
  implication;
- existing learner invitations can attach a role and cohort, but the ADMIN-ORG
  issuer is intentionally staff-only and there is no accepted rule for which
  role a learner invitation may grant;
- enrollments have immutable curriculum pins but no optimistic version, no
  application-enforced one-current-enrollment invariant and no accepted
  operator rules for creation, cohort movement, withdrawal or successor
  enrollment;
- trainer assignment could mean a new direct learner/trainer relationship or
  the existing, safer enrollment → cohort → cohort-trainer derivation;
- suspending a learner membership could retain, remove or later revive role
  authority, and it must define selected-credential and offline-lease effects;
  and
- support-note purpose, visibility, correction, legal hold, retention and
  erasure remain explicitly blocked, while learner administration history and
  backup disposal need exact values.

Choosing these rules silently would change personal-data handling, tenant
authority, immutable learning evidence and foundation schema. The repository
working agreement therefore requires the affected runtime path to stop at this
decision request.

## Decision profiles

| Option | Boundary | Trade-off |
|---|---|---|
| **A — Minimal relationship-based learner administration (recommended)** | Existing/current-organization learner relationships only; safe code-only onboarding; goal-only attributed correction; versioned enrollment and membership lifecycle; cohort-derived trainers; no support notes or global identity controls | Delivers the ordered vertical slice without creating a tenant user directory or mutable learning evidence, but defers richer profile/support workflows |
| B — Broader learner/account administration | Add exact personal profile fields, direct trainer assignment, support records, identity/admission lookup, reset behavior or different lifecycle/retention supplied by the approver | May support more institution workflows, but each field and authority needs purpose, visibility, correction, erasure, legal-hold, retention and backup policy before implementation |
| C — Hold ADMIN-LEARNER-01 | Keep the completed ADMIN-ORG workspace and add no learner administration schema, permission, route or UI | Avoids the unresolved risks but pauses the ordered ADMIN program |

## Recommended Option A policy

Approval of Option A means all of the following.

### 1. Tenant and personal-data boundary

1. ADMIN-LEARNER-01 operates only on `learner` memberships in the active
   organization. Every target is resolved through the membership relationship;
   no request accepts a platform-global user identifier or organization ID.
2. Learner list/detail may return membership ID, nullable existing display
   name, membership lifecycle/version, onboarding/adult-confirmation state,
   current goal key, enrollment/cohort summaries, derived current trainers and
   aggregate activity times/counts. Display name remains a read-only global
   projection.
3. Phone, email, identity/admission hints or digests, other-organization
   relationships, passwords/PINs, MFA, session/token/install identifiers, IP,
   user agent, raw answers, marks by item, audio, transcripts, conversation
   bodies, memory facts and provider payloads are excluded.
4. No global identity search, correction, reassignment, recovery, reset,
   closure or hard deletion is added. Existing privacy export/erasure remains
   a separate fresh-MFA workflow under its existing permissions.
5. Support-note keys remain unseeded. No support-note table, read/write/redact
   route, fake-success response, UI control, export field, audit body or
   migration is added in ADMIN-LEARNER-01.

### 2. Permissions and default grants

1. Seed exactly three new used permissions:
   `learners.read`, `learners.profile.correct` and
   `learners.lifecycle.manage`.
2. Reuse `enrollments.read`, `enrollments.manage`,
   `enrollments.curriculum.assign`, `cohorts.read` and
   `trainer_profiles.read` for their existing atomic boundaries. A learner
   permission grants none of them by implication.
3. The Direct organization-administrator system role receives the three new
   learner permissions plus existing `enrollments.read` and
   `enrollments.manage`. The trainer and content-reviewer packs receive no new
   learner-data permission by default; organizations may delegate atomic keys
   through the existing actor-held permission ceiling.
4. `support.notes.*` and `reports.export` remain unseeded. ADMIN-LEARNER-01 adds
   no learner export beyond the separately authorized privacy inventory.

### 3. Learner invitation and learning authority

1. A learner operator may issue a code-only learner invitation. The command
   accepts exactly one same-tenant active learner-access role, an optional
   same-tenant active cohort and a 24, 72 or 168 hour expiry. It accepts no
   recipient address, name, user ID, password/PIN, organization ID, free-text
   note or arbitrary permission.
2. An eligible learner-access role has exactly one seeded permission:
   `learning.participate`. It contains no administrative, privacy, content,
   support or platform authority. Issuance and acceptance fail closed if the
   role ceases to be active or learner-safe.
3. The existing at-least-128-bit, keyed-digest, show-once invitation behavior
   is reused. Exact replay never reveals the code; pending/accepted/revoked/
   expired failure remains non-enumerating; terminal digest clearing remains
   within 60 minutes.
4. Acceptance creates or reuses only the same user's membership in the invite
   organization, attaches the exact learner-safe role and, when a cohort was
   selected, creates one `pending_curriculum` enrollment. It does not modify
   another organization membership or global identity/MFA state.
5. A role assigned to any learner membership may not be changed to contain a
   permission other than `learning.participate`. The existing role command
   must recheck learner assignments before replacing its permission set.

### 4. Profile correction

1. Adult confirmation and onboarding timestamps remain immutable. An
   organization operator cannot correct or manufacture adult-confirmation
   evidence.
2. The only ADMIN-LEARNER profile correction is the current goal key, selected
   from the existing governed goal catalogue. No free-text profile field,
   correction reason or private note is added.
3. Goal correction is an attributed, append-only event recording the prior
   and successor goal keys, target learner membership, actor membership and
   effective time. A versioned current projection may be maintained, but the
   original onboarding goal and correction history are never overwritten.
4. The corrected goal becomes visible through learner home and operator detail
   after commit. It changes no mark, mastery, review schedule, enrollment pin,
   attempt, entitlement or published content.
5. The command requires `learners.profile.correct`, fresh TOTP, expected
   version and idempotency. Same-value, stale, cross-tenant and changed-replay
   requests fail without another event or audit write.

### 5. Enrollment and cohort lifecycle

1. An active learner membership may have at most one current enrollment whose
   status is `pending_curriculum` or `active`. PostgreSQL and transactional
   application checks enforce this under concurrency. Completed or withdrawn
   historical enrollments may coexist.
2. `enrollments.manage` may create a `pending_curriculum` enrollment for an
   existing active learner membership, optionally in an active cohort. It may
   assign or change the cohort of a current enrollment with an expected
   version. The learner membership, organization, enrollment creation time and
   any curriculum pin remain immutable.
3. Trainer assignment is derived only through the enrollment's cohort and the
   existing `cohort_trainers` relationship. No direct learner-trainer table or
   implied access is added. Zero, one or several current cohort trainers may be
   shown as safe summaries to an authorized reader.
4. `enrollments.curriculum.assign` activates only a
   `pending_curriculum` enrollment against an exact currently verified owned
   or shared publication. The existing immutable owner/version/publication/
   share pin is retained, and activation advances the enrollment version.
5. `enrollments.manage` may withdraw a pending or active enrollment, setting
   its end time and advancing version. Completed and withdrawn enrollments are
   immutable to ordinary learner administration. No enrollment reactivation
   exists; renewed study uses a new successor enrollment and never repins the
   old row.
6. Enrollment create, cohort change, activation and withdrawal are separate
   explicit command subresources. There is no generic update or HTTP delete.

### 6. Learner membership lifecycle and credentials

1. `learners.lifecycle.manage` may suspend an active learner membership and
   reactivate a suspended learner membership. `ended` remains exclusive to the
   reviewed privacy/global closure path and cannot be reactivated here.
2. Suspension retains enrollment and immutable learning evidence. It retains
   only learner-safe role relationships, revokes every web session and Android
   family still selected for the target membership, advances membership
   version and immediately blocks online learning/synchronization.
3. Reactivation validates under lock that every retained role is active and
   contains exactly `learning.participate`, then changes only membership status
   and version. It restores no credential and creates no new enrollment,
   curriculum pin, attempt or evidence.
4. Any unexpected learner role containing broader permission is an incident
   state: suspension may still remove authority, but reactivation and role
   mutation fail closed until the relationship is remediated through an exact
   reviewed path.
5. ADR-0006 remains authoritative for disconnected devices. Suspension or
   withdrawal is enforced on every online request and synchronization; an
   already downloaded mission may remain locally readable only through its
   existing maximum 24-hour offline lease, after which access fails closed.
   No server API claims to remotely erase a disconnected partition.
6. The operator cannot reset the learner's phone, OTP, PIN, MFA, global
   sessions or unselected credentials. Other memberships of the same global
   user remain unchanged.

### 7. Consistency, history and failure behavior

1. Learner-administration mutations use one actor/request-bound content-free
   receipt namespace with header-only UUID/ULID idempotency, canonical
   fingerprints and safe replay results. Changed actor, target, body, version
   or relationship state conflicts.
2. Commands lock the per-organization command mutex, actor membership and
   authority graph, then learner membership, current enrollment/profile state,
   related cohort/publication and selected credentials in deterministic order.
   They reauthorize after locks and use real PostgreSQL concurrency tests.
3. Expected integer versions are mandatory for profile, membership and
   enrollment changes. There is no last-write-wins path. Invitation
   accept-versus-revoke, one-current-enrollment creation, cohort change versus
   withdrawal/pinning and selection-switch versus suspension each have one
   safe winner or a safely compatible result.
4. Safe learner-administration relationship/lifecycle events contain opaque
   tenant IDs, fixed event/reason keys and times only. They contain no display
   name, identity value, search term, request body, note, goal free text,
   credential ID or learning evidence body.
5. API failure details and logs never expose framework/database messages,
   invite codes/digests, personal values or foreign-tenant existence.

### 8. Retention, privacy and backups

1. Content-free learner-administration command receipts and unreferenced
   operational lifecycle events have the existing exact 365-day live-history
   maximum. The centralized ADMIN maintenance path may purge them after that
   period.
2. Memberships, enrollments and immutable curriculum pins retain the existing
   privacy-inventory outcomes. Pending/active enrollments become withdrawn
   during approved privacy erasure; completed/withdrawn rows and their pins
   remain authoritative evidence.
3. Original onboarding/adult-confirmation evidence and attributed goal
   correction history follow the existing append-only learner-profile
   disposition. They are included in the privacy inventory and are not
   silently rewritten or omitted from export.
4. Learner invitation digests reuse the one-hour terminal disposal rule.
   Encrypted backups containing learner-administration operational state reuse
   the 30-day maximum age-out and current purge/revocation overlay requirement.
5. No legal hold is implemented. Qualified legal review and independent
   security, privacy and tenancy review remain pre-production gates; these
   repository values are not a compliance claim.

### 9. API and web experience

1. Reads are bounded, cursor-paginated, SQL tenant scoped and `no-store`.
   Search covers only normalized display name, membership ID and safe cohort
   labels; terms, results and cursors are not persisted or logged.
2. Every mutation is first-party Sanctum web only, fresh-TOTP protected,
   exact-permission checked, idempotent, versioned, transactional and safely
   audited. Passport bearers and recovery-code-only assertions cannot perform
   learner administration.
3. Add a manifest-gated `Learners` workspace only when the server exposes at
   least one learner panel. Server semantic actions, not role names or raw
   permissions, control every UI affordance.
4. Panels load/retry independently and provide accessible loading, empty,
   validation, confirmation, fresh-MFA, stale-conflict/reload and success
   states at 320 CSS pixels with visible focus and 44-pixel targets.
5. Learner invitation codes use the existing ephemeral show-once interaction.
   No learner data, filter, cursor or code enters localStorage, IndexedDB,
   Cache Storage, ordinary Capacitor preferences, URL/history or analytics.

## Required implementation consequence after approval

The approved bounded specification must translate this policy into:

- versioned learner-profile correction, enrollment and content-free command/
  event schema with same-tenant composite constraints and one-current-
  enrollment enforcement;
- learner-safe invitation/role validation, selected-credential revocation and
  existing immutable curriculum-pin integration;
- exact learner/enrollment permissions, organization-admin grants, bounded
  queries and explicit lifecycle commands;
- privacy-inventory, retention/restore and incident-runbook updates;
- OpenAPI 3.1.1, regenerated clients, six governed locale catalogues and the
  responsive Learners workspace; and
- same/cross-tenant, body/route binding, idempotency, stale-write, immutable
  evidence, role-safety, offline boundary and real PostgreSQL race tests plus
  full repository exit gates.

No schema, permission seed, route, UI control or runtime behavior is authorized
by this open request alone.

## Resolution requested

To accept the recommended profile, reply exactly:

```text
Approve DR-0017 Option A as written.
```

Alternatively, select Option C or describe exact changes to Option A. Any
Option B change must name the affected field or lifecycle, actor permission,
authoritative consumer, learner visibility, privacy/erasure outcome, live
retention and backup disposal.

## Resolution

Option A was accepted as written on 9 August 2026 under
[APP-0042](APPROVALS.md#app-0042) and [D-0134](DECISIONS.md). The exact
approved proposal, before its status, resolved date and this resolution
section were added, has SHA-256
`4e64e897daf66f0476d856baa8f8e0553c474903ef6bca9e952fd2f5c5185e58`.

ADMIN-LEARNER-01 may now publish its bounded active specification and
implement the accepted non-production vertical slice. This resolution does
not accept unbuilt schema, routes, UI or test results; authorize global
identity/account recovery, support notes, learner-data export, provider
delivery, deployment or production; or replace the retained independent-
review and legal gates.

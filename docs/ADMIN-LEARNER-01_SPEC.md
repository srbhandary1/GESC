# ADMIN-LEARNER-01 — bounded learner administration specification

**Status:** Repository implementation complete

**Checkpoint:** ADMIN-LEARNER-01 only

**Approved policy:** DR-0017 Option A / APP-0042 / D-0134

**Authority:** `ENTERPRISE_PRODUCT_MASTER_PLAN.md` §§8.2, 15, 16 and 18;
ADR-0002; ADR-0003; ADR-0004; ADR-0006; ADR-0007; ADR-0014; D-0126;
D-0130; D-0133; D-0134

**Prepared:** 9 August 2026

**Completed:** 9 August 2026 — `make verify`, `make e2e` and `make build` passed

**Production/public release:** Not authorized

## 1. Objective and exit boundary

Deliver one first-party web vertical slice for current-organization learner
relationships:

- safe learner directory and detail;
- code-only learner invitation and acceptance through an exact learner-safe
  role;
- attributed, append-only current-goal correction;
- versioned enrollment creation, cohort change, immutable curriculum
  activation and withdrawal;
- cohort-derived trainer summaries;
- reversible learner-membership suspension/reactivation with exact
  selected-credential revocation; and
- a responsive, manifest-gated Learners workspace with independent panel
  loading and conflict recovery.

The checkpoint is complete only when schema, commands, reads, API contract,
generated clients, six governed catalogues, web UI, privacy/retention records,
SQLite behavior tests, real PostgreSQL tenancy/concurrency tests, E2E and the
repository exit gates agree.

Support notes are not part of this slice. No support-note table, permission,
route, export field, placeholder success, migration or UI control may be
added.

## 2. Explicit exclusions

ADMIN-LEARNER-01 does not add:

- a platform-global user directory or a request parameter for global user ID;
- identity/admission lookup or correction, phone/email display, password/PIN/
  MFA reset, global session revocation, account closure or hard deletion;
- direct learner-to-trainer assignment;
- free-text learner profile fields, correction reasons or private notes;
- learner answer, item-mark, audio, transcript, conversation, memory-fact,
  provider-payload or device/token detail reads;
- mutation of adult-confirmation or onboarding-completion evidence;
- enrollment reactivation, curriculum repinning or ordinary mutation of
  completed/withdrawn enrollment history;
- a learner export or `reports.export`; or
- provider invitation delivery, payment/spend, Android operator mutations,
  deployment or production release.

## 3. Tenant, actor and transport boundary

1. Every route remains under `auth.first-party` → `auth.web-only` →
   `organization.context`. Every mutation additionally requires `auth.mfa`.
   Passport bearers and recovery-code-only assertions are denied.
2. The active membership-derived organization context is the only tenant
   source. Route identifiers are membership, enrollment or invitation ULIDs;
   bodies accept no organization ID or global user ID.
3. The actor must be an active trainer or organization-administrator
   membership. The target must be a `learner` membership in the same
   organization. Missing and foreign targets share the normal safe 404.
4. Every read and command response uses `Cache-Control: private, no-store`.
   Learner filters, cursors, results and activation codes are never logged,
   audited as content, added to analytics or persisted in browser storage.
5. Mutation bodies use strict allowlists. A UUID/ULID `Idempotency-Key` header,
   never a body field, is mandatory.

## 4. Permission contract and default grants

Seed exactly these new permission definitions:

| Permission | Authority |
|---|---|
| `learners.read` | Read the bounded current-organization learner projection |
| `learners.profile.correct` | Append a governed goal correction and advance its current projection |
| `learners.lifecycle.manage` | Issue/revoke learner invitations and suspend/reactivate learner memberships when the additional relationship permissions below are also held |

Reuse existing permissions without implication:

- `enrollments.read` for enrollment and cohort-pin history;
- `enrollments.manage` for create, cohort-change and withdrawal commands;
- `enrollments.curriculum.assign` for curriculum activation;
- `institution_invites.manage` for learner invitation issue/revocation;
- `roles.manage` for selecting an exact learner-safe role;
- `cohorts.read` for invitation/enrollment cohort choices; and
- `trainer_profiles.read` for derived trainer summaries.

The Direct `organization_admin` system role receives all three new learner
permissions plus `enrollments.read` and `enrollments.manage`. Trainer and
content-reviewer packs receive no new learner-data permission. Existing
`support.notes.*` and `reports.export` names remain documentation-only and
unseeded.

A learner invitation panel requires all of `learners.lifecycle.manage`,
`institution_invites.manage`, `roles.manage` and `cohorts.read`. A permission
to read learners grants no enrollment, trainer, invitation or export data by
implication.

## 5. Persistence and migration contract

One forward/reversible migration named for the checkpoint must make these
changes.

### 5.1 Current-goal projection

Create `learner_profile_current_goals` with:

- ULID `id` primary key;
- non-null `organization_id`, `learner_profile_id` and
  `learner_membership_id`;
- governed `goal_key`;
- positive integer `version`, initially `1`;
- `corrected_at` nullable for the original projection; and
- `created_at` / `updated_at`.

Enforce unique `(organization_id, id)`, one row for
`(organization_id, learner_membership_id)`, and a composite foreign key to the
exact learner profile `(organization_id, id, learner_membership_id)`. Backfill
one projection from every existing `learner_goals` row without modifying that
append-only source. New onboarding writes the original goal and current
projection in the same transaction.

### 5.2 Append-only correction history

Create `learner_goal_corrections` with:

- ULID `id`, organization/profile/learner-membership identity;
- non-null actor membership and administration command IDs;
- governed `previous_goal_key` and `successor_goal_key` that must differ; and
- equal `effective_at` / `created_at` timestamps.

Use composite same-tenant foreign keys to learner profile, actor membership
and `organization_administration_commands`. PostgreSQL rejects update/delete
through the existing append-only learning trigger. The table stores no name,
reason, request body or free text.

### 5.3 Versioned enrollments

Add positive integer `enrollments.version`, backfilled/defaulted to `1` and
guarded in the model. Create a PostgreSQL partial unique index on
`(organization_id, learner_membership_id)` for status in
`('pending_curriculum', 'active')`. The migration fails closed if pre-existing
rows violate the invariant; it never silently selects a winner.

The existing owner/version/publication/share pin fields, enrollment identity,
organization, learner membership and `enrolled_at` remain immutable.

### 5.4 Shared administration history

Continue using `organization_administration_commands` as the sole
learner-operator receipt namespace, with policy revision
`ADMIN-LEARNER-01-D-0134`. Extend
`organization_administration_events` with nullable same-tenant
`subject_learner_profile_id` and `subject_enrollment_id` relationships and the
exact event shapes required below. Existing ADMIN-ORG receipts/events remain
valid and retain their original fingerprint policy.

New event types are:

- `learner_goal.corrected`;
- `learner_enrollment.created`;
- `learner_enrollment.cohort_changed`;
- `learner_enrollment.curriculum_activated`;
- `learner_enrollment.withdrawn`;
- `learner_membership.suspended`;
- `learner_membership.reactivated`;
- `learner_membership.role_removed`; and
- `learner_invitation.issued` / `learner_invitation.revoked`.

Events contain only opaque tenant IDs, fixed event/reason keys and times. Goal
keys live only in the governed correction record, not generic audit/history.

## 6. Learner-safe role and invitation invariant

1. A learner-safe role is same-tenant, active and has exactly one seeded
   permission: `learning.participate`.
2. Learner invitation issue requires that role and, when present, one active
   same-tenant cohort at the exact supplied versions. It accepts only
   `role_id`, `expected_role_version`, nullable `cohort_id`, matching nullable
   `expected_cohort_version`, `expires_in_hours` in `24|72|168`, and
   `expected_version: 0`.
3. The existing at-least-128-bit random code, keyed digest, show-once response,
   non-enumerating terminal behavior, versioning and one-hour terminal digest
   disposal are reused. No recipient or free-text field is accepted.
4. Acceptance locks/rechecks organization, invite, role permissions and
   cohort. It creates/reuses only the accepting user's same-tenant active
   learner membership, attaches the exact role and optionally creates one
   version-1 `pending_curriculum` enrollment. The one-current-enrollment
   invariant resolves races; no second current enrollment is fabricated.
5. Before an existing role's permission set changes, role administration must
   detect any learner assignment under lock. Such a role may remain/update
   only with the exact singleton `learning.participate` set. It cannot be
   broadened, emptied or retired while assigned.

## 7. Read model and semantic actions

### 7.1 Learner list/detail

`GET /api/v1/operator/learners` requires `learners.read` and accepts only:

- `status=active|suspended|ended`;
- `goal_key` from the governed catalogue;
- optional active `cohort_id` only when `enrollments.read` is also held;
- trimmed `q` of 1–80 safe characters; and
- `limit` 1–25 plus an encrypted tenant/filter-bound keyset `cursor`.

Search matches normalized display name, membership ID and, only with
`enrollments.read`, current cohort key/name. SQL remains tenant constrained;
the term is not logged or persisted.

`GET /api/v1/operator/learners/{learner}` requires `learners.read`.
List/detail fields are limited to:

- membership ID, nullable display name, status, joined/ended time and version;
- profile ID when present, adult/onboarding booleans, current governed goal
  key and current-goal version; and
- aggregate learner-attempt count and latest attempt activity time.

They exclude user ID and every field listed in §2. Semantic
`allowed_actions` are emitted from current server authority/state:
`learner.goal_correct`, `learner.suspend`, `learner.reactivate`, and
`learner_enrollment.create`.

### 7.2 Enrollment history

`GET /api/v1/operator/learners/{learner}/enrollments` requires both
`learners.read` and `enrollments.read`, uses bounded encrypted keyset
continuation and returns:

- enrollment ID/status/version and enrolled/activated/ended times;
- safe cohort ID/key/name/status/version or null;
- immutable curriculum owner/version/publication/share-grant IDs or null; and
- `trainers: null` without `trainer_profiles.read`, otherwise safe current
  cohort-trainer membership ID and nullable display name summaries.

Actions are `learner_enrollment.change_cohort`,
`learner_enrollment.activate_curriculum` and
`learner_enrollment.withdraw` only when exact permission and state allow.

### 7.3 Learner invitations

`GET /api/v1/operator/learner-invitations` uses the four-permission panel
boundary in §4. It lists learner invitations only and includes bounded active
learner-safe role and active cohort choices. Invitation rows contain IDs,
safe role/cohort summaries, status/version and lifecycle times; code/digest,
issuer/revoker identity and accepted global user identity are absent.

## 8. Mutation API

All following routes are `POST`, strict, fresh-TOTP and no-store:

| Route | Permission | Body |
|---|---|---|
| `/operator/learner-invitations` | Four-permission invitation boundary | `role_id`, `expected_role_version`, nullable `cohort_id`, nullable `expected_cohort_version`, `expires_in_hours`, `expected_version: 0` |
| `/operator/learner-invitations/{invitation}/revocations` | `learners.lifecycle.manage` + `institution_invites.manage` | `expected_version` |
| `/operator/learners/{learner}/goal-corrections` | `learners.profile.correct` | `goal_key`, `expected_version` of current-goal projection |
| `/operator/learners/{learner}/enrollments` | `enrollments.manage` | `expected_membership_version`, `expected_version: 0`, nullable `cohort_id`, nullable `expected_cohort_version` |
| `/operator/enrollments/{enrollment}/cohort-changes` | `enrollments.manage` | `expected_version`, nullable `cohort_id`, nullable `expected_cohort_version` |
| `/operator/enrollments/{enrollment}/curriculum-activations` | `enrollments.curriculum.assign` | `publication_id`, nullable `share_grant_id`, `expected_version` |
| `/operator/enrollments/{enrollment}/withdrawals` | `enrollments.manage` | `expected_version` |
| `/operator/learners/{learner}/suspensions` | `learners.lifecycle.manage` | `expected_version` |
| `/operator/learners/{learner}/reactivations` | `learners.lifecycle.manage` | `expected_version` |

First invitation/enrollment creation returns HTTP 201; replay returns 200.
Other successful commands return 200. Every response contains the bounded
current projection plus `replayed`; first invitation issue alone may include
`activation_code`, which is null on replay.

Stable conflicts use `problem.learner_administration.stale_state`,
`problem.learner_administration.idempotency_conflict`,
`problem.learner_administration.active_dependency`, or
`problem.learner_administration.unsafe_role`. Foreign/missing/unauthorized
relationships remain safe not-found/forbidden according to the existing
global problem contract; raw database/framework detail is never returned.

## 9. Command behavior

### 9.1 Goal correction

- Require an active learner membership and an existing completed profile/
  current-goal projection at the expected version.
- Reject a same-value correction.
- Insert exactly one correction record, update only the current projection to
  the successor goal/version+1, record safe history/audit and complete one
  receipt transactionally.
- Original `learner_profiles` and `learner_goals` rows remain byte-for-byte
  unchanged. Learner home and mission goal resolution read the current
  projection after commit.

### 9.2 Enrollment lifecycle

- Create only for an active learner membership with no current enrollment;
  status is `pending_curriculum`, version `1`, and the optional cohort must be
  current/active at its expected version.
- Cohort change applies only to pending/active enrollment, changes only
  `cohort_id`, advances version and may explicitly clear the cohort. A same-
  value change conflicts.
- Curriculum activation applies only to an unpinned `pending_curriculum`
  enrollment at expected version. It reuses the existing verified owned/shared
  publication and immutable pin logic, sets `active`/`activated_at`, and
  advances version once. ADMIN activation uses the shared administration
  receipt instead of creating a second operator receipt.
- Withdrawal applies only to pending/active enrollment, sets status
  `withdrawn`, `ended_at=now`, advances version and never clears a pin.
  Completed/withdrawn rows reject all ordinary commands. Renewal is a new row.

### 9.3 Learner membership lifecycle

- Suspension accepts only active learner membership/version. Under lock it
  keeps active exact learner-safe roles, removes any unexpected broader/
  inactive role relationship as incident containment, retains enrollments and
  evidence, revokes every still-selected web session/Android family, changes
  membership status to `suspended` and advances version once.
- Reactivation accepts only suspended membership/version, requires at least
  one retained active exact learner-safe role, changes only status/version and
  restores no credential, role, enrollment, pin or evidence.
- `ended` is never changed here. Online learner authority and synchronization
  continue to require an active membership. ADR-0006's already-issued maximum
  24-hour offline lease remains the honest disconnected-device boundary.

## 10. Transactions, locks and replay

1. Every operator command claims one organization-scoped actor-bound receipt,
   then locks in this order: active organization mutex; actor membership and
   current permission graph; target learner membership; current profile or
   enrollment; related role/cohort/publication; selected credentials.
2. Permission and relationship predicates are re-evaluated after locks.
   Invitation acceptance uses the compatible user → organization → invite →
   learner role/cohort/membership/enrollment order and rechecks terminal state.
3. Fingerprints include policy revision, operation, target, actor binding,
   every body value and expected version. Changed replay conflicts; exact
   replay returns the safe current result and never recovers an invitation
   code.
4. PostgreSQL unique/check/foreign-key failures are mapped to safe conflicts.
   Real process-level tests must force lock-queue overlap for invitation
   accept/revoke, two current-enrollment creates, goal correction races,
   cohort change versus withdrawal/activation, and credential selection
   versus suspension.

## 11. Web experience and accessibility

The server manifest adds `navigation: learners` only when at least one of
these panels is authorized:

- `learner_directory` for `learners.read`;
- `learner_enrollments` for `learners.read` + `enrollments.read`; and
- `learner_invitations` for the four-permission boundary in §4.

The responsive Learners workspace:

- loads and retries directory, enrollment and invitation panels
  independently;
- provides explicit loading, empty, validation, success, show-once-code,
  confirmation, fresh-MFA, forbidden and stale-conflict/reload states;
- uses server `allowed_actions`, never role names/raw permissions;
- restores focus after modal/confirmation dismissal, announces asynchronous
  changes, retains visible focus, supports keyboard operation and 44-pixel
  targets, and has no horizontal overflow at 320 CSS pixels; and
- keeps all filters, cursors, learner projections and invitation codes in
  component memory only. Copying a code is an explicit user action with an
  honest clipboard warning.

All new source keys exist in `en-IN`, `kn-IN`, `ml-IN`, `ta-IN`, `te-IN` and
`hi-IN`. The five non-English catalogues remain source-linked implementation
previews with the established approved-English production fallback until
independent language review.

## 12. Privacy, retention and operational behavior

- Update `PRIVACY_DATA_INVENTORY.md` for current-goal projection/corrections
  and shared administration events. Export includes original and correction
  history; erasure retains them under the existing append-only onboarding
  evidence outcome.
- Pending/active enrollment privacy erasure continues to withdraw the row and
  retain immutable pins. The erasure path must advance enrollment version.
- Shared command/event history uses the existing exact 365-day live ceiling
  and bounded hourly purge. Referenced correction events remain until their
  authoritative correction dependency permits deletion.
- Learner invitation terminal digests use the existing one-hour cleanup;
  encrypted backups reuse the 30-day maximum age-out and current tombstone/
  revocation-overlay requirement.
- No legal hold is implemented. Qualified legal and independent security,
  privacy, tenancy, accessibility and language review remain pre-production
  gates, not claims made by repository tests.

## 13. Required tests and exit commands

At minimum, add or update tests for:

- migration up/down, backfill, constraints, one-current-enrollment and
  append-only correction protection;
- permission catalogue/default grants and absence of support/export keys;
- route middleware, strict bodies, safe problem mapping and Passport denial;
- same/cross-tenant list/detail/filter/cursor and relationship binding;
- no disclosure of global identity, credentials or learner content;
- exact learner-safe role issue/accept/update behavior and secret replay;
- goal correction history/projection and learner-home visibility;
- every enrollment transition, immutable pin/history and privacy withdrawal;
- learner suspension/reactivation, selected-credential scope and offline
  boundary;
- the real PostgreSQL races listed in §10; and
- manifest gating, independent panel retries, confirmation/focus, fresh MFA,
  conflict reload, show-once code, axe and 320-pixel reflow.

Run from repository root:

```text
make verify
make e2e
make build
```

`make ai-eval` is not required unless prompt, model, provider or orchestration
behavior changes. Update `STATUS.md`, `DECISIONS.md`, `QUALITY_REVIEW.md`, the
ADMIN parity/governance records, privacy/retention/restore documentation and
OpenAPI/generated types before closing the checkpoint. No later ADMIN slice
may begin opportunistically.

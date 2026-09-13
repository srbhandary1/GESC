# ADMIN-ORG-01 bounded specification

**Status:** Repository implementation complete under DR-0016 Option A /
APP-0041 / D-0132 / D-0133
**Checkpoint:** ADMIN-ORG-01 only
**Recorded:** 9 August 2026
**Production/public release:** Not authorized

## Authority

This specification implements only the ADMIN-ORG-01 rows in:

- `ENTERPRISE_PRODUCT_MASTER_PLAN.md` §§8.2, 11, 15, 16, 18 and 31;
- ADR-0002, ADR-0003, ADR-0004 and ADR-0014;
- `ADMIN_REFERENCE_PARITY_PLAN.md`;
- `ADMIN_GOVERNANCE_MATRIX.md`; and
- [DR-0016 Option A](DR-0016_ADMIN_ORG_AUTHORITY_LIFECYCLE_RETENTION_REQUEST.md).

Where this document is less detailed than an accepted authority, the accepted
authority controls. A material change to organization mutability, personal
fields, delegation, deactivation, credential scope, invitation replay or
retention requires a new decision request before implementation.

## Outcome and exclusions

ADMIN-ORG-01 delivers one current-organization People workspace covering:

- read-only organization registry metadata;
- branch and cohort administration;
- staff-only membership and code-only invitation administration;
- custom roles and membership role assignments under an actor-held delegation
  ceiling;
- derived trainer profiles, trainer/cohort relationships and existing
  curriculum-authority appointments;
- membership suspension/reactivation with no authority revival;
- count-only membership-selected web/mobile credential revocation; and
- content-minimized administration history and cleanup.

The checkpoint does **not** add writable organization settings, learner
administration, global identity correction/recovery, password or PIN reset,
MFA reset, provider-backed invitation delivery, trainer biography/contact/
qualification storage, new appointment kinds, generic configuration,
platform capability, data import, deployment or production behavior.

## Ownership and personal-data boundary

- `users`, authentication credentials, organizations and permission
  definitions remain platform-global.
- Branches, cohorts, memberships, roles, invitations, current relationship
  projections, administration commands and administration events are
  organization-owned.
- Every organization-owned write derives `organization_id` from the active
  context. No request body accepts or changes it.
- Staff responses exclude learner memberships and expose only membership ID,
  nullable existing global display name, membership type/status/timestamps,
  safe role summaries, version and semantic actions.
- Platform-global user/identity identifiers, identity hints/digests, phone,
  email, other-organization relationships, IP, user agent, session storage,
  token/install identifiers and MFA data are excluded.
- The existing display name is read-only. ADMIN-ORG never copies it into
  events, audit, outbox or retained snapshots.

## Permission and navigation contract

### Permission catalogue

Seed exactly these six new used permissions:

| Permission | Use |
|---|---|
| `organization.settings.read` | Read current organization safe metadata |
| `branches.read` | Read branches |
| `branches.manage` | Create, update and archive branches |
| `trainer_profiles.read` | Read derived trainer profiles and safe summaries |
| `trainer_profiles.manage` | Assign and revoke trainer/cohort relationships |
| `membership_sessions.revoke` | Read count-level selected credentials and revoke them |

Do not seed `organization.settings.manage`. Reuse existing:

- `cohorts.read`, `cohorts.manage`;
- `memberships.read`, `memberships.manage`;
- `institution_invites.manage`;
- `roles.manage`; and
- `curriculum.authority.manage` for existing appointment management.

The seeded Direct organization-admin system role receives the six new used
permissions plus the existing cohort, membership, invitation and role
permissions. The trainer system role receives no new ADMIN-ORG permission.
System roles remain HTTP-immutable. No named role is authorization.

### Shell manifest

`GET /operator/overview` adds:

- navigation key `people` when at least one ADMIN-ORG panel is available;
- panel keys `organization_settings`, `branches`, `cohorts`,
  `staff_memberships`, `institution_invites`, `roles`, `trainer_profiles`,
  `authority_appointments` and `membership_sessions`, each derived
  independently from its exact permission or self-appointment relation; and
- `search_available`, true only when at least one existing safe global-search
  source is authorized.

The manifest returns semantic keys, never raw permission names. The client
does not call global search when `search_available` is false.

### Privileged-session classification

Privilege is derived from current atomic grants. A user is privileged when any
active membership holds a seeded permission other than
`learning.participate`. Role keys and membership types are not inputs. This
preserves short privileged session policy and MFA enrollment for custom roles.

## Schema contract

One forward/rollback-safe ADMIN-ORG migration may compose the following exact
changes. PostgreSQL constraints are authoritative; SQLite-compatible model and
feature tests supplement them.

### `branches`

- `id` ULID primary key;
- non-null `organization_id` with restrictive organization FK;
- immutable `key` up to 100 characters;
- mutable `name` up to 160 characters;
- `status` in `active|archived`;
- integer `version`, initially `1`, positive;
- nullable `archived_at`, paired with archived status;
- UTC timestamps;
- tenant unique `(organization_id, id)` and `(organization_id, key)`; and
- tenant-leading status/name indexes.

### Existing aggregates

- `cohorts`: nullable same-tenant `branch_id`, positive integer `version`
  default `1`, nullable `archived_at` paired with archived status, and a
  tenant-leading branch/status index. Existing rows remain unbranched.
- `roles`: `status` in `active|retired`, positive integer `version` default
  `1`, nullable `retired_at` paired with retired status, plus tenant-leading
  lifecycle indexes. Existing roles start active.
- `memberships`: positive integer `version` default `1`. Existing membership
  type/status and one-user-per-organization identity remain unchanged.
- `institution_invites`: positive integer `version` default `1`, non-null
  same-tenant `issued_by_membership_id`, nullable same-tenant
  `revoked_by_membership_id`, nullable `revoked_at`, nullable `code_digest`
  after terminal transition, and nullable `code_discard_after`. Existing rows
  receive a reviewed migration attribution only where a real existing actor
  can be derived; otherwise issuer remains nullable for legacy rows and is
  non-null for every new operator-issued row. New issuance never writes role
  or cohort IDs.

`organization_id`, keys, membership type, system-role flag, invite type/digest
and relationship ownership are guarded against ordinary mass assignment.

### Web selected-membership authority

`authentication_sessions` gains nullable `selected_organization_id` and
`selected_membership_id` with:

- a paired-null check;
- composite FK `(selected_organization_id, user_id,
  selected_membership_id)` to membership identity
  `(organization_id, user_id, id)`;
- a selected-membership/revocation index; and
- protected model fields.

Explicit web selection, implicit exactly-one-active-membership resolution and
context clearing/switching synchronously update the authoritative row and the
Laravel session selection. A protected request cannot continue with divergent
selection state.

### `organization_administration_commands`

Content-free durable replay receipts contain:

- ULID ID and non-null organization;
- same-tenant actor membership;
- caller `idempotency_key`;
- fixed operation, target type and target ID;
- canonical SHA-256 request fingerprint;
- fixed result type and result ID;
- completion and expiry instants; and
- creation timestamp.

`(organization_id, idempotency_key)` is unique so another actor's reuse
conflicts. No user ID, request JSON, display label or secret is stored.

### `organization_administration_events`

Append-only content-minimized events contain:

- ULID ID, non-null organization and same-tenant actor membership;
- event type from a fixed ADMIN-ORG allowlist;
- nullable same-tenant subject membership, role, cohort, branch or invitation;
- nullable global permission ID;
- nullable command ID and fixed reason key;
- effective and creation instants.

Composite tenant FKs apply to every populated tenant relationship. A database
check requires an allowed event type and the target columns required for that
type. Events never store names, permission descriptions, qualification text,
request payloads, invite secrets/digests or credential identifiers.

Current `membership_roles`, `role_permissions` and `cohort_trainers` remain
the deterministic authorization/read projections. Assignment and revocation
update the projection and append exactly one event in the same transaction.

### `authentication_session_cleanup_requests`

Durable internal web-session cleanup work contains a ULID ID, unique
authentication-session FK, `pending|processing|completed` status, bounded
attempt count, availability/lease/completion instants and timestamps. It
contains no storage key, ciphertext or personal field. The worker resolves the
protected ciphertext from the already-revoked authentication-session row and
never exposes the request or credential identifier through API, audit or
outbox.

## Read API contract

All routes are relative to `/api/v1`, use
`auth.first-party` → `auth.web-only` → `organization.context`, return
`Cache-Control: no-store`, hide foreign/missing resources identically and use
encrypted tenant/purpose/filter-bound keyset cursors with a maximum page size
of 25.

| Route | Authority | Projection |
|---|---|---|
| `GET /operator/organization` | `organization.settings.read` | Current `id`, `name`, `kind`, `status`, `timezone`; read-only actions |
| `GET /operator/branches` | `branches.read` | ID, key, name, status, version, timestamps, semantic actions |
| `GET /operator/cohorts` | `cohorts.read` | ID, key, name, status, optional safe branch summary, version, actions |
| `GET /operator/memberships` | `memberships.read` | Staff-only safe projection and current safe role summaries |
| `GET /operator/institution-invites` | `institution_invites.manage` | Staff-only invite lifecycle; no code/digest/user ID; accepted membership only with membership-read authority |
| `GET /operator/roles` | `roles.manage` | Role metadata, current permission keys, `assignment_count`, version, actions, and actor-delegable permission catalogue |
| `GET /operator/trainer-profiles` | `trainer_profiles.read` | Derived trainer membership, roles, cohort summaries and safe appointment summaries |
| `GET /operator/trainer-profiles/{membership}/authority-appointments` | Manager permission or exact appointee | Safe list; qualification/evidence detail only for manager or exact appointee |
| `GET /operator/membership-sessions/{membership}` | `memberships.read` and `membership_sessions.revoke` | Count-only web/mobile activity, opaque state fingerprint, semantic actions |

Collection filters are closed allowlists:

- branches/cohorts/roles/invites: lifecycle status;
- cohorts: branch ID;
- memberships/trainers: membership status and exact normalized display-name
  prefix with no global-search persistence; and
- appointments: authority kind and current state.

Every collection returns `{data, next_cursor, allowed_actions}`. Collection
actions cover creation/issuance only; item actions remain target specific. The
organization and session-count resources return `{data}`.

Semantic action strings are fixed to:

- `branch.create|update|archive`;
- `cohort.create|update|archive`;
- `membership.assign_role|revoke_role|deactivate|reactivate`;
- `invitation.issue|revoke`;
- `role.create|update|retire`;
- `trainer.assign_cohort|revoke_cohort`;
- `appointment.create|accept|surrender|revoke`; and
- `membership_session.revoke`.

## Command API contract

Every command is an `O-COMMAND` POST subresource with fresh TOTP,
`Idempotency-Key`, strict body allowlist, transaction, inside-transaction
reauthorization, deterministic locks, safe audit and exactly one
administration event where specified. A recovery-code assertion is rejected.

All successful responses contain `{data, replayed}`. Creates return `201` on
first execution and `200` on exact replay; other commands return `200`.
Changed key reuse or stale state returns safe `409`; validation returns safe
`422`; authorization/foreign/missing resources remain existence hidden.
Create bodies carry `expected_version: 0`; updates carry the current positive
version or the exact opaque relationship/state fingerprint returned by the
corresponding read.

### Branch commands

| Route | Permission | Body and result |
|---|---|---|
| `POST /operator/branches` | `branches.manage` | `key`, `name`, `expected_version=0`; creates version 1 |
| `POST /operator/branches/{branch}/updates` | `branches.manage` | `name`, `expected_version`; increments version |
| `POST /operator/branches/{branch}/archivals` | `branches.manage` | `expected_version`; fails while an active cohort references it |

Branch key and ownership never change. Archived branches are not restored in
this checkpoint.

### Cohort commands

| Route | Permission | Body and result |
|---|---|---|
| `POST /operator/cohorts` | `cohorts.manage` | `key`, `name`, optional active same-tenant `branch_id`, `expected_version=0`; version 1 |
| `POST /operator/cohorts/{cohort}/updates` | `cohorts.manage` | `name`, optional `branch_id`, `expected_version`; increments version |
| `POST /operator/cohorts/{cohort}/archivals` | `cohorts.manage` | `expected_version`; fails while current trainer assignments or active/pending learner enrollments reference it |

Cohort key and ownership never change. No enrollment is changed. Archived
cohorts are not restored in this checkpoint.

### Invitation commands

| Route | Permission | Body and result |
|---|---|---|
| `POST /operator/institution-invites` | `institution_invites.manage` | `membership_type=trainer|organization_admin`, optional `expires_in_hours=24|72|168` default 72, `expected_version=0` |
| `POST /operator/institution-invites/{invite}/revocations` | `institution_invites.manage` | `expected_version`; pending only |

Issuance generates at least 128 random bits and stores only the keyed digest.
The first response is
`{data: invitation, activation_code: string, replayed: false}`. Exact replay is
`{data: same invitation, activation_code: null, replayed: true}`. No role,
cohort, recipient, password, organization or user field is accepted. Revoked,
accepted and expired states are immutable.

The existing invite-plus-OTP acceptance route remains enumeration safe. The
web authentication UI accepts an ephemeral invitation code and sends it only
with the OTP verification request; it never persists or places it in a URL.

### Role and membership-role commands

| Route | Permission | Body and result |
|---|---|---|
| `POST /operator/roles` | `roles.manage` | `key`, `name`, sorted unique `permission_keys`, `expected_version=0`; custom active role version 1 |
| `POST /operator/roles/{role}/updates` | `roles.manage` | `name`, complete sorted unique `permission_keys`, `expected_version`; increments version |
| `POST /operator/roles/{role}/retirements` | `roles.manage` | `expected_version`; custom and unassigned only |
| `POST /operator/memberships/{membership}/role-assignments` | `roles.manage` | `role_id`, `expected_membership_version`, `expected_role_version` |
| `POST /operator/memberships/{membership}/role-revocations` | `roles.manage` | same body |

The target must be active staff. System role mutation/retirement, self-role
assignment/revocation, editing a role assigned to the actor, out-of-ceiling
permission grants and retired-role assignment fail closed. Relationship
changes increment membership version.

Every role/grant/assignment transaction locks actor membership, actor role
grants, affected roles/permissions, target membership and all candidate final
manager authority in ascending stable ID order. The post-command graph must
retain at least one active membership holding both `roles.manage` and
`memberships.manage`.

### Staff lifecycle commands

| Route | Permission | Body and result |
|---|---|---|
| `POST /operator/memberships/{membership}/deactivations` | `memberships.manage` | `expected_version`; active staff only |
| `POST /operator/memberships/{membership}/reactivations` | `memberships.manage` | `expected_version`; suspended staff only |

The actor cannot target their own membership. Deactivation atomically:

1. locks/rechecks actor and target authority plus final-manager invariant;
2. changes only target membership to suspended and increments version;
3. removes all current membership roles and cohort-trainer relationships and
   appends revocation events;
4. appends non-retroactive revocation events for every pending/current
   curriculum-authority appointment; and
5. revokes every web session/mobile family still selected for the target.

It never changes the user, identity, MFA, other membership or credential with
another/no selection. Reactivation changes only suspended to active and
increments version. It restores no relation, appointment, credential or
authority.

### Trainer/cohort commands

| Route | Permission | Body and result |
|---|---|---|
| `POST /operator/trainer-profiles/{membership}/cohort-assignments` | `trainer_profiles.manage` | `cohort_id`, `expected_membership_version`, `expected_cohort_version` |
| `POST /operator/trainer-profiles/{membership}/cohort-revocations` | `trainer_profiles.manage` | same body |

Target membership must be an active trainer and cohort must be active. The
relationship change and event share one transaction and increment membership
version. No learner enrollment changes.

### Existing curriculum-authority appointment commands

These commands reuse `AuthorityAppointmentCommands` and existing immutable
events. No new kind or authority rule is introduced.

Exact-appointee accept/surrender is the narrow existing relationship-authority
exception to an atomic manager permission; it still requires active
membership-derived context and fresh TOTP. Every HTTP appointment command is
first claimed through the ADMIN-ORG organization-wide receipt and expected
state contract. The existing publication-command helper may remain an internal
compatibility receipt, but it is not sufficient as the HTTP idempotency or
conflict boundary by itself.

| Route | Authority | Body |
|---|---|---|
| `POST /operator/trainer-profiles/{membership}/authority-appointments` | `curriculum.authority.manage` | existing authority kind, `organization|program` scope, optional program, validity, qualification basis, approval evidence key, expected membership version |
| `POST /operator/trainer-profiles/{membership}/authority-appointments/{appointment}/acceptances` | Exact active appointee | appointment state fingerprint |
| `POST /operator/trainer-profiles/{membership}/authority-appointments/{appointment}/surrenders` | Exact active appointee | appointment state fingerprint |
| `POST /operator/trainer-profiles/{membership}/authority-appointments/{appointment}/revocations` | `curriculum.authority.manage` | `reason_key=operator_revoked`, appointment state fingerprint |

The server derives appointee user ID from the scoped membership. Appointment
detail is private; ordinary trainer lists expose no qualification narrative.
Deactivation revocation uses reason `membership_suspended` and does not
invalidate approvals valid before the effective instant.

### Membership-selected credential command

`POST /operator/membership-sessions/{membership}/revocations` requires
`membership_sessions.revoke` and body `state_fingerprint`. It rejects the
actor's own membership, locks target membership before credential rows and
rechecks selection.

The fingerprint covers sorted active web-session and mobile-family IDs without
exposing them. The command marks only still-selected credentials revoked,
revokes associated Passport tokens and returns safe revoked web/mobile counts.
Web storage cleanup is post-commit, retryable and driven by protected database
state; cleanup failure cannot restore authority and no storage identifier is
audited or placed in an outbox payload.

## Idempotency and failure semantics

- Fingerprints use canonical JSON over policy revision, operation, target,
  normalized allowlisted body and expected version/fingerprint.
- Replay looks up the organization-wide idempotency key under lock, verifies
  actor membership, operation, target and fingerprint, and returns the
  original result without another audit/event/write.
- A unique-race retry resolves through the same replay path.
- Commands reauthorize after locks; authority removed concurrently causes no
  write.
- Lock order is receipt claim → current organization row as the per-tenant
  command mutex → actor membership → actor authority graph → target aggregate
  → related aggregates in stable ID order → current relationship/credential
  rows → event/audit/receipt completion. The organization mutex makes the
  post-command final-manager graph deterministic across concurrent role and
  membership commands.
- API responses and logs never reflect database/framework exception text.

## Retention and scheduled maintenance

`config/operator.php` defines code-owned, non-operator-editable values:

- `organization_administration_history_days = 365`;
- `institution_invite_default_hours = 72`;
- `institution_invite_allowed_hours = [24, 72, 168]`; and
- `institution_invite_digest_discard_minutes = 60`.

An hourly scheduled command:

1. materializes pending invitation expiry under tenant-bounded leases;
2. marks terminal invite digests purgeable at the terminal transition and
   clears them on the hourly run, within the 60-minute maximum;
3. leases and completes durable revoked web-session storage cleanup requests;
4. purges expired command receipts and unreferenced ADMIN-ORG events after
   365 days; and
5. leaves minimum opaque anchors referenced by immutable learning,
   publication, review, rights or privacy evidence.

Maintenance output is aggregate/content-free. It never prints IDs, names,
codes, digests or personal fields. Existing privileged-audit retention is not
shortened. Encrypted backups containing this data must age out within 30 days,
and restore procedure must reapply revocation/purge tombstones before serving.

## Web experience

- Add `People` to the task navigation only when the server manifest includes
  an ADMIN-ORG panel.
- Keep organization metadata in Overview and render remaining resources in a
  separately maintained `OperatorOrganizationAdministration` People surface.
- Each resource loads and retries independently; one failed panel does not
  disable another.
- Use responsive cards/lists, not a wide table. All interaction reflows at 320
  CSS pixels, uses visible focus and at least 44-pixel targets.
- Every form has labelled inputs, linked validation, submitting/disabled,
  success, safe error, fresh-MFA and stale-conflict/reload states.
- Archive, revoke, retire and deactivate use explicit confirmation and restore
  focus after cancel/success.
- Invitation code is rendered left-to-right only after issuance, with explicit
  copy and “I saved the code” controls. It is not auto-copied or placed in an
  `aria-live` message. A replay explains that the invite exists but the secret
  is unrecoverable.
- No ADMIN-ORG data, filters, code, result or cursor enters localStorage,
  IndexedDB, Cache Storage, ordinary Capacitor preferences or analytics.

## Contract and localization

- Bump OpenAPI from 0.17.0 to the next repository version and update all paths,
  request/response/problem schemas, enums, cursor bounds, no-store semantics,
  `Idempotency-Key` headers and conflict responses.
- Regenerate the complete committed TypeScript contract bundle; do not edit it
  by hand.
- Add exact key parity to all six catalogues for People navigation, each
  panel/state/form, MFA guidance, conflict reload, confirmations, lifecycle
  success and one-time invitation warnings.
- Update localization governance hashes/revision honestly. Non-English draft
  strings remain pending independent review and production falls back visibly
  to approved English.

## Required tests

### PostgreSQL and backend

- same-tenant success plus cross-tenant read/list/create/update/lifecycle,
  relationship, route-binding and body ownership attacks;
- database rejection of foreign branch/cohort, event relationship and selected
  authentication-session pairs;
- role name grants nothing; equivalent actor-held atomic permission works;
- custom permission grants tighten privileged-session classification;
- actor delegation ceiling, self-role prohibition, immutable system role and
  concurrent final-manager preservation;
- exact replay creates one write/event/audit; changed actor/target/body/version
  conflicts;
- stale version produces one winner under concurrency;
- invitation issue/replay/lost-secret, accept-versus-revoke and digest cleanup;
- deactivation removes authority/appointments/relationships and only selected
  credentials, while reactivation restores none;
- selection-switch versus revocation and deactivation versus selection use the
  declared lock order;
- shared global user in organizations A/B retains B membership, credentials,
  roles, identity and MFA after A commands;
- Passport, unauthenticated, inactive context, missing permission, stale MFA
  and recovery-code assertion cause no write; and
- retention cleanup is tenant bounded, content free and leaves referenced
  immutable evidence.

### Web and E2E

- only manifest-authorized panels render and unauthorized APIs are not called;
- independent loading/empty/error/retry behavior and bounded cursor handling;
- cookie, CSRF, idempotency and strict body boundaries;
- safe handling of 403/fresh-MFA, 409 conflict and 422 validation;
- role-name independence, confirmations, focus restoration and no browser
  persistence;
- initial-only invitation code, acknowledgement hiding and replay warning;
- web OTP/invite activation path; and
- one Chromium operator-organization journey covering keyboard navigation,
  partial failure/retry, fresh TOTP then one authoritative mutation, conflict
  recovery, invitation handling, axe desktop/mobile and 320-pixel no-overflow.

## Verification and completion

Before ADMIN-ORG-01 can be marked complete:

1. focused SQLite, real PostgreSQL, frontend and Chromium suites pass;
2. migration up/down and non-production backup/restore drill pass;
3. `make verify`, `make e2e`, `make build` and `git diff --check` pass;
4. `make ai-eval` remains not applicable unless AI/provider behavior changes;
5. OpenAPI/generated clients and localization governance show no drift;
6. `docs/STATUS.md`, `docs/DECISIONS.md`, `docs/QUALITY_REVIEW.md`, privacy
   inventory and operations/retention documentation record exact evidence; and
7. known independent review, production backup/PITR, production volume,
   deployment and public-release gates remain explicit.

Only after those conditions may ADMIN-LEARNER-01 become the next checkpoint.

All repository conditions above passed on 9 August 2026. This completion is
limited to tested repository behavior and does not represent independent
review, production backup/PITR compliance, deployment or public-release
authorization. ADMIN-LEARNER-01 is now the only next checkpoint; support-note
writes retain their separate blocked policy gate.

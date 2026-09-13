# DR-0016 — Organization administration authority, lifecycle and retention request

**Status:** Resolved — Option A accepted under APP-0041 / D-0132
**Needed by:** ADMIN-ORG-01
**Authority:** Master plan §§8.2, 11, 15, 16, 18 and 31; ADR-0002;
ADR-0003; ADR-0004; ADR-0014; D-0123; D-0125; D-0130
**Opened:** 9 August 2026
**Resolved:** 9 August 2026

## Problem

ADMIN-ORG-01 is authorized to deliver current-organization, branch, cohort and
staff/trainer administration. The frozen governance matrix deliberately does
not decide several foundation-level details needed to make those writes safe:

- whether a tenant operator may mutate the platform-global organization
  registry or its authoritative learning-day timezone;
- which authority survives staff deactivation and whether it may revive;
- how custom-role delegation avoids self-escalation and concurrent final-admin
  lockout;
- how a web credential becomes authoritatively bound to one selected
  membership before an organization operator may revoke it;
- whether invitation retry can reveal a bearer secret again; and
- the exact live-history, privacy and backup disposal rules for new
  organization/staff administration evidence.

The existing schema also has no branch aggregate or optimistic versions,
stores only the current membership-role and cohort-trainer projections, and
does not bind web authentication sessions to a selected membership. Choosing
these semantics silently would change security, privacy, tenancy and
authoritative foundation schema. AGENTS.md therefore requires the affected
path to stop at this decision request.

## Decision profiles

| Option | Boundary | Trade-off |
|---|---|---|
| **A — Minimal, permission-ceiling staff administration (recommended)** | Read-only organization registry, purpose-minimized staff operations, show-once invitations, reversible membership suspension without authority revival, membership-bound credential revocation and bounded history | Delivers the safe staff vertical slice but defers writable organization configuration and rich trainer profiles |
| B — Broader organization and staff administration | Add named writable organization settings, profile fields, different lifecycle or retention rules supplied by the approver | May support more workflows, but each changed field needs an exact purpose, authority, effective-time, privacy and disposal policy before implementation |
| C — Hold ADMIN-ORG-01 | Keep the current read-only shell and add no ADMIN-ORG schema, permission seed or route | Avoids the unresolved risks but pauses the ordered ADMIN program |

## Recommended Option A policy

Approval of Option A means all of the following.

### 1. Scope and organization settings

1. ADMIN-ORG-01 operates only on the current organization and only on
   `trainer` and `organization_admin` memberships. Learner membership,
   invitation, enrollment, profile and lifecycle work remains
   ADMIN-LEARNER-01 scope.
2. Current organization `id`, `name`, `kind`, `status` and `timezone` are safe
   read-only metadata. `system_key` and other registry identifiers are not
   returned by the ADMIN-ORG route.
3. No ADMIN-ORG route may mutate the platform-global `organizations` row.
   `organization.settings.manage` remains an unseeded documentation
   reservation and no settings mutation route is registered in this
   checkpoint. A later decision must name each writable setting, type,
   consumer, validation and effective-time behavior. In particular, timezone
   cannot change without defining the treatment of existing authoritative
   learning-day records.
4. No generic key/value setting, secret, provider credential, unrestricted URL
   or platform capability is introduced.

### 2. Branches, cohorts and trainer profiles

1. A branch is organization-owned and contains only immutable `key`, mutable
   `name`, `active|archived` lifecycle, optimistic version and timestamps. It
   has no address, contact, timezone, manager identity or free-text notes.
2. Existing cohorts gain an optional branch relationship and optimistic
   version. A cohort retains its immutable key and existing
   `active|archived` lifecycle. Optional association preserves existing
   unbranched cohorts.
3. A branch cannot be archived while it has an active cohort. A cohort cannot
   be archived while an active or pending learner enrollment or a current
   trainer assignment depends on it. ADMIN-ORG-01 does not change learner
   enrollment or attendance to satisfy an archive request.
4. A trainer profile is a derived read model over an existing trainer
   membership, global display name, current roles, cohort assignments and
   existing curriculum-authority appointments. No `trainer_profiles` table or
   biography, phone, email, identity hint, qualification narrative,
   attachment, support note or other new personal field is added.
5. Cohort assignment and revocation use `trainer_profiles.manage`; curriculum
   authority appointments continue to use
   `curriculum.authority.manage`. ADMIN-ORG-01 reuses the existing appointment
   and event model and does not create a second generic appointment model.
   Appointee user identity is derived server-side from the scoped membership;
   no command accepts a platform-global user identifier.
6. Ordinary trainer lists return only safe appointment kind, scope, validity
   and state. Existing qualification basis and approval-evidence references
   are visible only to the exact appointee or a current
   `curriculum.authority.manage` holder through a separately authorized detail;
   they never enter global search.

### 3. Roles, delegation and privileged classification

1. Atomic permissions remain the only authorization primitive. Role names,
   role keys, membership types, client panel keys and MFA state grant no
   authority by themselves.
2. `roles.manage` permits custom-role creation, rename, versioned permission-set
   replacement, assignment, revocation and retirement. Custom role keys are
   immutable. Seeded system roles and their seed-defined grants cannot be
   edited or retired through operator routes. A custom role can retire only
   when it has no current assignments.
3. A role manager may create, grant or assign only seeded permission keys that
   are currently held by that actor in the same active membership. A role
   containing any permission outside that ceiling cannot be assigned.
   Platform capabilities can never enter a tenant role.
4. An actor cannot assign or revoke their own roles or change a role currently
   assigned to themselves. Transfer of authority requires another current
   role manager.
5. Every role, grant, assignment and membership-lifecycle command locks the
   relevant authority rows and must leave at least one active staff membership
   that holds both `memberships.manage` and `roles.manage`. The invariant is
   permission-derived and concurrency safe; a named `organization_admin` role
   is not sufficient.
6. Privileged web-session classification becomes permission derived. An
   active membership holding any seeded permission other than
   `learning.participate` is privileged. Custom role names and keys cannot
   weaken session lifetime or MFA enrollment policy.
7. ADMIN-ORG seeds six used permission definitions:
   `organization.settings.read`, `branches.read`, `branches.manage`,
   `trainer_profiles.read`, `trainer_profiles.manage` and
   `membership_sessions.revoke`. The reserved
   `organization.settings.manage` key remains unseeded until a writable route
   is accepted. The seeded organization-admin pack receives those six plus
   the existing cohort, membership, invitation and role permissions. The
   trainer pack receives no new administrative grant by default.

### 4. Staff invitations and activation

1. Operator issuance is limited to `trainer` and `organization_admin`. It
   accepts no recipient phone/email, password, display label, role, cohort,
   user ID or organization ID. Roles and cohort assignments occur after
   acceptance through their separately authorized commands.
2. The server generates at least 128 bits of cryptographic randomness and
   stores only a keyed digest. Expiry choices are exactly 24, 72 or 168 hours,
   defaulting to 72 hours; callers cannot exceed seven days.
3. The raw activation code appears only in the initial successful issuance
   response. It is absent from list, detail, exact replay, audit, outbox, log,
   analytics, URL, browser history and browser/device storage. The service
   never stores the code reversibly or sends it through a provider.
4. An exact idempotent replay returns the same authoritative invitation and
   `activation_code: null`; it never creates a second invite or a replacement
   code. Idempotency prevents duplicate authority and is not secret recovery.
   A lost response or code requires explicit revocation and issuance with a
   new idempotency key.
5. Pending, accepted, revoked and expired redemption failures remain
   enumeration safe. The existing invite-plus-OTP acceptance contract is
   retained, and the web authentication journey gains an accessible,
   ephemeral invitation-code field so the checkpoint can demonstrate actual
   activation end to end.

### 5. Membership lifecycle and authority consequences

1. Organization-admin `deactivate` changes only an active staff membership to
   `suspended`. It never changes the platform-global user, identity, MFA factor
   or another organization membership. `ended` remains reserved for the
   reviewed privacy/global account-closure path.
2. The same transaction removes current role assignments and cohort-trainer
   assignments, appends their revocation history, terminates every pending or
   active curriculum-authority appointment for that membership, and
   authoritatively revokes every web/mobile credential still selected for the
   membership. Previously valid reviews and approvals are not invalidated.
   These consequences require no broader global authority because retaining
   any one of them would make deactivation unsafe.
3. A separate versioned `reactivate` command may return a suspended membership
   to `active`. It restores no role, cohort assignment, appointment, credential
   or prior authorization; each must be deliberately recreated under its own
   permission. An ended membership cannot be reactivated by ADMIN-ORG.
4. The actor cannot deactivate or reactivate their own membership through the
   organization-admin route. The existing self-session controls remain the
   security escape path.

### 6. Membership-bound credentials

1. Web authentication-session authority gains a nullable selected
   organization/membership pair with pairing checks and an identity-preserving
   composite foreign key equivalent to the existing mobile-family binding.
   Explicit selection and implicit single-membership resolution synchronize
   this pair; context clearing or switching clears or replaces it.
2. Staff credential responses are count-only: membership identifier, active
   web-session count, active Android-family count, latest activity time,
   opaque state fingerprint and semantic allowed actions. They expose no IP,
   user agent, session/storage identifier, ciphertext, installation digest,
   OAuth identifier/scope, token identifier/digest, MFA metadata or
   other-tenant selection.
3. Manual membership-session revocation requires
   `membership_sessions.revoke`, rejects the actor's own target membership,
   and rechecks every selected pair under lock. It affects only web sessions
   and Android token families still bound to the target membership. Unselected
   credentials and credentials selected for another membership are untouched.
4. The authoritative database revocation commits before server-session storage
   cleanup. Post-commit cleanup is retryable and cannot restore authority on
   failure. Audit attributes the real operator membership, never the target
   user. Global `revokeAll*`, recovery, MFA reset and user suspension APIs are
   not reused.

### 7. API, command and UI invariants

1. Reads are bounded, cursor-paginated, organization scoped, independently
   retryable and `no-store`. Staff projection is limited to membership ID,
   nullable existing display name, membership type/status/timestamps, current
   safe role summaries, opaque state fingerprint and semantic allowed actions.
   It excludes learners and platform-global user/identity identifiers.
2. Every mutation is first-party Sanctum web only, membership-context derived,
   exact-permission checked and fresh-TOTP protected. A recovery-code assertion
   cannot authorize it.
3. Every mutation uses a header-only UUID/ULID `Idempotency-Key` and a canonical
   fingerprint containing operation, target, normalized payload and expected
   integer version or sorted relationship fingerprint. Receipts are bound to
   organization and actor membership. Changed replay returns `409`; exact
   replay performs one authoritative write and one audit/outbox effect.
4. Commands reauthorize inside one transaction and lock actor authority and
   targets in a documented deterministic order. Stale state returns `409`; no
   last-write-wins path exists. No generic HTTP `DELETE` route is added.
5. Audit and outbox payloads contain stable opaque identifiers and fixed action
   keys only. Display names, invite codes/digests, qualification text, identity
   hints, request bodies, credential identifiers and MFA details never enter
   them.
6. The UI derives navigation and controls from server semantic panel/action
   keys, not raw permissions or role names. Every panel has accessible loading,
   empty, error, retry, success, validation, fresh-MFA and conflict states.
   Lifecycle actions use confirmation and focus restoration. The invitation
   code is shown left-to-right only after issuance and hidden after explicit
   acknowledgement without claiming that JavaScript memory or the operating
   system clipboard was erased.

### 8. Operational history, privacy and backups

1. `organization_administration_history_days` is one centralized, code-owned
   policy value with an exact value of 365 days. Active and suspended current
   records remain while they are operationally current. Command receipts,
   relationship/lifecycle events, terminal invitation metadata and ADMIN-ORG
   session-revocation evidence remain in live storage for 365 days after
   command completion or terminal transition.
2. After 365 days, unreferenced history is purged. Records referenced by
   immutable learning, publication, review, rights or privacy evidence retain
   only the minimum opaque authority link for the life of that evidence and
   become purge-eligible 365 days after the final reference ends. Existing
   privileged-audit retention remains a separate overlay and is not shortened
   by this decision. No display name or identity hint is copied into retained
   history.
3. Invitation code digests are cleared within one hour after acceptance,
   revocation or expiry. Raw codes have no database, cache or backup lifetime.
4. Existing curriculum appointment qualification/evidence fields remain under
   ADR-0004's private immutable-authority policy. ADMIN-ORG adds no new such
   field and never returns qualification narrative in ordinary staff/trainer
   lists, audit or outbox payloads.
5. The existing global display name is a read-only staff-directory projection.
   Organization operators cannot correct global identity data. Global access,
   correction, erasure and user closure remain separate reviewed workflows;
   ending one organization relationship never changes another.
6. Encrypted backups containing ADMIN-ORG data have a maximum 30-day lifetime.
   A restore must reapply live purge/revocation tombstones before serving, and
   expired copies are physically removed within 30 days. No legal hold is
   implemented; a longer class-specific statutory floor requires a separate
   accepted overlay.
7. These values are explicit repository policy defaults and maxima, not a
   production-compliance claim. Qualified legal review and independent
   security, privacy and tenancy review remain pre-production gates.

## Required implementation consequence after approval

The approved bounded specification must translate this policy into:

- tenant-constrained branch/cohort/version, current-relationship event,
  idempotency-receipt and selected-web-membership schema;
- permission-derived privilege classification and seeded organization-admin
  grants with no role-name authority;
- organization-scoped queries and explicit invitation, role, membership,
  trainer assignment, authority-appointment and credential-revocation
  commands;
- OpenAPI 3.1.1 plus regenerated clients, six governed locale catalogues, the
  responsive People workspace and web invite activation;
- same-tenant, cross-tenant, mass-assignment, delegation-ceiling,
  last-manager, stale-write, replay, concurrency, session-switch and
  accept-versus-revoke tests on real PostgreSQL; and
- retention cleanup, restore/tombstone evidence and the repository verification
  gates required by the checkpoint protocol.

No schema, permission seed, route, UI control or runtime behavior is authorized
by this open request alone.

## Resolution requested

To accept the recommended profile, reply exactly:

```text
Approve DR-0016 Option A as written.
```

Alternatively, select Option C or describe exact changes to Option A. Any
Option B change must state the affected field or lifecycle, actor permission,
authoritative consumer, privacy outcome, live retention and backup disposal
because those are the unresolved parts of the current foundation.

## Resolution

Option A was accepted as written on 9 August 2026 under
[APP-0041](APPROVALS.md#app-0041) and [D-0132](DECISIONS.md). The exact
approved proposal, before its status, resolved date and this resolution
section were added, has SHA-256
`79d71ab91b1e07d725c2676a42e37c763ea4096e0f3b09dd288f562fffba8541`.

ADMIN-ORG-01 may now publish its bounded active specification and implement
the accepted non-production vertical slice. This resolution does not accept
unbuilt schema, routes, UI or test results; authorize writable organization
settings, learner administration, provider delivery, deployment or
production; or replace the retained independent-review and legal gates.

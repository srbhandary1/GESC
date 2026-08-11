# ADMIN-ORG staff privacy and retention runbook

**Checkpoint:** ADMIN-ORG-01
**Policy revision:** `organization-administration-v1`
**Authority:** DR-0016 Option A / APP-0041 / D-0132, ADR-0002, ADR-0003,
ADR-0004 and `ADMIN-ORG-01_SPEC.md`
**Production status:** Not authorized

## Boundary

This runbook covers current-organization administration for trainer and
organization-administrator memberships. It does not create a global user
directory, learner-administration workflow, staff identity-correction API,
global account closure, credential recovery/reset or legal-hold system.

An organization operator controls only the membership relationship and
organization-owned authority attached to it. The same global user can hold an
independent membership in another organization; no ADMIN-ORG command may
change that membership, the global user, authentication identity or MFA
factor.

## Data inventory and purpose

| Record/read class | Allowed staff-linked data | Purpose and exclusions |
|---|---|---|
| `organizations` | Current opaque ID, name, kind, status, timezone | Read-only registry context. No system key or mutation |
| `branches` | Key, name, lifecycle, version/timestamps | Organization structure only; no address, contact, timezone override, manager or notes |
| `cohorts` | Key, name, lifecycle, optional branch, version/timestamps | Operational grouping only; ADMIN-ORG exposes no learner identity, enrollment, attendance or evidence |
| `users` projection | Nullable existing display name | Read-only directory affordance. No global user ID, phone, email, identity hint/digest or other membership |
| `memberships` | Membership ID, trainer/admin type, lifecycle/timestamps and version | Tenant relationship and authority anchor; learner memberships are excluded |
| `roles`, `role_permissions`, `membership_roles` | Safe role metadata and permission keys only to `roles.manage` | Deterministic tenant authorization. Role name/key is never authority by itself |
| `institution_invites` | Staff type, status, expiry/timestamps, version and conditionally accepted membership ID | Code-only activation with no recipient address, label, password, role or cohort attachment |
| Invite code | Raw code in initial issuance response only; keyed digest while usable | Bearer activation secret. Raw value is never persisted, replayed, logged, audited, sent to analytics/provider or backed up |
| Derived trainer profile | Safe staff projection, roles, cohort summaries and safe appointment summaries | No trainer-profile table, biography, contact, identity hint, qualification narrative, attachment or notes |
| `authority_appointments` detail | Existing qualification basis/evidence reference only to exact appointee or current authority manager | Existing ADR-0004 private authority evidence; ordinary trainer lists and global search exclude it |
| `authentication_sessions` selected pair | Internal selected organization/membership link | Authoritative web credential scope. API exposes counts/times only, never storage identifiers/ciphertext, IP, agent or MFA state |
| `mobile_token_families` selected pair | Existing internal selected membership link | Authoritative Android credential scope. API excludes installation/token/OAuth identifiers and scopes |
| `organization_administration_commands` | Actor membership, operation/target/result IDs, fingerprints and times | Replay authority only; no user ID, body, label, evidence text or secret |
| `organization_administration_events` | Same-tenant opaque relationship/lifecycle IDs, fixed event/reason and times | Content-minimized authority history; no names, descriptions, request values or credentials |
| Session cleanup requests | Internal authentication-session FK and leased work state | Durable destruction of already-revoked web storage; not an API/audit/outbox projection |
| Tenant audit/outbox | Fixed action plus stable subject IDs | Accountability/durable side effect only; no display name, code/digest, qualification text, identity hint or credential ID |

## Access and correction

- Every read is active-membership and exact-permission checked, organization
  scoped, cursor bounded and `no-store`.
- Staff display name correction is not an ADMIN-ORG operation. It belongs to a
  separately reviewed global identity workflow.
- Branch/cohort/role name correction is a versioned organization command;
  keys and ownership never change.
- Current role and cohort-trainer relationships change only through explicit
  assignment/revocation commands with append-only safe events.
- Appointment qualification/evidence remains governed by ADR-0004 and cannot
  be rewritten through the ordinary trainer profile.
- A tenant operator may suspend/reactivate a staff membership but cannot end
  it, hard-delete it or alter the user/identity/MFA record.

## Deactivation and credential scope

Deactivation changes the target active staff membership to `suspended` and,
in the same transaction, removes current roles and cohort-trainer assignments,
terminates pending/current authority appointments, and marks only web/mobile
credentials still selected for that membership revoked. Previously valid
reviews/approvals remain valid evidence.

Reactivation restores only the membership to active. It restores no role,
assignment, appointment, credential or authority. Credentials with no selected
membership or a different selected membership remain untouched. The global
user, identities, MFA factors and other-organization memberships remain
unchanged.

## Retention and disposal

The code-owned `organization_administration_history_days` policy is exactly
365 days.

- Active/suspended current records remain while operationally current.
- New command receipts, relationship/lifecycle events, terminal invitation
  metadata and membership-session revocation evidence remain live for 365
  days after completion or terminal transition.
- Unreferenced history becomes purgeable after that period.
- Minimum opaque anchors referenced by immutable learning, publication,
  review, rights or privacy evidence remain for the life of the evidence and
  are reconsidered only after the reference no longer exists.
- Existing privileged-audit retention is a separate overlay and is not
  shortened here.
- Pending invite digests remain only while needed for redemption. Accepted,
  revoked or expired invite digests are cleared within 60 minutes. Raw invite
  codes have no database, cache or backup lifetime.
- Existing appointment qualification/evidence follows ADR-0004 immutable
  authority retention when referenced; ADMIN-ORG adds no new field.
- Completed internal cleanup work and content-free command/event history use
  the same 365-day maximum unless a retained immutable reference requires the
  opaque anchor.

No legal hold is implemented. A longer statutory class-specific floor requires
a separately accepted policy overlay. This repository policy is not legal or
production-compliance evidence.

## Backups and restore

- An encrypted backup containing ADMIN-ORG data has a maximum 30-day lifetime.
- Expired backup copies must be physically removed within 30 days.
- A restore cannot serve traffic until current revocation, digest-clear and
  purge tombstones from an independently current control plane have been
  reapplied. A tombstone restored only from the same old backup is not current
  deletion evidence.
- The repository drill may prove schema/aggregate preservation and replay of a
  synthetic tombstone overlay. It does not prove production encryption,
  offsite retention, WAL/PITR, legal hold or RPO/RTO.

## Logging, analytics and browser/device storage

Never place display names, identity data, invitation codes/digests,
qualification text, evidence references, request bodies, relationship lists,
session/token/install identifiers, IP/user-agent or MFA details in logs,
analytics, audit or outbox payloads.

The web UI keeps filters, cursors and initial invitation code in component
memory only. It does not use localStorage, IndexedDB, Cache Storage, URLs,
history, downloads, automatic clipboard writes or ordinary Capacitor
preferences for ADMIN-ORG values. An explicit copy button may place the code
on the operating-system clipboard after user action; the UI does not claim it
can erase that clipboard.

## Operator procedure

1. Select the exact current organization and establish a fresh TOTP assertion.
2. Use the People workspace only for staff memberships; do not paste learner
   or identity data into filters or names.
3. For an invitation, choose only staff type and bounded expiry. Save/share the
   initial code privately. If lost, revoke the invite and issue a new one; the
   server cannot reveal it again.
4. Before role assignment, review only the server-filtered permission set.
   Another role manager must change the operator's own assignment.
5. Confirm deactivation removes all current tenant authority and selected
   credentials. Use reactivation only when authority will be deliberately
   rebuilt.
6. Never use organization deactivation/session commands as global recovery or
   account closure.
7. Keep exported screenshots, copied codes or operational case material out of
   ordinary tickets and analytics; this checkpoint provides no export path.

## Known production gates

Independent security/privacy/tenancy review, qualified legal validation,
production backup/PITR design, current external tombstone overlay, production
volume measurement, incident access controls, deployment and public release
remain separate gates. One consolidated development approval is not
independent review.

# Global Spoken English administrator and operator user guide

**Document status:** Repository baseline guide, pre-production
**Prepared:** 10 August 2026
**Audience:** Organization administrators, trainers, content authors, appointed
reviewers, rights confirmers, auditors, support operators and privacy operators
**Production/public release:** Not authorized

This guide explains the current organization-scoped operator console and its
governed workflows. It is an operating guide, not a grant of authority. The
server-generated panel manifest, atomic permissions, active organization
membership, fresh MFA and any required human appointment decide what a signed-
in operator may see or do.

Use this guide with the current [status ledger](../STATUS.md), the
[governance matrix](../ADMIN_GOVERNANCE_MATRIX.md), and the relevant operating
runbooks. The repository implementation remains subject to deployment,
independent review and production release gates.

## Quick navigation

- [Access and privileged security](#3-access-prerequisites)
- [Understand the console](#5-understand-the-console)
- [People administration](#7-people-administration)
- [Learner administration](#8-learner-administration)
- [Content Studio](#9-content-studio)
- [Media Library](#10-media-library)
- [Operations, safety and privacy](#11-operations-safety-and-privacy)
- [Routine checklists](#14-routine-operating-checklists)
- [Troubleshooting](#16-troubleshooting)
- [Current limitations](#17-current-limitations-and-unavailable-functions)

## 1. Capability labels

| Label | Meaning |
|---|---|
| **Available in repository** | Implemented and covered by repository evidence, but not a claim of production deployment |
| **API/controlled operation** | Implemented behind a controlled API or runbook but not exposed in the current React operator console |
| **Demonstration only** | Synthetic browser-memory behavior, not an authoritative administration workflow |
| **Planned** | Governed design exists, but the end-to-end feature is not implemented |
| **Deferred or blocked** | Deliberately unavailable until a later checkpoint or policy decision |

## 2. Administration model

The operator console is a tenant-safe administration surface for the operator's
current organization. It is not a global super-admin console.

Five rules govern every operation:

1. **Organization context comes from membership.** A request body cannot pick
   or replace the owning organization.
2. **Permissions are atomic.** Role names are setup conveniences; server code
   checks exact permission keys.
3. **Sensitive mutations need fresh TOTP.** A normal sign-in or recovery code
   alone cannot authorize an administrative mutation.
4. **Published content and submitted evidence are immutable.** Correction uses
   a new revision, successor, rollback, withdrawal or append-only event.
5. **Human authority stays human.** AI cannot approve content, rights, marks,
   mastery, progression, attendance or entitlements.

An identifier belonging to another organization is treated like a missing
identifier. Do not try to diagnose cross-organization access by repeatedly
guessing IDs; use the authorized support path.

## 3. Access prerequisites

An operator needs:

- a first-party web/PWA session; privileged operator endpoints reject Android
  Passport bearer credentials;
- an active staff membership in the current organization;
- one or more exact permissions for the relevant panel and action;
- an enrolled authenticator-app TOTP factor;
- a fresh TOTP assertion for mutable work; and
- when reviewing English, Kannada or rights, a current accepted appointment
  whose scope covers the exact program/content/media.

The server sends only the navigation and resource panels authorized for the
current membership. A missing panel usually means the required read permission
is absent; it is not a client-side feature flag to bypass.

## 4. Sign in and secure the privileged account

### 4.1 Accept a staff invitation

Staff invitations are code-only and currently support the bounded Trainer and
Organization administrator onboarding paths. The issuer selects an expiry of
24, 72 or 168 hours.

1. Receive the code through an approved secure channel.
2. Open the normal web sign-in flow and verify the registered mobile number by
   OTP.
3. Enter the exact invitation code during verification.
4. Confirm that the expected organization and staff membership are active.

The plaintext invitation secret is shown only at issue time. It cannot be
recovered later or revealed by replaying the issuance request. If lost, revoke
it and issue a new invitation; do not copy it into an audit note, ticket or
ordinary email.

### 4.2 Enroll TOTP

Open **Account Security** when prompted or before performing administrative
work.

1. Start authenticator-factor setup.
2. Scan or manually enter the one-time provisioning URI in a trusted
   authenticator app.
3. Enter the current six-digit TOTP to confirm enrollment.
4. Save the ten show-once recovery codes in a secure offline location.
5. Confirm that the setup screen no longer exposes the provisioning secret.

Each recovery code is single-use. Recovery codes authorize the approved factor
replacement flow only; they do not make an ordinary admin mutation “fresh.”

### 4.3 Refresh the MFA window

Before a mutable operation, the console asks for the current six-digit TOTP.
A successful assertion opens a 15-minute freshness window for the same
privileged web session. Re-authenticate when the window expires.

Do not leave a freshly asserted administrator session unattended. Sign out or
lock the device before stepping away.

### 4.4 Replace or revoke a factor

Use the reviewed factor-recovery workflow. Replacing/revoking the factor or
regenerating recovery codes revokes active web sessions and Android credential
families as required by policy. If no valid recovery route remains, use the
reviewed support process; an organization administrator cannot inspect or reset
global identity credentials directly.

See the [privileged MFA runbook](../PRIVILEGED_MFA_RUNBOOK.md) for incident and
recovery detail.

## 5. Understand the console

The current console can expose the following top-level destinations:

| Destination | Current purpose |
|---|---|
| **Overview** | Organization descriptor and bounded pilot analytics |
| **People** | Read-only organization details, branches, cohorts, staff, roles, trainers, invitations, appointments and selected session revocation |
| **Learners** | Learner-safe directory, invitations, profile corrections, enrollment and lifecycle actions |
| **Learning** | Governed Content Studio and Media Library resources |
| **Operations** | Permission-bounded support diagnostics |
| **Safety** | Safe audit-event list and detail |
| **Account Security** | TOTP, recovery-code and factor lifecycle |

The navigation is derived from the server manifest. Resources load
independently: one failed panel should display its own retry state while other
authorized resources remain usable.

## 6. Overview and organization search

### 6.1 Overview

**Overview** displays the current organization name and timezone. When the
permission is present, it can also show bounded pilot analytics. Analytics are
derived read models, not authoritative learner records, and should not be used
to alter an individual result manually.

### 6.2 Search this organization

The federated search is a live, ephemeral projection.

- Enter 2–80 characters.
- Results are restricted to resources the operator can already read.
- Results expose safe descriptors, not raw learner content.
- A page contains at most 25 results and uses bounded continuation.
- The query is not a durable search index or note.

Do not put sensitive audio, transcript text, learner answers, credentials or
private support material in a search query. Production-volume latency and
concurrent-update behavior have not yet been established.

## 7. People administration

Open **People**. Only the panels named in the server manifest will appear.

### 7.1 View organization details

The organization registry panel is read-only in the current checkpoint. It
provides safe organization metadata such as name and timezone. Writable
organization settings are not yet available.

If a name, timezone or organization identity must change, record a decision
request through the authorized platform process. Do not edit the database
directly.

### 7.2 Create and manage branches

Use branches for physical or operating locations within the organization.

To create a branch:

1. Open **Branches**.
2. Select the create action.
3. Enter the allowlisted branch fields.
4. Review the organization context and submit with fresh MFA.
5. Confirm the new branch appears in the refreshed list.

To rename a branch, use the provided update action. To stop new use, archive
the branch. Archiving is not a hard delete and preserves historical references.
A stale-state conflict means another operator changed the record; reload,
review the new state and decide again.

### 7.3 Create and manage cohorts

A cohort groups learners for administration and trainer derivation.

1. Open **Cohorts** and create a cohort with its allowed descriptor.
2. Optionally select an active branch.
3. Save with fresh MFA.
4. Update its allowed fields as needed.
5. Archive it when it should no longer receive new operational use.

Archiving does not erase enrollment or authority history. A cohort is not an
authorization shortcut and does not expose learner answers, recordings or
conversation content.

### 7.4 Create and maintain custom roles

Roles bundle exact permissions for setup and navigation.

1. Open the role-management resource.
2. Create a role with a clear organization-local name.
3. Select only the permissions needed for the duty.
4. Save with fresh MFA.
5. Assign it to the intended active staff membership.

An operator can delegate only permissions within the operator's own current
delegation ceiling. System role packs are immutable. A role name never grants
authority by itself, and wildcard permissions are not supported.

The service protects the final-manager invariant: an action cannot remove or
deactivate the last membership that retains the organization's required
management authority.

### 7.5 Issue a staff invitation

1. Open **Invitations**.
2. Choose the supported staff role path and expiry: 24, 72 or 168 hours.
3. Submit with fresh MFA.
4. Copy the plaintext code from the one-time display.
5. Send it through a secure channel to the intended person.
6. Close the one-time display after transfer.

The list later shows safe invitation state, not the secret. Revoke an unused or
misdirected invitation. An idempotent replay confirms the same operation but
does not reveal the secret again.

### 7.6 Manage staff memberships

The staff membership panel supports bounded lifecycle and role operations:

- assign or revoke permitted organization roles;
- deactivate an active staff membership;
- reactivate a deactivated staff membership; and
- inspect safe status needed for administration.

Deactivation removes active roles, trainer links, current authority
appointments and selected current credentials according to policy.
Reactivation does not restore those relationships automatically. Reassign each
needed role, trainer scope or appointment after a deliberate review.

This panel does not reveal the person's global identity record, OTP factors,
token values, phone/email credentials or memberships in another organization.

### 7.7 Assign trainers to cohorts

Trainer profiles and cohort assignments are organization-owned relationships.

1. Confirm the staff membership is active and intended to act as a trainer.
2. Open the trainer assignment panel.
3. Select the active cohort(s) within the current organization.
4. Save with fresh MFA.
5. Verify the derived trainer/cohort view.

Learner trainer summaries are derived from these cohort links. There is no
current direct per-learner trainer assignment path.

### 7.8 Manage authority appointments

Possessing a review or rights permission is necessary but not sufficient.
The person must also hold a current accepted appointment for the exact scope.
Appointment kinds include:

- English content reviewer;
- Kannada content reviewer; and
- media rights confirmer.

The controlled lifecycle is:

1. An authorized manager creates a scoped appointment with its evidence.
2. The appointed person accepts it before exercising authority.
3. The person may surrender it, or an authorized manager may revoke it.
4. A later permission, membership or scope change invalidates authority as
   defined by the current state.

Do not create an appointment merely to approve the appointer's own work.
Author/reviewer and translator/reviewer separation remains mandatory.

### 7.9 Revoke selected membership sessions

The session tool operates on safe counts and the credentials currently selected
for the target organization membership. It does not expose tokens, devices or
global credential details.

Use it when a staff member leaves the organization or an organization-scoped
session is suspected. It is not a substitute for platform identity recovery or
factor replacement.

## 8. Learner administration

Open **Learners**. The directory is deliberately purpose-minimized: it does not
provide a global identity profile, phone/email credentials, learner answers,
audio, transcripts, AI-memory notes or private conversation content.

### 8.1 Find and inspect a learner

Use the bounded learner directory and continuation controls. Open a learner
detail only when your role requires it. The view can expose safe membership,
profile, goal, cohort and enrollment information for the current organization.

If a known learner is absent, check active organization context and lifecycle
filters. Do not search another tenant or request direct database access.

### 8.2 Issue a learner invitation

1. Open the learner-invitation resource.
2. Select the exact learner role, which contains only
   `learning.participate`.
3. Optionally select an active cohort.
4. Submit with fresh MFA.
5. Transfer the one-time code securely to the intended learner.

Acceptance can create the pending organization enrollment while preserving the
platform's multi-organization identity model. The code cannot be recovered
after its one-time display.

### 8.3 Correct a learner goal

Use **Goal correction** to record an attributed correction. The service appends
the new goal and preserves the earlier goal history; it does not rewrite
historical evidence.

Choose only an allowed goal and enter only the bounded reason/evidence fields.
Do not place sensitive notes in a goal correction. General support-note writing
is not implemented.

### 8.4 Change a learner's cohort

Select an active cohort in the current organization and submit the change with
fresh MFA. The relationship changes prospectively. It does not rewrite prior
trainer history, learning evidence or publication pins.

### 8.5 Manage enrollment

The current enrollment lifecycle supports:

- creating a pending enrollment;
- assigning/changing the current cohort where allowed;
- activating an enrollment against an exact verified immutable curriculum
  publication;
- withdrawing an enrollment; and
- reading the safe lifecycle history.

Activation must use a publication that is currently eligible and belongs to
the authorized curriculum boundary. The pin is exact: later draft edits or a
new publication do not silently rewrite an existing learner's course.

Enrollment reactivation is not available in the current operator UI. Follow a
new authorized enrollment path rather than modifying historical rows.

### 8.6 Suspend or reactivate learner participation

Suspension stops current organization participation while retaining immutable
learning evidence and history. Reactivation restores the membership lifecycle
state but does not silently restore withdrawn enrollment, cohort, trainer,
publication or other removed relationships.

Review and explicitly recreate only the relationships still authorized.

## 9. Content Studio

**Content Studio** is the governed authoring and publication workspace. It is
not a free-form CMS and does not accept arbitrary HTML or unknown schemas.

### 9.1 End-to-end content lifecycle

The safe operating sequence is:

1. create an organization-owned program descriptor;
2. create/open a graph-v2 draft workspace;
3. build the level/objective/module/lesson/item structure;
4. author typed English source content;
5. author the typed Kannada variant where required;
6. attach exact governed media when the content type requires it;
7. use private preview;
8. submit structure, English and Kannada heads to the correct review queues;
9. obtain independent accepted review decisions;
10. resolve every readiness blocker;
11. publish immediately or schedule activation;
12. manage the immutable publication through cancel, activate, retire,
    rollback or successor operations.

Skipping a review or media-rights step is not an administrative override. The
readiness service fails closed and gives allowlisted blocker/action codes.

### 9.2 Create a program and workspace

Create a program with its bounded descriptor, then create a graph-v2 workspace.
Workspaces and levels belong immutably to the current organization. Use stable,
meaningful titles; do not encode secrets or learner data in names.

The graph supports levels, objectives, modules, lessons, items and delivery
bundles. The domain does not hard-code a fixed number of levels, so a governed
program can grow without a schema change.

### 9.3 Build the curriculum graph

Use the structured graph forms to add and order the permitted entities. Save
one coherent change at a time and review the refreshed head before continuing.

If another operator changed the same workspace, the console returns a stable
stale-state conflict. Preserve any unsaved text shown by the UI, reload the
authoritative head, compare changes, and submit a deliberate new revision.

### 9.4 Author typed lesson content

Current authoring adapters include governed text and daily-speaking mission
content. The form exposes typed, allowlisted fields for the schema; it does not
allow arbitrary executable markup.

The current daily-speaking mission contract includes one authored knowledge
check and requires exact current model audio for publication. Dynamic audio
topic extraction, Skill Chat and multi-item AI quiz drafting are **planned** in
the [Lesson Practice Loop plan](../LESSON_PRACTICE_LOOP_PLAN.md), not available
in the current Content Studio UI.

### 9.5 Create English and Kannada revisions

Save English source and Kannada variant revisions separately. Each accepted
revision points to an exact immutable head. Editing after submission creates a
new head and invalidates a stale review rather than changing the reviewed text
underneath it.

The translation and review roles must be separated according to the current
appointment and permission rules.

### 9.6 Preview safely

Private preview renders only inert typed content through an authenticated,
no-store route. It is for review inside the current organization. It does not
create a public link, publication, learner assignment or rights approval.

### 9.7 Submit and review

Use the separate queues for:

- curriculum structure;
- English source; and
- Kannada variant.

The reviewer must have the exact permission, a current accepted appointment
covering the program, and separation from the creator/translator as required.
A decision is recorded against exact heads with allowlisted rationale. A
publisher cannot manufacture or waive that evidence.

### 9.8 Resolve readiness blockers

Readiness can block publication for reasons including stale or absent reviews,
missing translation evidence, missing/invalid model media, absent current
rights, schema errors or changed graph heads. Follow the listed action and
re-run readiness. Do not repair readiness by altering database rows or copying
an approval from another asset/revision.

### 9.9 Publish and schedule

After readiness is clean and the publisher has the exact permission:

- **Publish now** creates an immutable active publication;
- **Schedule** records an exact future activation;
- **Activate due publication** performs the guarded due transition;
- **Cancel schedule** stops a not-yet-active schedule;
- **Retire** prevents future active use according to lifecycle rules;
- **Rollback** activates an authorized prior immutable publication; and
- **Successor** creates the correction path for released content.

Every action revalidates authority and exact evidence under lock. A stale or
conflicting operation must be reloaded and reconsidered; never retry blindly
with changed data under the same idempotency key.

Published learner bytes do not change because a draft is edited. Existing
enrollment pins also remain exact until an authorized enrollment operation
changes the learner's publication.

## 10. Media Library

The current Media Library supports exactly two private usage types:

| Usage | Accepted file boundary |
|---|---|
| `model_audio` | Bounded audio, maximum 10 MB |
| `lesson_video` | MP4 video, maximum 20 MB |

Assets remain private. There is no public object URL or direct object-store key
in the operator UI.

### 10.1 Media lifecycle at a glance

**Upload → exact inspection/hash → scanner result → human quarantine decision
→ accessibility metadata/review → scoped rights confirmation → attach to exact
draft source → publish → replace/retire prospectively**

Each stage records its own authority. Uploading or owning a file does not
approve its rights, accessibility or curriculum use.

### 10.2 Start and resume an upload

1. Open **Media Library** and choose the usage type.
2. Select a file within the usage-specific type and size boundary.
3. Start the app-proxied resumable upload.
4. Keep the page open while sequential chunks are accepted.
5. If interrupted, resume only from the server-confirmed offset.
6. Complete the upload and wait for exact byte-size/hash inspection.

The service uses private quarantine and rejects out-of-order or mismatched
chunks. Do not rename an unsafe file to an allowed extension. A new byte stream
is a new exact asset and must pass the whole lifecycle.

### 10.3 Inspection and scanner evidence

The project-owned media boundary verifies exact size, type and SHA-256 evidence,
then invokes the configured scanner adapter. Unknown, failed or unavailable
scanner outcomes fail closed.

Scanner output alone does not accept an asset. An operator with
`media.quarantine.review` must inspect the clean preview/evidence and explicitly
accept or reject it. Rejection remains an auditable lifecycle outcome; do not
overwrite it with replacement bytes.

Production object-store and production scanner validation remain open release
gates even though the repository adapter and focused behavior are implemented.

### 10.4 Add accessibility metadata

Create append-only metadata revisions:

- model audio requires a transcript; and
- lesson video requires a transcript and text alternative.

English and Kannada metadata revisions/reviews follow their governed language
authority. Editing creates a successor revision. It does not mutate reviewed
metadata or inherit a previous approval automatically.

### 10.5 Confirm program-scoped rights

A rights decision requires all of the following:

- `media.rights.approve`;
- a current accepted in-scope `rights_confirmer` appointment;
- the exact asset/hash;
- the exact program;
- allowed uses, channels, territories and effective period;
- clean quarantine state and required metadata evidence; and
- the recorded owner/source attestation appropriate to that asset.

The repository's umbrella owner statement is reusable evidence, not automatic
approval. A publisher, uploader, importer or organization owner cannot
manufacture rights authority. Rights for one program or asset do not transfer
to another.

### 10.6 Attach media to Content Studio

Use **Attach to Content Studio source** only after the asset has the required
accepted quarantine, metadata and current program-scoped rights state.

1. Select the exact eligible asset.
2. Select the exact draft source and allowed media slot.
3. Review the program and current revision.
4. Submit with fresh MFA.
5. Re-run Content Studio readiness.

Attachment does not publish. It supplies one exact dependency to a draft
source. Guessing a foreign-organization source or asset yields not-found.

### 10.7 Replace or retire media

Replacement creates immutable lineage to a new exact asset. The new asset does
not inherit scanner acceptance, accessibility review, rights decision,
attachment or publication readiness from the old one.

Retirement blocks new use according to lifecycle rules but does not rewrite
prior immutable publications or evidence. Use a governed content successor or
rollback when a published lesson must move to different media.

### 10.8 Preview media

Operator preview is authenticated, private and `no-store`. Clean-state and
permission checks apply. Do not copy internal preview requests into public
pages or tickets. Preview access is not a distribution right.

## 11. Operations, safety and privacy

### 11.1 Support diagnostics

**Operations** exposes safe system summaries only when
`support.diagnostics.read` is present. Use it to identify bounded service,
queue or state failures. It must not expose raw recordings, transcripts,
learner answers, credentials or personal practice content.

Record the safe reference, timestamp, organization and affected resource. Do
not paste private source content into logs or ordinary support tickets.

### 11.2 Audit events

**Safety** provides a bounded audit list and allowlisted event detail for
operators with `audit.read`. Audit records explain who exercised an authorized
command and its safe state outcome; they are not a copy of private request
bodies.

Use audit to answer “which governed action occurred?” Do not treat absence of
raw content as missing audit evidence; content minimization is intentional.

### 11.3 Privacy export and erasure

The repository contains controlled privacy export and erasure request APIs,
including identifier-only and voice-only cases. The current React operator
console does not expose a complete privacy-request workflow.

Use the approved privacy/runbook path with the exact permission and case
authority. Do not perform a direct database export or deletion. Voice deletion
uses durable receipts and a retryable content-free outbox so intent commits
before storage deletion is attempted.

Consult the [privacy data inventory](../PRIVACY_DATA_INVENTORY.md) and
[voice deletion reconciliation](../VOICE_DELETION_RECONCILIATION.md).

## 12. Roles and permissions

### 12.1 Common repository seed role packs

The Direct-organization seed provides convenience packs for non-production
setup:

| Role pack | Typical seeded capability boundary |
|---|---|
| **Trainer** | Curriculum authoring, Kannada translation, media read/upload/metadata |
| **Organization administrator** | Broad People/Learner administration, publication/authority coordination, media operations, audit, diagnostics and privacy request permissions; content review authority is intentionally absent |
| **Content reviewer** | English/Kannada review, media read/quarantine review and audit |
| **Learner** | `learning.participate` only |

These names are not authorization checks. Custom roles can provide a narrower
least-privilege bundle, but no operator can delegate above the operator's own
ceiling.

### 12.2 Permission and appointment combinations

| Action | Permission concept | Additional condition |
|---|---|---|
| Review English | `curriculum.review.en` | Accepted in-scope English reviewer appointment and creator separation |
| Review Kannada | `curriculum.review.kn` | Accepted in-scope Kannada reviewer appointment and translator separation |
| Publish curriculum | `curriculum.publish` | All exact review/readiness evidence; cannot waive blockers |
| Confirm rights | `media.rights.approve` | Accepted in-scope rights-confirmer appointment and exact evidence |
| Upload media | `media.upload` | Allowed usage/type/size and private quarantine |
| Accept quarantine | `media.quarantine.review` | Clean scanner/inspection evidence and independent human action |
| Manage learners | Exact learner/enrollment permissions | Active current-organization membership and fresh MFA |
| Read audit/diagnostics | Exact read permission | Bounded safe projection only |

Refer to the [full governance matrix](../ADMIN_GOVERNANCE_MATRIX.md) for the
normative permission catalogue and checkpoint ownership.

## 13. Idempotency, conflicts and safe retry

The console generates and submits an `Idempotency-Key` for authoritative
mutations. The service binds it to the normalized operation and request.

- If a request times out, allow the UI to retry the same unchanged operation
  with the same key.
- If you change any field, start a new operation/key.
- A successful replay returns the prior safe result but never recovers a
  one-time invitation/recovery secret.
- A `stale_state` or equivalent conflict is not a transient network failure.
  Reload and compare current authority/state before deciding again.
- A not-found response can intentionally hide a foreign-organization target.
  Do not request a bypass.
- A fresh-MFA failure requires a new TOTP assertion, not repeated submission.

Authoritative multi-write operations use transactions and post-lock
reauthorization. A losing concurrent command should have no partial effect.

## 14. Routine operating checklists

### 14.1 Onboard a staff member

- Confirm the duty and least-privilege permission bundle.
- Issue a bounded staff invitation and transfer the code securely.
- Confirm membership activation.
- Require TOTP enrollment and secure recovery-code storage.
- Assign only reviewed roles/cohorts.
- Create a reviewer/rights appointment only when evidence and separation are
  valid.
- Verify the server manifest exposes only the expected panels.

### 14.2 Offboard a staff member

- Deactivate the current organization membership.
- Confirm roles, trainer links and current appointments are removed.
- Revoke selected membership sessions.
- Reassign operational duties and authority deliberately.
- Verify final-manager protection and required reviewer coverage.
- Use platform recovery only if global credential risk is separately
  established.

### 14.3 Onboard a learner

- Confirm the intended organization and optional cohort.
- Issue an exact learner-role invitation.
- Transfer the show-once code securely.
- Confirm pending enrollment creation/acceptance.
- Activate only an eligible verified immutable publication.
- Verify the learner can see the expected mission without exposing another
  tenant's records.

### 14.4 Release a lesson

- Verify structure and typed content against the learning objective.
- Verify English and Kannada revision heads.
- Upload and accept exact private media.
- Complete accessibility metadata and independent reviews.
- Complete exact program-scoped rights confirmation.
- Attach the exact asset to the exact source.
- Obtain independent structure/language decisions.
- Resolve all readiness blockers.
- Preview privately at narrow and desktop widths.
- Publish/schedule with fresh MFA and record the resulting publication.
- Test only through an authorized enrollment pin; never through a public object
  URL.

### 14.5 Correct released content or media

- Do not edit the released record.
- Create a new source/media revision or successor.
- Repeat every review, accessibility, rights and readiness decision that is no
  longer exact/current.
- Publish the successor or use a governed rollback.
- Change learner pins only through the authorized enrollment lifecycle.
- Preserve the prior publication and evidence for audit/history.

## 15. Security, privacy and tenancy rules

- Use only the current membership-derived organization context.
- Never place raw audio, transcripts, answers, secrets or personal learner
  content in logs, audit rationales, search queries or idempotency keys.
- Never use direct object-store URLs or expose storage keys.
- Never share OTP, TOTP, invitation or recovery secrets.
- Never approve your own authored/translated work where separation applies.
- Never use AI output as a committed review, mark, mastery, progression,
  attendance or entitlement decision.
- Never hard-delete a membership, enrollment, publication, rights decision or
  submitted evidence through ordinary administration.
- Never copy a permission, review or rights decision across organizations,
  programs, revisions or exact assets.
- Never use browser localStorage/IndexedDB or ordinary Capacitor preferences
  for authentication credentials.

If a request affects platform-global identity, tenancy ownership, security
credentials, foundation schema, public distribution rights or retention policy
outside the documented workflow, stop and open a decision request.

## 16. Troubleshooting

| Condition | Safe response |
|---|---|
| Panel is missing | Confirm active membership and exact read permission. The server manifest intentionally hides unauthorized resources. |
| Mutation asks for MFA | Enter a current authenticator TOTP. A recovery code is not an admin-action substitute. |
| `forbidden` | Recheck permission and required appointment. Do not add a broader role merely to bypass a workflow. |
| `not_found` for a known ID | Confirm organization context and current lifecycle. Foreign IDs are existence-hidden. |
| `stale_state`/conflict | Preserve unsaved form data, reload current state, compare and submit a new deliberate operation. |
| Same request timed out | Retry the unchanged operation through the UI so it retains the idempotency key. |
| Invitation code is not visible | It is show-once by design. Revoke and issue a new invitation if transfer failed. |
| Publication is blocked | Follow each readiness blocker; verify exact current heads, reviews, media, metadata and rights. |
| Upload cannot resume | Use only the server-confirmed sequential offset; otherwise start a new exact upload. |
| Scanner is unavailable | Wait or restore the approved scanner adapter. Unknown state must remain quarantined. |
| Media preview is denied | Confirm clean acceptance, read permission and current tenant; preview is private and state-gated. |
| Privacy deletion is pending | Use the durable receipt/runbook and reconciliation worker; do not manually delete object/database rows. |

## 17. Current limitations and unavailable functions

As of 10 August 2026:

- production/public release is not authorized;
- ADMIN-MEDIA-01 focused repository evidence exists, but full PostgreSQL race
  and repository release-gate evidence remains incomplete on the current host;
- there is no complete independently reviewed production curriculum pathway or
  approved production media set;
- organization settings are read-only;
- global identity recovery, credential inspection and tenant impersonation are
  unavailable to organization admins;
- general support-note authoring is unavailable;
- direct per-learner trainer assignment and enrollment reactivation are not
  implemented;
- a complete privacy export/erasure workflow is not present in the React
  console, although controlled APIs/runbooks exist;
- AI content drafting, automatic audio topic extraction, dynamic Skill Chat and
  multi-item lesson quiz generation are planned, not current Content Studio
  functions;
- groups, facilitated sessions, activities, assignment review, communications,
  moderated learner conversations, CMS, entitlements and writable typed
  configuration remain later checkpoints; communication/moderation and payment
  provider paths retain additional policy gates;
- public media, generic signed URLs, offline video and production object-store
  scanner/load evidence are unavailable; and
- all five non-English interface catalogues require independent review and
  retain approved-English production fallback.

## 18. Related operator documents

- [Administrator governance matrix](../ADMIN_GOVERNANCE_MATRIX.md)
- [Privileged MFA runbook](../PRIVILEGED_MFA_RUNBOOK.md)
- [Privacy data inventory](../PRIVACY_DATA_INVENTORY.md)
- [Voice deletion reconciliation](../VOICE_DELETION_RECONCILIATION.md)
- [Incident and support runbook](../ops/INCIDENT_SUPPORT_RUNBOOK.md)
- [Release gates](../ops/RELEASE_GATES.md)
- [Content Studio specification](../ADMIN-CONTENT-01_SPEC.md)
- [Media Library specification](../ADMIN-MEDIA-01_SPEC.md)
- [Lesson Practice Loop plan](../LESSON_PRACTICE_LOOP_PLAN.md)

## 19. Glossary

| Term | Meaning |
|---|---|
| **Atomic permission** | One exact organization capability checked by the server |
| **Role pack** | Convenience bundle of permissions; never an authorization primitive |
| **Authority appointment** | Accepted, scoped human mandate needed in addition to certain review/rights permissions |
| **Fresh MFA** | Confirmed TOTP assertion within the current 15-minute privileged window |
| **Idempotency key** | Request identifier binding a replayable command to one exact normalized operation |
| **Workspace head** | Exact latest immutable graph/content revision used for concurrency and review |
| **Readiness blocker** | Code-owned reason an exact draft cannot yet be published |
| **Publication** | Immutable governed curriculum version that can be pinned to an enrollment |
| **Quarantine** | Private state in which uploaded media awaits exact inspection, scanning and human acceptance |
| **Rights confirmation** | Human, appointed, program-scoped decision for one exact asset and allowed use |
| **Successor** | New governed correction that preserves released history |

When an operational case falls outside this guide, preserve current state and
request a governed decision rather than creating an undocumented override.

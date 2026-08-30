# Learner privacy data inventory and operator runbook

**Checkpoint:** PRIV-DSR-01

**Inventory version:** `learner-privacy-v5`

**Scope:** one learner membership in one organization

**Authority:** master plan §§15, 16 and 18; ADR-0002, ADR-0003,
ADR-0004, ADR-0006, ADR-0007, ADR-0014 and ADR-0021

## Boundary

The privacy API operates on an organization-owned learner membership. It does
not treat a membership identifier as authority over another organization and
does not hard-delete the platform-global user. A user can legitimately have
memberships in several organizations under ADR-0003.

Every export returns every inventory class, including classes with zero rows.
Safe structured values are returned without a per-table truncation limit.
Authentication credentials, password hashes, recovery tokens, cryptographic
digests, raw audio, transcripts,
object keys and provider secret payloads are never returned. Responses are
permission checked, fresh-password-confirmation protected, tenant scoped and
`no-store`. Password confirmation is one-factor freshness, not MFA.

This is a product data-handling policy, not a claim of compliance with every
jurisdiction. Before production, qualified counsel must confirm statutory
access, legal-hold, retention, backup and identity-reassignment requirements.
No jurisdiction-specific legal hold is implemented by this checkpoint.

ADMIN-ORG-01 does not broaden this learner export/erasure API into a staff
identity-management surface. Its separate staff-membership minimization,
credential-selection, invitation-secret and 365-day administration-history
rules are recorded in [ADMIN-ORG privacy and retention](ADMIN_ORG_PRIVACY_RETENTION.md).
ADMIN-LEARNER-01 extends the fixed learner inventory only for the governed
current-goal projection, its attributed correction history and the shared
administration events that link those records.

## Inventory and deterministic outcome matrix

“Retain” below means the record remains available only through its existing
authorization boundary; it does not make the record public or mutable.

| Record classes | Link to learner | Export | Erasure/retention outcome |
|---|---|---|---|
| `users` | Membership `user_id` | Username, display name, status, credential version and lifecycle timestamps | Retain as global identity authority and continue reserving the username; another organization membership is not changed |
| `user_identities` | Global `user_id` | Type, display hint, status and verification time; identifier digest excluded | Retain as global login authority; phone reassignment/account closure still requires its own reviewed global workflow |
| `sessions` | Global `user_id` | Count only; storage key, payload, IP and user agent excluded | Delete server session storage |
| `authentication_sessions` | Global `user_id` | Safe lifecycle metadata; storage digest/ciphertext and password/MFA assertion detail excluded | Revoke all active sessions, clear freshness and retain lifecycle evidence |
| `password_credentials` | Global `user_id` | Count only; password hash and hashing metadata excluded | Retain as global authentication authority; no organization-scoped operator can export, erase or reset it |
| `password_recovery_challenges` | Global `user_id` when eligible | Count only; lookup/token digests and delivery metadata excluded | Retain through short security expiry/cleanup; successful organization erasure revokes sessions but does not create a recovery bypass |
| `password_cutover_exclusions` | Exact excluded global `user_id`, global cutover marker and configured recovery owner | Count only; manifest digest and owner relationship remain restricted platform evidence | Retain append-only cutover and recovery accountability; it cannot be changed through tenant administration or learner erasure |
| `privileged_password_recovery_cases` | Target global `user_id` | Safe historical reason/status/version/times; external evidence reference/digest excluded | Retain superseded/completed historical accountability outside tenant administration; no active route advances it |
| `privileged_password_recovery_approvals` | Historical recovery-case chain | Count only; actor and evidence digests remain restricted platform evidence | Retain historical append-only two-operator approval accountability |
| `account_recovery_management_events` | Target global `user_id` | Safe action/reason/time; actor, old/new identity links and evidence digest excluded | Retain append-only account setup/contact-replacement accountability outside tenant administration |
| `account_recovery_notification_events` | Account-recovery management-event chain | Safe notification outcome/time; former identity link excluded | Retain append-only former-contact notification accountability |
| `password_security_events` | Global `user_id` | Content-free event type/outcome/time; request digest excluded | Retain security accountability evidence |
| `otp_challenges` | Identity destination digest | Count only; destination/code digests excluded | Retain only through existing security expiry/cleanup policy |
| `oauth_auth_codes`, `oauth_access_tokens`, `oauth_refresh_tokens` | Global `user_id` or access-token chain | Count only; token IDs/scopes excluded | Revoke globally and retain security evidence until normal cleanup |
| `mobile_token_families`, `mobile_refresh_tokens` | Global `user_id`; optional selected membership | Safe family lifecycle metadata or count only; installation/token digests and other-tenant selection identifiers excluded | Clear the erased membership selection, revoke every family globally and retain reuse-detection evidence |
| `privileged_mfa_factors`, `privileged_mfa_challenges`, `privileged_mfa_recovery_codes` | Global `user_id` | Safe historical factor lifecycle metadata or count only; secrets/assertion/recovery digests excluded | Before cutover, retain as global authentication authority; at cutover clear/invalidate secret material and retain only lifecycle evidence until later reviewed cleanup |
| `memberships` | Direct tenant owner | Type, status and lifecycle timestamps | Set target membership to `ended`; retain it as the ownership anchor for evidence and receipts |
| `membership_roles` | Direct authorization link | Role key/name and assignment timestamps | Delete target membership role assignments |
| `institution_invites` | Accepted membership/user | Safe acceptance metadata; invite digest and idempotency fingerprint excluded | Retain tenant-access accountability evidence |
| `enrollments` | `learner_membership_id` | Status, cohort, immutable curriculum pins and lifecycle timestamps | Change `pending_curriculum`/`active` to `withdrawn`; retain completed/withdrawn rows and exact pins |
| `learner_profiles`, `learner_goals` | `learner_membership_id` | Onboarding timestamps and selected goal | Retain as append-only adult-confirmation/onboarding evidence protected by the learning authority trigger |
| `learner_profile_current_goals` | `learner_membership_id` | Current goal, projection version and correction time | Retain the governed onboarding projection backed by retained correction evidence |
| `learner_goal_corrections` | `learner_membership_id` | Previous/successor goal, actor, command and effective time | Retain as attributed, append-only authoritative onboarding correction history |
| `organization_administration_events` | Target membership/profile/enrollment or actor link | Content-minimized action, subject, reason-key and lifecycle metadata; command fingerprint and invitation secret excluded | Retain under the existing exact 365-day live ceiling; a correction-linked event remains while its authoritative correction depends on it |
| `learner_attempts`, `item_responses`, `attempt_submissions` | `learner_membership_id` | Safe attempt, response, local-capture receipt and result fields; snapshot hashes excluded | Retain immutable submitted/authoritative evidence so marks remain deterministic |
| `mastery_states`, `review_schedules` | `learner_membership_id` | Safe progression state and source evidence IDs | Retain deterministic projections tied to immutable evidence |
| `learning_commands` | Actor membership | Operation/result metadata; idempotency key and fingerprint excluded | Retain replay/idempotency authority |
| `conversation_sessions`, `conversation_turns`, `conversation_turn_events` | `learner_membership_id` | Safe version/state metadata; utterance/transcript/reply digests excluded | Retain immutable lifecycle evidence; persistent message bodies do not exist |
| `ai_cost_ledger` | `learner_membership_id` | Safe provider/model/usage/cost metadata | Retain usage and cost accountability evidence |
| `conversation_commands` | Actor membership | Operation/result metadata; key/fingerprint excluded | Retain replay/idempotency authority |
| `correction_suggestions` | `learner_membership_id` | Error category, retry and safety state; correction/explanation digests excluded | Retain derived feedback evidence linked to immutable turns |
| `learner_error_patterns` | `learner_membership_id` | Aggregate category/count/times | Delete reconstructable personalization projection |
| `learner_memory_facts` | `learner_membership_id` | Learner-visible fact text and lifecycle fields | Replace all fact text with `[privacy-erased]`, mark active facts deleted and write one digest-only deletion event |
| `tuition_requests` | `membership_id` | Preferred window, topic, notes and status for 1:1 waitlist requests | Redact `preferred_window`/`topic` to `[privacy-erased]`, clear notes, retain lifecycle/status |
| `memory_events` | `learner_membership_id` | Event ID/type/time; before/after digests excluded | Retain append-only digest lifecycle evidence |
| `conversation_session_stage_events`, `roleplay_feedback_evidence` | `learner_membership_id` | Safe stage/objective state; evidence digest excluded | Retain immutable roleplay evidence |
| `voice_consent_events` | `learner_membership_id` | Purpose, policy/notice revision, locale, state and time; notice digest/interaction key excluded | Retain grant/withdrawal accountability for the ADR-0007 window |
| `voice_recordings` | `learner_membership_id` | Safe capture/lifecycle metadata; object key, audio hash, transcript ciphertext and transcript digest excluded | Delete source object and transcript through durable receipts; retain safe recording tombstone |
| `voice_provider_runs` | Recording/member chain | Safe provider/model/attempt/failure/usage metadata | Retain provider accountability metadata; no content is stored |
| `voice_deletion_requests` | `learner_membership_id` | Deletion reason/state and source/transcript/provider outcomes | Retain as deletion receipt |
| `voice_source_objects` | External object behind each recording | Presence/deletion state only; key and bytes excluded | Delete outside the DB transaction only after durable intent commits; retry until reconciled or explicitly restricted |
| `privacy_export_requests` | Target membership | Request status/times, not nested historical payloads | Redact every prior stored export payload and retain the request receipt |
| `privacy_erasure_requests`, `privacy_erasure_voice_deletions` | Target membership/request chain | Status, counts, times and receipt links | Retain privacy-operation and exact voice-deletion receipts |
| `audit_events` | Target as actor or known subject in the selected organization, plus global authentication lifecycle events | Safe action/subject/time only; other-tenant and unrelated platform rows, correlation and platform capability details excluded | Retain append-only accountability evidence |
| `outbox_events`, `processed_events` | Known target aggregate chain | Safe delivery state; outbox payload excluded | Retain durable-work and replay evidence |
| `client_offline_records` | Encrypted learner binding on device | Not server enumerable | Server credentials are revoked; client authentication/logout lifecycle must purge the protected profile. Lost/offline devices cannot be remotely inspected or wiped by this API |

### Structurally membership-linked governance records

Authorized learner flows cannot create these records, but the foundation
schema can link a membership to an actor field. The inventory therefore checks
and exports their record identifiers and retains the governed authority if an
unexpected row exists:

- `programs.created_by_membership_id`;
- `cohort_trainers.trainer_membership_id`;
- `curriculum_versions.created_by_membership_id`;
- `content_revisions.authored_by_membership_id`;
- `localized_variants.translated_by_membership_id`;
- `curriculum_graph_revisions.authored_by_membership_id`;
- both `authority_appointments.appointee_membership_id` and
  `authority_appointments.appointed_by_membership_id`;
- `authority_appointment_events.actor_membership_id`;
- `content_reviews.reviewer_membership_id`;
- `curriculum_graph_reviews.reviewer_membership_id`;
- `media_assets.uploaded_by_membership_id`;
- `media_rights_revisions.confirmed_by_membership_id`;
- `curriculum_publications.created_by_membership_id`;
- `publication_graph_components.reviewer_membership_id`;
- `publication_events.actor_membership_id`;
- `publication_commands.actor_membership_id`;
- `curriculum_share_grants.issued_by_membership_id`;
- `curriculum_share_grant_events.actor_membership_id`;
- `curriculum_clones.cloned_by_membership_id`;
- `privacy_export_requests.requested_by_membership_id` and
  `privacy_erasure_requests.requested_by_membership_id`;
- `organization_administration_commands.actor_membership_id` and
  `organization_administration_events.actor_membership_id`; learner event
  links through `subject_membership_id`, `subject_learner_profile_id` and
  `subject_enrollment_id` are inventoried directly above, while any other
  learner actor link remains an incident-review signal; and
- the global-user actor links
  `authority_appointments.appointee_user_id`,
  `publication_events.platform_actor_user_id` and
  `publication_commands.actor_user_id`.

An occurrence is a security/tenancy review signal because it contradicts the
normal learner capability boundary. Erasure does not rewrite published,
reviewed, rights, appointment or share authority.

### Tables outside the learner subject inventory

The following classes define organizations, permissions, roles, curriculum,
content, publication, provider prompts or aggregate evaluation fixtures and
have no learner/user/membership link except through a record already mapped
above: `organizations`, `branches`, `permissions`, `roles`, `cohorts`,
`cohort_trainers`, `role_permissions`, `ai_personas`, `persona_versions`,
`roleplay_scenarios`, `scenario_versions`, `scenario_objectives`, `programs`
and the curriculum/content/publication component tables when the actor-link
columns listed above do not match the target. `oauth_clients`,
`prompt_versions` and `evaluation_runs` are platform configuration/evaluation
records, not learner records.

## Operator procedure

1. Establish the requester's identity and organization scope outside the API.
   Record any legal hold or jurisdictional exception before acting. Do not use
   a learner-supplied organization or user ID as authority.
2. Select the operator's organization context and complete fresh password
   confirmation. Confirm
   `privacy.export.request` or `privacy.erasure.request` permission.
3. For access, call `POST /api/v1/operator/privacy/exports` with the target
   learner membership and a new UUID/ULID `Idempotency-Key`. Reuse the same key
   only for an exact retry. Store any downloaded response in an approved
   restricted case system; browser/API caching is disabled.
4. Review `inventory_version`, every `record_group`, its count, export and
   erasure dispositions, and the excluded-sensitive-values declaration.
5. For erasure, call `POST /api/v1/operator/privacy/erasures` with a new key.
   The database disposition commits atomically. Prior export payloads are
   redacted as part of that transaction.
6. Poll the returned erasure URL. `completed` means every linked voice receipt
   is complete; `partially_completed` means durable deletion work remains;
   `restricted` requires reviewed operator/legal handling. Replaying the exact
   request does not create duplicate receipts or repeat completed data writes.
7. If the learner used offline mode, instruct them to reconnect and sign out or
   explicitly discard the protected offline profile on every device. Record
   that follow-up separately because the server cannot enumerate a disconnected
   IndexedDB/Android WebView store.

Never paste export bodies, memory facts, identity hints, audio, transcripts,
credentials or object keys into tickets, logs, analytics or audit reasons.

## Failure and recovery semantics

- Database actions, the erasure receipt, memory deletion events, membership
  closure, credential revocation state and voice intents share one transaction.
  A database exception rolls them all back before object storage is called.
- Voice storage/provider deletion runs only after commit. Failure leaves the
  key and a non-terminal receipt available for leased retry; it never marks the
  object deleted optimistically.
- An exact idempotency replay returns the original request. Reusing a key for a
  different target or actor is a conflict.
- Tenant mismatch and missing permission remain generic 404 responses.
- Existing immutable learning, conversation, consent, audit and governance
  database protections remain active during erasure.

## Known limitations and production gates

- Export generation is synchronous. It has no silent row cap, so production
  capacity/latency limits and an approved encrypted case-delivery mechanism
  must be measured before large-scale use.
- Global identity deletion, phone-number reassignment, identity hold periods
  and closure of a user with no remaining memberships are deliberately not
  inferred from an organization-membership request.
- Password credentials and usernames are global identity authority and are not
  changed by an organization-scoped erasure. A distinct global-account closure
  workflow must decide their disposition and the 365-day username reuse hold.
- Pre-cutover MFA records may protect another membership. Cutover clears their
  secret material globally; a later reviewed cleanup decides physical removal.
- Device-local offline data cannot be remotely wiped while the device is
  disconnected. Server revocation prevents new authority but is not proof that
  a lost device destroyed its encrypted cache.
- REL-01 verifies deletion/tombstone aggregate preservation through a
  non-production logical dump/restore drill. Production backup access,
  encrypted retention, WAL/PITR recovery and jurisdiction-specific legal holds
  remain production work.
- Consolidated development review is not independent privacy/security/tenancy
  review or production authorization.

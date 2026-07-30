---
status: accepted
date: 2026-07-30
deciders:
  - S R Bhandary as Technical Decision Owner
  - S R Bhandary as Security Reviewer
  - S R Bhandary as Privacy Reviewer
  - S R Bhandary as Tenancy Reviewer
consulted:
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: false
approval_evidence:
  - APP-0008
approval_reference: Codex task 019fb263-fe9a-77f0-a1db-e3d563707715
---

# ADR-0006: Offline retention, isolation and conflict policy

## Status

Accepted on 2026-07-30 under
[APP-0008](../APPROVALS.md#app-0008). The approved proposal SHA-256 is
`242a3cff4a5822ebb986515b670ed7c5ed0d243aa86622d80a3254892d348fbb`.

This ADR resolves DR-0004 and authorizes the policy boundary for PR-007
implementation. It does not accept PR-007, authorize PR-008, approve
production, or authorize recording upload, offline AI or provider processing.

## Context

PR-007 introduces a service-worker application shell, downloaded deterministic
missions, an IndexedDB outbox and reconnect synchronization. Those capabilities
place organization-scoped curriculum and learner progress on a device outside
the server's immediate authorization boundary.

The decision must reconcile:

- legitimate offline work must not be silently lost;
- authentication credentials must never enter browser storage;
- one learner's data must never appear to another learner on a shared device;
- logout, account changes, revocation and emergency suspension must fail
  closed;
- PostgreSQL remains authoritative for attempts, scores, mastery and review;
- browser clocks and client-provided sequence numbers are not authoritative;
- raw learner audio remains volatile and local-only under PR-006; and
- web storage cannot guarantee timed deletion while the application is not
  running.

## Decision

### Data classification and storage boundary

PR-007 defines three storage classes:

1. **Public application assets:** hashed application-shell files, reviewed
   interface catalogues, fonts and non-user-specific offline error pages may
   use Cache Storage.
2. **Protected offline mission data:** the exact immutable publication pin,
   instructions, transcript, deterministic questions, curated help, model
   audio, bundle manifest and authorization lease use an encrypted IndexedDB
   partition.
3. **Protected learner outbox data:** immutable local command envelopes and
   their visible sync state use the same encrypted per-account partition.

The service worker must not blanket-cache API responses. Authentication,
identity, session, authorization, problem, personalized home/progress and
mutation responses are network-only unless an exact PR-007 contract explicitly
extracts an allowlisted field into protected storage.

The offline store must never contain:

- session cookies, CSRF secrets, bearer or refresh tokens, OTPs, recovery
  material or native secure-storage credentials;
- raw learner audio, blob URLs, transcripts of learner speech or device labels;
- AI chat, provider payloads or generated responses;
- phone numbers, email addresses, learner names or organization display names;
- logs containing curriculum bodies, answers or personal learner data.

PR-007 may retain the existing bounded local-capture receipt only inside its
immutable attempt command. It remains non-authoritative and contains no audio.

### Personal-device opt-in and shared-device rule

Persistent offline learning is off until the learner explicitly enables it
after a localized warning that:

- downloaded lessons and unsynchronized progress remain on this browser or
  device;
- browser or operating-system users with access to the same profile may be
  able to interfere with stored data;
- logout or removing offline data can discard unsynchronized work; and
- the learner should not enable downloads on a public or shared device.

An organization setting may disable persistent offline learning for its
memberships. It may not silently enable it. A learner who identifies the
device as shared receives application-shell caching only; protected mission
downloads and outbox persistence remain disabled.

### Partitioning and encryption

Each enabled offline profile receives a random opaque partition identifier.
It is not derived from a user, phone number, organization or membership
identifier. The partition binds internally to the exact authenticated user,
membership, organization and enrollment publication pin that created it.

Protected records are encrypted with authenticated encryption using a
non-exportable Web Crypto key generated per partition. Record type, schema
version, partition identifier and immutable record identifier are bound as
additional authenticated data. Unencrypted indexes contain only opaque
identifiers, schema/version data, safe timestamps and sync-state categories.

Deleting the partition key is the first purge action so protected data becomes
inaccessible even if later physical cleanup is interrupted. Encryption limits
casual disk disclosure; it does not claim to defeat malicious same-origin
JavaScript, a compromised browser profile, an unlocked device or an operating
system administrator.

No storage partition may be opened merely because a caller supplies an
identifier. Online opening requires the authenticated exact user and active
membership. Offline reopening requires the existing same browser profile,
unexpired offline authorization lease and exact partition binding.

### Offline authorization lease

Every mission bundle includes a server-signed, purpose-specific authorization
lease bound to:

- opaque lease and bundle identifiers;
- authenticated user, membership and organization identifiers;
- enrollment, curriculum owner, publication and mission identifiers;
- bundle schema and content hashes;
- issue and expiry instants; and
- the server policy version.

The lease is not an API credential and cannot authorize an online request.
Its maximum lifetime is 24 hours. Offline mission access fails closed after
expiry until the same account reauthenticates online and the server revalidates
membership, enrollment pin, publication eligibility and suspension state.

Reconnect synchronization always performs current server authorization.
An unexpired offline lease does not bypass account revocation, inactive
membership, changed enrollment state, rights failure or emergency suspension.
A rejected command remains visible in a protected conflict state until the
learner explicitly discards it or an approved recovery path resolves it.

### Retention and deletion

- Public hashed application assets may remain until version replacement,
  browser eviction or explicit site-data removal.
- A protected mission bundle expires no later than 30 days after its most
  recent successful online revalidation. The application purges expired
  bundles on startup, activation and successful online contact.
- An accepted outbox command and its protected payload are deleted immediately
  after the server returns the exact idempotent receipt and the client commits
  that receipt locally.
- Pending or conflicted commands are retained for at most 30 days in an active
  state. At that point the partition becomes read-only and requires online
  reauthentication to synchronize or explicit learner confirmation to
  discard. The application must not silently delete acknowledged
  `saved-on-device` work merely because it became old.
- When the application is not running, the web platform cannot guarantee a
  timed purge. The product notice must state this limitation. Cleanup runs at
  the next service-worker/application activation.
- Account closure or a confirmed server security reset destroys the partition
  key on the next contact and schedules physical deletion without attempting
  synchronization.
- A learner can remove downloaded missions at any time. If pending work
  depends on a bundle, removal requires a specific warning and explicit
  confirmation or a successful sync first.

Retention values are centralized policy/configuration with the stated values
as maximums. Extending them requires new privacy and security approval.

### Logout, session expiry and account switching

When logout begins:

1. stop new local commands and concurrent synchronization;
2. if online, attempt one bounded foreground sync for the same authenticated
   account;
3. if pending work remains, show its count and require an explicit choice to
   cancel logout or permanently discard that work;
4. delete the local partition key and lock account-scoped application state;
5. revoke the server session and clear its cookie or mobile token family; and
6. remove the protected database, dependent media and service-worker account
   state.

Logout must not claim success while retaining a readable protected partition.
If physical deletion is interrupted, key deletion still makes the records
inaccessible. If server revocation fails after local key deletion, the
application remains locked, reports that logout is incomplete and retries
revocation without restoring the deleted offline partition.

While offline, the web client can remove the partition key immediately but
cannot prove that an HttpOnly server session was revoked. It records only a
safe `revocation-required` marker, keeps the application locked and completes
server logout before allowing the browser profile to resume or select another
account. It must describe this state as local data removed and logout pending,
not as completed logout.

An expired session locks the partition rather than submitting under a new
identity. Online reauthentication may reopen it only for the same user and
membership after current authorization succeeds. A different account,
membership switch, remote logout, token-family revocation, account closure or
security reset purges the old key before the next account's data is shown.
No offline command is ever replayed using another account's credentials.

### Outbox and authoritative conflict rules

Each offline completion is an immutable, versioned command envelope with:

- a client-generated UUID/ULID command identifier and idempotency key;
- exact user, organization, membership, enrollment, publication, mission,
  bundle and policy-version bindings;
- an ordered set of deterministic responses and the bounded capture receipt;
- dependencies and a canonical request fingerprint; and
- non-authoritative device-created time used only for learner display and
  diagnostics.

The server assigns authoritative attempt numbers, scores, mastery, review
timing and accepted timestamps. Client clocks, local sequence numbers and
cached projections never override server state.

Synchronization follows these rules:

- one offline mission completion is accepted atomically or rejected atomically;
- exact replay returns the original result and creates no duplicate attempt;
- changed payload under the same identifier conflicts;
- dependency order is explicit and deterministic;
- multiple devices may contribute distinct valid attempts;
- server acceptance order determines first/latest evidence under the existing
  PR-006 rule; best remains the deterministic highest score with the existing
  tie-break;
- no last-write-wins merge is permitted for attempts, mastery, enrollment,
  publication pins or other authoritative records;
- stale, unauthorized, malformed or policy-incompatible commands receive a
  stable safe problem type and remain visibly `needs-attention`; and
- pending or rejected local work must not create attendance, mastery or
  completion claims until the server accepts it.

A current-state rejection is not silently rewritten as success. The client
retains the protected command for the bounded recovery state and explains that
the work was saved locally but not accepted by the server.

### Cache and schema migration safety

Application-shell caches are content-addressed and activated atomically. A new
service worker may delete only cache names owned by this application and no
longer referenced by an active version.

IndexedDB schema upgrades use a versioned, restartable migration. A migration
must validate and decrypt the old record, write the new record, then switch the
active schema marker. Failure leaves the old partition readable by the prior
compatible application rather than clearing it or reporting success.

Two tabs or workers coordinate through one lease/lock per partition. Only one
sync owner may send a command at a time; server idempotency remains mandatory
because browser locking is not authoritative.

## Alternatives considered

### Cache protected API responses directly

Rejected because it mixes authentication and personalized responses with a
cache whose authorization, expiry and account-switch behavior is difficult to
audit.

### Store plaintext data partitioned by user identifier

Rejected because identifiers expose account relationships and physical cleanup
can be interrupted. Partitioning without cryptographic deletion is too weak
for logout on shared devices.

### Purge every pending command automatically after a fixed interval

Rejected because the application would destroy work it had already described
as saved on the device. Aging instead locks new offline work and requires
reauthentication, sync or explicit discard.

### Keep offline data across logout for convenience

Rejected because a subsequent user of the same browser could inherit protected
content or cause another learner's commands to replay.

### Last-write-wins synchronization

Rejected because browser clocks are untrusted and authoritative attempts,
mastery and enrollment state must remain append-only or server-derived.

## Consequences

### Positive

- Shared-device and account-switch behavior fails closed.
- Credentials and raw learner audio remain outside offline storage.
- Cryptographic deletion makes logout robust against interrupted cleanup.
- Server authority, idempotency and deterministic evidence rules are
  preserved across reconnect.
- Legitimate pending work is visible and never silently converted, merged or
  discarded.

### Negative and trade-offs

- Offline use requires explicit trusted-device consent and expires after a
  short authorization lease.
- Web encryption protects stored bytes but cannot protect an unlocked,
  compromised same-origin application.
- Emergency or tenancy changes may cause locally completed work to require
  recovery rather than server acceptance.
- Multi-tab locking, crash-safe migrations and encrypted media storage add
  substantial browser complexity.
- Timed deletion cannot be guaranteed while the PWA is never opened.

## Security, privacy and tenancy impact

Every protected record is bound to the authenticated exact user, organization,
membership, enrollment and immutable publication. Cross-user, cross-membership
and cross-tenant opening, listing, replay, migration and deletion tests are
mandatory.

Logs and telemetry may record only opaque partition/command identifiers,
schema version, safe state category, duration and allowlisted error category.
They must not include curriculum text, learner answers, message contents,
personal identifiers, raw audio, credentials, encryption keys or plaintext
storage payloads.

Independent security, privacy and tenancy specialist review remains mandatory
before production. Consolidated development approval under ADR-0001 is not
independent review.

## Authoritative records and consistency impact

IndexedDB records are local pending evidence, not authoritative learning
records. PostgreSQL becomes authoritative only after a transaction validates
the exact command, current authorization, publication pin, policy version and
idempotency identity, and atomically records the attempt, responses,
submission, summary, mastery, review and receipt.

PR-007 may add a server command/receipt schema and supporting idempotency
records. Any database migration must be additive and must not weaken PR-006
append-only evidence, tenant constraints or replay binding.

## API, client and operational impact

PR-007 will require versioned bundle-manifest, lease-refresh and atomic
offline-completion contracts. OpenAPI and generated clients must change
together. Safe problem types must distinguish expired lease, reauthentication,
authorization rejection, incompatible bundle, changed replay and retryable
service failure without disclosing another tenant's state.

Operations require visibility into aggregate sync outcomes, expiry and
conflict categories without learner payloads. Rollback must leave old
partitions readable by the last compatible client or locked with an honest
recovery message; it must not clear them.

## Master-plan amendment

This ADR does not amend the master plan. It supplies the exact retention,
shared-device, logout and conflict policy required by master-plan §§17, 18,
20.1 and 26 PR-007.

## Approval evidence

[APP-0008](../APPROVALS.md#app-0008) records:

- acceptance date: 2026-07-30, Asia/Kolkata;
- human approver: `S R Bhandary`;
- roles exercised: Technical Decision Owner, Security Reviewer, Privacy
  Reviewer and Tenancy Reviewer;
- exact approved proposal SHA-256:
  `242a3cff4a5822ebb986515b670ed7c5ed0d243aa86622d80a3254892d348fbb`;
- explicit acceptance of the documented trade-offs;
- authorization of only PR-007 implementation; and
- Codex as author and evidence recorder only, never approver.

The consolidated development roles are not independent specialist review and
do not authorize production.

## Verification

The PR-007 implementation specification must require:

- cross-user, cross-membership and cross-tenant storage-isolation tests;
- confirmation that credentials, raw audio and personal identifiers never
  enter Cache Storage or IndexedDB;
- encryption, tamper rejection and key-first purge tests;
- personal/shared-device opt-in tests;
- logout with empty, synced, pending and interrupted-cleanup states;
- remote revocation, account switch, lease expiry and emergency-suspension
  tests;
- exact replay, changed replay, multi-device, multi-tab and concurrent-sync
  tests against PostgreSQL;
- browser offline completion, reconnect, cache-version and crash-safe
  IndexedDB migration tests;
- accessibility tests for every offline/sync/conflict state;
- OpenAPI/generated-client drift checks; and
- `make verify`, `make e2e` and `make build`.

## Follow-up

- Implement and verify only PR-007 under this accepted policy.
- Obtain independent security, privacy and tenancy specialist review before
  production.
- Keep recording upload, consent history, persistent audio, transcription and
  provider processing blocked by DR-0005 and PR-009.

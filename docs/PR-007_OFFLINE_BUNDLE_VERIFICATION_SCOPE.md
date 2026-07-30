# PR-007 offline bundle and idempotent outbox verification scope

## Authority and checkpoint boundary

PR-007 is authorized by [APP-0008](APPROVALS.md#app-0008) under the accepted
[ADR-0006](adr/0006-offline-retention-isolation-and-conflict-policy.md).
PR-006 behavior, ADR-0002 authentication, ADR-0003 tenancy, ADR-0004
publication authority and ADR-0006 offline policy remain authoritative.

PR-007 does not authorize:

- PR-008 conversation or AI/speech-provider orchestration;
- raw learner-audio persistence, upload, caching or synchronization;
- recording consent/retention behavior reserved for DR-0005 and PR-009;
- a second scoring, mastery or review authority in the browser;
- production deployment or checkpoint acceptance; or
- curriculum, model-audio or translated-interface approval.

## Vertical slice

One authenticated learner can:

1. explicitly identify the browser as a personal device;
2. download the exact currently pinned deterministic mission and verified
   model-audio bytes;
3. complete its knowledge check without network connectivity;
4. see that the completion is saved on the device but not yet authoritative;
5. reconnect and synchronize one atomic immutable completion command; and
6. receive the exact ordinary PR-006 result without a duplicate attempt.

## Server contracts

### Mission bundle

`GET /api/v1/missions/{mission_id}/offline-bundle` requires the authenticated
exact learner organization context and `enrollment_id`.

The response contains:

- schema `global-english.offline-mission-bundle.v1`;
- opaque bundle and lease identifiers;
- exact learner user, membership and organization binding;
- exact enrollment, curriculum owner, version, publication and mission
  binding;
- publication manifest, mission definition and policy versions/hashes;
- issue and expiry instants with a maximum 24-hour lease;
- the ordinary learner mission payload;
- the deterministic correct option required for local result guidance;
- verified model-audio asset ID, URL, MIME type, content hash, byte length and
  duration; and
- an opaque signed lease token covering the canonical manifest.

The bundle endpoint is `private, no-store`. The application—not an HTTP
cache—places its allowlisted fields into protected storage.

### Atomic offline completion

`POST /api/v1/offline-completions` requires:

- the ordinary authenticated exact organization context;
- CSRF or mobile bearer authentication as applicable;
- a UUID/ULID `Idempotency-Key`;
- the exact bundle and signed lease;
- a client attempt UUID;
- the check ID, selected option ID and explicit nullable bounded local-capture
  receipt; and
- no raw audio, score, mastery, attempt number or client-authoritative time.

The server:

1. validates the lease signature, purpose, schema and expiry;
2. binds it to the exact authenticated user, membership and organization;
3. revalidates the current enrollment pin, publication and suspension state;
4. locks and fingerprints the outer completion command;
5. starts, responds to and submits one PR-006 attempt inside one outer
   PostgreSQL transaction using deterministic derived idempotency keys;
6. records the outer command only after the complete authoritative result
   exists; and
7. returns the ordinary `LearnerMissionResult`.

Exact replay returns the original result. A changed payload conflicts. Any
late failure rolls back the attempt, response, submission, mastery, review,
audit, outbox and command evidence together.

## Browser storage

- Cache Storage owns only same-origin public hashed shell assets, reviewed UI
  catalogues, fonts, the root document and a non-personal offline fallback.
- API, authentication, identity, personalized progress, error and mutation
  responses are network-only.
- Protected mission metadata, verified model audio and pending commands use
  IndexedDB database `global-english-offline-v1`.
- Each account partition has a random opaque identifier and non-exportable
  AES-GCM key.
- Stored bindings are SHA-256 digests over exact user, membership and
  organization identifiers; raw identifiers are inside encrypted records only.
- Record type, schema, partition and record identifier are authenticated as
  additional data.
- Raw audio, credentials, names, phone numbers, organization display names
  and AI/provider data are rejected from offline records.
- Model audio is verified against the bundle byte length and SHA-256 before
  storage.

## Client states and transitions

Visible localized states are:

- offline unavailable;
- personal-device confirmation required;
- downloading;
- downloaded and available offline;
- offline;
- saved on device;
- syncing;
- synchronized;
- needs attention; and
- offline authorization expired.

An offline completion never displays authoritative attempt number, mastery,
review time or progress until server acceptance. Local correct/incorrect
guidance is derived only from the signed protected bundle and is labeled as
pending synchronization.

Reconnect starts one same-account sync owner. Commands remain immutable and
ordered. Success deletes the protected command after committing the exact
receipt. Retryable failures remain pending; authorization, expiry, changed
replay and policy failures become `needs-attention`.

## Logout and account isolation

- New commands and synchronization stop before logout purge begins.
- Pending work requires successful sync, logout cancellation or explicit
  permanent-discard confirmation.
- The partition key is deleted before server revocation and physical cleanup.
- Offline server logout remains visibly pending and blocks account switching.
- A different user or membership can never open, list or replay the prior
  partition.
- Remote revocation, account closure and security reset destroy access at the
  next contact.

The current application has no learner-facing logout control. PR-007 must add
the offline purge boundary to authentication adapters and tests so every
existing or later logout caller receives it; adding account-management UI is
not required by this checkpoint.

## Migration and recovery

- Service-worker caches are versioned and content-addressed.
- Activation deletes only superseded cache names owned by this application.
- IndexedDB v1 creation and every later schema upgrade are restartable.
- A failed migration leaves the prior committed records intact and produces an
  honest recovery state.
- Browser eviction is possible; the interface must not describe unsynced work
  as server-saved.

## Required verification

### Backend

- bundle signature, expiry, exact identity/membership/tenant/pin binding and
  current-authority rejection;
- raw-audio and caller-authority field rejection;
- atomic success, exact replay, changed replay and late-failure rollback;
- concurrent exact replay creates one submitted attempt;
- ordinary PR-006 first/latest/best, mastery and review rules remain unchanged;
- cross-user, same-tenant-other-learner and cross-tenant denial; and
- safe no-store problem responses and OpenAPI/generated-client parity.

### Browser and client

- personal/shared-device choice;
- encrypted round trip, ciphertext tamper rejection and non-exportable key;
- exact account isolation and key-first purge;
- no credential, raw audio, names or display-name persistence;
- audio hash/length verification;
- online download, offline reload/completion and reconnect sync;
- ambiguous sync retry with one idempotency key and no duplicate;
- visible accessible states in all six UI locale modes;
- service-worker API/auth exclusion and cache-version migration; and
- multi-tab single-owner behavior with server idempotency as final authority.

### Root commands

```text
make verify
make e2e
make build
```

`make ai-eval` is not applicable because prompts, models, providers and AI
orchestration remain unchanged.

## Exit gate

PR-007 is implemented only when:

- a downloaded deterministic mission completes offline;
- reconnect creates exactly one authoritative result and loses no acknowledged
  local command;
- logout and account switching cannot expose or replay another account's
  protected data;
- every required automated check passes; and
- status, decision and quality evidence records the exact limitations.

PR-007 was accepted under APP-0009 at the exact implementation commit
`e0e2f1b90e0eb4ecf680f5243b57ec0731c32648`. That acceptance does not
authorize PR-008, production or a later implementation checkpoint.

## Implementation result

The bounded slice is implemented and repository-verified on 30 July 2026:

- exact replay and a two-worker concurrent exact replay create one submitted
  PostgreSQL attempt and one outer offline command;
- the full PostgreSQL suite passes 164 tests and 1,883 assertions;
- workspace unit suites pass 31 localization, 9 design-system and 54 web
  tests;
- 8 Chromium journeys cover no-storage-before-opt-in, encrypted storage,
  offline completion, one reconnect submission, cache exclusions and
  ciphertext-tamper rejection;
- OpenAPI/generated-client drift, formatting, PHPStan, dependency audits,
  locale governance, lint, type checking and production builds pass; and
- GNU Make is absent on this workstation, so the exact documented Makefile
  constituents were run rather than the wrapper targets.

This result is implementation evidence. Checkpoint acceptance is recorded
separately in APP-0009; production and PR-008 remain unauthorized.

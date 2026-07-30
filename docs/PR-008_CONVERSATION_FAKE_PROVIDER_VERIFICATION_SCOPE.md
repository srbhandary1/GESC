# PR-008 conversation domain with deterministic fake providers

## Authority and boundary

PR-008 is authorized by [APP-0010](APPROVALS.md#app-0010) against the accepted
PR-007 baseline. It implements only the master-plan §26 PR-008 checkpoint:

- personas, scenarios, sessions and turns;
- project-owned STT, conversation-model and TTS provider ports;
- deterministic fake adapters;
- a deterministic turn state machine;
- a one-way event/status stream; and
- privacy-safe cost and latency record skeletons.

PR-008 does not authorize real provider calls, provider SDKs, audio upload or
persistence, transcript-retention policy, PR-009, production deployment, or AI
authority over deterministic learning records.

## Vertical slice

The slice proves one authenticated Friend turn end to end:

1. the learner starts a `friend` session using the exact current development
   persona and scenario keys;
2. the server snapshots their immutable version identifiers and hashes;
3. the learner submits a bounded typed prototype utterance with an idempotency
   key;
4. the deterministic fake STT, model and TTS adapters run behind project-owned
   interfaces;
5. the state machine records ordered status events and bounded retry evidence;
6. the response returns the fake transcript and assistant reply to the same
   request;
7. the event endpoint returns the stored safe state history as
   `text/event-stream`; and
8. exact replay returns the same deterministic result without adding a turn,
   event or cost row.

The fake TTS adapter returns text-only synthesis metadata. It creates no audio
bytes, URL, file or object-storage record.

## Persistence and privacy boundary

Every conversation resource is scoped by organization and exact learner
membership. The schema stores:

- immutable persona and scenario version snapshots;
- session/turn identity and state;
- SHA-256 digests of prototype utterance, transcript and reply content;
- ordered safe status events with no content payload; and
- provider/model version, attempt, latency, usage and zero fake cost.

The schema does not store learner utterance text, assistant reply text, raw
audio, transcript content, generated audio, names, phone numbers, organization
display names, credentials, provider payloads or prompt bodies. Prototype text
is held only in request memory and is never written to logs, audit or outbox.

This digest-only fake boundary avoids inventing a production conversation-text
retention policy. A later checkpoint must obtain the applicable privacy and
security decision before persisting real transcripts, replies or audio.

## Tenancy and authority

- Authentication and organization context use the existing first-party
  middleware and `LearnerAuthority`.
- A session belongs to the authenticated learner's exact active membership.
- Persona and scenario resolution is restricted to the current organization
  and development-only versions.
- Session, turn, event and cost rows carry `organization_id` and composite
  foreign keys.
- A learner cannot supply organization, membership, provider/model, cost,
  state, sequence or version hashes.
- Same-organization other-learner and cross-organization reads/writes fail
  closed without revealing existence.

Conversation output is advisory prototype data. It cannot update learner
attempts, item responses, mastery states, review schedules, enrollments or
curriculum publications.

## State and retry contract

The allowed successful state order is:

```text
accepted
→ transcribing
→ generating_reply
→ synthesizing
→ completed
```

A provider failure may add one `turn.provider_retrying` event while remaining
in that stage before the second and final attempt. Exhaustion transitions once
to `failed`. Invalid, out-of-order or terminal-state transitions fail closed.
Client clocks and client-supplied state are never authoritative.

## API contract

Authenticated, organization-scoped endpoints:

```text
POST /api/v1/conversation-sessions
POST /api/v1/conversation-sessions/{session}/turns
GET  /api/v1/conversation-turns/{turn}/events
```

Mutations require a UUID/ULID `Idempotency-Key`, strict allowlisted JSON and
`private, no-store` responses. The event endpoint is `private, no-store`,
returns only safe state metadata and uses `text/event-stream`. No endpoint
accepts multipart data, media, base64 audio, provider configuration or
organization authority.

## Verification

Required automated evidence:

- schema and composite-foreign-key tests on PostgreSQL;
- exact learner, same-tenant other-learner and cross-tenant isolation tests;
- session and turn exact replay, changed replay and concurrency tests;
- complete successful state order and deterministic fail-once/exhausted retry
  tests;
- assertions that fake provider types do not escape project interfaces;
- assertions that stored rows, audit, outbox and HTTP logs contain no
  prototype utterance/reply content or audio;
- event-stream media type, ordering, ownership and no-store tests;
- proof that conversation processing does not modify deterministic learning
  tables;
- frontend unit and Chromium coverage for AI disclosure, fake-provider notice,
  one typed Friend turn, reply, state history and failure state;
- OpenAPI and generated-client drift checks; and
- repository formatting, static analysis, tests, audits and production build.

`make ai-eval` remains not applicable because PR-008 introduces deterministic
fakes only and no prompt, model or real provider selection.

## Implementation evidence

The 30 July 2026 local verification used fresh native PHP 8.4.23, Node.js
24.18.1, pnpm 11.18.0 and Composer 2.10.2:

- Composer validation/audit and pnpm frozen installation/audit passed with no
  reported advisories;
- Pint and level-8 PHPStan passed;
- SQLite-safe tests passed: 42 tests and 277 assertions;
- the full PostgreSQL integration suite passed: 169 tests and 1,981
  assertions;
- workspace tests passed: 31 localization, 9 design-system and 57 web tests;
- strict OpenAPI 3.1.1 validation, policy checks and generated-client drift
  checks passed for document version 0.6.0;
- locale governance passed for all six catalogues with the five expected
  pending-independent-review warnings;
- all 9 Chromium journeys passed, including the disclosed deterministic
  conversation flow and accessibility scan; and
- Laravel production optimization/cleanup and the Vite production build
  passed with 74 transformed modules.

GNU Make is not installed on this workstation. The exact `make verify` and
`make build` constituent commands were therefore executed from the Makefile;
`pnpm run e2e` executed the browser target directly. The pinned Playwright
Chromium 151.0.7922.34 runtime was installed in the current user's cache.

No real provider call, audio capture/upload/persistence, public listener,
production deployment or deterministic learning-record mutation was part of
verification.

## Exit gate

PR-008 is implemented only when:

- one complete Friend turn works end to end using deterministic fakes;
- state transitions, exact replay and retries are deterministic and tested;
- every resource is tenant/learner scoped;
- no conversation content or audio is persisted;
- no deterministic learning authority changes;
- all required repository-controlled checks pass; and
- status, decision and quality records preserve limitations.

Implementation and successful tests do not accept PR-008, authorize PR-009 or
authorize production. Exact-commit checkpoint acceptance remains a later
Accountable Product Owner action.

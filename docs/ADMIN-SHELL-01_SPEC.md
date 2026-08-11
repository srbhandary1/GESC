# ADMIN-SHELL-01 bounded specification

**Status:** Complete under ADR-0014, D-0130 and D-0131
**Recorded:** 9 August 2026
**Checkpoint boundary:** Read-only operator shell and derived safe reads only
**Production/public release:** Not authorized

## Objective

Deliver the first complete ADMIN vertical slice: a responsive operator shell
that shows only server-authorized work, loads each panel independently,
supports bounded federated search across existing safe descriptors and lets an
authorized operator inspect an audit event without exposing raw private
content.

This checkpoint cites the ADMIN-SHELL-01 rows of
[ADMIN_GOVERNANCE_MATRIX.md](ADMIN_GOVERNANCE_MATRIX.md). It creates no domain
schema and starts none of the organization, learner, communications, groups,
activities, review, CMS, entitlement or configuration checkpoints.

## Authority and permission contract

- Every route uses the matrix `O-READ` profile: first-party authentication,
  web-only transport, active membership-derived organization context,
  existence-hidden denial and `private, no-store` responses.
- The shell never authorizes by role name. Role keys remain client navigation
  hints only; the server evaluates the current membership and atomic
  permissions for every manifest entry, panel and search source.
- No permission is seeded by this checkpoint. In particular,
  `reports.export` remains a documentation-only reservation because the shell
  adds no export workflow.
- Content visibility requires any existing
  `OperatorContentAuthorization::CONTENT_READ_PERMISSIONS` key.
- Audit visibility requires existing `audit.read`.
- Support visibility requires existing `support.diagnostics.read`.
- A caller with none of those read authorities receives the ordinary
  existence-hidden operator denial. Privacy mutation permissions alone do not
  imply shell read access.

## API contract

### `GET /operator/overview`

Returns only:

- the active organization identifier, display name and timezone;
- `generated_at`;
- ordered navigation keys selected from `overview`, `learning`, `operations`
  and `safety`; and
- ordered panel keys selected from `pilot_analytics`, `review_queue`,
  `publish_status`, `support_diagnostics` and `audit`.

`overview` is present for every authorized shell caller. `learning` and its
content panels require content-read authority. `operations` and support
diagnostics require support authority. `safety` and audit require
`audit.read`. Pilot analytics remains visible only when its existing
authorization accepts audit or content authority.

The response exposes neither permission keys nor unavailable navigation
entries. It contains no learner count or domain data; panel endpoints remain
independently authoritative and independently loadable.

### `GET /operator/search`

Accepted query fields are exactly:

- `q`: trimmed Unicode text, 2–80 characters with control characters rejected;
- `limit`: optional integer from 1–25, default 20; and
- `cursor`: optional encrypted opaque continuation token.

The fixed source order is:

1. curriculum-version descriptors;
2. curriculum-publication descriptors;
3. stuck-attempt diagnostic descriptors;
4. recoverable-voice-failure descriptors;
5. open voice-deletion descriptors; and
6. safe privileged audit descriptors.

Each source performs its own atomic permission check before its table is
queried. Results are organization scoped in SQL and contain only a fixed kind,
stable record identifier, section key, primary safe label, secondary safe
descriptor and optional timestamp. Search never returns learner identity,
answers, support-note bodies, message bodies, transcript/audio fields, object
keys, audit payloads or provider payloads.

The encrypted cursor is bound to its purpose, organization, normalized query,
authorized source set, source position and offset. Invalid, cross-query,
cross-organization or malformed cursors fail validation. Results are bounded
without a silent terminal row cap; `next_cursor` is `null` only after every
authorized source is exhausted.

Search input, results and cursors are request-only state. They are not written
to the database, browser storage, analytics, audit or application logs.

### `GET /operator/audit-events`

The existing safe action allowlist and `audit.read` authority remain
unchanged. The listing gains an optional encrypted cursor bound to the active
organization and uses descending `(occurred_at, id)` keyset pagination. The
response gains `next_cursor`; it still exposes no payload or private content.

## UI contract

- Non-learner web memberships may enter the operator shell affordance, but
  only the overview response decides which work is visible. Passport bearers
  remain denied by the API.
- The learner primary navigation is hidden inside an operator session. The
  operator shell renders only server-returned task sections.
- The first viewport identifies the current organization and prioritizes safe
  search and exception/work-queue panels rather than generic dashboard chrome.
- Overview failure has a localized retry path. Every authorized panel has its
  own loading, empty, error, retry and ready state; one panel failure cannot
  remove successful panels.
- Search is debounced, also supports explicit submit, rejects fewer than two
  characters without a request, announces progress/results/errors, supports
  cursor-based “load more” and never persists the query.
- Audit rows are keyboard-operable controls. Selecting one fills an adjacent
  labelled detail pane with the same allowlisted fields already returned by
  the listing; no extra authority or hidden data is inferred.
- The shell reflows without horizontal page overflow at 320 CSS pixels and
  enlarged text, has visible focus, minimum 44-pixel controls, semantic
  landmarks/headings and no state conveyed only by color.
- New messages are added to all six UI catalogues. Non-English copies remain
  honest Codex implementation drafts under ADR-0005 and ordinary production
  continues to fall back visibly to approved English.

## Authoritative records, lifecycle and observability

Overview and search responses are derived, non-authoritative projections.
They create no audit event, outbox event, cache record or durable search
index. Existing content, learning, voice, privacy and audit records remain
authoritative under their accepted ADRs.

Every response is private and non-cacheable. Correlation middleware continues
to record safe request metadata only. Search terms, result labels, subject
identifiers and cursors must not be added to logs, metrics properties or error
messages.

## Verification and exit criteria

ADMIN-SHELL-01 closes only when:

1. overview and search have same-tenant, cross-tenant, missing-permission,
   malformed-input and cursor tests on real PostgreSQL;
2. the common operator-route policy continues to pass for both new routes;
3. audit keyset continuation has stable no-duplicate paging tests;
4. component tests prove independent panel failure/retry, permission-aware
   navigation, search continuation and audit drill-down;
5. a critical browser journey proves keyboard use, partial failure, search,
   audit detail, reflow and automated accessibility;
6. OpenAPI 3.1.1 and generated clients change together;
7. locale governance and source-linked hashes pass; and
8. `make verify`, `make e2e` and `make build` pass before checkpoint ledgers
   mark ADMIN-SHELL-01 complete.

`make ai-eval` is not required because this checkpoint changes no prompt,
model, provider or orchestration behavior.

## Completion record

All eight exit criteria passed on 9 August 2026. The implementation added no
migration, feature schema, permission seed, platform capability, provider or
configuration. Final evidence is recorded in
[QUALITY_REVIEW.md](QUALITY_REVIEW.md#admin-shell-01--permission-aware-read-only-operator-workspace):

- 3 focused PostgreSQL tests / 74 assertions;
- 148 web tests within 188 package/web tests;
- 126 SQLite tests / 1,036 assertions and 209 PostgreSQL tests / 2,851
  assertions in `make verify`;
- 12/12 Chromium journeys in `make e2e`; and
- successful `make build` with the pre-existing chunk-size warning.

ADMIN-ORG-01 is next and requires its own bounded specification. This
completion record does not authorize production/public release or any later
ADMIN domain slice.

# Codebase gap remediation plan

**Status:** Ordered remediation checkpoints, ADMIN-GOV-01 and ADMIN-SHELL-01
complete; ADMIN-ORG-01 is next

**Started:** 8 August 2026

**Authority:** `ENTERPRISE_PRODUCT_MASTER_PLAN.md`, accepted ADRs, and the
repository-wide review requested on 8 August 2026.

## Working rule

Only one checkpoint is active at a time. A checkpoint closes only after its
behavior, tests, documentation and proportional repository verification are
complete. Later checkpoints must not be implemented opportunistically.

## Ordered checkpoints

| Order | ID | Risk addressed | Exit criteria | Status |
|---:|---|---|---|---|
| 1 | LV-01 | Synthetic users, appointments, reviews and rights approvals created by lesson-video import; non-atomic/non-idempotent import | Draft-only caller-idempotent import; verified object writes outside one authoritative DB transaction; pending rights only; no authority/publication/pin fabrication; replay/conflict/failure/tenancy/governed-delivery tests; runbook and evidence corrected | **Complete** — in-scope gates pass; subsequent repository verification and bounded delivery work are complete under PIPE-01 and VIDEO-01 |
| 2 | PRIV-DEL-01 | Failed voice-object deletion can be marked terminal and orphan sensitive bytes | Durable deletion intent/tombstone or outbox; retry-safe deletion; storage key retained until confirmed delete; privacy erasure and scheduled retention share one implementation; fail/retry/concurrency tests; no raw audio/transcript logging | **Complete** — content-free leased outbox, shared deletion service, privacy receipt links and SQLite/PostgreSQL adversarial coverage pass |
| 3 | AND-AUTH-01 | Android bearer auth is incomplete beyond `/me`; refresh/cold-start and callback races; video fetch cannot authenticate safely | One project-owned authenticated fetch path for every Android API/media request; secure-store refresh restoration; single-consumption PKCE callbacks; 401 refresh/retry/revocation behavior; no credential in web storage; Android contract and device tests | **Repository implementation complete** — automated client/native contracts, PostgreSQL token-family tests, Gradle unit/build tasks and APK identity pass; physical DM-01/DM-02 acceptance remains an explicit external REL-01/F0 gate because no device is attached |
| 4 | MFA-01 | Privileged MFA model exists but no usable enrollment/challenge lifecycle is wired | Reviewed MFA enrollment, verification, freshness and recovery/revocation workflow behind the authentication abstraction; privileged curriculum/security actions enforce it; rate-limit/audit/privacy tests and API contracts | **Repository implementation complete** — RFC 6238 TOTP, show-once recovery codes, transactional replay controls, global credential revocation, web-only operator routes, OpenAPI/client/UI support and SQLite/PostgreSQL security coverage conform to DR-0013 Option A; independent production review remains open |
| 5 | PIPE-01 | Dependency audit, formatting, static analysis, lint, typecheck and localization gates are red | Upgrade vulnerable Composer/pnpm locks; resolve Pint/PHPStan/ESLint/TypeScript/localization failures without suppressions; `make verify` green on supported toolchain and PostgreSQL, or each environmental blocker recorded with exact evidence | **Complete** — Composer/pnpm advisories are clear; full Pint, PHPStan level 8, authored-source ESLint, TypeScript, localization, OpenAPI/client drift, SQLite/PostgreSQL and package/web tests pass under the pinned toolchain |
| 6 | VIDEO-01 | Video stream lacks robust range delivery and repeats manifest/query/storage work; web object lifecycle and errors need hardening | Standards-compliant bounded range responses; no full-buffer requirement for large MP4; query/storage work measured and bounded; client object URLs/retries/accessibility fixed; tamper/range/tenant/performance tests and OpenAPI update | **Complete** — verified single-range 200/206/416 delivery, constant-query evidence/catalog reads, bounded local/S3 object access, retryable accessible web/Android playback and OpenAPI 0.15.0 pass the full repository gates |
| 7 | PRIV-DSR-01 | Privacy export/erasure coverage is narrower than the product data model | Data inventory maps every learner-owned/linked table and object; exports and erasure/retention outcomes are complete and deterministic; legal/authoritative retention is explicit; cross-tenant/idempotency/failure tests; operator docs and API contracts updated | **Complete** — `learner-privacy-v1` safe-field inventory, persisted pre-mutation outcome matrix, tenant/multi-membership isolation, credential revocation, memory/export redaction and durable voice deletion reconciliation pass focused PostgreSQL and repository gates; qualified legal and production review remain open |
| 8 | REL-01 | Critical E2E/Android/PostgreSQL/outbox/recovery checks are incomplete or weakly enforced | CI runs PostgreSQL integration, critical web E2E and Android unit/build checks; outbox dispatch/retry/dead-letter observability exists; load/backup/restore drills have executable evidence; release gates fail closed | **Complete** — separate critical jobs, self-validated aggregate gate, bounded dead letters/operator recovery, threshold-bearing readiness load and schema/migration/deletion-state restore evidence pass |
| 9 | MAINT-01 | Duplicated query/validation/test-fixture code and stale status material increase change risk | Bounded deduplication with unchanged behavior; indexes/query plans reviewed from measurements; stale/duplicate docs reconciled; public interfaces and non-obvious decisions documented; full verification green | **Complete** — shared header-only idempotency validation, shared synthetic authorization fixtures, self-bootstrapping isolated test storage, three measured diagnostics indexes and current-only status documentation pass the full gate |

## Planned follow-on operator parity program

[ADMIN_REFERENCE_PARITY_PLAN.md](ADMIN_REFERENCE_PARITY_PLAN.md) inventories
the authenticated reference admin portal and is the approved secure functional
parity specification under
[DR-0014](DR-0014_ADMIN_REFERENCE_SCOPE_AND_POLICY_REQUEST.md) Option A,
[APP-0039](APPROVALS.md#app-0039) and
[ADR-0014](adr/0014-secure-reference-admin-functional-parity.md). It does not
reorder the remediation table above. MAINT-01, the governance-only
ADMIN-GOV-01 gate and the read-only ADMIN-SHELL-01 slice are complete;
ADMIN-ORG-01 is the next authorized implementation checkpoint.

ADMIN-GOV-01 froze the capability, permission, ownership, retention and route
contract in
[ADMIN_GOVERNANCE_MATRIX.md](ADMIN_GOVERNANCE_MATRIX.md) without adding a
feature schema or route. Option A authorizes only its exact optional product
scope and ordered bounded program; it does not authorize legacy-data import,
provider/payment spend, deployment, production release, advance checkpoint
acceptance or waiver of retained later decisions and independent reviews.

## Checkpoint protocol

For each checkpoint:

1. read the master plan, current status, relevant accepted ADRs and this plan;
2. inspect the affected implementation and tests before editing;
3. record any genuine security, privacy, tenancy, authoritative-record or
   foundation-schema ambiguity as a decision request and stop that path;
4. implement one complete vertical slice with idempotency, tenant scope,
   authorization, transaction boundaries and failure behavior where relevant;
5. update tests, OpenAPI/generated contracts for API changes, and the status,
   decision and quality ledgers; and
6. run `make verify` plus `make e2e`, `make ai-eval` or `make build` when the
   checkpoint changes those surfaces.

## LV-01 bounded specification

The legacy lesson-video importer may validate and copy gitignored source media,
create operator-attributed draft content/translation, snapshot the draft graph,
and create initial pending-rights revisions. It must never create identities,
memberships, authority appointments, appointment acceptance, review decisions,
approved rights, publications, cohorts, enrollments or pins. Publication stays
blocked until distinct qualified humans act through the existing ADR-0004
governance commands.

No later checkpoint is part of LV-01.

## PRIV-DEL-01 bounded specification

Learner deletion, withdrawal, scheduled retention and operator erasure record
the same tenant-bound deletion receipt and content-free outbox intent before
source-object deletion begins. Storage work runs after commit. A failure keeps
the object key, recording state and unpublished work intact for capped-backoff
retry; only a confirmed delete clears the key and writes completion evidence.
Privacy erasure links to the exact receipt rather than duplicating storage
logic.

Provider deletion, backup restore/tombstone drills and the broader learner-data
inventory remain later authorized work. No API response shape changed in this
checkpoint.

## AND-AUTH-01 bounded specification

Production Android uses one shared request transport for learner, conversation,
operator, event-stream and private-media calls. It accepts only the configured
API origin and `/api/v1` path boundary, replaces caller authorization with the
memory-only access token, omits cookies and performs at most one serialized
refresh plus exact request replay after a 401. A terminal refresh or rejected
replay clears native authentication and invalidates the shell once; transient
network/server failure preserves the rotating refresh credential for retry.

Cold start restores only through the native encrypted refresh token. The
project-owned native plugin allowlists installation ID, refresh token and PKCE
pending state, durably commits writes, and atomically takes pending state.
Callback redirect/state validation precedes consumption, exchanges are
serialized and repeated native deliveries for the same state are ignored.
Missing or unsafe mobile configuration fails closed instead of falling back to
Sanctum cookies in the WebView.

No API or foundation-schema contract changed. Android private model audio and
lesson video became revocable object URLs after an authenticated whole-body
download; VIDEO-01 subsequently adds the catalog-size/type/body bounds and
normal-browser range behavior recorded below.
Physical DM-01/DM-02 credential-storage and lifecycle evidence remains open in
[PR-014_ANDROID_DEVICE_MATRIX.md](PR-014_ANDROID_DEVICE_MATRIX.md); no physical
device was attached during this checkpoint.

## MFA-01 bounded implementation

The master plan and ADR-0002 require independent privileged MFA, a 15-minute
freshness limit and recovery that is not SMS-only. They do not choose TOTP,
WebAuthn or a cross-platform Passport freshness design. The repository's
existing assertion record belongs only to web authentication sessions even
though operator routes currently accept first-party Passport requests.

[DR-0013](DR-0013_PRIVILEGED_MFA_FACTOR_AND_RECOVERY_REQUEST.md) records the
required factor, recovery, protected-action and platform decisions. Option A
was accepted as written under [APP-0038](APPROVALS.md#app-0038) / D-0123.

MFA-01 implements that decision with user-bound factor, challenge and recovery
records; locked TOTP time-step and one-use recovery consumption; five-minute,
five-attempt challenges; 15-minute scoped assertions; and global web/Android
credential revocation on factor or recovery changes. Operator endpoints are
web-session-only and every mutable operator route additionally requires fresh
TOTP. OpenAPI 0.14.0, generated TypeScript contracts, the web authentication
adapter and the operator-console lifecycle are included. Operational behavior
is documented in [PRIVILEGED_MFA_RUNBOOK.md](PRIVILEGED_MFA_RUNBOOK.md).

TOTP remains non-phishing-resistant, in-app support-assisted reset is not
implemented, and independent security/privacy review remains a production
gate. Cross-platform privileged access still requires a separate decision.

## PIPE-01 bounded implementation

PIPE-01 applies security patch updates to the exact dependency authorities:
`league/commonmark` 2.9.0 in the Composer lock, `js-yaml` 4.3.1 in the pnpm
manifest/override/lock and resolved `nanoid` 3.3.18 in the pnpm lock. It clears
the full Pint and PHPStan level-8 findings, scopes ESLint away from copied
native build output while retaining all authored web/package source, removes
React effect-state lint violations through lifecycle/state ownership changes,
and keeps TypeScript, locale governance, OpenAPI policy and generated-client
drift gates green.

Verification fixture corrections preserve the seeded foundation authority and
exercise PostgreSQL against a writable test-only storage path. Deterministic
spoken-number normalization now handles a lone spoken zero, and the web AI
memory loader preserves the class API receiver; both have regression coverage.
No product API, migration, production configuration, foundation schema or
architecture decision changes in this checkpoint.

The existing production-bundle size warning and optional Rolldown WASM peer
version mismatch are recorded limitations, not suppressed failures. Bounded
large-video delivery and browser object lifecycle were left exclusively to the
subsequently completed VIDEO-01 checkpoint.

## VIDEO-01 bounded implementation

VIDEO-01 keeps ADR-0013's authenticated app-proxy boundary and exact
enrollment/publication pin. The stream accepts one byte range, returns strong
SHA-256 ETags and `Accept-Ranges: bytes`, honors only an exact strong
`If-Range`, and returns RFC problem details plus `Content-Range: bytes */N`
for a valid unsatisfiable range. Malformed and multipart range requests are
deliberately ignored and receive the complete 200 representation.

Private objects are verified sequentially in 64 KiB chunks under the existing
20 MB limit. A generic seekable disk reuses the verified handle without a
temporary full-file spool. S3 performs one full hash-verification `GetObject`
and one exact range `GetObject` guarded by the first response's `ETag` through
`If-Match`; provider types remain inside the project-owned storage adapter.
Measured delivery tests cap the whole authenticated request at 30 database
queries and assert constant component, rights, content and asset query counts.

Normal browsers retain native video loading so cookies and HTTP ranges work
without a JavaScript full-body buffer. Android's project-owned Passport fetch
still creates a revocable object URL, but now validates the catalog byte size,
20 MB maximum, response length and MP4 type while incrementally rejecting an
oversized body. Catalog and playback loading/errors are announced, retryable,
and all video/object URL state is reset on error, source switch and unmount.

This does not add multipart ranges, a trusted verification cache, a CDN/signed
URL path, an Android native streaming data source or production S3/load/device
evidence. Those changes would require separate measurement and, where they
alter revocation/authentication, a reviewed decision.

## PRIV-DSR-01 bounded implementation

PRIV-DSR-01 retains the existing web-only, fresh-TOTP operator endpoints and
scopes every request to one learner membership owned by the current
organization. `learner-privacy-v1` reports all fixed record classes, including
empty classes, and exports safe structured records without the prior 500-row
caps. Credentials, cryptographic digests, raw audio, transcripts, object keys,
provider secret payloads and other-tenant selection identifiers stay excluded.

Erasure first persists the complete per-class count, action and retention basis
inside the authoritative transaction. It deletes target role assignments and
reconstructable error projections, withdraws open enrollments, redacts learner
memory and prior export bodies, ends only the target membership, and revokes
global web/mobile/OAuth credentials without deleting the global user or another
organization membership. Append-only onboarding, immutable learning and
conversation evidence, consent, governed authority, audit and deletion
receipts remain retained. Every recording is linked to the shared ADR-0007
deletion service so object-store failure remains visible and retryable after
commit.

The complete matrix and operator recovery procedure are in
[PRIVACY_DATA_INVENTORY.md](PRIVACY_DATA_INVENTORY.md). Global account closure,
phone reassignment, device-remote-wipe,
jurisdiction-specific legal holds and an encrypted large-export delivery path
remain separate production decisions and evidence gates. REL-01 subsequently
proved aggregate deletion/tombstone state through a non-production logical
restore; production WAL/PITR and backup governance remain open.

## REL-01 bounded implementation

REL-01 separates full PostgreSQL/web verification, production-path Android and
non-production operations drills into required CI jobs. One stable
`release-gate` job runs under `always()` and accepts only exact `success`
results; a repository-owned YAML validator prevents required commands, image
digests, full-SHA action pins or dependencies from silently disappearing.

Voice source-deletion work now has a PostgreSQL-constrained dead-letter state
after five bounded attempts. Dead letters remain unpublished and retain the
private object key plus non-terminal receipt. Content-free health reports
active/due/retrying/dead/stale aggregates, scheduled work exits non-zero when
unhealthy, and only an exact CLI dead letter can be requeued after remediation.
No tenant, learner, recording, object key, audio, transcript or payload is
emitted by the health/requeue commands.

The readiness load harness validates inputs, requires every response and its
p95 threshold, writes pass/fail evidence and returns non-zero on failure. The
logical PostgreSQL drill uses a mode-0600 temporary dump, cleanup traps and
exact source/restore comparisons for column schema, migrations and aggregate
deletion/tombstone state. CI executes both before the aggregate gate.

This closes repository enforcement only. Clean remote CI has not yet run on a
commit containing the working-tree changes. Physical Android device evidence,
signed AAB, representative 2× pilot load, production backup encryption/WAL
PITR, production alert routing, independent reviews and production/public
release remain separate gates.

## MAINT-01 bounded implementation

MAINT-01 centralizes the existing header-only UUID/ULID idempotency contract in
one documented FormRequest boundary and reuses common synthetic permission,
MFA-factor and privileged-session fixtures across the existing PostgreSQL
integration suite. These are behavior-preserving refactors; request bodies do
not gain idempotency authority and production authorization rules do not use
test helpers.

The common test base creates missing isolated storage directories with
requested mode 0700 before Laravel resolves its view compiler path. The
hard-coded PostgreSQL test storage root therefore no longer relies on
pre-existing workstation state.

PostgreSQL plans for the three `SupportDiagnosticsQuery` branches previously
used an explicit Sort after indexes that did not match their full predicate and
order. Three tenant-leading indexes now yield forward/backward index-only plans
without Sort nodes, and executable JSON-plan tests protect that contract. The
voice-deletion timeline keeps status last because it orders three open states
together by request time.

`docs/STATUS.md` is again a current-state document; historical checkpoint
evidence remains in the quality, decision and approval ledgers and Git history.
Exact scope, before/after plans, rollback and limitations are recorded in
[MAINT-01_MAINTENANCE_REVIEW.md](MAINT-01_MAINTENANCE_REVIEW.md).

No endpoint, OpenAPI/generated-client contract, provider, prompt,
authoritative-record rule, tenant relationship or product capability changes.
Representative production-volume query measurement and the retained release
reviews remain outside this maintenance checkpoint.

The complete repository gate passes with 123 SQLite tests / 939 assertions,
206 PostgreSQL tests / 2,777 assertions and 182 package/web tests in addition
to dependency audits, formatting, level-8 static analysis, localization,
OpenAPI/client drift, release-contract, lint and TypeScript checks.

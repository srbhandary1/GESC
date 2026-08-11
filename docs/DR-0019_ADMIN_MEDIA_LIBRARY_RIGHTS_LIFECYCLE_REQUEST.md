# DR-0019 — Media library, rights and object-lifecycle request

**Status:** Resolved — Option A accepted under APP-0045 / D-0139

**Needed by:** ADMIN-MEDIA-01

**Authority:** Master plan §§6.3–6.4, 8.2, 10–11, 13, 15–18 and 25;
ADR-0001; ADR-0003; ADR-0004; ADR-0007; ADR-0013; ADR-0014; D-0118–D-0120;
D-0130; D-0136–D-0138

**Opened:** 9 August 2026

**Resolved:** 9 August 2026

## Problem

ADMIN-MEDIA-01 is the next ordered checkpoint. The accountable product owner
has now stated, exactly, `I have overall rights of all media used here.
Proceed with the next steps.` That statement is recorded in
[the ADMIN media rights attestation](ADMIN_MEDIA_RIGHTS_ATTESTATION.md) and
APP-0044 / D-0138.

The attestation resolves the owner-level uncertainty but cannot safely choose
the remaining security, accessibility and authoritative-record behavior:

- `media_assets` is an immutable accepted-object record. There is no upload
  session, quarantine state, scan evidence, safe promotion, retirement,
  replacement lineage, metadata revision or operator media API;
- the repository has no approved malware-scanner adapter or media-inspection
  lifecycle. Fileinfo and a client MIME value do not prove safe, decodable
  audio/video bytes;
- existing rights evidence is program scoped and bound to the exact asset
  SHA-256, allowed uses, channels, territories and effective period. Treating
  the blanket statement as automatic approval would conflict with ADR-0004;
- rights approval additionally requires `media.rights.approve` and an active,
  in-scope `rights_confirmer` appointment. Uploaders and publishers cannot
  infer or manufacture that authority;
- the current permission catalogue contains none of ADMIN-MEDIA-01's six
  reserved library permissions, so their exact Direct role-pack grants have
  not been accepted;
- transcripts and text alternatives are learner-visible pedagogical and
  accessibility content. Storing them as unreviewed mutable asset fields would
  bypass the accepted English/Kannada revision and review authority;
- `content_revision_media_assets` is append-only. In-place replacement would
  mutate the meaning of reviewed or published content, while adding a second
  runtime-required asset could make an exact source revision ambiguous;
- current publication evidence verifies rights but has no exact clean-scan or
  accessibility-metadata component. Legacy draft assets may be hash verified
  yet have no malware-scan evidence; and
- quarantine expiry, rejected-object deletion, accepted-object retention,
  private preview, worker failure and backup behavior need exact values before
  storing new private objects.

These choices affect security, tenancy, foundation schema, learner-visible
content and immutable publication evidence. The repository agreement requires
runtime work to stop at this decision request.

## Decision profiles

| Option | Boundary | Trade-off |
|---|---|---|
| **A — Exact-asset governed media pipeline (recommended)** | Record the blanket attestation as reusable evidence, but require private resumable quarantine, deterministic inspection, a clean project-owned scanner result, explicit human quarantine acceptance, reviewed accessibility revisions, per-program appointed rights confirmation, immutable replacement lineage and exact publication pins | Delivers the intended media library without weakening existing rights/publication authority; adds a bounded upload/worker schema and keeps production disabled when the scanner or object-store controls are unavailable |
| B — Blanket auto-approval | Mark every present or future file rights-approved from the owner statement and attach or replace it in place | Fastest, but conflicts with ADR-0004's exact-hash, scope and appointment rules, bypasses scanning/review and rewrites immutable meaning; requires an ADR/master-plan change and is not recommended |
| C — Hold ADMIN-MEDIA-01 | Preserve the attestation only; add no media schema, route, upload or UI | Keeps all media-dependent publications blocked and pauses the ordered ADMIN program |

## Recommended Option A policy

Approval of Option A means all of the following.

### 1. Tenant, permission and transport boundary

1. Every upload, object, revision, scan, rights record, attachment and event is
   owned by the active membership-derived organization. Requests accept no
   organization ID, storage disk/key, uploader identity or scanner choice.
2. Seed exactly the six frozen permission definitions:
   `media.library.read`, `media.upload`, `media.quarantine.review`,
   `media.metadata.manage`, `media.replace` and `media.retire`. They are
   evaluated atomically, never through role names or a broad media-admin key.
3. The synthetic Direct role packs receive only these setup grants:
   trainers get library read, upload and metadata management; content
   reviewers get library read and quarantine review; organization
   administrators get all six. Custom roles remain governed by the existing
   actor-held delegation ceiling.
4. `media.rights.approve` remains separate and is not implied by any new
   media permission or default grant. A confirmation still requires a valid,
   accepted, in-scope `rights_confirmer` appointment under ADR-0004.
5. Operator reads and commands stay first-party web-session-only; Passport
   bearers are denied. Every command requires fresh TOTP, header
   `Idempotency-Key`, a normalized fingerprint and the exact applicable
   version/head. Binary part retries are idempotent and content-hash bound.
6. No public media URL, signed direct-upload URL, cross-owner library view,
   platform capability or reference-site import is added.

### 2. Supported media and upload contract

1. The first slice supports only the two existing runtime classes:
   `model_audio` and `lesson_video`. Images, documents, SVG, HTML, archives,
   executable content, learner recordings and conversation media remain out
   of scope.
2. Model audio retains its 10 MB ceiling and code-owned audio MIME allowlist;
   lesson video remains MP4-only with the ADR-0013 20 MB ceiling. Centralized
   policy may narrow accepted containers/codecs; callers cannot raise limits.
3. A resumable app-proxied upload starts with a declared kind, total bytes,
   whole-object SHA-256 and safe operator label. Fixed-size sequential parts
   have their own index, byte count and SHA-256. Parts cannot overlap, skip,
   change or be reused across sessions.
4. Original filenames, paths and client object keys are discarded. Private
   random keys contain no organization, membership, program, learner, label
   or supplied filename. Raw bytes never enter the database, logs, audit,
   outbox, analytics, URLs or browser persistence.
5. Finalization streams the parts into one bounded private quarantine object,
   recomputes exact size/hash and records an immutable candidate. A mismatch
   rejects the candidate and schedules prompt object deletion; it never
   creates a `media_assets` row.

### 3. Inspection, malware scan and quarantine review

1. A project-owned `MediaInspector` verifies signature, container, stream
   bounds, duration and decodability without exposing tool/provider types to
   the domain. Client MIME, extension and metadata are never authority.
2. A project-owned `MediaMalwareScanner` streams the exact quarantined object
   to a configured scanner adapter and records engine, signature-policy
   version, exact object hash, result and time. Production has no permissive
   or fake scanner; missing/unreachable/stale scanner state fails closed.
3. Deterministic fakes may be used only in automated tests. Local/staging may
   use a reviewed ClamAV-compatible adapter through the project interface;
   neither path is represented as production security review.
4. Only a candidate with matching inspection and `clean` scan evidence may be
   privately previewed or accepted. Infected, malformed, mismatched or
   unsupported bytes are never previewable and are rejected with an
   allowlisted content-free reason.
5. A human with `media.quarantine.review` explicitly accepts or rejects a
   clean candidate. The same human may also be the uploader in a lean pilot;
   automated clean status is mandatory and cannot be overridden.
6. Acceptance creates exactly one immutable `media_assets` row and append-only
   acceptance/event evidence. Storage promotion is retryable and verifies the
   hash again; partial copy/move failure leaves the candidate non-attachable
   and visibly recoverable rather than reporting success.

### 4. Metadata and accessibility authority

1. Technical metadata derived by inspection is immutable with the asset:
   kind, MIME, byte size, SHA-256, duration and applicable stream dimensions.
   A safe operator label and search key use append-only expected-version
   metadata revisions; original filenames are never displayed or retained.
2. Model audio requires an exact English transcript. Lesson video requires an
   exact English transcript plus a concise text alternative/visual
   description. These are bounded text-only revisions with no markup, URLs or
   executable content.
3. Learner-visible accessibility text uses append-only English source and
   Kannada variant revisions, exact hashes and the existing author/translator
   separation. Exact English academic and Kannada review evidence uses the
   existing review permissions and appointment kinds; a metadata editor
   cannot self-review their source/translation.
4. A source change makes its Kannada variant and reviews stale for a new
   publication. Prior publication snapshots retain the exact accessibility
   revisions and evidence they selected.
5. Operator-only labels do not become learner content. Transcript or text
   alternative bodies are absent from shell search, audit/outbox payloads,
   logs and analytics.

### 5. Rights evidence and the owner attestation

1. APP-0044's attestation may be offered as the evidence source and ownership
   basis, but the server never turns it into an approved database row during
   upload, scan, quarantine acceptance, attachment or publication.
2. An appointed confirmer selects one exact accepted asset/hash and program,
   verifies the evidence reference, and explicitly records allowed learner
   delivery, sharing and derivative use, release channels, territories,
   effective/expiry times, retention and deletion constraints through the
   existing `MediaRightsConfirmationCommands` service.
3. The UI may prefill the attestation reference and common owner-controlled
   basis, but it must show and require confirmation of every scope field. It
   cannot bulk-confirm hidden assets or infer `sharing_allowed` or
   `derivative_use_allowed` from technical ownership.
4. Pending legacy rights revisions may receive exact approved successors only
   after the stored object passes the same current inspection/scan gate and
   the appointed human checks the exact hash. This decision authorizes no
   legacy production-data/media import or invented missing file.
5. Rights rejection and withdrawal remain append-only appointed actions.
   Expiry/withdrawal continues to block delivery deterministically and cannot
   be overridden by publisher or media-library authority.

### 6. Usage, replacement and immutable publication

1. Attachment is allowlisted: `model_audio` may attach only once to an exact
   daily-speaking-mission English source revision; `lesson_video` may attach
   only once to an exact lesson-video source revision. The asset kind, usage,
   owner, current clean scan, approved accessibility revision and program
   relationship must agree.
2. The attachment command requires the exact workspace/source/metadata heads,
   runs in the owner tenant transaction and appends the relationship. Guessed
   foreign assets, programs, revisions and storage objects remain 404-like.
3. A reviewed source cannot receive a new attachment silently. Attachment or
   replacement after review requires a new English source successor and fresh
   Kannada/review evidence; existing links and publication manifests are
   never edited.
4. Replacement creates a new immutable asset plus explicit same-owner
   predecessor/successor lineage. It copies no scan, metadata review, rights,
   attachment or publication authority. The new asset must independently pass
   every gate.
5. New publication, scheduling, activation and rollback require exact clean
   scan, accessibility and rights revisions in the derived manifest.
   Existing immutable publication snapshots are not backfilled or rewritten.
6. The publication snapshot records asset, content hash, scan evidence,
   accessibility revision/review and rights revision in deterministic order.
   Delivery revalidates rights and safety suspension without selecting a
   mutable latest object.

### 7. Retirement, retention and deletion

1. Media retirement is an append-only expected-version event. It blocks new
   attachment and publication but does not rewrite a prior publication,
   enrollment pin or learning evidence. Rights withdrawal or the existing
   emergency suspension path, not ordinary retirement, stops existing
   delivery where legal/safety scope requires it.
2. Incomplete upload parts expire after 24 hours. Rejected, infected,
   mismatched and abandoned quarantine objects have a 15-minute deletion
   target and one-hour hard maximum. Clean candidates awaiting human review
   expire after seven days. Deletion is receipt-backed, retryable and cannot
   be reported complete before storage confirms it.
3. Content-free upload/scan/review operational history is retained for 365
   days. Quarantine bytes are excluded from ordinary backups. Accepted
   immutable media is retained while referenced by metadata, rights,
   publications, grants, enrollments or audit and follows encrypted private
   object backup controls.
4. Retired accepted objects are not physically deleted in this checkpoint.
   Disposal of an unreferenced accepted asset, legal hold, production archive
   period and restore custody require a separately reviewed migration/policy.
5. A rollback disables new upload/finalization while keeping rejection,
   deletion and reconciliation workers available; it cannot strand public or
   readable quarantine bytes.

### 8. API, UI and failure behavior

1. Add only `/operator/media` `O-READ`/`O-COMMAND` resources for bounded
   library/search/detail, resumable upload/finalization/status, quarantine
   review, private preview, metadata/accessibility revisions/reviews, rights
   lifecycle, exact attachment, replacement and retirement.
2. Reads are cursor paginated, tenant scoped and `private, no-store`. Responses
   expose safe identifiers, hashes, states and server-derived actions/blockers,
   never storage keys, filenames, scanner endpoints, appointment evidence
   bodies or raw permission names.
3. The manifest-gated Media Library appears under Learning with independent
   loading/retry, upload progress/resume, quarantine state, safe preview,
   metadata/accessibility forms, rights scope, exact usage references,
   replacement/retirement confirmations, history and stale-conflict recovery.
4. Unsaved text, chunks, previews, filters and idempotency keys remain in
   memory only. They do not enter localStorage, IndexedDB, Cache Storage,
   ordinary Capacitor preferences, URL history or analytics.
5. Stable allowlisted problem/conflict codes distinguish caller-correctable
   state without revealing foreign resources or scanner internals. Worker or
   storage failure remains retryable and never fabricates a clean/accepted
   state.
6. OpenAPI 3.1.1, generated TypeScript contracts, route-policy checks and all
   six governed UI catalogues change with the API/UI.

### 9. Consistency and verification

1. Multi-write commands use tenant-scoped transactions, deterministic locks,
   post-lock permission/appointment reauthorization, exact optimistic heads,
   request-bound idempotency, content-free audit and transactional outbox
   work. Object I/O is reconciled across the transaction boundary.
2. Real PostgreSQL tests force upload-finalization, quarantine review,
   metadata head, rights successor, attach/replace, permission/appointment and
   retirement/publication races. Exactly one valid head advances and no
   orphaned authority is reported as complete.
3. Same/cross-tenant tests cover list/read/upload/part/finalize/preview/review/
   metadata/rights/attachment/replacement/retirement, relationship insertion,
   route binding, worker envelopes and mass assignment.
4. Tests cover MIME/signature/hash/size/container/decoding mismatch, scanner
   infection/unavailable/stale evidence, object-store failure, deletion
   reconciliation, accessibility review staleness, rights expiry/withdrawal,
   immutable prior publications and no raw-content telemetry.

## Required implementation consequence after approval

The approved bounded specification must translate this policy into:

- additive upload/quarantine/scan/metadata/accessibility/lineage/lifecycle
  schema while preserving existing immutable assets and publications;
- project-owned inspection, scanner and private object-store interfaces with
  fail-closed configuration and reconciliation workers;
- exact media authorization/query/command services and the existing appointed
  rights command rather than controller-owned authority;
- an accessible Media Library and Content Studio attachment handoff;
- OpenAPI 3.1.1, regenerated clients, six governed catalogues and operations/
  retention documentation;
- SQLite behavior plus real PostgreSQL tenancy, immutability, idempotency and
  concurrency evidence; and
- `make verify`, `make e2e`, `make build`, migration rollback/reapply and
  backup/restore evidence before ADMIN-GROUPS-01 begins.

`make ai-eval` is not required unless implementation unexpectedly changes a
prompt, model, AI/speech provider or orchestration path.

No schema, permission seed, route, upload, scan adapter, object copy, UI
control, rights approval or media import is authorized by this open request
alone.

## Resolution requested

To accept the recommended profile, reply exactly:

```text
Approve DR-0019 Option A as written.
```

Alternatively, select Option C or describe exact changes to Option A. Option B
requires an explicit ADR-0004/master-plan amendment and separate security,
tenancy and rights review before it could authorize runtime work.

## Resolution

Option A was accepted as written on 9 August 2026 under
[APP-0045](APPROVALS.md#app-0045) and [D-0139](DECISIONS.md). The exact
approved proposal, before its status, resolved date and this resolution
section were added, has SHA-256
`5c18ca97be6eded6d6a39882dae0f05a675827451b32aced5f0cb05dacf603b1`.

ADMIN-MEDIA-01 may now publish its bounded active specification and implement
the accepted non-production vertical slice. This resolution does not accept
unbuilt schema, routes, UI or test results; approve any media object or rights
revision; authorize legacy/reference data or media import, external spend,
deployment, production or public release; or waive ADR-0004's appointed
per-asset rights, academic/Kannada review and retained independent-review
gates.

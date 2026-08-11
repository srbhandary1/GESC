# ADMIN-MEDIA-01 — governed private media library specification

**Status:** Approved implementation specification; ADMIN-MEDIA-01 complete;
ADR-0016 long-form lesson-audio extension active under LPL-01

**Recorded:** 9 August 2026

**Authority:** `ENTERPRISE_PRODUCT_MASTER_PLAN.md` §§6.3–6.4, 8.2, 10–11,
13, 15–18 and 25; ADR-0001; ADR-0003; ADR-0004; ADR-0007; ADR-0013;
ADR-0014; ADR-0016; D-0118–D-0120; D-0130; D-0136–D-0139; D-0145;
[DR-0019](DR-0019_ADMIN_MEDIA_LIBRARY_RIGHTS_LIFECYCLE_REQUEST.md) Option A;
[APP-0044](APPROVALS.md#app-0044); [APP-0045](APPROVALS.md#app-0045);
[APP-0048](APPROVALS.md#app-0048)

**Production/public release:** Not authorized

## 1. Checkpoint outcome

ADMIN-MEDIA-01 delivers one current-organization, first-party Media Library.
ADR-0016 additively extends it to three governed media kinds:

- `model_audio`, bounded by the existing 10 MB / five-minute short-model
  policy;
- `lesson_audio`, bounded by ADR-0016's 20 MB / 15-minute audio-only teaching
  policy; and
- `lesson_video`, bounded by ADR-0013's MP4-only 20 MB policy.

The slice covers resumable app-proxied upload, private quarantine, exact
object inspection and malware-scan evidence, explicit quarantine acceptance,
append-only accessibility metadata and reviews, program-scoped appointed
rights confirmation, exact Content Studio attachment, immutable replacement
lineage, ordinary retirement, private preview, safe history and cleanup
reconciliation.

Images, documents, SVG/HTML, archives, learner recordings, conversation
media, public URLs, cross-owner sharing/cloning, legacy/reference import,
provider spend and later ADMIN capabilities remain excluded.

## 2. Ownership, permission and transport contract

Every new table is organization owned with non-null `organization_id`,
tenant-leading indexes and composite relationships. Ownership derives only
from `OrganizationContext` and never from request fields.

The permission catalogue adds exactly:

- `media.library.read`;
- `media.upload`;
- `media.quarantine.review`;
- `media.metadata.manage`;
- `media.replace`; and
- `media.retire`.

Direct setup grants are fixed by D-0139:

| Role pack | Added setup grants |
|---|---|
| Trainer | library read, upload, metadata management |
| Content reviewer | library read, quarantine review |
| Organization administrator | all six media permissions |

Role names never authorize runtime behavior. `media.rights.approve`,
`curriculum.translate.kn`, `curriculum.review.en`, `curriculum.review.kn` and
their appointments remain separate existing authority.

All `/operator/media` routes require first-party web authentication and an
active membership-derived organization context. Passport is denied. Commands
add fresh TOTP and header `Idempotency-Key`; the request body cannot supply an
idempotency key, actor, tenant, storage target, scanner or authority.

## 3. Additive data contract

The checkpoint adds these organization-owned records:

| Record | Authority and lifecycle |
|---|---|
| `media_uploads` | Mutable expected-version projection for one upload/adoption candidate; states are backed by append-only events |
| `media_upload_parts` | Immutable sequential part receipt with exact byte count/hash and private random object key |
| `media_security_scans` | Immutable inspection/scanner result bound to one upload or existing asset and exact object hash |
| `media_upload_events` | Append-only upload, finalization, scan, acceptance, rejection, expiry and failure history |
| `media_asset_lifecycles` | Versioned current active/retired projection selecting the exact clean scan and optional predecessor |
| `media_asset_events` | Append-only adoption, acceptance, replacement and retirement history |
| `media_asset_metadata_revisions` | Append-only English operator label, transcript and text-alternative revision with exact content hash |
| `media_asset_metadata_heads` | Expected-version current English metadata head |
| `media_asset_metadata_variants` | Append-only `kn-IN` variant bound to an exact English metadata hash |
| `media_asset_metadata_variant_heads` | Expected-version current Kannada head for one English metadata revision |
| `media_asset_metadata_reviews` | Append-only exact English/Kannada approved/rejected/withdrawn evidence with accepted appointment snapshot |
| `media_asset_replacements` | Immutable same-owner predecessor/successor lineage; no inherited authority |
| `media_attachment_events` | Append-only exact content/media relationship and workspace-version evidence |
| `media_object_deletion_requests` | Content-free retry/dead-letter receipt for rejected/expired quarantine cleanup |
| `publication_media_governance_components` | Exact clean scan, metadata, Kannada variant and review evidence selected by a new publication |

Existing `media_assets`, `media_rights_revisions`,
`content_revision_media_assets` and prior publication component rows remain
immutable. A new accepted upload creates a normal `media_assets` row only
after exact clean-scan and human acceptance. Existing rows can enter the
managed library only through an exact private-object adoption scan.

PostgreSQL constraints and append-only triggers enforce tenant relationships,
hash shapes, immediate parentage, state values, review target shape and
immutable evidence. SQLite receives equivalent application behavior and the
portable constraints supported by its migration driver.

## 4. Upload and quarantine state machine

An upload uses:

```text
uploading
→ finalized
→ scan_clean
→ accepted
```

Terminal or recoverable alternatives are:

```text
rejected
expired
inspection_failed
scan_failed
promotion_failed
```

1. Start records kind, safe label, total bytes, whole SHA-256, fixed part size,
   expected part count, creator and a 24-hour upload expiry. The server
   allocates all opaque identifiers and keys.
2. Parts are sequential and fixed-size except the final part. Each POST is
   exact-index/version/idempotency/hash bound. A duplicate exact replay returns
   the original receipt; changed bytes conflict.
3. Finalization requires every exact part. The private object store streams
   them into one quarantine object and recomputes the declared total and
   whole-object SHA-256.
4. The project `MediaInspector` verifies signature, container, stream bounds,
   duration and decodability. The project `MediaMalwareScanner` scans the same
   exact hash and returns engine/signature-policy evidence. Caller MIME and
   extension are never trusted.
5. A missing, unavailable, error or stale scanner never becomes clean. Test
   fakes bind only in the testing environment. Operational environments use
   the configured project adapter and fail closed when unavailable.
6. Only `scan_clean` bytes are previewable to a quarantine reviewer. Human
   acceptance promotes and re-verifies the object, creates the immutable asset
   and lifecycle selection, and queues quarantine/part cleanup. Human
   rejection queues cleanup without creating an asset.
7. External object I/O never creates false transactional success. Deterministic
   keys, exact hashes, deletion receipts and reconciliation make replay and
   crash recovery explicit.

## 5. Private storage and cleanup

`config/media.php` centrally fixes kind policies, part size, expiry,
inspection/scanner adapters and disks. Quarantine and accepted objects are
private. Keys contain only random upload/asset identifiers and hashes.

Cleanup deadlines:

- incomplete uploads: 24 hours;
- malformed, infected, rejected or abandoned quarantine bytes: target 15
  minutes, hard maximum one hour;
- clean candidates awaiting human review: seven days; and
- content-free operational upload/scan/review history: 365 days.

Quarantine bytes are excluded from ordinary backups. Accepted immutable media
is retained while referenced by metadata, rights, publications, grants,
enrollments or audit. Physical disposal of an unreferenced accepted asset is
not implemented. `media:reconcile` processes due deletion receipts and expires
bounded candidates; the scheduler runs it without inventing human review or
rights authority.

## 6. Metadata, localization and review

Technical scan metadata is immutable with the selected scan. Operator label
and accessibility content use separate append-only revisions:

| Kind | Required English accessibility fields |
|---|---|
| `model_audio` | transcript; text alternative absent |
| `lesson_audio` | transcript; text alternative absent |
| `lesson_video` | transcript and concise text alternative/visual description |

Text is Unicode-normalized, markup/URL-free and bounded: label 160 scalar
values, transcript 64 KiB/32,000 scalar values and text alternative 2,000
scalar values. Bodies never enter logs, analytics, audit, outbox or shell
search.

English metadata authoring requires `media.metadata.manage`. Kannada variant
authoring requires both `media.metadata.manage` and
`curriculum.translate.kn`. English and Kannada review reuse
`curriculum.review.en` / `curriculum.review.kn` plus exact accepted
appointments. The reviewer differs from all source authors or translators.

A new English source makes prior variants/reviews stale for future
publication. A new Kannada variant makes prior Kannada review stale. Decisions
and withdrawals are append-only and exact-hash bound.

## 7. Rights evidence

APP-0044's SHA-256 and reference may be prefilled as umbrella owner evidence.
It never creates approval automatically.

The HTTP application service calls the existing
`MediaRightsConfirmationCommands` authority. Confirmation requires:

- one exact active, clean asset/hash and selected program;
- `media.rights.approve`;
- a valid accepted in-scope `rights_confirmer` appointment;
- rights holder, provenance, ownership/licence reference and evidence SHA-256;
- explicit learner-delivery, sharing and derivative-use booleans;
- allowlisted release channels and territories;
- effective/optional expiry time; and
- retention/deletion policy keys.

The initial approval or exact latest successor is allowed. Reject and
withdraw remain appointed append-only decisions. Rights expiry/withdrawal and
appointment invalidation continue to fail publication/delivery closed.

## 8. Attachment, replacement and retirement

Attachment requires `media.metadata.manage` and `curriculum.author`. It is
restricted to the active owner workspace and exact current English head:

- `model_audio` → a daily-speaking-mission instruction revision; or
- `lesson_video` → an exact lesson-video instruction revision.

`lesson_audio` attachment is intentionally closed during LPL-01 even though
its usage key is reserved by the successor PostgreSQL checks. LPL-02 must add
the exact typed lesson-practice source schema and attachment rule before the
API or UI may select it.

The command checks exact workspace and metadata heads, active lifecycle,
selected clean scan, current approved English/Kannada metadata reviews and
current approved program rights. A source with any review or an existing
usage attachment cannot change silently. The operator creates a content
successor first; the old relationship remains immutable.

Attachment advances the workspace version and records an append-only media
attachment event. It does not copy bytes, reviews or rights.

Replacement requires `media.replace`. It links a new independently accepted
asset of the same kind to one predecessor. Scan, metadata, review, rights,
attachment and publication authority are never inherited. Replacement does
not retire or mutate the predecessor.

Retirement requires `media.retire` and an exact lifecycle version. It blocks
new attachment and new publication. Prior immutable publications and
enrollment pins are unchanged. Rights withdrawal or the existing emergency
suspension path—not ordinary retirement—stops existing delivery where the
rights/safety scope requires it.

## 9. Publication integration

New graph-v2/managed-asset publication manifests add an exact media-governance
object containing:

- selected clean scan ID, object hash and policy versions;
- current English metadata revision/hash and approved review;
- current Kannada variant/hash and approved review; and
- the existing exact approved rights revision/evidence hash.

`publication_media_governance_components` preserves those references beside
the existing immutable media component. Schedule/activation/rollback
revalidation requires the component and current authority for managed media.
Existing graph-v1 publication snapshots are not backfilled or mutated. An
ADMIN workspace or any asset with a managed lifecycle cannot use the legacy
compatibility path.

LPL-01 does not add a `lesson_audio` publication builder or learner route.
Those remain closed until LPL-02 can bind the exact practice definition,
ordered component, review and learner publication pin.

## 10. HTTP contract

All paths are below `/api/v1/operator/media`:

| Method/path | Purpose |
|---|---|
| `GET /manifest` | Safe server-derived panels, actions, limits and attestation reference |
| `GET /assets`, `GET /assets/{asset}` | Cursor-paged library and exact detail/history summary |
| `GET /assets/{asset}/preview` | Hash-verified accepted private stream/range |
| `GET /uploads`, `GET /uploads/{upload}` | Cursor-paged upload/quarantine state |
| `GET /uploads/{upload}/preview` | Clean quarantine-review preview only |
| `POST /uploads` | Start resumable upload |
| `POST /uploads/{upload}/parts` | Store one exact multipart binary part |
| `POST /uploads/{upload}/finalizations` | Assemble, inspect and scan exact bytes |
| `POST /uploads/{upload}/acceptances`, `/rejections` | Human quarantine decision |
| `POST /assets/{asset}/adoptions` | Inspect/scan and adopt an existing exact private asset |
| `POST /assets/{asset}/metadata-revisions` | Append English metadata |
| `POST /metadata-revisions/{revision}/kannada-variants` | Append Kannada metadata |
| `POST /metadata-reviews` | Approve/reject/withdraw exact metadata evidence |
| `POST /rights-decisions` | Confirm/reject/withdraw exact ADR-0004 rights evidence |
| `POST /assets/{asset}/attachments` | Attach exact approved asset to exact draft source |
| `POST /assets/{asset}/replacements` | Record immutable successor lineage |
| `POST /assets/{asset}/retirements` | Retire from new use |

All JSON reads are bounded, tenant scoped and `private, no-store`. Binary
responses expose no object key and use exact type/length/hash plus private
no-store headers. Video preview supports one bounded range; audio preview is
bounded full streaming.

Stable conflict keys cover stale upload, metadata, variant, workspace and
lifecycle heads, changed idempotency replay, duplicate use/replacement and
concurrent change. Missing authority and foreign/guessed identifiers remain
indistinguishable from not found.

## 11. Operator experience

The overview adds a `media_library` panel under Learning only when the actor
can read or act on media. The Media Library provides independent asset/upload
loading and retry, filters, upload progress/resume, scan/quarantine status,
private preview, accessibility revisions/reviews, rights scope, exact usage,
replacement/retirement confirmations and history.

Server-derived semantic actions gate every control. Unsaved fields, binary
parts, object URLs, filters and idempotency keys remain memory-only; object
URLs are revoked on replacement/unmount. Nothing enters URLs, localStorage,
IndexedDB, Cache Storage, ordinary Capacitor preferences or analytics.

The critical UI remains keyboard operable with announced status, visible
focus, labelled fields, confirmations, 200–400% reflow and no horizontal
overflow at 320 CSS pixels. All six catalogues change together; the five
non-English catalogues remain declared pending-independent-review with
approved-English fallback.

## 12. Verification and exit gate

Repository completion requires:

1. apply/rollback/reapply on SQLite and PostgreSQL with composite tenant,
   append-only, lineage, state and review constraints;
2. API tests for same/cross-tenant list/detail/upload/part/finalize/preview/
   quarantine/metadata/review/rights/attach/replace/retire, strict bodies,
   route mismatch and mass assignment;
3. inspector/scanner/hash/type/size/decoding, storage failure, cleanup retry,
   no-content telemetry and private-response tests;
4. current scan/accessibility/rights publication and immutable prior-release
   tests;
5. real PostgreSQL races for upload finalization, quarantine acceptance,
   metadata/variant/review, rights successor, attach/replacement, retirement/
   publication and permission/appointment invalidation;
6. OpenAPI 3.1.1 and generated TypeScript client parity;
7. Media Library component/API/E2E coverage for partial failure, resume,
   clean/rejected scan, preview cleanup, rights, attachment, conflicts, axe and
   320-pixel layout; and
8. `make verify`, `make e2e`, `make build`, migration round-trip and
   `POSTGRES_DB=global_english_test make backup-restore-drill` pass.

`make ai-eval` is not required unless a prompt, model, AI/speech provider or
orchestration path changes.

## 13. Retained limitations

- No production scanner/object-store/security review, legal validation,
  independent specialist review, staging evidence, deployment or public
  release is claimed.
- No legacy/reference media import is authorized. Existing private assets are
  adopted only one exact object at a time by an authorized human.
- No accepted-asset physical deletion, legal hold, production archive period,
  offsite backup/PITR or restore-custody policy is added.
- No image/document media, public URL, direct-to-storage upload, cross-owner
  clone/share, offline lesson-video bundle, native Android streaming source or
  provider spend is added.
- Later groups, activity, review, communications, conversation, CMS,
  entitlement and configuration checkpoints remain ordered and untouched.

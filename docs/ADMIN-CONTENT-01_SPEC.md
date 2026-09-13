# ADMIN-CONTENT-01 — governed content workspace specification

**Status:** Repository implementation complete

**Recorded:** 9 August 2026

**Authority:** `ENTERPRISE_PRODUCT_MASTER_PLAN.md` §§5.4, 6, 8.2, 15, 16,
18 and 31; ADR-0003; ADR-0004; ADR-0013; ADR-0014; D-0130; D-0136;
D-0137;
[DR-0018](DR-0018_ADMIN_CONTENT_AUTHORING_PUBLICATION_REQUEST.md) Option A;
[APP-0043](APPROVALS.md#app-0043)

**Production/public release:** Not authorized

## 1. Checkpoint outcome

ADMIN-CONTENT-01 delivers one first-party, organization-owned Content Studio
from program creation through a reviewed immutable publication. It adds an
append-only draft workspace and graph-v2 level model around the existing
ADR-0004 authoring, appointment, review and publication services.

This checkpoint does not add a permission, role grant, platform capability,
media-management operation, cross-owner clone/share operation, learner
assignment, public preview, CMS record, AI draft or imported reference asset.
Unknown legacy payload schemas remain read-only.

## 2. Invariants

1. Every route resolves the active membership-derived organization. No body,
   path or query field may select or replace ownership.
2. Reads require any existing content permission. Each command evaluates its
   exact existing atomic permission after deterministic locks. Review commands
   additionally use a current accepted appointment and retain creator/reviewer
   or translator/reviewer human separation.
3. All mutations are Sanctum-web-only, fresh-TOTP protected and require the
   `Idempotency-Key` header. Passport bearer credentials remain denied.
4. A changed replay, stale expected version/head, concurrent winner or sealed
   aggregate returns `409` with an allowlisted conflict code. Missing authority
   and foreign-tenant identifiers remain indistinguishable from `404`.
5. Requests are strict allowlists. IDs, attribution, organization, review
   identity, pedagogical authority, localization policy and publication
   evidence are never mass assignable.
6. Source text, Kannada text, preview bodies, media keys and qualification
   evidence never enter logs, audit payloads, outbox payloads or shell search.
7. Published components, submitted graph revisions, decisions and events are
   immutable. Ordinary commands abandon, retire, cancel or create successors;
   they never hard-delete authority or evidence.

## 3. Additive data contract

Migration `2026_08_09_000140_create_admin_content_workspace.php` adds:

- `programs.descriptor_version`, defaulting existing rows to version 1;
- `program_descriptor_events`, an append-only expected-version descriptor
  correction timeline;
- `curriculum_levels`, organization/version-owned stable level nodes with
  version-unique keys and sequences and nullable retirement time;
- nullable `curriculum_modules.curriculum_level_id` plus retirement state on
  graph nodes. Existing graph-v1 rows remain active with a null level;
- `curriculum_workspaces`, one graph-v2 workspace per ADMIN-authored version,
  with `draft|in_review|sealed|abandoned` state, optimistic version, current
  draft head and immutable source version/publication lineage;
- `curriculum_draft_graph_revisions`, canonical append-only incomplete-or-
  complete graph snapshots with parent, hash, blocker set and actor/time;
- `curriculum_workspace_events`, the append-only workspace lifecycle; and
- `content_workspace_commands`, actor/request-bound idempotency receipts.

PostgreSQL constraints enforce tenant-composite references, positive versions,
state/value allowlists, immutable ownership/lineage, append-only histories and
valid state transitions. SQLite retains equivalent application checks for the
fast suite. Migration rollback removes only the additive workspace contract
and restores the legacy module sequence constraint without rewriting graph-v1
or publication evidence.

### 3.1 Workspace lifecycle

- Creating a program atomically creates its blank graph-v2 curriculum version,
  workspace, empty draft revision 1 and `workspace.created` event.
- A workspace is editable only in `draft`. A rejected or withdrawn exact graph
  decision permits the next expected-version save to reopen `in_review` to
  `draft`; an approved or undecided submission stays sealed for structure.
- Review submission materializes the current relational projection, requires a
  closed graph, calls the existing immutable graph-snapshot command and moves
  the workspace to `in_review`.
- Creating either an immediate or scheduled immutable publication snapshot
  moves the workspace to terminal `sealed` in the same transaction.
- Abandonment is terminal and preserves all snapshots/events. Further work
  creates a successor.

### 3.2 Successors

A successor command locks the source program/version and publication head,
requires the same owner and a new version key, and creates new document,
revision, variant and graph-node identifiers. It copies current payloads,
staff attribution/times and same-owner media links, but no review, graph-review,
publication, share, enrollment, pin or active-head authority.

Graph-v2 sources retain their explicit levels. A graph-v1 source receives one
system-marked `legacy-unassigned` level and the readiness blocker
`legacy_level_assignment_required` until every copied module is deliberately
saved under a non-placeholder level. The immutable source version and optional
source publication are recorded as lineage.

## 4. Canonical draft graph v2

The write contract is typed and key-related; callers cannot supply database
IDs:

```json
{
  "schema": "global-english.curriculum-draft-graph.v2",
  "levels": [{"key": "a1", "sequence": 1}],
  "objectives": [{
    "key": "greet-politely",
    "outcome_code": "CAN_GREET_POLITELY",
    "sequence": 1,
    "prerequisite_keys": []
  }],
  "modules": [{
    "key": "introductions",
    "level_key": "a1",
    "sequence": 1,
    "estimated_minutes": 30
  }],
  "lessons": [{
    "key": "greetings",
    "module_key": "introductions",
    "sequence": 1,
    "objective_keys": ["greet-politely"]
  }],
  "items": [{
    "key": "greeting-model",
    "lesson_key": "greetings",
    "content_document_key": "greeting-model-text",
    "item_type": "model_text",
    "sequence": 1
  }],
  "delivery_bundles": [{
    "key": "greeting-practice",
    "instruction_document_key": "greeting-instruction",
    "sequence": 1,
    "item_keys": ["greeting-model"]
  }]
}
```

Keys match `^[a-z0-9][a-z0-9._-]{0,99}$`; outcome codes match
`^[A-Z0-9][A-Z0-9_]{0,119}$`; sequences are positive and unique in their
parent scope. Every reference must resolve within the submitted graph or the
same workspace's active content documents. Prerequisites are acyclic.

Drafts may omit collections, but present relationships must be consistent.
Review submission additionally requires every collection non-empty, every
module/lesson/objective/item/bundle used, every referenced document headed,
no `legacy-unassigned` level, and the existing strict graph invariants.

Central limits are 25 levels, 100 modules, 500 objectives, 500 lessons, 2,000
items, 500 delivery bundles and 5,000 combined relationships. One canonical
draft body is at most 1 MiB.

The strict submitted projection is
`global-english.curriculum-graph.v2`; publication manifests containing it use
`global-english.curriculum-publication.v2`. Existing graph/publication v1 bytes
and readers remain unchanged.

## 5. Typed governed content

Document keys are immutable. The server derives membership attribution and
localization policy. Guidance types `instruction`, `explanation`, `feedback`
and `learner_action` are always `kn_required`; other accepted ADR-0004 content
types are `english_only_pedagogical` and therefore require an allowlisted
English-review rationale before publication.

The first writable payload adapters are:

- `global-english.governed-text.v1`: exactly `{schema, text}`; and
- `global-english.daily-speaking-mission.v1`: the existing English fields
  `goal_key`, `estimated_minutes`, `title`, `model_text`, `transcript`,
  `can_do`, `model_audio_duration_ms` and one typed multiple-choice `check`;
  Kannada accepts exactly `{schema, guidance}`.

No text field exceeds 8,000 Unicode scalar values. Payloads are at most 64 KiB,
depth 4 and 50 collection members. HTML tags, control characters, executable
content and URL schemes are rejected. Daily mission goals use the existing
three learner goal keys, durations are bounded by the learner reader, option
IDs are unique keys, there are 2–6 options and the correct ID must resolve.

Daily mission source creation forces `instruction`, learner-visible and
Kannada-required. Publication v2 additionally requires exactly one current
`model_audio` attachment whose existing media/rights predicate passes. This
checkpoint provides no attach/upload/rights command and no waiver.

Private preview returns the exact source head and latest exact-source Kannada
variant as plain text/typed fields, locale, revision number, revision ID and
hash. It is `private, no-store`, executes nothing and creates no public URL.

## 6. HTTP surface

All endpoints are under `/api/v1`, return `Cache-Control: private, no-store`
and retain the existing content endpoints for compatibility.

### 6.1 Reads

| Method and path | Result |
|---|---|
| `GET /operator/content/workspaces` | Bounded keyset list of programs/workspaces, state, versions, exact heads, blockers and semantic actions |
| `GET /operator/content/workspaces/{curriculumVersion}` | Exact typed graph, document-head summaries, lineage, blockers and actions |
| `GET /operator/content/workspaces/{curriculumVersion}/preview?document_key=…` | Private exact English/Kannada typed preview |
| `GET /operator/content/workspaces/{curriculumVersion}/history` | Bounded workspace/graph/content/review/publication event timeline |
| `GET /operator/content/review-queues?queue=structure|english|kannada|readiness` | One bounded exact-head queue with semantic actions |
| `GET /operator/content/publications` | Bounded publication/schedule status and lifecycle actions |

List `limit` is 1–25, default 25. Encrypted continuation is bound to tenant,
route purpose and filters.

### 6.2 Workspace and content commands

| Method and path | Exact command permission |
|---|---|
| `POST /operator/content/programs` | `curriculum.author` |
| `POST /operator/content/programs/{program}/descriptor-corrections` | `curriculum.author` |
| `POST /operator/content/versions/{curriculumVersion}/successors` | `curriculum.author` |
| `POST /operator/content/workspaces/{curriculumVersion}/graph-revisions` | `curriculum.author` |
| `POST /operator/content/workspaces/{curriculumVersion}/review-submissions` | `curriculum.author` |
| `POST /operator/content/workspaces/{curriculumVersion}/abandonments` | `curriculum.author` |
| `POST /operator/content/workspaces/{curriculumVersion}/documents` | `curriculum.author` |
| `POST /operator/content/documents/{document}/english-revisions` | `curriculum.author` |
| `POST /operator/content/revisions/{revision}/kannada-variants` | `curriculum.translate.kn` |

Program creation accepts only `program_key`, `internal_name`, `version_key`
and `expected_version: 0`. Graph save/submission/abandonment require
`expected_workspace_version`; descriptor correction requires
`expected_descriptor_version`. Source/variant commands require the exact IDs,
hashes and revision numbers already used by the domain append services.

### 6.3 Review commands

| Method and path | Exact command permission |
|---|---|
| `POST /operator/content/reviews/structure` | `curriculum.review.en` plus exact English academic appointment |
| `POST /operator/content/reviews/english` | existing exact English review contract |
| `POST /operator/content/reviews/kannada` | existing exact Kannada review contract |
| `POST /operator/content/reviews/{review}/withdrawals` | matching review permission and appointment authority |

Decisions are `approved|rejected`. Rationales are code-owned keys:
`meets_standard`, `accuracy_revision_required`, `language_revision_required`,
`structure_revision_required`, `english_only_justified`, and
`evidence_withdrawn`. The server rejects a rationale inappropriate to the
decision/kind.

### 6.4 Publication commands

| Method and path | Domain service |
|---|---|
| `POST /operator/content/publications` | existing immediate publisher |
| `POST /operator/content/publication-schedules` | existing future scheduler |
| `POST /operator/content/publication-activations` | existing due activator |
| `POST /operator/content/publication-schedule-cancellations` | additive guarded cancellation service |
| `POST /operator/content/publication-retirements` | existing retirement service |
| `POST /operator/content/rollbacks` | existing rollback service |

Every request requires exact `expected_head_version`. Scheduling accepts one
UTC `activation_at` strictly in the future. At most one non-terminal schedule
exists per program. Cancellation is valid only before activation and uses
`schedule_replaced|release_withdrawn|timing_changed`; retirement/rollback keep
their existing allowlisted reasons. Activation is an explicit publisher
command at or after the scheduled instant.

## 7. Stable response semantics

Server-derived `allowed_actions` contains semantic values such as
`edit_graph`, `submit_structure`, `create_document`, `translate`,
`review_structure`, `review_english`, `review_kannada`, `publish_now`,
`schedule`, `activate`, `cancel_schedule`, `retire`, `rollback` and
`create_successor`. It never contains permission names.

Readiness blockers are allowlisted identifiers:

`workspace_incomplete`, `workspace_in_review`, `workspace_abandoned`,
`workspace_sealed`, `legacy_level_assignment_required`, `graph_missing`,
`graph_review_missing`, `graph_review_rejected`, `english_review_missing`,
`kannada_variant_missing`, `kannada_review_missing`, `media_required_missing`,
`media_rights_missing`, `evidence_invalid`, `publication_suspended`,
`schedule_pending`, and `schedule_not_due`.

Conflict responses use `stale_version`, `idempotency_conflict`,
`state_conflict`, `head_conflict` or `concurrent_change`; validation remains
`422`. Responses disclose no raw permission or qualification detail.

## 8. Web experience

The manifest-gated Learning navigation adds **Content Studio** only when a
content panel is available. It provides:

- independent workspace, document, review-queue and publication loading/retry;
- structured program, graph, English and Kannada forms with no raw JSON/HTML;
- phone-width side-by-side/stacked private preview;
- visible blocker/preflight state and exact review/publication confirmations;
- stale conflict reload without silently merging authority; and
- keyboard operation, visible focus, announced status and usable 320 CSS pixel
  layout.

Unsaved values remain in React memory. Content, filters, cursors, request keys
and previews are excluded from URLs, browser persistence, service-worker cache
and analytics.

All six governed UI catalogues add source-linked Content Studio strings.
Kannada, Malayalam, Tamil, Telugu and Hindi continue to resolve visibly to the
approved English source until independent review; no synthetic translation is
claimed.

## 9. Verification and exit gate

The checkpoint is repository-complete only after:

1. migration apply/rollback/reapply passes on SQLite and PostgreSQL, including
   tenant composite, append-only and transition constraints;
2. API tests cover same/cross-tenant list/detail/create/edit/successor/review/
   publication behavior, strict bodies, route/body mismatch, mass assignment,
   private preview and stable failures;
3. exact review separation, appointment invalidation, Kannada and media-rights
   blockers remain fail closed;
4. real PostgreSQL races force graph-save, source/variant-head, review/edit,
   schedule/publish/rollback and permission/appointment invalidation overlap;
5. OpenAPI 3.1.1 and generated TypeScript contracts match the implementation;
6. Content Studio component/API/E2E tests cover partial failure, structured
   editing, preview, review, conflict and 320-pixel accessibility; and
7. `make verify`, `make e2e`, `make build` and
   `POSTGRES_DB=global_english_test make backup-restore-drill` pass.

`make ai-eval` is not required unless this checkpoint unexpectedly changes a
prompt, model, provider or orchestration path. ADMIN-MEDIA-01 cannot begin
until this evidence and the end-of-checkpoint status/decision/quality records
are complete.

## 10. Retained limitations

- No media upload, browse, attach, replacement, scanning, rights confirmation
  or retirement is present; a required missing asset remains a release block.
- No cross-owner share/clone, enrollment pin/assignment, cohort targeting,
  public preview, CMS, AI drafting, provider/spend or legacy import is added.
- Draft physical disposal, production archival/legal hold/restore custody,
  independent specialist review, deployment and production/public release
  remain separate gates.

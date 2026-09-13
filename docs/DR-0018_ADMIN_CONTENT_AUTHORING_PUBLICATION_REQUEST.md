# DR-0018 — Content authoring, review and publication workspace request

**Status:** Resolved — Option A accepted under APP-0043 / D-0136

**Needed by:** ADMIN-CONTENT-01

**Authority:** Master plan §§5.4, 6, 8.2, 15, 16 and 18; ADR-0003;
ADR-0004; ADR-0013; ADR-0014; D-0036–D-0047; D-0120; D-0130

**Opened:** 9 August 2026

**Resolved:** 9 August 2026

## Problem

ADMIN-CONTENT-01 is the next ordered checkpoint, but the accepted authority
and current implementation do not decide several foundation-schema and
authoritative-record choices required for its advertised vertical slice:

- the parity specification requires program/level/module/objective/lesson/item
  authoring, while the authoritative curriculum graph has no level table,
  level relationship or level component in graph schema v1;
- programs, curriculum versions and structural rows are currently created
  only by seeds, imports, cloning code and test fixtures. The public domain
  authoring command can append English/Kannada revisions only after a content
  document and complete graph already exist;
- the current structural projection has no operator draft-workspace state,
  optimistic version, abandonment rule or same-owner successor workflow.
  Once a version has any publication it is sealed, but an operator cannot
  create the governed successor needed to correct it;
- the graph projector accepts only a closed graph containing objectives,
  modules, lessons, items and at least one mission. ADMIN-CONTENT-01's short
  row omits missions, while ADMIN-ACTIVITY-01 later owns a different activity/
  assignment domain;
- an exact graph review is a publication prerequisite, but the current HTTP
  surface exposes only English-source and Kannada-variant review commands.
  Its two unbounded summaries do not expose exact heads, stale evidence,
  blockers, actor-eligible actions or complete revision/publication history;
- content payloads are arbitrary canonical JSON objects with no HTTP authoring
  schema, size/depth boundary or safe editing/preview contract. Choosing raw
  JSON, HTML, Markdown or one product-specific schema silently would change
  learner-visible content and security behavior;
- immediate publish and rollback are exposed, while accepted domain services
  also support future scheduling, due activation and retirement. There is no
  accepted cancellation or cohort-scheduling rule for the operator workspace;
  and
- ADMIN-MEDIA-01 is deliberately next. New daily-speaking content may need
  model audio and rights evidence, but ADMIN-CONTENT-01 cannot infer media
  browse/upload/link authority or relax the existing release predicate.

These choices affect tenancy, immutable publications, academic/language
authority, learner delivery and foundation schema. The repository working
agreement therefore requires runtime work to stop at this decision request.

## Decision profiles

| Option | Boundary | Trade-off |
|---|---|---|
| **A — Versioned owner-workspace with explicit levels (recommended)** | Add a bounded owner-organization draft workspace, explicit level graph v2, same-owner successor lineage, typed safe content forms, exact review queues and the complete existing publication lifecycle; preserve graph/publication v1 unchanged | Delivers the intended studio without weakening ADR-0004, but adds a reviewed additive schema and keeps new media-dependent bundles visibly blocked until ADMIN-MEDIA-01 |
| B — Existing-schema minimum | Omit levels and structural creation; expose only revision append, exact reviews, immediate publish and rollback for pre-created graph/content rows | Smaller change, but it does not meet the approved level-to-item authoring or conflict/history objective and leaves operators dependent on seeds/imports/database work |
| C — Hold ADMIN-CONTENT-01 | Add no content schema, authoring route or mutable studio UI | Preserves the current reviewed publication foundation but pauses the ordered ADMIN program |

## Recommended Option A policy

Approval of Option A means all of the following.

### 1. Tenant, ownership and scope boundary

1. Every draft, graph, revision, review, publication and event remains owned
   by the active membership-derived organization under ADR-0003/ADR-0004.
   Requests accept no organization ID and cannot edit a shared owner's records.
2. Reuse only the existing atomic curriculum permissions. ADMIN-CONTENT-01
   seeds no permission, broad `content.admin` key, role-name shortcut or
   platform capability and changes no default role pack.
3. Owner-side authoring, translation, review and publication stay distinct.
   Permission alone never supplies an academic/Kannada appointment, and a
   publisher cannot create, waive or impersonate review evidence.
4. Cross-owner sharing/cloning, enrollment assignment, authority-appointment
   administration, media upload/rights management, public preview, editorial
   CMS, AI drafting and imported reference content/media are excluded.
5. The slice uses synthetic or operator-authored non-personal content only.
   Real learner data, learner answers, support notes, recordings, credentials,
   provider payloads and unlicensed media are prohibited from content bodies.

### 2. Program, curriculum-version and successor lifecycle

1. A program has an immutable owner and stable key. Its operator-only internal
   descriptor may be corrected only through an expected-version command and
   append-only descriptor event; it is not learner content and is not inserted
   into an immutable publication manifest.
2. A new program command atomically creates one blank workspace-backed
   curriculum version. A version has an immutable owner, program, key and
   creation attribution plus a versioned workspace projection.
3. Workspace lifecycle is `draft`, `in_review`, `sealed` or `abandoned`,
   derived from append-only events. `sealed` follows creation of an immutable
   publication snapshot. `abandoned` is terminal, retains history and cannot
   publish; renewed work creates a successor.
4. Editing an existing legacy draft or released version first creates a new
   same-owner successor version. The successor records immutable source
   version/publication lineage, receives new identifiers and copies exact
   content attribution, but copies no review, publication, share, enrollment
   or active-head authority.
5. Existing graph-v1 revisions and publications remain byte-for-byte
   immutable and readable. They are not backfilled, relabelled or silently
   promoted to the new graph contract. A legacy version must be forked before
   ADMIN authoring changes it.

### 3. Explicit levels and draft graph authority

1. ADMIN-authored versions use graph schema v2 with explicit organization-
   owned levels. Every active module belongs to exactly one level; level,
   module, objective, lesson and item keys are stable within the version and
   sequences are unique in their parent scope.
2. A saved draft graph is a bounded canonical successor snapshot guarded by
   the expected workspace version. Draft snapshots may be incomplete but must
   be internally type-safe and relationship-consistent.
3. The append-only draft snapshot/event history is authoritative. Relational
   structure rows are the current organization-scoped projection. Omitting a
   node retires it from the active draft projection and records that outcome;
   it does not erase prior snapshots, reviews or publication components.
4. Review submission requires one closed graph that satisfies the existing
   projector invariants and creates an exact immutable graph revision/hash.
   Incomplete work returns stable blocker codes and cannot enter review or
   publication.
5. The graph includes the core `curriculum_missions` and `mission_items`
   relationships required to close an ADR-0004 publication. In this workspace
   they are presented as delivery bundles attached to lessons/items.
   ADMIN-ACTIVITY-01 remains responsible for separate reusable activity/daily-
   room templates, scheduling, assignment and reporting.
6. Centralized request limits cap one workspace at 25 levels, 100 modules,
   500 objectives, 500 lessons, 2,000 items, 500 delivery bundles and 5,000
   relationship edges. Raising a limit is an explicit configuration review,
   not a caller-controlled value.

### 4. Governed content and preview

1. A content-document key is immutable. Creating a document records its first
   English source revision; later English edits and Kannada translations call
   the existing append-only, exact-head, hash-bound authoring commands.
2. The server, not the caller, selects pedagogical authority from the existing
   code-owned content-type/localization policy. Instructions, explanations,
   feedback and learner actions remain `kn_required`; publishers cannot
   downgrade them.
3. The writable UI/API accepts typed fields, not executable or arbitrary raw
   markup. The first slice supports a code-owned governed-text schema for the
   existing content classifications and the existing
   `global-english.daily-speaking-mission.v1` field set. Unknown legacy
   payload schemas are safe read-only previews until a later explicit adapter
   is reviewed.
4. HTML, script, embedded executable content and unrestricted external URLs
   are rejected. Canonical payloads are depth-, collection- and byte-bounded;
   no individual text field exceeds 8,000 Unicode scalar values and no
   English/Kannada payload exceeds 64 KiB.
5. Author/translator attribution is derived from the authenticated membership
   and a fixed operation key. The HTTP request accepts no free-form personal
   attribution or reviewer identity.
6. Preview is private and `no-store`. It renders the exact English head and,
   when present, exact Kannada variant side by side as text with locale
   metadata and revision/hash identifiers. It executes no content, creates no
   public URL and cannot be used as publication evidence.

### 5. Media boundary

1. Content detail may show only the safe identity, kind, usage and current
   readiness state of media already attached to that exact revision. It does
   not browse the private media library or expose storage keys, evidence
   objects, uploader data or unrestricted rights metadata.
2. Upload, quarantine, scan, attach, replace, transcript/alt-text editing,
   rights confirmation and media retirement remain ADMIN-MEDIA-01 work.
3. Text/structure review may proceed while media is missing, but a new
   workspace-authored daily-speaking release remains publication-blocked until
   every runtime-required asset is attached and passes the existing exact
   rights predicate. No placeholder asset or publisher waiver is allowed.

### 6. Review queues and evidence

1. Server queues separately identify structure review, English source review,
   Kannada translation/review and publication readiness. Every entry names the
   exact current revision/hash and returns only server-derived actions the
   actor may attempt; it never discloses raw permission names.
2. Submitting structure for review seals that draft snapshot until a rejection
   or successor edit. An English-appointed reviewer may approve/reject the
   exact graph through the existing graph-governance command.
3. English and Kannada decisions remain exact-head/hash bound. Creator/reviewer
   and translator/reviewer human separation, appointment scope/validity and
   for-cause invalidation remain those of ADR-0004.
4. Approve, reject and withdraw commands use code-owned rationale keys only.
   A successor graph/source/variant makes earlier evidence stale for a new
   release but never edits or deletes it.
5. Review history exposes exact safe decision, revision, membership identifier,
   appointment identifier and time to an authorized content reader. Private
   qualification/evidence details remain in the existing appointment surface.

### 7. Publication lifecycle

1. A safe server preflight returns exact current head versions and allowlisted
   blocker codes for graph, English, Kannada, media-rights, schedule and
   suspension predicates. The client never decides readiness.
2. Reuse the existing immediate publish, future schedule, due activation,
   retirement and eligible-prior-publication rollback services. Add only
   guarded HTTP commands and status/history reads; do not duplicate their
   authority in a controller or client.
3. At most one unactivated schedule may remain current for a program. A
   publisher may cancel it before activation through a new append-only
   cancellation event with an allowlisted reason. The immutable snapshot and
   event history remain retained and a cancelled snapshot is never rollback-
   eligible.
4. Scheduled activation is an explicit publisher action at or after the
   effective instant in this non-production slice. No background job invents
   a human actor or review authority. Automatic service identity remains a
   separately reviewed operational follow-up.
5. Scheduling is program/curriculum-release scoped only. Cohort assignment or
   targeting does not alter publication authority and remains with later
   enrollment/activity work.
6. Publish, schedule, activate, cancel, retire and rollback require
   `curriculum.publish`, fresh web TOTP, header idempotency and the exact
   program-head version. Rollback/activation revalidate the target's pinned
   evidence and never move existing enrollment pins.

### 8. Consistency, history and failure behavior

1. Every mutation is one tenant-scoped PostgreSQL transaction with a canonical
   actor/request-bound fingerprint, deterministic aggregate locks, post-lock
   permission/appointment reauthorization, optimistic version checks, safe
   audit and transactional outbox evidence where a side effect follows.
2. Workspace version, graph head/revision, content/variant head and program
   publication-head version are separate concurrency tokens. The request must
   provide the exact token affected by its command; there is no last-write-
   wins or client-merged authority.
3. Same-tenant stale state returns a stable conflict only after the target and
   actor authority are resolved. Missing permission and guessed foreign IDs
   remain indistinguishable from not found. Changed idempotency replay returns
   conflict without another authoritative write.
4. History reads combine append-only workspace/graph/content/review/publication
   events without treating the generic privileged audit log as a content
   store. Audit/outbox payloads contain identifiers and fixed keys only, never
   source text, Kannada text, preview bodies, media keys or personal content.
5. Real PostgreSQL tests must force concurrent graph saves, source/translation
   heads, review-versus-edit, publish/schedule/rollback head races and
   permission/appointment invalidation. Exactly one valid authority outcome
   may commit.

### 9. Retention, privacy and ordinary deletion

1. Released revisions, graph snapshots, reviews, publications, events and
   referenced drafts remain immutable/append-only and retained under the
   existing curriculum/publication rule. Ordinary operators cannot hard-delete
   them.
2. Abandoning or retiring affects current selection only. Physical disposal of
   unreferenced draft content requires a separately reviewed migration and is
   not inferred from this approval.
3. No new learner-personal field is added. Existing staff membership IDs and
   authority attribution remain authoritative evidence. Content bodies are
   excluded from logs, analytics, audit/outbox payloads and global shell
   search; encrypted backups follow the repository backup policy.
4. No legal hold, production archival period, production restore custody or
   independent specialist review is claimed. Those remain pre-production
   gates rather than reasons to weaken repository history.

### 10. API and web experience

1. Reads are bounded, cursor-paginated where lists can grow, SQL tenant scoped
   and `no-store`. Detail endpoints return one exact version/revision with a
   server-derived action/blocker manifest.
2. Add a manifest-gated Content Studio under Learning only when at least one
   content panel is available. Navigation and controls use semantic server
   actions, never role names or raw permissions as authority.
3. The workspace provides independent loading/retry, draft/translation forms,
   private phone-width preview, exact review queues, preflight, confirmations,
   stale-conflict reload, history and publication actions at 320 CSS pixels
   with keyboard operation, visible focus and announced status.
4. Unsaved fields remain in component memory only. Content, filters, cursors,
   idempotency keys and previews never enter URL history, localStorage,
   IndexedDB, Cache Storage, ordinary Capacitor preferences or analytics.
5. OpenAPI 3.1.1, generated TypeScript clients, route-policy checks and all six
   governed UI catalogues change with the API/UI. Existing endpoints remain
   compatible or receive an explicitly versioned narrower response.

## Required implementation consequence after approval

The approved bounded specification must translate this policy into:

- an additive workspace/level/successor/history schema that preserves graph-v1
  and immutable publication evidence;
- owner-scoped program/version, canonical draft-graph and typed content
  commands layered over the existing authoring/governance services;
- exact graph/English/Kannada/readiness queues and guarded immediate/scheduled/
  activation/cancellation/retirement/rollback operations;
- a bounded private preview and complete accessible Content Studio;
- OpenAPI 3.1.1, regenerated clients, six governed catalogues, governance/
  retention/runbook updates and no new permission/default grant;
- same/cross-tenant, body/route/relationship, mass-assignment, append-only,
  stale-write, review separation, rights-blocker and real PostgreSQL race
  tests; and
- `make verify`, `make e2e`, `make build`, migration rollback/reapply and
  backup/restore evidence before ADMIN-MEDIA-01 begins.

No schema, permission seed, route, UI control or runtime behavior is authorized
by this open request alone.

## Resolution requested

To accept the recommended profile, reply exactly:

```text
Approve DR-0018 Option A as written.
```

Alternatively, select Option C or describe exact changes to Option A. An
Option B or broader alternative must explicitly resolve the level model,
successor/version lifecycle, core mission boundary, writable payload schemas,
review queues, media-dependent release behavior, scheduling/cancellation and
retention consequences.

## Resolution

Option A was accepted as written on 9 August 2026 under
[APP-0043](APPROVALS.md#app-0043) and [D-0136](DECISIONS.md). The exact
approved proposal, before its status, resolved date and this resolution
section were added, has SHA-256
`9774813580bd9522fa9c077bbca0a3cfda9c37d834d82eb2b1ca66b34f8abd72`.

ADMIN-CONTENT-01 may now publish its bounded active specification and
implement the accepted non-production vertical slice. This resolution does
not accept unbuilt schema, routes, UI or test results; authorize media-library
management, cross-owner sharing/cloning, public preview, AI drafting,
reference-data/media import, deployment or production; or replace ADR-0004's
human review, rights and retained independent-review gates.

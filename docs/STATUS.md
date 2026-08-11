# Repository status

## Current checkpoint

| Field | Value |
|---|---|
| Checkpoint | AUTH-PASSWORD-01 governed excluded-operator recovery ceremony under APP-0057 / D-0169 / D-0170 |
| Status | DR-0027 Option A is implemented and verified. The exact excluded-operator case has two distinct fresh-password-confirmed approvals and is in `waiting_delay`. Its non-bypassable deadline is 12 August 2026 at 10:44:26 UTC / 16:14:26 IST. No establishment challenge or target credential exists yet. |
| Canonical baseline | `ENTERPRISE_PRODUCT_MASTER_PLAN.md` version 3.3 |
| Updated date | 11 August 2026 |
| Git branch | `codex/confidence-conversation` |
| Product code | Free-flow AI = Practice Confidence Conversation; dashboard AI Teacher remains non-generative |
| Next required action | Do not attempt completion before 12 August 2026 at 16:14:26 IST. At or after that time, either still-authorized configured actor must sign in, establish fresh password confirmation, and select **Complete after 24 hours**. The target must then use the protected establishment page within 15 minutes to choose their own username/password. LPL-01 remains blocked until that succeeds. |
| Production/public release | Not authorized |
| Soft launch | Invite-only on Ubuntu static IP / Cloudflare HTTPS tunnel; not Google Play public |

## APP-0053 Conversation Learning Flow closure

- The soft-launch Friend turn now uses confirmed learner text for the STT-stage
  contract, the allowlisted Azure OpenAI adapter for model generation when its
  external evidence/secret/kill switch pass, and explicit client text-first /
  on-device speech playback. The cost ledger accepts only those exact
  stage/provider combinations outside local/testing; local/testing remains
  deterministic fake-only.
- Confidence Chat, Skill Focus and Review share the Friend engine but have
  distinct immutable scenario snapshots. Skill Focus defaults to Tutor with a
  five-turn threshold; Confidence Chat uses four turns and Review uses two.
  The existing Observe → Rehearse → Perform → Reflect Role-play remains the
  fourth session type.
- Product copy no longer claims the ordinary soft-launch path is a fake. It
  discloses that the partner is AI, feedback is advisory, marks/mastery are
  unchanged, turn evidence is content-free and optional voice retention is
  controlled by explicit consent. Local/demo copy remains synthetic.
- OpenAPI 0.24.0 and generated clients contain all Friend scenario keys and the
  live text-first speech/cost response contract. Migration `000180` narrowly
  permits the same provider/stage combinations in PostgreSQL.
- A repository-owned persistent user unit is enabled at
  `~/.config/systemd/user/global-english-soft-launch.service`; `Linger=yes`
  provides reboot startup. API, worker, scheduler, loopback Caddy, static-IP
  HTTP listener and Cloudflare tunnel are running. Readiness is 13 pass, six
  expected Azure-configuration warnings and zero failures.
- A pre-migration backup was written to
  `~/ge-soft-launch/backups/soft-launch-20260810T170110Z.sql.gz`; migration,
  idempotent foundation seed and production web build passed before service
  replacement.

The separate Azure OpenAI endpoint, South India regional deployment and
outside-repository secret are now installed. Readiness is 17 pass, one warning
and zero failures. The remaining warning is the intentionally disabled live
kill switch. A forced live-enabled no-call preflight returns exactly
`vendor_evidence_logging_enabled` because Azure has not granted modified abuse
monitoring and the resource does not expose `ContentLogging=false`. No model
request or model-token spend occurred.
ADR-0007/0008 still withhold ordinary real learner-audio Azure STT activation.
The Azure Speech synthetic-benchmark environment and one active credential are
already present outside the repository with mode `0600`. Its completed
5 August run passed all recorded gates; the repaired no-call wrapper now reports
the configured environment as `ready` instead of falsely reporting the
unloaded application defaults as blocked. This does not enable learner audio.
The product name is now **Global spoken English Course**. Caddy binds
`0.0.0.0:8080`; local root/readiness return HTTP 200 and `:8088` is closed.
The router forward must target this host's LAN address `192.168.1.3:8080`.
Public-IP testing from the same LAN times out, which may be missing NAT
loopback; external cellular/VPS verification and Ubuntu firewall inspection
remain required. The existing `https://www.globalspokenenglish.com` site was
not accessed or changed.
Production/Play release and independent review are not authorized.

## Audience documentation

The repository now includes three evidence-based audience guides:

- [Learner user guide](guides/LEARNER_USER_GUIDE.md) for sign-in, daily
  missions, offline use, private media, Confidence Conversation, voice/privacy
  choices, results and troubleshooting;
- [Administrator and operator user guide](guides/ADMINISTRATOR_USER_GUIDE.md)
  for privileged access, People, Learners, Content Studio, Media Library,
  operations, safety and governed lifecycle checklists; and
- [Sales feature and solution guide](SALES_FEATURE_GUIDE.md) for positioning,
  buyer use cases, feature status, responsible-AI boundaries, pilot discovery,
  claims guardrails and roadmap communication; and
- [Ubuntu soft-launch runbook](SOFT_LAUNCH_UBUNTU_RUNBOOK.md) for the invite-only
  real-product host under APP-0053.

Each guide distinguishes repository-implemented, controlled/conditional,
demonstration-only, planned and deferred behavior. They introduce no runtime,
API, schema, provider, permission, release or architecture change.

## Active learner-experience plan

[LESSON_PRACTICE_LOOP_PLAN.md](LESSON_PRACTICE_LOOP_PLAN.md) version 2.0
records the future-proof Learn → Talk → Check → Apply → Review platform plan.
It covers dynamic media analysis, governed lesson/assessment generation,
all-level commissioning, Skill Chat, deterministic mastery, personalization,
speech, trainer workflows, accessibility, offline delivery, analytics,
integrations and separately gated platform-evolution horizons. LPL-00–LPL-07
form the launch-critical core; LPL-08+ cannot be pulled forward silently. The
plan has completed LPL-00. [DR-0020](DR-0020_LESSON_PRACTICE_FOUNDATION_REQUEST.md)
Option A and [ADR-0015](adr/0015-lesson-practice-foundation.md) are accepted
under [APP-0046](APPROVALS.md#app-0046) / D-0140. The active bounded
[LPL-00 specification](LPL-00_SPEC.md) and locked
[verification scope](LPL-00_VERIFICATION_SCOPE.md) fix the exact schema,
scoring, speaking-evidence, answer-isolation and unlock policy. LPL-01 is now
active. Its [exact inventory](LPL-01_CONTENT_INVENTORY.md) found no current
governed set of four Level 1 audio lessons. A subsequent authenticated,
read-only [legacy source map](LPL-01_LEGACY_SOURCE_MAP.md) identified the
reported structure: one Level 1 `Can` source lesson with four ordered MP3 parts
(Introduction, Practice, Conversation and Interaction), inside a repeated
17-level × six-lesson architecture. APP-0047 now authorizes those four exact
hashes for bounded private non-production ingestion, transcription and
AI-assisted derivative drafting. DR-0021 Option A is now accepted under
[APP-0048](APPROVALS.md#app-0048) / D-0145 /
[ADR-0016](adr/0016-long-form-lesson-audio-media.md), and its bounded
`lesson_audio` Media Library prerequisite has passed repository verification,
E2E and build gates. The four authorized source files were reacquired into
temporary non-repository storage, matched byte-for-byte and hash-for-hash, and
passed the project-owned real ffprobe and ClamAV adapters. DR-0022 Option A and
APP-0049 bound one normal phone identity to the exact synthetic Direct
administrator. DR-0023 Option A then preserved the locked incident in a
validated snapshot, passed a strict expected-delta audit, restored the exact
pinned baseline, reran migrations/seed and replayed only that identity
binding. Ordinary OTP, exact membership and retained TOTP now work without a
support reset. Through explicit ffprobe/ClamAV adapters and the first-party
HTTP lifecycle, all four APP-0047 hashes are active private `lesson_audio`
assets with matching byte counts, durations and clean scans. Failed adapter
attempts remain failure evidence, and quarantine objects use the normal
reconciliation worker. DR-0024 Option A is accepted and executed under
[APP-0051](APPROVALS.md#app-0051), D-0150/D-0151 and
[ADR-0019](adr/0019-local-lpl-pilot-and-rights-authority-bootstrap.md). One
standalone private LPL pilot program now owns one least-privilege role and one
accepted program-scoped rights-confirmer appointment. Ordinary selected-
membership/fresh-TOTP HTTP commands created exactly four approved APP-0047
rights revisions for later gated web/Android delivery in India, derivatives
allowed, sharing false and no expiry. The persistent bootstrap switch is
absent. No lesson-audio metadata, attachment, transcript, curriculum version,
publication, enrollment or learner delivery was inferred. The next exact stop
was accepted as
[DR-0025](DR-0025_LPL01_TRANSCRIPTION_COMMISSIONING_REQUEST.md) Option A under
[APP-0052](APPROVALS.md#app-0052), D-0153 and
[ADR-0020](adr/0020-governed-curriculum-media-transcription-commissioning.md). It fixes
a separate provider-neutral curriculum-media path using direct exact-MP3 Azure
Fast Transcription, encrypted expiring candidates, immutable content-free
provenance and a first-party human reconciliation command. Its fixed one-
campaign caps are four assets, eight HTTP attempts, 37,520,380 uploaded bytes,
2,345,026 billable input milliseconds and ₹100 estimated Azure usage. The
bounded repository implementation is now complete and verified under D-0154:
it adds exact campaign/attempt/event/reconciliation provenance, an authorized
Azure adapter behind the project port, encrypted expiring candidates,
content-free retention/dead-letter processing, a private fresh-TOTP
reconciliation API and safe external reports. OpenAPI 0.23.0 and generated
clients changed with that API. Ordinary DI remains disabled and no live
credential, provider call, candidate, transcript or metadata revision was
created. A subsequent D-0157 live-preflight audit applied the additive
commissioning migration only after a content-free PostgreSQL backup, captured
same-day official price/FX evidence and projected ₹22.378788 maximum spend.
That preflight dispatch still failed closed: prior evidence identified the contracting-entity
review only by an opaque reference, and Azure security defaults require
interactive reauthentication before the exact billing entity or current key
could be verified. No campaign permit existed and database/provider counts
remained zero at that historical stop. The subsequently authorized exact
campaign completed with four successful attempts, zero failures/retries,
18,760,190 uploaded bytes, 1,172,513 billable input milliseconds and
₹11.189394 estimated usage. Its four encrypted provider candidates remain
non-authoritative pending first-party human reconciliation.
The current local smoke data still has only one unique Level 1 MP4
with no transcript/topic objective and derivative use set to false. The
[Level 1 review packet](LPL-01_LEVEL1_BLUEPRINT_REVIEW_PACKET.md) therefore
remains blocked and unapproved; no source text was promoted into an answer key
and no LPL practice runtime code exists yet. Under
[APP-0053](APPROVALS.md#app-0053) LPL-01 is paused as the critical path while
Ubuntu invite-only soft launch is active. The product owner's live-AI and
repository-media allowance applies to bounded non-production soft-launch work
within the APP-0053 ₹10,000 Azure ceiling and existing privacy, tenancy,
review and release controls; it is not unlimited spend or Play/public release
authority.

D-0158 hardens the APP-0053 launch boundary after an operational review found
that the dedicated conversation env named by the runbook was not loaded by the
runtime/preflight and that syntactically valid setup placeholders could satisfy
parts of provider authorization. Runtime and preflight now share one precedence
contract: provider settings come from the dedicated conversation env while the
main soft-launch env owns `APP_ENV`, driver and kill switch. Setup preserves an
existing conversation env, new evidence scaffolds are unapproved/invalid, and
the provider rejects both old and new scaffold sentinels before HTTP. The live
host remained healthy through its HTTPS tunnel with two active publication
heads and two active enrollments; a fresh mode-`0600` gzip-valid database dump
was taken. Live tutoring remains off because current external vendor evidence,
endpoint, deployment and secret are still placeholders/missing. No provider
request or spend occurred.

## Current governance boundary

The approved ADMIN program is
[ADMIN_REFERENCE_PARITY_PLAN.md](ADMIN_REFERENCE_PARITY_PLAN.md), with the
implementation boundary frozen in
[ADMIN_GOVERNANCE_MATRIX.md](ADMIN_GOVERNANCE_MATRIX.md). ADMIN-SHELL-01
implements only the matrix's derived `O-READ` surface.

Delivered shell:

- `GET /operator/overview` derives organization metadata, navigation and panel
  keys from the current membership's existing content, audit and support read
  permissions without disclosing permission names;
- `GET /operator/search` federates fixed safe descriptor fields across only
  authorized, SQL organization-scoped sources with a bounded encrypted cursor
  tied to tenant, normalized query and source set;
- the existing audit listing now uses encrypted organization-bound
  `(occurred_at, id)` keyset continuation without changing its safe action or
  field allowlist;
- the responsive React workspace hides learner navigation, loads every panel
  independently, retries partial failures, keeps search ephemeral and provides
  keyboard-operable safe audit detail at 320 CSS pixels; and
- OpenAPI 0.17.0, generated clients and all six source-linked UI catalogues
  changed together. No migration, feature schema, permission seed, provider,
  environment variable or mutable domain behavior was added.

Delivered ADMIN-ORG slice:

- the permission-derived People workspace now covers safe current-organization
  metadata, branches, cohorts, staff memberships, code-only invitations,
  custom roles, derived trainer profiles, existing authority appointments and
  count-only membership-selected credential administration;
- mutations are web-only, fresh-TOTP, header-idempotent and version/fingerprint
  guarded, with tenant-scoped transactions, safe audit and content-minimized
  administration events;
- staff suspension strips current authority and only credentials still
  selected for that membership; reactivation restores no role, assignment,
  appointment, credential or authority;
- invitation secrets are show-once, terminal transitions advance version and
  the hourly maintenance path clears terminal digests, retries revoked web
  session storage cleanup and expires/purges bounded operational history; and
- OpenAPI 0.18.0, generated clients, six used permission definitions, all six
  source-linked UI catalogues and the responsive People journey changed
  together. Writable organization settings, learner administration and
  global identity/recovery authority were not added.

Delivered ADMIN-LEARNER slice:

- the manifest-gated Learners workspace provides bounded current-organization
  directory/detail, enrollment history and code-only learner invitation tasks
  with independent loading/retry, cursor pagination and safe server-derived
  actions;
- exact learner-safe invitation roles, attributed append-only current-goal
  correction, one versioned current enrollment, immutable verified curriculum
  pins, cohort-derived trainer summaries and reversible learner suspension are
  enforced transactionally under fresh TOTP and header idempotency;
- suspension revokes only credentials still selected for the target learner
  membership, retains learning/enrollment evidence and restores no credential,
  enrollment or pin on reactivation;
- the fixed privacy inventory is now `learner-privacy-v2`, including the
  current-goal projection, correction history and linked content-minimized
  administration events; and
- OpenAPI 0.19.0, generated clients, three used learner permissions, all six
  source-linked catalogues and the responsive Learners browser journey changed
  together. Support notes, global identity/account controls, learner exports,
  direct trainer assignment and enrollment reactivation were not added.

Delivered ADMIN-CONTENT slice:

- append-only current-organization workspaces now create programs, explicit
  graph-v2 levels through delivery bundles, immutable draft revisions,
  descriptor corrections, abandonment and same-owner successors while every
  existing graph-v1 publication remains byte-stable and immutable;
- typed governed-text and daily-speaking-mission adapters provide bounded
  English/Kannada authoring and private no-store preview without raw JSON,
  markup execution, public URLs or browser persistence;
- exact graph, English, Kannada and readiness queues derive safe blockers and
  semantic actions, retain reviewer/translator separation and appointment
  authority, and expose stable tenant-safe conflict codes;
- publication status and commands cover immediate publish, future scheduling,
  due activation, guarded cancellation, retirement and rollback with exact
  heads, evidence revalidation and idempotency; forced PostgreSQL races cover
  workspace, source, variant, review, lifecycle, permission and appointment
  overlap; and
- OpenAPI 0.20.0, generated clients, all six governed catalogues and the
  manifest-gated responsive Content Studio changed together under D-0137. No
  permission, grant, platform capability or media-management authority was
  added; media-dependent daily missions remain blocked until ADMIN-MEDIA-01.

Support-note writes, notices/messages, moderated group conversation,
provider-backed entitlement/payment work, platform configuration, legacy data
import and production/public release remain blocked by the exact retained
decisions in the matrix.

## Ordered remediation state

| Checkpoint | Repository state | Principal evidence |
|---|---|---|
| LV-01 | Complete | Draft-only, caller-idempotent legacy video import; no fabricated identity, authority, approval, rights or publication |
| PRIV-DEL-01 | Complete | Durable content-free deletion intent, retry-safe object deletion and exact privacy receipt links |
| AND-AUTH-01 | Repository implementation complete | One Android Passport transport, secure native refresh storage and single-use PKCE callback handling; physical device evidence remains external |
| MFA-01 | Repository implementation complete | RFC 6238 TOTP, one-use recovery, fresh scoped assertions and global credential revocation; independent production review remains open |
| PIPE-01 | Complete | Pinned dependency/security updates and green formatting/static/type/localization gates |
| VIDEO-01 | Complete | Verified bounded single-range delivery, constant-query evidence and bounded Android Blob fallback |
| PRIV-DSR-01 | Complete | Full fixed-class privacy inventory, deterministic erasure outcomes and shared voice deletion reconciliation |
| REL-01 | Complete | Fail-closed CI aggregate, bounded outbox dead letters, recovery commands and executable load/restore evidence |
| MAINT-01 | Complete | Shared request/test fixtures, self-bootstrapping isolated test storage, measured diagnostics indexes and current-status reconciliation pass the full gate |

No ADMIN feature schema is part of these remediation checkpoints.

## ADMIN program state

| Checkpoint | Repository state | Principal evidence |
|---|---|---|
| ADMIN-DISC-01 | Complete | Read-only reference capability inventory and approved safe parity specification |
| ADMIN-GOV-01 | Complete | Frozen governance matrix plus executable common operator-route policy; no feature schema or route |
| ADMIN-SHELL-01 | Complete | Permission-derived overview/navigation, independent panels, safe bounded search, encrypted audit continuation and accessible responsive drill-down |
| ADMIN-ORG-01 | Repository implementation complete | Staff-only tenant administration, strict delegation and final-manager boundaries, show-once invitations, reversible authority-stripping suspension, selected-credential revocation, bounded history/cleanup, OpenAPI 0.18.0 and accessible People UI |
| ADMIN-LEARNER-01 | Repository implementation complete | Bounded learner directory/detail, learner-safe invitations, append-only goal correction, versioned enrollment/pins, cohort-derived trainers, selected-credential lifecycle, OpenAPI 0.19.0 and accessible Learners UI; support notes remain excluded |
| ADMIN-CONTENT-01 | Repository implementation complete | Graph-v2 owner workspaces, typed authoring/preview, exact review/readiness queues, guarded publication lifecycle, OpenAPI 0.20.0 and accessible Content Studio under D-0136/D-0137; media management remains excluded |
| ADMIN-MEDIA-01 | Repository implementation and retained gates complete | Exact-asset private quarantine, inspection/scanning, accessibility metadata/review, appointed rights, immutable attachment/replacement, retirement and private preview; OpenAPI 0.21.0, generated contracts and Media Library UI; PostgreSQL races, full verify/E2E/build and backup/restore passed on 10 August 2026 |

## Current product and architecture boundary

### Identity, tenancy and privileged access

- Users and authentication records are platform-global; memberships, roles
  and domain resources remain organization-owned under ADR-0003.
- Web/PWA uses Sanctum cookie sessions. Production Android uses Passport
  Authorization Code with PKCE through the project authentication abstraction.
- Android access tokens remain memory-only; refresh credentials and pending
  PKCE state use the reviewed native secure store. Browser `localStorage`,
  IndexedDB and ordinary Capacitor preferences are not credential stores.
- Mutable operator actions are web-only and require fresh TOTP where specified.
  TOTP is not phishing resistant, and support-assisted reset is not yet an
  implemented product path.

### Learning, content and video

- Learning evidence, mastery, review and progression remain deterministic.
  AI paths cannot write authoritative learning records.
- Published curriculum and submitted evidence remain immutable/versioned.
  The canonical seed is synthetic and unpublished. The local staging-smoke
  legacy publications contain approved generic English/Kannada wrappers and
  learner-delivery rights, but those records do not approve a transcript,
  topic objective or lesson-practice blueprint and explicitly disallow
  derivative use.
- Lesson video delivery is exact-pin and current-rights gated. It supports one
  bounded byte range with a strong governed SHA-256 ETag. Browsers use native
  range loading; Android uses an authenticated, type/length checked Blob no
  larger than 20 MB.

### Conversation, voice and privacy

- Local/testing uses deterministic provider fakes. Production conversation
  and speech bindings remain fail closed unless their separately approved
  environment and evidence gates are satisfied.
- Learner audio upload requires the accepted ADR-0007 consent and retention
  path. Raw sensitive audio, transcripts, object keys and provider payloads
  are excluded from logs and operational health output.
- Voice source deletion records a durable tenant-owned receipt and content-free
  leased outbox event before storage I/O. Five failed attempts dead-letter the
  unpublished work without clearing the object key or completing the receipt.
- Privacy export emits the fixed `learner-privacy-v2` class inventory without
  silent row caps. Erasure records its pre-mutation outcome, removes mutable
  authorization/personalization, ends only the selected membership, revokes
  active credentials and retains explicitly justified immutable authority.

### Release enforcement

- CI has separate `verify`, `android` and `operations` jobs plus an `always()`
  `release-gate` that accepts only exact success from every dependency.
- The release-contract validator checks required commands, dependencies,
  action SHA pins and PostgreSQL digest pinning.
- Voice-deletion status is aggregate/content-free and fails non-zero for stale
  or dead-lettered work. Requeue requires the exact CLI event identifier after
  dependency remediation.
- The load harness fails for any non-200 or p95 threshold breach. The logical
  restore drill compares column schema, migrations and aggregate
  deletion/tombstone state without printing records.

Repository contract and local drill evidence is not a clean remote CI run,
branch-protection configuration, production capacity result or promotion.

## Authorization and next gate

[DR-0015](DR-0015_PRODUCTION_REVIEW_WAIVER_REQUEST.md) Option A is accepted
under [APP-0040](APPROVALS.md#app-0040) / D-0127. Ordered non-production
implementation, testing and staging may continue without repeated requests for
the retained legal or independent reviews. Missing review still cannot be
described as completed, passed or waived, and this authority does not permit
production or public release.

[ADR-0014](adr/0014-secure-reference-admin-functional-parity.md), the
[approved parity plan](ADMIN_REFERENCE_PARITY_PLAN.md) and accepted DR-0016
Option A authorized the now-complete ADMIN-ORG-01 repository slice. The
implementation remains limited to the organization/staff boundary in
[ADMIN-ORG-01_SPEC.md](ADMIN-ORG-01_SPEC.md); it does not authorize reference
code/data/media import, payment/provider spend, deployment or production.

ADMIN-LEARNER-01 has passed its repository exit gates under DR-0017 Option A,
[APP-0042](APPROVALS.md#app-0042) / D-0134 and
[ADMIN-LEARNER-01_SPEC.md](ADMIN-LEARNER-01_SPEC.md). ADMIN-CONTENT-01 has now
passed its repository exit gates under accepted
[DR-0018](DR-0018_ADMIN_CONTENT_AUTHORING_PUBLICATION_REQUEST.md) Option A,
[APP-0043](APPROVALS.md#app-0043), D-0136, D-0137 and the bounded
[specification](ADMIN-CONTENT-01_SPEC.md). The Accountable Product Owner has
now supplied the exact owner-level media-rights statement preserved under
[APP-0044](APPROVALS.md#app-0044), D-0138 and the
[rights attestation](ADMIN_MEDIA_RIGHTS_ATTESTATION.md). That statement
authorizes the next decision/specification step but does not automatically
approve an asset or bypass ADR-0004's exact-hash, use-scope, permission,
appointment and confirmation predicates. [DR-0019](DR-0019_ADMIN_MEDIA_LIBRARY_RIGHTS_LIFECYCLE_REQUEST.md)
is retained as the historical pre-implementation gate record. ADMIN-MEDIA-01
implementation and retained repository gates are now complete within its
accepted specification. Support-note tables, keys, routes and UI remain
blocked. LPL-00 is complete. LPL-01 has identified its legacy source, recorded
exact processing authorization, implemented the accepted long-form lesson-
audio prerequisite, passed the exact four-file real-adapter ingestion and
completed DR-0024's private pilot/runtime-rights authority. DR-0025 Option A is
accepted, and the bounded transcription/candidate/reconciliation repository
slice has passed its required gates under D-0154. LPL-01 is now stopped before
an exact contracting-entity/current-credential verification, campaign permit,
provider run and human transcript reconciliation. Same-day price/FX and the
isolated encrypted candidate-store path passed the D-0157 audit, but neither
can waive the missing gates. LPL-02 remains unauthorized.

## Verification state

Current LPL-01 evidence:

- read-only local PostgreSQL inventory found 3 programs, 27 lessons, 26 media
  rows and 2 local smoke publications, representing one synthetic unpublished
  lesson and two duplicate imports of 12 unique legacy level MP4s plus a
  synthetic scaffold;
- repository and gitignored artifact searches found no Level 1 audio lesson
  files; the retained legacy catalog records 17 Video rows, 12 MP4 downloads
  and missing video files for Levels 4, 5, 12, 15 and 16;
- authenticated read-only legacy inspection then reconciled one course, 19
  level descriptors and 102 numbered-level lesson rows: each Level 1–17 has
  Video, Audio, PDF/Text Book, Workshop, Group Practice and Assignment nodes;
  the Audio pages expose 67 MP3 references across all levels;
- Level 1 is explicitly `Can — I can speak in English`; its Audio node contains
  four ordered teaching MP3s. Ephemeral reads recorded exact SHA-256 and byte
  size for Introduction, Practice, Conversation and Interaction without
  retaining credentials, cookies or media in the repository;
- all 12 local MP4 SHA-256 values match the retained inventory; the exact
  private first-import Level 1 object was present and a fresh object-store
  read produced the governed SHA-256
  `8cc0ab3ceae5247e15b9feb17c2b9836d102a131b42232e6d74627b706edb5c6`;
- every imported level wrapper lacks transcript, can-do, target pattern,
  question, answer-rationale and Apply-rubric content; its only objective is
  the shared generic `legacy.level_video.orientation` objective;
- all local imported video rights rows allow web/Android learner delivery in
  India but disallow sharing and derivative use. APP-0047 separately
  authorizes only the four selected MP3 hashes for bounded private ingestion,
  transcription and derivative drafting;
- DR-0021 Option A / APP-0048 / D-0145 / ADR-0016 added only the governed
  `lesson_audio` kind and retained the current private Media Library lifecycle;
- each exact APP-0047 source was reacquired into temporary non-repository
  storage, matched its recorded SHA-256 and byte size, inspected as MPEG audio
  by the project ffprobe adapter, and returned clean through the project
  ClamAV adapter. The exact operational evidence is recorded in
  [LPL-01_INGESTION_PREFLIGHT.md](LPL-01_INGESTION_PREFLIGHT.md);
- final `make verify`, `make e2e` (16/16 Chromium journeys) and `make build`
  passed for the lesson-audio prerequisite. `make ai-eval` was not run because
  no prompt, model, provider or orchestration behavior changed;
- DR-0024 Option A created one standalone private pilot program, one exact
  two-permission non-system role assigned only to the retained membership and
  one program-scoped appointment through the existing authority service;
- ordinary OTP, exact membership and fresh TOTP accepted that appointment,
  after which four exact HTTP rights confirmations created four approved
  initial rights revisions with APP-0047 evidence, `android`/`web`, `IN`,
  derivatives and later-gated learner delivery allowed, sharing false, no
  expiry and the approved retention/deletion keys; and
- exact bootstrap replay and read-only persisted-state checks found one
  audit/outbox pair, appointment events `appointed` then `accepted`, four and
  only four rights rows and zero pilot curriculum versions. No attachment,
  transcript, provider run, learner record, enrollment or publication was
  created; and
- the final DR-0024 `make verify` passed 144 SQLite tests / 1,815 assertions,
  280 PostgreSQL tests / 4,017 assertions, PHPStan, audits, contracts,
  localization, lint/typecheck and 270 package/web tests; `make e2e` passed all
  16 Chromium journeys. `make ai-eval` and a separate `make build` were not
  required because no provider orchestration or packaging changed (the E2E
  server's production Vite build passed with the retained chunk-size warning);
  and
- accepted DR-0025 Option A now governs the exact-byte curriculum-media campaign,
  additive provenance/reconciliation model, seven-day encrypted candidate
  retention and hard four-asset/eight-attempt/37,520,380-byte/2,345,026-ms/
  ₹100 limits. Current official provider evidence was checked and the APP-0052
  approval-recording `make verify` passed 144 SQLite tests / 1,815 assertions,
  280 PostgreSQL tests / 4,017 assertions and all static, audit, contract,
  localization and 270 package/web tests. The acceptance created no runtime
  artifact and no provider call occurred; and
- the D-0154 implementation adds six tenant-owned commissioning tables, a
  provider-neutral adapter boundary, exact reauthorization/cap reservation,
  PostgreSQL single-run locking, encrypted private candidates, seven-day
  expiry, retry/dead-letter deletion, 365-day unreferenced provenance purge,
  private no-store fresh-TOTP reconciliation, safe external reporting and a
  disabled-default dedicated command. Focused SQLite passed 32 tests / 344
  assertions; focused PostgreSQL passed 16 tests / 305 assertions. Final
  `make verify` passed 160 SQLite tests / 1,876 assertions, 297 PostgreSQL
  tests / 4,344 assertions and all static, audit, contract, localization and
  270 package/web tests. `make e2e` passed the real reconciliation API journey
  (4 tests / 105 assertions) and 16/16 Chromium journeys; deterministic
  `make ai-eval` passed 15/15 with zero estimated cost and correctly skipped
  its separately gated speech half; `make build` passed with the retained Vite
  chunk-size warning. No live transcription provider call occurred; and
- the D-0157 execution audit created a PostgreSQL custom-format pre-migration
  backup (799,372 bytes; SHA-256
  `a1510723c88f350a60d99844ff0ae268d01b482eae4512165d40ef33643370d2`),
  applied only migration `2026_08_10_000170`, and confirmed zero campaigns,
  provider attempts, reconciliations and candidate-deletion rows. Official
  Central India retail pricing was $0.36/hour; the 10 August ECB cross-rate
  derived 95.30315880571182 INR/USD and a conservative 9,531 microunits per
  billable second, making the maximum approved manifest estimate ₹22.378788.
  The external mode-`0600` preflight audit has SHA-256
  `2c56e5a359636dc4da3e55a3917953019dce3738af40816caaf2eb4fe6d63abb`.
  Azure management-plane billing/key verification then failed with the
  allowlisted `interactive_reauthentication_required` condition, so no vendor
  evidence projection or campaign permit was created and no request/spend
  occurred. A mode-`0700` external resume utility (SHA-256
  `281e0cd64ced70652b7d5d1c233666a7f2f63165e31939a1086b5d31cee6276f`)
  now performs the exact billing-account/subscription correlation and
  resource/key comparison after normal Azure reauthentication without
  printing identifiers or credentials; Bash syntax validation passed and it
  cannot create the campaign permit. The documented mono-response
  compatibility correction passed a 17-test/50-assertion focused boundary
  suite, final `make verify` (165 SQLite tests / 1,883 assertions; 297
  PostgreSQL tests / 4,345 assertions; 270
  package/web tests), `make e2e` (4 API tests / 105 assertions and 16/16
  Chromium) and deterministic `make ai-eval` (15/15, zero cost).

LPL-01 remains active rather than complete. Accepted DR-0025 Option A fixes the
finite provider run ceiling, fail-closed kill switch, no-training/minimum-
retention settings and immutable provenance/human-reconciliation path. Its
repository implementation, external campaign and provider-candidate creation
are complete. Four attempts succeeded for four exact assets with zero failures
or retries; the four encrypted candidates remain protected and
non-authoritative. Human transcript reconciliation, English/Kannada review and
one exact source-supported publishable Level 1 blueprint remain pending.
LPL-02 has not started.

Final ADMIN-MEDIA-01 evidence:

- the implementation follows DR-0019 Option A / D-0139: organization-scoped
  model-audio and lesson-video quarantine, exact hash/inspection/scanner
  evidence, human acceptance, governed accessibility metadata/reviews,
  appointed rights confirmation, attachment, replacement, retirement and
  private preview;
- the focused real-PostgreSQL `ConcurrentMediaLibraryTest` passed 5 tests / 53
  assertions in disposable PHP 8.4 with `pdo_pgsql` and `pcntl`;
- final `make verify` passed using the repository's pinned-compatible PHP
  8.4.24 and Node 24.18.1/pnpm 11.18.0 environment: Composer validation/audit,
  Pint (587 files), PHPStan (565 files), 130 SQLite tests / 1,665 assertions,
  271 PostgreSQL tests / 3,890 assertions, pnpm audit, locale validation,
  OpenAPI/client drift, release contract, ESLint, TypeScript and package/web
  tests all passed;
- final `make e2e` passed 16/16 Chromium journeys, including Media Library;
- final `make build` passed with the retained Vite chunk-size warning; and
- `POSTGRES_DB=global_english_test make backup-restore-drill` passed with a
  measured local RTO of 1 second and artifact
  `artifacts/ops/backup-restore-20260810T050100Z.json`.

The first disposable PHP image lacked `pcntl`; the first full-container static
analysis used a mismatched mount path; and initial E2E/build/restore attempts
met sandbox or temporary-storage setup failures. None reached a failing product
assertion. Each environment issue was corrected and the exact applicable gate
then passed. `make ai-eval` was not required for ADMIN-MEDIA because no prompt,
model, provider or orchestration behavior changed.

APP-0044 remains reusable umbrella ownership evidence only. It does not
automatically approve a file, rights scope, program, appointment or
publication. Independent security/privacy/tenancy/accessibility/language
review, production object-store/scanner validation, deployment and
production/public release remain unclaimed.

Final ADMIN-CONTENT-01 evidence:

- the master plan, ADR-0003/0004/0013/0014, D-0136, APP-0043, the frozen ADMIN
  matrices, existing curriculum/governance/publication code and the bounded
  specification were inspected before runtime edits;
- migration apply/rollback/reapply passed on SQLite and PostgreSQL (1 test / 20
  assertions on each), preserving graph-v1/publication tables while restoring
  only the additive workspace contract;
- focused Content Studio API, publication lifecycle and shell suites passed on
  both databases; focused web component/API-client behavior passed 27 tests;
- forced real PostgreSQL concurrency passed 9 tests / 95 assertions, with both
  workers entering the lock queue for graph, English head, Kannada head,
  structure review/edit, publish, schedule and rollback races, plus permission
  and appointment invalidation overlap;
- final `make verify` passed Composer/pnpm validation and audits, Pint,
  PHPStan, 128 SQLite tests / 1,526 assertions, 257 PostgreSQL tests / 3,541
  assertions, locale governance, OpenAPI/generated-client drift,
  release-contract validation, ESLint, recursive TypeScript and 247
  package/web tests;
- final `make e2e` passed all 15/15 Chromium journeys, including Content
  Studio partial failure, structured authoring, exact review, stale-state
  retention, axe and 320-pixel no-overflow coverage;
- final `make build` passed Laravel cache preparation/cleanup and the
  production web build with the retained Vite chunk-size warning;
- `POSTGRES_DB=global_english_test make backup-restore-drill` passed with exact
  schema/migration/aggregate reconciliation and measured local RTO 1 second;
  and
- `make ai-eval` was not run because no prompt, model, provider or
  orchestration behavior changed. The five non-English catalogues retain their
  declared pending-independent-review warnings and approved-English fallback.

Earlier ADMIN-LEARNER-01 evidence remains recorded below:

Final ADMIN-LEARNER-01 evidence:

- the master plan, accepted tenancy/authentication/content/offline/privacy
  ADRs, privacy inventory, frozen ADMIN matrices and existing learner/profile/
  enrollment code and tests were inspected before any runtime edit;
- DR-0017 Option A is accepted under APP-0042 / D-0134 and translated into
  exact schema, permission, route, lifecycle, retention, UI and test contracts;
- focused learner API/schema suites passed on SQLite and PostgreSQL; focused
  learner web/API-client behavior passed 23 tests;
- the real PostgreSQL administration concurrency class passed 11 tests / 85
  assertions, forcing lock-queue overlap for learner invitation accept/revoke,
  duplicate current-enrollment creation, competing goal corrections, cohort
  change versus withdrawal and verified activation, and membership selection
  versus suspension alongside the retained ADMIN-ORG races;
- OpenAPI 0.19.0 validates, generated TypeScript contracts match, and all six
  catalogues validate with only the five declared independent-review warnings;
- final `make verify`: passed — Composer/pnpm audits; Pint; PHPStan level 8;
  128 SQLite tests / 1,390 assertions; 249 PostgreSQL tests / 3,369 assertions;
  locale governance; OpenAPI/generated-client drift; release-contract
  validation; ESLint; recursive TypeScript; and 227 package/web tests;
- final `make e2e`: passed all 14/14 Chromium journeys, including the Learners
  partial-failure, goal-correction, ephemeral-code, axe and 320-pixel journey;
- final `make build`: passed with the existing measured Vite chunk-size
  warning; and
- `POSTGRES_DB=global_english_test make backup-restore-drill`: passed with an
  exact schema, migration-ledger and deletion/learner-goal authority aggregate
  match; measured local RTO 1 second; and
- `make ai-eval` was not required because no prompt, model, provider or
  orchestration behavior changed.

The final repository-wide result is recorded in the ADMIN-LEARNER-01 section
of [QUALITY_REVIEW.md](QUALITY_REVIEW.md).

The immediately preceding REL-01 final evidence remains:

- `make verify`: 120 SQLite tests / 932 assertions, 203 PostgreSQL tests /
  2,759 assertions and 182 package/web tests passed with audits, formatting,
  PHPStan, localization, OpenAPI/client drift, release-contract validation,
  lint and TypeScript checks;
- `make e2e`: 11/11 Chromium journeys passed;
- `make build`: passed;
- production-path Android `testDebugUnitTest assembleDebug`: passed;
- readiness drill: 100/100 HTTP 200, p95 202 ms under its explicit 1,000 ms
  non-production threshold; and
- logical restore drill: exact schema/migration/deletion-state match, 1 second.

## Known limitations and retained gates

- Remote GitHub-hosted CI has not run on a commit containing the current
  working-tree changes; branch protection must require `release-gate`.
- Physical DM-01/DM-02 device evidence, representative low-memory/network
  video evidence, a signed AAB and Play closed/public release evidence remain
  open.
- The readiness drill is not the authenticated normal-API SLO, a 2× pilot
  capacity result or a production soak. Empty-schema query plans prove index
  eligibility, not production latency/selectivity.
- The logical restore drill is not encrypted production backup, offsite
  retention, continuous WAL/PITR or production RPO evidence.
- Production alert routing, qualified legal review and independent security,
  privacy, tenancy, accessibility, language/content and release review remain
  open.
- Kannada, Malayalam, Tamil, Telugu and Hindi UI wording remains
  pending-independent-review; production resolves visibly to approved English.
- No complete human-reviewed pilot pathway or approved production media exists;
  the media library currently has only non-production fixture evidence.
- The existing Vite chunk-size warning and optional Rolldown WASM peer-version
  mismatch remain measured maintenance limitations, not passing performance
  evidence.
- Synchronous privacy export still needs production-volume measurement and an
  approved encrypted case-delivery mechanism.
- No production object-store load test, trusted media verification cache,
  Android native streaming data source or multipart byte-range response exists.
- The operator workspace includes the bounded ADMIN-ORG People,
  ADMIN-LEARNER Learners, ADMIN-CONTENT Content Studio and ADMIN-MEDIA Media
  Library slices. PostgreSQL race evidence and the retained release gates are
  incomplete; groups, activity review, communications, moderation, CMS,
  entitlement and configuration parity remain incomplete.
- Federated search is a bounded live database projection, not a durable index;
  its production-volume latency and concurrent-update behavior have not been
  measured.

## Professional client path

- Staging Friend: [STAGING_CONFIDENCE_CONVERSATION.md](STAGING_CONFIDENCE_CONVERSATION.md)
- Closed beta: [CLOSED_BETA_PILOT_RUNBOOK.md](CLOSED_BETA_PILOT_RUNBOOK.md)
- Android lifecycle: [ANDROID_AUTHENTICATED_REQUEST_LIFECYCLE.md](ANDROID_AUTHENTICATED_REQUEST_LIFECYCLE.md)
- Password authentication: [ops/PASSWORD_AUTHENTICATION_RUNBOOK.md](ops/PASSWORD_AUTHENTICATION_RUNBOOK.md)
- Historical privileged MFA: [PRIVILEGED_MFA_RUNBOOK.md](PRIVILEGED_MFA_RUNBOOK.md)
- Voice deletion: [VOICE_DELETION_RECONCILIATION.md](VOICE_DELETION_RECONCILIATION.md)
- Privacy inventory: [PRIVACY_DATA_INVENTORY.md](PRIVACY_DATA_INVENTORY.md)
- Release gates: [ops/RELEASE_GATES.md](ops/RELEASE_GATES.md)

## DR-0026 password-only authentication decision stop — 11 August 2026

> Historical stop record. APP-0056 subsequently authorized AUTH-PASSWORD-01;
> the completed repository implementation is recorded below.

- The product owner requested username/password sign-in for every learner and
  privileged user, with no TOTP or other second factor.
- This conflicts with the direct/institutional OTP onboarding contract, the
  master plan's privileged-MFA baseline, ADR-0002 and accepted DR-0013 Option A.
  No authentication code, schema, API, credential or session was changed.
- [DR-0026](DR-0026_PASSWORD_ONLY_AUTHENTICATION_REQUEST.md) Option B is now
  accepted under APP-0055 / D-0163 / ADR-0021 with explicit acceptance of the
  documented single-factor risk. Master-plan version 3.3 records
  username/password-only authentication for every role and fresh same-password
  confirmation for sensitive commands.
- The exact LPL-01 Azure Fast Transcription campaign completed before this
  decision stop: four attempts succeeded for four exact assets, with zero
  failures or retries, 18,760,190 uploaded bytes, 1,172,513 billable input
  milliseconds and ₹11.189394 estimated usage under the ₹100 cap. Provider
  candidates remain non-authoritative and protected pending human
  reconciliation.
- [AUTH-PASSWORD-01](AUTH-PASSWORD-01_SPEC.md) now defines the additive
  credential schema, APIs, migration/cutover, privileged recovery authority,
  OTP/TOTP retirement and verification boundary. Runtime implementation is not
  yet authorized. Human reconciliation, English/Kannada review and a
  publishable Level 1 blueprint remain incomplete; the current endpoint still
  requires fresh TOTP and no bypass is authorized.
- LPL-02 and later LPL checkpoints, remaining ADMIN checkpoints, independent
  reviews, physical-device evidence, production and Google Play authorization
  remain outstanding. Remote CI has not run against the current working tree.

## AUTH-PASSWORD-01 repository implementation — 11 August 2026

- APP-0056 / D-0164 authorized the exact specification. The additive migration
  now supplies platform-global canonical usernames, versioned Argon2id
  credentials, session-bound password freshness, enumeration-safe ordinary
  recovery, delayed two-operator privileged recovery, append-only safe security
  evidence and an idempotent global cutover marker.
- Web and Android use username/password while retaining Sanctum cookie/CSRF and
  system-browser Passport S256 PKCE boundaries. Account Security is available
  to learners and operators. The local web port is 5174 and does not conflict
  with Anamaya's reserved port.
- Every formerly MFA-protected route remains on the exact protected set through
  mode-aware `auth.password-confirmed`: pre-cutover migration can still fail
  closed on legacy evidence, while password-only mode accepts only a current
  five-minute same-password confirmation plus the existing independent tenant,
  permission and appointment checks.
- The final migration was rolled back and reapplied before local credentials or
  a cutover marker existed. PostgreSQL then passed the password/Passport slice
  (14 tests / 605 assertions) and the isolated full integration suite (306 /
  4,454); SQLite passed 172 / 1,904. PHPStan reported zero errors, Pint passed,
  dependency audits found no known vulnerabilities, OpenAPI/generated clients,
  release/soft-launch contracts, locale validation, ESLint, TypeScript and all
  workspace tests passed.
- The exact root `make verify` passed end-to-end. `make e2e` prerequisites
  passed and Chromium passed 16/16 axe/reflow/journey
  tests. `make build` passed. The PostgreSQL backup/restore drill passed with a
  two-second RTO. Production-path Capacitor sync plus Android JDK 21
  `testDebugUnitTest assembleDebug` passed.
- The local password-only cutover completed after two users independently
  established credentials and two ready recovery actors were configured. A
  reviewed manifest accounted for all 16 active users as two credential-ready
  users plus 14 explicit content-free local pre-production exclusions. The
  command retired legacy OTP/TOTP material and globally revoked sessions and
  tokens; its replay reported the already-complete state without mutation.
- Pre-cutover recovery evidence is the readable custom PostgreSQL dump
  `/tmp/gse-pre-password-cutover-20260811.dump` (898,961 bytes; SHA-256
  `de3c59e76ffaa6132cf08fb4cd8bb728691f87070b05a5b8f4fafde4c4680374`).
  Aggregate post-cutover checks found zero active sessions, mobile token
  families, OAuth access tokens, MFA factors/challenges/recovery codes and OTP
  challenges. The live local mode endpoint returned `password_only`; a
  synthetic legacy OTP request returned HTTP 410.
- A credential-setup usability follow-up now makes the exact 4–64-character
  username and 15–128-character non-common-password rules visible and
  browser-validated, adds a show-password control, keeps the save action at a
  full usable width, and explains that successful one-time setup signs the
  current session out before the normal Sign in button appears. Rejections now
  retain generic server handling while giving safe corrective guidance. The
  live Vite service on port 5174 hot-reloaded the fix; no user credential was
  inspected, logged or manufactured.
- After routine password sign-in, **Username and password security** is
  collapsed behind an explicit button and does not load its forms until
  opened. The authenticated learner/operator workspace remains the primary
  view, and a separate Sign out action is always available from the compact
  secure-session summary.
- A live follow-up found that the signed-in learner-only session was correctly
  classified non-privileged but the shared security panel still offered
  privileged confirmation and username-change controls. Those controls are now
  shown only for a privileged current session; ordinary users retain password
  change and session management. Failed privileged confirmation has a specific
  safe corrective message instead of the generic load/update error. Focused
  component coverage passes 6 tests, the complete web suite passes 242 tests,
  ESLint and TypeScript pass, and locale governance revision `.7` validates all
  six catalogues with the five declared independent-review warnings.
- A deeper authority check found that both credential-ready accounts are
  learner-only and that exactly one of the 14 exclusions is an active
  permission-derived organization administrator with a verified contact. The
  configured platform recovery actors were not recognized by the session
  classifier; a composite classifier now recognizes only explicit recovery/
  emergency allowlist entries before falling back to organization permissions.
  Focused unit, real-PostgreSQL and PHPStan evidence passes. This grants no
  organization authority.
- DR-0027 records the remaining fail-closed stop: privileged recovery rejects
  a username-less credential-unready target, so the excluded operator cannot
  yet establish a credential. No role mutation, direct credential write,
  OTP/TOTP restore or recovery case was performed.
- Remote CI, independent security/privacy/tenancy/accessibility/language review,
  production blocklist and recovery-delivery evidence, physical-device
  inspection, production deployment and Google Play authorization remain open.
  LPL-01 human reconciliation/English-Kannada review/blueprint and all later
  LPL/ADMIN checkpoints remain separate and incomplete.

## Android release-signing mechanism — 11 August 2026

- The production Android Gradle tree now has a fail-closed upload-signing
  configuration. It accepts only the four `GE_ANDROID_UPLOAD_*` process
  variables, rejects partial input, requires an absolute readable keystore
  outside the repository and refuses release APK/AAB tasks when the inputs are
  absent. Debug builds remain independent of release keys.
- `make android-release-bundle` requires a deployed HTTPS API origin, builds
  the production web bundle, synchronizes only `com.globalenglish.app`, runs
  native debug unit tests and builds the signed release AAB. The release-gate
  contract now protects this command from accidental removal.
- Negative validation passed: Gradle configuration succeeded without secrets,
  while `bundleRelease` failed before execution with the expected missing-
  signing error. Positive validation with a disposable `/tmp` self-signed test
  key passed 172 Gradle tasks and produced a signed 4,807,426-byte AAB with
  SHA-256
  `096097a1871e24d5f46e0410ace3fb5db18a379bccf3b646d20f97fb2c`.
  `jarsigner -verify` reported `jar verified`. Native `testDebugUnitTest
  assembleDebug` separately remained successful; the debug APK is 6,344,957
  bytes with SHA-256
  `721f21e0e92c22c8254f6b75be257fa384c4cb77841be6e75d4433fd77e14b20`.
- This closes the repository signing-wiring gap, not the operational signing
  gate. A product-owner-controlled upload key, protected backup/custody record,
  production-bound signed AAB, Play App Signing, physical DM-01/DM-02 evidence
  and Google Play upload authorization remain outstanding. GitHub publication
  was still paused at this mechanism-only check; the candidate publication
  recorded below supersedes that transient tooling state.

## Release-candidate remote verification — 11 August 2026

- The accumulated governed implementation was committed to
  `codex/confidence-conversation` and published as draft pull request
  [#3](https://github.com/srbhandary1/GESC/pull/3). Repository-local IDE state,
  runtime output, build artifacts and secrets remain excluded.
- Clean GitHub runners exposed two environment assumptions that local ignored
  files had masked. CI now installs the Android command-line SDK through an
  exact-commit-pinned action, and production session-default coverage runs in
  an isolated process. The PostgreSQL HTTP suite additionally fixes its HTTPS
  cookie contract before middleware resolution so host environment variables
  cannot weaken the assertions.
- The final local `make verify` passed Pint, PHPStan, 175 SQLite tests / 1,919
  assertions, 309 PostgreSQL tests / 4,496 assertions, dependency audits,
  locale/OpenAPI/release contracts, ESLint, TypeScript and all 282 JavaScript
  tests. A hostile-environment reproduction separately passed the affected 25
  PostgreSQL authentication/PKCE tests / 800 assertions.
- GitHub Actions run
  [31487637244](https://github.com/srbhandary1/GESC/actions/runs/31487637244)
  passed Verify, Android, Operations and the fail-closed Release Gate. This
  closes the previously open remote-CI gate for this candidate revision.
- Remote repository verification is not production authorization. Independent
  security, privacy, tenancy, accessibility and language reviews; production
  infrastructure/configuration evidence; product-owner upload-key custody;
  physical-device DM-01/DM-02 evidence; a production-bound AAB; Play App
  Signing/upload authority; and LPL-01 human content reconciliation remain
  open.

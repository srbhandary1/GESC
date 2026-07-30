# PR-006 deterministic daily-speaking verification scope

## Authority and boundary

This record defines and verifies PR-006 — Deterministic daily-speaking slice —
against:

1. `ENTERPRISE_PRODUCT_MASTER_PLAN.md` version 3.0;
2. [ADR-0003](adr/0003-resource-ownership-and-organization-context.md);
3. [ADR-0004](adr/0004-content-ownership-publication-and-curriculum-sharing.md);
4. [APP-0005](APPROVALS.md#app-0005);
5. [the accepted PR-005 verification scope](PR-005_CONTENT_PUBLICATION_VERIFICATION_SCOPE.md);
   and
6. the repository rules in `AGENTS.md`.

APP-0005 accepts PR-005 at
`daeee1df7a7e4d31427001aedece76d8121413d6` and authorizes implementation of
only PR-006. It does not approve a future curriculum publication, Kannada
wording, model-audio asset or media-rights record. It also does not authorize
PR-007 offline/outbox work, AI or speech-provider orchestration, a
recording-upload/retention workflow, production deployment or a later
checkpoint.

## Verification status

PR-006 implementation and repository-controlled verification are complete and
ready for Accountable Product Owner review. This record does not accept the
checkpoint, approve learner-facing content or authorize PR-007.

The results below were run against the final PR-006 worktree. The exact
implementation commit is identified in the checkpoint completion handoff
because a commit cannot contain its own hash.

## Bounded implementation baseline

PR-006 is limited to one deterministic, no-AI learner journey:

- authenticate through the accepted first-party web session;
- complete minimal adult onboarding and select one allowlisted learner goal;
- use the authenticated learner's active organization membership and active
  enrollment;
- resolve one mission only through that enrollment's exact immutable
  publication pin;
- present governed English content, approved Kannada guidance, transcript and
  model-audio references when an authorized publication exists;
- record, play back, discard and retry raw audio only in volatile browser
  memory;
- submit deterministic formative checks;
- retain immutable attempt/response evidence and derived first/latest/best
  pointers;
- calculate deterministic knowledge mastery and a review schedule; and
- show a result and progress state without AI.

PR-006 does not make the unpublished foundation seed executable. A real
learner-facing mission remains unavailable until accountable humans create
valid runtime academic, Kannada and media-rights evidence and publish it
through the accepted PR-005 commands.

The PR-006 reader is deliberately exact-one: an eligible immutable publication
must contain exactly one mission, and the requested mission must be that
manifest mission. Zero or multiple missions fail closed. Multi-mission
objective projection is a later design change, not an implicit extension of
this checkpoint.

## Authoritative first/latest/best semantics

The formative evidence scope is the exact tuple:

```text
learner membership
+ enrollment
+ immutable curriculum publication
+ curriculum mission
```

Only **submitted** mission attempts participate in the summary:

- `first` points to the submitted attempt with the lowest server-assigned
  `attempt_number`;
- `latest` points to the submitted attempt with the highest server-assigned
  `attempt_number`; and
- `best` points to the submitted attempt with the highest deterministic check
  score, using the lowest `attempt_number` as the stable tie-break.

Attempt numbers are allocated by the server under the authoritative
transaction. A caller cannot choose or reorder them. Responses inside an
attempt are append-only. A submitted attempt, its score inputs, publication
pin and result cannot be edited in place. First/latest/best pointers are
derived projections over retained evidence; they do not replace or rewrite
the attempts to which they point.

An exact idempotent replay returns the original result. Reuse of an
idempotency key for a different fingerprint conflicts. Another learner or
membership cannot replay or discover the original actor's command. Competing
submissions serialize so that an attempt is finalized once and the evidence
summary, mastery and review schedule advance atomically.

The original start response remains `{status: in_progress}` on exact replay;
that historical response does not reopen a submitted attempt. A delayed
submission replay derives knowledge state and first/latest/best only from
submitted attempts at or before that attempt's server number, so later retries
cannot change the replayed response. The progress read model remains current.

## Mastery and review policy

PR-006 keeps knowledge and spoken authority separate:

- deterministic check answers are scored only from the exact governed
  publication content and a versioned rule;
- a qualifying deterministic result may advance **knowledge mastery**;
- review timing is derived deterministically from the submitted evidence and
  the versioned review rule, using a server timestamp;
- the mastery state and review schedule identify the exact attempts/evidence
  and rule version used; and
- later retries append evidence and may advance the derived summary without
  modifying an earlier result.

A local browser capture receipt is an engagement/input receipt only. It is
not proof of intelligible learner speech, cannot affect the deterministic
check score and cannot satisfy spoken mastery. PR-006 must not create a
`mastered` spoken state without an eligible spoken-evidence identifier.

Because [DR-0005](DECISIONS.md#open-decision-requests-before-affected-checkpoints)
remains open until PR-009, PR-006 intentionally provides no retained or
provider-verified spoken evidence. Its no-spoken-mastery path is an enforced
boundary, not a failed speech-analysis result.

## Recording privacy boundary

Raw learner audio is local and ephemeral:

- capture uses the browser's media APIs only after a clear learner action and
  permission explanation;
- bytes remain in volatile browser memory for immediate playback and retry;
- no raw audio, transcript, filename, object URL, media-device label or audio
  hash enters an API request, database row, object-storage object, IndexedDB,
  service-worker cache, log, metric, audit event or outbox event;
- retry replaces the prior local blob and revokes its object URL;
- cancel, route exit, logout and component unmount stop every media track and
  release every object URL; and
- blocked, unsupported or denied microphone access has a transparent
  alternative path but never awards spoken mastery.

The server may retain only the bounded non-authoritative receipt defined by
the PR-006 contract. The receipt must not be described as a recording asset,
speech verification, transcript or authoritative spoken evidence.

Recording upload, persistent storage, consent-state history, retention,
deletion, provider processing and human review remain governed by DR-0005 and
the later checkpoint that resolves it.

## Publication, media and seed boundary

- Mission delivery starts from
  `PinnedEnrollmentAccessResolver`; it does not accept a caller-selected
  organization, enrollment owner, curriculum publication or curriculum
  version as authority.
- The learner must own the selected active membership and enrollment.
- `pending_curriculum`, completed and withdrawn enrollments cannot start or
  submit a mission attempt.
- Mission and check identities must exist in the exact publication assigned to
  the enrollment.
- Ordinary share withdrawal preserves an existing immutable pin. Current
  rights failure, owner inactivity or emergency suspension still denies
  delivery and new authoritative learning writes.
- Attempts snapshot the learner organization, learner membership, enrollment,
  content owner, curriculum version, publication, publication manifest and
  mission/rule identities needed to reproduce the result.
- A current publication head, newer revision or caller-provided content cannot
  replace the attempt's pinned publication.
- Model audio is an owner media asset only when its exact asset and
  rights-evidence revisions are present in the immutable publication and
  authorize learner delivery for the active channel, territory and instant.
- The canonical `FoundationSeeder` remains synthetic, Codex-attributed and
  unpublished. It must not manufacture human review, rights confirmation or a
  production publication.

Contract stubs and synthetic test fixtures may exercise the browser journey.
They remain test evidence only and cannot be represented as accountable
content, Kannada, voice or media-rights approval.

## Invariant and evidence matrix

| Invariant | Required enforcement | Required verification |
|---|---|---|
| Learner records are organization-owned | non-null learner organization; composite membership/enrollment relationships; guarded ownership fields | PostgreSQL schema, mass-assignment and cross-tenant tests |
| A user acts only as the selected learner | membership-derived organization context plus exact user/membership/enrollment equality | same-tenant-other-learner, other-tenant, inactive-user and inactive-membership denial |
| Learning requires one active immutable pin | active enrollment plus PR-005 pinned-access resolution; no learner pin mutation | pending/completed/withdrawn denial, direct pin mutation rejection and exact-pin tests |
| The mission belongs to the pin | mission and governed check are resolved from the pinned publication, not a current draft/head | wrong-publication, wrong-version, wrong-mission and later-head rejection |
| PR-006 remains an exact-one slice | immutable publication has exactly one mission and the requested ID is that mission | zero/multiple/guessed mission rejection |
| Suspension and current rights still govern delivery | one trusted instant and locked PR-005 delivery/evidence state | suspension, owner inactivity and rights-expiry/withdrawal denial; ordinary share-withdrawal continuity |
| Attempt numbering is authoritative | server allocation under transaction and uniqueness for the exact evidence scope | concurrent starts/submissions, caller-number rejection and gap/duplicate protection |
| Submitted evidence is immutable | append-only response and submitted-attempt protection | direct update/delete rejection and post-submit response denial |
| Replay is actor- and fingerprint-bound | normalized request fingerprint plus scoped idempotency key | exact replay, changed-payload conflict and other-actor denial |
| First/latest/best is exact | lowest number, highest number, highest score plus earliest-number tie-break | ordered retries, score regression, equal-score tie and excluded-draft tests |
| Scoring is deterministic | server-owned answer/rule version from exact publication; no client score authority | forged score/answer rejection, stable result and no answer leakage before response |
| Completion writes are atomic | attempt submission, summary, knowledge mastery, review and safe event records share one transaction | forced-failure rollback and concurrent-finalize single-winner tests |
| Spoken mastery requires spoken evidence | knowledge and spoken dimensions are distinct; capture receipt is ineligible | passing quiz with no capture, local capture receipt, denied microphone and forged spoken flag tests |
| Raw audio is not retained | browser-memory-only capture and strict API/log/storage exclusion | frontend storage/network spies, object-URL/track cleanup and backend payload rejection |
| Seed and media authority remain honest | production seed stays unpublished; test fixtures are labeled; model audio uses exact rights evidence | seeder integrity, no synthetic approval/publication and media-rights delivery tests |
| No later checkpoint slips in | no service-worker mission cache, IndexedDB outbox, provider adapter, transcription, AI or recording upload | source-scope audit and explicit route/storage/provider absence checks |

## API, client and end-to-end verification

Every new HTTP operation must be documented in OpenAPI 3.1.1 and regenerated
client types. Learner routes require accepted authentication and organization
context middleware. Error responses use the accepted problem-details
contract and do not reveal another tenant's learner, enrollment, attempt,
mission or publication.

Backend authoritative behavior must be verified through real PostgreSQL
integration tests, including duplicate and concurrent execution. SQLite tests
remain useful for request/response behavior but cannot replace PostgreSQL
constraint, trigger, lock or transaction evidence.

The browser golden path must cover:

```text
authenticated learner
→ minimal onboarding and goal
→ pinned mission
→ model audio and transcript
→ local record/playback/retry
→ deterministic check
→ result
→ progress and review due
```

If the browser suite uses a contract-stubbed API or synthetic media response,
the result must be labeled as a browser-contract journey rather than a true
full-stack published-content path. Backend PostgreSQL integration remains the
authority for tenant ownership, publication pins, attempt evidence,
idempotency, concurrency, mastery and review.

The browser suite also verifies keyboard behavior, accessible names and status
announcements, mixed-language metadata, microphone-denial fallback, absence
of raw-audio network/storage writes, object-URL cleanup and no AI/provider
request.

## Verification commands

Run from the repository root after implementation is complete:

```text
make verify
make e2e
make openapi
make build
```

`make ai-eval` is not applicable because PR-006 changes no prompt, model,
provider or AI orchestration. It must not be reported as passing.

| Command or check | Result |
|---|---|
| Focused PR-006 PostgreSQL integration suites | Pass: governed lifecycle/exact-one 2 tests/78 assertions; concurrent start/submit 1/24; authenticated HTTP boundary 2/65; request validation 3/10; verified private audio 4/8 |
| Fresh migration, repeat seed and rollback/forward exercise | Pass on the explicitly verified `global_english_test` PostgreSQL 14.19 database; repeat seed remained idempotent, empty PR-006 rollback and forward reapply succeeded, final seed remained unpublished with 21 permissions and zero learner/publication records |
| `make verify` | Pass: 39 SQLite tests/264 assertions, 163 PostgreSQL tests/1,848 assertions, 23 localization tests, 8 design-system tests and 50 web tests; formatting, PHPStan, lint, type checks, audits, locale policy and contract drift also passed |
| `make e2e` | Pass: 7 Chromium scenarios, including the contract-stubbed learner golden path and microphone-denial path |
| `make openapi` | Pass: OpenAPI 3.1.1 document version 0.4.0 and regenerated TypeScript contracts match |
| `make build` | Pass: Laravel optimization/cleanup and strict production Vite build, 63 modules |
| Local Markdown structure and repository-link validation | Pass: 25 in-scope Markdown files, zero structural or local-link errors; external URLs were not live-checked |
| `git diff --check` and checkpoint-scope audit | Pass: no whitespace errors, later-checkpoint implementation, secret, provider path or learner-audio persistence/upload path |

## Known limitations

- APP-0005 does not approve a learner-facing curriculum publication, Kannada
  translation, transcript, model-audio asset or media-rights record.
- The canonical synthetic foundation mission remains unpublished and cannot
  be delivered to a learner.
- Browser contract fixtures are not accountable human content review or proof
  of a full-stack published-media journey.
- Historical replay ordering is intentionally bounded to PR-006's one
  mission/objective slice. Before multiple missions can contribute to one
  objective, the result contract needs a persisted projection snapshot or a
  global objective-evidence order rather than mission-scoped attempt numbers.
- Raw learner audio is not retained, uploaded, transcribed, analyzed or
  available for trainer review.
- A local capture receipt cannot award spoken mastery.
- DR-0005 remains open for recording consent, retention, provider processing
  and deletion.
- PR-007 offline bundles, service-worker mission caching, IndexedDB outbox and
  reconnect synchronization are excluded.
- AI conversation, speech-to-text, text-to-speech providers and pronunciation
  analysis are excluded.
- Independent tenancy, security, privacy, accessibility, English-academic,
  Kannada-language, media-rights and legal review remains required before
  production.
- Docker/Compose and PostgreSQL 18.4 were unavailable; database verification
  used local PostgreSQL 14.19.
- Local PHP 8.4.17 differs from the pinned 8.4.23 patch. Pinned Node.js
  24.18.1, pnpm 11.18.0 and Composer 2.10.2 were used.
- GitHub Actions did not run on a remote runner, and external Markdown URLs
  were not live-checked.

## Exit gate

Every repository-controlled exit condition is complete. APP-0007 records the
Accountable Product Owner's explicit acceptance of PR-006 at
`598ad387e65b94707564dce1e199c97deb411d82`, including the
no-production-content and local-only-recording limitations.

Only PR-007 decision-resolution work is authorized. PR-007 product
implementation remains blocked until ADR-0006 is explicitly accepted and
DR-0004 is resolved.

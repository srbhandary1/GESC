# PR-005 content and publication verification scope

## Authority and boundary

This record verifies PR-005 — Versioned content and publication foundation —
against:

1. `ENTERPRISE_PRODUCT_MASTER_PLAN.md` version 3.0;
2. [ADR-0004](adr/0004-content-ownership-publication-and-curriculum-sharing.md),
   accepted at proposal commit
   `d9d7cba4b2ccc0d9f2e8656e5fd19bab9ce9dc99`;
3. [APP-0004](APPROVALS.md#app-0004); and
4. the repository rules in `AGENTS.md`.

APP-0004 authorizes implementation and verification of only PR-005. This
record does not accept PR-005, authorize PR-006, approve a curriculum
publication or authorize production.

## Delivered foundation

PR-005 introduces:

- organization-owned programs, curriculum versions, objectives, prerequisite
  edges, modules, lessons, items and missions;
- append-only English source revisions, Kannada localized variants and exact
  draft heads;
- code-governed content classification, with every implemented PR-005 content
  type treated as pedagogically authoritative;
- append-only curriculum-graph snapshots and academic review of the exact
  graph hash;
- scoped, accepted academic, Kannada and rights authority appointments;
- English, Kannada and media-rights review evidence bound to exact immutable
  identities, hashes and appointment-event revisions;
- deterministic publication manifests and component tables;
- immediate publication with atomic scheduled/activated events, future
  scheduling, activation, retirement, eligible-prior-release rollback and
  owner/platform emergency suspension;
- explicit immutable-publication sharing, ordinary withdrawal and a dedicated
  recipient access path;
- recipient-owned cloning with immutable source lineage;
- atomic enrollment activation with an immutable owned or shared publication
  pin; and
- one synthetic, safe, unpublished mission draft for development review.

No PR-005 public HTTP operation is introduced. The new write paths are
project-owned domain commands. The only API-contract change is the
`pending_curriculum` enrollment state required before publication assignment.

## Invariant and evidence matrix

| Invariant | Enforcement | Verification |
|---|---|---|
| Curriculum records have non-null owner organizations | owner-prefixed schema, composite foreign keys and tenant-scoped models/commands | schema and cross-tenant integration tests |
| Authors and translators append; they do not overwrite | revision lineage, exact heads, optimistic concurrency and append-only triggers | authoring command tests and direct mutation rejection |
| Classification cannot be changed by a publisher | classification is stored on each immutable source revision and selected by the manifest builder | schema/classification and publication tests |
| Academic review covers the exact English source and graph | hash-bound reviews, scoped appointments and creator/reviewer human separation | governance and missing/stale/rejected graph tests |
| Required Kannada cannot fall back to English or move across equal-hash sources | exact source/variant/review/component foreign keys and current Kannada approval are publication prerequisites | failed publication without Kannada evidence and cross-source schema-protection tests |
| Revision and authority evidence cannot skip lineage or substitute roles | same-aggregate immediate-parent triggers plus exact accepted appointment-kind/event triggers | source, Kannada, rights and authority schema-protection tests |
| Media must have current deterministic program-scoped rights | asset/evidence hashes, channel/territory/use predicates, exact expiry boundaries and invalidation checks | governance, successor-rights and schedule/activation withdrawal tests |
| Publication contents are closed and immutable | graph traversal, canonical JSON, aggregate SHA-256, immutable snapshot/components and graph-sealing triggers | lifecycle, schema and mutation tests |
| Schedule and activation are distinct even for immediate publication | scheduling creates an inactive immutable snapshot; immediate publication records scheduled plus activated atomically; activation revalidates exact evidence at or after the effective instant | lifecycle and concurrent scheduling tests |
| Competing first writes have one winner and authority cannot be revoked between check/write | locked authority/program/head rows, expected head version and fingerprint-bound idempotency | real PostgreSQL forked-worker publish/schedule races and concurrent permission-removal lock test |
| Rollback never edits or promotes a never-activated snapshot | append-only rollback selects a previously activated stored manifest and revalidates its exact pinned evidence | lifecycle tests for restoration, scheduled-only denial and later review withdrawal |
| Retirement blocks new assignment without moving pins | head is cleared; enrollment pin columns and activation time remain immutable | retirement and enrollment lifecycle tests |
| Ordinary share withdrawal preserves existing pins | grant events/heads deny new use while pinned access bypasses later grant status only | owner/shared enrollment lifecycle test |
| Emergency suspension blocks new and existing delivery without blocking remediation | program head suspension, fresh MFA, safe reasons, remediation references, exact database-bound session/audit evidence and append-only owner/platform events; replacement heads preserve suspension | owner and named-platform emergency tests, forged-evidence schema tests, retired pins and suspended replacement |
| Named platform intervention grants no tenant edit authority | deny-by-default stable-user allowlist and signed target-bound platform capability | platform-context and forged/expired authorization tests |
| Signed platform authorization is one-time without breaking exact replay | unique append-only audit nonce; replay preflight validates the current authority, exact actor and fingerprint before returning the stored result | no-duplicate-audit replay and different-command nonce-reuse rejection tests |
| Shared and pinned delivery cannot mix pre/post-withdrawal or suspension state | resolver-owned transactions, one trusted instant and locked grant/pin/publication/head/activation/evidence reads | PostgreSQL transaction/lock assertions and withdrawal/suspension access tests |
| Clone authority does not copy approval | exact grant/use resolution, new recipient identifiers and source lineage; approvals/publications/grants/pins are omitted | clone and cross-tenant tests |
| Audit/outbox payloads exclude bodies and learner data | allowlisted identifier-only payload keys | command tests and outbox recorder checks |

## Migration and recovery notes

Migrations `2026_07_30_000020` through `2026_07_30_000023` are additive to the
accepted PR-004 schema:

- `000020` creates organization-owned curriculum structure and immutable
  revision/graph foundations;
- `000021` creates authority, review, media and rights evidence;
- `000022` adds unique signed platform-authorization nonce evidence; creates
  publication, component, event, command, share and clone records; and adds
  immutable graph and exact emergency-evidence enforcement; and
- `000023` adds enrollment publication pins, preflights inconsistent completed
  rows, converts pre-content unpinned `active` rows to
  `pending_curriculum`, and enforces valid immutable activation.

The four migrations pass fresh apply, repeat seed, four-step rollback, forward
re-apply and seed on the local PostgreSQL service when no enrollment exists.
That rollback exercise proves pre-adoption migration mechanics only.
Migration `000023` refuses its `down()` operation when any enrollment exists,
preserving authoritative pins rather than silently mapping or dropping them.
After adoption, recovery must use a reviewed forward fix, backup restoration
and explicit data reconciliation rather than an automatic production
rollback.

## Verification commands

Run from the repository root with the declared Node.js, pnpm and Composer
versions available on `PATH`:

```text
make verify
make e2e
make openapi
make build
```

`make ai-eval` remains intentionally unavailable before PR-011 and is not
applicable to this checkpoint because PR-005 adds no prompt, model, provider or
AI orchestration.

The final command results and exact test/assertion counts are recorded in
[QUALITY_REVIEW.md](QUALITY_REVIEW.md).

## Security, privacy and tenancy review

- Every owner-side mutation requires membership-derived organization context
  and the exact stable permission. The in-transaction recheck locks the
  authority rows it relies on, closing concurrent suspension/role-revocation
  races.
- Recipient preview, assignment and cloning use a dedicated allowlisted
  cross-owner resolver; they do not disable ordinary tenant scoping. Shared
  and pinned access resolve from one trusted instant while holding locks on
  their mutable authority and delivery state.
- Authority appointments supplement rather than replace permissions.
- APP-0004 self-appointment is deny-by-default and becomes eligible only when
  configuration binds the exact evidence, role and recorded qualification to
  the approved human's stable runtime user ULID.
- Publication, sharing, enrollment and emergency changes use PostgreSQL
  transactions, aggregate locking, optimistic concurrency, idempotency, audit
  and the transactional outbox.
- Existing pinned delivery still revalidates rights and suspension. It does
  not treat a historical share grant as access to later publications.
- Platform emergency authority is disabled by default and names stable active
  user IDs through `PLATFORM_CONTENT_EMERGENCY_ACTOR_USER_IDS`.
- Suspension/resumption events record the privileged authentication-session
  identifier and remediation reference. PostgreSQL verifies that session
  belongs to the accountable actor; platform events also bind the exact prior
  audit target, capability, action, reason, program and correlation.
- Every new signed platform operation records a unique authorization nonce.
  Exact actor-bound idempotent replay returns its stored result without a
  duplicate privileged audit, while another command cannot reuse the nonce.
- No learner content, raw media, credentials, transcript, phone number or
  provider payload is added to audit/outbox data.
- The seed contains synthetic development text only. It has no approval,
  Kannada variant, publication or real learner data.

## Known limitations and deferred work

- APP-0004 consolidates the accountable product, technical, tenancy, rights,
  English-academic and Kannada roles in one human. That review is explicitly
  non-independent; independent tenancy, English-academic and Kannada review
  remains a pre-production gate.
- No seeded or production curriculum has human runtime review evidence. The
  synthetic mission is deliberately unpublished.
- Media asset import and initial pending-rights creation have no public or
  dedicated domain import command. Program-scoped confirmation, rejection and
  withdrawal are implemented; production ingest remains a reviewed later
  workflow.
- Cloning a media-bearing publication fails closed until a reviewed
  object-copy, retention and derivative-rights workflow exists.
- Automatic activation workers and schedule cancellation are not included;
  scheduling and activation are explicit command operations.
- Evidence transitions take PostgreSQL table-level share locks. This favors
  correctness in the foundation and requires representative concurrency
  benchmarking before high-volume production use.
- No learner mission execution, attempt, mastery, speech capture/playback,
  offline behavior, AI/provider integration or Android product UI is included.
  Those remain later checkpoints.
- Docker and the declared PostgreSQL 18.4 image are unavailable on this host;
  database verification uses the available PostgreSQL 14 service. Local PHP is
  8.4.17 rather than the declared 8.4.23 patch.
- Remote CI and independent specialist reviews remain outstanding.

## Exit gate

PR-005 implementation may be presented for Accountable Product Owner
acceptance only after the repository-wide verification commands pass and
`docs/STATUS.md`, `docs/DECISIONS.md` and `docs/QUALITY_REVIEW.md` record the
result. PR-006 remains blocked until the human explicitly accepts PR-005 at
its exact implementation commit and authorizes only PR-006.

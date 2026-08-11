# LPL-00 — locked Lesson Practice Loop verification scope

**Status:** Locked under APP-0046 / D-0140 / ADR-0015

**Prepared:** 10 August 2026

**Accepted scope SHA-256:**
`a7bf02f70dc5bb602c3f2b4341718bd04d1a218c829c82a3db9998b70d1b9dd4`
before this status and acceptance annotation

**Applies to:** LPL-02 through LPL-07, with each checkpoint running the
applicable subset plus its ordinary repository gates

## 1. Purpose

This scope prevents a visually complete lesson loop from shipping with a
second learning authority, answer leakage, broken v1 evidence, cross-tenant
access, AI-controlled progression or unusable provider/offline failure states.

A later checkpoint may add tests, but it may not remove or weaken a locked row
without a reviewed decision explaining the replacement evidence.

## 2. Backward-compatibility locks

| Contract | Required evidence |
|---|---|
| Publication v1/v2 predecessors | Golden canonical JSON/manifest hashes and existing publication readers remain unchanged |
| Daily mission v1 | Existing one-check public payload, result behavior, 0/100 policy and progress output remain exact |
| Existing v1 attempts | Migration preserves every identifier, hash, state, score, schedule and append-only rule |
| Existing offline v1 | Golden bundle/lease behavior remains exact and is documented as the legacy plaintext-answer exception |
| Existing Friend/Role-play | Session, prompt, turn, voice, correction, memory and cost behavior remains unchanged without a practice-context link |
| Unknown schemas | Read-only/unavailable with an allowlisted reason; never guessed or coerced into LPL v1 |

Run migration apply/rollback/reapply on empty and representative predecessor
data in SQLite and PostgreSQL. Rollback must refuse if doing so would discard
live LPL authority, or prove a lossless reviewed reversal.

## 3. Definition and publication validation

Tests must reject:

- fewer than 3 or more than 7 Check items;
- duplicate item/option IDs, unresolved answers or unknown item types;
- fewer than 2 or more than 6 single-choice options;
- zero/negative weights or weights not summing to 100;
- pass threshold, scoring key or review key outside the code-owned registry;
- unknown/extra fields, over-limit text/list/depth/payload, markup, URL schemes
  or control characters;
- bundle edges spanning more than one v1 lesson/primary objective;
- missing/stale English or Kannada review;
- missing/mismatched media hash, clean scan, accessibility or rights evidence;
- prompt/scoring/review hash mismatch; and
- publication ordering or canonical-hash nondeterminism.

Success evidence must prove a successor definition/publication leaves its
predecessor component, enrollment pin, context and attempt reproducible.

## 4. Answer-leakage lock

Before submission, exact correct answers and rationales must be absent from:

- lesson path and practice-loop responses;
- browser/Android state, URL/query/history, localStorage, ordinary IndexedDB,
  Cache Storage and ordinary Capacitor preferences;
- Skill Chat allowlisted context, constructed prompt, provider request,
  repair/retry request and provider-fake capture;
- conversation events/SSE, errors/problem details and client diagnostics;
- audit, outbox, logs, traces, metrics labels/properties and cost ledger;
- media/transcript/accessibility responses; and
- every new LPL offline manifest, lease and queued command.

Use sentinel answer/rationale strings that fail a repository-wide captured-
surface assertion if they appear. Test direct requests, validation failures,
provider failure/retry/fallback, stale publication, cross-tenant guesses and
offline conflict paths.

After submission, the result may show only the exact pinned item answer and
rationale. It must not expose another item/publication's hidden content.

## 5. Deterministic attempt/scoring lock

Prove on SQLite and PostgreSQL:

- one open attempt per exact learner/enrollment/mission rule;
- response numbers are append-only and sequential under replay/concurrency;
- latest valid response per item is selected deterministically;
- submission rejects missing or unknown items and seals exactly once;
- weighted scores at 0, 79, 80, 99 and 100 produce exact expected states;
- 100 alone is all-items-correct; 80 is a pass;
- first/latest/best evidence remains correct across retries;
- two concurrent submissions produce one submission, projection and schedule;
- a better later attempt advances projection without changing predecessor
  evidence; and
- AI/STT/TTS output cannot invoke or alter attempt, mastery, schedule or unlock
  writes.

Use database constraint/trigger tests in addition to service tests. No test may
pass only because SQLite omits a PostgreSQL authority rule.

## 6. Practice context and stage-evidence lock

Prove:

- context idempotency for the same exact definition and a distinct context for
  a successor publication;
- exact learner-tenant and curriculum-owner pins on every relation;
- a conversation session links to at most one context while retries are
  append-only;
- Learn, Talk, Check and Apply completion each record once per context;
- Review records once per exact schedule and cannot complete before its
  required submitted evidence;
- four accepted learner turns or all authored fallback steps complete Talk;
  AI judgment/wording does not;
- a failed Check does not create Check completion;
- an accessibility Apply fallback completes participation without creating
  recorded/spoken evidence; and
- stage read models rebuild exactly from canonical evidence.

PostgreSQL races must force overlapping context creation, Talk completion,
Check submission, Apply evidence and Review completion.

## 7. Speaking-evidence and privacy lock

Prove all four evidence states only from allowed records. No recording gives
`not_recorded`; an accepted consented recording gives
`recorded_unassessed`; an attributed open human request gives
`awaiting_review`; and an attributed completed rubric gives `reviewed`.

Also prove:

- AI target-use/pronunciation/transcript confidence never changes
  `spoken_state`;
- no spoken-progress claim appears for a fallback-only learner;
- voice consent withdrawal/deletion updates availability honestly without
  rewriting immutable historical evidence references;
- raw audio/transcript text and object keys do not enter LPL telemetry;
- privacy export/erasure inventory includes every new learner record with an
  explicit retain/redact/delete rule; and
- retention/reconciliation failure remains visible and fail closed.

## 8. Tenancy, authorization and abuse lock

Every new read/command receives same-tenant success and cross-tenant/foreign-ID
coverage for learner, enrollment, publication, level, lesson, context,
conversation, attempt, recording, schedule and unlock relationships.

Test:

- inactive/ended membership and enrollment;
- stale or replaced publication/context;
- mass-assignment of organization, actor, owner, rule, score or evidence IDs;
- mixed-tenant relationship insertion at the database level;
- replay with changed fingerprint;
- malformed canonical hashes and expired offline lease;
- prompt injection in authored fields and learner transcript;
- provider output attempting to supply score/mastery/unlock commands; and
- content-free 404-like behavior for guessed foreign identifiers.

## 9. Level-checkpoint and unlock lock

When LPL-04 adds this authority, prove:

- level/lesson counts are read from the exact publication, never fixed at four
  or a named level;
- all required lessons must be checkpoint-ready;
- prerequisite objective/level failure blocks with a code-owned explanation;
- the exact checkpoint must pass under its pinned rule;
- reviews due at checkpoint submission block until complete, while later-due
  reviews do not revoke a prior unlock;
- provider/AI availability and observations are absent from evaluated inputs;
- concurrent/replayed evaluation creates one immutable unlock decision; and
- a client cannot directly grant, edit or delete an unlock.

## 10. Offline and failure-equivalence lock

For the new LPL bundle/outbox, prove:

- public content/media and definition hashes are exact and bounded;
- answer/rationale sentinels are absent;
- the learner can complete Learn, authored Talk fallback, Check responses and
  Apply fallback while disconnected;
- queued Check remains visibly pending until authoritative server scoring;
- reconnect is idempotent and handles expired lease, changed enrollment,
  duplicate commands and publication successor without rewriting the pin;
- legitimate offline work is neither lost nor turned into a false pass;
- AI/provider outage immediately exposes the authored fallback; and
- sensitive conversation audio/transcript is not silently cached/uploaded.

## 11. AI evaluation and cost lock

Prompt/model/provider changes require deterministic fake-provider tests and
`make ai-eval`. The locked suite covers:

- lesson relevance and target elicitation without answer leakage;
- meaning-first response, one priority correction and bounded retry;
- level/locale appropriateness and existing safety schema;
- prompt-injection resistance and unrelated-topic recovery;
- provider timeout/retry/fallback and invalid structured output;
- exact prompt/model/configuration provenance;
- per-session turn/token/time ceilings, content-free usage and cost ledger;
- tenant/global kill switch and aggregate budget stop; and
- invariant that no model output can write authoritative learning state.

Live evaluation is additional evidence only. It requires an exact suite hash,
provider/model/configuration, spend ceiling and stored content-minimized report;
it never replaces deterministic CI or human bilingual review.

## 12. API, client, UX and accessibility lock

For every changed API, validate OpenAPI 3.1.1 and regenerated TypeScript types.
Component and browser tests cover loading, empty, error, retry, stale conflict,
offline, provider fallback and completion states independently.

Critical journeys cover:

- exact learning path → Learn → Skill Chat → Check → Apply → Review;
- no-AI authored fallback;
- offline queue/reconnect;
- failed and passing Check with post-submit rationale;
- speaking evidence labels; and
- level checkpoint/unlock when introduced.

Run keyboard, screen-reader semantics, focus, reduced motion, contrast, axe and
320 CSS-pixel no-horizontal-overflow checks. Audio has transcript and controls;
status is not color only; generated speech can be stopped.

## 13. Required gates by checkpoint

| Checkpoint | Minimum gate |
|---|---|
| LPL-00 | Documentation structure, links, state consistency and `git diff --check`; no runtime claim |
| LPL-01 | Exact inventory/hash/readiness reconciliation, media-analysis provenance and human review evidence; no fabricated gaps |
| LPL-02 | Focused SQLite/PostgreSQL/API/web/AI-eval/E2E plus `make verify` and applicable build |
| LPL-03 | Operator permission/appointment/concurrency/cross-tenant/publication suites, OpenAPI/clients/catalogues, E2E and full gates |
| LPL-04 | Level/unlock races, replay, variable lesson counts, offline equivalence and critical E2E |
| LPL-05 | Per-wave completeness matrix, bilingual review, live/deterministic AI draft evaluation and exact publication evidence |
| LPL-06 | Offline conflict, retention/deletion/export, analytics privacy, budget/kill switch and failure drills |
| LPL-07 | `make verify`, `make e2e`, `make ai-eval`, `make build`, migration/backup-restore, Android/device/UAT and exact release evidence |

Never claim a command/provider call passed unless it ran successfully. Record
environment failures separately from product failures and rerun the exact gate
in a suitable environment before closure.

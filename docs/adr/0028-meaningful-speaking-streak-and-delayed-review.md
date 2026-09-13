---
status: accepted
date: 2026-08-30
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
consulted:
  - Cursor agent as author and evidence recorder, not approver
informed: []
amends_master_plan: true
approval_evidence:
  - APP-0069
approval_reference: Structured decision-form selection accepting DR-0038 A/B/C in the active Cursor conversation
---

# ADR-0028: Meaningful-speaking-day streak and deterministic delayed review

## Status

Accepted on 30 August 2026. Recorded as D-0219 under
[APP-0069](../APPROVALS.md#app-0069). This ADR amends master plan **§7.2 Home**
only. It does not amend §7.1 navigation, does not change §3.3 north-star
definitions, and does not authorize production or Google Play.

## Context

Authenticated Home currently returns at most one `next_mission` carrying a
single MCQ. There is no streak, no delayed review and no "today" surface. This
is the weakest part of the learner product and the main reason day-two return
is unsupported.

The product owner asked for an app better than SuperNova on usage, features and
interface, and proposed a daily attendance check-in with an attendance
percentage gauge. [DR-0038](../DR-0038_PHASE1_DAILY_LOOP_CLIENT_PLATFORM_AND_PROVIDER_REQUEST.md)
recommended against that construct: a self-serve tap-to-check-in percentage
measures application opens, invites gaming, and conflicts with master plan §3.3,
which states that opening a page, playing audio or answering only
multiple-choice questions does not qualify as meaningful practice. Master plan
§§9.2 and 16 also keep attendance separate from canonical learning records.

Master plan §7.2 already requires Home to show today's recommended practice and
progress. §7.3 already requires Progress to show "review due". Neither is
implemented. A durable decision is required because the streak introduces a new
learner-facing engagement construct and delayed review introduces a new
persisted schedule.

## Decision

### 1. Meaningful speaking day

A calendar day counts as a **meaningful speaking day** for a learner when that
day contains at least one conversation turn that has **both**:

- learner speech or a learner-authored utterance submitted as a turn; and
- a recorded system response or task outcome for that turn.

The following do **not** qualify on their own: opening the application, signing
in, playing lesson audio, downloading an offline bundle, submitting only MCQ
check items, or a turn that failed before a response was recorded.

The day boundary is the learner's organization-configured timezone, defaulting
to `Asia/Kolkata`. The resolved timezone is persisted with the day record so the
computation remains auditable if configuration later changes.

### 2. Streak

The streak is the count of consecutive meaningful speaking days ending today or
yesterday. It is a **derived read model** computed from authoritative turn
records. It is never written by AI output and never affects marks, mastery,
entitlements or level unlocks (master plan §0.3).

Constraints:

- No streak freezes, repairs or purchases. Those are monetization and stay
  deferred to Phase 2 / `ADMIN-ENT-01`.
- No shaming presentation on streak loss. §3.2 requires learners not be shamed
  for mistakes; a broken streak is displayed neutrally with the next action.
- The streak is advisory display only and must not gate any content.

### 3. Delayed review

Delayed review is a **deterministic persisted schedule**. When a learner reaches
a mastery event for an objective, review occurrences are scheduled at fixed
intervals from a configuration table.

- Intervals are configuration, not hard-coded constants, and the default set
  requires named academic review before production. Until that review, the
  default is treated as provisional and disclosed as such in operator docs.
- Scheduling is idempotent: replaying the same mastery event must not create
  duplicate occurrences.
- Scheduling and completion happen inside the same transaction boundary as the
  authoritative evidence write.
- AI never selects, reorders, advances or cancels a review occurrence.

### 4. Home composition

Home presents one dominant action, consistent with §7.2:

1. a due review when one exists; otherwise
2. today's recommended level session or mission.

The streak is secondary chrome, not the dominant action.

### 5. Progress is a conformance fix

Progress must stop rendering the Learn level path and must present the §7.3
contract: objectives, speaking activity, review due, recurring errors and recent
improvement. This requires **no** master-plan amendment; the requirement already
exists and the implementation diverged.

## Alternatives considered

### Alternative A — Attendance check-in with a percentage gauge

Rejected. It measures application opens rather than practice, is trivially
gamed by a daily tap, conflicts with §3.3, and would blur the §16 separation
between attendance semantics and learning engagement. Trainer-recorded
attendance remains a separate optional institution module that does not write
canonical learning records.

### Alternative B — Streak counted on any application activity

Rejected. It is the same vanity metric as A with a different label, and it would
reward audio playback and MCQ-only sessions that §3.3 explicitly excludes.

### Alternative C — Adaptive, model-selected review intervals

Rejected for Phase 1. It would place an AI system in the path of what a learner
is required to study next, weakening the §0.3 guarantee that the deterministic
path remains usable and explainable when AI is unavailable.

## Consequences

### Positive

- Day-two return is supported by a metric that is honest about what it measures.
- Delayed review is the strongest available retention mechanism for language
  learning and is already named in the §3.3 north star.
- The deterministic path remains fully usable when AI is unavailable.
- Progress stops duplicating Learn, removing a visible interface defect.

### Negative and trade-offs

- A meaningful-speaking-day streak will read lower than an attendance streak,
  because it is harder to earn. This is intended.
- Delayed review adds a persisted schedule, a migration and new reconciliation
  paths that must be tested for idempotency.
- Provisional review intervals carry pedagogical risk until academic review.

## Security, privacy and tenancy impact

- Streak and review records are organization-scoped and derived from existing
  org-scoped authoritative rows. Cross-tenant read and write tests are required.
- No new personal data category is collected. No raw audio, transcript or L1
  text enters the streak or review records.
- No new provider call and no new spend. This checkpoint does not touch the
  `ai_cost_ledger` or any provider allowlist.
- Review occurrences are covered by existing learner privacy erasure and must be
  added to the privacy inventory revision.

## Authoritative records and consistency impact

- The streak is derived and may be recomputed at any time; it is not an
  authoritative record and must not be relied on for entitlement.
- Review occurrences are authoritative scheduling rows: versioned, idempotent on
  replay, written inside the mastery transaction, and never mutated by AI.
- Offline completions that arrive late must reconcile into the correct
  historical day using the persisted timezone, not the sync time.

## API, client and operational impact

- `GET /api/v1/home` gains streak and due-review fields; OpenAPI and generated
  client types must be updated in the same change.
- Progress requires a read model distinct from the level path.
- Observability: counters for meaningful speaking days, review occurrences
  created, due and completed.

## Master-plan amendment

This ADR amends **§7.2 Home** to add the meaningful-speaking-day streak as
secondary chrome and to state that a due review takes precedence over today's
recommended practice as the dominant action.

§7.1, §7.3, §3.3 and §9.2 are unchanged. The master plan is updated in the same
accepted change and its version is raised to 3.9.

## Approval evidence

Accepted under [APP-0069](../APPROVALS.md#app-0069) on 30 August 2026
(Asia/Kolkata) by `S R Bhandary` exercising Accountable Product Owner and
Technical Decision Owner roles, through a structured decision-form selection.
The Cursor agent authored and recorded this ADR and is not its approver.

Independent security, privacy and tenancy specialist review remains required
before production, as does named academic review of the default review
intervals.

## Verification

```text
make verify
make e2e
```

Required coverage:

- unit tests for the meaningful-speaking-day predicate, including the negative
  cases (MCQ-only, audio-only, failed turn, sign-in only);
- unit tests for streak continuity across timezone boundaries and for late
  offline reconciliation into the correct historical day;
- idempotency test proving replayed mastery events do not duplicate review
  occurrences;
- cross-tenant tests for streak and review reads;
- an assertion that no AI-sourced value can write a streak or review row.

## Follow-up

- Named academic review of the default delayed-review intervals.
- Privacy inventory revision covering review occurrences.
- Progress read-model conformance to §7.3 in the same checkpoint sequence.

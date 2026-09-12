---
status: accepted
date: 2026-08-10
deciders:
  - Codex task accountable product owner as Accountable Product Owner and Technical Decision Owner
  - Codex task accountable product owner as Security Reviewer, Privacy Reviewer and Tenancy Reviewer
consulted:
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: false
approval_evidence:
  - APP-0046
approval_reference: Exact human instruction in the active Codex conversation
---

# ADR-0015: Extend existing learning authority for lesson practice loops

## Status

Accepted on 10 August 2026 under
[APP-0046](../APPROVALS.md#app-0046) / D-0140. This ADR resolves
[DR-0020](../DR-0020_LESSON_PRACTICE_FOUNDATION_REQUEST.md) by accepting
Option A exactly as written. The approved pre-resolution DR has SHA-256
`698d03917a494c29c58d8d34a02f13a09271c8ac192e1bf73117a04cf0b1cab3`.

## Context

The repository already has immutable curriculum publications, deterministic
daily mission attempts/mastery/review, tenant-scoped conversation sessions,
voice consent/evidence and provider-neutral AI ports. It does not have an
exact published lesson-practice component, learner practice context,
lesson-scoped conversation link, five-stage evidence or level-unlock record.

The current daily-speaking reader and policy are intentionally narrow: one
mission, one primary objective, one check and a 0/100 result. Conversation AI
is advisory and has no lesson authority. A new subsystem would duplicate
learning truth; generic metadata would provide insufficient referential and
concurrency guarantees.

## Decision

DR-0020 Option A is accepted exactly as written:

- retain governed content/publication, mission attempts, mastery/review,
  conversation and voice records as the canonical authorities;
- add a typed, versioned lesson-practice definition and immutable publication
  component;
- add an immutable learner practice context, append-only conversation link and
  append-only stage-completion evidence;
- generalize the existing attempt engine under a new deterministic rule version
  while preserving daily-v1 bytes and behavior;
- keep speaking-evidence lifecycle distinct from spoken mastery;
- add transparent append-only unlock decisions only with LPL-04's level
  checkpoint; and
- keep AI/provider output advisory, answer-isolated and outside marks, mastery,
  review schedules and unlock authority.

The detailed schema, scoring, stage, unlock, offline answer-isolation,
live-development and tenancy rules in DR-0020 are normative.

## Alternatives considered

### Separate lesson assessment/progress subsystem

Rejected because it would create conflicting attempts, scores, mastery, review
and progression records and duplicate existing publication/offline/tenancy
logic.

### Generic content JSON and conversation metadata

Rejected because client/session metadata cannot safely become authoritative
stage or unlock evidence and cannot enforce exact publication, tenant,
idempotency and immutable-evidence relationships.

## Consequences

### Positive

- One deterministic learning authority remains explainable end to end.
- New lessons and levels become governed data rather than code branches.
- Existing publication/attempt/offline evidence remains reproducible.
- AI can enrich practice without gaining authority over learner outcomes.
- Later item types and human speaking review can use versioned adapters and
  additive evidence instead of replacing the core.

### Negative and trade-offs

- The new LPL offline path defers authoritative score/rationale until sync to
  avoid shipping a plaintext answer key; daily-v1 retains its documented
  legacy behavior.
- The first `lesson-practice-v1` check supports only reviewed single-choice
  items; richer response shapes require later versioned adapters.
- The five-stage path adds immutable relationship/event records and more
  publication validation.
- Speaking participation can be recorded before spoken mastery exists; the UI
  must preserve that distinction.

## Security, privacy and tenancy impact

All new learner records derive their organization from active membership and
bind exact tenant/publication relationships. Raw lesson media, learner audio,
transcripts, answer keys, rationales, provider payloads, object keys and
personal facts are excluded from ordinary logs, audit, outbox and analytics.
Skill Chat receives only the approved lesson allowlist. Cross-tenant, guessed-
identifier, answer-exfiltration, prompt-injection and provider-failure tests are
mandatory.

Voice evidence remains under ADR-0007 consent, retention, transcript-review
and deletion rules. Repository media remains under exact ADMIN-MEDIA scanning,
accessibility and rights evidence. The live-development allowance does not
waive either boundary.

## Authoritative records and consistency impact

Attempts, responses, submissions, mastery and review remain canonical.
Practice contexts, stage completions and unlock decisions are immutable or
append-only and exact-publication bound. Authoritative multi-write commands
use transactions, deterministic locks and idempotency; PostgreSQL constraints
enforce tenant relationships and append-only state. AI output is never an
authoritative input.

Migration work must preserve all daily-v1 rows and golden payloads. New rule
versions broaden constraints additively and are selected from a code-owned
registry. Prior publications and submitted evidence are never backfilled to
new semantic versions.

## API, client and operational impact

LPL-02 will add the bounded learner practice resources and update OpenAPI and
generated clients. LPL-03 will add the governed operator workflow. Prompt,
model or provider changes require `make ai-eval`; critical learner journeys
require E2E; packaging changes require build evidence. Provider use remains
metered, capped and kill-switch controlled with authored offline fallback.

## Master-plan amendment

This ADR does not amend the master plan. It selects the implementation
refinement already required by the master plan's deterministic authority,
immutable evidence, generic curriculum, offline fallback and provider-neutral
AI boundaries.

## Approval evidence

- Evidence ID: `APP-0046`
- Decision date: 2026-08-10, Asia/Kolkata
- Human approver identifier: `Codex task accountable product owner`
- Roles exercised: Accountable Product Owner; Technical Decision Owner;
  Security Reviewer; Privacy Reviewer; Tenancy Reviewer
- Exact human statement: `Approve DR-0020 Option A as written.`
- Approved DR SHA-256:
  `698d03917a494c29c58d8d34a02f13a09271c8ac192e1bf73117a04cf0b1cab3`
- Master-plan impact: none

Codex is the author and evidence recorder only, never the approver. One human
exercised consolidated development roles; this is not independent specialist
or production review.

## Verification

- Validate the DR, specification and locked verification scope structurally.
- Confirm no runtime, migration, API, generated client, permission, provider
  binding or configuration changes are attributed to LPL-00.
- At LPL-02, run the full locked scope in
  [LPL-00_VERIFICATION_SCOPE.md](../LPL-00_VERIFICATION_SCOPE.md), including
  v1 golden compatibility, answer-isolation, tenancy, PostgreSQL races, AI
  authority and offline reconciliation.

## Follow-up

1. Complete LPL-01's exact governed inventory and reviewed Level 1 blueprint.
2. Implement only one Level 1 vertical slice in LPL-02.
3. Continue LPL-03–LPL-07 in their published order; do not pull LPL-08+ ahead.

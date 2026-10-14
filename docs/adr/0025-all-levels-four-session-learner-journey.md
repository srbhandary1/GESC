---
status: accepted
date: 2026-08-14
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
  - S R Bhandary as consolidated Security, Privacy and Tenancy Reviewer
consulted:
  - Codex / Cursor agent as author and evidence recorder, not approver
informed: []
amends_master_plan: true
approval_evidence:
  - APP-0061
approval_reference: Exact human instruction in the active Cursor conversation
---

# ADR-0025: All-levels four-session learner journey

## Status

Accepted on 14 August 2026 under
[APP-0061](../APPROVALS.md#app-0061) / D-0201. This ADR resolves
[DR-0032](../DR-0032_ALL_LEVELS_FOUR_SESSION_LEARNER_JOURNEY_REQUEST.md)
Option A. The pre-resolution request has SHA-256
`ef26f0bfbabac0c23bebf55df4475957e764272af974b41566d292870465fc79`.

## Context

The product must beat continuous chat-only spoken-English apps by anchoring
every level in governed lesson audio and an explicit progression. Level 1
already has four ordered `lesson_audio` parts named Introduction, Practice,
Conversation and Interaction. The product owner directed that the same
session shape applies to every configured level, with Kannada Tutor
explanation of the Introduction clip, designed activities, and a free-speech
conversation room on level topics.

DR-0020 / ADR-0015 already freeze Learn → Talk → Check → Apply → Review as
the practice-loop authority. This ADR names the learner-facing rooms and
binds AI roles without replacing deterministic scoring or publication gates.

## Decision

1. Every configured level uses exactly four ordered learner sessions:
   **Introduction → Practice → Conversation → Interaction**.
2. Session kinds are data-driven configuration/content. Domain code must not
   hard-code Level 1–17 counts, the word `can`, or Level-1-only session forks.
3. Session roles:
   - **Introduction** — play the exact governed Introduction audio; Structured
     Tutor explains it in Kannada; short speak-heavy activities.
   - **Practice** — Practice audio plus authored rehearsal activities and
     deterministic Check items.
   - **Conversation** — free (unscripted) lesson-scoped Skill Chat on
     level-appropriate topics; meaning-first reply; separate coaching object.
   - **Interaction** — freer Friend or light Role-play Apply speech still
     bounded by level safety/topic policy.
4. Mapping to practice-loop authority:

   | Learner session | Practice-loop stages |
   |---|---|
   | Introduction | Learn (+ short Tutor activities) |
   | Practice | Talk rehearsal + Check |
   | Conversation | Talk / Skill Chat (free turns) |
   | Interaction | Apply; Review remains delayed return |

5. AI remains advisory. Marks, mastery, unlocks and review schedules stay
   deterministic. Raw conversation does not auto-ingest into persistent
   memory.
6. Levels without commissioned media/blueprints appear as
   `commissioning_pending` shells. Level 1 is the first content instance.
7. Private Direct-organization pilot delivery of an attached unpublished
   draft blueprint is authorized for implementation evidence under APP-0061.
   Curriculum publication, production/public release and Google Play remain
   separately gated.
8. This ADR amends master-plan §4 and
   `docs/LESSON_PRACTICE_LOOP_PLAN.md` §2 learner experience naming. It does
   not weaken ADR-0004 content review, ADR-0015 foundation schema,
   ADR-0024 attachment rules or provider/privacy ADRs.

## Consequences

- Operator and learner APIs, UI, OpenAPI and localization must speak the four
  session names.
- LPL-02 attachment and learner session delivery become the immediate
  implementation path for Level 1.
- Later levels reuse the same contract when their media and reviews exist.
- A continuous Supernova-style chat-only primary path is rejected for level
  progression.

## Owner clarification — 25 September 2026, LEVELS-BRIEF-06

The owner explicitly requested Levels 1 and 2 open by default for all
subscriptions, with later levels progressing normally. Master-plan §4.2 now
records this amendment. Configure the starting set independently of plan tier;
version/hash the progression policy and retain deterministic prerequisite gates
from Level 3 onward. Do not manufacture completions or rewrite historical unlock
decisions. Content commissioning, active entitlements and per-plan session
features remain separate requirements. No schema or assessment authority changes.

## Owner clarification — 26 September 2026, LEARN-LEVEL-08

The owner requires Ask AI Tutor to stay appropriate to the lesson in every level,
and reports a passed Practice check that did not unlock Conversation. Master §4.2
now explicitly pins lesson help to the server-resolved authorized blueprint and
records Practice completion in the passing-check transaction. Reconcile older
matching passes with Introduction completed, exact tenant/learner ownership and
content hash; never manufacture marks or rewrite submitted evidence. Speak's
unscoped question help, plan/content gates and later-level prerequisites remain.
No schema, AI assessment authority or retention amendment is introduced.

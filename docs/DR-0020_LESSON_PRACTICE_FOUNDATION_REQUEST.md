# DR-0020 — Lesson Practice Loop foundation request

**Status:** Resolved — Option A accepted under APP-0046 / D-0140

**Needed by:** LPL-00 exit and every LPL-01+ checkpoint

**Authority:** `ENTERPRISE_PRODUCT_MASTER_PLAN.md` §§4.6, 6, 10, 13–18 and
21–27; ADR-0003; ADR-0004; ADR-0006; ADR-0007; ADR-0010; ADR-0011;
ADR-0013; ADR-0014; D-0139; `docs/LESSON_PRACTICE_LOOP_PLAN.md`

**Opened:** 10 August 2026

**Resolved:** 10 August 2026

## Problem

LPL-00 must freeze the foundation before any migration or runtime work. The
current repository has useful authorities to extend, but it does not yet have
the complete Learn → Talk → Check → Apply → Review contract:

- a published daily-speaking mission resolves exactly one mission, one lesson
  objective, one matching learning item and one multiple-choice check;
- `daily-speaking-v1` scores only 0 or 100 from the latest response and stores
  `spoken_state=unassessed`;
- the existing offline v1 bundle includes a plaintext `correct_option_id` so
  it can score locally; changing that payload would break pinned v1 behavior;
- conversation sessions pin persona/scenario versions but have no immutable
  link to a learner's exact lesson, publication, objective or prompt policy;
- current conversation context contains transcript, mode, locale, persona,
  scenario and approved memory, but no lesson target fields;
- graph-v2 publications have levels, lessons, objectives, items, missions,
  exact content revisions and media evidence, but no lesson-practice component;
- `mastery_states` and `review_schedules` are deterministic, but there is no
  lesson-stage evidence or level-unlock decision record; and
- voice records can prove consented recording/transcript activity, but AI
  observations have no authority to turn that activity into spoken mastery.

The missing choices affect foundation schema, immutable learner evidence,
answer security, progression, tenancy and backward compatibility. They must
not be selected implicitly while implementing LPL-02.

## Decision profiles

| Option | Boundary | Trade-off |
|---|---|---|
| **A — Extend the existing authorities with immutable practice context and events (recommended)** | Reuse governed content/publication, mission attempts, mastery/review and conversation/voice records; add a typed practice schema, exact publication component, immutable learner context, conversation link, append-only stage evidence and later transparent unlock decisions | Adds only the missing relationships, preserves one learning authority and gives new lessons a data-driven path without rewriting prior publications or v1 attempts |
| B — Create a separate lesson/quiz/progress subsystem | Add new lesson assessments, attempts, scores, progress and unlock tables beside the existing mission engine | Can be developed in isolation, but creates conflicting scores/mastery/review authorities and duplicated offline, tenancy and publication logic |
| C — Store the loop in content JSON and conversation metadata only | Avoid learner-side schema; infer completion from client state, generic JSON and AI/session metadata | Smallest migration, but weak referential integrity, unsafe progression, poor replay/concurrency behavior and no durable explanation of what was completed |

## Recommended Option A policy

Approval of Option A means all of the following.

### 1. One versioned lesson-practice definition

1. Add the typed governed source schema
   `global-english.lesson-practice-loop.v1`; do not add a raw JSON, prompt or
   markup editor.
2. One v1 practice delivery bundle resolves to exactly one published lesson,
   one primary learning objective and one practice definition. Secondary skill
   concepts may be content metadata, but v1 mastery projects only to the exact
   primary objective. A later schema may add reviewed multi-objective scoring
   without changing v1 meaning.
3. The source definition contains bounded Learn fields; target communication
   functions, patterns, chunks and common-error codes; a 4–8-turn Skill Chat
   policy; a 4–8-step authored Talk fallback; 3–7 Check items; one novel Apply
   task; remediation; and review policy keys.
4. Kannada learning guidance is a source-linked governed variant. Exact
   English and Kannada review remains mandatory under ADR-0004. AI-generated
   text is only a draft until those reviews exist.
5. The publication builder derives owner, curriculum version, level, lesson,
   mission, objective, content/media revisions and review evidence from the
   relational graph. Callers do not duplicate or choose those authority IDs in
   free-form content.
6. An immutable publication practice component pins those identifiers and
   hashes plus the definition, prompt, scoring and review-policy hashes. A
   successor definition requires a successor revision/publication; prior
   learner pins and attempts remain reproducible.
7. Adding or reordering a lesson is graph/content work. Once the governed
   operator workflow exists, no application release, level-count migration or
   condition for `can`, Level 1, four lessons or Levels 1–17 is required.

### 2. Minimal additive learner records

1. Add an immutable organization-owned `lesson_practice_contexts` record that
   binds the learner membership and enrollment to the exact curriculum owner,
   version, publication/manifest, level, lesson, mission, objective, practice
   definition and prompt/scoring policies. It may contain an optional exact
   predecessor daily-v1 attempt for legacy continuation; absence is explicit.
2. Add an append-only `lesson_practice_conversation_sessions` link. One
   conversation session belongs to at most one context; a context may have
   several retries. The link preserves existing immutable conversation rows.
3. Add append-only `lesson_practice_stage_events`. A completion event records
   one exact context, stage, completion path, definition hash, event time and
   the applicable tenant-composite attempt, conversation, voice or review
   reference. Content-free acknowledgements/fallback receipts carry their
   exact authored-step definition hash.
4. Non-review stage completion is unique per context/stage. Review may have
   one completion per exact review schedule. Attempts, conversation turns,
   voice records and human review remain their own canonical evidence; the
   stage event links them and never copies raw audio/transcript content.
5. Add `unlock_decisions` only in LPL-04, when the level-checkpoint semantics
   are implemented. It must be append-only, idempotent and bound to the exact
   enrollment/publication, level, rule version/hash, evaluated evidence set
   and allowlisted reason codes.
6. Do not create another progress or mastery aggregate. Stage and path status
   are read models derived from immutable events, submissions, mastery,
   schedules and unlock decisions.

### 3. Deterministic multi-item scoring

1. Preserve `global-english.daily-speaking-mission.v1`,
   `daily-speaking-v1`, existing publication bytes, existing API results and
   existing offline-v1 bundle behavior byte-for-byte and behavior-for-behavior.
2. Add code-owned `lesson-practice-v1`. Its Check has 3–7 stable item IDs.
   LPL-02 authorizes only `single_choice.v1`: 2–6 stable options, exactly one
   reviewed answer and one reviewed post-submission rationale. New response
   and item types require versioned adapters, not overloaded fields.
3. Every item has an integer `weight_points`; all weights are positive and sum
   to exactly 100. The score is the sum of weights for the latest response to
   each item. There is no rounding or model judgment.
4. Submission requires one valid response for every item. Duplicate item or
   option IDs, missing items, unknown response shapes, invalid weights or
   unresolved answers reject the definition/command fail closed.
5. The v1 pass threshold is 80. A score of 80–100 produces deterministic
   knowledge mastery for the primary objective; 0–79 produces retry. The
   all-items-correct indicator is separate from pass and is true only at 100.
6. The current attempt, response, submission, mastery and review tables remain
   authoritative. Their constraints/columns may be broadened additively for
   bounded item counts, exact aggregate evidence and new rule versions, while
   old rows retain their original interpretation.
7. Each submitted attempt is immutable. First/latest/best evidence is derived
   from submitted attempts; a better later result may advance the current
   mastery projection but never edits the earlier submission.

### 4. Answer and rationale isolation

1. Pre-submission learner payloads, Skill Chat context, provider requests,
   prompts, analytics, audit, outbox, logs, errors and event streams contain no
   correct answer or rationale.
2. Post-submission results may expose only the exact pinned answers and
   rationales for that submitted definition. AI does not generate an
   authoritative answer or rationale at attempt time.
3. New LPL offline bundles contain public item material and a signed
   publication/definition lease, but no plaintext or model-accessible answer
   key. Learner responses are sealed and queued; authoritative scoring and
   rationale arrive after idempotent server reconciliation.
4. The existing daily-v1 plaintext offline answer is a documented legacy
   compatibility exception. It is not copied into the new LPL schema or
   represented as meeting the stronger LPL answer-isolation contract.

### 5. Speaking evidence is separate from mastery

1. The learner-facing speaking-evidence projection has exactly four states:
   `not_recorded`, `recorded_unassessed`, `awaiting_review` and `reviewed`.
2. `not_recorded` means no accepted recording reference exists;
   `recorded_unassessed` means an exact consented recording/participation
   receipt exists without authoritative review; `awaiting_review` requires an
   attributed open human-review request; and `reviewed` requires a completed
   attributed rubric review.
3. These are evidence-lifecycle states, not fluency/mastery labels. The
   existing authoritative `spoken_state` remains `unassessed` until a later
   accepted human-rubric rule can project `developing` or `mastered`.
4. LLM/STT observations, transcript confidence, target-use detection and
   pronunciation signals are advisory only. They cannot create or advance a
   score, mastery state, checkpoint pass or unlock.
5. A microphone/accessibility fallback may complete participation without
   fabricating recorded or spoken evidence. The result must say so plainly.

### 6. Stage completion and level unlock

1. Learn completes when the learner acknowledges the exact published lesson
   definition after the required governed media/transcript path is available.
2. Talk completes through either a qualifying exact-context Skill Chat with at
   least four accepted learner turns or every required step of the authored
   guided-dialogue fallback. AI output quality does not determine completion.
3. Check completes only on a submitted `lesson-practice-v1` attempt scoring at
   least 80. Apply completes through an accepted exact speaking-participation
   receipt or an explicit authored accessibility alternative; only the former
   creates speaking evidence.
4. Review completes against one exact due review schedule and submitted review
   attempt. A scheduled but not-yet-due review is visible, not falsely marked
   complete.
5. A lesson becomes `checkpoint_ready` when Learn, Talk, passing Check and
   Apply are complete. Review remains independently scheduled and visible.
6. `level-unlock-v1` permits a next-level unlock only when every required
   lesson in the learner's exact publication is checkpoint-ready, the exact
   published level checkpoint has passed under its versioned rule, all
   prerequisite levels/objectives are satisfied, and no required review that
   was already due at checkpoint submission remains incomplete.
7. A future review becoming due does not revoke an already issued unlock.
   AI/provider availability, AI observations and optional Friend chat are not
   unlock inputs. Concurrent/replayed evaluation produces one transparent
   immutable decision.

### 7. Lesson-scoped AI boundary

1. Add a project-owned `skill-focus` conversation mode through the existing
   provider ports and structured-output validation. Do not call provider SDKs
   from curriculum, learning or controller code.
2. Its allowlisted context contains only the exact can-do outcome, target
   patterns/chunks, educator-reviewed misconception codes, bounded scenario
   and elicitation goals, turn count, coaching mode, locale, learner-confirmed
   current transcript and separately approved learner-visible memory facts.
3. It excludes answers/rationales, object/storage keys, rights evidence, raw
   media, unrelated learner history and authoritative score/mastery/unlock
   records.
4. A context/session pins prompt key/version/body hash, model/configuration
   identity and practice definition hash. Provider usage/cost is content-free
   and remains in the existing cost controls.
5. AI outage or budget kill-switch selects the authored Talk fallback and
   cannot block deterministic learning or reduce an authoritative result.

### 8. Live AI and repository-media allowance

1. The product owner's active instruction authorizes non-production use of
   live AI and repository-held media for bounded LPL analysis, drafting,
   evaluation and development.
2. The allowance does not supply a secret, provider contract, production
   deployment, public release, media-rights confirmation, academic/Kannada
   review or automatic publication.
3. Each live run must bind exact input hashes, purpose, adapter/model/prompt
   versions, safe status, output hash and a configured per-run and aggregate
   spend ceiling. A missing ceiling or kill switch fails closed; the blanket
   instruction is not an unlimited monetary authorization.
4. Only exact eligible governed lesson media may leave the application for a
   reviewed provider purpose. Raw media/transcripts, secrets and personal
   learner data stay out of ordinary logs, analytics and repository commits.
5. Live output remains a draft. Deterministic validation and attributed human
   English/Kannada/media review are still required before publication.

### 9. Security, tenancy and consistency

1. Every new learner record is owned by the active learner organization and
   binds the exact curriculum-owner publication through tenant-composite
   relationships. Requests never select an organization.
2. Guessed, stale or cross-tenant enrollment, publication, lesson, context,
   session, attempt, recording, schedule and unlock IDs remain
   indistinguishable from not found.
3. Authoritative multi-write commands use transactions, deterministic locks,
   post-lock authorization, header idempotency and content-free audit/outbox
   evidence. PostgreSQL constraints/triggers enforce append-only/transition
   rules in addition to application checks.
4. Published definitions, stage completion, attempt submissions and unlock
   decisions are immutable. Corrections use successor definitions, new
   attempts or attributed review events.
5. API changes update OpenAPI 3.1.1 and generated clients in the same bounded
   checkpoint. Every tenant route receives same-tenant/cross-tenant tests.

## Required implementation consequence after approval

Approval activates [LPL-00_SPEC.md](LPL-00_SPEC.md) and proposed
[ADR-0015](adr/0015-lesson-practice-foundation.md). It authorizes only the
ordered non-production LPL checkpoints, one at a time. LPL-01 remains an exact
inventory/content task; the first runtime migration is LPL-02.

It does not authorize production/public release, unbounded provider spend,
reference/legacy import, fabricated missing lessons, automatic AI publication,
AI-awarded marks/mastery/unlocks, or LPL-08+ evolution work.

## Exact approval requested

To accept the recommended foundation, reply exactly:

```text
Approve DR-0020 Option A as written.
```

Any changed wording will be treated as a new instruction to evaluate, not as
silent approval.

## Resolution

Option A was accepted as written on 10 August 2026 under
[APP-0046](APPROVALS.md#app-0046), [D-0140](DECISIONS.md) and
[ADR-0015](adr/0015-lesson-practice-foundation.md). The exact approved
proposal, before its status, resolved date and this resolution section were
added, has SHA-256
`698d03917a494c29c58d8d34a02f13a09271c8ac192e1bf73117a04cf0b1cab3`.

[LPL-00_SPEC.md](LPL-00_SPEC.md) is active and
[LPL-00_VERIFICATION_SCOPE.md](LPL-00_VERIFICATION_SCOPE.md) is locked. LPL-00
is complete with no runtime implementation. LPL-01 may now perform only the
exact governed lesson/media/objective inventory, eligible Level 1 media
analysis, truthful blueprint drafting and required human review described by
the ordered plan.

This resolution does not accept future implementation results, authorize
production/public release or unlimited provider spend, waive exact media or
content review, permit fabricated missing lessons, or give AI authority over
publication, scores, mastery, review schedules or unlocks.

# LPL-00 — Lesson Practice Loop foundation specification

**Status:** Active and accepted under APP-0046 / D-0140 / ADR-0015

**Prepared:** 10 August 2026

**Checkpoint:** LPL-00 — prerequisites and policy freeze

**Authority:** `ENTERPRISE_PRODUCT_MASTER_PLAN.md` version 3.2;
`docs/LESSON_PRACTICE_LOOP_PLAN.md` version 2.0; accepted DR-0020 and
ADR-0015; accepted ADR-0003, ADR-0004, ADR-0006, ADR-0007, ADR-0010,
ADR-0011, ADR-0013 and ADR-0014

## 1. Purpose and activation

This specification translates accepted DR-0020 Option A into the bounded
contracts that LPL-01 and LPL-02 must follow. It is active under APP-0046 and
creates no authority outside the ordered non-production checkpoints.

LPL-00 closes only when:

1. the retained ADMIN-MEDIA PostgreSQL and release gates are recorded as
   passed;
2. DR-0020 Option A and proposed ADR-0015 are accepted with durable human
   evidence;
3. this specification is marked active without changing the approved policy;
4. the locked verification scope is accepted; and
5. no runtime code, migration, API or provider call is represented as LPL-00
   implementation.

All five LPL-00 conditions are satisfied. The next checkpoint is LPL-01 exact
inventory and content work, not migration work.

## 2. Inspected baseline

The implementation inventory establishes these current facts:

| Area | Existing authority | Gap addressed later |
|---|---|---|
| Curriculum | Graph-v2 levels/modules/lessons/objectives/items/missions and immutable publication manifests | No typed lesson-practice schema/component |
| Content | Source/Kannada revisions, academic/Kannada review, exact media/rights/scanning publication evidence | Writable adapters support governed text and one-check daily mission only |
| Learning | Enrollment/publication pins, append-only attempts/responses/submissions, mastery and review schedules | One check, 0/100 policy, no five-stage evidence or unlock decision |
| Offline | Signed exact-publication mission lease and idempotent reconciliation | Daily-v1 includes plaintext answer; no LPL answer-isolated bundle |
| Conversation | Versioned persona/scenario/prompt, immutable sessions/turn events, provider ports, correction and cost ledger | No exact lesson-practice context or `skill-focus` mode |
| Voice | Consent, private recording, STT, learner transcript review, retention and deletion receipts | No Apply-stage/human rubric link; AI speech output is advisory |
| Progression | Objective mastery and review projection | No level checkpoint or `unlock_decisions` table |

No code may assume the reported four Level 1 audio files exist as governed
assets. LPL-01 must resolve exact repository/storage records and hashes first.

## 3. Frozen v1 content contract

### 3.1 Definition placement

`global-english.lesson-practice-loop.v1` is a governed learner-visible,
Kannada-required instruction document selected as the exact instruction for a
practice delivery bundle. In v1, the bundle's item graph must resolve to one
lesson and one primary objective. Publication derives all relational IDs and
pins them into a practice component.

The English source is a strict typed object with these groups:

- `schema`, `goal_key`, `estimated_minutes`, `concept_key` and `title`;
- `can_do`, `explanation`, exact target function/pattern/chunk lists and
  educator-reviewed common-error codes;
- `learn`, containing model text/transcript usage and bounded chunk/rehearsal
  instructions while media identity remains relational;
- `talk`, containing prompt-policy key, opening, elicitation goals, safe
  scenarios, a 4–8 learner-turn budget and 4–8 authored fallback steps;
- `check`, containing `lesson-practice-v1`, pass threshold 80 and 3–7 items;
- `apply`, containing one novel task, bounded instructions and accepted
  participation/fallback paths; and
- `review`, containing code-owned remediation and schedule-policy keys.

The Kannada variant contains the exact schema and the Kannada fields defined
by the eventual typed adapter. It cannot change item IDs, answer keys, weights,
rule keys or other deterministic authority.

Every text/key/list/depth/payload limit is code owned. HTML, URLs, executable
content, control characters, duplicate IDs and unknown fields fail closed.

### 3.2 Check item v1

Each v1 item is exactly:

```text
id
type = single_choice.v1
prompt
options[2..6] = {id, label}
correct_option_id
rationale
weight_points
```

There are 3–7 items; item IDs and option IDs are unique in their scopes;
weights are positive integers summing to 100; every answer resolves to one
option. Prompt/options are pre-submission learner content. Answer/rationale are
authoritative hidden content until submission.

Other activity types from the platform plan require their own versioned item
and response adapters. They must not be encoded into `selected_option_id` or a
caller-defined generic payload.

### 3.3 Publication practice component

The immutable component must pin at least:

- curriculum owner organization, version, publication and manifest hash;
- level, lesson, mission/delivery bundle and primary objective IDs;
- practice document, source revision/hash and Kannada variant/hash;
- exact required media component IDs/hashes and applicable review evidence;
- canonical practice definition hash;
- prompt policy key/version/body hash;
- scoring and review policy keys/versions/hashes; and
- component creation time in deterministic manifest order.

Prior publications are never backfilled. A source, translation, media,
answer/rationale, prompt, scoring or review-policy change requires a new exact
component in a successor publication.

## 4. Frozen learner evidence model

### 4.1 Practice context

One immutable context pins:

- learner organization, membership and enrollment;
- curriculum owner, version, publication and manifest hash;
- level, lesson, mission and primary objective;
- practice component/definition and prompt/scoring policy hashes;
- optional predecessor daily-v1 attempt ID for exact legacy continuation; and
- creation source and time without raw transcript, media or answer content.

Context creation is idempotent for the same learner/enrollment/practice
definition. A successor publication creates a new context; it never rewrites a
prior context.

### 4.2 Conversation link

The append-only link binds one exact conversation session to one practice
context and records its retry ordinal. The context may have several sessions;
a session may not change context. Existing session persona/scenario/prompt
pins stay intact.

### 4.3 Stage completion event

The append-only event contains context, learner membership, stage,
completion path, exact definition hash, sequence and time plus one applicable
tenant-composite evidence reference:

| Stage | Allowed completion evidence |
|---|---|
| Learn | Exact-definition acknowledgement after available governed lesson path |
| Talk | Qualifying `skill-focus` conversation or all required guided-dialogue fallback steps |
| Check | Exact submitted passing attempt |
| Apply | Accepted voice participation or explicit authored accessibility alternative |
| Review | Exact review schedule and submitted review attempt |

No event stores raw audio, transcript, prompt, answer, rationale or personal
fact. Non-review completion is unique for context/stage. Review completion is
unique for context/schedule. Failed/retried attempts remain visible in their
canonical tables without creating a false completion.

## 5. Frozen scoring and projection rules

`daily-speaking-v1` is unchanged. `lesson-practice-v1` works as follows:

1. an attempt pins the exact publication, practice definition and rule;
2. append-only responses may retry an item while the attempt is open;
3. submission requires a latest valid response for all 3–7 items;
4. score is the exact sum of weights for correct latest responses;
5. 80–100 passes and projects knowledge mastery; 0–79 schedules remediation;
6. 100 alone is all-items-correct;
7. the immutable submission records item count, correct count, score, result,
   rule version and aggregate definition hash; and
8. best/current mastery may advance from later evidence but no prior attempt
   or submission changes.

The first LPL migration may add or broaden only fields/constraints needed for
those semantics. It must not reinterpret old rows or drop database-level
append-only/transition enforcement.

## 6. Frozen speaking-evidence projection

Speaking evidence and spoken mastery are separate:

| Evidence state | Exact meaning |
|---|---|
| `not_recorded` | No accepted exact recording reference |
| `recorded_unassessed` | Accepted consented recording/participation exists; no authoritative review |
| `awaiting_review` | An attributed human rubric-review request is open |
| `reviewed` | An attributed human rubric result is complete |

LPL-02 need implement only states justified by its evidence. It may not invent
an open or completed human review. `spoken_state` remains `unassessed` until a
separately accepted deterministic human-review projector exists. AI/STT/TTS
output cannot transition this authority.

## 7. Frozen stage and unlock rules

- Learn: exact-definition acknowledgement.
- Talk: at least four accepted learner turns in exact `skill-focus` context, or
  all authored fallback steps.
- Check: submitted score of at least 80.
- Apply: exact participation or authored accessibility fallback; only actual
  voice evidence supports a spoken-progress claim.
- Review: exact scheduled review attempt submitted when due.
- Lesson `checkpoint_ready`: Learn + Talk + passing Check + Apply.

LPL-04 adds the first level-checkpoint and `level-unlock-v1`. It unlocks the
next level only after all required published lessons are checkpoint-ready,
the exact published level checkpoint passes, prerequisites pass, and no
required review already due at checkpoint submission remains incomplete. A
later-due review does not revoke an issued unlock. The decision stores its
exact evaluated evidence and allowlisted explanation; there is no client
“grant unlock” command.

## 8. Frozen Skill Chat and provider boundary

The new mode is `skill-focus`, implemented through existing project-owned
conversation/speech interfaces. It pins the practice context and a dedicated
prompt policy. The model allowlist contains only can-do, target
patterns/chunks, misconception codes, bounded scenarios/goals, turn count,
mode/locale, current learner-confirmed transcript and approved visible memory.

It excludes answers, rationales, rights/scan details, object keys, raw lesson
media, unrelated history and authoritative learner outcomes. Structured
target-use observations and retry suggestions remain advisory. Four accepted
learner turns—not an AI judgment—meet the v1 Talk participation threshold.

Provider failure, disabled configuration, budget exhaustion or no consent
selects the authored fallback. It does not fail or lower deterministic
learning evidence.

## 9. Offline and answer-isolation boundary

The LPL practice response exposes no answer/rationale before submission. A new
offline bundle pins public definition, media metadata, prompt/fallback material
and lease but omits plaintext answers/rationales. Offline responses are
locally sealed and queued. Server reconciliation verifies the lease and exact
publication, records the same append-only responses and returns the
authoritative result/rationale after sync.

The current daily-v1 bundle remains a byte-compatible legacy exception. LPL
tests must prove no new route, model context, log or bundle copies that answer
key.

## 10. Dynamic lesson/media lifecycle

LPL-01 inventories exact governed assets. LPL-03 exposes typed operator forms.
LPL-05 may run AI-assisted drafting. In every case:

1. exact bytes/hash, scan, metadata, rights and transcript are resolved;
2. analysis records exact input/config/model/output hashes and safe status;
3. AI proposes structured fields and items only;
4. deterministic validators reject malformed/ambiguous output;
5. English/Kannada humans review and correct exact revisions; and
6. ordinary immutable publication selects the result.

Adding more lessons repeats this data workflow. Missing/unreviewed media,
transcript, objective, answer/rationale, accessibility or rights remains a
visible blocker; no filename/topic assumption or fabricated lesson fills it.

## 11. Live-development allowance

The product owner's instruction permits bounded non-production live-AI and
repository-media use. Every run still requires exact eligible input, project
adapter, external secret, configured per-run and aggregate ceiling, kill
switch, content-free usage record and no learner-personal data unless an
existing consent/purpose permits it. Missing controls fail closed. Output is a
draft and cannot publish or affect learner authority.

This specification sets no unlimited budget and authorizes no production,
public release, provider contract, media-rights approval or independent-review
claim.

## 12. Ordered implementation boundaries

### LPL-01

Read exact database/storage/repository inventory; analyze only eligible exact
Level 1 media; draft truthful blueprints and a completeness matrix; obtain
required human review. No learner/runtime migration.

### LPL-02

Implement one reviewed Level 1 `can` loop only if exact content supports that
topic: minimal additive migration, typed reader, multi-item attempt policy,
practice context/stage evidence, `skill-focus`, learner UI, fallback/offline
queue, OpenAPI/clients/catalogues and locked evidence.

LPL-03–LPL-07 remain ordered by the main plan. LPL-08+ is not authorized by
this specification.

## 13. Exit evidence

Before LPL-00 can be marked complete:

- DR-0020 Option A and ADR-0015 are accepted with an APP record and proposal
  hash;
- this document is marked active;
- `docs/LESSON_PRACTICE_LOOP_PLAN.md`, `docs/STATUS.md`,
  `docs/DECISIONS.md` and `docs/QUALITY_REVIEW.md` agree on checkpoint state;
- `docs/LPL-00_VERIFICATION_SCOPE.md` is locked; and
- documentation structure/link/diff checks pass with no runtime change
  attributed to LPL-00.

Production/public release and every retained independent review remain
separate gates.

## 14. Acceptance record

DR-0020 Option A and ADR-0015 were accepted on 10 August 2026 under APP-0046 /
D-0140. The exact pre-acceptance specification has SHA-256
`5bda28ff6be15bee37c32d00fb701ce776108a750db7a4ee8dccddecadd53f6e`.
The verification scope accepted with it has SHA-256
`a7bf02f70dc5bb602c3f2b4341718bd04d1a218c829c82a3db9998b70d1b9dd4`.

LPL-00 is complete without a product migration, API, UI, provider call or
runtime behavior change. LPL-01 is the only next checkpoint.

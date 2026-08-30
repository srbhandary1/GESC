# DR-0032 — All-levels four-session learner journey

**Status:** Resolved — Option A accepted under APP-0061 / D-0201 / ADR-0025

**Needed by:** LPL-01 blueprint shape, LPL-02+ practice delivery UX, and every
configured level after Level 1

**Authority:** `ENTERPRISE_PRODUCT_MASTER_PLAN.md` §§1, 4.1–4.4, 6 and 26;
ADR-0004; ADR-0006; ADR-0007; ADR-0011; DR-0020 / APP-0046 / D-0140;
`docs/LESSON_PRACTICE_LOOP_PLAN.md`; Level 1 four-part media inventory under
D-0191

**Opened:** 14 August 2026

**Resolved:** 14 August 2026

**Pre-resolution SHA-256:**
`ef26f0bfbabac0c23bebf55df4475957e764272af974b41566d292870465fc79`

## 1. Decision needed

The product owner directed that every level — not only Level 1 — follows the
same learner journey:

1. **Introduction session**
2. **Practice session**
3. **Conversation session**
4. **Interaction session**

Under Introduction, the governed level audio is explained to the learner in
Kannada by the AI Tutor, followed by designed activities. A conversation room
then lets the learner speak freely with the AI on topics for that level —
free speech, not a fixed script.

The repository already has:

- Level 1 four ordered audio parts named Introduction, Practice, Conversation
  and Interaction with human-reconciled English metadata;
- master-plan Tutor / Friend / Role-play modes;
- a data-driven Learn → Talk → Check → Apply → Review practice-loop plan that
  must not hard-code Level 1–17.

Those pieces are compatible but not yet locked as one universal per-level
session contract. Without an explicit decision, later levels could diverge into
ad-hoc rooms, endless chat, or Level-1-only special cases.

This decision does **not** authorize production release, Google Play public
distribution, unreviewed Kannada publication, AI-awarded mastery, automatic
memory ingest from chat, or skipping English/Kannada specialist review.

## 2. Recommended Option A — Universal four-session level contract

Approve Option A if every configured level must use the same data-driven
session shape below. Level topic, media, activities and prompts change by
content; the session kinds and AI roles do not.

### 2.1 Per-level session contract

| Session | Learner promise | Primary media / content | AI role | Authoritative result |
|---|---|---|---|---|
| **Introduction** | Understand the level idea from the exact governed audio, with Kannada explanation | Exact Introduction audio (+ reviewed transcript/guidance when published) | Structured **Tutor**: explain the clip in Kannada; demonstrate pattern; short comprehension/speak activities | Participation + comprehension/activity evidence; no AI mastery award |
| **Practice** | Rehearse the target patterns with short speak-heavy activities | Exact Practice audio + authored practice activities | Structured **Tutor**: model, repeat, transform, one priority correction, Kannada explanation, retry | Deterministic activity/check evidence where authored; AI coaching advisory |
| **Conversation** | Use the level skill in natural talk on level-appropriate topics | Level topic/objective bounds; Conversation audio may seed topics | Lesson-scoped **Skill Chat** that allows free learner turns (not a fixed script). Meaning-first replies; ≤1 priority correction; coaching separable from the reply | Speaking participation evidence; AI feedback advisory |
| **Interaction** | Transfer into freer or situational use | Interaction audio / scenario bounds | **Friend** or light **Role-play**: freer speech still bounded by level safety/topic policy | Speaking participation / apply evidence; AI feedback advisory |

Rules that apply to every level:

1. **One schema for all levels.** Domain code must not special-case Level 1
   names, counts or the word `can`. Session kinds are configuration/content.
2. **Governed audio is the Introduction source of truth.** The Tutor explains
   and coaches from the exact published clip/transcript; it does not replace
   the clip with an unreviewed generated lecture.
3. **Activities are authored or derived from reviewed blueprints**, then
   published. Live AI may draft, not publish or score authoritatively.
4. **Conversation and Interaction are free speech inside level bounds**, not
   scripted line-by-line dialogue. Optional hints exist; the learner is not
   forced through a fixed utterance list.
5. **Reply ≠ coaching.** Natural reply first; correction/Kannada explanation
   as a separate learning-feedback object per master plan §4.3.
6. **Marks, mastery, unlocks and review schedules remain deterministic.** AI
   never mutates authoritative learning records.
7. **Memory stays explicit and learner-visible.** Raw conversation does not
   auto-become persistent memory.
8. **Offline / AI-down resilience.** Deterministic Introduction listen path,
   Practice activities and any authored Check remain usable without live AI;
   free Conversation/Interaction fall back honestly when AI is unavailable.
9. **Same unlock grammar everywhere.** A level exposes the four sessions in
   order. Later sessions may soft-unlock for browsing, but completion credit
   and next-best-action follow deterministic prerequisites from the published
   practice definition.
10. **L1 guidance default is Kannada** for Tutor explanation; English model
    speech remains English. Other UI locales stay separately governed.

### 2.2 Mapping to the existing Learn → Talk → Check → Apply → Review plan

Option A does **not** discard DR-0020. It names the learner-facing rooms and
maps them onto the practice-loop stages:

| Four sessions (learner UX) | Practice-loop stages (authority) |
|---|---|
| Introduction | Learn (+ short Tutor speak activities) |
| Practice | Talk rehearsal activities + Check items as authored |
| Conversation | Talk / Skill Chat (free turns, lesson-scoped) |
| Interaction | Apply (+ optional light role-play); Review remains the delayed return path |

Review remains a scheduled return, not a fifth always-visible first-run room.

### 2.3 Concrete Level 1 example (template for every later level)

For Level 1 (`can`), the same template applies; only content changes for
Levels 2–17.

**Introduction**

1. Play the exact Introduction MP3.
2. Tutor explains in Kannada what `can` means and when to use it, grounded in
   the clip.
3. Activities: meaning check; notice 3–5 chunks; shadow/repeat; build a few
   `I can…` / `I cannot…` sentences; one short spoken answer.

**Practice**

1. Play/use the Practice audio as the rehearsal model.
2. Activities: substitution, transform, mini Q&A, priority-error retry.
3. Optional short authored Check items with rationale after submit.

**Conversation**

1. Open the conversation room for Level 1 topics (abilities, offers, requests).
2. Learner speaks or types freely; AI replies naturally and keeps the skill in
   play without a script.
3. Coaching card: one priority correction + short Kannada explanation + retry.

**Interaction**

1. Freer or situational use (for example asking a colleague for help).
2. Friend/role-play bounded to level policy.
3. Result screen separates knowledge, speaking evidence and AI coaching.

### 2.4 What “better than Supernova” means under Option A

| Supernova-style risk | This product rule |
|---|---|
| Endless AI chat as the whole product | Four explicit sessions per level with a next step |
| AI lecture replaces curriculum | Exact governed audio + Kannada Tutor explanation |
| Scripted-only practice | Free Conversation/Interaction after Introduction/Practice |
| Opaque progress | Deterministic evidence, unlocks and review |
| Hidden memory | Learner-visible, opt-in memory only |

## 3. Alternatives

| Option | Boundary | Trade-off |
|---|---|---|
| **A — Universal four-session contract (recommended)** | Every level uses Introduction → Practice → Conversation → Interaction with the AI roles above | One clear product; content scales by blueprint; needs consistent media/activity authorship per level |
| B — Level 1 only; invent later-level shapes later | Ship Level 1 as special case | Faster near-term demo; creates rewrite risk and inconsistent learner mental model |
| C — Chat-first continuous Tutor like Supernova; four audios are optional extras | Maximize free talk immediately | Weak curriculum anchoring; weaker deterministic progression; drifts from owned lesson media |

## 4. Acceptance effects

Accepting Option A means:

1. LPL-01 blueprints and later level blueprints must express the four sessions
   as first-class ordered parts of one level/lesson practice definition.
2. Product copy, operator guides and learner UX use these four session names.
3. Implementation checkpoints may build Level 1 first as the vertical slice,
   then reuse the same contract for Levels 2–17 without schema forks.
4. A successor ADR updates the master plan / lesson-practice plan only if
   acceptance explicitly amends naming or stage mapping.
5. No level may ship Conversation/Interaction free speech as authoritative
   mastery without the deterministic evidence rules above.

Rejecting Option A in favor of B or C requires an explicit alternate product
rule before LPL-02 learner UX hardens.

## 5. Out of scope until separately approved

- Production or public Play release
- Live ordinary-learner STT/TTS provider spend beyond existing authorizations
- Auto-generating or publishing Kannada guidance without specialist review
- Changing marks/mastery to AI authority
- Replacing Confidence Conversation / dashboard AI Teacher product codes
  without a separate product decision
- Ingesting all 17 levels’ media without per-level rights and review gates

## 6. Requested decision

Please accept **Option A**, reject it for **B** or **C**, or return a bounded
amendment. Until accepted, LPL-01 may continue Level 1 content work, but the
all-levels four-session UX must not be treated as frozen product law.

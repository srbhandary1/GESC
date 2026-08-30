# Lesson-linked AI learning platform plan

**Status:** Active ordered implementation plan; LPL-00 complete; LPL-01
inventory, governed four-asset ingestion, private pilot and exact runtime
rights complete; transcript commissioning and reviewed blueprint are next

**Prepared:** 10 August 2026

**Plan version:** 2.0 — future-proof product and engineering roadmap

**Authority:** `ENTERPRISE_PRODUCT_MASTER_PLAN.md` §§4, 6–7, 14–18 and
21–27; ADR-0003; ADR-0004; ADR-0006; ADR-0007; ADR-0010; ADR-0011;
ADR-0013; ADR-0015; ADMIN-MEDIA-01; the existing deterministic learning and
Confidence Conversation implementations.

**Product-owner direction:** The active conversation authorizes use of live AI
and repository-held media for this non-production programme. That allowance
does not expose credentials, bypass tenant/privacy controls, make AI
authoritative, auto-approve content or media, silently exceed a recorded
provider-spend ceiling, or authorize production/public release.

## 0. How to read this plan

This document has two completion horizons:

1. **Core completion (LPL-00–LPL-07):** every enabled lesson and level has a
   reviewed Learn → Talk → Check → Apply → Review loop, deterministic
   progression, offline fallback and production-quality evidence.
2. **Platform evolution (LPL-08+):** adaptive learning, richer speech coaching,
   trainer workflows, enterprise integrations, additional languages and other
   separately approved capabilities. These are designed now so the core does
   not need replacement, but they do not delay a useful launch.

Ideas in the evolution horizon are options, not silent scope commitments.
Each still requires its own bounded checkpoint, evidence and any material
privacy, tenancy, content, commercial or foundation-schema decision.

### 0.1 Future-proof invariants

The following rules are permanent unless an accepted ADR explicitly amends
them:

1. **Data-driven:** levels, lesson counts, activity types, languages, media and
   scoring policies are configuration/content—not conditionals in domain code.
2. **Versioned:** media, transcripts, topics, objectives, prompts, rubrics,
   answer keys, mastery rules and AI evaluations are immutable/versioned.
3. **Explainable authority:** marks, mastery, review schedules and unlocks come
   only from deterministic versioned rules or attributed human review.
4. **AI-assisted, not AI-authoritative:** AI may analyze, draft, converse,
   recommend and explain; it may not publish, approve rights or mutate an
   authoritative learner result.
5. **Provider-neutral:** STT, LLM, TTS, translation, embeddings and content
   analysis remain behind project-owned interfaces and portable contracts.
6. **Exact provenance:** every generated draft and learner result traces to
   exact media/content hashes, model/prompt versions, reviewers and rule
   versions.
7. **Offline-resilient:** deterministic learning remains useful without AI,
   continuous connectivity or high bandwidth.
8. **Tenant-safe by construction:** every organization-owned resource is
   scoped, authorized and cross-tenant tested.
9. **Privacy-minimized:** raw audio, transcripts and personal context are
   purpose-bound, consent-aware, retention-controlled and absent from ordinary
   telemetry.
10. **Accessible and multilingual:** keyboard, screen-reader, reflow,
    transcripts, captions and reviewed language variants are first-class
    publication requirements.
11. **Backward-compatible:** new lesson/activity schemas use versioned adapters;
    existing publications, attempts and offline bundles remain reproducible.
12. **Observable and affordable:** every provider path has latency, quality,
    fallback, usage, budget and kill-switch evidence without content leakage.

### 0.2 Scope tiers

| Tier | Meaning | Release effect |
|---|---|---|
| Core | Necessary for complete all-level lesson loops | Blocks core completion |
| Growth | High-value feature after core learner evidence | Does not block core launch |
| Optional | Pilot/customer-dependent capability | Requires separate business/policy gate |
| Prohibited | Conflicts with accepted safety/authority rules | Must not be implemented |

## 1. Outcome

Every published lesson becomes a complete learning loop:

```text
Learn → Talk → Check → Apply → Review
```

For a Level 1 lesson about `can`, the learner will:

1. listen to the exact governed lesson audio and follow its transcript;
2. notice and rehearse the target patterns;
3. have a short AI conversation that naturally elicits those patterns;
4. complete an authored deterministic quiz;
5. attempt a new speaking situation;
6. see separate knowledge, speaking-evidence and review states; and
7. return for a delayed review.

The same data-driven contract must work for every configured level. No domain
code may hard-code Level 1–17, four lessons, the word `can`, or a fixed grammar
sequence.

## 2. Learner experience

### 2.1 Level page

The level page is a calm visual path rather than a room grid. It contains:

- a concise level promise and can-do outcomes;
- one card per published lesson, each with an exact progress state;
- a level checkpoint card shown only when its deterministic prerequisites are
  met; and
- one next-best action, not several competing primary buttons.

Each level shows four learner sessions (ADR-0025):

| Session | Learner promise | Authority |
|---|---|---|
| Introduction | Hear the governed audio, receive Kannada Tutor explanation and complete short activities | Published/attached content and governed media; AI advisory |
| Practice | Rehearse with Practice audio and authored activities; answer Check items | Deterministic Check scoring; AI coaching advisory |
| Conversation | Free-speech Skill Chat on level topics (not a fixed script) | Advisory AI only |
| Interaction | Freer Apply speaking / light role-play | Evidence captured; AI feedback advisory |

Delayed **Review** remains a deterministic return path, not a fifth first-run room. These sessions map onto Learn → Talk → Check → Apply → Review without changing scoring authority.

AI unavailability never makes a downloaded deterministic lesson unusable or
blocks progression. Conversation/Interaction fall back honestly when AI is
unavailable, and the Check stage remains available offline.

### 2.2 Skill Chat

Skill Chat is a lesson-scoped Tutor conversation, distinct from free Friend
chat. It uses only the exact published practice context for the learner's
pinned curriculum release.

For `can`, a sample sequence is:

1. “Tell me two things you can do.”
2. “What is one thing you cannot do yet?”
3. “Ask me what I can do.”
4. “Imagine you are at work. Ask a colleague for help.”
5. Retry one priority sentence if useful.

The model responds to meaning first, asks a relevant follow-up and gives no
more than one priority correction per turn. Tutor mode may require one retry.
The learner can speak, edit the transcript, type, request a hint, replay the AI
reply or leave without losing lesson progress.

The AI receives target patterns, safe examples, scenario boundaries, learner
locale and approved non-sensitive memory. It never receives quiz answers,
object keys, rights evidence, unrelated learner history or raw media bytes.

### 2.3 Check and Apply

The quick Check is formative and uses authored items only. Recommended item
mix:

- choose the correct sentence;
- choose the meaning/function;
- arrange a short sentence;
- select an appropriate reply; and
- identify or repair one common error.

Correct answers and rationale are never sent before submission and are never
generated live for an authoritative attempt.

Apply is a novel situation. For `can`, the learner might explain what they can
do for an event, say what they cannot do, and ask another person for help.
The system records speaking participation and exact evidence. Until automated
speech scoring is independently validated, AI feedback is advisory and the
authoritative spoken state is `unassessed` or `awaiting_review`, never
AI-awarded mastery.

### 2.4 Results

The result screen separates:

- **Knowledge:** deterministic score and item-level rationale;
- **Speaking evidence:** recorded / not recorded / awaiting review / reviewed;
- **AI coaching:** what was understood, one improvement and a retry;
- **Can-do progress:** developing or mastered under a versioned rule; and
- **Next action:** retry, continue, or review on the scheduled date.

Completion celebrates useful action (“You used `can` in four turns”) without
public ranking, streak pressure or invented fluency claims.

## 3. Governed lesson-practice blueprint

Each lesson needs one immutable, reviewed blueprint bound to its exact
curriculum objective and publication. The first implementation should add a
typed content schema such as `global-english.lesson-practice-loop.v1`, not a
free-form prompt or raw JSON editor.

Required fields:

- exact owner, curriculum version, level, lesson/mission and objective IDs;
- can-do outcome and prerequisite objective IDs;
- exact governed audio/media component and transcript revision;
- concept key, plain-English title and Kannada explanation;
- target communication functions, patterns and useful chunks;
- bounded examples and educator-reviewed common-error codes;
- Skill Chat opening, elicitation goals, safe scenario choices and 4–8 turn
  budget;
- 3–7 formative items with stable IDs, options, correct answers and rationale;
- one novel Apply task and human-review rubric where applicable;
- deterministic completion/mastery policy version;
- remediation and delayed-review rules; and
- English/Kannada review evidence and publication hashes.

Changing any authoritative field creates a successor revision and a new
publication. Existing attempts continue to point to the exact prior definition
and rule version.

### 3.1 Universal learning graph

The long-term content graph is:

```text
Program
└── Curriculum version
    └── Level
        └── Module
            └── Lesson
                ├── Objective(s)
                ├── Media source(s)
                ├── Activity sequence
                ├── Practice blueprint
                ├── Assessment definition
                └── Review/remediation rules
```

Objectives connect to reusable skill concepts rather than level labels. A
concept can represent a communication function (`request_help`), grammar
pattern (`modal.can.base_verb`), vocabulary domain (`workplace.tools`),
pronunciation focus or interaction skill. Prerequisite edges form a reviewed
acyclic graph.

This makes the system able to:

- place a new lesson in any level without code changes;
- attach several lessons to one objective or one lesson to several objectives;
- reuse a skill in review, role-play, assessment and personalized remediation;
- support different programmes or institutions over the same skill graph;
- add future languages without duplicating authoritative progress semantics;
  and
- explain exactly why a lesson or review was recommended.

### 3.2 Extensible activity catalogue

The activity engine uses versioned project-owned activity schemas. Core types
are:

- governed audio/video/text lesson;
- chunked listen-and-repeat;
- Skill Chat;
- guided dialogue fallback;
- multiple choice, multiple select, ordering, matching and cloze;
- listen-and-select and dictation;
- sentence construction/transformation;
- personal spoken response;
- task-based role-play;
- transfer challenge; and
- delayed review.

Growth schemas may add pronunciation drills, image-supported vocabulary,
reading/writing tasks, teacher assignments and collaborative activities. An
unknown schema is rendered read-only or unavailable with a safe reason; it is
never guessed by the client.

### 3.3 Dynamic lesson intelligence lifecycle

Adding a lesson starts an idempotent, observable workflow:

```text
Uploaded
→ Scanned
→ Transcribed
→ Segmented
→ Analyzed
→ Blueprint drafted
→ Deterministically validated
→ Human reviewed
→ Published
→ Quality monitored
→ Revised or retired
```

Every stage records status, exact input hash, adapter/configuration version,
attempt count, safe failure code, cost and output hash. External processing is
replayable and cannot create false transactional success.

The analysis result is structured and field-attributed. It may include:

- detected learning and guidance languages with confidence;
- timestamped transcript segments and speaker changes where useful;
- primary/secondary topics;
- can-do objective candidates and prerequisites;
- grammar patterns, communication functions, vocabulary and chunks;
- examples, counterexamples and likely misconceptions;
- estimated difficulty, duration and cognitive load;
- discussion scenarios and safe elicitation goals;
- quiz/item candidates with answer/rationale drafts;
- Apply task and rubric draft;
- accessibility gaps, content-safety flags and duplication similarity; and
- confidence/rationale for each suggestion.

Low confidence, conflicting topics, poor audio, code-switching uncertainty or
ambiguous answer keys route to human correction. Confidence never creates
publication authority.

### 3.4 Adding, replacing and combining lessons

- **New lesson:** creates a new draft node and processing workflow; it appears
  to operators as `needs_analysis`, `needs_review`, `ready` or a safe blocker.
- **More lessons in a level:** the level path renders all published ordered
  nodes dynamically; no migration or app release is needed.
- **Replacement media:** creates a successor blueprint/publication. Existing
  learner pins and attempts remain tied to the predecessor.
- **One lesson with several media files:** media can be ordered as parts or
  alternatives while the blueprint identifies exact required/optional pieces.
- **One media file covering several topics:** reviewers may split timestamped
  segments into several lessons without copying bytes.
- **Duplicate/near-duplicate lesson:** similarity is advisory; a human chooses
  reuse, merge, successor or distinct objective.
- **Missing transcript/rights/review:** the lesson remains blocked with a
  visible reason and cannot be silently published.

## 4. Runtime architecture

### 4.1 Reuse before addition

Reuse the existing:

- pinned enrollment/publication resolver;
- curriculum objectives, missions, items and immutable revisions;
- governed model-audio delivery and ADMIN-MEDIA evidence;
- `learner_attempts`, `item_responses`, `attempt_submissions`,
  `mastery_states` and `review_schedules` authority;
- conversation provider ports, prompt registry, structured correction and
  locale evaluation;
- voice consent, transcription review and deletion lifecycle; and
- outbox, audit, idempotency and organization context.

Do not create a second progress or mastery subsystem.

### 4.2 Minimal additive records

The bounded implementation is expected to need:

1. an immutable lesson-practice definition/snapshot selected by publication;
2. an immutable practice-context snapshot that links one learner, enrollment,
   publication, objective, source lesson attempt and prompt policy;
3. a link from a Skill Chat conversation session to that snapshot; and
4. append-only stage-completion evidence for Learn, Talk, Check, Apply and
   Review.

The existing mission attempt engine should be generalized from one check to a
bounded list of authored items. Formal level assessment tables should be added
only when the level-checkpoint slice needs semantics that the generalized
mission attempt cannot represent.

This foundation-schema choice must be frozen in a bounded decision/specification
before migration work.

### 4.3 Proposed HTTP surface

Exact paths may be refined in the approved specification, but the intended
resource model is:

| Method/path | Purpose |
|---|---|
| `GET /me/learning-path?enrollment_id=…` | Published levels, lessons, stage states and one next action |
| `GET /lessons/{lesson}/practice-loop?enrollment_id=…` | Exact pinned blueprint without answer leakage |
| `POST /lessons/{lesson}/practice-sessions` | Start/replay lesson-scoped Skill Chat |
| Existing conversation turn/events routes | Run structured lesson-scoped turns |
| Existing mission-attempt routes | Start, answer and submit multi-item deterministic checks |
| `POST /attempts/{attempt}/apply-evidence` | Attach exact speaking evidence without AI scoring |
| Existing progress route | Return objective, evidence and review state |

All commands are tenant-scoped, idempotent and exact-publication bound. Guessed
or foreign IDs remain indistinguishable from not found.

### 4.4 AI boundary

Add a project-owned `skill-focus` conversation policy rather than bypassing
the current `ConversationModelProvider`.

The prompt context allowlist contains only:

- can-do outcome;
- target patterns/chunks and misconception codes;
- bounded scenario and elicitation goals;
- coaching mode and active UI locale;
- turn count and current learner-confirmed transcript; and
- separately approved learner-visible memory facts.

Structured output adds advisory target-use observations and an optional retry,
while retaining the existing reply, intent, correction, L1 explanation and
safety fields. Prompt/model/configuration hashes and provider usage are
recorded. Raw transcript text remains out of ordinary logs, audit and outbox.

## 5. AI-assisted content commissioning

Live AI may accelerate drafting for all repository-held lessons, but every
published artifact remains governed human-reviewed content.

For each exact media asset:

1. verify hash, rights, lifecycle and private accessibility metadata;
2. transcribe or ingest the approved transcript;
3. have AI propose a topic, can-do outcome, target patterns, chunks, common
   errors, Skill Chat goals, quiz candidates and Apply task;
4. run deterministic schema, language, length, duplicate-answer and
   answer-leakage validators;
5. have an English academic reviewer approve/correct the source;
6. create and independently review the Kannada explanation/variant;
7. confirm exact program-scoped rights and media readiness; and
8. publish an immutable version.

AI drafts never publish themselves, confirm rights, appoint reviewers, mark an
answer correct, or alter learner records.

The inventory step must reconcile the product owner's reported four Level 1
audio lessons with actual governed repository/storage records. The local file
inventory currently exposes legacy level videos, but the four Level 1 audio
files are not identifiable as committed governed media from repository search
alone. No topic mapping should be fabricated from filenames or assumptions.

## 6. Future-proof capability map

The capability map records extension points now while keeping the first release
focused. “Growth” and “optional” features become implementation scope only
through later numbered checkpoints.

### 6.1 Learner home and learning path

Core:

- one personalized next action with reason;
- dynamic programme/level/module/lesson path;
- continue/resume from exact activity state;
- due review, offline/sync and provider-fallback status;
- separate knowledge, speaking, interaction and review progress;
- transparent prerequisites and unlock explanations;
- accessible lesson transcript, speed, replay and chunk controls;
- goal, available-time and confidence preferences; and
- private, encouraging progress without public comparison.

Growth:

- weekly learning plan and calendar;
- learner-chosen focus topics and scenario packs;
- bookmarks, saved phrases and personal phrasebook;
- mistake notebook generated from approved evidence categories;
- achievements based on meaningful practice rather than addictive streaks;
- optional reminders with quiet hours and locale-aware scheduling; and
- cross-device continuation with safe conflict resolution.

### 6.2 Tutor, Friend and role-play intelligence

Core modes:

- **Skill Chat:** exact lesson objective and target-language practice;
- **Structured Tutor:** short L1 explanation, model, guided retry and transfer;
- **AI Friend:** learner-selected natural conversation with controlled memory;
- **Role-play:** observable scenario goal with Observe → Rehearse → Perform →
  Reflect; and
- **Review Coach:** short retrieval of due patterns and carry sentences.

Growth modes:

- interview coach with role/industry/difficulty choices;
- workplace meeting, presentation and customer-service simulations;
- listening-comprehension partner with adjustable speech rate;
- vocabulary-in-context and grammar contrast coach;
- learner-requested “explain another way” and example generation;
- branching scenario consequences without authoritative scoring;
- conversation recap, reusable phrase extraction and explicit save-to-memory;
- trainer-authored scenario templates; and
- cohort/course-specific AI context restricted to published content.

Every mode has a turn/time/token ceiling, safety state, text fallback,
structured output, prompt/model hash, no-answer-leakage rule and a learner-
visible AI disclosure.

### 6.3 Listening, voice and pronunciation

Core:

- private governed audio/video playback;
- transcript/caption synchronization where timestamps exist;
- 0.75×/1× speed, replay, seek and short-loop practice;
- recording, playback, retry and learner-confirmed transcript;
- text alternative when microphone/STT/AI is unavailable;
- generated model pronunciation behind project TTS ports; and
- consent, retention, export and deletion controls.

Growth after validation:

- word/chunk shadowing with visual timing;
- pause, pace and repeated-word observations;
- minimal-pair and sound-focus practice selected from actual evidence;
- advisory word-level intelligibility confidence;
- noise/input-quality check before recording;
- comparison against several acceptable Indian-English models;
- learner-selectable voice/speed within reviewed bounds;
- pronunciation improvement trends without accent-shaming; and
- human-review queue for uncertain or high-value speaking evidence.

Pronunciation analysis never controls authoritative marks until a separately
accepted validation policy proves subgroup fairness against trained raters.

### 6.4 Assessment and evidence engine

Assessment types:

- lesson formative checks with unlimited/guided retries;
- bounded lesson checkpoint;
- level summative checkpoint in a new context;
- delayed retention review;
- placement and diagnostic assessment;
- role-play/transfer performance;
- trainer-assigned assessment; and
- optional externally mapped certificate evidence without claiming official
  equivalence.

Item types can include single/multiple choice, ordering, matching, cloze,
sentence repair, listen-and-select, dictation, short constructed response,
spoken response and task-based dialogue. Each item has exact objective links,
difficulty metadata, answer/rationale, accessibility data, review history and
exposure policy.

Future-proof assessment controls include:

- deterministic scoring policy registry and effective versions;
- bounded attempts, autosave and idempotent submission;
- item pools and equivalent forms without live AI answer generation;
- answer/rationale secrecy before submission;
- partial-credit rules only when explicit and testable;
- human rubric, moderation, correction and appeal history;
- item-quality statistics, distractor analysis and retirement;
- accommodations without lowering the target construct;
- evidence chain from response to objective/mastery/unlock; and
- no high-stakes proctoring or biometric surveillance in the core product.

### 6.5 Mastery, adaptation and review

Core mastery is a deterministic state machine over exact evidence. It records
knowledge and spoken/interaction dimensions separately, the rule version,
supporting attempts, review state and explanation.

Core personalization:

- objective prerequisites;
- deterministic remediation after incorrect checks;
- spaced retrieval based on exact objective/error evidence;
- one next-best action selected by versioned rules;
- learner goal, confidence, locale, time and connectivity preferences; and
- no lockout when AI is unavailable.

Growth personalization:

- advisory difficulty and scenario recommendations;
- adaptive item selection from reviewed pools;
- interleaving old/new objectives;
- personalized vocabulary and recurring-error review;
- configurable learning pace and weekly plan;
- transfer tasks selected from learner interests without exposing sensitive
  memory; and
- explainable model-assisted recommendations evaluated for subgroup bias.

An opaque recommendation model may rank safe reviewed choices, but it cannot
write mastery or unlock records.

### 6.6 Content and media intelligence workspace

Core operator capabilities:

- resumable private upload, inspection, scanning and rights lifecycle;
- transcript generation/import with timestamp and confidence review;
- topic/objective/chunk/misconception extraction into an editable draft;
- assessment and Skill Chat draft generation;
- side-by-side media, transcript and blueprint review;
- exact English/Kannada academic and accessibility review;
- deterministic readiness blockers;
- immutable publish/schedule/rollback/retire; and
- batch completeness matrix for every programme/level/lesson.

Growth capabilities:

- transcript segment editor with audio looping;
- glossary/terminology memory and controlled translation suggestions;
- duplicate-content and prerequisite-cycle warnings;
- difficulty/readability and speaking-time estimates;
- batch draft generation with per-item approval—not bulk auto-publish;
- content diff, provenance and “why AI suggested this” views;
- question-quality warnings and answer-ambiguity review;
- live learner issue reports linked to exact publication versions;
- content performance dashboard and safe item retirement; and
- re-analysis against a new model/prompt as a draft successor only.

### 6.7 Trainer and reviewer experience

Core:

- learner objective progress and due-review overview;
- sampled consent-permitted speaking evidence;
- rubric-based speaking review and correction history;
- learners needing help from deterministic signals;
- assignment of reviewed lessons/checkpoints;
- feedback visible to the exact learner/enrollment;
- content/language/media review queues; and
- safe audit trail.

Growth/optional:

- cohort learning plans and differentiated assignments;
- trainer-created reviewed prompt/scenario templates;
- office-hour or external-session scheduling;
- moderation/escalation for learner-reported AI/content issues;
- export/integration with institution systems under scoped contracts;
- trainer effectiveness/support metrics that do not rank staff from noisy AI
  data; and
- team review calibration and double-marking for sampled assessments.

### 6.8 Localization, accessibility and inclusion

- reviewed UI catalogues with visible approved-English fallback;
- learning content variants independently versioned from UI translation;
- Kannada-first explanation, glossary and contrastive examples;
- code-switch-aware transcript review without dialect labeling;
- captions, transcripts, text alternatives and non-audio equivalents;
- full keyboard/screen-reader use and 200–400% reflow at 320 CSS pixels;
- color/contrast/reduced-motion and large-target support;
- clear recording/processing/speaking/fallback states;
- support for low literacy through short audio-led guidance without hiding
  text; and
- future language packs activated independently only after content, STT/TTS
  and human-review evidence passes.

### 6.9 Offline, low bandwidth and device resilience

- manifest-hashed downloadable lesson bundles;
- size/connection preview and learner-controlled download/remove;
- transcript, model text/audio, guided Talk fallback and deterministic checks;
- encrypted/isolated per-membership storage under ADR-0006;
- queued responses with idempotency and user-visible sync state;
- exact conflict behavior after publication/enrollment changes;
- safe cleanup on logout/membership change/expiry;
- media quality variants and bounded caches;
- Android lifecycle, interruption, permission and storage-pressure handling;
- no silent upload of conversation recordings; and
- background work that respects battery, data-saver and network policy.

### 6.10 Safety, privacy and responsible AI

- adult-only onboarding and clear AI disclosure;
- separate consent for recording, provider processing and transcript history;
- purpose-bound context allowlists and learner-controlled memory;
- no training on learner content without separate explicit opt-in;
- safety classifier/validator and curated fallback;
- shame, harassment, discrimination, sexual content and self-harm response
  policies appropriate to the bounded educational context;
- no dialect/accent labeling, emotion inference or employment guarantees;
- learner report/block/erase controls;
- content-free operational telemetry and redacted support diagnostics;
- provider deletion/retention/reconciliation evidence;
- privacy inventory/export/erasure updates for every new record class; and
- incident response, kill switch and exact prompt/model rollback.

### 6.11 Analytics, evaluation and experimentation

Product and learning analytics:

- first-session speaking activation;
- meaningful speaking sessions/turns/minutes;
- lesson/check/Apply/review completion;
- first/latest/best attempt improvement;
- delayed retention and transfer performance;
- recurring-error reduction;
- offline success/failure and sync latency; and
- cohort/subgroup gaps with minimum-cell privacy controls.

AI/speech evaluation:

- schema/safety/fallback validity;
- false and missed correction rates;
- level/topic adherence and answer leakage;
- comprehensibility, L1 explanation and retry usefulness;
- STT word/meaning capture across representative speech patterns;
- latency, availability, token/audio usage and cost;
- prompt/model regression by locked suite hash; and
- sampled bilingual human review with inter-rater calibration.

Experiments may change presentation, recommendation ordering or reviewed
practice variants. They may not silently change answer keys, mastery rules,
rights, consent, retention or access control.

### 6.12 Platform, integration and operational maturity

- modular-monolith domain boundaries with asynchronous outbox workers;
- stable OpenAPI and generated clients;
- feature/configuration flags scoped by environment/organization with audit;
- provider routing, circuit breakers, retry budgets and deterministic fallback;
- per-tenant/global cost ceilings and usage anomaly alerts;
- prompt/model/content registry with canary and rollback;
- searchable safe descriptors, never raw learner content;
- release health, queues, deletion backlog, storage and provider dashboards;
- backup/restore, PITR, disaster recovery and evidence reconciliation;
- capacity/load/soak and low-end Android testing;
- optional SSO, roster/LMS imports, webhooks and standards mappings only behind
  reviewed tenant contracts; and
- migration tools that are idempotent, dry-runnable and never fabricate
  authority.

### 6.13 Separately gated future opportunities

These are architecturally possible but not part of core completion:

- additional Indian-language guidance and full curricula;
- organization-specific programmes and branded learning paths;
- teacher-facilitated workshops and externally hosted live sessions;
- moderated cohort challenges or paired practice with reporting/blocking;
- image/document-supported lessons after media-policy expansion;
- writing and reading pathways alongside spoken English;
- reviewed career-domain packs (healthcare, retail, hospitality, technology);
- portable achievement reports or partner credentials with careful claims;
- on-prem/open-weight provider deployments through existing ports;
- enterprise directory/roster integrations; and
- commercial entitlements/billing under separate accepted policy.

### 6.14 Explicitly prohibited shortcuts

- automatic publication of AI-generated lessons or answers;
- AI-awarded marks, mastery, attendance, entitlements or unlocks;
- sending quiz answers to the conversation model before submission;
- using raw media filename/level number as topic truth;
- rewriting prior publications, attempts or evidence after content changes;
- public media URLs or credentials in browser storage;
- raw audio/transcripts/personal facts in logs or analytics;
- unmoderated open social chat, biometric proctoring or accent elimination;
- fabricated content for missing levels; and
- presenting synthetic/demo/provider-fallback evidence as reviewed production
  quality.

## 7. Ordered core checkpoints

Only one checkpoint is active at a time.

LPL-00–LPL-07 are the launch-critical sequence. Core completion is declared at
LPL-07; later evolution checkpoints cannot be pulled forward opportunistically.

| Checkpoint | Outcome | Current state |
|---|---|---|
| **LPL-00** | Close prerequisite gates and freeze exact policy/specification | complete — APP-0046 / D-0140 / ADR-0015 |
| **LPL-01** | Inventory every exact lesson/media/objective and draft Level 1 blueprint | active under APP-0058 / D-0184 — commissioning implementation is verified; live transcription dispatch, human reconciliation and English/Kannada blueprint review remain pending |
| **LPL-02** | Deliver one Level 1 `can` vertical slice end to end | pending |
| **LPL-03** | Add governed operator authoring/review/publication for practice loops | pending |
| **LPL-04** | Deliver a complete four-lesson level journey and level checkpoint | pending |
| **LPL-05** | AI-assist, review and publish blueprints for every configured level | pending |
| **LPL-06** | Add offline fallback, delayed review, analytics and cost controls | pending |
| **LPL-07** | Roll out in reviewed waves and close release evidence | pending |

### LPL-00 — prerequisites and policy freeze

1. Complete the retained ADMIN-MEDIA PostgreSQL, locale and full release-gate
   checks already recorded in `docs/STATUS.md`.
2. Inventory current curriculum/attempt/conversation constraints and publish a
   bounded LPL specification.
3. Record a decision for the minimal additive schema, multi-item scoring,
   speaking-evidence states and level-unlock rule.
4. Record the live-development AI/media allowance without interpreting it as
   unlimited spend or production promotion.
5. Define locked verification scope and answer-leakage/security tests.

Exit: specification and decision accepted; no runtime code yet.

### LPL-01 — exact inventory and Level 1 blueprint

1. **Complete:** enumerate all configured levels, lessons, objectives, exact
   media hashes, transcripts, rights/review state and source gaps.
2. **Complete:** the four Level 1 audio parts were reacquired and reverified
   against APP-0047, then accepted as active exact-hash `lesson_audio` assets
   through ordinary OTP, selected membership, retained TOTP, ffprobe and
   ClamAV after DR-0023's validated restore/replay.
3. **Complete:** DR-0024 created the standalone private pilot, exact two-
   permission role and program-scoped appointment; ordinary selected-
   membership/fresh-TOTP HTTP commands accepted the appointment and recorded
   one exact APP-0047 rights revision per eligible asset.
4. **Complete:** create a content-completeness matrix and stop on
   missing/unreviewed media, transcript, objective, answer rationale or rights
   evidence.
5. **In progress — DR-0025 accepted:** implement and verify the finite provider-
   run/no-training/retention/provenance contract, then execute its one exact
   campaign only after every live gate passes and have a human reconcile each
   transcript without assuming every part has the same `can` scope. No
   implementation or provider request has occurred from approval alone.
6. **Pending:** draft and deterministically validate one complete pilot
   blueprint, then obtain English and Kannada review of its exact hashes.

Evidence: [LPL-01 content inventory](LPL-01_CONTENT_INVENTORY.md) and
[Level 1 blueprint review packet](LPL-01_LEVEL1_BLUEPRINT_REVIEW_PACKET.md),
with the [legacy source map](LPL-01_LEGACY_SOURCE_MAP.md).

Exit: one publishable Level 1 blueprint and a truthful all-level gap inventory.

### LPL-02 — Level 1 `can` vertical slice

1. Add the minimal migration and typed content adapter.
2. Generalize the current single-check reader/attempt service to 3–7 items while
   preserving existing v1 attempts byte-for-byte and behavior-for-behavior.
3. Add exact lesson-practice context resolution and `skill-focus` conversation
   sessions behind the project provider interface.
4. Build the learner Learn → Talk → Check → Apply → Review UI.
5. Provide authored offline Talk fallback and offline Check.
6. Add OpenAPI/generated clients, all six UI catalogues and observability.
7. Run focused SQLite/PostgreSQL/API/web/AI-eval/E2E checks.

Exit: one real pinned learner can complete the `can` loop; AI feedback cannot
change score, mastery or unlock.

### LPL-03 — governed authoring workflow

1. Add typed Content Studio forms for blueprint fields and bounded previews.
2. Add exact English/Kannada review queues and readiness blockers.
3. Pin practice definition, prompt policy, quiz/rationale and media evidence in
   publication manifests.
4. Add successor, schedule, rollback and retirement behavior without mutating
   prior attempts.
5. Add permission, appointment, concurrency and cross-tenant tests.

Exit: an authorized operator can commission a new lesson loop without database
access or raw JSON.

### LPL-04 — complete level and checkpoint

1. Render every published lesson dynamically; do not assume exactly four.
2. Define deterministic lesson completion and prerequisite projection.
3. Add a novel level checkpoint with bounded attempts and versioned rules.
4. Keep AI Skill Chat optional/advisory and provide deterministic offline
   equivalence.
5. Require actual speaking evidence for any spoken-progress claim; use
   `awaiting_review` until an accepted authoritative reviewer/rule exists.
6. Add one transparent unlock-decision record and learner explanation.

Exit: all Level 1 lessons and its checkpoint form one explainable progression
path with concurrency and replay tests.

### LPL-05 — all-level commissioning

1. Run the AI-assisted draft pipeline against each exact eligible media asset.
2. Review and publish in waves: Level 1, then Levels 2–3, then remaining
   configured levels based on content readiness—not hard-coded numbers.
3. Track per lesson: media, transcript, objective, blueprint, English review,
   Kannada review, quiz rationale, Apply task, rights and publication.
4. Never fill missing source content with fabricated lessons to satisfy a
   percentage target.

Exit: every enabled level has complete reviewed loops; incomplete levels remain
clearly unavailable with a code-owned reason.

### LPL-06 — retention, offline and measurement

1. Add delayed-review selection and due-state UX.
2. Test offline bundles, queued attempts, reconnect conflict handling and
   publication pin stability.
3. Add content-free metrics for stage completion, speaking turns, quiz retry,
   review return, AI fallback, latency and per-session cost.
4. Add provider kill switch, per-session token/turn limits and budget alerts.
5. Validate voice consent, transcript correction, deletion and privacy export.

Exit: low-connectivity and provider-failure journeys remain useful, private and
deterministic.

### LPL-07 — rollout and completion

For each rollout wave:

1. run bilingual content review and locked AI evaluation;
2. run tenant, authorization, publication, idempotency and PostgreSQL races;
3. run component, axe, 320-pixel, browser and Android journeys;
4. run `make verify`, `make e2e`, `make ai-eval`, `make build` and migration/
   backup-restore gates where applicable;
5. conduct representative learner/trainer UAT and record corrections as new
   content revisions; and
6. promote only exact reviewed publications and configuration hashes.

Exit: the all-level completeness matrix is green for every enabled level,
release gates pass and the accountable owner issues exact release acceptance.
Production/public release remains a separate explicit decision.

## 8. Ordered platform-evolution checkpoints

These checkpoints begin only after core completion and fresh prioritization.
Their order may be amended by an accepted decision based on learner evidence,
pilot needs and commercial strategy.

| Checkpoint | Capability | Tier |
|---|---|---|
| **LPL-08** | Explainable adaptive path and richer spaced review | Growth |
| **LPL-09** | Validated listening/pronunciation and speech-review tools | Growth |
| **LPL-10** | Trainer assignments, rubric review and intervention workspace | Growth |
| **LPL-11** | Content intelligence quality loop and item analytics | Growth |
| **LPL-12** | Enterprise configuration and reviewed integrations | Optional |
| **LPL-13** | Additional language and career-domain packs | Optional |
| **LPL-14** | Moderated collaborative/live learning | Optional |
| **LPL-15** | Provider portability, open-weight/on-prem and global-scale maturity | Optional |

### LPL-08 — explainable adaptation

- implement deterministic spaced-review/interleaving policies;
- rank only reviewed eligible activities;
- expose “why this is next” to learners/trainers;
- add goal, interest, time, confidence and connectivity preferences;
- evaluate recommendation quality and subgroup differences; and
- retain a deterministic non-model fallback and immutable recommendation
  evidence where needed for support.

Exit: personalization improves selection without changing authoritative mastery
or making the path opaque.

### LPL-09 — richer speech coaching

- add timestamped shadowing, chunk loops and input-quality checks;
- benchmark pace/pause/intelligibility observations against trained raters;
- prove subgroup fairness and safe confidence thresholds;
- provide human review for uncertain/high-value evidence;
- add trend UX that rewards comprehensibility rather than accent imitation; and
- preserve consent, correction, retention and deletion lifecycle.

Exit: reviewed speech features meet accepted accuracy/fairness thresholds;
otherwise they remain advisory or disabled.

### LPL-10 — trainer learning operations

- assignment and due-date workflow over exact publications;
- rubric review, correction, moderation and learner-visible feedback;
- help-needed queues from deterministic evidence;
- cohort plan and differentiated reviewed activities;
- safe exports and audit; and
- workload/accessibility/notification controls.

Exit: trainers can support the complete learner loop without database access or
broad personal-data exposure.

### LPL-11 — closed-loop content quality

- item performance/distractor and content issue analysis;
- learner/trainer report triage tied to exact versions;
- stale/low-performing item review and successor workflow;
- AI re-analysis as non-authoritative draft;
- controlled glossary, translation and duplicate/prerequisite tooling; and
- content quality/effectiveness dashboards with privacy thresholds.

Exit: production evidence can improve content through reviewed successors
without rewriting historical learner results.

### LPL-12 — enterprise configuration and integrations

- organization-scoped programme/cohort configuration;
- reviewed roster/SSO/LMS/webhook contracts where required;
- feature/config flags with delegation and audit;
- organization reporting with privacy-safe aggregation;
- entitlement/commercial integration only under separate policy; and
- migration/dry-run/reconciliation tooling.

Exit: each integration is tenant-isolated, idempotent, observable and removable
without changing core learning authority.

### LPL-13 — languages and career domains

- language-pack readiness contract for UI, guidance, content, STT/TTS and eval;
- independently reviewed curricula and glossaries;
- domain packs with qualified content review;
- locale-specific safety/fallback and representative speech evaluation; and
- no automatic translation-based publication.

Exit: each language/domain activates independently only after its complete
review and quality matrix passes.

### LPL-14 — moderated collaborative/live learning

- separately consented paired/cohort practice with reporting and blocking;
- trainer-facilitated external session scheduling;
- typed purpose/audience/retention/moderation policies;
- no unmoderated public social feed or in-product recording by default; and
- deterministic participation records distinct from mastery/attendance.

Exit: safety, moderation, retention and tenancy policies are accepted and
operational before any learner-to-learner interaction opens.

### LPL-15 — provider and scale maturity

- benchmarked provider routing/failover behind existing ports;
- optional open-weight/on-prem inference;
- prompt/model canary, drift and rollback automation;
- multi-region/privacy-residency design if commercially required;
- production capacity, disaster recovery and cost optimization; and
- long-term data/schema compatibility and archive testing.

Exit: provider changes do not alter domain contracts, learner authority,
privacy promises or exact evidence reproducibility.

## 9. Verification minimum

The programme is incomplete unless tests prove:

- same-tenant success and cross-tenant non-disclosure for every new resource;
- exact enrollment/publication/objective/media binding;
- no quiz answer/rationale reaches the AI or pre-submit learner response;
- changed idempotency replay conflicts and concurrent submission has one winner;
- AI/provider failure selects an authored fallback without changing marks;
- old publications and attempts remain immutable after content successors;
- rights withdrawal and safety suspension fail delivery closed as specified;
- spoken evidence is not misrepresented as spoken mastery;
- offline attempts sync once and retain their exact rule/content versions;
- prompts, models and content are eval-hash pinned with bounded cost; and
- no raw audio, transcript, answers, credentials or personal facts enter logs,
  analytics, audit or outbox.

## 10. Definitions of complete

### 10.1 Core completion

This plan is complete only when:

1. every enabled lesson has a reviewed published practice blueprint;
2. every enabled level exposes Learn, Talk, Check, Apply and Review;
3. every enabled level has one deterministic, versioned checkpoint and
   explainable unlock decision;
4. the learner can finish the deterministic path when AI is unavailable;
5. AI Skill Chat works through a reviewed project-owned live adapter with
   passing locked evaluation and cost controls;
6. English/Kannada content, media, rights and accessibility evidence is exact;
7. web/PWA and Android critical journeys pass at target widths/devices;
8. privacy, tenancy, security, backup and operational gates pass; and
9. the exact release is accepted without claiming AI-awarded mastery or
   unreviewed content.

### 10.2 Evolution-checkpoint completion

An LPL-08+ capability is complete only when its bounded specification,
privacy/tenancy/security impact, API/data contracts, accessibility,
provider/content evaluation, operations, migrations and rollback are evidenced.
No evolution checkpoint reopens or weakens the core completion invariants.

### 10.3 Product success review

After each rollout wave, accountable humans review:

- whether learners speak more and return;
- whether knowledge and transfer improve after delay;
- whether feedback is accurate, kind and useful;
- whether subgroup outcomes reveal avoidable quality gaps;
- whether trainers can intervene effectively;
- whether offline/device failure remains recoverable;
- whether privacy/safety incidents or complaints require policy changes; and
- whether cost per meaningful session supports continued scale.

Passing software tests without these learner and operational outcomes is not a
claim that the product is educationally successful.

## 11. Principal risks and design responses

| Risk | Design response |
|---|---|
| Audio does not reveal one reliable topic | Timestamped segmentation, confidence per suggestion and mandatory reviewer selection |
| Poor/noisy or code-switched transcript | Editable transcript, bounded reprocessing and human correction before analysis authority |
| AI hallucinates a rule, answer or rationale | AI output remains a draft; deterministic validators and academic review precede publication |
| New lessons break clients | Versioned activity schemas, capability manifest and safe unknown-schema behavior |
| Content changes invalidate old results | Immutable successors and exact publication/rule pins on every attempt |
| AI/provider outage blocks learning | Authored guided dialogue, deterministic checks and offline bundle fallback |
| AI gives unsafe or shaming feedback | Structured validator, safety policy, curated fallback, reporting and kill switch |
| Quiz answers leak into prompts/responses | Separate sealed assessment projection and explicit no-answer-leakage tests |
| Automated speech scoring is unfair | Advisory-only signals until human-rater validation and subgroup thresholds pass |
| Personal conversation data expands silently | Purpose allowlist, learner-controlled memory, consent/retention/deletion and no raw telemetry |
| Content review becomes the scale bottleneck | Batch drafting and completeness queues, but field-level human approval and no bulk auto-publish |
| Provider/model update changes quality | Locked eval suites, prompt/model hashes, canary, drift detection and rollback |
| Live AI becomes too expensive | Per-session ceilings, tenant/global budgets, usage alerts, caching where safe and deterministic fallback |
| Institution configuration weakens tenancy | Organization-derived scope, atomic permissions, delegation ceilings and cross-tenant tests |
| Feature breadth delays learner value | Core/evolution boundary and one checkpoint at a time |

## 12. Traceability and living-plan rules

Every implementation checkpoint must update:

- the accepted bounded specification or decision request;
- OpenAPI and generated clients for API changes;
- schema/data-class privacy inventory for new records;
- content/AI evaluation suites for new prompts or providers;
- all used localization catalogues and accessibility evidence;
- `docs/STATUS.md`, `docs/DECISIONS.md` and `docs/QUALITY_REVIEW.md`; and
- the checkpoint register in this plan.

Feature requests are classified against the scope tiers and mapped to an
existing or new checkpoint. They do not become implementation authority merely
because they appear in this roadmap.

## 13. Working protocol

When the owner says “continue this plan”:

1. read `docs/STATUS.md` and this plan;
2. execute only the first pending checkpoint;
3. update OpenAPI/generated clients whenever an API changes;
4. update tests and the required status/decision/quality ledgers;
5. mark the checkpoint complete only after its exit gate actually passes; and
6. do not begin the next checkpoint opportunistically.

**Current executable action:** implement and verify accepted
[DR-0025](DR-0025_LPL01_TRANSCRIPTION_COMMISSIONING_REQUEST.md) Option A under
APP-0052 / D-0153 / ADR-0020. It fixes
a separate provider-neutral curriculum-media path and one exact direct-MP3
Azure campaign capped at four logical assets, eight HTTP attempts, 37,520,380
uploaded bytes, 2,345,026 billable milliseconds and ₹100 estimated service
usage, with fail-closed evidence/permit switches, no-storage/no-training gates,
encrypted expiring candidates and fresh-TOTP human reconciliation. Do not
dispatch until every execution-time gate passes, and do not begin LPL-02 until
LPL-01 has one reviewed publishable blueprint.

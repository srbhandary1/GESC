# Global Spoken English sales feature and solution guide

**Document status:** Sales-safe pre-release baseline
**Prepared:** 10 August 2026
**Intended readers:** Sales, partnerships, customer success, institutional
buyers, workforce-learning sponsors and implementation partners
**Production/public release:** Not authorized

## Quick navigation

- [Executive proposition](#1-executive-proposition)
- [Sales status rules](#2-sales-status-rules)
- [Target buyers](#3-who-the-product-is-for)
- [Learner experience](#4-the-learner-experience)
- [Feature catalogue](#5-feature-catalogue)
- [Scalable lesson system](#6-why-the-lesson-system-can-scale)
- [Responsible AI](#7-responsible-ai-proposition)
- [Security, privacy and tenancy](#8-security-privacy-and-tenancy)
- [Buyer use cases](#11-buyer-use-cases)
- [Sales demonstration](#14-demonstration-guide-for-sales)
- [Claims guardrail](#17-claims-guardrail)
- [Roadmap](#18-current-limitations-and-roadmap)

## 1. Executive proposition

Global Spoken English is a mobile-first learning product for Kannada-speaking
adults who want practical English for everyday life, travel and work. It
combines short governed lessons, private speaking practice, meaning-first AI
conversation, deterministic knowledge checks and a controlled institution
workspace.

The product is designed for a learner who understands more than they feel
comfortable saying. It creates a low-pressure route from **understand** to
**hear**, **try**, **check**, **apply** and **review**, while keeping official
marks, mastery and progression outside the control of generative AI.

The commercial story rests on six strengths:

1. **Kannada-guided confidence:** explain meaning in the learner's familiar
   language while practising useful English.
2. **Private practice before public performance:** text, local playback and
   optional consented voice reduce fear of making mistakes.
3. **Governed learning, not an open chatbot:** every learner interaction is
   grounded in approved lesson content and bounded product policy.
4. **Institution-ready administration:** organization-scoped people, learner,
   curriculum, publication and private-media governance.
5. **Low-bandwidth and mobile design:** responsive web/PWA, bounded offline
   missions and an Android secure-auth foundation.
6. **Responsible, provider-neutral AI:** project-owned adapters, explicit
   consent, fail-closed provider configuration and deterministic authority.

## 2. Sales status rules

This document separates product evidence from product vision. Use the following
terms in proposals, demonstrations and buyer conversations:

| Status | Sales-safe meaning |
|---|---|
| **Repository implemented** | Feature exists in the product repository with automated evidence; deployment and release approval are still required |
| **Controlled/conditional** | Workflow exists, but use depends on approved provider, credentials, content, media, legal/privacy, spend or operating configuration |
| **Demonstration only** | Synthetic browser-memory experience used to communicate direction; not an authoritative customer feature |
| **Planned** | Designed and queued, but not available to promise in a current contract |
| **Deferred/blocked** | Intentionally outside the current release or waiting for a policy/architecture decision |

As of 10 August 2026, the product is not authorized for production or public
release. There is no complete independently reviewed production curriculum
pathway or approved production media set. Sales must not describe repository
implementation, a synthetic demo or a planned feature as a live deployment.

## 3. Who the product is for

### Individual adult learners

Learners who want useful spoken English without the pressure of a classroom or
an open-ended English-only chatbot.

Typical needs:

- daily-life phrases and sentence patterns;
- workplace confidence and interview preparation foundations;
- travel and service interactions;
- repeated private listening and speaking attempts; and
- first-language explanations when English-only correction is overwhelming.

### Colleges and placement cells

Institutions seeking a governed supplementary practice layer for adult or
near-workforce learners, with cohort organization, trainer relationships and
controlled curriculum publication.

### Training organizations

Providers that need reusable curriculum structure, independent language and
rights review, private media, learner invitations and organization isolation.

### Employers and workforce programs

Programs focused on role-based communication confidence rather than formal
language certification: front-desk interactions, customer handling, workplace
clarification and everyday team communication.

## 4. The learner experience

### Current repository journey

| Moment | Learner experience | Product value | Status |
|---|---|---|---|
| Join | Mobile-number OTP; optional institutional invitation | Low-friction direct or sponsored access | Repository implemented |
| Orient | Adult confirmation and Everyday/Travel/Workplace goal | Relevant starting context without a long placement form | Repository implemented |
| Learn | Model English, Kannada guidance, transcript and private model audio | Connect form, meaning and sound | Repository implemented; production content/media gated |
| Try | Optional local record, playback and retry | Private confidence rehearsal without automatic scoring | Repository implemented |
| Check | One authored multiple-choice knowledge question | Deterministic evidence that meaning/usage was understood | Repository implemented |
| Result | First/latest/best attempt, knowledge state, review timing and objective progress | Transparent evidence without pretending speech was assessed | Repository implemented |
| Practise | Warm-up → Listen → Talk → Reflect Confidence Conversation | Bounded, meaning-first practice with text fallback | Repository implemented; live provider conditional |
| Remember | Learner-visible add/edit/delete AI-memory notes | Personalization under learner control | Repository implemented |
| Watch | Private level video with transcript/text alternative when pinned | Governed multimodal explanation | Repository implemented; approved assets conditional |
| Continue offline | Encrypted, personal-device, 24-hour mission bundle and later sync | Bounded resilience for intermittent connectivity | Repository implemented |

The current daily mission intentionally reports local speaking as unassessed.
It does not manufacture a pronunciation mark from a browser recording.

### Planned complete lesson loop

The queued lesson-practice design expands every governed audio lesson into:

**Learn → Talk → Check → Apply → Review**

| Stage | Planned experience |
|---|---|
| **Learn** | Listen, read the transcript, understand the topic, useful forms and first-language explanation |
| **Talk** | Hold a lesson-grounded Skill Chat with hints, replay, text fallback and bounded correction |
| **Check** | Complete a 3–7 item quiz generated as a draft and reviewed before publication |
| **Apply** | Use the taught language in a practical speaking or response task |
| **Review** | Return through deterministic spaced review based on governed evidence |

This is the future-proof answer to adding new lessons. An administrator uploads
an audio asset; a transcription/analysis pipeline proposes its topic, language
functions, vocabulary, likely level, quiz blueprint and conversation contract;
an authorized human reviews and edits the proposal; only an immutable published
version reaches learners. AI drafts. Humans publish. Deterministic services own
marks, review timing and progression.

The full loop is **planned**, not current runtime behavior. Uploading new audio
today does not automatically create or publish a chat and assessment.

## 5. Feature catalogue

### Learner access and engagement

| Feature | Customer benefit | Status and boundary |
|---|---|---|
| Responsive web/PWA shell | Access across common phone and desktop browsers | Repository implemented |
| Android application foundation | Secure mobile authorization and app packaging path | Repository implemented for closed-beta foundation; physical-device/public-release evidence open |
| Mobile OTP sign-in | Familiar low-friction authentication | Repository implemented |
| Direct and institutional onboarding | Supports self-directed and sponsored learners | Repository implemented |
| Interface locale selector | Kannada-first shell with English/Hindi/Malayalam/Tamil/Telugu catalogue options | Repository implemented; five non-English catalogues pending independent review and production English fallback |
| Everyday/Travel/Workplace goals | Simple learner-relevance signal | Repository implemented |
| Daily mission estimate and next action | Keeps practice short and understandable | Repository implemented |
| Attempt/result transparency | Learners can see first/latest/best evidence and review timing | Repository implemented |
| Full Home and standalone Progress dashboards | Richer longitudinal engagement view | Partly demonstration-only/planned |
| Attendance, notices and daily-activity dashboard | Institutional engagement concept | Demonstration only |

### Learning content and assessment

| Feature | Customer benefit | Status and boundary |
|---|---|---|
| Kannada-guided model lesson | Meaning before correction | Repository implemented; approved curriculum required |
| Private model audio | Reliable example tied to exact content | Repository implemented; governed media/rights required |
| Transcript and text alternative | Accessibility and low-bandwidth fallback | Repository implemented in governed content/media contracts |
| Local speaking playback | Risk-free self-rehearsal | Repository implemented; not automatically assessed |
| Authored knowledge check | Deterministic understanding check | Repository implemented; one check in current daily mission |
| Objective progress and review due | Clear next learning signal | Repository implemented |
| AI audio transcription/topic extraction | Faster onboarding of future lesson assets | Planned |
| Operator-reviewed AI lesson blueprint | Scalable lesson setup without auto-publication | Planned |
| Dynamic 3–7 item quiz bank | Broader diagnostic and retrieval practice | Planned |
| Applied speaking task | Transfer from recognition to use | Planned |
| Spaced and interleaved review | Durable retention over time | Planned extension |
| Validated pronunciation diagnostics | Targeted intelligibility feedback | Future, only after dialect/fairness validation; no current authoritative score |
| Formal CEFR certification | Regulated/exam-style credential | Not offered |

### AI conversation and speaking confidence

| Feature | Customer benefit | Status and boundary |
|---|---|---|
| Confidence Conversation | Structured private conversational practice | Repository implemented |
| Gentle/Tutor/Fluency coaching modes | Learner-controlled coaching intensity | Repository implemented |
| Warm-up/Listen/Talk/Reflect flow | Clear interaction rather than an empty chat box | Repository implemented |
| Meaning-first response | Keeps communication ahead of correction | Repository implemented in orchestration policy |
| One priority correction | Reduces correction overload | Repository implemented in orchestration policy |
| Hint, repeat, skip, type instead | Supports anxiety, accessibility and recognition failures | Repository implemented |
| Text-always fallback | Practice without microphone/provider speech dependency | Repository implemented |
| Separate voice consents | Learner chooses upload/transcription and provider processing | Repository implemented |
| Transcript correction/discard | Learner can correct recognition before use | Repository implemented |
| Learner-visible AI memory | Personalization without hidden memory | Repository implemented |
| Live generative conversation | More varied lesson-grounded dialogue | Controlled/conditional; local/test default is deterministic fake and live provider requires approved configuration/spend/release |
| Lesson-specific Skill Chat | Chat grounded automatically in each new lesson blueprint | Planned |
| Clinic reception role-play | Demonstrates Observe/Rehearse/Perform/Reflect | Synthetic non-production fixture |
| Additional workplace/travel/everyday role-plays | Scenario-based transfer | Planned after specialist content review |

### Offline, media and accessibility

| Feature | Customer benefit | Status and boundary |
|---|---|---|
| Explicit personal-device offline opt-in | Learner controls local retention | Repository implemented |
| Encrypted offline mission partition | Safer bounded offline learning | Repository implemented |
| 24-hour authorization lease | Limits stale/offboarded offline access | Repository implemented |
| Authoritative sync/reconciliation | Prevents local state becoming an unofficial mark | Repository implemented |
| Private level-video streaming | Multimodal lesson support without public media URLs | Repository implemented; exact pin/current rights required |
| Bounded Android video handling | Works within current 20 MB media ceiling | Repository implemented; representative physical-device evidence open |
| Offline video | Video without connectivity | Not currently supported |
| Responsive 320-pixel reflow/automated accessibility checks | Supports small screens and keyboard/label use | Repository evidence exists; independent human review remains open |

### Institution and operator capabilities

| Feature | Customer benefit | Status and boundary |
|---|---|---|
| Permission-aware operator shell | Each operator sees only server-authorized panels | Repository implemented |
| TOTP and one-time recovery codes | Stronger protection for authoritative actions | Repository implemented |
| Fresh MFA for mutations | Limits damage from an unattended signed-in session | Repository implemented |
| Branch and cohort management | Organize institutional delivery | Repository implemented |
| Staff invitations/memberships/roles | Govern who can operate the program | Repository implemented |
| Trainer-to-cohort relationships | Derive trainer learner summaries safely | Repository implemented |
| Reviewer and rights appointments | Bind human authority to exact scope | Repository implemented |
| Learner invitations and lifecycle | Controlled enrollment and participation | Repository implemented |
| Exact curriculum publication pin | Learners stay on an immutable known version | Repository implemented |
| Content Studio | Structured program/level/lesson authoring and review | Repository implemented |
| Immediate/scheduled publish, retire, rollback, successor | Govern the full content lifecycle | Repository implemented |
| Media Library | Private upload, quarantine, scanning, metadata, rights, attachment, replacement and retirement | Repository implementation/focused evidence; full current-host release gate incomplete |
| Bounded organization search | Find safe descriptors without indexing private learner content | Repository implemented |
| Audit and support diagnostics | Investigate operations without exposing raw practice content | Repository implemented |
| Privacy export/erasure requests | Governed data-subject operations | Controlled API/runbook; complete React admin workflow not yet available |
| Writable organization settings | Customer-specific typed configuration | Planned later checkpoint |
| Groups, facilitated sessions and activities | Structured institutional learning operations | Planned later checkpoints |
| Human assignment review | Trainer rubric/feedback workflow | Planned later checkpoint |
| Notices and moderated groups | Managed communication/community | Blocked pending exact retention/moderation policy |
| Entitlements and plans | Deterministic access records | Manual/external-evidence design planned |
| Checkout, billing, invoices and payment webhooks | Self-service monetization | Deferred/blocked pending commercial/provider decisions |

## 6. Why the lesson system can scale

The architecture treats a lesson as governed data, not a hard-coded screen.
A curriculum graph can contain programs, levels, objectives, modules, lessons,
items and delivery bundles without hard-coding “Level 1” or a fixed number of
levels in domain behavior.

For future audio ingestion, the planned pipeline separates concerns:

1. **Exact asset:** private bytes, hash, type and rights evidence.
2. **Transcript:** immutable source transcript with language/confidence facts.
3. **Lesson analysis:** topic, learning function, vocabulary, grammar pattern,
   learner level and safety flags.
4. **Practice blueprint:** approved prompts, hints, correction policy and
   allowed scope for Skill Chat.
5. **Assessment blueprint:** human-reviewed questions, answer keys, rationales
   and difficulty tags.
6. **Publication:** one immutable version binding content, media, reviews and
   rights.
7. **Evidence:** deterministic attempts, results, mastery and review schedule.

Replacing an audio file creates a new exact asset and invalidates assumptions
that are no longer exact. It cannot inherit scanner, accessibility, rights or
academic approval by accident. This is slower than instant auto-publication,
but it is the foundation for reliable institutional scale.

## 7. Responsible AI proposition

The product's AI boundary is a commercial differentiator for institutions that
need both innovation and control.

### AI may

- propose a transcript or lesson analysis;
- draft a bounded chat/quiz blueprint for human review;
- respond within the published lesson scope;
- provide formative, meaning-first coaching;
- offer one priority correction; and
- use learner-visible, editable memory when authorized.

### AI may not

- publish curriculum or media;
- approve language, accessibility or rights evidence;
- create an official mark;
- decide mastery, progression or unlocks;
- change attendance, enrollment or entitlements;
- hide persistent learner memory;
- silently upload voice; or
- replace a qualified teacher, reviewer, examiner or legal/rights decision.

Provider SDK types stay behind project-owned adapters. A live provider must be
explicitly configured and approved. If configuration is missing or unsafe, the
provider path fails closed and the product retains deterministic/text routes.

## 8. Security, privacy and tenancy

### Organization isolation

- Every tenant-owned resource is scoped to an active organization membership.
- Tenant equality and permission checks are independent mandatory controls.
- Foreign identifiers are existence-hidden.
- Roles and grants are organization-owned; no general super-admin bypass is
  exposed in the organization console.

### Identity and credentials

- Web/PWA uses secure cookie sessions.
- Android uses Authorization Code with PKCE behind a project-owned transport.
- Android access tokens stay memory-only; refresh/installation/PKCE state uses
  reviewed Keystore-backed secure storage.
- Operator mutations are web-only and fresh-TOTP protected.
- Authentication credentials are not placed in browser localStorage,
  IndexedDB or ordinary Capacitor preferences.

### Voice and learner data

- Local recording is separate from upload consent.
- Upload/transcription and conversation-provider processing have explicit
  consent boundaries.
- Text remains available.
- Raw sensitive audio, transcripts and personal practice content are excluded
  from ordinary logs, audit payloads, analytics properties and operator search.
- Voice deletion uses durable intent/receipt and controlled retry.

### Content and media authority

- Published content is immutable/versioned.
- Review decisions bind to exact source/translation heads.
- Media is private, scanned, accessibility-reviewed and rights-confirmed for an
  exact program and use.
- A new asset or revision does not silently inherit old authority.

These are repository design and test boundaries, not substitutes for an
independent security review, data-processing agreement, qualified legal review,
production penetration test or customer-specific compliance assessment.

## 9. Accessibility and language positioning

The product is designed around first-language guidance, text alternatives,
keyboard support, responsive reflow, clear retry states and optional voice.
Automated tests cover critical pages and narrow layouts, but independent human
accessibility review remains open.

Sales may say:

> “The product is designed Kannada-first, with English as the learning language
> and optional text/voice routes.”

Sales must not yet say:

> “The complete curriculum and AI speech experience is certified in six
> languages.”

Kannada is the primary learning-language design. English, Hindi, Malayalam,
Tamil and Telugu interface catalogues exist, but the five non-English
catalogues await independent review and use an approved-English production
fallback until accepted. Interface translation does not imply full curriculum,
speech-recognition or guidance-voice coverage.

## 10. Deployment and integration position

### Current delivery foundations

- Laravel modular-monolith API/domain architecture;
- React responsive web/PWA;
- Capacitor Android foundation;
- PostgreSQL production data model with SQLite test support where appropriate;
- private object-storage abstraction for governed media;
- project-owned AI, speech and storage adapters; and
- OpenAPI 3.1.1 with generated TypeScript client contracts.

### Environment prerequisites

A customer-ready deployment will require at least:

- approved hosting region and tenant setup;
- PostgreSQL, private object storage and scanner configuration;
- SMS/OTP and mail/notification operational decisions where applicable;
- approved AI/speech provider credentials, data terms, budget and rate limits;
- reviewed curriculum, translations, media, transcripts, accessibility and
  rights evidence;
- support, privacy-request, incident, backup/restore and deletion operations;
- security/privacy/accessibility/language/legal acceptance; and
- signed Android build/device evidence if Android is in scope.

### Integration roadmap

The modular provider boundary supports future provider replacement and
benchmarking. Institution SSO, roster sync, HR/LMS integration, external
reporting, payment providers and on-premises deployment are not current product
commitments and require separately scoped work.

## 11. Buyer use cases

### College spoken-confidence supplement

Learners complete short daily missions, practise privately before class and use
cohort-aligned content. Trainers manage cohorts and curriculum governance
without receiving unrestricted access to private voice/chat content.

**Current fit:** controlled pilot after release gates and reviewed content.
**Future expansion:** applied tasks, assignment review and aggregate cohort
reporting.

### Workplace communication pathway

An employer publishes role-relevant micro-lessons and scenarios, assigns an
immutable version to a cohort and offers private AI practice before live
workplace use.

**Current fit:** governed lessons and bounded conversation prototype.
**Future expansion:** approved workplace role-play library, trainer review and
external HR/LMS evidence exchange.

### Training-provider curriculum operation

Authors build a multi-level program, language reviewers approve exact revisions,
rights confirmers govern media, and publishers release immutable versions to
learners.

**Current fit:** repository-implemented Content Studio and Media Library
vertical slices, pending production gate closure.
**Future expansion:** AI-assisted audio ingestion, activities, reviews,
communications and typed configuration.

## 12. Packaging conversation without premature pricing

Commercial packaging and billing are not yet approved product functions. Sales
can structure discovery around capability bundles without quoting an
unsupported price or entitlement workflow:

| Discovery bundle | Candidate scope |
|---|---|
| **Learner pilot** | One organization, reviewed pathway, bounded learner/cohort count, web/PWA, text-first practice, agreed support window |
| **Voice practice pilot** | Learner pilot plus approved speech/conversation provider, explicit consent, deletion operations, budget/rate cap and evaluation plan |
| **Institution content pilot** | Learner pilot plus trained authors/reviewers, Content Studio, governed private media and publication operations |
| **Android closed beta** | Eligible pilot plus exact signed build, physical-device matrix, secure auth evidence and closed-distribution controls |

Every proposal should state exact learner count, organizations, content scope,
media rights, provider budget, data region, languages, support, evaluation
period, success measures and exit/deletion process. Do not imply self-service
billing, unlimited provider usage or automatic production publication.

## 13. Suggested pilot outcomes

Use measurable behavior rather than guaranteed fluency claims. Candidate pilot
questions include:

- What proportion of invited learners activate successfully?
- How many learners complete the agreed mission sequence?
- How often do learners return for the scheduled review?
- Does authored knowledge-check accuracy improve across attempts?
- How many learners choose text, local playback or consented voice?
- Can learners complete critical journeys on target low-bandwidth devices?
- Do trainers and reviewers complete the governed content workflow without
  unauthorized workarounds?
- Are provider cost, latency, transcription failure and deletion receipts
  within the pre-agreed pilot thresholds?
- Do accessibility and language reviewers accept the exact pilot pathway?

The exact beta sample, subgroup definitions and acceptance thresholds remain a
formal decision before validation beta. Sales must agree them in the pilot
scope rather than inventing a universal benchmark.

## 14. Demonstration guide for sales

### Before the demonstration

- State that the environment is synthetic and pre-production.
- Use only licensed/repository-approved demonstration media.
- Do not enter real learner data.
- Confirm whether the session uses a deterministic fake provider or an
  explicitly approved live provider.
- Never imply that demo attendance, assignments, notices or dashboard cards are
  authoritative runtime functions.

### Ten-minute story

1. **The learner problem (1 minute):** “I know the meaning, but I hesitate to
   speak.”
2. **Join and orient (1 minute):** show OTP/invitation concept and the simple
   learning goal.
3. **Daily mission (3 minutes):** model English, Kannada guidance, transcript,
   local playback, one deterministic check and transparent result.
4. **Private practice (2 minutes):** coaching mode, text/voice choice, repeat,
   hint and one priority correction. State the provider mode truthfully.
5. **Institution control (2 minutes):** permission-aware People/Learner,
   Content Studio and private Media Library governance.
6. **Future lesson automation (1 minute):** explain operator-reviewed audio
   analysis → Skill Chat → quiz → apply → review without claiming it is live.

### Close

Ask whether the buyer's first problem is learner confidence, content
operations, workforce scenarios, low-bandwidth access or measurable review.
Propose a bounded discovery/pilot plan only after the release and content gates
for that scope are explicit.

## 15. Buyer discovery questions

### Learners and outcomes

- Who are the adult learners, and what English situations matter most?
- What is their primary first language and literacy profile?
- Is the goal confidence, intelligibility, workplace performance, exam support
  or certification?
- Which outcomes can be observed without making a fluency guarantee?

### Delivery

- Web/PWA, Android closed beta or both?
- What devices, connectivity constraints and shared-device risks exist?
- Is offline mission access required?
- Who provides learner support and account recovery?

### Curriculum and governance

- Who owns the source curriculum and media?
- Who will author, translate, independently review and publish?
- Can exact rights, transcript and accessibility evidence be supplied?
- How many levels/lessons are in the first reviewed pathway?

### AI, speech and privacy

- Is text-only acceptable for the first pilot?
- Which provider/data region is permitted?
- Is provider training on customer data prohibited under the intended terms?
- What cost/rate cap and deletion SLA are required?
- Which learner groups or contexts should never use voice?

### Institution operations

- How are organizations, branches, cohorts, trainers and learners rostered?
- Which roles require content, rights, audit or privacy authority?
- Are LMS/HR/SSO/reporting integrations required now or later?
- What retention, legal-hold and data-subject workflows apply?

## 16. Frequently asked questions

### “Can the system understand an uploaded audio lesson automatically?”

The future pipeline is designed to transcribe and analyze an uploaded audio
asset, propose its topic and learning blueprint, and draft grounded practice
and assessment. A human must review and publish the exact version. The current
Media Library manages the asset lifecycle, but automatic topic-to-chat-to-quiz
publication is planned rather than live.

### “What happens when we add more lessons or levels?”

The curriculum graph is data-driven and does not hard-code a fixed number of
levels. New governed lessons can be added through program/workspace revisions.
In the planned practice loop, each published lesson carries its own versioned
analysis, chat and assessment contract, so the learner experience can scale
without custom code per lesson.

### “Does AI grade speaking?”

No authoritative AI speaking grade is currently offered. The current daily
mission treats local speech as unassessed, and conversation feedback is
advisory. Future pronunciation diagnostics require explicit validation for
fairness, dialects and intelligibility before any high-stakes use.

### “Can trainers listen to every learner recording?”

No. The current operator directory and diagnostics intentionally exclude raw
recordings and transcripts. Any future sampled human-review workflow requires
explicit scope, consent, permission and retention controls.

### “Does it work without internet?”

Bounded mission bundles can be used offline after personal-device opt-in and
must synchronize for an authoritative result. Voice provider operations and
video are not offline functions.

### “Is it available in six languages?”

The shell has six locale catalogues, with Kannada as the primary design and
English as the approved fallback. Five non-English catalogues still await
independent review, and interface translation is not the same as complete
curriculum or speech coverage.

### “Can each customer have its own content?”

The architecture supports organization-owned governed programs, revisions,
media and publications. Production onboarding still requires exact tenant,
review, rights, hosting and operating acceptance.

### “Can we buy it now?”

The repository is pre-production and public release is not authorized. A
commercially approved, bounded pilot would first need its release gates,
content/media evidence, deployment, support, provider budget and legal/privacy
terms closed.

## 17. Claims guardrail

### Approved direction of claim

- “Designed for practical spoken-English confidence.”
- “Kannada-guided with a text-always practice route.”
- “Repository-implemented daily missions and governed operator workflows.”
- “AI coaching is advisory; official learning state remains deterministic.”
- “Private, consent-driven voice design with controlled deletion.”
- “Data-driven multi-level curriculum and immutable publication pins.”
- “Provider-neutral architecture designed for controlled AI/speech adoption.”

### Do not claim

- fluency in four weeks or any guaranteed time;
- guaranteed employment, promotion, interview success or salary outcome;
- accent elimination or accent as a defect;
- perfect recognition of Kannada-influenced English or every dialect;
- formal CEFR, examination or government certification;
- authoritative automated pronunciation scoring;
- unrestricted live AI or unlimited provider usage;
- complete six-language curriculum/speech support;
- teacher replacement;
- automatic rights, accessibility or academic approval;
- production readiness, deployment or public availability; or
- compliance certification that has not been independently granted.

## 18. Current limitations and roadmap

### Release-critical work still open

- complete independently reviewed pilot curriculum and production media;
- close the current Media Library PostgreSQL/full verification gap;
- independent security, privacy, tenancy, accessibility and language review;
- qualified legal and media-rights review;
- production object-store/scanner, alerting, backup/PITR and capacity evidence;
- physical Android device matrix, signed release artifact and distribution
  evidence; and
- exact validation-beta scope and thresholds.

### Planned lesson-practice core

- governed audio ingestion/transcription and analysis;
- operator review/edit/publish of the AI blueprint;
- lesson-grounded Skill Chat;
- 3–7 item assessment sets;
- applied speaking/response task;
- delayed review and deterministic progression;
- all-level authoring and learner delivery; and
- evaluation, observability, cost and safety gates.

### Later expansion candidates

- trainer-reviewed assignments and sampled evidence;
- validated pronunciation/intelligibility diagnostics;
- additional language packs and guidance voices;
- richer analytics and longitudinal progress;
- groups, facilitated sessions and activities;
- governed notices and moderated learning communities;
- LMS/HR/SSO integrations;
- typed organization configuration and manual entitlements;
- approved payment/billing integration; and
- separately evaluated deployment models, including on-premises where
  commercially and operationally justified.

Roadmap items are planning inputs, not contractual delivery dates.

## 19. Proposal checklist

Before a proposal or statement of work is approved, record:

- exact feature statuses used in the proposal;
- target organization(s), learner count, cohorts and operator roles;
- reviewed lesson/level scope and content owner;
- translation, academic, accessibility and rights reviewers;
- media inventory, hashes/source and allowed distribution;
- web/PWA/Android delivery boundary;
- AI/speech provider, data region, retention terms, rate and budget cap;
- text-only fallback and voice-consent/deletion workflow;
- hosting, tenancy, backup, support and incident ownership;
- pilot metrics, subgroup definitions and acceptance thresholds;
- exclusions, dependencies and roadmap statements;
- data return/deletion/exit process; and
- exact production/release approvals still required.

## 20. Source guides

- [Learner user guide](guides/LEARNER_USER_GUIDE.md)
- [Administrator and operator user guide](guides/ADMINISTRATOR_USER_GUIDE.md)
- [Lesson Practice Loop plan](LESSON_PRACTICE_LOOP_PLAN.md)
- [Current product status](STATUS.md)
- [Enterprise Product Master Plan](../ENTERPRISE_PRODUCT_MASTER_PLAN.md)
- [Release gates](ops/RELEASE_GATES.md)

The safest sales promise is an exact, evidence-backed scope. This product is
most compelling when its innovation and its controls are presented together:
private confidence practice for the learner, and governed authority for the
institution.

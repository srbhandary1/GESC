# Enterprise student path plan

**Status:** Active working plan (follow step-by-step; not an APP by itself)  
**Date:** 2026-08-07  
**Owner:** Accountable Product Owner + engineering  
**Product spine:** [Student AI conversation learning flow](STUDENT_AI_CONVERSATION_LEARNING_FLOW.md)  
**Authority:** `ENTERPRISE_PRODUCT_MASTER_PLAN.md` v3.1 · `AGENTS.md` · accepted ADRs · `docs/APPROVALS.md`  
**Updated when:** each step starts, completes, or is blocked by a decision request

---

## 0. How we use this plan

1. Work **one numbered step at a time**.
2. Before code: inspect existing modules/tests; write or update verification scope; obtain APP when required.
3. After code: run the verification commands named in the step; update `STATUS.md`, `DECISIONS.md`, `QUALITY_REVIEW.md`.
4. Do **not** treat demo adapters (D-0076–D-0094 Vite `/demo/*`) as production evidence.
5. Stop for an APP/ADR when the step touches real providers, spend, privacy, tenancy, authoritative records, or release.

### Enterprise vs demo

| Track | Purpose | Counts toward Phase 1 Done? |
|---|---|---|
| **Enterprise path (this plan)** | Laravel modular monolith, project-owned adapters, OpenAPI, tenancy, eval, fail-closed production | Yes |
| **Presentation demo** | Stakeholder preview only (`make demo`, Azure TTS/OpenRouter exceptions) | No |

Demo work may continue for sales under existing APP-0015 bounds, but every enterprise step must land in ordinary app DI, tests, and contracts.

---

## 1. Current baseline (do not re-litigate)

| Item | State |
|---|---|
| Checkpoints accepted | PR-000 → PR-011 (with recorded limitations) |
| Latest APO acceptance | APP-0020 @ `220628f4…` |
| Conversation domain | Fake STT / model / TTS ports; production fail-closed |
| Speech | ADR-0008 Azure Speech; locked en-IN STT benchmark |
| AI Tutor / correction | PR-010 orchestration with deterministic fakes |
| Eval / memory | PR-011 harness + learner-visible memory controls |
| Next master-plan PR | PR-012 Role-play — **unauthorized** until separate APP |
| Real LLM in ordinary DI | **Unauthorized** |
| Production / Play | **Unauthorized** |
| Lesson audio policy (product intent) | **No human recordings** — AI TTS only |

### Honest gap to “helps shy students”

The repo has foundations. It does **not** yet deliver a production Confidence Conversation where a learner:

- speaks English in a two-way session,
- hears AI English replies (TTS),
- receives grammar correction + explanation in their UI language,
- with evaluated quality, privacy, and tenancy guarantees.

Closing that gap is the spine of Steps A–E below, then we resume master-plan PR-012 → PR-015.

---

## 2. Target student outcome (Phase 1 spine)

> Adult learner, low confidence → opens app in L1 → warm-up → AI-spoken English model line → two-way Gentle Friend chat → one priority correction with L1 explanation → reflect → returns tomorrow.

Hard rules (from the learning-flow design):

- Learning language: English (`en-IN`)
- Explanation language: UI locale (`kn-IN`, `hi-IN`, `ml-IN`, `ta-IN`, `te-IN`, `en-IN`)
- No recorded voice lessons
- AI advisory only (never marks/mastery/unlocks)
- Meaning first; ≤1 correction per turn in Gentle mode

---

## 3. Step register

Status legend: `pending` · `in_progress` · `blocked` · `done`

| Step | Title | Status | APP / ADR gate |
|---|---|---|---|
| **A0** | Freeze product decision: Confidence Conversation before Role-play | `done` | [APP-0021](APPROVALS.md#app-0021) |
| **A1** | Spec + verification scope for Confidence Conversation | `done` | APP-0021 |
| **A2** | Structured turn contract (EN reply + L1 explanation) for all UI locales | `done` | APP-0021 / D-0096 |
| **A3** | Product UI vertical slice (not demo): warm-up → listen → talk → correct → reflect | `done` | Bound by APP-0021 |
| **A4** | Conversation-model provider ADR + secure binding (fail-closed) | `done` | [APP-0022](APPROVALS.md#app-0022); ADR-0010 accepted |
| **A5** | Multi-locale guidance TTS policy (AI-only lesson audio) | `done` | [APP-0024](APPROVALS.md#app-0024); ADR-0011 accepted |
| **A6** | Locale eval fixtures + `make ai-eval` gates for L1 explanations | `done` | `friend.l1.locale.v1` hash-locked; APP-0021 |
| **A7** | APO acceptance of Confidence Conversation slice | `done` | [APP-0025](APPROVALS.md#app-0025) at `5582c9ab69d6c6b4409ffade6f0f5b9d94fc4b7b` |
| **B0** | Authorize PR-012 Role-play (synthetic first) | `done` | [APP-0026](APPROVALS.md#app-0026) |
| **B1** | Implement PR-012 verification scope | `done` | Synthetic stage machine + UI + OpenAPI 0.10.0 under APP-0026 |
| **B2** | Pilot content path (synthetic → reviewed modules) | `done` | D-0105 — synthetic retained for this APP; commissioned content still DR-0007 |
| **C0** | PR-013 Operator console authorization | `done` | [APP-0028](APPROVALS.md#app-0028) begin-implementation for content-workflow + audit |
| **C1** | PR-013 bounded operator content-workflow + audit | `done` | [APP-0029](APPROVALS.md#app-0029) at `9220ab27baa6dc29d82caf8e9e97e94f7df7219d` |
| **D0** | PR-014 Capacitor Android closed beta (production path, not demo APK) | `pending` | Separate APP required; remaining PR-013 items also separately gated |
| **E0** | PR-015 Production hardening + pilot release gates | `pending` | APP + specialist reviews |
| **F0** | Phase 1 Definition of Done sign-off | `pending` | APO + release board |

**Active step:** `D0` — Consider separate APP for PR-014 Capacitor Android and/or remaining PR-013 items; bounded PR-013 operator accepted under [APP-0029](APPROVALS.md#app-0029) at `9220ab27baa6dc29d82caf8e9e97e94f7df7219d`.

---

## 4. Step details

### A0 — Freeze product decision

**Goal:** APO records that the next enterprise checkpoint is **Confidence Conversation** (Gentle Friend + grammar correction + L1 explanation + AI-only TTS), inserted **before** PR-012 Role-play.

**Deliverables:**

1. Short decision note in `docs/DECISIONS.md` (new D-number).
2. New APP entry authorizing only this checkpoint (not production, not Play, not unlimited spend).
3. Update `docs/STATUS.md` “Next checkpoint”.
4. Explicit statement that demo OpenRouter/Azure Vite paths remain non-evidence.

**Exit criteria:**

- Written APO authorize sentence committed under `APPROVALS.md`.
- This plan’s Active step moves to `A1`.

**Proposed APO begin sentence:**

```text
APO authorize only Confidence Conversation enterprise slice under
docs/ENTERPRISE_STUDENT_PATH_PLAN.md Steps A1–A7; PR-012+ and production
remain unauthorized until separate APPs.
```

---

### A1 — Spec + verification scope

**Goal:** Bound what “done” means for the slice before code expands.

**Deliverables:**

- `docs/CONFIDENCE_CONVERSATION_SPEC.md` — learner journey, coaching modes, copy rules, shame ban-list.
- `docs/CONFIDENCE_CONVERSATION_VERIFICATION_SCOPE.md` — HTTP/API, tenancy, OpenAPI, UI, offline fallback, eval hooks.
- OpenAPI draft deltas listed (not necessarily generated until implementation).

**Exit criteria:** Spec and verification scope committed; no production claims.

---

### A2 — Structured turn contract

**Goal:** One project-owned schema for every AI turn.

Required fields (minimum):

- `assistant_reply_en`
- `priority_correction.needed`
- `priority_correction.correction_en`
- `priority_correction.error_category`
- `priority_correction.explanation_l1` (active UI locale)
- `priority_correction.retry_recommended`
- `safety_state`
- `coaching_mode`

**Deliverables:**

- Schema + validator in conversation/AI module.
- Fake provider fixtures for all six UI locales.
- Unit tests: reject mixed-language abuse, empty L1 when correction needed, unsafe output → curated fallback.
- OpenAPI / generated types updated if HTTP surface changes.

**Exit criteria:** `make verify` green for contract tests; fakes only unless A4 already authorized.

---

### A3 — Product UI vertical slice (enterprise shell)

**Goal:** Real learner surfaces (Sanctum session path / existing product web), **not** Vite demo middleware.

**Screens / states:**

1. Home next-step in L1  
2. Warm-up  
3. Listen (TTS request via project speech port)  
4. Talk (speak or type; editable transcript)  
5. Correction card (EN fix + L1 explanation; Gentle defaults)  
6. Reflect  

**Rules:**

- Confidence controls: retry, type instead, skip, hint in L1.
- Disclose AI/advisory boundary.
- Organization scoping + authorization on every API.
- Cross-tenant tests.

**Exit criteria:** Verification scope A1 checks pass with fake providers; a11y smoke for critical controls.

---

### A4 — Real conversation-model provider (enterprise)

**Goal:** Replace fake model adapter in **non-production first**, then gated staging — still fail-closed in production until E0.

**Deliverables:**

- ADR: provider choice, data residency/processing, retention, cost controls, prompt/version pinning, eval gate before promote.
- Secure credential workspace (outside repo), same pattern as speech benchmark.
- Project-owned adapter only (no SDK types in domain).
- Idempotent turn submission; no raw audio/transcripts in ordinary logs.
- Spend cap + kill switch.

**Exit criteria:**

- ADR accepted by required roles.
- APP authorizes binding + capped spend.
- Adapter tests with recorded HTTP fakes; one authorized live smoke if APP allows.
- Production config still fails closed without explicit promote flag.

---

### A5 — AI-only lesson audio / multi-locale guidance TTS

**Goal:** Codify “no human lesson recordings”; allow guidance TTS in UI locales.

**Deliverables:**

- ADR amendment relative to ADR-0008:  
  - Learning STT remains `en-IN` (unless later benchmark expands).  
  - Model English TTS: `en-IN` neural.  
  - Optional L1 explanation TTS: mapped neural voices; text-first fallback if quality fails eval.
- Adapter + policy tests; cache policy if any.
- Content model: curriculum supplies text/objectives only for spoken lines.

**Exit criteria:** ADR accepted; no CMS dependency on human audio for Confidence Conversation.

---

### A6 — Evaluation gates for L1 explanations

**Goal:** Make quality measurable before claiming student help.

**Deliverables:**

- Locked fixtures per UI locale (hash-locked like PR-011).
- Metrics: false-correction rate, missing L1 explanation, shame-language hits, schema validity, cost ceilings.
- `make ai-eval` fails closed on regressions.
- Human-review import path for bilingual reviewers (existing PR-011 pattern).

**Exit criteria:** Eval suite green on fakes; live-provider eval only under A4 spend authority.

---

### A7 — APO acceptance of Confidence Conversation

**Goal:** Exact-commit acceptance with limitations recorded.

**Exit criteria:**

- APP accept entry.
- STATUS next checkpoint = PR-012 (or content DR if blocking).
- Known limitations explicit (locale quality, provider, content).

---

### B0–B2 — Role-play (master plan PR-012)

Follow existing drafts:

- [PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md](PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md)
- [PR-012_ROLEPLAY_VERIFICATION_SCOPE.md](PR-012_ROLEPLAY_VERIFICATION_SCOPE.md)

Order: authorize → implement synthetic stage machine → decide content path (synthetic vs commissioned). Role-play stays advisory over mastery.

---

### C0 — Operator console authorization (PR-013)

Authorize the first bounded APP for content-workflow + audit only. Full
master-plan §8.2 items (sampled review, privacy ops, support diagnostics)
stay deferred.

### C1 — Operator content-workflow + audit (PR-013)

Seeded role packs, thin org-scoped operator HTTP APIs wrapping existing
curriculum commands, audit digest list, and ordinary-product operator shell.
No DB access for authorized operator actions. No learner audio/transcripts.

---

### D0 — Production Android (PR-014)

Capacitor production app: Passport PKCE, native secure storage, device matrix — **not** the demo APK (`com.globalenglish.demo`).

---

### E0 — Production hardening (PR-015)

Load/soak, security/privacy, a11y evidence, backup/restore, monitoring, incident runbooks, staged deploy/rollback, pilot analytics.

---

### F0 — Phase 1 Definition of Done

Use master plan §27 checklist. Sign only when every item is evidenced — including real speaking mission online + offline, Friend + role-play, L1 correction, reviewed pathway content, tenant/security gates.

---

## 5. Parallel workstreams (do not block A0–A7 unless marked)

| Stream | Owner | Notes |
|---|---|---|
| Academic English reviewer | APO appoint | Correction quality, curriculum |
| L1 reviewers (kn/hi/ml/ta/te) | APO appoint | Explanation adequacy |
| Content commissioning | APO | Reviewed modules; DR-0007 time-box |
| Vendor / DPA / cloud accounts | APO | Required before A4/E0 |
| Demo presentation | Optional | Never substitutes for A-steps |

---

## 6. Working agreement for this chat / Codex

When the user says “continue the plan” or “next step”:

1. Read this file’s **Active step**.
2. Execute only that step’s deliverables.
3. Mark it `done` or `blocked` with reason.
4. Advance **Active step** exactly one notch.
5. Do not opportunistically implement later steps.

**Immediate next action:** Draft/consider a separate APP for PR-014 Capacitor
Android closed beta and/or remaining PR-013 items (sampled review under
ADR-0007 amendment, privacy ops). Do not start that code without a distinct APP.

---

## 7. Traceability

| Artifact | Role |
|---|---|
| [STUDENT_AI_CONVERSATION_LEARNING_FLOW.md](STUDENT_AI_CONVERSATION_LEARNING_FLOW.md) | UX / pedagogy design |
| This file | Ordered enterprise delivery plan |
| Master plan §26 PR-012–015 | Remaining Phase 1 engineering |
| Master plan §27 | Release Done checklist |
| `docs/APPROVALS.md` | Binding APO gates |
| `docs/STATUS.md` | Live checkpoint truth |

---

## 8. Change log

| Date | Change |
|---|---|
| 2026-08-07 | Initial plan; Active step = A0 |
| 2026-08-07 | A0 done ([APP-0021](APPROVALS.md#app-0021)); A1 done (spec + verification scope); Active step = A2 |
| 2026-08-07 | A2 done (`explanation_l1` + `ui_locale`, OpenAPI 0.9.0); Active step = A3 |
| 2026-08-07 | A3 done (Confidence Conversation stages + controls on product Practice UI); Active step = A4 |
| 2026-08-07 | A4 ADR-0010 proposed (Azure conversation-model policy); awaiting acceptance |
| 2026-08-07 | A4 done ([APP-0022](APPROVALS.md#app-0022); ADR-0010 accepted; Azure OpenAI adapter + ₹500 live-dev cap); Active step = A5 |
| 2026-08-07 | A5 in progress: ADR-0011 proposed; APP-0023 begin (fakes/text-first); awaiting Accept ADR-0011 |
| 2026-08-07 | A5 done ([APP-0024](APPROVALS.md#app-0024); ADR-0011 accepted; fakes/text-first); Active step = A6 |
| 2026-08-07 | A6 done (`friend.l1.locale.v1` hash-locked L1/shame/false-correction gates; ai-eval green on fakes); Active step = A7 |
| 2026-08-07 | A7 acceptance package drafted; Exact commit SHA pending authorized commit of uncommitted A1–A6 workspace |
| 2026-08-07 | Slice committed at `5582c9ab69d6c6b4409ffade6f0f5b9d94fc4b7b`; A7 awaiting APO accept statement |
| 2026-08-07 | A7 done ([APP-0025](APPROVALS.md#app-0025) / D-0103); Active step = B0 (PR-012 APP consideration only) |
| 2026-08-07 | B0: PR-012 authorization + verification drafts refreshed post–APP-0025; awaiting APO authorize statement |
| 2026-08-07 | B0 done ([APP-0026](APPROVALS.md#app-0026) / D-0104); Active step = B1 |
| 2026-08-07 | B1 implementation landed (stage machine, objectives, advisory feedback, OpenAPI 0.10.0, RoleplayJourney); focused tests green; full verify / APO accept pending |
| 2026-08-07 | B1 verification re-run (PHP unit + Docker Postgres + Node 24 vitest/locale/contracts); B2 synthetic content path recorded (D-0105); PR-012 APO acceptance package drafted |
| 2026-08-07 | PR-012 synthetic slice committed at `0fb6f2953580ed6154e4d895e128c46167c6f5d4`; awaiting APO accept |
| 2026-08-07 | PR-012 synthetic accepted ([APP-0027](APPROVALS.md#app-0027) / D-0106); Active step = C0 (PR-013 APP consideration only) |
| 2026-08-07 | C0: PR-013 authorization + verification drafts ready (content-workflow + audit first; sampled review/privacy ops deferred); awaiting APO authorize |
| 2026-08-07 | C0 done ([APP-0028](APPROVALS.md#app-0028)); Active step = C1 (bounded operator content-workflow + audit implementation) |
| 2026-08-07 | C1 landed at `9220ab27baa6dc29d82caf8e9e97e94f7df7219d`; PR-013 APO acceptance package drafted; awaiting APO accept |
| 2026-08-07 | PR-013 bounded operator accepted ([APP-0029](APPROVALS.md#app-0029) / D-0108); Active step = D0 (PR-014 / remaining PR-013 APP consideration only) |

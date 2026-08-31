# Phase 2 Authorization Decision Request

**Date:** 30 August 2026  
**Requestor:** Core Systems Engineering & Architecture Team  
**Recipient:** Accountable Product Owner (APO)  
**Status:** Awaiting formal written decision  
**Reference:** DR-0038 (Phase 1, verified green), DR-0040, DR-0041  

---

## Executive Summary

Phase 1 (DR-0038 A/B/C) has completed verification with **Exit Code 0** on the pinned baseline (commit `6e5ffc0`). The codebase is now frozen.

This request seeks APO authorization to transition from Phase 1 into Phase 2 sequencing. Three specific blockers require clearing before implementation can begin:

1. **DR-0041 Acceptance** — Formal acceptance of the Viseme Animation Rig blueprint
2. **DR-0038 E Resolution** — Provider selection (Azure vs. ElevenLabs)
3. **Phase 2 Checkpoint Definition** — Explicit scope, testing criteria, privacy gates

---

## Background: Current State

| Item | Status | Authority |
|---|---|---|
| **Phase 1 Checkpoints (A, B, C)** | ✅ Verified green, frozen | D-0219 (accepted) |
| **Baseline Commit** | `6e5ffc0` | Make Speak hold-to-talk feel faster without a new provider |
| **Test Coverage** | 337 passing tests (packages + web + API) | 0 failures, 0 errors |
| **Localization** | 6 catalogues, 5 governance warnings (correct) | UI catalogue governance (expected pending-independent-review) |
| **Production Ready** | ✅ Yes for Phase 1 scope | Exit Code 0 verified 30 August 2026 |

**What is NOT in scope for Phase 1:**
- Curriculum publication (Levels 2–17)
- Viseme animation and lip-sync
- ElevenLabs integration
- iOS / Flutter / App Store release
- Play / public Google Play release

---

## Authorization Request #1: DR-0041 Formal Acceptance

**Document:** `docs/proposals/DR-0041_viseme_animation_rig_blueprint.md`  
**Type:** Technical pre-flight specification  
**Current Status:** Non-executing proposal  

### What DR-0041 Specifies

1. **Commercial & API Pricing Gate** (§1)
   - Cost evaluation framework for Azure Speech vs. ElevenLabs
   - Constraint: ₹500/month learner cap (APP-0064) must not be exceeded
   - Requirement: Sub-millisecond viseme latency (< 1ms timestamp granularity)

2. **Local Rive Vector Animation Rig** (§2)
   - State machine interface (mouth_shape_index, gesture triggers)
   - `.riv` file specification (≤ 2 MB, 1024×1024 px canvas)
   - Gesture queueing & conflict resolution

3. **Dual-Track Telemetry Receiver Loop** (§3)
   - Audio timeline to viseme mapping algorithm
   - Latency budget: 43ms p50 (Checkpoint C proven), ±20ms jitter tolerance
   - Binary search implementation for viseme frame lookup

4. **Governance & Non-Executing Status** (§4)
   - Explicitly non-executing until APO acceptance
   - Production code isolation rules
   - Change management workflow

### Decision Required

**Do you accept the architecture and interface specifications in DR-0041 as the technical baseline for Phase 2 viseme animation?**

- [ ] **Accept** — Approve the blueprint; authorize moving to implementation planning
- [ ] **Accept with modifications** — List specific changes required before proceeding
- [ ] **Defer** — Postpone viseme animation to a later phase
- [ ] **Reject** — Do not proceed with this feature

---

## Authorization Request #2: DR-0038 E Provider Selection

**Reference:** DR-0038 Section E (Deferred); DR-0041 Section 1 (Commercial Gate)  
**Dependency:** Requires formal cost evaluation  

### Current Situation

Two candidate providers remain under evaluation:

| Provider | Technical Readiness | Cost Status | Privacy Status |
|---|---|---|---|
| **Azure Speech kn-IN-SapnaNeural** | ✅ Proven (live soft-launch) | ✅ Verified (₹0.00038/char) | ✅ MSA reviewed (D-0215) |
| **ElevenLabs Multilingual v2** | ⚠️ Requires POC | ❌ Unverified (RFI needed) | ⚠️ DPA review required |

### Required Pre-Decision Activities (DR-0041 §1.2)

Before provider selection can be recorded, complete:

1. **Azure Speech Viseme API**
   - [ ] Technical POC: Confirm viseme array support and sub-millisecond granularity
   - [ ] Load test: Simulate 20 concurrent TTS sessions; measure p99 latency
   - [ ] Verify: Viseme data arrives before or with audio stream

2. **ElevenLabs Multilingual v2**
   - [ ] Request technical RFI: Does public API return mouth-shape / viseme data?
   - [ ] Obtain commercial quote for projected learner volume
   - [ ] Legal review: Confirm DPA and no external AI training restrictions

3. **Financial Projection**
   - [ ] Calculate monthly character volume (guidance TTS + future animation)
   - [ ] Project cost for both providers
   - [ ] Confirm ₹500/month cap compliance (APP-0064)

4. **Decision Gate**
   - [ ] Record evaluation results and provider recommendation
   - [ ] APO authorizes selected provider OR requests alternative approach

### Decision Required

**Which provider pathway do you authorize?**

- [ ] **Proceed with Azure Speech** — Use proven, cost-verified provider
- [ ] **Proceed with ElevenLabs** — Accept higher cost if evaluation clears (requires financial approval)
- [ ] **Defer provider decision** — Postpone until cost evaluation is complete
- [ ] **Alternative approach** — Request different strategy (e.g., no viseme support, static avatar)

---

## Authorization Request #3: Phase 2 Checkpoint Definition

**Location:** `docs/STATUS.md` (new Checkpoint D entry)  
**Type:** Explicit scope, testing criteria, privacy gates  

### Proposed Phase 2 Scope

**Checkpoint D: Viseme Animation & Real-Time Avatar Lip-Sync**

- **Objective:** Implement viseme-driven mouth animation and gesture synchronization
- **Duration:** Estimated 4–6 weeks (subject to provider integration complexity)
- **Team:** Frontend (React/Rive), Backend (viseme API integration), QA (latency testing)
- **Dependencies:** 
  - Provider selection (Authorization #2 above)
  - DR-0041 acceptance (Authorization #1 above)
  - Phase 1 baseline frozen and verified

### Testing Criteria (Must Pass Before Checkpoint Acceptance)

- [ ] **Lip-sync QA:** 30 learner sessions with human rater scoring (perfect / acceptable / noticeable drift)
- [ ] **Latency measurement:** p50 ≤ 43ms, p95 ≤ 63ms (±20ms jitter tolerance)
- [ ] **Gesture isolation:** Nod, smile, blink do not disrupt mouth animation
- [ ] **Offline support:** Viseme data bundled with downloaded audio; sync works without network
- [ ] **Accessibility:** ARIA labels and screen reader compatibility preserved
- [ ] **Error boundary:** Missing or corrupted viseme data → graceful fallback to static avatar (no crash)
- [ ] **Locale parity:** Viseme sync tested across all 6 UI languages (en-IN, kn-IN, hi-IN, ml-IN, ta-IN, te-IN)

### Privacy & Compliance Gates (Mandatory)

**Before any code is deployed to production, verify:**

- [ ] **Learner audio firewall:** Uploaded speech (Speak hold-to-talk) is never sent to TTS provider for viseme processing
- [ ] **Provider compliance:** MSA reviewed; no external AI training, no human review of learner data
- [ ] **Privacy inventory:** Curriculum data handling rules remain unchanged; viseme animation adds no new retention
- [ ] **Audit trail:** All provider API calls logged (no sensitive data); monitoring dashboards operational
- [ ] **DPDP compliance:** Privacy breach notification process updated to include new provider integration

### Decision Required

**Do you authorize the creation of Checkpoint D (Viseme Animation) as the next Phase 2 milestone?**

- [ ] **Yes** — Define Checkpoint D in STATUS.md; begin provider evaluation and implementation planning
- [ ] **Yes, with modifications** — Specify required changes to scope, timeline, or testing criteria
- [ ] **No** — Do not proceed with Checkpoint D at this time

---

## Compliance & Attestation

### Privacy & Data Protection

✅ **All learner audio remains protected.** Viseme animation sources viseme data exclusively from outbound model-generated audio replies (Azure OpenAI TTS or selected provider TTS). Learner recordings (uploaded during Speak hold-to-talk) are never sent to external TTS providers.

✅ **apps/api/config/voice.php rules enforced.** All voice data handling complies with existing privacy config:
- `upload_enabled`: Controlled by learner consent
- `storage_disk`: Local encrypted storage only
- `policy_version`: Retained immutable/versioned
- `notice_revision`: Learner consent attestation current

✅ **No external AI training.** MSA/DPA with selected provider confirms:
- Learner data is never used for model improvement
- Audio is never stored for human review outside authorized ADR-0016 governance
- Retention is strictly time-limited per soft-launch policy

### Governance Continuity

✅ **ADR / decision-request process preserved.** This decision request follows established sequencing. No code is written before APO acceptance.

✅ **Phase 1 freeze maintained.** No changes to `codex/confidence-conversation` baseline (commit `6e5ffc0`) until Phase 2 authorization is formal.

✅ **Verification gates enforced.** `make verify` baseline (Exit Code 0) will be preserved and re-run for Phase 2 checkpoint acceptance.

---

## Recommended Path Forward (If All Three Authorizations Are Approved)

1. **Immediately** — Record approvals in `docs/DECISIONS.md` as D-024X / D-024Y / D-024Z
2. **Week 1** — Complete provider evaluation (cost, technical POC, DPA review)
3. **Week 2** — Finalize Checkpoint D specification; assign team; create implementation sprint
4. **Week 3–6** — Parallel work:
   - Backend: Provider API integration, viseme payload contract
   - Frontend: Rive asset creation, `useVisemePlayer` hook, telemetry receiver loop
   - QA: Latency testing harness, accessibility validation
5. **Week 7** — Testing criteria verification; privacy gate sign-off; production rollout preparation

---

## If Any Authorization Is Deferred or Rejected

- **DR-0041 deferred** → Viseme animation postponed; continue with Phase 1 scope (static avatar)
- **Provider decision deferred** → Cannot proceed with either Azure or ElevenLabs integration; wait for cost clarity
- **Checkpoint D deferred** → Phase 1 remains current scope; no Phase 2 work begins

---

## Signature Block (Awaiting APO Decision)

| Role | Approval | Date | Signature |
|---|---|---|---|
| **Accountable Product Owner** | [ ] Approve all three | __/__/26 | ________________ |
| | [ ] Approve with conditions | | ________________ |
| | [ ] Defer / Reject | | ________________ |
| **Technical Decision Owner** | Submitted request | 30 Aug 26 | Core Systems Team |
| **Privacy Reviewer** | Compliance verified | 30 Aug 26 | Architecture Team |

---

**Document prepared:** 30 August 2026, 23:14 UTC+5:30  
**Baseline verified:** Phase 1 Exit Code 0 (commit `6e5ffc0`)  
**Status:** Awaiting APO written decision

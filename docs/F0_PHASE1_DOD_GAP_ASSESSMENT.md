# F0 Phase 1 Definition of Done — gap assessment

**Status:** Pre-F0 evidence closure executed under
[APP-0034](APPROVALS.md#app-0034) / D-0114. Package:
[F0_APP0034_EVIDENCE_PACKAGE.md](F0_APP0034_EVIDENCE_PACKAGE.md). F0 Definition
of Done sign-off and Play public release remain unauthorized.  
**Plan step:** [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) F0  
**Master plan:** §27 Phase 1 definition of done  
**Evidence commit:** `21f577cb2b1ac032124f65d3774b46e45e665196`  
**Prior acceptance:** PR-015 bounded ops hardening [APP-0033](APPROVALS.md#app-0033) at
`5eca7515291ea571a7f697dcdc2da6197535f839`

This document governs APP-0034. Evidence closure is recorded; Exact-commit F0
DoD acceptance remains a later APO statement
([F0_PHASE1_DOD_CONSIDERATION_PACKAGE.md](F0_PHASE1_DOD_CONSIDERATION_PACKAGE.md)).

## Free-flow AI product decision

Enterprise free-flow speaking is **Practice → Confidence Conversation / Friend**.
Dashboard Phase 1 “AI Teacher” remains structured/non-generative (demo or later
FAQ) and is not the production free-flow path.

## §27 checklist — evidence matrix (APP-0034)

| §27 requirement | Posture | Notes |
|---|---|---|
| Adult learner signs in; first action understandable in Kannada | Partial | UI locales exist; independent kn-IN content/language review pending |
| Real speaking mission online + deterministic offline | Partial | Online/offline slices accepted; full pathway + UAT incomplete |
| Talk with AI Friend + bounded role-play | Partial | Product Confidence Conversation + synthetic Role-play; live providers non-prod only under APP-0022 |
| Prioritized correction + Kannada explanation | Partial | Enterprise slice accepted; live multi-locale TTS spend gated; human L1 review pending |
| Attempts/turns survive retry/duplicate/reconnect/deploy | Partial | Domain tests exist; full deploy/reconnect UAT not claimed |
| Can-do progress, review due, recent improvement | Partial | Learner progress APIs/UI exist; pilot UAT incomplete |
| One complete pathway: reviewed content, remediation, delayed review, unlock | **Blocked** (limitation) | DR-0007 resolved to retain synthetic with explicit F0 pathway incompleteness until commissioned reviewed content lands |
| Operators: enroll, publish, review, support, export, privacy requests (no DB) | Partial / Pass (ops slice) | Content-workflow + audit (APP-0029); support diagnostics + privacy export/erasure under [APP-0035](APPROVALS.md#app-0035); sampled speaking review still unauthorized |
| Kannada content reviewed, versioned, publishable, rollback-safe | Partial | Publish/rollback machinery exists; independent content review incomplete |
| AI bounded, evaluated, cost-controlled, private, non-authoritative | Partial | Eval harness + fake/default bindings; live-dev capped under APP-0022 |
| Learner memory visible and controllable | Partial | Memory APIs accepted; pilot UAT incomplete |
| Tenant isolation, authz, a11y, security, monitoring, restore, incident, support | Partial | Local load-soak + backup drill **Pass**; physical Android matrix open; specialist reviews unclaimed |
| Analytics distinguish usage vs demonstrated learning | Pass (skeleton) | Aggregate pilot analytics under APP-0033 |
| No marketing of unavailable fluency/scoring claims | Partial | Product copy governance; store listing not authorized |

## Critical blockers before any F0 accept statement

1. **Reviewed pathway content** — synthetic retained with recorded F0 limitation;
   commissioning budget/owner still required before pathway Pass.
2. **Remaining PR-013** privacy/support ops — implemented under APP-0035
   (sampled speaking review still unauthorized).
3. **Android physical device matrix** DM-01/DM-02 (+ credential negatives).
4. **Independent specialist reviews**.
5. **Production-like environment** — accepted password-only authentication,
   consented pilots, stable HTTPS, recovery and monitoring beyond harnesses.
6. **Google Play public listing** stays separately gated.

## Android mobile-test readiness

| Item | Ready? |
|---|---|
| Production package identity `com.globalenglish.app` | Yes (code) |
| System-browser PKCE + EncryptedSharedPreferences | Yes (code; APP-0031) |
| Local APK assemble evidence | **Pass** at `21f577cb…` (~5.8 MB debug APK) |
| Physical device matrix pass claimed | **No** |
| Play public listing | **No** (unauthorized) |
| Sideload smoke | Conditional — see [CLOSED_BETA_PILOT_RUNBOOK.md](CLOSED_BETA_PILOT_RUNBOOK.md) |

## Ops drills executed (local)

| Check | Result | Artifact |
|---|---|---|
| PH-06 load-soak | Pass | `artifacts/ops/load-soak-20260807T161805Z.json` |
| PH-07 backup/restore | Pass | `artifacts/ops/backup-restore-20260807T161800Z.json` |

## APP-0034 completion posture

Evidence-closure deliverables under APP-0034 are **recorded**. Privacy/support
ops accepted under APP-0036; DR-0007 synthetic limitation under APP-0037.
`assembleDebug` re-verified at `22815bd…`. F0 Exact-commit accept remains
**unauthorized**. Next gates: physical DM-01/DM-02, specialist reviews
([F0_SPECIALIST_REVIEW_PACKET.md](F0_SPECIALIST_REVIEW_PACKET.md)), then
Option A/B in [F0_PHASE1_DOD_CONSIDERATION_PACKAGE.md](F0_PHASE1_DOD_CONSIDERATION_PACKAGE.md).

The ordered 12 August 2026 current-state view—including the rebuilt current-
hostname APK, provider/voice decision stops and payload-bound L1 review gate—is
maintained in the
[production readiness closure matrix](PRODUCTION_READINESS_CLOSURE_MATRIX.md).

# F0 Phase 1 Definition of Done — gap assessment

**Status:** Draft for APO / release-board consideration only.  
**Plan step:** [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) F0  
**Master plan:** §27 Phase 1 definition of done  
**Prior acceptance:** PR-015 bounded ops hardening [APP-0033](APPROVALS.md#app-0033) at
`5eca7515291ea571a7f697dcdc2da6197535f839`

This document is **not** F0 sign-off and **not** implementation authorization.
F0 is a release-board evidence gate: every §27 item must already be evidenced
(or explicitly deferred by a new APP). Codex records only; never claims DoD.

## Purpose

Show what still blocks Phase 1 Definition of Done so the Accountable Product
Owner can choose:

1. authorize a **bounded pre-F0 evidence closure** APP (recommended), or
2. defer F0 until blockers clear, or
3. (not recommended) attempt F0 sign-off prematurely.

## §27 checklist — current evidence posture

| §27 requirement | Posture | Notes |
|---|---|---|
| Adult learner signs in; first action understandable in Kannada | Partial | UI locales exist; independent kn-IN content/language review still pending |
| Real speaking mission online + deterministic offline | Partial | Online/offline slices accepted earlier; full pathway + UAT evidence incomplete |
| Talk with AI Friend + bounded role-play | Partial | Confidence Conversation + synthetic Role-play accepted; live providers gated; synthetic content only for role-play |
| Prioritized correction + Kannada explanation | Partial | Enterprise slice accepted; live multi-locale TTS spend still gated; human L1 review pending |
| Attempts/turns survive retry/duplicate/reconnect/deploy | Partial | Domain tests exist; full deploy/reconnect UAT not claimed |
| Can-do progress, review due, recent improvement | Partial | Learner progress APIs/UI exist; pilot UAT incomplete |
| One complete pathway: reviewed content, remediation, delayed review, unlock | **Blocked** | DR-0007 / commissioned reviewed curriculum still open; synthetic retained |
| Operators: enroll, publish, review, support, export, privacy requests (no DB) | **Blocked** | Bounded content-workflow + audit accepted; support diagnostics, privacy ops, PII export unauthorized (ADR-0007) |
| Kannada content reviewed, versioned, publishable, rollback-safe | Partial | Publish/rollback machinery exists; independent content review incomplete |
| AI bounded, evaluated, cost-controlled, private, non-authoritative | Partial | Eval harness + fake/default bindings; live spend capped under earlier APPs |
| Learner memory visible and controllable | Partial | Memory APIs accepted under PR-011; pilot UAT incomplete |
| Tenant isolation, authz, a11y, security, monitoring, restore, incident, support | Partial | Many gates have code/docs; physical Android matrix, `make verify`, live drills, specialist reviews unclaimed |
| Analytics distinguish usage vs demonstrated learning | Pass (skeleton) | Aggregate pilot analytics under APP-0033 |
| No marketing of unavailable fluency/scoring claims | Partial | Product copy governance; store listing not authorized |

## Critical blockers before any F0 accept statement

1. **Reviewed pathway content** (DR-0007) — specialist-commissioned modules.
2. **Remaining PR-013** — support diagnostics + privacy/account-deletion ops
   (ADR-0007 amendment + APP for sampled speaking review if required).
3. **Android closed-beta evidence** — physical device matrix, reproducible
   signed assemble, on-device credential negative checks (APP-0031/0033
   limitations).
4. **Independent specialist reviews** — security, privacy, tenancy,
   accessibility, language/content, legal.
5. **Production-like environment** — OTP cutover, real (consented) pilot
   learners, restore drill executed, monitoring wired — not just harnesses.
6. **Google Play** is **out of F0 scope as a public listing**; closed testing
   may be operationally useful but public release stays separately gated.

## Android mobile-test readiness (separate from F0)

| Item | Ready? |
|---|---|
| Production package identity `com.globalenglish.app` | Yes (code) |
| System-browser PKCE + EncryptedSharedPreferences | Yes (code; APP-0031) |
| Verified APK assemble claimed in governance | **No** (unclaimed) |
| Physical device matrix pass claimed | **No** |
| Play public listing | **No** (unauthorized) |
| Sideload smoke test possible after local `make android-closed-beta` | **Conditional** — needs JDK/SDK, `VITE_MOBILE_API_ORIGIN` reachable from phone, Passport public Android client, debug APK install |

See [PR-014_ANDROID_CLOSED_BETA_BUILD.md](PR-014_ANDROID_CLOSED_BETA_BUILD.md).

## Proposed next APP (bounded pre-F0)

Authorize only **evidence closure** required to make F0 decidable — not F0
sign-off itself:

1. Execute/record Android closed-beta assemble + device-matrix PH/AN gaps.
2. Execute load-soak + backup/restore drills against staging-like stack.
3. Close or explicitly APP-defer remaining PR-013 privacy/support items.
4. Produce a filled §27 evidence matrix with pass/fail/deferred per row.
5. Keep F0 Exact-commit accept as a **later** APO statement after that matrix
   is green or explicitly limited.

## Proposed APO begin-implementation statement (pre-F0 evidence APP)

Reply exactly:

```text
APO authorize only pre-F0 Phase 1 evidence-closure under
F0_PHASE1_DOD_GAP_ASSESSMENT.md; F0 Definition of Done sign-off and Play
public release remain unauthorized.
```

Interpretation for the recorder (Codex is never an approver):

- Authorize work to close evidence gaps listed above.
- Do **not** accept Phase 1 DoD, Play public release, or waive specialist
  reviews.
- Create a new APP, update STATUS/DECISIONS/QUALITY/plan, then execute only
  that evidence-closure scope.

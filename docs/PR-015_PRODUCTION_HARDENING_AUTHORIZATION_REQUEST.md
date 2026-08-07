# PR-015 production hardening authorization request

**Status:** Begin-implementation authorized under [APP-0032](APPROVALS.md#app-0032)
/ D-0112. Prior acceptance: PR-014 [APP-0031](APPROVALS.md#app-0031) at
`44b0e672e004cf3846cddabc456c21153fa4607e`.  
**Plan step:** [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) E0→E1  
**Master plan:** §26 PR-015 — Production hardening and pilot release; §20 ops;
§19 accessibility (evidence package only)  
**Verification:** [PR-015_PRODUCTION_HARDENING_VERIFICATION_SCOPE.md](PR-015_PRODUCTION_HARDENING_VERIFICATION_SCOPE.md)  
**Prior acceptance:** PR-014 closed-beta foundations [APP-0031](APPROVALS.md#app-0031) at
`44b0e672e004cf3846cddabc456c21153fa4607e`

This document is the governing authorization request for APP-0032. It is
**begin-implementation only** — not acceptance of completed PR-015 work.

## Purpose

Authorize one **bounded** PR-015 vertical slice so the product can evidence
pilot-ready **operations hardening** and close outstanding APP-0031 Android
closed-beta evidence obligations — without claiming Phase 1 Definition of Done
(F0), Google Play public release, or remaining PR-013 privacy/sampled-review
work.

Full master-plan PR-015 / §27 DoD remains larger than this first APP.

## Preconditions (satisfied or explicitly carried)

1. PR-014 closed-beta foundations accepted under APP-0031 with recorded
   limitations (physical device matrix, Gradle assemble, `make verify`
   unclaimed).
2. Bounded operator content-workflow + audit accepted under APP-0029.
3. Confidence Conversation, synthetic Role-play, fake-provider conversation
   and deterministic speaking slices already accepted under prior APPs.
4. Vendor / DPA / independent specialist reviews for production remain
   APO-owned parallel workstreams (plan §5) — this APP does not waive them.

## Proposed authorized scope (first APP)

Authorize only:

1. **Close APP-0031 evidence gaps** for production-path Android closed beta:
   physical device-matrix runs (AN-06–AN-08), reproducible signed closed-beta
   assemble (`make android-closed-beta` / documented signing), and recorded
   `make verify` (or Docker-pinned equivalent) evidence where feasible.
2. **Load / soak harness** against local or staging-like synthetic stack at
   documented 2× forecast pilot peak assumptions (master plan §20.1–20.2) —
   evidence recorded; not a production capacity guarantee.
3. **Backup / restore drill** runbook + one evidenced non-production restore
   rehearsal against RPO/RTO planning targets (≤15 min / ≤2 h).
4. **Monitoring / alerts skeleton** for health, auth failures and provider
   cost-ceiling signals (aggregate/safe metadata only) plus written
   incident / support runbooks.
5. **Staged deploy + rollback rehearsal** for API/web (documented procedure +
   dry-run evidence); no production cutover.
6. **Accessibility evidence package** for already-accepted learner and
   operator critical paths (automated scans + manual checklist; no claim that
   automated scans alone satisfy production a11y).
7. **Pilot analytics skeleton** (aggregate usage vs demonstrated-learning
   distinction; digests / safe rollups only — no learner PII CSV).
8. STATUS / DECISIONS / QUALITY_REVIEW / plan updates for this APP.

## Explicit non-goals (remain unauthorized)

- Google Play **public** listing / open production release / full store
  compliance pack beyond closed-beta needs
- Phase 1 Definition of Done sign-off (plan **F0** / master plan §27)
- Remaining PR-013 items (sampled speaking review under ADR-0007, privacy
  request/account-erasure ops, learner PII CSV export, full §8.2 console)
- Production OTP provider cutover / real learner production data
- Expanding live Azure OpenAI / multi-locale TTS spend beyond existing APPs
- Waiver of independent legal, security, privacy, tenancy, accessibility,
  language/content or release specialist reviews
- Claiming production availability / latency SLOs as contractually met
- Promoting demo APK (`com.globalenglish.demo`) to production identity

## Proposed APO begin-implementation statement

Reply exactly:

```text
APO authorize only PR-015 bounded operations hardening and closed-beta evidence
closure under PR-015_PRODUCTION_HARDENING_AUTHORIZATION_REQUEST.md and
PR-015_PRODUCTION_HARDENING_VERIFICATION_SCOPE.md.
```

Recorded as [APP-0032](APPROVALS.md#app-0032) / D-0112. Active plan step = E1.
Bounded PR-015 implementation is authorized under this APP; exact-commit
acceptance remains a later APO gate.

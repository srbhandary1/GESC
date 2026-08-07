# F0 Phase 1 Definition of Done — consideration package

**Status:** Ready for APO **consideration only**. Not accepted.  
**Evidence package:** [F0_APP0034_EVIDENCE_PACKAGE.md](F0_APP0034_EVIDENCE_PACKAGE.md)  
**Gap assessment:** [F0_PHASE1_DOD_GAP_ASSESSMENT.md](F0_PHASE1_DOD_GAP_ASSESSMENT.md)  
**Evidence commit:** `21f577cb2b1ac032124f65d3774b46e45e665196`

## Recommendation

**Do not accept F0 DoD yet.** Critical blockers remain open:

1. DR-0007 reviewed pathway content
2. Remaining PR-013 privacy/support (authorization request drafted; not begun)
3. Physical Android device matrix (DM-01/DM-02)
4. Independent specialist reviews (security, privacy, tenancy, a11y, language, legal)
5. Production-like OTP cutover + consented pilot learners

## What APP-0034 closed

- Local Android `assembleDebug` evidence recorded
- Local load-soak (PH-06) and backup/restore (PH-07) executed and recorded
- §27 matrix filled with pass/partial/blocked/deferred
- PR-013 privacy/support explicitly APP-deferred with a draft authorize request

## Exact APO accept statement (use only after blockers clear)

```text
APO accept Phase 1 Definition of Done at Exact commit <FULL_SHA> with every
recorded limitation in F0_PHASE1_DOD_GAP_ASSESSMENT.md and
F0_APP0034_EVIDENCE_PACKAGE.md; Play public release remains unauthorized.
```

Until that Exact statement is recorded, Codex must not claim F0 DoD.

## Specialist review checklist (external)

| Review | Owner | Status |
|---|---|---|
| Security | Independent reviewer | Not claimed |
| Privacy / DPDP | Independent reviewer | Not claimed |
| Tenancy | Independent reviewer | Not claimed |
| Accessibility | Independent reviewer | Not claimed |
| Language / content | Independent reviewer | Not claimed |
| Legal | Qualified counsel | Not claimed |

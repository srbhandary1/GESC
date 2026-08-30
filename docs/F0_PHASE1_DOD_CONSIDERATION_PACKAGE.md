# F0 Phase 1 Definition of Done — consideration package

**Status:** Ready for APO **consideration only**. Not accepted.  
**Evidence package:** [F0_APP0034_EVIDENCE_PACKAGE.md](F0_APP0034_EVIDENCE_PACKAGE.md)  
**Gap assessment:** [F0_PHASE1_DOD_GAP_ASSESSMENT.md](F0_PHASE1_DOD_GAP_ASSESSMENT.md)  
**Specialist packet:** [F0_SPECIALIST_REVIEW_PACKET.md](F0_SPECIALIST_REVIEW_PACKET.md)  
**Evidence commit (APP-0034 drills):** `21f577cb2b1ac032124f65d3774b46e45e665196`  
**Current branch tip considered:** `22815bdfea835acfdd8eb4968743dd2b7ec52a7d`

## Recommendation

**Do not accept unrestricted F0 DoD yet.** Remaining hard blockers:

1. Physical Android device matrix DM-01/DM-02 (no device attached; AVD system
   image missing / no KVM on this workstation)
2. Independent specialist reviews (see specialist packet — not APO-substitutable)
3. Production-like password-only authentication/recovery + consented pilot learners
4. Reviewed pathway content commissioning (budget/owner still open; synthetic
   retained under D-0116 / APP-0037 with explicit F0 incompleteness)

## Closed since APP-0034 evidence package

| Item | Evidence |
|---|---|
| PR-013 privacy/support ops | [APP-0036](APPROVALS.md#app-0036) at `1357eb161d3754da3b8d6b888bd1e3b0a8698ff9` |
| DR-0007 synthetic limitation | [APP-0037](APPROVALS.md#app-0037) / D-0116 |
| Android `assembleDebug` re-verified | Pass at `22815bd…` (~5.8 MB debug APK; see device matrix note) |

## What APP-0034 closed

- Local Android `assembleDebug` evidence recorded
- Local load-soak (PH-06) and backup/restore (PH-07) executed and recorded
- §27 matrix filled with pass/partial/blocked/deferred

## Option A — wait for hard blockers (preferred)

```text
APO accept Phase 1 Definition of Done at Exact commit <FULL_SHA> with every
recorded limitation in F0_PHASE1_DOD_GAP_ASSESSMENT.md and
F0_APP0034_EVIDENCE_PACKAGE.md; Play public release remains unauthorized.
```

Use only after physical matrix + specialist APP evidence land.

## Option B — limited F0 accept (explicit remaining open items)

Only if the Accountable Product Owner chooses to accept Phase 1 DoD **with**
named open limitations (not a waiver of specialist reviews for production):

```text
APO accept Phase 1 Definition of Done at Exact commit 22815bdfea835acfdd8eb4968743dd2b7ec52a7d
with recorded limitations: physical Android DM-01/DM-02 open; independent
specialist reviews open; synthetic pathway incompleteness under D-0116;
production authentication/recovery and pilot cutover open; Play public release
remains unauthorized.
```

Until an Exact accept statement is recorded, Codex must not claim F0 DoD.

## Specialist review checklist (external)

| Review | Owner | Status |
|---|---|---|
| Security | Independent reviewer | Not claimed |
| Privacy / DPDP | Independent reviewer | Not claimed |
| Tenancy | Independent reviewer | Not claimed |
| Accessibility | Independent reviewer | Not claimed |
| Language / content | Independent reviewer | Not claimed |
| Legal | Qualified counsel | Not claimed |

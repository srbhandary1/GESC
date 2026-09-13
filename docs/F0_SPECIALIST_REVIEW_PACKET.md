# F0 Phase 1 — independent specialist review packet

**Status:** Ready for external reviewers. Not a completed review.  
**Product code under review:** Free-flow AI = Practice Confidence Conversation /
Friend (enterprise DI). Dashboard Phase 1 “AI Teacher” remains structured /
non-generative and is out of free-flow scope.  
**Baseline commit for this packet:** `22815bdfea835acfdd8eb4968743dd2b7ec52a7d`  
**Governing gap assessment:** [F0_PHASE1_DOD_GAP_ASSESSMENT.md](F0_PHASE1_DOD_GAP_ASSESSMENT.md)  
**Consideration package:** [F0_PHASE1_DOD_CONSIDERATION_PACKAGE.md](F0_PHASE1_DOD_CONSIDERATION_PACKAGE.md)

Codex and the Accountable Product Owner **cannot** satisfy these reviews.
Each row requires an independent human specialist before production claims.

## Review roster

| Review | Required independence | Primary artifacts | Status |
|---|---|---|---|
| Security | Independent security reviewer | Auth (Sanctum + Passport PKCE), Android secure store (ADR-0012), operator authz, problem details, logging redaction | Not claimed |
| Privacy / DPDP | Independent privacy reviewer / qualified counsel | ADR-0007, voice consent/retention/deletion, privacy export/erasure (APP-0036), no operator audio/transcript access | Not claimed |
| Tenancy | Independent tenancy reviewer | Organization scoping, cross-tenant 404 tests, curriculum share grants, operator privacy/support isolation | Not claimed |
| Accessibility | Independent a11y reviewer | Learner shell, operator console, localization fallback policy, keyboard/focus paths | Not claimed |
| Language / content | Independent language/content reviewers | kn-IN UI packs (pending independent review), synthetic pathway/Role-play limitation (D-0116), L1 explanation drafts; execute [the exact review runbook](L1_INDEPENDENT_REVIEW_RUNBOOK.md) | Mechanism ready; review not claimed |
| Legal | Qualified counsel | Consent notices, retention/deletion, store listing claims (Play still unauthorized) | Not claimed |

## Suggested review questions

1. Are authentication credentials confined to reviewed stores (cookie session /
   native EncryptedSharedPreferences) with no WebView/localStorage leakage?
2. Does ADR-0007 remain enforced for operator paths (diagnostics/export/erasure
   digests only — no transcript/audio bodies)?
3. Are all tenant-owned resources organization-scoped with fail-closed
   cross-tenant negatives?
4. Are Phase 1 UI locales honestly marked pending independent review where
   applicable?
5. Are synthetic Role-play / pathway fixtures disclosed so they cannot be
   marketed as specialist-reviewed curriculum?

## Explicit non-claims until reviews complete

- Production readiness
- Google Play public listing
- Waiver by APO alone of any specialist row above
- Physical Android DM-01/DM-02 matrix (separate evidence track)

## How to record a completed review

Append a new APP entry in [APPROVALS.md](APPROVALS.md) with:

1. reviewer identity and role;
2. Exact statement of findings (pass / pass-with-limitations / fail);
3. Exact repository revision reviewed;
4. open follow-ups, if any.

Do not mark a row Pass in this packet without that APP evidence.

---
status: proposal
date: 2026-08-30
deciders:
  - Accountable Product Owner (awaiting acceptance)
consulted: []
informed: []
amends_master_plan: false
approval_evidence: []
approval_reference: Formal proposal pending APO decision and sequencing after DR-0038 A/B/C verified closure.
---

# DR-0040: Curriculum Publication Framework — Levels 2–17

## Status

**Proposal** (not accepted). This document establishes the governance, audit, and compliance framework required to publish 17 authored curriculum levels to production. It is strictly a pre-decision planning artifact and does **not** authorize, implement, or change any production code or content delivery path until separately accepted via formal APO approval.

**Predecessor:** DR-0038 Phase 1 (accepted and verified, 30 August 2026). Publication remains out of scope per DR-0038 §9.3.

**Related:** ADR-0016 (Owned Institute Curriculum Audio Rights); D-0219 (Phase 1 acceptance); master plan §§3, 9, 16.

## Context

Global Spoken English delivers 17 levels (Levels 1–17; Pre-Levels 1–2 reserved for future localization work) of English practice content. All 67 teaching audio recordings and 17 lesson blueprints are author-owned, rights-verified via ADR-0016, and currently ingested from local object storage only (APP-0068 soft-launch ingestion path). 

Phase 1 (DR-0038 A/B/C, verified 30 August 2026) deferred curriculum publication to allow product stabilization on the daily loop (Home), progress tracking (Progress), and speech latency optimization (Speak). A future checkpoint must formally govern the publication readiness assessment and gate the transition from soft-launch-only to authorized multi-tenant delivery.

A previous proposal for `LegacyAudioSyncService` pulling from `globalspokenenglish.com` was explicitly rejected. Ingestion must remain within the rights-verified, local-storage-only boundary established under ADR-0016.

## Decision Requested

**This document proposes but does not request immediate acceptance.** When curriculum publication becomes a scheduled checkpoint, the Accountable Product Owner will decide whether to:

1. **Accept DR-0040** and sequence curriculum publication as a future Phase 2 or later checkpoint; or
2. **Defer** curriculum publication indefinitely and retain soft-launch-only scope.

Acceptance commits to the Content Audit Checklist (§3) and Rights Attestation Framework (§4) as prerequisites for the publication checkpoint's acceptance gate.

## Proposed Framework

### 1. Current Baseline & Storage Architecture

**Principle:** All production curriculum delivery relies on ADR-0016 rights-verified local object storage. No external sync, no legacy-site pull, no unapproved third-party ingestion.

| Component | Current State | Authority | Production Ready |
|---|---|---|---|
| Teaching audio (67 MP3s, 17 levels) | Local object storage (dated 29 August 2026) | ADR-0016 rights path | ✅ Yes |
| Lesson blueprints (17 levels, Kannada-reviewed) | Committed in `database/seeders/` | D-0219 (Phase 1 acceptance) | ✅ Yes |
| Localized vocabulary (6 locales) | Catalogued in `packages/localization` | UI catalogue governance | ⚠️ Pending independent review (non-English) |
| globalspokenenglish.com sync | **Permanently decommissioned** | Not authorized; ADR-0016 only | ❌ No |
| LegacyAudioSyncService | **Rejected** | Not recommended | ❌ No |

**Compliance Gate:** Before any production publication checkpoint is accepted, verify in writing that:
- No external sync code exists in the repository
- All audio ingestion uses authenticated local object store only
- ADR-0016 rights logs are attached to every asset

### 2. Content Audit Checklist

Publication requires every level to pass the following verifications. Each checklist must be digitally signed off by a designated reviewer and attached to the checkpoint acceptance evidence.

#### 2.1 Completeness Verification (Per Level)

- [ ] **Audio presence**: 4 audio files present (Introduction, Question, Coaching correction, Optional retry feedback) for the assigned level
- [ ] **Blueprint validity**: Lesson blueprint JSON is parseable and conforms to `LevelSessionBlueprint` contract
- [ ] **Vocabulary coverage**: All vocabulary keys referenced in the blueprint exist in at least the source locale (en-IN) catalogue
- [ ] **Objective mapping**: All learning objectives align with the generic level model (Levels 2–17 mapped to APP-0065/0066/0067 content; Pre-Levels 1–2 reserved)
- [ ] **MCQ integrity**: All multiple-choice quiz content has at least one correct answer and exactly three plausible distractors
- [ ] **Timing validation**: All audio durations and session timeout parameters fall within documented limits (§5.2 below)

#### 2.2 Localization Alignment Verification

- [ ] **6-locale catalogue parity**: All UI strings for the level are present across en-IN, kn-IN, hi-IN, ml-IN, ta-IN, te-IN catalogues
- [ ] **No orphaned keys**: No localization keys exist for this level that are not referenced in the blueprint or the canonical UI code
- [ ] **Kannada-reviewed**: Vocabulary and coaching corrections in kn-IN are verified by a named human reviewer (non-Codex) and signed off
- [ ] **Governance hash match**: Localization governance.json hashes match the current UI catalogue revision

#### 2.3 Automated Static Test Analysis

- [ ] **Contract compliance**: Run `pnpm run openapi:check` and verify the level's API contract passes without breaking changes
- [ ] **Typescript type safety**: All level data structures pass `pnpm run typecheck` with zero errors
- [ ] **Locale validation**: Run `pnpm run locale:check` and confirm no governance mismatches for this level's keys
- [ ] **Quiz constraint validation**: PostgreSQL constraint checks pass for all quiz objectives (automated in CI only; integration test coverage required)

### 3. Rights Attestation Framework

Publication requires a signed attestation log for every localized audio asset. This log must be retained durably and audited annually.

#### 3.1 Rights Attestation Record

For each audio file (MP3), create and commit a record in `docs/curriculum/rights-attestations/`:

**File format:** `{level_id}_{asset_type}_{locale}_ATTESTATION.md`

**Example:** `001_introduction_en-IN_ATTESTATION.md`

**Required fields:**

```markdown
---
asset_id: 001-introduction
asset_type: introduction_audio
locales: [en-IN, kn-IN, hi-IN, ml-IN, ta-IN, te-IN]
source_recording_date: 2025-11-15
source_recording_location: Global Spoken English studio, Bengaluru
rights_holder: Global Spoken English Institute (GSEI)
rights_verification_date: 2026-08-29
rights_verification_method: ADR-0016 audit trail
storage_hash_sha256: <computed at ingestion>
ingestion_date: 2026-08-29
ingestion_method: local object store upload
reviewed_by: [named human, not Codex]
approved_for_publication: false (default until checkpoint is accepted)
---

### Ownership Statement

[Name, title, date]: The audio file(s) referenced in this attestation are 
owned by [rights_holder] and have been verified to be free of third-party 
licensing restrictions, subject to the terms of ADR-0016 and the Global 
Spoken English Privacy Inventory revision dated [date].

### Training & Usage Restrictions

This audio is licensed for learner playback and speech-to-text transcription only.
It is **not** permitted to be used for:
- AI model training or fine-tuning
- Human speech quality review outside ADR-0016 governance
- Bulk export or re-distribution
- Derivative voice synthesis

Violations are reportable under the privacy incident protocol (DPDP rules).

### Locale-Specific Notes

[Per locale, record any adaptation, re-recording, or localization-specific concerns]
```

#### 3.2 Annual Audit

Once per calendar year, the Accountable Product Owner and a designated Privacy Reviewer must:

1. Verify all attestations are still on file and digitally signed
2. Confirm no unauthorized external ingestion has occurred
3. Review the privacy inventory revision to ensure no changes affect the attestations
4. Re-sign (with current date) or flag for re-verification if any input has changed
5. File the audit report in `docs/curriculum/rights-audits/{year}_audit_report.md`

### 4. Compliance Workflow

#### 4.1 Pre-Decision State (Current)

- ✅ DR-0040 is a proposal document only; it does **not** execute in any build, test, or deployment
- ✅ No code implementing this framework exists in the repository
- ✅ All Phase 1 (DR-0038 A/B/C) verified code is frozen and unmodified
- ✅ No content is published to production; soft-launch remains the only authorized delivery path

**Verification:** `make verify` and `make e2e` pass without any curriculum publication code or infrastructure.

#### 4.2 APO Acceptance Gate

For curriculum publication to proceed, the Accountable Product Owner must formally accept DR-0040 by:

1. **Recording acceptance** in `docs/DECISIONS.md` as a new decision (D-024X) that references this document
2. **Authorizing the checkpoint** (e.g., "Phase 2 – Curriculum Publication") as a distinct, sequenced item after all Phase 1 items are verified and closed
3. **Providing legal clearance** confirming that all 17 levels have been reviewed by the institute's legal counsel and comply with local educational regulations in all target locales
4. **Signing the Rights Framework adoption** (section 3 above), designating named reviewers and an audit cadence

#### 4.3 Checkpoint Implementation (Post-Acceptance)

Once accepted, the following work becomes in-scope:

1. **Content Audit Execution** (§2 above): Run all checklists for all 17 levels; resolve any failures
2. **Rights Attestation Completion** (§3 above): Create signed attestation records for every audio asset and locale
3. **Publication API & Delivery Path**: Implement (if needed) a production ingestion path that:
   - Uses only ADR-0016 approved local storage
   - Does **not** pull from external domains
   - Requires signed attestations for every asset before marking it `published=true`
4. **Soft-Launch to Production Cutover**: Update routing, DNS, or tenant configuration to make curriculum content live to paying institutions

#### 4.4 Post-Publication Compliance

After publication:

- All changes to curriculum content (audio re-recordings, blueprint updates, vocabulary corrections) require a new rights attestation and a checkpoint-level decision
- Annual rights audits (§3.2) are mandatory
- Any security incident affecting audio storage triggers an immediate audit and legal review
- Privacy inventory revisions are reviewed against curriculum assets before finalization

### 5. Technical Scope Boundaries

#### 5.1 What This Framework Does NOT Include

- ❌ Specification of the production ingestion API or storage backend (deferred to the checkpoint)
- ❌ Authorization of any external sync, CDN, or third-party delivery service
- ❌ Curriculum translation, localization of audio, or voiceover recording (out of scope; Phase 1 retained authored content only)
- ❌ Changes to the learner client, marking system, or mastery model (Phase 1 frozen)
- ❌ Play Store, App Store, or public distribution (out of scope; Phase 1 and forward)

#### 5.2 Technical Assumptions

- All audio files are <= 10 MB per file (soft-launch limit maintained)
- Blueprint structure matches current `LevelSessionBlueprint` contract (no schema changes without new ADR)
- Localization remains 6-locale parity (no new locales added without separate decision)
- Speech-to-text transcription is still via Azure Speech (ADR-0008, D-0215; not changed by this framework)
- Learner audio is **never** used for training AI models or human review outside ADR-0016 governance

### 6. Out of Scope (Deferred or Permanently Excluded)

- iOS, App Store, or multi-platform releases
- Curriculum versioning, A/B testing, or experimentation framework
- Teacher/institution customization of lesson content
- Integration with third-party LMS platforms
- Automated content generation or AI-assisted curriculum authoring

## Security, Privacy and Tenancy Impact

- **Privacy:** All curriculum audio remains subject to ADR-0016 rights and DPDP learner data rules. Audio is never used for external AI training. Annual audits verify compliance.
- **Tenancy:** Curriculum is authored once and licensed to all institutions. Publication does not introduce multi-tenant access control changes; existing org-scoping rules apply.
- **Governance:** Rights attestations are immutable audit records. Any breach or unauthorized re-use is reportable under privacy incident protocols.

## Approval Evidence (When Accepted)

- [ ] APO written acceptance and decision recording
- [ ] Legal clearance statement signed by institute counsel
- [ ] Named Rights Reviewer and Annual Audit Owner appointed
- [ ] All 17 levels pass Content Audit Checklist (§2)
- [ ] All audio assets have signed Rights Attestation Records (§3)
- [ ] `make verify` and `make e2e` pass with no curriculum publication code changes to Phase 1

## Consequences if Accepted

- Curriculum publication becomes a formal, gated checkpoint with mandatory compliance steps
- Local object storage becomes the canonical, permanently approved ingestion path
- ADR-0016 rights framework becomes a mandatory, annually audited control
- Any future curriculum changes (re-recordings, blueprint updates, new levels) require new attestations and checkpoint-level decisions
- Institute legal counsel is accountable for rights clearance and regulatory compliance per locale

## Consequences if Deferred or Rejected

- Phase 1 (DR-0038 A/B/C) remains the current production scope
- Soft-launch-only delivery continues indefinitely
- Curriculum publication is not a future milestone
- No compliance infrastructure for curriculum assets is required
- Existing ADR-0016 rights audit remains sufficient for soft-launch scope

## Follow-Up

If accepted, create a Phase 2 checkpoint in `docs/proposals/` with:
- Detailed implementation plan for the production delivery path
- API specification for curriculum asset ingestion
- Tenant and institution routing configuration
- Cutover procedure from soft-launch to multi-tenant production
- Monitoring and compliance dashboards (privacy, audit trail, error rates)

---

**Document Status:** Proposal. Not executable until formally accepted. No code, no implementation, no production changes authorized.

**Date Issued:** 30 August 2026  
**Last Reviewed:** 30 August 2026  
**Next Review:** On APO decision or 90 days, whichever is sooner.

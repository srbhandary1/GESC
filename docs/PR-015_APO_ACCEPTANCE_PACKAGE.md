# PR-015 APO acceptance package

This package prepares exact-artifact Accountable Product Owner acceptance of
master-plan §26 **PR-015 — Production hardening and pilot release** for the
**bounded operations hardening + closed-beta evidence closure** slice
authorized under [APP-0032](APPROVALS.md#app-0032). Acceptance is **pending** a
new APP record after the Exact human accept statement below.

## Artifact proposed for acceptance

| Field | Value |
|---|---|
| Checkpoint | PR-015 bounded ops hardening + closed-beta evidence closure (plan E0–E1) |
| Branch | `codex/confidence-conversation` |
| Governing begin-implementation | [APP-0032](APPROVALS.md#app-0032) |
| Acceptance record | Pending (next APP after Exact APO accept) |
| Authorization request | [PR-015_PRODUCTION_HARDENING_AUTHORIZATION_REQUEST.md](PR-015_PRODUCTION_HARDENING_AUTHORIZATION_REQUEST.md) |
| Verification scope | [PR-015_PRODUCTION_HARDENING_VERIFICATION_SCOPE.md](PR-015_PRODUCTION_HARDENING_VERIFICATION_SCOPE.md) |
| Quality evidence | [QUALITY_REVIEW.md](QUALITY_REVIEW.md) (PR-015 E0/E1 sections) |
| Exact commit | `5eca7515291ea571a7f697dcdc2da6197535f839` |

## Delivered behaviour

1. **E0** — APP-0032 begin-implementation for bounded PR-015 only.
2. Aggregate-only org-scoped **pilot analytics** (`GET /operator/pilot-analytics`)
   distinguishing usage vs demonstrated-learning rollups; no PII CSV; OpenAPI
   **0.12.0** + OperatorConsole section + localization keys.
3. Ops foundations: backup/restore drill docs+script, incident/support
   runbooks, alert stubs, staged deploy/rollback dry-run docs+script,
   load/soak harness script; Makefile targets.
4. Accessibility evidence package draft for accepted critical paths.
5. Device-matrix doc updated for APP-0032 evidence-closure authority.
6. Focused fail-closed regression coverage for pilot analytics.

## Verification evidence (recorded this slice)

| Area | Result |
|---|---|
| PHPUnit integration (Docker PHP 8.4.24 + pdo_pgsql) | `OperatorContentWorkflowTest` — **2 passed / 94 assertions** |
| Vitest (Docker Node 24.18.1) | `OperatorConsole.test.tsx` — **1 passed** |
| Locale validation (Docker Node 24) | Pass — 6 catalogues, 5 pending-review warnings |
| OpenAPI contracts generate | Pass — 0.12.0 |
| PH-01–PH-04 physical matrix / Gradle assemble | **Not claimed** |
| PH-05 `make verify` | **Not claimed** |
| PH-06 live load-soak against running API | Harness present; **execution not claimed** |
| PH-07 backup/restore drill execution | Script/doc present; **execution not claimed** this package |
| PH-08–PH-10 docs | Pass (runbooks + a11y package; manual a11y rows open) |
| PH-11 pilot analytics | Pass (code + focused tests) |
| PH-12 fail-closed | Pass (focused) |
| Play public / F0 | **Not authorized** |

## Recorded limitations (must accompany acceptance)

1. Accepts only the **bounded** PR-015 slice — not Phase 1 Definition of Done
   (F0 / master plan §27).
2. Google Play public listing / open production release remain unauthorized.
3. Physical device-matrix runs, signed Gradle assemble, and host `make verify`
   remain unclaimed follow-ups.
4. Live load/soak against a running API and executed backup/restore drill
   evidence were not claimed green in this package (harnesses/docs delivered).
5. Manual accessibility checklist rows remain open; automated scans alone are
   not production a11y sign-off.
6. Remaining PR-013 items (sampled speaking review under ADR-0007, privacy
   ops, PII CSV) remain unauthorized.
7. Production OTP / real learner production data and specialist-review waiver
   remain unauthorized.
8. Live Azure spend remains under existing APP gates only.
9. Demo APK remains presentation-only (D-0078).

## Proposed APO statement

Reply exactly:

```text
APO accept PR-015 bounded operations hardening and closed-beta evidence closure at 5eca7515291ea571a7f697dcdc2da6197535f839 with its recorded limitations
```

Interpretation for the recorder (Codex is never an approver):

- Accept the exact commit only, with every recorded limitation above.
- Close plan Steps E0–E1 for this bounded APP scope.
- Do **not** treat this as F0, Play public release, remaining PR-013, or real
  learner production authorization.
- Next checkpoint consideration is plan **F0** (Phase 1 DoD) and/or separate
  APPs for unclaimed PH evidence / remaining PR-013.

## Recorder checklist after APO statement

1. Exact commit SHA filled (`5eca7515291ea571a7f697dcdc2da6197535f839`).
2. Append next APP to [APPROVALS.md](APPROVALS.md).
3. Append decision row to [DECISIONS.md](DECISIONS.md).
4. Update [STATUS.md](STATUS.md) and
   [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md)
   (E1 accepted; Active = F0 consideration).
5. Append acceptance note to [QUALITY_REVIEW.md](QUALITY_REVIEW.md).
6. Mark this package Accepted with the new APP id.
7. Do **not** start F0 sign-off, Play public release, or remaining PR-013
   sampled review/privacy ops until a distinct APP authorizes that scope.

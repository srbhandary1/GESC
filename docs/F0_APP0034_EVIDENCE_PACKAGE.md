# APP-0034 pre-F0 evidence package

**Status:** Evidence closure executed under [APP-0034](APPROVALS.md#app-0034) /
D-0114. This is **not** F0 Phase 1 Definition of Done acceptance and does **not**
authorize Google Play public release.  
**Exact commit under test:** `21f577cb2b1ac032124f65d3774b46e45e665196`  
**Date:** 2026-08-07 (Asia/Kolkata)  
**Governing assessment:** [F0_PHASE1_DOD_GAP_ASSESSMENT.md](F0_PHASE1_DOD_GAP_ASSESSMENT.md)

## 1. Android closed-beta assemble

| Field | Value |
|---|---|
| Result | Pass (local workstation) |
| Package | `com.globalenglish.app` |
| Artifact | `apps/web/android-app/app/build/outputs/apk/debug/app-debug.apk` (~5.8 MB) |
| JDK | Temurin 21 (`~/.local/jdk/jdk-21.0.12+8`) |
| security-crypto | `1.1.0-alpha06` (`MasterKey`) |
| minSdk / targetSdk | 24 / 36 |
| Signing | Debug keystore only (not Play App Signing) |
| Physical device matrix | **Not claimed** — no DM-01/DM-02 physical pass |
| On-device credential negatives | **Not claimed** |

Device matrix log remains open in
[PR-014_ANDROID_DEVICE_MATRIX.md](PR-014_ANDROID_DEVICE_MATRIX.md). Emulator
substitute not used; hardware unavailable on this workstation (recorded
limitation).

## 2. Load-soak (PH-06)

| Field | Value |
|---|---|
| Result | Pass |
| Artifact | `artifacts/ops/load-soak-20260807T161805Z.json` |
| Base URL | `http://127.0.0.1:8000/api/v1` |
| Concurrency / requests | 50 / 200 |
| ok / fail | 200 / 0 |
| Elapsed | 633 ms |
| Commit | `21f577cb2b1ac032124f65d3774b46e45e665196` |
| Limitation | Health harness only — not a production latency/SLO contract |

## 3. Backup/restore drill (PH-07)

| Field | Value |
|---|---|
| Result | Pass |
| Artifact | `artifacts/ops/backup-restore-20260807T161800Z.json` |
| Environment | local Docker Compose Postgres |
| Dump / restore | 2026-08-07T16:18:01Z → 16:18:02Z |
| Measured RTO | 2 seconds (planning target ≤ 2 hours) |
| Dump size | 384617 bytes |
| Commit | `21f577cb2b1ac032124f65d3774b46e45e665196` |
| Limitation | Logical dump/restore only; WAL/PITR and production offsite retention not claimed |

## 4. Remaining PR-013 privacy/support

**APP-deferred** under APP-0034 scope item 4. Sampled speaking review, support
diagnostics, PII export, and account-erasure ops remain unauthorized until a
distinct APP under
[PR-013_PRIVACY_SUPPORT_AUTHORIZATION_REQUEST.md](PR-013_PRIVACY_SUPPORT_AUTHORIZATION_REQUEST.md).

## 5. §27 matrix

Filled in [F0_PHASE1_DOD_GAP_ASSESSMENT.md](F0_PHASE1_DOD_GAP_ASSESSMENT.md)
(pass / partial / blocked / deferred). Critical blockers remain: DR-0007
content, PR-013 privacy/support, physical Android matrix, specialist reviews,
production-like OTP/pilot cutover.

## 6. Explicit non-claims

- F0 Phase 1 Definition of Done
- Google Play public listing
- Production promote of live conversation-model / speech DI
- Real learner production data
- Full `make verify` (not re-run as part of this package)

## Recommended next APO statements

1. Authorize remaining PR-013 privacy/support (exact statement in that request).
2. After blockers clear: Exact-commit F0 DoD accept (separate statement).
3. Play public release remains a later separate APP.

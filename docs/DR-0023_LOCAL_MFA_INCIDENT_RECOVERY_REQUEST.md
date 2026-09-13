# DR-0023 — Local MFA incident recovery for governed LPL-01 ingestion

**Status:** Resolved — Option A accepted under APP-0050 / D-0148 / ADR-0018

**Needed by:** LPL-01 acceptance of the four APP-0047 `lesson_audio` objects

**Prepared:** 10 August 2026

**Resolved:** 10 August 2026

## 1. Decision needed

Choose whether and how the local non-production project database may recover
from an operator-factor revocation that correctly placed the only privileged
synthetic account into `support_required`.

This is an authentication-recovery and authoritative-record decision. The
current MFA policy intentionally prohibits SMS/primary OTP from resetting a
confirmed factor, and no reviewed support-assisted reset exists. Codex may not
delete credential history, alter factor state, create another administrator or
use direct database/test authentication to continue ingestion.

## 2. Exact incident and current state

DR-0022 Option A was implemented, passed repository verification and bound one
verified phone identity to the exact existing Direct administrator. The kill
switch was removed after the successful one-shot command.

The first ordinary HTTP attempt then exposed a pre-existing integration gap:
the Direct learner provisioner rejected an already verified active staff
membership. The bounded correction now permits an existing verified active
Direct learner, trainer or organization administrator to authenticate without
changing its membership or role; unknown identities still receive only the
ordinary learner provisioning. Focused PostgreSQL service and HTTP tests pass.

The next ordinary OTP → selected membership → TOTP attempt succeeded. Its first
Introduction upload used the exact authorized bytes and completed all five
parts, but the API process had retained fail-closed `unavailable` media adapter
selectors. Finalization therefore created only upload
`01KZNE3E2HX1HKR0Y7MSXVMTVK` and scan
`01KZNE3E9HQZX9E64SSH6Q0SR7` in `inspection_failed` state. Both inspection and
malware outcomes were `unavailable`; no media asset, metadata, right,
attachment or publication was created.

The bounded runner then revoked its ephemeral TOTP factor to avoid leaving an
unrecoverable secret. That was incorrect operational cleanup: under accepted
DR-0013 Option A, factor revocation correctly invalidates recovery material,
revokes every session and leaves confirmed-factor history. The account now
reports `support_required`. A subsequent correctly configured ffprobe/ClamAV
attempt stopped at that precondition before TOTP enrollment or any media
mutation.

The database remains non-production. The exact validated pre-media-migration
backup is `/tmp/gse-lpl01-pre-media-migrations-20260810.dump`, 548,570 bytes,
SHA-256
`b9f3e4ddc75e4be48dfa278c1297ed49cf9f43e34db5092aad38e959c4808053`.
It predates migrations 70–160 and every LPL-01 identity/session/MFA/media write.

## 3. Options

| Option | Decision | Consequence |
|---|---|---|
| **A — Verified local restore and governed replay (recommended)** | Snapshot the locked incident state, prove that the post-backup delta contains no out-of-scope runtime record, restore the exact pre-LPL backup, rerun reviewed migrations/seed, re-execute the exact APP-0049 binding once in restored state, and repeat the ordinary HTTP path with reviewed adapter selectors and protected MFA handoff | Smallest non-production recovery; removes the failed local runtime history from the active database but preserves it in a validated incident snapshot and durable content-free documentation |
| **B — Implement support-assisted MFA reset first** | Design an append-only, high-assurance support reset lifecycle, authority model, API/CLI, audit evidence and recovery tests before ingestion | Reusable and production-shaped, but materially expands LPL-01 into global credential recovery and requires a separate architecture/security checkpoint |
| **C — Hold the account and LPL-01** | Keep `support_required` and make no further privileged mutation | Preserves current state exactly but leaves governed ingestion blocked |

## 4. Recommended Option A contract

If accepted exactly as written, Option A authorizes only this local recovery:

1. Stop the local API before recovery. Create and validate a second
   custom-format PostgreSQL snapshot of the current locked incident state,
   recording its byte count and SHA-256 outside secrets.
2. Produce a content-minimized database delta inventory against the known
   pre-LPL baseline. Continue only if every post-backup write is attributable
   to reviewed migrations/foundation seed, APP-0049 identity binding, local
   OTP/session/MFA evidence or the one failed media upload/scan. Any learner,
   curriculum, review, rights, publication or other unexplained business delta
   stops the restore.
3. Revalidate the exact original backup hash and `pg_restore --list`, restore
   only the named local non-production project database, then rerun reviewed
   migrations 70–160 and the idempotent FoundationSeeder. Validate the exact
   expected user, membership, organization and seeded administrator role
   before any identity action.
4. APP-0049 is extended only enough to re-execute the already implemented
   one-shot binding once after this whole-database restore, for the same exact
   user/membership, protected-input phone and approval reference. No second
   identity, role, permission, appointment or other account may be created.
5. Repeat sign-in only through ordinary local OTP, select the exact membership
   and enroll TOTP through the existing HTTP API. Raw phone, OTP, setup secret
   and recovery codes remain absent from Git, process arguments, chat output,
   logs, audit and durable evidence.
6. TOTP setup material and recovery codes may be held only in one randomly
   named, owner-only `0600` system-temporary handoff file for this synthetic
   non-production operator. Only its path and content-free lifecycle may be
   reported. The product owner must transfer it to reviewed authenticator and
   recovery-code custody before the file is securely removed. If the file is
   lost, stop; do not revoke or reset the factor automatically.
7. Do not revoke the factor as routine cleanup. Revoke only ordinary web
   sessions after bounded runs. Factor replacement, revocation or support reset
   remains governed by DR-0013 and cannot be inferred from this recovery.
8. Start the API with explicit `ffprobe` and `clamav` project-adapter selectors,
   verify their health, and perform every start/part/finalize/accept mutation
   through the current first-party HTTP API with fresh MFA and tenant-derived
   permission. Only the four APP-0047 hashes may be accepted.
9. The failed adapter attempt remains preserved in the incident snapshot and
   content-free repository evidence. It may not be represented as a clean
   scan or successful ingestion.
10. This recovery creates no rights approval. The current administrator still
    lacks `media.rights.approve` and a rights-confirmer appointment, so
    asset-bound rights revisions remain a separate stop condition after clean
    asset acceptance.
11. The restored database and all media remain private non-production state.
    No export, sharing, deployment, learner delivery, production/public
    release, SQL/test-auth bypass, LPL-02 implementation or production recovery
    precedent is authorized.

## 5. Security, privacy and tenancy impact

Option A rewinds a whole local non-production database to a validated backup;
it does not mutate selected credential rows in place. That still removes
authoritative incident history from the active database, so the pre-restore
snapshot, strict delta audit, exact target and explicit approval are mandatory.

The replay binds only the same verified identity to the same immutable Direct
administrator and then restores the normal OTP, membership, TOTP, permission,
quarantine and audit boundaries. No tenant is selected from untrusted input.
Sensitive credential material has one owner-only temporary handoff and no
ordinary observability or repository path.

This is not a support-reset design and cannot be used in production. A proper
audited recovery workflow remains future work.

## 6. Exact approval phrase

To accept the recommended recovery boundary, reply exactly:

```text
Approve DR-0023 Option A as written.
```

Any other response is a revision or rejection, not approval.

## 7. Resolution

Option A was accepted exactly as written on 10 August 2026 under APP-0050,
D-0148 and ADR-0018. The exact approved proposal, before its status, resolved
date and this resolution section were added, has SHA-256
`956d6d6e1513412ce662dbc70fb0f642f4f1e763626b158b76f37e70ad79b79e`.

LPL-01 may execute only the verified local restore and governed replay contract
above. The approval does not authorize direct credential mutation, a second
administrator, rights authority, production/public release or LPL-02.

## 8. Execution evidence

Option A completed on 10 August 2026 within its local non-production boundary.

- The locked incident database was preserved as a validated PostgreSQL custom
  dump containing 781,927 bytes with SHA-256
  `2f2b524f1913e40fc3e46be76fde58142c879098395e5e740b9452960f14fab0`.
- An isolated full-table count and deterministic row-fingerprint comparison
  against the pre-LPL baseline found only the expected migrations/seed,
  APP-0049 identity, OTP/session/MFA, failed-upload/scan, cleanup,
  idempotency, audit and outbox deltas. Pre-existing learner, enrollment,
  curriculum, review, publication, media-asset and rights records were
  unchanged.
- The original 548,570-byte backup was revalidated at SHA-256
  `b9f3e4ddc75e4be48dfa278c1297ed49cf9f43e34db5092aad38e959c4808053`,
  restored only to the named local project database, and followed by reviewed
  migrations 70–160 and the idempotent foundation seed.
- APP-0049 replay bound exactly one identity to user
  `01KZGE9QRTCQE3PQ3RN31AXQSE` and membership
  `01KZGE9QRTCQE3PQ3RN31AXQSF`; its persistent enable switch remains absent.
- Ordinary OTP, exact membership selection and TOTP completed. Factor
  `01kznfk6xhjm0k4gmrng69zr46` remains active. Setup and recovery material is
  held only in the owner-only `0600` temporary handoff authorized by this DR;
  its contents were not emitted, persisted in the repository or logged.
- A first restored HTTP attempt exposed Laravel development-server environment
  filtering and correctly failed closed as upload
  `01KZNFK6ZKECGF2ZCBAVMWX4V4` / scan
  `01KZNFK75WGEKQ854E84Q3K7RM`, with unavailable outcomes and no asset. The
  server was restarted with explicit no-reload environment pass-through; no
  adapter or policy was weakened.
- The corrected ordinary HTTP run accepted all four exact APP-0047 hashes as
  active `lesson_audio` assets. Every selected scan records `ffprobe`,
  `audio/mpeg`, the expected duration, `clamav-instream`, inspection `passed`
  and malware `clean` under the approved policy versions.
- The incident snapshot's six quarantine objects were deleted through the
  normal reconciliation worker in an isolated restored database. The active
  database's six failed-attempt and 25 accepted-upload quarantine objects were
  deleted by the same worker only after their scheduled due times. All 31
  active deletion receipts are complete with no retry, dead-letter,
  unavailable or overdue backlog; accepted private objects remain intact.

The active database contains no lesson-audio metadata, rights revision,
content attachment or publication. Runtime rights authority remains a
separate stop condition exactly as required by this approval.

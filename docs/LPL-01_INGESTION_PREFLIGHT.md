# LPL-01 governed-audio ingestion preflight

**Recorded:** 10 August 2026

**State:** DR-0024 private pilot authority, four exact asset-bound rights
confirmations, DR-0025 commissioning, human transcript reconciliation and
candidate cleanup complete

**Authority:** APP-0047 / APP-0048 / APP-0050 / APP-0051 / ADR-0016 /
ADR-0018 / ADR-0019 / APP-0052 / ADR-0020

## Exact source reacquisition

The authenticated legacy Level 1 Audio page was reopened read-only. Only its
four teaching-part URLs were read. The temporary credential-bearing fetch
script and cookie jar were deleted immediately after the bounded download.
No legacy record was created, edited or enabled, and no user, message, report,
Assignment audio or learner-originated object was accessed.

| Order | Part | Bytes | SHA-256 |
|---:|---|---:|---|
| 1 | Introduction | 4,968,408 | `6838818d74481ffe38e13948dea2954886dcc519ed927112b703f8e2064573aa` |
| 2 | Practice | 7,357,881 | `75909e9baf340356d7ecf66435977ee1cce1fe93a597da58a24b5b9ea1833dd7` |
| 3 | Conversation | 2,157,215 | `8124b3bc6f71b65258358b0121cc3d72f1543948ca3ef3a1bb419f1fa0d65c70` |
| 4 | Interaction | 4,276,686 | `4862e927078ecb0ea56b7240f6d5b52356365115a6512836357bf340fdcf895e` |

Every byte count and hash matched APP-0047. A mismatch would have discarded
the complete candidate set.

## Real project-adapter evidence

The ffprobe adapter used a local static FFmpeg 8.1.2 build from the official
signed release. Its binary SHA-256 is
`621aa69f44dd12fa2e51039e4315e8efe6886fd65307051e0351db83a4b96f31`;
network protocols and external dependency autodetection were disabled.

The ClamAV adapter used official image
`clamav/clamav:1.5.4_base@sha256:a3c78b2f808ea797f842c6bca1c5f0a54a3d1182ab2efac1884510f667e44f56`
over workstation loopback. At the preflight it reported engine 1.5.4 and
signature database 28088 dated 10 August 2026. A benign fixture returned clean
and the harmless EICAR test signature returned infected before source scans.

| Part | Inspection | Detected MIME | Duration ms | Malware |
|---|---|---|---:|---|
| Introduction | passed | `audio/mpeg` | 310,526 | clean |
| Practice | passed | `audio/mpeg` | 459,868 | clean |
| Conversation | passed | `audio/mpeg` | 134,826 | clean |
| Interaction | passed | `audio/mpeg` | 267,293 | clean |

The application policy versions were `admin-media-inspection-v1` and
`admin-media-malware-v1`. These are preflight results only; the Media Library
must recompute and persist evidence over its own exact quarantine object.

## Local database preparation

Before migrations, a PostgreSQL custom-format dump was validated with
`pg_restore --list`. It contained 548,570 bytes and had SHA-256
`b9f3e4ddc75e4be48dfa278c1297ed49cf9f43e34db5092aad38e959c4808053`.
Reviewed migrations 70 through 160 then applied successfully, including the
ADMIN-MEDIA schema and additive `lesson_audio` constraints. The idempotent
FoundationSeeder completed afterward.

That first preparation was later rewound under DR-0023's exact validated
restore contract. Migrations 70–160 and the foundation seed completed again
after restore before the exact APP-0049 identity replay.

## Incident recovery and governed ingestion

DR-0022 Option A and APP-0049 bound one verified identity to the exact local
Direct administrator. The bounded staff-sign-in correction now permits that
existing verified active administrator to use ordinary OTP without learner or
role mutation; focused PostgreSQL service/HTTP coverage passes.

The original failed attempt and resulting MFA lock were preserved in a
781,927-byte incident snapshot with SHA-256
`2f2b524f1913e40fc3e46be76fde58142c879098395e5e740b9452960f14fab0`.
An isolated count/full-row-fingerprint audit found only expected LPL runtime
deltas and no changed pre-existing learner, curriculum, review, publication,
media-asset or rights state. DR-0023 Option A then restored the exact pinned
baseline, reran migrations/seed and replayed only APP-0049.

Ordinary OTP, exact membership selection and TOTP were repeated. The retained
factor remains active and its setup/recovery material remains only in the
owner-only temporary handoff. A first restored request again failed closed
because Laravel's reloading development-server child filtered the explicit
adapter environment; upload `01KZNFK6ZKECGF2ZCBAVMWX4V4` and scan
`01KZNFK75WGEKQ854E84Q3K7RM` have unavailable outcomes and no asset. Restarting
the same server with `--no-reload` passed the reviewed adapter selectors to the
HTTP worker without changing application policy.

The corrected first-party HTTP lifecycle then persisted this exact evidence:

| Part | Media asset | Selected scan | SHA-256 | Bytes | Duration ms |
|---|---|---|---|---:|---:|
| Introduction | `01KZNFYHCJSDWSPK08ZAC7G3AJ` | `01KZNFYHT1XZ5SB2B0S6Z3C863` | `6838818d74481ffe38e13948dea2954886dcc519ed927112b703f8e2064573aa` | 4,968,408 | 310,526 |
| Practice | `01KZNFYJ1M911SB8YVH9P4EZZ0` | `01KZNFYJM0WCFVHN41FWW65Y4F` | `75909e9baf340356d7ecf66435977ee1cce1fe93a597da58a24b5b9ea1833dd7` | 7,357,881 | 459,868 |
| Conversation | `01KZNFYJVSZRPZCEPTTQ4QTMNT` | `01KZNFYK2B9E9JX770G810D9CN` | `8124b3bc6f71b65258358b0121cc3d72f1543948ca3ef3a1bb419f1fa0d65c70` | 2,157,215 | 134,826 |
| Interaction | `01KZNFYK75V9WF8R5VCWRRZHNN` | `01KZNFYKJACNCGNRFHFFX1HAVC` | `4862e927078ecb0ea56b7240f6d5b52356365115a6512836357bf340fdcf895e` | 4,276,686 | 267,293 |

All four assets are organization-scoped, active lifecycle version 1,
`lesson_audio`, `audio/mpeg`, inspection `passed` by `ffprobe` and malware
`clean` by `clamav-instream`. Independent persisted-state queries found zero
lesson-audio metadata revisions, rights revisions and content attachments.
The incident snapshot's six queued quarantine objects were reconciled through
the normal worker in its isolated restored database. After their scheduled due
times, the active database's six failed-attempt and 25 accepted-upload
quarantine objects were also deleted through that worker. All 31 active
receipts are complete with no retry, dead-letter, unavailable or overdue
backlog, and all four accepted-object digests were independently re-read and
matched.

## Commissioning completion and current stop

DR-0024 Option A established standalone private pilot program
`01KZNK3BWD8A8NJ5A5JW0ZVZMT`, the exact least-privilege role and accepted
program-scoped rights-confirmer appointment. Ordinary selected-membership and
fresh-TOTP HTTP commands created one approved initial APP-0047 rights revision
for each exact asset. Their IDs, in source order, are
`01KZNKNFWGSRDBD57H18YCDPG8`, `01KZNKNFXKFRSVXRKPYWHDCF7C`,
`01KZNKNFYDDC31CV2BR155DXVR` and `01KZNKNFZ7A5Z9JPSC2AV0QPZQ`.

DR-0025 Option A was accepted under APP-0052 / D-0153 / ADR-0020 and its
bounded direct-MP3 Azure campaign later completed with four successful
attempts, zero failures/retries, 18,760,190 uploaded bytes, 1,172,513 billable
milliseconds and ₹11.189394 estimated usage. Under D-0191 the authorized
operator listened to every complete exact asset, inspected every returned
segment and confirmed all four candidates into English metadata revisions.
The scheduled retention worker completed all four encrypted-candidate
deletions on the first attempt, with no retry/dead-letter backlog, and campaign
`01KZP5129TNAQPE542XM15604C` became `completed` at 14 August 2026 05:35:20
UTC.

No attachment, curriculum version, academic/Kannada review, publication,
enrollment or learner delivery was created by commissioning or reconciliation.
LPL-01 now stops at the typed source snapshot, ordered blueprint and specialist
review boundary rather than provider or transcript availability.

Source copies remain restricted temporary non-production inputs until the
approved transcription/commissioning sequence completes or is abandoned.
No source URL, cookie, credential, raw audio or transcript is preserved in
this repository.

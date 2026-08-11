# DR-0024 — LPL-01 pilot program and initial rights authority

**Status:** Resolved — Option A accepted under APP-0051 / D-0150 / ADR-0019

**Needed by:** LPL-01 asset-bound rights and transcript commissioning

**Prepared:** 10 August 2026

**Resolved:** 10 August 2026

## 1. Decision needed

Choose the canonical owner program and the narrow initial authority path for
the four clean APP-0047 `lesson_audio` assets.

DR-0023 is complete: all four exact objects are active private assets with
persisted ffprobe/ClamAV evidence. APP-0047 is exact owner authorization for
non-production transcription, AI-assisted derivative drafting and possible
later web/Android delivery in India. It deliberately does not manufacture a
runtime rights revision, permission or appointment.

The current Direct administrator can upload, accept and manage media and can
manage authority appointments, but it does not hold `curriculum.author` or
`media.rights.approve`. No role in the Direct organization grants the latter,
and ordinary role administration correctly prohibits delegating a permission
the actor does not already hold. There is no accepted `rights_confirmer`
appointment.

The database also has no unambiguous LPL owner program. Its candidates are one
synthetic foundation preview and two duplicate immutable staging-smoke legacy
imports. Treating any of them as the canonical future LPL pilot without a
decision would turn fixture/history data into authoritative product ownership.

## 2. Exact affected records

The only candidate media in scope is:

| Part | Media asset | SHA-256 |
|---|---|---|
| Introduction | `01KZNFYHCJSDWSPK08ZAC7G3AJ` | `6838818d74481ffe38e13948dea2954886dcc519ed927112b703f8e2064573aa` |
| Practice | `01KZNFYJ1M911SB8YVH9P4EZZ0` | `75909e9baf340356d7ecf66435977ee1cce1fe93a597da58a24b5b9ea1833dd7` |
| Conversation | `01KZNFYJVSZRPZCEPTTQ4QTMNT` | `8124b3bc6f71b65258358b0121cc3d72f1543948ca3ef3a1bb419f1fa0d65c70` |
| Interaction | `01KZNFYK75V9WF8R5VCWRRZHNN` | `4862e927078ecb0ea56b7240f6d5b52356365115a6512836357bf340fdcf895e` |

The exact current actor remains user `01KZGE9QRTCQE3PQ3RN31AXQSE`, membership
`01KZGE9QRTCQE3PQ3RN31AXQSF`, in Direct organization
`01KYS97G7MX374P303XJHDXT72`.

## 3. Options

| Option | Decision | Consequence |
|---|---|---|
| **A — Dedicated local LPL pilot and bounded authority bootstrap (recommended)** | Create one exact private non-production pilot program and use one disabled local-only, approval-bound bootstrap to add an organization-owned role with only `curriculum.author` and `media.rights.approve`, assign it only to the exact existing membership, and create one program-scoped self-appointment through the existing authority service; accept the appointment and confirm all four rights revisions through ordinary fresh-TOTP HTTP APIs | Establishes explicit, revocable and program-bounded authority without modifying the system organization-admin role, blessing staging-smoke fixtures, adding another identity or weakening rights predicates |
| **B — Implement general initial-authority onboarding first** | Design platform custody, first-rights-grant administration and staff-wide appointment APIs before LPL commissioning | Better production-shaped onboarding, but materially expands LPL-01 into global authority administration and requires a separate security/tenancy product slice |
| **C — Hold LPL-01** | Keep the clean assets private and create no program, grant, appointment, rights record or provider job | Preserves current state but leaves transcription and blueprint work blocked |

The duplicate staging-smoke programs are not offered as a shortcut. Reusing
one would bind new commissioning rights to immutable smoke-history ownership
and leave the future canonical program unresolved.

## 4. Recommended Option A contract

If accepted exactly as written, Option A authorizes only this local
non-production setup:

1. Create exactly one active private program with stable key
   `global-spoken-english-lpl-pilot` and internal name
   `Global Spoken English LPL Pilot`. Create no publication, curriculum head,
   enrollment, learner pin, public route or delivery entitlement.
2. Add exactly one organization-owned non-system role with stable key
   `lpl-pilot-commissioner`. Its complete permission set is
   `curriculum.author` and `media.rights.approve`. Do not alter the system
   organization-administrator or trainer role packs.
3. Assign that role only to membership
   `01KZGE9QRTCQE3PQ3RN31AXQSF`. Add no user, identity, membership type,
   second administrator, platform capability or cross-tenant grant.
4. Implement the setup as a disabled-by-default, local-environment-only,
   approval-bound project command/service. It must validate APP-0047 and the
   eventual approval for this DR, exact tenant/user/membership IDs, the four
   exact active clean asset hashes, absence or exact idempotent equivalence of
   the program/role/assignment/appointment, and the persistent kill switch.
   It must use transactions, locks, idempotency and content-free audit/outbox
   evidence. Direct SQL, seed-time assignment and test authentication remain
   prohibited.
5. Use the existing consolidated self-appointment authority from APP-0004 only
   for this exact human-controlled account. Create one `rights_confirmer`
   appointment scoped only to the new pilot program, with APP-0047 as the
   owner/qualification evidence. The appointment must still be explicitly
   accepted by its appointee through the ordinary first-party web API with
   selected membership and fresh TOTP.
6. Remove/disable the bootstrap switch immediately after its one successful
   execution. The role assignment and accepted appointment remain visible,
   revocable authoritative records; they are not temporary credentials and
   must not be silently deleted after use.
7. Confirm one initial rights revision for each of the four exact assets only
   through the existing Media Library HTTP command, with the new program,
   exact selected asset hash, APP-0047 evidence hash/reference, derivative use
   allowed, learner delivery allowed only for later gated web/Android delivery
   in `IN`, sharing false, no expiry unless the owner supplies one, and the
   current approved retention/deletion policy keys.
8. A rights revision authorizes bounded processing; it does not attach media,
   approve a transcript, answer, English/Kannada content or publication. No
   learner delivery occurs until the later exact content, accessibility,
   English, Kannada, publication and release gates pass.
9. Provider transcription remains a later action in LPL-01 and still requires
   an explicit finite run ceiling, kill switch, no-training/minimum-retention
   configuration, project-owned adapter, immutable provenance and human
   reconciliation. This decision alone makes no provider request.
10. All assets/program/authority stay private and non-production. No sharing,
    deployment, production/public release, LPL-02 runtime implementation or
    general production initial-authority precedent is authorized.

## 5. Security, privacy and tenancy impact

Option A closes a local first-authority bootstrap gap without broadening every
organization administrator. The extra permission is carried by one explicit
organization-owned role on one exact membership; actual rights exercise still
requires the same-program accepted appointment, fresh TOTP, exact clean asset,
transaction, idempotency, audit and outbox controls.

The actor and assets are all in the immutable Direct organization. Request
payloads cannot choose another tenant. No learner data, raw media, transcript,
credential or secret enters the bootstrap evidence.

The consolidated account is acceptable only because APP-0004/APP-0047 identify
the accountable owner as the retained rights confirmer for this development
work. It is not independent review and cannot satisfy the later English/Kannada
reviewer-separation or production staffing gates.

## 6. Exact approval phrase

To accept the recommended boundary, reply exactly:

```text
Approve DR-0024 Option A as written.
```

Any other response is a revision or rejection, not approval.

## 7. Resolution

Option A was accepted exactly as written on 10 August 2026 under APP-0051,
D-0150 and ADR-0019. The exact approved proposal, before its status, resolved
date and this resolution section were added, has SHA-256
`2b52f1c3c09206a33de7b940e2184c473d3095b89388d30fc9830386f9266538`.

LPL-01 may implement and execute only the dedicated local pilot-program and
bounded authority bootstrap, ordinary first-party appointment acceptance and
four exact APP-0047 rights confirmations described above. This approval does
not authorize a provider call, media attachment, transcript/content review,
publication, learner delivery, production/public release or LPL-02.

## 8. Execution evidence

Option A completed on 10 August 2026 within its local non-production boundary.

- The approval-bound command created standalone program
  `01KZNK3BWD8A8NJ5A5JW0ZVZMT`, non-system role
  `01KZNK3BWD8A8NJ5A5JW0ZVZMV` and pending appointment
  `01KZNK3BWRCRWG1EYK7T1ZAYYE` in one transaction. The role contains exactly
  `curriculum.author` and `media.rights.approve` and is assigned only to the
  approved membership.
- The program has no curriculum version. The bootstrap emitted exactly one
  content-free audit event and one outbox event. An exact replay after
  appointment acceptance returned `replayed` with the original completion
  time and created no duplicate authority record.
- The switch was supplied only to the command process and remains absent from
  persistent environment files. The role and appointment remain visible,
  revocable authoritative state as required.
- Ordinary CSRF/Sanctum OTP login, exact membership selection and a fresh TOTP
  assertion succeeded. The exact appointee read the private state fingerprint
  and accepted the appointment through the existing HTTP command.
- One temporary first rights request referenced asset identifiers outside this
  DR and correctly returned the generic Media Library not-found response; it
  created no rights row. The corrected requests used the four exact table-2
  asset/hash pairs and each returned HTTP `201`.
- The resulting initial rights revisions are
  `01KZNKNFWGSRDBD57H18YCDPG8`,
  `01KZNKNFXKFRSVXRKPYWHDCF7C`,
  `01KZNKNFYDDC31CV2BR155DXVR` and
  `01KZNKNFZ7A5Z9JPSC2AV0QPZQ`, in table-2 order. Persisted-state inspection
  found exactly four approved rows bound to APP-0047 evidence hash
  `cc50ca8d13c9753bafffb195e952880014ed39bd08476601ccc428cff5f6b201`,
  channels `android`/`web`, territory `IN`, learner delivery and derivatives
  true, sharing false, no expiry, `accepted_media`,
  `preserve_while_referenced` and the accepted appointment.
- No provider was called and no metadata, transcript, media attachment,
  curriculum version, publication, enrollment or learner delivery was
  created. The next stop is the finite provider-run and transcript-
  reconciliation contract in LPL-01.
- Final `make verify` passed 144 SQLite tests / 1,815 assertions, 280
  PostgreSQL tests / 4,017 assertions and all static, dependency, contract,
  localization and web-unit gates. Final `make e2e` passed 16/16 Chromium
  journeys. No AI evaluation or separate packaging build was required by this
  non-provider, non-packaging change.

The operational procedure and fail-closed recovery rules are retained in the
[local LPL pilot authority runbook](ops/LOCAL_LPL_PILOT_AUTHORITY_BOOTSTRAP.md).

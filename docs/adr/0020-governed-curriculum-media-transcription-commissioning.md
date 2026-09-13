---
status: accepted
date: 2026-08-10
deciders:
  - Codex task accountable product owner as Accountable Product Owner and Technical Decision Owner
  - Codex task accountable product owner as Security Reviewer, Privacy Reviewer and Tenancy Reviewer
consulted:
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: false
approval_evidence:
  - APP-0052
approval_reference: Exact human instruction in the active Codex conversation
---

# ADR-0020: Governed curriculum-media transcription commissioning

## Status

Accepted on 10 August 2026 under
[APP-0052](../APPROVALS.md#app-0052) / D-0153. This ADR resolves
[DR-0025](../DR-0025_LPL01_TRANSCRIPTION_COMMISSIONING_REQUEST.md) by
accepting Option A exactly as written. The approved pre-resolution DR has
SHA-256
`cb489ec3f034861d256934aa0fcf5128a997af73ec6da4bd461453ebce02bdf4`.

Acceptance authorizes the bounded implementation, verification and one exact
non-production campaign in DR-0025. It does not itself create a migration,
route, credential, provider request, candidate, metadata revision, review or
publication. Every execution-time gate in this ADR and DR-0025 remains
mandatory before network dispatch.

## Context

LPL-01 has four exact APP-0047 teaching MP3s as active, clean, private
`lesson_audio` assets in the Direct organization. One standalone pilot program
and one approved program-scoped rights revision per asset authorize bounded
non-production transcription and derivative commissioning.

The existing Azure recorded-speech adapter cannot be widened silently. It is
purpose-built for synthetic benchmarking and short consented learner PCM/WAV,
limits candidates to 500 characters and uses learner recording/retention
semantics. Curriculum media requires separate ownership, long-form response,
candidate, provenance and human-authoring records.

Current Microsoft documentation supports synchronous inline MP3 Fast
Transcription, `en-IN` and Central India and documents no provider retention
for Fast Transcription. Terms, configuration and prices can change, so public
documentation remains only one part of the execution evidence.

## Decision

Implement DR-0025 Option A's complete normative contract.

### Provider boundary

- Add a project-owned curriculum-media transcription port and project result
  types under curriculum commissioning. No provider SDK type enters a domain
  module.
- Add a dedicated Azure REST adapter that may reuse reviewed HTTP, endpoint,
  TLS and secret-redaction components without changing the learner adapter or
  ordinary application binding.
- Construct the adapter only inside a disabled-default command in the named
  `lpl-media-transcription` non-production environment. It remains unavailable
  to ordinary web/Android learner routes, queues, schedulers and dependency
  injection.
- Lock requests to the reviewed Central India custom-domain Azure Speech
  resource, synchronous Fast Transcription route, API `2025-10-15`, fixed
  `en-IN`, provider-managed default transcription model and
  `profanityFilterMode=None`.
- Prohibit URL/public-object input, callbacks, batch, translation, language
  identification, diarization, channel separation, word timestamps, custom
  speech/model, phrase lists, custom prompting, pronunciation assessment, LLM
  Speech, MAI Transcribe and fallback providers.
- Revalidate tenant, program, active asset, selected clean scan, latest
  effective rights, byte count and SHA-256 before every dispatch. Send the
  exact immutable MP3 bytes inline as generic `audio.mp3`; do not split,
  normalize, transcode or expose a URL.

### Exact campaign and spend boundary

The single authorized campaign contains only these exact assets and rights:

| Part | Media asset | Rights revision | SHA-256 |
|---|---|---|---|
| Introduction | `01KZNFYHCJSDWSPK08ZAC7G3AJ` | `01KZNKNFWGSRDBD57H18YCDPG8` | `6838818d74481ffe38e13948dea2954886dcc519ed927112b703f8e2064573aa` |
| Practice | `01KZNFYJ1M911SB8YVH9P4EZZ0` | `01KZNKNFXKFRSVXRKPYWHDCF7C` | `75909e9baf340356d7ecf66435977ee1cce1fe93a597da58a24b5b9ea1833dd7` |
| Conversation | `01KZNFYJVSZRPZCEPTTQ4QTMNT` | `01KZNKNFYDDC31CV2BR155DXVR` | `8124b3bc6f71b65258358b0121cc3d72f1543948ca3ef3a1bb419f1fa0d65c70` |
| Interaction | `01KZNFYK75V9WF8R5VCWRRZHNN` | `01KZNKNFZ7A5Z9JPSC2AV0QPZQ` | `4862e927078ecb0ea56b7240f6d5b52356365115a6512836357bf340fdcf895e` |

- Run sequentially. Permit one initial attempt and at most one allowlisted
  transient retry per asset. A successful asset is never resent on resume.
- Hard caps are four logical assets, eight HTTP attempts, 37,520,380 uploaded
  bytes, 2,345,026 billable input milliseconds and ₹100 estimated Azure
  service usage excluding external tax/settlement effects.
- Reserve bytes, duration and price-snapshot-derived estimated cost before
  each attempt, including uncertain timeouts and failed HTTP responses.
- Require the APP-0052 reference, approved DR hash, run and exact manifest
  hashes, provider/config/vendor/price evidence, external credential reference
  and a mode-`0600` external campaign permit before every attempt. Removing or
  changing any gate stops dispatch.
- Remove the permit/live switch after terminal state and rotate/revoke the run
  credential. A second campaign or larger asset, attempt, byte, duration or
  spend set requires new approval.

### Privacy, retention and provenance

- Renew exact subscription/resource, contracting entity, agreement, May
  2026-or-later DPA, Product Terms, subprocessors, privacy/legal disposition,
  Central India, credential version, logging-disabled, no-training/model-
  improvement/human-quality-review and same-day price/FX evidence before live
  execution. Prior benchmark spend authority is not reused.
- Provider payloads contain only exact audio, fixed locale/options and required
  auth/content headers. They contain no tenant/program/asset/operator label,
  learner data, source body, vocabulary prompt, URL or conversation history.
- Raw media, requests/responses, transcript text, candidate keys and secrets
  never enter ordinary logs, traces, exceptions, audit, outbox, analytics,
  command output or Git. The raw provider response exists in memory only until
  validation.
- Store a validated candidate only through a project-owned application-
  encrypted private store. Unreconciled content expires after seven days;
  reconciled/discarded/superseded content is deleted within a target 15
  minutes and hard one hour, with retry/dead-letter evidence.
- Retain content-free provenance while referenced by resulting governed
  content and otherwise for 365 days, with maximum 30-day backup age-out.
  Existing accepted source-media retention is unchanged.

### Authoritative records and reconciliation

Add only the organization-owned records named in DR-0025:

- a versioned campaign projection with exact program, approvals, evidence,
  configuration, caps, reserved usage/spend and state;
- one exact asset state per campaign with asset/scan/rights/input/candidate
  digest and expiry relationships;
- append-only content-free provider attempts;
- append-only lifecycle and candidate-deletion events; and
- append-only human reconciliation binding the exact candidate digest to one
  resulting English metadata revision/hash or explicit discard/attention
  outcome.

Tenant-leading foreign keys, state/append-only database protections,
transactions, deterministic locks and idempotency must prevent cross-tenant,
duplicate and replayed authority. Candidate ciphertext remains outside these
content-free records.

Provider output is never metadata by itself. A private no-store operator read
and reconciliation command requires current-organization first-party web
authentication, selected membership, fresh TOTP, `media.metadata.manage`,
expected version, candidate hash and idempotency. A human listens to every
segment and chooses confirmed, corrected, discarded or needs-attention.
Confirmed/corrected creates the metadata revision and reconciliation evidence
atomically.

The human is the metadata author. Reconciliation is not English academic or
Kannada review. Existing author/reviewer separation remains mandatory, and an
unresolved timestamped inaudible marker blocks later approval/publication.

### Deterministic and AI authority

No LLM or AI lesson drafting is part of this campaign. A later gate may use
only human-reconciled exact hashes. Speech or later AI cannot write marks,
mastery, progression, unlock, answers, academic/Kannada decisions or
publication authority.

## Alternatives considered

### One-off external transcription handoff

Rejected because it cannot preserve durable attempt, candidate, cleanup and
human reconciliation lineage and would need replacement before all-level
commissioning.

### Hold or human-only transcription

Retained as the fail-closed fallback, but not selected because it does not
establish the approved dynamic workflow and leaves LPL-01 blocked.

### Reuse learner recording tables and adapter

Rejected because learner consent, ownership, format, duration, transcript and
deletion semantics are purpose-specific and must not become curriculum-media
authority.

## Consequences

### Positive

- Exact accepted MP3 bytes remain the provider input and immutable lineage.
- Future lessons can use the same data-driven pipeline after exact media,
  rights and separately approved campaign evidence, without level-specific
  code.
- Provider output stays visibly separate from human-authored metadata and
  specialist review.
- Attempts, cost, cleanup, failures and reconciliation become inspectable
  without retaining raw content in operational evidence.

### Trade-offs

- LPL-01 gains additive schema, a dedicated command/adapter, encrypted object
  lifecycle, private operator API and OpenAPI/generated-client changes before
  the first four requests.
- Provider-managed model rotation remains a negative trade-off and requires
  configuration/evidence fingerprints and future evaluation on material
  change.
- The initial human path can use the API directly; the full Content Studio
  experience remains LPL-03.

## Security, privacy and tenancy impact

All resources bind to the immutable Direct organization and exact pilot
program. Callers cannot select tenant, provider, endpoint, region, model,
locale, options, assets or fallback. Wrong tenant/program, stale rights,
changed bytes or changed evidence fails before content read or provider
dispatch.

Teaching voice audio can contain personal or sensitive characteristics even
though it is not learner audio. APP-0047 covers only the four exact owner-
authorized hashes. Assignment audio, learner/employee/production audio and
future lesson media remain excluded.

One human exercised consolidated development roles. This is not independent
academic, Kannada, legal, security, privacy, tenancy or production review.

## API, client and operational impact

Implementation adds the private candidate-read/reconciliation API and updates
OpenAPI/generated clients. It adds no learner route or LPL-02 runtime. The
dedicated command must produce only a safe content-free report and remain
disabled outside its exact environment/evidence gates.

Before any live call, implementation must pass the DR-0025 configuration,
secret, tenant, cap, resume/retry, concurrency, provider-fake, response,
candidate deletion and reconciliation suites, plus `make verify`, critical
`make e2e` and `make ai-eval`. `make build` applies only if packaging changes.

## Master-plan amendment

This ADR does not amend the master plan. It applies the existing project-owned
provider, immutable content, deterministic authority, tenant isolation,
privacy/retention, cost and human-review boundaries to LPL-01 curriculum-media
commissioning.

## Approval evidence

- Evidence ID: `APP-0052`
- Decision date: 2026-08-10, Asia/Kolkata
- Human approver identifier: `Codex task accountable product owner`
- Roles exercised: Accountable Product Owner; Technical Decision Owner;
  Security Reviewer; Privacy Reviewer; Tenancy Reviewer
- Exact human statement: `Approve DR-0025 Option A as written.`
- Approved pre-resolution DR SHA-256:
  `cb489ec3f034861d256934aa0fcf5128a997af73ec6da4bd461453ebce02bdf4`
- Master-plan impact: none

Codex is the author and evidence recorder only, never the approver. One human
exercised consolidated development roles; this is not independent specialist,
academic/Kannada, legal or production review.

## Follow-up

1. Implement and verify only the accepted provider-neutral records, adapter,
   command, encrypted candidate lifecycle and human reconciliation API.
2. Renew every exact vendor/configuration/price/FX/credential/permit gate.
3. Execute at most the one accepted four-asset campaign and preserve its safe
   report; stop on any drift or cap.
4. Reconcile every candidate through a human before blueprint drafting.
5. Complete English academic and Kannada review before LPL-01 exit; do not
   begin LPL-02 opportunistically.

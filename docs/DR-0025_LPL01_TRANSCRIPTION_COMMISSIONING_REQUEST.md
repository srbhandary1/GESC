# DR-0025 — LPL-01 governed transcription commissioning

**Status:** Resolved — Option A accepted under APP-0052 / D-0153 / ADR-0020

**Needed by:** LPL-01 transcript reconciliation and Level 1 blueprint drafting

**Prepared:** 10 August 2026

**Resolved:** 10 August 2026

## 1. Decision needed

Choose the provider, finite run boundary and authoritative reconciliation model
for transcribing the four exact APP-0047 Level 1 teaching assets.

The assets are active, clean, private `lesson_audio` records in the Direct
organization. DR-0024 also established the standalone pilot program and one
approved program-scoped rights revision per exact asset. Those records permit
bounded non-production transcription and derivative commissioning, but they do
not authorize a provider request or make provider text authoritative.

The repository has a previously benchmarked Azure Speech REST boundary, but it
is deliberately specialized for synthetic and short learner PCM/WAV. It
rejects MP3, limits a transcript candidate to 500 characters and is available
only through `speech:benchmark`. Reusing the learner recording tables would
also apply the wrong consent, ownership, duration and deletion semantics to
owner-licensed curriculum media.

A material choice is therefore required before implementation: use a
first-class curriculum-media transcription path with immutable provenance, use
a one-off external handoff with weaker lineage, or keep LPL-01 on hold.

## 2. Exact eligible input set

No asset outside this table may be dispatched under this request.

| Order | Part | Media asset | Rights revision | SHA-256 | Bytes | Duration ms |
|---:|---|---|---|---|---:|---:|
| 1 | Introduction | `01KZNFYHCJSDWSPK08ZAC7G3AJ` | `01KZNKNFWGSRDBD57H18YCDPG8` | `6838818d74481ffe38e13948dea2954886dcc519ed927112b703f8e2064573aa` | 4,968,408 | 310,526 |
| 2 | Practice | `01KZNFYJ1M911SB8YVH9P4EZZ0` | `01KZNKNFXKFRSVXRKPYWHDCF7C` | `75909e9baf340356d7ecf66435977ee1cce1fe93a597da58a24b5b9ea1833dd7` | 7,357,881 | 459,868 |
| 3 | Conversation | `01KZNFYJVSZRPZCEPTTQ4QTMNT` | `01KZNKNFYDDC31CV2BR155DXVR` | `8124b3bc6f71b65258358b0121cc3d72f1543948ca3ef3a1bb419f1fa0d65c70` | 2,157,215 | 134,826 |
| 4 | Interaction | `01KZNFYK75V9WF8R5VCWRRZHNN` | `01KZNKNFZ7A5Z9JPSC2AV0QPZQ` | `4862e927078ecb0ea56b7240f6d5b52356365115a6512836357bf340fdcf895e` | 4,276,686 | 267,293 |

The exact total is 18,760,190 bytes and 1,172,513 milliseconds
(19 minutes 32.513 seconds). The selected program is
`01KZNK3BWD8A8NJ5A5JW0ZVZMT` in Direct organization
`01KYS97G7MX374P303XJHDXT72`.

## 3. Current official provider evidence

Official Microsoft material was rechecked on 10 August 2026:

- Fast Transcription is a synchronous REST operation and supports inline MP3
  input, segment timestamps and fixed locale selection. The current guide
  states a less-than-five-hour/500 MB limit, while the exact `2025-10-15` REST
  reference states the stricter less-than-two-hour/250 MB limit. This proposal
  adopts the stricter limit; every selected file is below eight minutes and
  eight megabytes.
- API version `2025-10-15`, Fast Transcription and locale `en-IN` are current
  supported choices. `centralindia` supports Fast Transcription, and Microsoft
  documents processing/storage in the resource's selected region.
- Microsoft documents that Fast Transcription does not retain or store
  customer-provided data. This proposal still requires exact subscription,
  DPA, Product Terms, subprocessor, no-training/model-improvement and logging-
  disabled evidence; public documentation is not a substitute for the
  subscription's contractual/configuration record.
- Speech-to-text usage is billed in one-second increments, but the public
  pricing page is dynamic. A dated rate and INR conversion snapshot must be
  captured before dispatch; no price is inferred in this request.

Provider terms and capabilities can change. Any mismatch at execution time
fails closed rather than silently changing provider, region, API, model or
options.

## 4. Options

| Option | Decision | Consequence |
|---|---|---|
| **A — First-class curriculum-media commissioning path on bounded Azure Fast Transcription (recommended)** | Add a provider-neutral media-transcription port, dedicated Azure REST adapter, finite one-campaign records, encrypted short-lived candidates and an authenticated human reconciliation command. Send each exact accepted MP3 inline to Central India under the contract in section 5. | Preserves exact-byte lineage, reusable dynamic onboarding for future lessons and explicit separation between provider candidates, human-authored metadata and academic review. It adds a narrow additive schema and API contract before the four calls. |
| **B — One-off external transcription handoff** | Use a protected workstation script to call Azure, keep candidates only in an external encrypted bundle and manually paste reconciled text into Media Library metadata. | Faster initially, but loses durable attempt/candidate/reconciliation lineage, is difficult to resume safely and would need replacement before additional levels are commissioned. |
| **C — Hold or transcribe manually without a provider** | Make no provider call and wait for human-created transcripts. | Avoids provider transfer and schema work, but leaves LPL-01 blocked and does not establish the requested dynamic workflow for future lessons. |

The learner `voice_recordings` / `voice_provider_runs` path and ordinary
application Azure binding are explicitly not options. Their constraints and
authority must remain unchanged.

## 5. Recommended Option A contract

If accepted exactly as written, Option A authorizes only the implementation,
verification and one finite non-production campaign below.

### 5.1 Provider and request lock

1. Add a project-owned curriculum-media transcription port and project result
   types under the curriculum commissioning boundary. Provider SDK types may
   not enter domain code. The Azure implementation may reuse reviewed HTTP,
   secret-redaction and endpoint-validation components, but it may not widen
   the learner recording adapter or ordinary dependency injection binding.
2. Construct the live adapter only inside a dedicated command in the named
   non-production environment `lpl-media-transcription`. It is disabled by
   default in all environments and unavailable to web/Android learner routes,
   queues, schedulers and ordinary application resolution.
3. Lock the service to Microsoft Azure AI Speech Fast Transcription on the
   exact reviewed `centralindia` custom-domain resource, REST route
   `/speechtotext/transcriptions:transcribe`, API `2025-10-15`, provider-
   managed default transcription model, fixed locale `en-IN` and
   `profanityFilterMode=None`.
4. Do not enable URL input, public object access, callbacks, batch jobs,
   language identification, translation, diarization, channel separation,
   word-level timestamps, custom speech, custom models, phrase lists, custom
   prompting, LLM Speech, MAI Transcribe, pronunciation assessment, endpoint
   logging or any fallback provider. Default merged-channel segment phrases
   are accepted only as non-authoritative candidate evidence.
5. Read each accepted private object through a project-owned store, recompute
   SHA-256 and byte count, and revalidate the active lifecycle, exact selected
   clean scan and latest effective approved rights revision immediately before
   every dispatch. Send the exact immutable MP3 bytes inline under the generic
   filename `audio.mp3`; do not normalize, split, transcode or expose a URL.
6. Reuse the existing endpoint-host, no-redirect, TLS, external mode-`0600`
   credential, safe-header hashing and content-free exception controls. Use a
   three-second connection timeout and a separately bounded maximum 300-second
   request timeout suitable for these long synchronous files. A timeout is an
   uncertain spent attempt, never proof that the provider did no work.

### 5.2 Exact finite ceiling and kill switches

1. One campaign contains exactly the four table-2 asset/hash/rights tuples in
   order. It may run sequentially only; it cannot discover assets by level,
   kind, query, prefix or storage listing.
2. Allow one initial HTTP attempt per asset and at most one retry for an
   allowlisted transient connection failure, timeout, HTTP 429 or HTTP 5xx.
   Authentication, authorization, input, rights, configuration, contract,
   malformed-response, content-bound or candidate-storage failures are never
   retried. A successful asset is never resent on resume.
3. Hard caps are four logical assets, eight HTTP attempts, 37,520,380 total
   uploaded bytes and 2,345,026 total billable input milliseconds. Attempts
   are reserved against every cap before network dispatch, including uncertain
   timeouts and failed HTTP responses.
4. The estimated Azure service-usage ceiling is **₹100 INR**, excluding taxes
   and card/bank settlement adjustments the application cannot control. A
   same-day official price snapshot and named INR conversion source must prove
   that every still-possible attempt fits below the ceiling before the first
   request. Reserve the full estimated per-second charge before each attempt;
   stop rather than dispatch if the next reservation can exceed ₹100.
5. Execution requires all of the following at every attempt: the eventual
   DR-0025 approval reference, exact approved request hash, exact run ID,
   dedicated environment, live-provider boolean, four-asset manifest hash,
   vendor/configuration evidence hash, price evidence, external credential
   reference and a mode-`0600` external permit file containing only the exact
   campaign ID. Missing, changed or removed evidence is the kill switch.
6. Remove the permit and live-provider switch immediately after terminal
   completion or failure, and rotate/revoke the run credential. Resume may
   process only an existing nonterminal campaign's unsucceeded assets within
   its original reserved caps. A second campaign or any additional asset,
   attempt, time or spend requires a new explicit approval.

### 5.3 Provider and application privacy

1. Before dispatch, renew the exact Azure subscription/resource, contracting
   entity, agreement, May 2026-or-later DPA, Product Terms, subprocessor,
   privacy/legal disposition, Central India, credential-version, data-logging-
   disabled, no-training/model-improvement/human-quality-review and price/FX
   evidence. Existing PR-009 artifacts may be referenced only when they still
   describe the same resource and remain current; APP-0015/APP-0016 spend is
   not reused as this campaign's authority.
2. Send only exact audio bytes, locale/options and provider-required auth/
   content headers. Do not send an asset label, source title, organization,
   program, learner, operator, URL, internal identifier, source body,
   vocabulary prompt or conversation history.
3. Raw audio, provider request/response bodies, transcripts, candidate object
   keys, credentials and personal/operator data stay out of logs, traces,
   exceptions, audit, outbox, analytics, command output and Git. Record only
   safe identifiers, hashes, policy labels, counts, durations, latency, usage,
   estimated cost and bounded failure categories.
4. The provider raw response exists only in process memory until validation
   and is then discarded. Microsoft-side no-storage/no-retention remains an
   execution gate. No remote deletion claim is created for the selected
   synchronous operation.
5. A validated candidate is stored only through a project-owned,
   application-encrypted private candidate store. An unreconciled candidate
   expires after seven days. Reconciled, discarded or superseded candidate
   content is deleted with a target of 15 minutes and hard maximum of one hour;
   deletion uses retry/dead-letter evidence. Accepted source media retains its
   existing `preserve_while_referenced` lifecycle and is not deleted by the
   transcription command.
6. Content-free provider provenance is retained while any resulting metadata,
   content revision or publication references it, and otherwise for 365 days;
   backup copies have a maximum 30-day age-out. This evidence may not be used
   to reconstruct deleted transcript text.

### 5.4 Additive authoritative records

Implement only the minimal organization-owned schema needed to avoid treating
an external bundle or learner record as authority:

- a versioned `media_transcription_campaigns` projection containing exact
  program, approval/policy/configuration/evidence fingerprints, fixed caps,
  reserved usage/spend and terminal state;
- one exact asset entry per campaign binding media asset, selected scan,
  rights revision, source hash/bytes/duration, locale, state and candidate
  digest/expiry without transcript text;
- append-only provider attempts containing provider/model/region/API/config,
  input/output metadata digests, attempt number, status, safe failure, usage,
  latency and estimated cost but no content;
- append-only campaign/asset lifecycle and candidate-deletion events; and
- append-only human reconciliation evidence binding the exact candidate digest
  to either one resulting English metadata revision/content hash or an
  explicit `discarded` / `needs_attention` outcome.

Tenant-leading keys and foreign keys must bind every relationship to the same
organization, campaign, program, asset, scan and rights revision. PostgreSQL
append-only/state-transition protections, transactions, deterministic locks
and idempotency must prevent duplicate campaigns, attempts, candidates and
reconciliations. SQLite must preserve equivalent application behavior. No
foundation table is repurposed and no platform-global record is added.

Candidate ciphertext belongs in the private store, not provider-attempt,
audit or event rows. Provider output is never an English metadata revision by
itself.

### 5.5 Response validation and human reconciliation

1. Bound the HTTP response to one MiB. Require HTTP 200 JSON, one nonblank
   merged-channel `combinedPhrases` transcript, valid UTF-8/NFC text no larger
   than the existing Media Library limit of 32,000 scalar values/64 KiB, and a
   nonempty bounded phrase list with finite confidence and valid monotonic
   offsets/durations within the source duration plus the existing tolerance.
   Truncation, multiple channel transcripts, invalid locale, unknown shapes,
   HTML/error bodies, impossible duration or over-limit text fail closed.
2. Preserve candidate text and segment timestamps/confidence only inside the
   encrypted candidate object. Record canonical candidate and response-
   metadata hashes separately. Never invent or truncate missing text.
3. Add a private, no-store operator candidate read and reconciliation command
   under current-organization first-party web authentication, selected
   membership, fresh TOTP, `media.metadata.manage`, expected version,
   candidate hash and idempotency. Update OpenAPI/generated clients for this
   API. The initial LPL-01 execution may use that API directly; the full
   Content Studio experience remains LPL-03.
4. A human must listen against the complete exact asset, inspect every segment
   and choose `confirmed`, `corrected`, `discarded` or `needs_attention`.
   Confirmed/corrected creates the English Media Library metadata revision and
   reconciliation evidence in one authoritative transaction. An unresolved
   passage may use a timestamped `[inaudible ...]` marker, but any such marker
   blocks academic approval and publication until replaced by a reviewed
   successor.
5. The reconciler is the metadata author. Reconciliation is not English
   academic approval, does not establish that all four parts have the same
   `can` scope and does not satisfy Kannada review. Existing author/reviewer
   separation remains mandatory for the exact resulting metadata hash.
6. No LLM or AI lesson-drafting call is authorized by this campaign. That
   later step may consume only human-reconciled exact transcript/source hashes
   through its own provider/version/prompt/spend/provenance gate. Neither STT
   nor later AI can write marks, mastery, progression, unlock, answer keys,
   review decisions or publication authority.

### 5.6 Verification and completion boundary

Before the first live request, implementation must pass:

- configuration/evidence/permit/secret redaction and ordinary-DI denial tests;
- exact four-asset allowlisting, rights/scan/hash drift and cross-tenant tests;
- cap reservation, resume, retry, timeout, kill-switch and concurrent-run
  tests on SQLite and PostgreSQL;
- MP3 request minimization and malformed/oversized/provider-failure tests using
  HTTP fakes with no raw content in observable evidence;
- encrypted candidate expiry/deletion/dead-letter and candidate-read/
  reconciliation authorization, idempotency and reviewer-separation tests;
- OpenAPI/generated-client and all applicable localization checks; and
- `make verify`, `make e2e` for the critical reconciliation journey and
  `make ai-eval` because live provider orchestration changed. Run `make build`
  only if packaging/deployment behavior changes.

The live campaign is complete only when all four candidates have terminal
provider evidence, every retained transcript has a human reconciliation
outcome, candidate cleanup is within policy and the safe report proves all
caps. A provider success alone does not complete LPL-01. Blueprint drafting,
English academic review, Kannada translation/review and publication readiness
remain later LPL-01 result gates; LPL-02 remains unstarted.

## 6. Future-lesson behavior

Option A is intentionally data-driven. A future lesson uses the same port,
records and reconciliation contract after it has an accepted exact media
asset, clean selected scan, effective program-scoped processing rights,
reviewed locale/provider policy and a separately approved campaign manifest
whose caps are derived from its exact durations. Adding a lesson never edits a
level-specific code branch and never inherits this four-asset spend approval.

A provider, API, region, locale, storage, retention, model class or candidate-
authority change requires a successor decision and evaluation. Routine new
assets that fit an already accepted production commissioning policy may later
use an operator-created manifest, but this development decision does not create
that production authority.

## 7. Security, privacy and tenancy impact

Option A keeps owner-licensed teaching media separate from learner recordings
while applying the same strict provider minimization. Voice audio can still be
personal or sensitive content; APP-0047 supplies owner processing authority
for these exact files only, not for Assignment audio, learner media or future
assets.

Every campaign resource remains in the immutable Direct organization and
exact pilot program. The client cannot choose tenant, provider, endpoint,
region, model, locale, options, asset list or fallback. A cross-tenant,
wrong-program, stale-rights, changed-object or changed-evidence request fails
before reading candidate content or contacting Azure.

One human currently exercises consolidated development roles. That is
acceptable for this non-production commissioning decision but is not
independent academic, Kannada, security, privacy, legal or production review.

## 8. Non-goals

This decision does not authorize:

- more than the four exact table-2 assets, one campaign or ₹100 estimated
  Azure service usage;
- any legacy Assignment, learner, employee or production audio;
- a learner-facing audio route, LPL-02 practice runtime or offline package;
- provider/LLM content approval, quiz/answer generation or AI drafting;
- automatic metadata, academic/Kannada review, attachment or publication;
- Azure batch/custom/LLM speech, public URLs or a fallback provider;
- production deployment, public release, sharing or cross-owner use; or
- representing consolidated development review as independent assurance.

## 9. Exact approval phrase

To accept the recommended boundary, reply exactly:

```text
Approve DR-0025 Option A as written.
```

Any other response is a revision or rejection, not approval.

## 10. Resolution

Option A was accepted exactly as written on 10 August 2026 under
[APP-0052](APPROVALS.md#app-0052), D-0153 and
[ADR-0020](adr/0020-governed-curriculum-media-transcription-commissioning.md).
The approved pre-resolution request has SHA-256
`cb489ec3f034861d256934aa0fcf5128a997af73ec6da4bd461453ebce02bdf4`.

This acceptance authorizes only the bounded implementation, verification and
single campaign described above. It did not itself create a migration, API,
credential use, provider call, candidate, transcript or metadata revision.
Every execution-time evidence, configuration, price, permit, security,
privacy, tenancy and verification gate remains mandatory before dispatch.

D-0154 now records the bounded repository implementation and verification
result, including OpenAPI 0.23.0 and the accepted direct reconciliation API
journey. That later record does not amend the campaign, provider, spend,
privacy or authority boundary above. No credential, provider request,
candidate, transcript or metadata revision was created, so every external
execution-time gate remains open.

## 11. References

- [APP-0047 exact media authorization](LPL-01_LEVEL1_MEDIA_RIGHTS_AUTHORIZATION.md)
- [LPL-01 governed ingestion and rights evidence](LPL-01_INGESTION_PREFLIGHT.md)
- [ADR-0007 provider processing policy](adr/0007-recording-consent-retention-provider-processing-and-deletion-policy.md)
- [ADR-0008 Azure Speech development policy](adr/0008-azure-speech-development-provider-and-benchmark-policy.md)
- [ADR-0019 LPL pilot and rights authority](adr/0019-local-lpl-pilot-and-rights-authority-bootstrap.md)
- [Azure Fast Transcription guide](https://learn.microsoft.com/en-us/azure/ai-services/speech-service/fast-transcription-create)
- [Azure Fast Transcription REST `2025-10-15` reference](https://learn.microsoft.com/en-us/rest/api/speechtotext/transcriptions/transcribe?view=rest-speechtotext-2025-10-15)
- [Azure Speech regions](https://learn.microsoft.com/en-us/azure/ai-services/speech-service/regions)
- [Azure Speech language support](https://learn.microsoft.com/en-us/azure/ai-services/speech-service/language-support)
- [Azure Speech-to-text privacy and retention](https://learn.microsoft.com/en-us/azure/foundry/responsible-ai/speech-service/speech-to-text/data-privacy-security)
- [Azure Speech pricing](https://azure.microsoft.com/en-us/pricing/details/speech/)
- [Microsoft Products and Services DPA](https://www.microsoft.com/licensing/docs/view/Microsoft-Products-and-Services-Data-Protection-Addendum-DPA)

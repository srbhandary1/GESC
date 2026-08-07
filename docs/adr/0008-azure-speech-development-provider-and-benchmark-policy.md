---
status: accepted
date: 2026-07-31
deciders:
  - S R Bhandary as Technical Decision Owner
  - S R Bhandary as Security Reviewer
  - S R Bhandary as Privacy Reviewer
  - S R Bhandary as Tenancy Reviewer
consulted:
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: false
approval_evidence:
  - APP-0013
approval_reference: Codex task 019fb263-fe9a-77f0-a1db-e3d563707715
---

# ADR-0008: Azure Speech development provider and benchmark policy

## Status

Accepted on 2026-07-31 under
[APP-0013](../APPROVALS.md#app-0013). The approved proposal SHA-256 is
`b1c778753ab9b0b6b7b8202f8d106a8ee302485455189afce76baac361841b51`;
the separately hashed benchmark plan SHA-256 is
`817f430aabd93eb06d9ecd1b980994862e1ddfe19f3285fe184ba508204581ab`.

This acceptance resolves DR-0011 for the PR-009 provider selection and
benchmark policy. It authorizes only the remaining Azure Speech adapter and
locked synthetic-benchmark implementation.

It does not supply a credential, approve provider spend, report the benchmark
as passed, accept PR-009, authorize learner data, PR-010 or production. No
credential or live synthetic request is permitted until the exact
subscription/DPA/subprocessor evidence and separate provider-spend approval
required by this ADR are recorded.

**Product-policy amendment:** [ADR-0011](0011-confidence-conversation-guidance-tts-policy.md)
(accepted under APP-0024) amends Confidence Conversation guidance TTS and
AI-only lesson-audio policy only. The locked PR-009 synthetic benchmark
allowlist in this ADR remains unchanged.

## Context

The provider-neutral PR-009 vertical slice is implemented and verified. A
disabled adapter and a testing-only deterministic adapter currently prove the
project boundary. The checkpoint is not complete because ADR-0007 requires an
exact reviewed STT and TTS implementation plus locked synthetic benchmark
evidence before a live adapter can be enabled.

The provider decision must preserve:

- English as the learning and recognition language;
- project-owned provider ports with no provider SDK type in domain modules;
- separate learner consent for upload/transcription and provider processing;
- no training, model improvement, advertising or human quality review using
  learner content;
- private, transient audio and transcript processing under ADR-0007;
- Central India processing for the selected managed path;
- learner correction of every transcript candidate;
- text fallback when STT or TTS is unavailable;
- deterministic authority over marks, mastery, progression and review; and
- a synthetic-only development benchmark that is not represented as learner,
  accent, production or pedagogical validation.

Official provider documentation was reviewed on 31 July 2026. Provider terms,
features, regions and models can change. The evidence snapshot must be renewed
immediately before the live benchmark and again before any production review.

## Decision

### Selected development stack

Subject to exact-artifact approval and the evidence gates below, PR-009 will
benchmark the following single managed stack:

| Capability | Locked development selection |
|---|---|
| Provider | Microsoft Azure AI Speech |
| Resource region | `centralindia` only |
| STT operation | Synchronous Fast Transcription REST API |
| STT API version | `2025-10-15` |
| STT route | `/speechtotext/transcriptions:transcribe?api-version=2025-10-15` on the exact resource endpoint |
| STT locale | Exactly `en-IN`; no language identification |
| STT model | Azure provider-managed default transcription Speech model exposed by API `2025-10-15` for `en-IN` |
| STT options | Inline validated WAV only; `profanityFilterMode=None`; no URL input, batch job, diarization, custom model, phrase list, pronunciation assessment or endpoint logging |
| TTS operation | Synchronous real-time Text to Speech REST API |
| TTS API route | Locked TTS operation on the exact resource endpoint: `/tts/cognitiveservices/v1` for `*.cognitiveservices.azure.com` custom-domain Speech resources (D-0083; Azure returns 404 for bare `/cognitiveservices/v1` on that host) |
| TTS locale and voice | `en-IN`, standard prebuilt `en-IN-NeerjaNeural` |
| TTS format | `riff-24khz-16bit-mono-pcm` |
| TTS options | Escaped project-created SSML with only `speak` and `voice`; no custom/personal voice, avatar, HD preview, long-audio or batch synthesis |
| Callback behavior | None; both selected operations are synchronous and callbacks/webhooks are prohibited |
| Fallback | Text and re-record/correct-transcript paths only; no unreviewed provider fallback |

The selected STT service does not expose an immutable marketing model identifier
for its default base model. The locked technical identity is therefore the
provider, resource region, REST operation, GA API version, locale, option
allowlist and benchmark instant. The benchmark report must record all safe
provider response headers or model metadata that are exposed, plus a hash of
the complete allowlisted configuration.

This managed-model rotation is an explicit negative trade-off. A later model or
service change requires the locked benchmark to run again. A material quality,
retention, region or contract change disables the adapter until review.

### Data-processing and regional boundary

The selected path is eligible only while current official and contractual
evidence confirms:

- the Speech resource is created in `centralindia`;
- Azure Speech processes and stores Speech data only in that resource region;
- fast transcription does not retain or store submitted customer audio or
  returned transcript content;
- real-time prebuilt TTS does not retain input text or generated audio;
- endpoint logging, data logging, custom speech, custom voice, personal voice,
  avatars, batch transcription and batch synthesis remain disabled;
- Microsoft does not use customer Speech data to improve its Speech models;
- no submitted content is used for training, model improvement, advertising or
  human quality review; and
- the current Microsoft Products and Services DPA and Product Terms govern the
  exact Azure subscription.

The application's short encrypted recovery hold occurs before a request and is
project controlled. After a successful provider result is committed, the raw
source is deleted under ADR-0007. Because the selected synchronous operations
document no provider-side storage, there is no normal per-request provider
deletion API. Withdrawal before dispatch cancels the request. Withdrawal during
an in-flight synchronous request records `provider_deletion_not_applicable`
only after the request ends and the application has deleted its source and
result content; it must never claim that a remote stored object was deleted.

### Legal entity, DPA, subprocessors and transfer evidence

The repository cannot infer the contracting Microsoft entity or subscription
terms from public documentation. Before any live credential or request, the
Accountable Product Owner must provide a dedicated development Azure
subscription and an access-controlled vendor evidence record containing:

1. customer legal name and exact contracting Microsoft entity;
2. Azure subscription and agreement type, recorded by opaque reference rather
   than a secret or full account identifier in Git;
3. acceptance date and SHA-256 of the governing agreement, May 2026 or later
   Microsoft Products and Services DPA, and applicable Product Terms;
4. a dated export and SHA-256 of the Microsoft Online Services Subprocessor
   List applicable to Azure;
5. the documented locations and purposes of Microsoft personnel or contract
   staff who may have exceptional operational access;
6. the `centralindia` Speech resource identifier recorded only as a safe,
   non-secret resource reference;
7. confirmation that data-logging and model-improvement opt-ins are off; and
8. qualified privacy/legal disposition of Indian transfer, government-access,
   incident-notice, erasure and processor obligations.

The repository stores only evidence identifiers, dates, hashes and reviewer
outcomes. Contracts, invoices, tenant identifiers, contact details and account
data remain in the access-controlled vendor register.

Missing or stale evidence fails closed. Public documentation supports the
development proposal but is not a substitute for the exact subscription
contract, subprocessor snapshot or legal review.

### Credentials and network security

No credential is committed, written to either `.env`, printed, logged, stored
in a database, sent to the browser or embedded in a mobile application.

For the bounded workstation benchmark:

- use a dedicated non-production Speech resource with the minimum role and
  quota;
- prefer a short-lived Microsoft Entra token obtained outside the application;
- if the service requires a resource key, inject it through a mode-`0600`
  process-readable secret file outside the repository and remove it after the
  benchmark;
- configuration may contain only a secret-file reference, exact HTTPS resource
  endpoint, region and reviewed option values;
- reject redirects and any endpoint whose normalized host is not the exact
  allowlisted Azure Speech resource host;
- require TLS certificate verification and current system trust;
- use bounded connection, request and total timeouts;
- rotate/revoke the benchmark credential after evidence capture; and
- record only a credential version identifier, never the credential.

Production would require workload identity or managed identity, Key Vault,
private-network review, egress allowlisting, rotation and emergency-revocation
evidence. Those are not authorized by this ADR.

The adapter uses Laravel's project-controlled HTTP boundary rather than
exposing an Azure SDK type. Provider response bodies are bounded and parsed
into project-owned results. Provider request/response bodies and authorization
headers never enter logs, traces, exceptions, audit or outbox payloads.

### Request minimization and response validation

STT sends only:

- the already validated mono 16-bit PCM/WAV bytes;
- the fixed `en-IN` locale;
- the fixed profanity policy approved by implementation tests; and
- provider-required authentication and content headers.

It does not send a filename containing identity, URL, learner or tenant
identifier, callback, display name, phone number, organization name,
conversation history, curriculum body or internal authorization record.

TTS sends only:

- the bounded assistant text required for the current turn;
- fixed `en-IN` and `en-IN-NeerjaNeural` values; and
- fixed output-format and application headers.

Text is XML escaped by project code. Provider-generated or learner-controlled
SSML is prohibited.

STT success requires one bounded nonblank `combinedPhrases` result, a supported
locale, finite bounded confidence values and duration consistent with the
validated input. TTS success requires a bounded, decodable RIFF/WAVE response
whose actual PCM format and duration match policy. HTML, JSON error bodies,
redirects, extra multipart output, malformed numbers, unknown locales,
truncation and oversized media fail closed.

Automatic retries remain at two total attempts and only for reviewed transient
categories. Authentication, authorization, malformed response, invalid
configuration and policy failures are never retried as success. A timeout may
leave provider processing uncertain, but because the selected operations store
no content it does not create a remote asset-reconciliation claim.

### Locked development benchmark

The authoritative benchmark definition is
[the PR-009 speech provider benchmark plan](../PR-009_SPEECH_PROVIDER_BENCHMARK_PLAN.md).
Before the first paid request:

- its corpus, transformations, thresholds, runner revision and environment
  manifest are frozen and SHA-256 hashed;
- all utterances are synthetic project-authored strings with no learner,
  employee, customer or production data;
- Azure prebuilt TTS produces the synthetic audio used for the development STT
  round trip;
- deterministic local transforms create the declared acoustic variants;
- fixture and result storage is private, encrypted and expires within 24 hours;
- raw request and response content is excluded from committed results;
- the run has an explicit request and spend cap; and
- the complete result is reproducible from safe manifests plus ephemeral
  synthetic media.

The benchmark must meet every critical security/privacy/contract check and
every quantitative threshold. A partial result, exceeded budget, missing
subgroup, changed corpus, provider error, model/config drift or content leak is
a failure, not a waiver.

The synthetic round trip is intentionally circular and does not validate
Karnataka accents, hesitant beginner speech, microphone diversity, real
network conditions, pedagogical usefulness or production fitness. Those remain
separate consented/human evaluation and production gates.

### Rollout and emergency disablement

An accepted ADR permits implementation and synthetic benchmarking only.
Rollout remains:

1. disabled in every environment by default;
2. enabled only in `local` or a named non-production benchmark environment;
3. scoped to one designated synthetic test organization;
4. protected by the existing consent, adult-eligibility, tenant and retention
   controls even though benchmark fixtures contain no learner data; and
5. disabled immediately for contract, region, retention, security, quality,
   cost or provider-behavior drift.

No tenant or client can select Azure, region, voice, model, options or fallback.
No learner recording may be used during the development benchmark. Production
remains fail-closed.

## Alternatives considered

### Google Cloud Speech-to-Text and Text-to-Speech

Google documents no customer-content storage for synchronous STT when the
optional data-logging program is off, and documents stateless TTS. It was not
selected because the current Speech-to-Text documentation limits controlled
processing to US or EU multi-region endpoints and says single-region processing
is unsupported. That does not satisfy the proposed India-region development
boundary.

### Amazon Transcribe and Amazon Polly in `ap-south-1`

Both services have Mumbai endpoints. This stack was not selected because Amazon
Transcribe documents that it stores and uses voice inputs for service
improvement by default unless an AWS Organizations opt-out policy is applied.
Batch transcription also introduces S3 job/output retention and deletion
reconciliation. Those controls are possible, but add more configuration and
evidence risk than the selected Azure synchronous no-storage path.

### Local Whisper and local TTS

OpenAI Whisper code/model weights are MIT licensed, and Kokoro-82M advertises
Apache-2.0 model licensing. A local stack would remove an external content
transfer. It is deferred because PR-009 currently has no reviewed inference
service, exact voice-asset provenance, hardware capacity target, model
distribution process, isolation boundary, patching plan or concurrency
evidence. Piper's current engine is GPL-3.0 and its voice documentation warns
that individual voice models may have separate restrictive licences.

The master plan already schedules an open-weight benchmark after managed
provider validation. Deferral does not reject local inference for a later
authorized comparison.

### Keep deterministic fake providers only

This remains the safe fallback but cannot satisfy PR-009's requirement for one
benchmarked STT and TTS adapter.

## Consequences

### Positive

- One regional managed service supplies both STT and TTS behind existing ports.
- Synchronous no-storage operations fit ADR-0007's short lifecycle.
- `centralindia` reduces ordinary cross-border content processing.
- REST integration avoids provider SDK types and callback attack surface.
- Fixed locale, voice, format and options make drift and abuse easier to test.
- Text fallback and deterministic learning authority remain unchanged.

### Negative and trade-offs

- Azure's default STT base model is provider managed rather than immutably
  pinned; benchmark reruns and emergency disablement are mandatory.
- The exact contracting entity, DPA acceptance and subprocessor snapshot cannot
  be proven until the Product Owner supplies the dedicated subscription
  evidence.
- The synthetic Azure-TTS-to-Azure-STT round trip can overestimate real learner
  recognition quality.
- A managed service introduces cost, internet dependency, vendor change and
  credential risk.
- The development benchmark does not approve real learner processing.
- Consolidated development approval is not independent production review.

## Security, privacy and tenancy impact

The selected adapter does not change organization or exact-membership
ownership. Dispatch occurs only after existing consent and authority
revalidation while the authoritative recording is locked. Same-organization
other-learner and cross-organization attempts remain denied before any
provider request.

Synthetic benchmark records use a dedicated test organization and opaque
interaction identifiers. Ordinary telemetry contains only safe provider,
policy, region, API, voice, latency, usage, cost and failure categories.

Required tests cover secret redaction, endpoint allowlisting, redirect denial,
TLS failure, request minimization, malformed/oversized output, timeouts,
bounded retry, withdrawal races, content-free logs, exact-learner ownership
and unchanged deterministic learning tables.

An incident involving a credential, unexpected region, logging opt-in,
provider retention, content leak or unreviewed model/config change disables the
adapter, revokes the credential, preserves content-free evidence and follows
the ADR-0007 incident path.

## Authoritative records and consistency impact

Provider configuration and benchmark manifests are immutable versioned
evidence. A provider run records only the configuration fingerprint, safe
provider/model label, region, latency, usage, cost, attempts and terminal
category.

Provider results remain candidates. A transaction commits the encrypted
candidate and lifecycle evidence before deleting source audio. TTS media is
non-authoritative, private and short lived. Neither provider can write marks,
mastery, progression, review, attendance, enrollment or entitlements.

No foundation-schema decision changes. Additive schema is allowed only when an
accepted implementation proves it is required for safe provider/TTS evidence.

## API, client and operational impact

An accepted implementation may add:

- an Azure recorded-STT adapter and real-time TTS adapter behind project ports;
- disabled-by-default configuration with secret references;
- private generated-media creation and streaming already anticipated by
  ADR-0007 and the PR-009 API boundary;
- OpenAPI/generated-client changes for TTS availability/media where required;
- the bounded PR-009 speech benchmark runner and safe report; and
- provider emergency-disable, expiry and cost/latency operations evidence.

It may not add PR-010 model orchestration, broad PR-011 evaluation/memory
features, provider selection by clients, persistent credentials or production
deployment.

Rollback disables dispatch first, keeps deletion/expiry workers running,
deletes generated media and synthetic fixtures, revokes the benchmark
credential and preserves only content-free benchmark/evidence records.

## Master-plan amendment

This ADR does not amend the master plan. It supplies the exact managed-provider
decision and benchmark policy requested by master-plan §§14.4, 14.7, 14.8,
18, 21.2 and §26 PR-009.

## Approval evidence

- Evidence ID: `APP-0013`
- Approved proposal SHA-256:
  `b1c778753ab9b0b6b7b8202f8d106a8ee302485455189afce76baac361841b51`
- Approved benchmark-plan SHA-256:
  `817f430aabd93eb06d9ecd1b980994862e1ddfe19f3285fe184ba508204581ab`
- Acceptance date: 2026-07-31, Asia/Kolkata
- Human approver identifier: `S R Bhandary`
- Roles exercised: Technical Decision Owner; Security Reviewer; Privacy
  Reviewer; Tenancy Reviewer
- Scope: resolve DR-0011 and authorize only the remaining PR-009 adapter and
  locked synthetic-benchmark implementation
- Explicitly accepted limitations: provider-managed STT model rotation and the
  circular synthetic benchmark
- Limitations: no credential, live request, provider spend, benchmark success,
  PR-009 acceptance, learner data, PR-010 or production approval
- Master-plan impact: none

Codex is the author and evidence recorder only, never the approver. The
consolidated development roles are not independent production review.

## Implementation status

The authorized adapter and runner implementation is present on the PR-009
branch:

- project-owned Azure Fast Transcription and real-time TTS REST adapters;
- exact endpoint-host, Central India, locale, API, voice, format and response
  validation;
- external mode-`0600` secret-file references with no `.env`, database,
  browser or log credential path;
- the exact 48-string corpus, two voices, three deterministic audio conditions
  and four fixed rate fixtures;
- 544-logical-operation, 1,088-attempt and `₹1,000` hard caps;
- aggregate-only safe reports and in-memory synthetic media;
- deterministic HTTP-fake coverage for the complete run and required fault
  categories; and
- a fail-closed `make ai-eval` command.

The ordinary application binding remains disabled. No Azure credential,
provider request or synthetic Azure media was used during implementation or
repository verification. Exact subscription/DPA/subprocessor evidence,
separate spend approval and a live benchmark remain outstanding.

## Verification

Before proposal review:

- validate front matter, headings, local links and final newline;
- hash the exact proposal and benchmark plan;
- confirm source/API/schema/configuration/environment files did not change;
- check every external claim against the current official source; and
- confirm DR-0011 is resolved only for policy selection and live provider
  requests remain disabled.

After acceptance and before a live benchmark:

- record the exact vendor/subscription/DPA/subprocessor evidence;
- implement the adapters, secret-reference boundary and synthetic runner;
- add adversarial unit and PostgreSQL tenant tests;
- update OpenAPI and generated clients for any API change;
- run `make verify`, `make e2e`, `make ai-eval` and `make build`; and
- preserve the safe benchmark report and exact configuration/corpus hashes.

## Follow-up

- Provision the dedicated Central India Azure Speech resource and private
  vendor evidence outside Git.
- Obtain separate approval for the benchmark's bounded provider spend.
- Implement only the accepted PR-009 provider adapters and locked benchmark.
- Keep PR-009 unaccepted until the benchmark and full repository verification
  pass.
- Obtain qualified legal and independent security, privacy, tenancy,
  accessibility and language review before any learner or production use.

## References

- [Azure Speech regions](https://learn.microsoft.com/en-us/azure/ai-services/speech-service/regions)
- [Azure Speech-to-Text data and privacy](https://learn.microsoft.com/en-us/azure/foundry/responsible-ai/speech-service/speech-to-text/data-privacy-security)
- [Azure Text-to-Speech data and privacy](https://learn.microsoft.com/en-us/azure/foundry/responsible-ai/speech-service/text-to-speech/data-privacy-security)
- [Azure Fast Transcription REST API](https://learn.microsoft.com/en-us/azure/ai-services/speech-service/fast-transcription-create)
- [Azure Text-to-Speech REST API](https://learn.microsoft.com/en-us/azure/ai-services/speech-service/rest-text-to-speech)
- [Azure Speech language and voice support](https://learn.microsoft.com/en-us/azure/ai-services/speech-service/language-support)
- [Microsoft Products and Services DPA](https://www.microsoft.com/licensing/docs/view/Microsoft-Products-and-Services-Data-Protection-Addendum-DPA)
- [Microsoft Azure Product Terms](https://www.microsoft.com/licensing/terms/en-US/productoffering/MicrosoftAzure/MCA)
- [Microsoft data access and subprocessors](https://www.microsoft.com/en-us/trust-center/privacy/data-access)
- [Google Speech-to-Text data usage](https://cloud.google.com/speech-to-text/docs/data-usage-faq)
- [Google Text-to-Speech data logging](https://cloud.google.com/text-to-speech/docs/data-logging)
- [Amazon Transcribe content opt-out](https://docs.aws.amazon.com/transcribe/latest/dg/opt-out.html)
- [Amazon Transcribe data input and output](https://docs.aws.amazon.com/transcribe/latest/dg/how-input.html)
- [OpenAI Whisper](https://github.com/openai/whisper)
- [Kokoro-82M model card](https://huggingface.co/hexgrad/Kokoro-82M)
- [Piper voice licence warning](https://github.com/OHF-Voice/piper1-gpl/blob/main/docs/VOICES.md)

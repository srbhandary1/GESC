---
status: accepted
date: 2026-07-31
deciders:
  - S R Bhandary as Technical Decision Owner
  - S R Bhandary as Security Reviewer
  - S R Bhandary as Privacy Reviewer
  - S R Bhandary as Tenancy Reviewer
consulted:
  - S R Bhandary requested the DR-0005 policy draft
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: false
approval_evidence:
  - APP-0011
approval_reference: Codex task 019fb263-fe9a-77f0-a1db-e3d563707715
---

# ADR-0007: Recording consent, retention, provider processing and deletion policy

## Status

Accepted on 2026-07-31 under
[APP-0011](../APPROVALS.md#app-0011). The approved proposal SHA-256 is
`e831e09092256d18804e5c6c7292a432ecfb71ac7d52544d16fde8d751a12ff9`.

This ADR resolves DR-0005 and establishes the recording-policy prerequisite
for a separately authorized PR-009 implementation.

This approval does not accept PR-008, authorize PR-009 implementation, select
a production provider, approve production, or permit audio/model data to train
a provider.

## Amendment D-0215 (29 August 2026)

For consented Talk and Role-play Perform turns, pressing and holding the speak
control and then releasing is the learner-initiated submit. Session-level
consent for `voice_upload_and_transcription` and
`conversation_provider_processing` is unchanged and still required before the
hold control is offered. Local playback and retry remain available after a
failed, low-confidence, or discarded turn, and Type instead remains. This does
not authorize always-on capture, silent upload, or using speech scores as
marks.

## Context

PR-009 is the first checkpoint that may accept learner audio on the server,
send speech or conversation content to a real provider, persist a recoverable
transcript or create generated speech. Those actions process personal learner
data and create security, privacy, tenancy, vendor and deletion obligations
that cannot be inferred from a generic consent checkbox.

The accepted baseline already requires:

- adults only in Phase 1;
- separate, clear Kannada notice and consent for recording and AI processing;
- learner-accessible withdrawal and deletion controls;
- no raw voice retention beyond the declared learning need;
- no training on learner audio or transcripts without separate explicit
  opt-in;
- project-owned provider ports and no provider SDK type in domain code;
- organization-scoped ownership and cross-tenant database constraints;
- encrypted private media with no stable public recording URL;
- bounded retries, idempotency and safe telemetry;
- advisory automated speech analysis; and
- no AI authority over marks, mastery, progression, attendance, enrollment,
  entitlements or other deterministic learning records.

India's Digital Personal Data Protection Act, 2023 and final Digital Personal
Data Protection Rules, 2025 have phased commencement. As of 31 July 2026,
several substantive notice, consent, security, erasure and rights provisions
are scheduled to commence eighteen months after the 13 November 2025 Gazette
publication. This ADR is a product and engineering control proposal, not legal
advice. Qualified Indian privacy counsel must confirm the applicable legal
basis, commencement, notices, retention floors, erasure exceptions, processor
contracts, grievance period and transfer restrictions before production.

## Decision

### Scope and data classification

PR-009 may introduce only the following recording-path data:

| Class | Examples | Sensitivity and authority |
|---|---|---|
| Local volatile capture | microphone frames, local playback blob | Sensitive learner audio; browser-memory only before upload |
| Source recording | validated encrypted server object | Sensitive personal data; temporary processing input, never authoritative learning evidence in PR-009 |
| Transcript candidate | provider text, confidence and word uncertainty | Sensitive derived personal data; advisory until learner confirms or corrects it |
| Confirmed transcript | learner-confirmed or learner-corrected text | Learner-authored conversation artifact; still non-authoritative for marks or mastery |
| Generated speech | synthesized assistant/correction audio | Private conversation media; not a learner recording and not a public asset |
| Processing evidence | consent version, state, provider/model version, latency, usage, digest and deletion receipt | Minimized operational evidence; no audio or transcript body |

Raw learner audio, transcripts and generated conversation audio are never
ordinary logs, audit payloads, outbox payloads, analytics properties, cache
entries, URLs, filenames or object keys.

PR-009 does not authorize human review, trainer playback, research use, model
training, long-term learner memory, pronunciation scoring or formal spoken
mastery.

### Consent model

Browser or operating-system microphone permission is not product consent.
Organization enrollment, trainer assignment, terms acceptance and an
administrator setting are not recording or provider-processing consent.

The server uses an append-only, versioned consent ledger. Each consent event
binds:

- the authenticated user, exact active membership and organization;
- the consent purpose and policy version;
- the exact notice revision and content hash;
- requested and resolved notice locales;
- grant, withdrawal or expiry state;
- server-recorded effective time;
- the first-party interaction identifier; and
- the safe actor and correlation identifiers.

The ledger must not store audio, transcript text, provider payloads, IP
addresses, device fingerprints or a copy of authentication credentials.

The learner must separately grant:

1. **`voice_upload_and_transcription`** — upload one learner recording and
   process it to obtain a transcript;
2. **`conversation_provider_processing`** — send the minimum transcript or
   assistant text needed for the selected speech/conversation provider path;
   and
3. **`transcript_history`** — optionally retain a confirmed/corrected
   transcript after the short recovery window.

The first two grants are required before their respective processing starts.
The third is optional and off by default. Refusal preserves a clear no-upload
or text-only path and cannot create a false failure, absence, score or mastery
penalty.

The server must also revalidate the existing Phase 1 adult-eligibility gate
before accepting consent or an upload. Missing, expired or under-18
eligibility fails closed; consent cannot override that gate.

Every consent request must:

- be a standalone clear notice available in Kannada and English, with the
  selected UI-language draft used only under the existing preview governance;
- itemize data, purpose, provider category, intended retention, deletion path,
  contact route and material consequence of refusing or withdrawing;
- require an unambiguous affirmative action with no preselected control;
- offer withdrawal as easily as grant;
- distinguish server processing from any future human review, research or
  training; and
- state that the current provider path is automated and that speech analysis
  is advisory.

No provider training, product model improvement, human quality review,
research corpus or marketing use is permitted in PR-009. A future use requires
a separately versioned, purpose-specific opt-in and a separately authorized
checkpoint. It cannot be bundled with access to core learning.

### Per-turn upload authorization

Active standing consent does not silently upload a recording. Each upload
requires a learner-initiated submit action. After D-0215, that submit is:

- **Hold-to-talk (Talk / Perform):** releasing the hold control after a
  consented session, with a concise reminder in the speaking room that letting
  go sends the turn; or
- **Tap capture (level-path local recording and failed-turn retry):** a
  separate upload or continue action after local playback/retry controls.

The reminder still covers:

- what will be uploaded;
- which processing purpose applies;
- whether transcript history is enabled; and
- how to cancel before submission.

The client sends no organization, learner, consent state, provider choice,
retention deadline or authority field. The server derives them from the
authenticated exact membership, policy and active consent records.

Upload commands use a UUID/ULID idempotency key and a server-created
interaction identifier. Exact replay returns the original state; a changed
payload under the same key conflicts. An upload cannot be reassigned to
another turn, learner, membership or organization.

### Capture, upload and validation boundary

Local capture remains volatile until the learner submits it. Cancellation,
retry, replacement, unmount and logout stop media tracks and revoke local
object URLs.

The server accepts only the explicitly supported browser/native audio
container and codec combinations. It must:

- cap declared and actual size at 10 MB;
- cap decoded duration at 120 seconds;
- stream to a private quarantine object rather than loading unbounded content
  into memory;
- reject multipart fields other than the one documented audio part and safe
  interaction metadata;
- ignore filenames as authority and never reuse them in storage;
- verify actual size, media signature, container, codec, channel/rate bounds
  and decodability rather than trusting MIME or extension;
- calculate a server-side content digest;
- run the approved malware/content-safety scan where applicable;
- normalize only after validation in an isolated bounded worker;
- reject malformed, polyglot, decompression-bomb and duration-mismatch input;
  and
- delete rejected quarantine objects promptly.

Object keys are random and contain no user, organization, phone, mission,
persona or filename data. Source and generated media use private buckets,
managed encryption at rest, explicit service identities and no public access.
Any delivery URL is purpose-specific, short lived, user bound and no-store.

### Recording and transcript lifecycle

The authoritative lifecycle is server owned:

```text
awaiting_upload
→ quarantined
→ validated
→ processing
→ transcript_available
→ awaiting_learner_confirmation
→ confirmed | corrected
→ source_deleted
```

Safe terminal alternatives are:

```text
rejected_deleted
provider_failed_recoverable
expired_deleted
withdrawn_deleted
```

The client cannot set state, provider, confidence, retention deadline,
deletion completion or retry count. Every transition is tenant scoped,
transactional where it changes more than one authoritative row, and protected
against terminal-state reversal.

Provider outage, malformed response and uncertain transcript never fabricate
success. The learner receives a text-only/re-record/correct-transcript path.
Only the server can schedule bounded processing retry; automatic retries stop
after two total provider attempts.

### Retention schedule

Policy values are centralized configuration with the following maximums.
Deployments may shorten them. Extending them requires new Security Reviewer
and Privacy Reviewer approval.

| Data | Default and maximum active retention |
|---|---|
| Unsubmitted local capture | Volatile browser memory only; remove on cancel, retry, unmount, logout or page termination |
| Rejected quarantine object | Delete immediately; operational target 15 minutes, hard maximum 1 hour |
| Successfully transcribed raw source audio | Delete after a valid transcript candidate is committed; operational target 15 minutes, hard maximum 1 hour |
| Raw source during recoverable provider failure | Encrypted recovery hold for at most 24 hours from server upload acceptance, then delete and require re-recording |
| Unconfirmed transcript candidate | At most 24 hours from availability, then delete unless the learner confirms/corrects it |
| Confirmed/corrected transcript with history disabled | Delete after the response/recovery transaction completes; operational target 15 minutes, hard maximum 1 hour |
| Confirmed/corrected transcript with explicit history consent | 30 days from turn completion, refreshed by neither login nor unrelated activity |
| Generated conversation/TTS audio | At most 24 hours from generation; regenerate rather than retain longer |
| Content-free provider/cost/state metadata | 30 days for product operations, then aggregate or delete |
| Consent events, withdrawal events and deletion receipts | One year after the event, containing no audio/transcript body |
| Security-access and processing logs required by approved legal/security policy | One year, minimized and content free |

Raw learner audio and generated conversation audio must not enter general
database backups, log archives, analytics systems or ordinary application
backups. The private media store disables versioning for these lifecycle
classes unless a reviewed legal requirement says otherwise.

Transcript backups are encrypted and isolated from ordinary application
access. A deletion tombstone prevents restoration into the live product.
Physically expired backup copies are removed within 30 days. A restore process
must reapply tombstones before the application becomes available.

If qualified counsel determines that an applicable law requires a longer
minimum retention for any class, production remains blocked until a narrowly
scoped legal-retention overlay is approved. The overlay must identify the
exact data class, legal authority, purpose, access role and expiry. It must not
silently expand product use, provider retention or learner visibility.

### Withdrawal and deletion

Withdrawal takes effect for new work immediately:

- reject new upload and provider commands for the withdrawn purpose;
- cancel queued work before provider dispatch;
- do not retry a provider call after withdrawal;
- request provider deletion for dispatched content;
- delete raw source and generated media from active storage;
- delete transcript content unless the learner still has a separate active
  transcript-history purpose or a documented legal-retention overlay applies;
  and
- retain only the minimized withdrawal and deletion evidence.

Withdrawal does not rewrite the historical fact that processing occurred
before withdrawal. It does not convert a prior transcript into authoritative
learning evidence.

Learners receive accessible controls to:

- view active recording/provider/history consents;
- withdraw each purpose independently;
- delete a specific recording/turn artifact;
- disable and erase transcript history;
- request export/correction/erasure through the privacy route; and
- see pending, completed, partially completed or legally restricted deletion
  states.

A deletion request is idempotent and transactionally creates a tombstone plus
outbox work before returning acknowledgement. The active-store objectives are:

- revoke signed media access immediately;
- delete source/generated media within 15 minutes;
- delete ordinary transcript content within 24 hours;
- send a provider deletion request within 15 minutes;
- obtain or record provider deletion outcome within 24 hours; and
- remove expired encrypted backup copies within 30 days.

Failure remains visible and retryable; the product must not report deletion
complete merely because work was queued. Legal retention returns a specific
restricted state and records the policy identifier and expiry without
disclosing sensitive investigation data.

Account closure and confirmed security reset stop new processing and apply the
same deletion workflow to every recording artifact. A tenant administrator
cannot suppress, complete or reverse a learner deletion request.

### Provider-processing boundary

A real provider adapter is eligible for benchmark only after a documented
review records:

- legal entity, service, API region and sub-processors;
- data-processing terms, transfer locations and government-access handling;
- no training, model improvement, human review or advertising use of submitted
  data;
- configured provider retention and deletion guarantees no longer than this
  ADR permits;
- encryption in transit and at rest, access control, incident notice and
  deletion API/support evidence;
- authentication, secret rotation, callback signing and replay protection;
- supported codecs, limits, timeout, retry and idempotency behavior;
- service disablement and export/deletion procedures; and
- cost, latency, accuracy, safety and failure results from the locked
  evaluation suite.

Until production approval, live-provider benchmarking uses synthetic,
licensed or separately consented evaluation audio only. It must not use real
learner production recordings.

Provider requests contain only:

- the validated/normalized bytes or minimum text required by the stage;
- a random interaction identifier that is not a platform user, membership or
  organization identifier;
- the required language/voice and bounded processing options; and
- callback/idempotency material with no learner identity.

Do not send learner names, phone numbers, organization names, authentication
credentials, broad profiles, curriculum bodies, unrelated conversation
history or internal authorization records. Provider payloads and raw
responses are not logged or retained after validated project-owned results are
created.

Provider selection is server owned and allowlisted by policy version. No
client or tenant request field selects a provider, model, region, retention
mode or safety setting. A fallback may use only another separately reviewed
adapter under an explicit server policy; it may never silently route data to
an unreviewed provider.

### Tenancy and authorization

Every consent, recording, transcript, processing run, generated media,
deletion request and deletion receipt is organization owned and bound to the
learner's exact membership and interaction.

Schema relationships use composite organization/resource identifiers so
PostgreSQL rejects cross-tenant associations. Application policies enforce
self-service learner access. PR-009 grants no trainer, operator or tenant
administrator access to learner audio or transcript bodies.

Workers reconstruct organization context from the trusted job envelope and
revalidate the resource relationship. Object-store and cache keys include
opaque tenant partitions, while external provider identifiers remain random
and non-personal.

Same-organization other-learner and cross-organization read, stream, retry,
correct, delete, callback and job-manipulation attempts fail closed without
revealing existence.

### Transcript recovery and learning authority

The provider transcript is always a candidate. The learner can:

- accept it;
- edit it before continuing;
- discard it and make a new recording; or
- continue through the documented text-only path.

The application preserves the immutable provider candidate digest and the
learner action category, but the learner-corrected text becomes the only text
eligible for later conversation context. UI copy must not imply that editing
audio changes a score or proves pronunciation.

PR-009 does not change marks, mastery, progression, first/latest/best evidence,
review schedules, attendance, enrollment or entitlements. `spoken_state`
remains `unassessed`. A future authoritative spoken-evidence policy requires a
separate accepted specification, validation against trained human raters and
explicit authorization.

### Observability, audit and incident response

Ordinary telemetry may contain only:

- opaque organization/interaction/provider-run identifiers;
- state and allowlisted failure category;
- provider/model/policy versions;
- bounded size/duration/latency/usage/cost numbers;
- retry/fallback category; and
- consent/deletion policy identifiers.

It must not contain audio, transcript or reply text, signed URLs, object keys,
provider payloads, filenames, learner names, phone numbers, IP addresses,
credentials or device labels.

Privileged access to consent/deletion evidence and any approved legal hold is
permission scoped, reason bound and audited. Raw content is never copied into
an audit event.

Operations need alerts and runbooks for:

- public-media exposure or cross-tenant access;
- stalled quarantine or deletion;
- provider retention/deletion failure;
- callback authentication/replay failure;
- unexpected provider routing or region;
- consent withdrawal races;
- object-store, queue or media-worker outage; and
- personal-data breach assessment and notification.

### Configuration and fail-closed rollout

All real provider adapters and uploads default to disabled. Enabling requires:

- an accepted version of this ADR;
- explicit PR-009 implementation authorization;
- reviewed provider configuration and secret references;
- a non-production feature flag scoped to designated test organizations;
- synthetic/consented evaluation fixtures;
- retention and deletion workers plus reconciliation;
- documented emergency disablement; and
- passing security, privacy, tenancy and affected accessibility tests.

A missing consent, policy version, retention worker, provider review,
encryption key, scanner, callback verifier, deletion capability or
organization context fails closed. It never falls back to unreviewed storage,
provider routing or false success.

Production additionally requires qualified legal review, independent
security/privacy/tenancy review, approved notices/translations, a vendor
register and contract, DPIA/threat model, restore/deletion drill, incident
runbook and explicit production release authorization.

## Alternatives considered

### Retain all raw recordings for learning history

Rejected because raw voice creates the highest privacy and breach impact while
PR-009 needs it only for bounded transcription/recovery.

### Delete raw audio before obtaining a validated transcript

Rejected because a transient provider failure would destroy an acknowledged
turn and violate the recovery requirement. The maximum 24-hour encrypted hold
supports bounded retry without becoming a recording library.

### Treat microphone permission or terms acceptance as consent

Rejected because platform permission and generic terms do not communicate the
specific upload, provider, retention and deletion purposes.

### Use one bundled consent for recording, provider processing, history and training

Rejected because optional uses would not be specific or freely separable.
Training and human review remain prohibited rather than merely unchecked.

### Let each provider's default retention control lifecycle

Rejected because the project remains accountable for processor behavior and
must enforce its own reviewed maximums.

### Keep transcripts indefinitely but delete audio

Rejected because transcripts remain personal learner content and long-term
history is not necessary for PR-009. Optional 30-day history is explicit and
bounded.

### Allow trainers to review failed transcripts

Rejected for PR-009 because human access changes purpose, permissions,
training, support and retention obligations. A later checkpoint needs a
separate policy and learner notice.

## Consequences

### Positive

- Learners control recording, provider processing and history independently.
- Raw audio is transient and never becomes an ordinary learning-history or
  backup asset.
- Provider and tenant boundaries are explicit and testable.
- Transcript recovery remains useful without making automated speech output
  authoritative.
- Deletion has measurable states, deadlines and reconciliation rather than a
  best-effort button.
- Training, human review and unrelated secondary uses are prohibited by
  default.

### Negative and trade-offs

- A learner may need to re-record after the 24-hour recovery limit.
- Optional history off by default limits long-running conversation context.
- Short media retention reduces provider-dispute and debugging material.
- Consent, deletion, tombstone, provider-contract and reconciliation records
  add implementation and operational complexity.
- Backups and restore procedures must honor deletion tombstones.
- A legal retention floor may conflict with the product-minimization schedule;
  production remains blocked until counsel resolves that exact scope.

## Security, privacy and tenancy impact

This ADR introduces processing of biometric-adjacent voice content and derived
personal text but minimizes collection, purpose, recipients and retention.
Encryption, quarantine, strict validation, private delivery, provider
contracts, consent proof, withdrawal races, deletion reconciliation and breach
response are mandatory.

All records remain organization scoped under ADR-0003. Tenant equality never
replaces self-only authorization. Cross-tenant database, HTTP, object-store,
worker, callback, cache and deletion tests are required.

One human may exercise consolidated development review roles under ADR-0001,
but that is not independent review. Qualified legal counsel and independent
security, privacy and tenancy reviewers remain production gates.

## Authoritative records and consistency impact

Consent events, recording states, transcript candidate digests, learner
confirmation/correction events, provider runs, deletion requests, tombstones
and deletion receipts are authoritative processing evidence.

Multi-write transitions use transactions and row locks. Upload, provider
dispatch, callback and deletion are idempotent. Outbox workers bridge database
and object/provider side effects. Reconciliation detects stranded objects,
provider jobs and incomplete deletion.

Published learning evidence remains unchanged. Neither audio nor transcript is
an authoritative learning outcome in PR-009.

Migrations must be additive. Rollback disables new upload/provider work,
drains or cancels in-flight jobs, keeps deletion/reconciliation workers
available and preserves tombstones until every retained object is gone. A
rollback must not strand readable media or resurrect deleted data.

## API, client and operational impact

An authorized PR-009 implementation would update OpenAPI and generated clients
for:

- consent grant/list/withdraw;
- recording creation/upload/finalization;
- turn processing and safe status history;
- transcript acceptance/correction/discard;
- private generated-media streaming;
- recording/history deletion and deletion status; and
- privacy export/correction/erasure request integration where in scope.

Every mutation requires current first-party authentication, exact organization
context, strict input allowlists, idempotency where replayable, rate limits,
private no-store responses and stable problem details.

The web/PWA and Android clients require accessible permission, consent,
recording, upload, processing, uncertainty, correction, fallback, withdrawal
and deletion states. No credential or raw audio enters browser persistent
storage or ordinary Capacitor preferences.

Operations require queue/deletion reconciliation, aggregate retention
inventory, provider SLA monitoring, cost/latency dashboards without content,
and auditable emergency disablement.

## Master-plan amendment

This ADR does not amend the master plan. It supplies the exact decision
requested by master-plan §§14.2–14.7, 15, 16.3–16.4, 17, 18, 20, 21 and 26
PR-009.

## Approval evidence

- Evidence ID: `APP-0011`
- Approved proposal SHA-256:
  `e831e09092256d18804e5c6c7292a432ecfb71ac7d52544d16fde8d751a12ff9`
- Acceptance date: 2026-07-31, Asia/Kolkata
- Human approver identifier: `S R Bhandary`
- Roles exercised: Technical Decision Owner; Security Reviewer; Privacy
  Reviewer; Tenancy Reviewer
- Scope: accept ADR-0007 and resolve DR-0005 only
- Limitations: consolidated development approval is not independent production
  review and does not authorize PR-009 implementation or production
- Master-plan impact: none

Codex is the author and evidence recorder only, never the approver.

## Verification

ADR acceptance evidence requires:

- validate front matter, headings, local links and final newline;
- verify the proposal hash independently;
- confirm no source code, API, migration, configuration or environment file
  changed; and
- review current official legal sources while retaining the qualified-counsel
  production gate.

An authorized PR-009 implementation specification must require:

- consent purpose/version/locale/hash, refusal, withdrawal and race tests;
- browser microphone lifecycle and no-persistent-audio tests;
- upload size, duration, MIME/signature, codec, decode, malformed and abuse
  tests;
- encryption, private object storage, short-lived streaming and no-public-URL
  tests;
- PostgreSQL composite-foreign-key and same-/cross-tenant tests for every
  resource;
- provider minimization, no-training configuration, timeout, malformed
  response, bounded retry, callback authentication and replay tests;
- transcript uncertainty, accept, correct, discard, re-record and text-only
  fallback tests;
- exact lifecycle, retention deadline, withdrawal, deletion, provider
  deletion, backup tombstone and reconciliation tests;
- assertions that audio/transcript/provider payloads do not enter logs, audit,
  outbox, analytics, cache or ordinary backups;
- proof that marks, mastery, progression, review, attendance, enrollment and
  entitlements do not change;
- OpenAPI/generated-client drift, accessibility and critical web/Android
  journey tests;
- locked STT/TTS quality, latency, cost, dialect/noise and failure benchmarks
  using synthetic/licensed/separately consented evaluation data; and
- `make verify`, `make e2e`, `make ai-eval` and `make build`.

## Follow-up

- Separately accept PR-008 at its exact implementation commit.
- Obtain Accountable Product Owner authorization for only PR-009 before
  implementation.
- Select and benchmark provider candidates only inside the accepted boundary.
- Obtain qualified legal and independent specialist review before production.

## References

- [Digital Personal Data Protection Act, 2023](https://www.meity.gov.in/static/uploads/2024/02/Digital-Personal-Data-Protection-Act-2023.pdf)
- [Digital Personal Data Protection Rules, 2025](https://www.meity.gov.in/static/uploads/2025/11/53450e6e5dc0bfa85ebd78686cadad39.pdf)
- [DPDP Act phased commencement notification, 13 November 2025](https://www.meity.gov.in/static/uploads/2025/11/c56ceae6c383460ca69577428d36828b.pdf)

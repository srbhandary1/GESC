# PR-009 secure speech and transcript recovery

## Authority and boundary

PR-009 is authorized by [APP-0012](APPROVALS.md#app-0012) against the accepted
PR-008 implementation at
`11f7b597ad2db3b8cf65a1394c1171a8280045d1`. Recording and provider behavior
is governed by
[ADR-0007](adr/0007-recording-consent-retention-provider-processing-and-deletion-policy.md).

This checkpoint may add secure learner-audio upload, reviewed STT/TTS adapter
boundaries, transcript confidence and recovery, text/TTS fallback and
privacy-safe telemetry. It does not authorize PR-010 orchestration,
pronunciation scoring, spoken mastery, provider training, human review,
production or a later checkpoint.

## Provider implementation and operational gate

[ADR-0008](adr/0008-azure-speech-development-provider-and-benchmark-policy.md)
and its locked benchmark plan resolve DR-0011 under
[APP-0013](APPROVALS.md#app-0013). They select Azure Speech in `centralindia`
for the PR-009 development benchmark only.

Before any live synthetic adapter call, the exact subscription, legal entity,
region, subprocessors, data-processing terms, transfer path,
no-training/human-review configuration, retention/deletion guarantees,
security behavior, credential reference, callback behavior and separate
provider-spend approval must be recorded and validated.

Until every operational gate is satisfied:

- every live provider binding and request remains disabled;
- no live provider call or credential use is permitted;
- deterministic testing adapters prove project contracts but do not count as
  the required live benchmark;
- upload is disabled by default and may be enabled only in local/testing for
  designated synthetic test organizations; and
- production fails closed.

This gate does not block provider-neutral consent, storage, validation,
lifecycle, transcript-recovery, deletion and tenancy implementation.

## Vertical slice

The initial provider-neutral slice is:

1. an authenticated, onboarded adult learner grants the separate
   `voice_upload_and_transcription` and
   `conversation_provider_processing` purposes from a versioned notice;
2. the learner locally records, plays back and explicitly submits one turn;
3. the browser converts the local capture to bounded mono PCM/WAV without
   persistent browser storage;
4. the server derives tenant and learner authority, validates active consent,
   verifies actual RIFF/WAVE structure, PCM codec, channels, sample rate,
   duration and size, then encrypts a random-key private quarantine object;
5. a testing-only deterministic adapter returns a transcript candidate and
   confidence through a project-owned port;
6. the source object is deleted immediately after candidate commit;
7. the learner accepts, edits or discards the candidate;
8. history-off content is removed after the response/recovery transaction;
9. deletion is idempotent, visible and reconciled; and
10. safe state/provider metadata contains no audio or transcript content.

Text entry remains available when permission, validation or provider
processing fails. TTS playback remains unavailable until a reviewed provider
or reviewed local model is selected; the UI must state that text fallback is
active.

## Data and lifecycle

The implementation uses append-only consent events and tenant-owned recording,
provider-run and deletion evidence. Transcript bodies and private media are
application-encrypted; ordinary logs, audit, outbox, analytics, cache and
public URLs contain neither.

The server-owned states are:

```text
quarantined
→ validated
→ processing
→ awaiting_learner_confirmation
→ confirmed | corrected | discarded
→ source_deleted
```

Safe alternatives are:

```text
rejected_deleted
provider_failed_recoverable
expired_deleted
withdrawn_deleted
```

The source-delete target is immediate after candidate commit with a hard
maximum of one hour. Recoverable provider failure may retain encrypted source
audio for at most 24 hours. Unconfirmed transcript content expires after 24
hours. Confirmed/corrected history is off by default and otherwise expires
after 30 days.

## API boundary

Authenticated, exact-organization endpoints:

```text
GET    /api/v1/voice-consents
POST   /api/v1/voice-consents
DELETE /api/v1/voice-consents/{purpose}
POST   /api/v1/conversation-sessions/{session}/recordings
GET    /api/v1/voice-recordings/{recording}
POST   /api/v1/voice-recordings/{recording}/transcript-review
DELETE /api/v1/voice-recordings/{recording}
GET    /api/v1/voice-recordings/{recording}/deletion
```

Mutations require first-party authentication, exact membership context,
strict allowlists, rate limits and UUID/ULID idempotency keys. The client
cannot select organization, learner, provider, model, region, retention,
state, confidence or cost.

## Verification

Required evidence:

- front matter/approval and scope checks;
- PCM/WAV valid, malformed, wrong-signature, wrong-codec, oversize and
  over-duration tests;
- consent refusal, purpose separation, notice hash, withdrawal and race tests;
- exact replay and changed-replay tests;
- encrypted object, random-key, immediate source deletion and no-public-URL
  tests;
- transcript accept, edit, discard, expiry and history-off deletion tests;
- provider outage, malformed result, bounded retry and recoverable-state
  tests;
- same-tenant other-learner and cross-tenant database/HTTP tests;
- assertions that no content enters audit, outbox, logs or telemetry;
- proof that learning attempts, mastery and review records do not change;
- OpenAPI/generated-client, localization, accessibility, unit and Chromium
  coverage;
- `make verify`, `make e2e` and `make build`; and
- fail-closed `make ai-eval` preflight and the locked benchmark only after all
  APP-0013 operational evidence and spend gates pass.

## Exit gate

The provider-neutral implementation and disabled adapter/runner code may be
repository-verified, but PR-009 cannot be accepted as complete until the
reviewed STT and TTS adapters have live locked synthetic benchmark evidence.
Production remains separately prohibited.

## Current verification state

The provider-neutral vertical slice is implemented and repository-verified.
`make verify` passes: Composer/pnpm locked dependency audits, Pint, PHPStan,
66 SQLite tests/365 assertions, 173 PostgreSQL tests/2,016 assertions,
localization governance, OpenAPI/generated-client parity, ESLint, recursive
TypeScript and 99 workspace tests all pass. `make build` and 10 axe-checked
Chromium journeys also pass; Playwright uses only a temporary
`127.0.0.1:4173` preview listener and stops it after the run.

ADR-0008 is accepted under APP-0013. The Azure Fast Transcription and
real-time TTS REST adapters, exact 48-string corpus, deterministic audio
transforms, bounded runner, safe aggregate report and `make ai-eval` command
are implemented but not live-benchmarked. The application provider binding
remains disabled: only the dedicated command constructs these adapters after
every operational gate passes.

Implemented adversarial coverage includes malformed RIFF/WAV input, wrong
codec/channel/rate, truncated and unknown chunks, provider outage, malformed
provider results, bounded retry, encrypted recoverable source retention,
immediate successful-source deletion, scheduled expiry, transcript correction,
history-off content deletion, explicit deletion evidence, cross-tenant denial
and unchanged deterministic learning tables.

The following exit items intentionally remain blocked by DR-0011 and
production review:

- exact reviewed STT and TTS implementations and locked synthetic benchmarks;
- generated TTS media and playback;
- live provider credentials, callbacks, deletion reconciliation and outage
  exercises;
- isolated streaming quarantine/normalization and an approved malware scanner;
- a reviewed production private object store, managed encryption/service
  identities, backup tombstone replay and restore drill; and
- independent security, privacy, tenancy, accessibility and language review.

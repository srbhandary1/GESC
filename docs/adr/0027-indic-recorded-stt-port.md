---
status: accepted
date: 2026-08-29
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
consulted:
  - Cursor agent as author and evidence recorder, not approver
informed: []
amends_master_plan: false
approval_evidence: []
approval_reference: Exact human instruction in the active Cursor conversation to start a cheaper-than-Azure Indian STT framework
---

# ADR-0027: Indic HTTP recorded-STT port behind the existing voice adapter

## Status

Accepted on 29 August 2026. Recorded as D-0217. Wave 2A amendment
1 September 2026 (25-second clip cap, Azure STT failover, ledger/policy
allowlist, Sarvam named in consent). This ADR amends
[ADR-0008](0008-azure-speech-development-provider-and-benchmark-policy.md) only
to allow an **optional** recorded-STT driver. It does not replace the locked
PR-009 Azure Speech synthetic benchmark, does not authorize live Indic STT on
soft-launch, and does not authorize production/Play.

## Context

Hold-to-talk uploads use `RecordedSpeechToTextProvider`. Soft-launch Direct is
Azure Speech (APP-0068). The product owner wants a cheaper Indian STT path
later (Indic translate-to-English, so Kannada/code-mixed speech can still
yield an English turn for Maya). SuperNova-style public stacks cite Sarvam STT
in translate mode. The domain must not import a vendor SDK type.

## Decision

1. Keep `RecordedSpeechToTextProvider` as the only recorded-STT port. Azure
   remains the live soft-launch driver until `VOICE_PROVIDER_DRIVER=indic` is
   set with a reviewed secret file outside the repository.
2. Add `IndicHttpSpeechToTextProvider`: HTTPS multipart compatible with
   Sarvam-style REST (`file`, `model`, `mode`, `language_code` → `transcript`)
   without a Sarvam SDK. `mode` is locked to `translate` so the product
   learning language stays English (ADR-0008).
3. Secrets use a mode-0600 file outside the git tree (same class of control as
   Azure Speech). Endpoints must be HTTPS with an allowlisted host. CI uses
   `Http::fake` only.
4. Production never binds this driver. Local, testing, and soft-launch may
   bind it when upload is enabled and the driver is `indic`. Soft-launch
   default remains Azure.
5. Consent, retention, deletion, and advisory-only AI from ADR-0007 are
   unchanged. Switching STT vendors is not a licence to keep audio longer or
   to train models.
6. Live Indic enablement still requires a later spend, DPA/subprocessor, and
   organization-allowlist review. This ADR only builds the adapter.

## Alternatives considered

### Alternative A — browser Web Speech API

Rejected as the product path. Quality and privacy are not equivalent.

### Alternative B — vendor SDK in domain modules

Rejected. Architecture rules require project-owned HTTP mapping.

## Consequences

### Positive

- A complete vertical slice exists to point at a cheaper Indic HTTP STT later
  without rewriting the voice upload path.

### Negative and trade-offs

- Translate-mode English is not a substitute for pronunciation scores or
  marks. Confidence may be defaulted when the vendor omits it.
- REST clip limits (for example 30s on some Indic APIs) may require a later
  batch/websocket checkpoint for long recordings.

## Amendment — Wave 2A (1 September 2026)

Recorded Speak clips are capped at 25 seconds (`voice.maximum_duration_ms`;
hold-to-talk auto-stops in the client). Learn tap-to-record stays 120 seconds.
`IndicWithAzureFailoverSpeechToTextProvider` retries once on Azure Fast
Transcription after a transient Indic HTTP/connection/transport failure.
`ai_cost_ledger` and `ConversationProviderUsagePolicy` allow `indic-http-stt`
with model prefix `indic-http-stt-v1:` and failover key
`azure-speech-soft-launch-stt`. Consent notices name Sarvam as the
transcription processor and Azure as failover. Soft-launch remains Azure
until `VOICE_PROVIDER_DRIVER=indic` (Wave 6). This does not amend the master
plan or authorize production/Play.

## Security, privacy and tenancy impact

- No credentials in git. Fail closed without a valid secret file.
- Audio still uploads to the project API first; the adapter forwards only
  after ADR-0007 consent. Raw audio deletion policy is unchanged.
- Organization allowlist (`VOICE_ALLOWED_ORGANIZATION_IDS`) still gates
  learner upload.

## Authoritative records and consistency impact

Provider usage records `indic-http-stt` with model prefix `indic-http-stt-v1:`.
Wave 2A extends the PostgreSQL `ai_cost_ledger` CHECK for that key and for
Azure recorded-STT failover.

## API, client and operational impact

No public API shape change. Configuration keys are documented in
`apps/api/.env.example`.

## Master-plan amendment

This ADR does not amend the master plan.

## Approval evidence

Accountable Product Owner instruction to start the cheaper-than-Azure Indian
STT framework and build it.

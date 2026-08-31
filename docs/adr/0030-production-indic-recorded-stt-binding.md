---
status: accepted
date: 2026-08-31
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
consulted:
  - Cursor agent as implementer and evidence recorder, not approver
informed: []
amends_master_plan: true
approval_evidence: []
approval_reference: Decision D-0243 and the APO handover-scope selection in the active Cursor conversation
---

# ADR-0030: Fail-closed production binding for Indic recorded STT

## Status

Accepted on 31 August 2026 and recorded as D-0243. This ADR narrowly amends
ADR-0027 items 4 and 6: production may bind the existing project-owned Indic
HTTP recorded-STT adapter. It does not enable a deployed environment, add a
credential to the repository, authorize Play release, or weaken consent,
retention, deletion, tenant scoping, endpoint validation, or secret handling.

## Context

ADR-0027 built a Sarvam-compatible translate-to-English adapter but excluded
the production Laravel environment. The Accountable Product Owner requires the
handover build to contain a production-capable Sarvam STT path while retaining
all fail-closed privacy and security controls. Azure remains the tutor TTS
provider and the only source of optional viseme metadata; learner recordings
are not sent to the TTS or viseme path.

## Decision

1. Permit `VOICE_PROVIDER_DRIVER=indic` to bind
   `IndicHttpSpeechToTextProvider` in the Laravel `production` environment.
2. Permit the voice consent/upload lifecycle in production only when
   `VOICE_UPLOAD_ENABLED=true` and the learner's organization is explicitly
   present in `VOICE_ALLOWED_ORGANIZATION_IDS`.
3. Keep provider preflight fail-closed. Live requests require:
   - HTTPS endpoint and exact host allowlist;
   - a readable, owner-controlled mode-0600 secret file outside the repository;
   - `api-subscription-key` authentication;
   - translate-to-English mode and valid model/language configuration.
4. Pin the documented handover defaults to
   `https://api.sarvam.ai/speech-to-text`, host `api.sarvam.ai`, and
   `saaras:v3`. The secret remains unset.
5. Do not automatically fail over a learner recording to another subprocessor.
   Provider failures use the existing bounded retry and learner-safe failure
   path, avoiding an undeclared secondary disclosure.
6. Keep Azure reply synthesis and client viseme animation independent from
   recorded learner STT. This decision does not route learner audio through
   `AzureWavVisemeExtractor`.

## Security, privacy, and tenancy impact

- No credential is accepted directly from a committed environment template.
- Redirects are refused, TLS verification stays enabled, response size and
  request time are bounded, and no raw audio or transcript is logged.
- Existing provider consent is checked immediately before each provider call.
- Encrypted source audio follows the existing deletion lifecycle and is
  deleted after successful transcription.
- An empty organization allowlist fails closed in production.

## Operational activation

The Accountable Product Owner subsequently selected the current workstation
soft-launch stack as the handover runtime and authorized live activation. Its
outside-repository environment now selects the Indic driver and existing
single-organization allowlist. Configuration preflight rejects incomplete or
unsafe values before any provider request. A synthetic, non-learner
Azure-audio → Sarvam STT smoke verified the live endpoint without persisting
audio or printing transcript text. No separate remote production deployment
target exists.

The credential supplied during activation was exposed in the operator
conversation and must be revoked and replaced in the external mode-0600 secret
file before handover.

## Master-plan amendment

This ADR amends master plan §14.8 to permit fail-closed production selection of
the existing Indic HTTP recorded-STT adapter. The master plan is updated to
version 3.10.

## Verification

Tests cover production provider binding, disabled-by-default behavior,
production organization scoping, complete preflight, HTTPS/host validation,
external-secret validation, and omission of the interaction identifier from
the multipart request.

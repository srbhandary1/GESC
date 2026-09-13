# DR-0028 — Ordinary learner Speech activation for the closed-group pilot

**Status:** Resolved for invite-only Direct soft-launch under [APP-0068](APPROVALS.md#app-0068) / D-0212. Production, Play, independent privacy-firm review and a separate Phase B supervised smoke remain open.  
**Needed by:** Student-helpful Confidence Conversation voice proof and F0  
**Authority affected:** ADR-0007, ADR-0008, ADR-0011, APP-0053, master plan
 §§14, 18, 21 and 22  
**Opened:** 12 August 2026

## 1. Decision needed

The repository has a complete consent, upload, quarantine, transcript-review,
retention and deletion lifecycle plus a project-owned Azure recorded-speech
adapter. Ordinary application dependency injection still binds the disabled
recorded-speech provider, and `VOICE_UPLOAD_ENABLED` remains false. The current
soft launch therefore proves typed or previously confirmed text turns, not an
ordinary learner speaking to the Friend and receiving an AI-spoken reply.

Enabling this path processes learner voice and derived transcript content with
an external provider. ADR-0007/0008 deliberately require an exact pilot scope,
independent privacy/legal/security evidence, cost and incident controls, and a
separate authorization. Those choices cannot be inferred from the existing
synthetic benchmark or APP-0053 model spend approval.

## 2. Verified current evidence

- Azure Speech development resource and credential evidence exist outside Git
  for the accepted `centralindia`, `en-IN` synchronous operations.
- The locked synthetic Azure benchmark passed its recorded technical gates;
  it is not learner-accent, device, network, language or production evidence.
- Consent purposes are separate: `voice_upload_and_transcription`,
  `conversation_provider_processing` and optional `transcript_history`.
- Source audio and transcript retention/deletion/reconciliation behavior is
  implemented and covered by tenant and lifecycle tests.
- `RecordedSpeechToTextProvider` is disabled in ordinary application DI;
  Azure is used only by the separately gated benchmark path.
- Confidence Conversation keeps typing and text-first playback available.
- No live learner audio request, learner transcript provider request or
  ordinary-app Azure Speech spend is authorized or claimed.

## 3. Options

### Option A — Bounded Direct-organization voice pilot (recommended)

Authorize a two-phase vertical slice with all of these limits:

1. Scope only the platform-owned Global English Direct organization and the
   two existing credential-ready, consenting adult closed-group learners.
   No partner tenant, minor, public signup or administrator-selected learner.
2. Use only the reviewed Central India Azure Speech resource. Learner STT is
   synchronous Fast Transcription `2025-10-15`, fixed `en-IN`, with no language
   identification, pronunciation scoring, custom model, logging opt-in, human
   review or provider training. English model/Friend TTS is fixed prebuilt
   `en-IN-NeerjaNeural`; L1 guidance remains text-first.
3. Require active adult eligibility, separate upload/transcription and
   conversation-provider-processing consent, and explicit per-turn submit.
   Transcript history remains off unless independently granted. The learner
   can review, correct, discard or re-record before confirmed text reaches the
   conversation model.
4. Retain and delete audio, transcripts and generated media exactly under
   ADR-0007. No raw content enters logs, audit, analytics, outbox, cache,
   filenames, object keys or ordinary backups. Deletion and reconciliation
   workers must be healthy before activation and remain available on rollback.
5. Add an explicit ordinary-app Azure recorded-speech driver and separate
   kill switch. Both default disabled in every environment. Activation is
   allowed only in `soft-launch`, for the exact organization allowlist, after
   configuration/evidence validation; missing evidence fails closed.
6. Cap the initial authorization at 20 submitted learner recordings, 120
   seconds per recording, two provider attempts per recording and ₹1,000 INR
   total Speech spend. Stop automatically on any count, duration, attempt or
   spend cap; no silent fallback provider is permitted.
7. Before any live learner request, record independent privacy/DPDP/legal and
   security review of the exact Azure subscription/DPA/subprocessor snapshot,
   notices, consent, transfer, retention, deletion, breach and credential
   controls. Record the exact reviewers and reviewed revision in APPROVALS.
8. Phase A may implement only the fail-closed binding, configuration, contract,
   tests, readiness checks and runbook. Phase B requires a second explicit
   live-smoke authorization after `make verify`, `make e2e`, `make ai-eval`,
   `make build`, deletion reconciliation and no-call readiness pass.
9. The live smoke is one supervised turn per consenting learner. Record only
   content-free correlation, provider, latency, usage, cost, state and deletion
   evidence. Stop on privacy, tenancy, content leakage, unexpected region,
   retention, quality, cost or deletion failure.
10. AI remains advisory. Voice/transcript output cannot write marks, mastery,
    review schedules, attendance, progression, enrollment, entitlements or
    unlocks. Text practice remains available before, during and after rollout.

This option does not authorize production, Google Play, broader pilot rollout,
multi-locale Azure guidance TTS, trainer access to learner content, research,
model improvement or use of learner recordings as authoritative evidence.
It also does not waive the separate Azure conversation-model privacy gate:
modified-abuse-monitoring/`ContentLogging=false` evidence and its live-request
authorization must pass before a Speech turn can be represented as an end-to-
end live AI Friend turn. A Speech-only smoke closes only Speech transport and
lifecycle evidence.

### Option B — Keep ordinary learner Speech disabled

Continue typed/confirmed-text Confidence Conversation and on-device optional
playback. Complete independent reviews and representative consented evaluation
before proposing a live learner-audio scope. This preserves the current
privacy posture but leaves the full voice-first student-helpful claim open.

## 4. Recommendation and authorization text

Option A is recommended because it is small enough to stop safely while
proving the missing ordinary-product voice lifecycle on intended pilot users.

To authorize only Phase A implementation:

```text
Approve DR-0028 Option A Phase A exactly as written. Live learner Speech
requests remain unauthorized pending the required independent evidence and a
separate Phase B authorization.
```

After Phase A and every independent prerequisite passes, the later Phase B
statement must identify the exact commit and evidence hashes. Do not record or
use a Phase B statement before those prerequisites exist.

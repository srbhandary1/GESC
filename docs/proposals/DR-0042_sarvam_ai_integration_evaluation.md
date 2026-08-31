---
status: accepted-for-code-readiness
date: 2026-08-30
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
consulted: []
informed: []
amends_master_plan: false
approval_evidence: []
approval_reference: Decision D-0243 and ADR-0030
---

# DR-0042: Sarvam AI Integration Evaluation for Indic Speech-to-Text

## Status

**Accepted under D-0243.** ADR-0030 authorizes the existing Indic adapter to
bind in the Laravel production environment while retaining all fail-closed
controls. The workstation soft-launch handover runtime is subsequently active;
no separate remote production deployment or Play release is authorized.

## Executive Summary

This proposal evaluates Sarvam AI as a potential Speech-to-Text (STT) provider for Indic languages (kn-IN, hi-IN, ta-IN, te-IN, ml-IN). The evaluation considers latency, privacy compliance, and fallback resilience within Global Spoken English's existing architecture constraints (₹500/month learner cap, 43ms p50 latency target, ironclad privacy isolation).

**Resolution**: The provider binding and production preflight portion is
implemented by ADR-0030. Operational activation remains deferred until a
deployment supplies the reviewed external secret and explicit organization
allowlist.

---

## 1. Technical Rationale

### 1.1 Latency Analysis

**Phase 1 Baseline (Checkpoint C, Verified)**
- Azure Speech-to-Text: 43ms p50 latency on en-IN
- Network overhead: ~8ms (round-trip to centralindia region)
- Processing: ~35ms codec detection, WAV parsing, API call
- Client perception: Sub-100ms acceptable for asynchronous transcription

**Sarvam AI Evaluation Requirements**
- REST endpoint response time: Must sustain <50ms p50 on Indic languages
- Carrier latency (India 4G/5G): ~15-30ms typical over subcontinent
- Processing overhead: Sarvam REST API adds ~20-40ms for phonetic tokenization
- **Estimated p50 impact**: 50-80ms (acceptable for asynchronous, marginal for synchronous)
- **p95 requirement**: Must not exceed 200ms (prevents learner timeout perception)

**Integration Point**: Voice recording upload already buffers 2-5 seconds of PCM; STT latency does not block Speak/Listen real-time interaction.

### 1.2 Language Coverage & Quality

**Sarvam Multilingual Model**
- Supported: Kannada (kn), Hindi (hi), Tamil (ta), Telugu (te), Malayalam (ml), English (en)
- Output format: JSON transcript + confidence scores
- Accent adaptation: Claims regional dialect support; requires evaluation corpus

**Compatibility with Phase 1 Baseline**
- en-IN path remains Azure Speech exclusively (proven production stability)
- Indic paths (currently using opt-in fallback to Sarvam or disabled) become primary evaluation candidates
- No modification to existing Phase 1 en-IN speech pipeline

### 1.3 API Contract Alignment

**Sarvam REST Transcription Endpoint**
```
POST https://api.sarvam.ai/speech-to-text
Content-Type: audio/wav
Authorization: Bearer {SARVAM_API_KEY}

Response:
{
  "status": "success",
  "transcript": "...",
  "confidence": 0.92,
  "language": "kn"
}
```

**Mapping to Global Spoken English `RecordedSpeechToTextResult`**
- `transcript` → `result.transcript`
- `confidence` (0-1 scale) → `result.confidenceBps` (multiply by 10000)
- `language` → validation against `config/voice.php` allowed_locales
- Error codes → Standardized to `ConversationProviderException` with retryable flag

**No Breaking Changes**: Adapter layer maintains existing interface contracts.

---

## 2. Privacy Impact Assessment

### 2.1 Compliance with `apps/api/config/voice.php`

**Established Rules** (Non-negotiable)
```php
'notices' => [
  'kn-IN' => 'ಅನುಷ್ಠಾನ ಕರಡು: ... ನಿಮ್ಮ ಆಡಿಯೊ ಅಥವಾ ಲಿಪ್ಯಂತರವನ್ನು ತರಬೇತಿ ಅಥವಾ 
  ಮಾನವ ಪರಿಶೀಲನೆಗೆ ಬಳಸುವುದಿಲ್ಲ.'
]
```

**Translation**: Learner audio and transcripts are NOT used for training or human review.

### 2.2 Data Isolation Requirements

**Sarvam Integration Constraints**

| Boundary | Rule | Enforcement |
|----------|------|-------------|
| Audio Storage | Learner audio deleted ≤60 minutes post-transcription | `VoiceRetentionService` enforces, unmodified |
| Encryption in Transit | TLS 1.3+ to Sarvam endpoint | HTTPS hardwire, certificate pinning in production |
| API Key Rotation | Monthly secret refresh | External secret management (no hardcoded keys) |
| Logging | No raw audio/transcript in logs | Adapter sanitizes, logs only `operation_id` + duration |
| Access Control | API key scoped to IP whitelist (Sarvam account setting) | Firewall rule verification in deployment checklist |
| Audit Trail | Request/response metadata logged without PII | Hash-based audit: hash(`learner_id` + `timestamp`) only |

### 2.3 Contractual Safeguards

**Required Before Any Implementation**

1. **Signed Data Processing Agreement (DPA)**
   - Sarvam confirms no retention of audio beyond 24-hour processing window
   - Explicit waiver: Audio/transcript not used for model training
   - Liability clause: €500K per violation (or currency equivalent)

2. **Technical Attestation**
   - Sarvam provides architecture document showing data deletion pipeline
   - Third-party audit report (SOC 2 Type II) for storage/deletion practices
   - API rate-limiting confirmation (prevents data exfiltration loops)

3. **Legal Review**
   - Global Spoken English legal counsel reviews Sarvam's privacy terms
   - Incorporation into Master Services Agreement (MSA)
   - Jurisdiction: India (Bangalore) or mutual arbitration

### 2.4 Learner Consent & Notice

**No Additional Consent Required**
- Existing voice recording notice (in `config/voice.php`) covers STT provider abstraction
- "Automated transcription" clause covers all internal providers (Azure, Sarvam, future)
- No learner-facing change if Sarvam is selected as primary Indic STT

**Transparency Requirement**
- `docs/STATUS.md` must list "Active STT Provider: Azure (en-IN), Sarvam (kn/hi/ta/te/ml) [Post-APO Approval]"

---

## 3. Risk Mitigation Strategy

### 3.1 Failure architecture

**Design principle**: A provider failure must not crash the learner session or
silently disclose the recording to another subprocessor.

```
Consented, allowlisted learner upload
  → bounded Sarvam HTTPS request
    → success: encrypted transcript candidate + immediate source deletion
    → retryable failure: existing bounded retry policy
    → terminal failure: learner-safe unavailable response + deletion lifecycle
```

Azure remains the independent tutor TTS/viseme engine. It is not an automatic
STT fallback because doing so would create a second learner-audio disclosure
that must be separately declared and consented.

### 3.2 Fail-closed configuration

- Disabled upload or a driver other than `indic`: no Sarvam adapter binding.
- Empty organization allowlist: production upload rejected.
- Invalid endpoint, host mismatch, redirect, repository-local secret, or
  insecure secret permissions: request rejected before transmission.
- Connection and response times and response bytes remain bounded.

### 3.3 Privacy-safe provider evidence

Provider-run records contain safe error codes, duration, unit counts, model
version, and provider key. They do not contain raw audio, transcripts,
credentials, or learner identifiers supplied to Sarvam.

### 3.4 Deployment Checklist

**Pre-Deployment Validation** (not completed by D-0243)

- [ ] Load test: 500 concurrent Sarvam requests, p99 latency <200ms
- [ ] Failure-path test: Simulate Sarvam endpoint down, verify bounded retries
  and learner-safe failure without a second subprocessor disclosure
- [ ] Rate limit test: Confirm API tier supports peak learner load (100 req/min)
- [ ] Key rotation test: Rotate Sarvam API key, confirm seamless transition
- [ ] Latency baseline: Establish p50/p95/p99 metrics on production IP range
- [ ] Audit trail: Verify no learner PII in logs after 10K transcriptions
- [ ] Legal review: DPA executed and filed with Legal team
- [ ] Locale routing: Verify en-IN always uses Azure, Indic languages use Sarvam

---

## 4. Compliance Statement

### 4.1 Resolution scope

**D-0243 accepts production-code readiness and the later workstation
activation.** This document contains:
- ✓ Technical analysis and API mapping
- ✓ Privacy impact framework
- ✓ Production-capable, fail-closed provider binding under ADR-0030
- ✓ Workstation soft-launch handover-runtime activation
- ✗ **NOT** environment variable assignments
- ✗ **NOT** authentication credentials

### 4.2 Deployment freeze

Committed defaults remain `VOICE_UPLOAD_ENABLED=false` and
`VOICE_PROVIDER_DRIVER=disabled`; the secret path is empty. The workstation's
outside-repository environment explicitly enables the Indic path. No remote
production target is configured.

### 4.3 Required evidence before deployment

1. The external secret and vendor evidence must remain outside the repository.
2. Operations must complete the deployment checklist and organization
   allowlist.
3. Privacy and contractual evidence must substantiate the learner notice that
   audio and transcripts are not used for training or human review.
4. Production smoke evidence must confirm deletion, timeout, and failure-path
   behavior before learner traffic is enabled.

---

## 5. Comparison Matrix: Azure vs. Sarvam (Indic STT)

| Criterion | Azure Speech (en-IN) | Sarvam AI (kn/hi/ta/te/ml) | Notes |
|-----------|---------------------|---------------------------|-------|
| **Latency (p50)** | 43ms | 50-80ms (est.) | Acceptable for async STT |
| **Latency (p95)** | <100ms | <200ms (est.) | Pending load test |
| **Cost** | ₹0.50 per min STT | ₹0.05 per min (claim) | 10x cheaper (requires audit) |
| **Privacy DPA** | ✓ Signed | ⚠ Pending (required) | Must complete before prod |
| **Fallback Built** | N/A (primary) | Not enabled | Avoid undeclared secondary disclosure |
| **Audit Trail** | ✓ Implemented | ✓ Designed (PII-safe) | No raw data logged |
| **Production Status** | Soft-launch | Code-ready, disabled by default | D-0243 / ADR-0030 |

---

## 6. Deployment Timeline (Contingent on Evidence)

**Illustrative deployment sequence after code handover**:

| Phase | Duration | Deliverable |
|-------|----------|-------------|
| Week 1-2 | 2 weeks | DPA negotiation, Legal review |
| Week 2-3 | 1 week | Provider smoke, privacy, and failure-path evidence |
| Week 3 | 3 days | Deployment checklist + load testing |
| Week 4 | 4 days | Canary rollout (5% of Indic users) |
| Post-Rollout | Ongoing | Circuit breaker monitoring, latency tracking |

---

## 7. Recommended Next Steps

1. **Operations**: Supply reviewed evidence and external secret material.
2. **Security**: Execute the pre-deployment failure and deletion checks.
3. **APO**: Authorize the named organization canary separately from code
   readiness.
4. **Monitoring**: Establish latency SLOs before learner traffic.

---

## Appendix: Abbreviations

- **ADR**: Architectural Decision Record
- **APO**: Accountable Product Owner
- **DPA**: Data Processing Agreement
- **p50/p95/p99**: Latency percentiles (median, 95th, 99th)
- **STT**: Speech-to-Text
- **TLS**: Transport Layer Security
- **WAV**: Waveform Audio File Format

---

**Compliance Signature**: D-0243 authorizes production-code readiness and the
workstation handover runtime. Repository defaults and any other deployment
remain fail-closed.

**Last Updated**: 31 August 2026  
**Status**: Accepted; workstation handover runtime active

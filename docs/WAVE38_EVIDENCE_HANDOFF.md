# Wave 3.8 evidence handoff

## Purpose and authority

This document describes the outside-repository Wave 3.8 packet consumed by
`speech-wave38:preflight`. It does not provide vendor evidence, approve spend,
authorize a credential, or permit a provider request.

Wave 3.8 is the privacy/account gate that must pass **before** any later
HTTP-enablement approval for ElevenLabs or Sarvam challenger dispatch.
Closing the packet is not processing authority and does not change live Speak.

The controlling records remain
[ADR-0038](adr/0038-multiprovider-speech-routing-kannada-help-and-first-audible-gate.md),
[APP-0078](APPROVALS.md#app-0078), [APP-0079](APPROVALS.md#app-0079) and
[WORLD_CLASS_PRODUCTION_STEPS.md](WORLD_CLASS_PRODUCTION_STEPS.md) item 3.8.

No value shown below is live evidence. Do not copy templates into an
authorized packet. Do not invent DPA hashes.

## External-file boundary

The Wave 3.8 files must:

- use absolute paths outside the repository;
- be regular files rather than symbolic links;
- be owned by the process user;
- have Unix mode `0600` for files and `0700` for the packet directory;
- remain absent from Git, `.env`, logs and command output; and
- contain no secrets, audio, transcripts or personal learner data.

Canonical workstation directory:

`$HOME/ge-speech-benchmark/evidence/wave38/`

Required siblings:

| File | Role |
|---|---|
| `wave38-status.json` | Packet index and inherited Azure paths |
| `elevenlabs-vendor-evidence.json` | Exact products, India host, Zero Retention, executed-document hashes |
| `elevenlabs-spend-approval.json` | ₹500 benchmark cap |
| `elevenlabs-rotation-receipt.json` | Previous key revoked; no secret value |
| `elevenlabs-deletion-test.json` | Content-free deletion-test receipt |
| `sarvam-vendor-evidence.json` | Executed DPA hash for the selected products |
| `sarvam-spend-approval.json` | ₹500 monthly cap |

Azure Speech current evidence is referenced from
`inherited_azure.speech_vendor_evidence`. Wave 3.8 inherits the live APP-0064
soft-launch packet
(`$HOME/ge-speech-benchmark/evidence/soft-launch-guidance-tts-vendor-evidence.json`,
`valid_until` **2026-09-14T13:15:00Z**), not the expired PR-009 benchmark
packet. Renew APP-0064 before that instant.

## India residency and Zero Retention

Official ElevenLabs documentation states:

- India isolated API host is `api.in.residency.elevenlabs.io`;
- isolated environments use a **different** API key from `elevenlabs.io`;
- default hosting is the United States;
- Zero Retention is optional Enterprise and is **not** automatic with residency.

The only allowed challenger host in this repository is
`api.in.residency.elevenlabs.io`. Public documentation hashes are source-docs,
not an executed DPA and not proof that the live workspace is the India
environment.

## Rotation and deletion

The D-0305 ElevenLabs test key was disclosed in chat and is treated as
compromised. APP-0081 defers rotation and the deletion test until **before
live deployment**, not during development. Those items remain hard blocks on
HTTP enablement and production:

1. rotate and revoke the disclosed key;
2. store the replacement only at
   `$HOME/ge-speech-benchmark/secrets/elevenlabs.secret` with mode `0600`;
3. record `elevenlabs-rotation-receipt.json` with `previous_key_revoked: true`
   and no secret value; and
4. record a content-free deletion-test receipt for
   `eleven_v3_conversational` and `scribe_v2`.

Do not bind either key into Git or application `.env`.

## Preflight

```text
php apps/api/artisan speech-wave38:preflight --status-file="$HOME/ge-speech-benchmark/evidence/wave38/wave38-status.json"
```

or `scripts/run-speech-wave38-preflight.sh`.

A development `ready` result may include
`deferred_until_live_deployment` for key rotation and the deletion test. It
still prints `dispatch_authorized: false`. HTTP enablement remains a later
approval. `SpeechDispatchAuthority` continues to deny challenger dispatch.

After you rotate the key or finish a deletion test, record receipts without
pasting secrets:

```text
php apps/api/artisan speech-wave38:record-receipt rotation \
  --packet="$HOME/ge-speech-benchmark/evidence/wave38" \
  --rotated-at=2026-09-09T15:00:00Z

php apps/api/artisan speech-wave38:record-receipt deletion \
  --packet="$HOME/ge-speech-benchmark/evidence/wave38" \
  --evidence-id=el-deletion-test-YYYYMMDD \
  --deleted-at=2026-09-09T15:05:00Z
```

Hash an executed DPA or agreement that already lives outside Git (mode `0600`):

```text
php apps/api/artisan speech-wave38:hash-document elevenlabs-dpa \
  --packet="$HOME/ge-speech-benchmark/evidence/wave38" \
  --path="$HOME/ge-speech-benchmark/evidence/wave38/executed/elevenlabs-dpa.pdf" \
  --executed
```

APP-0080 accepted residual risk treating public `elevenlabs.io/dpa`, Terms of
Use, `compliance.elevenlabs.io` and the Sarvam Trust Center as Wave 3.8
contract evidence. `compliance.elevenlabs.io` hashed as a Vanta portal shell,
not a named subprocessor table. That acceptance does not replace key rotation
or a deletion test.

## Safe error codes

| Code | Meaning |
|---|---|
| `wave38_status_unusable` | Status file missing, inside Git, wrong mode, or invalid JSON |
| `elevenlabs_key_not_rotated` | Rotation receipt missing or previous key not revoked |
| `elevenlabs_deletion_test_missing` | No content-free deletion-test receipt |
| `elevenlabs_executed_dpa_missing` | Executed agreement/DPA/subprocessor SHA-256 missing |
| `elevenlabs_host_not_india_residency` | Host is not `api.in.residency.elevenlabs.io` |
| `sarvam_executed_dpa_missing` | Executed Sarvam DPA hash missing |
| `elevenlabs_vendor_evidence_expired` | ElevenLabs vendor `valid_until` is missing or past |
| `azure_speech_vendor_evidence_expired` | Azure Speech packet `valid_until` has passed |
| `unexpected_dispatch_authorization` | Packet claimed dispatch authority; preflight refuses |

## What Accountable Product Owner still supplies before live deployment

APP-0081 deferred these out of the development-phase Wave 3.8 close:

- rotate and revoke the disclosed ElevenLabs key, then record a rotation
  receipt (do not paste the secret);
- a content-free deletion-test evidence id and UTC timestamp;
- a separate HTTP-enablement approval;
- renew APP-0064 Azure Speech evidence before **2026-09-14**.

Do not paste API keys into chat or Git.

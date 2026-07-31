# PR-009 speech benchmark evidence handoff

## Purpose and authority

This document describes the external evidence files consumed by the
fail-closed PR-009 Azure Speech benchmark implementation. It does not provide
vendor evidence, approve spend, authorize a credential or permit a provider
request.

The controlling decisions remain
[ADR-0008](adr/0008-azure-speech-development-provider-and-benchmark-policy.md),
its [locked benchmark plan](PR-009_SPEECH_PROVIDER_BENCHMARK_PLAN.md) and
[APP-0013](APPROVALS.md#app-0013), plus
[ADR-0009](adr/0009-synthetic-tts-listener-fixture-review-workflow.md) and
[APP-0014](APPROVALS.md#app-0014).

No value shown below is real evidence. Placeholder values must never be copied
into an authorized run.

## External-file boundary

The vendor, spend, listener and credential files must:

- use absolute paths outside the repository;
- be regular files rather than symbolic links;
- be owned by the process user;
- have Unix mode `0600`;
- remain access controlled and absent from Git, `.env`, logs and command
  output; and
- be removed or archived under the applicable evidence-retention policy after
  the benchmark.

The two JSON evidence files are limited to 64 KiB. Timestamps use UTC RFC 3339
form such as `2026-08-01T10:30:00Z`; `reviewed_at` cannot be in the future and
`valid_until` must be later than preflight.

## Encrypted fixture directory

`SPEECH_BENCHMARK_EPHEMERAL_ROOT` points to a pre-created, empty or
benchmark-owned directory that:

- is outside the repository, Laravel storage/public paths and every web root;
- is local, non-backed-up and excluded from indexing, synchronization and
  malware-upload tooling;
- is owned by the benchmark process user, is not a symbolic link and has Unix
  mode `0700`; and
- contains only opaque run directories, authenticated ciphertext and
  content-free deletion receipts.

Set `SPEECH_BENCHMARK_EPHEMERAL_STORAGE_ACKNOWLEDGED=true` only after the
operational owner verifies those conditions. Every run directory is mode
`0700`; each encrypted state, lock and fixture file is mode `0600`. The same
protected benchmark `APP_KEY` must remain available for prepare, review and
complete. Rotation invalidates the authenticated bundle, after which it must
be purged rather than regenerated under the same run ID.

## Vendor evidence record

`AZURE_SPEECH_VENDOR_EVIDENCE_PATH` points to this safe evidence projection.
Contracts, subscription identifiers, invoices, contacts and account details
remain in the access-controlled vendor register, not this file.

```json
{
  "schema_version": 1,
  "provider": "azure-speech",
  "region": "centralindia",
  "approved_for_synthetic_benchmark": true,
  "data_logging_enabled": false,
  "model_improvement_enabled": false,
  "custom_speech_enabled": false,
  "subscription_evidence_id": "vendor-register-reference",
  "contracting_entity_review_id": "contracting-entity-review-reference",
  "agreement_sha256": "64-lowercase-hex-characters",
  "dpa_sha256": "64-lowercase-hex-characters",
  "subprocessor_sha256": "64-lowercase-hex-characters",
  "privacy_legal_review_id": "privacy-legal-review-reference",
  "logging_disabled_evidence_id": "speech-configuration-review-reference",
  "reviewed_at": "2026-08-01T10:30:00Z",
  "valid_until": "2026-08-02T10:30:00Z"
}
```

The identifiers and hashes must exactly match:

- `AZURE_SPEECH_SUBSCRIPTION_EVIDENCE_ID`;
- `AZURE_SPEECH_CONTRACTING_ENTITY_REVIEW_ID`;
- `AZURE_SPEECH_AGREEMENT_SHA256`;
- `AZURE_SPEECH_DPA_SHA256`;
- `AZURE_SPEECH_SUBPROCESSOR_SHA256`;
- `AZURE_SPEECH_PRIVACY_LEGAL_REVIEW_ID`; and
- `AZURE_SPEECH_LOGGING_DISABLED_EVIDENCE_ID`.

## Provider-spend approval record

`SPEECH_BENCHMARK_SPEND_APPROVAL_PATH` points to the separate human
provider-spend approval:

```json
{
  "schema_version": 1,
  "benchmark_id": "pr009-azure-speech-synthetic-v1",
  "approval_id": "provider-spend-approval-reference",
  "approved": true,
  "cap_inr": 1000,
  "reviewed_at": "2026-08-01T10:30:00Z",
  "valid_until": "2026-08-02T10:30:00Z"
}
```

`approval_id` must equal `SPEECH_BENCHMARK_SPEND_APPROVAL_ID`.
`cap_inr` must equal `SPEECH_BENCHMARK_APPROVED_SPEND_CAP_INR`, be greater
than zero and never exceed `₹1,000`.

## Listener evidence record

`SPEECH_BENCHMARK_LISTENER_EVIDENCE_PATH` points to the safe aggregate
disposition from two accountable development listeners:

```json
{
  "benchmark_id": "pr009-azure-speech-synthetic-v1",
  "reviewer_ids": [
    "development-listener-reference-1",
    "development-listener-reference-2"
  ],
  "output_count": 96,
  "intelligibility_accepted_by_both": 96,
  "acceptability_accepted_by_both": 96,
  "critical_pronunciation_defects": 0
}
```

The record contains no listener contact details, audio, transcript or
utterance body. It is valid only when both reviewers assessed the 96 clean
outputs governed by the locked plan. Creating or accepting this record is a
human review step; repository tests use obvious synthetic placeholders and do
not constitute listener evidence.

### Implemented listener workflow

DR-0012 is resolved by accepted ADR-0009 under APP-0014. The implementation
uses one opaque run ID across four explicit phases:

1. `prepare` generates the 96 clean TTS outputs, validates each WAV, counts
   every operation/attempt/character/spend unit and writes only separately
   authenticated ciphertext plus encrypted resume state;
2. `review` starts one ephemeral `127.0.0.1` listener on an
   operating-system-assigned port for one listener slot;
3. `complete` atomically prevents replay, restores the 96 prepared operations,
   reuses the exact reviewed outputs for 288 STT operations, performs the
   remaining 80 TTS plus 80 STT operations, and deletes the bundle on every
   normal terminal outcome; and
4. `purge` removes an explicit or expired bundle without requiring a provider
   credential.

The review URL carries a random 256-bit token only in its fragment. The page
sends it in an `Authorization` header, rejects proxy/public host behavior,
uses `no-store` plus restrictive CSP headers, creates browser-memory blobs and
revokes object URLs. The utility records only required boolean dispositions;
it records no reviewer identity, contact detail, IP address, browser
identifier or free text. The two accountable human identities remain in the
external review register and are projected only into the safe aggregate
listener record above.

A prepared run expires exactly 24 hours after creation. Preparation is
idempotent only for an already complete 96-fixture bundle; a failed or partial
run cannot restart billable operations. Completion verifies the exact
configuration, corpus, plan, runner revision/commit, dependency locks,
operation counters and all ciphertext hashes before a provider request.
Successful deletion receipts are immutable. Failed deletion attempts remain
visible, return failure and block acceptance.

## Credential reference

`AZURE_SPEECH_SECRET_FILE` contains only the dedicated non-production
credential or short-lived token. It is not JSON and is never committed,
printed or copied into either `.env` file.

`AZURE_SPEECH_CREDENTIAL_KIND` is exactly one of:

- `entra-token`; or
- `subscription-key`.

The corresponding safe credential-version reference is stored separately in
`AZURE_SPEECH_CREDENTIAL_VERSION_ID`.

## Remaining safe configuration

The operational owner must also provide:

- exact HTTPS `AZURE_SPEECH_ENDPOINT` and matching
  `AZURE_SPEECH_ALLOWED_HOST`;
- current implementation commit in `SPEECH_BENCHMARK_RUNNER_COMMIT`;
- reviewed price snapshot and INR conversion values;
- `SPEECH_BENCHMARK_CONTENT_SCAN_EVIDENCE_ID`;
- an absolute `SPEECH_BENCHMARK_SAFE_REPORT_PATH`; and
- the external `SPEECH_BENCHMARK_EPHEMERAL_ROOT` plus explicit storage
  acknowledgement described above; and
- the dedicated `APP_ENV=speech-benchmark`,
  `SPEECH_BENCHMARK_ENABLED=true` and
  `AZURE_SPEECH_LIVE_REQUESTS_ENABLED=true` values.

These values belong only in the isolated benchmark process environment. They
must not be added to the repository's ordinary `.env`.

## No-call handoff sequence

1. Record exact vendor and privacy/legal evidence outside Git.
2. Obtain a separate bounded provider-spend approval.
3. Provision and verify the dedicated Central India resource and credential.
4. Pre-create and verify the external mode-`0700` ephemeral root.
5. Set the isolated benchmark process environment using only external
   secret/evidence paths.
6. Run `php artisan speech:benchmark --phase=preflight`; stop on any safe
   error code.
7. Obtain explicit authorization for the exact live synthetic preparation;
   APP-0014 is not that authorization.
8. Run `php artisan speech:benchmark --phase=prepare` once and retain its
   opaque run ID.
9. Run two separate local sessions:
   `php artisan speech:benchmark --phase=review --run=<run-id> --listener-slot=1`
   and then the same command with `--listener-slot=2`.
10. Create the external aggregate listener record from those exact
    dispositions and the separately controlled accountable-reviewer register.
11. Run
    `php artisan speech:benchmark --phase=preflight --run=<run-id>`; this
    decrypts only for validation and makes no provider request.
12. Obtain or reconfirm explicit authorization for the remaining live
    synthetic requests.
13. Run
    `SPEECH_BENCHMARK_RUN_ID=<run-id> make ai-eval` once under the approved
    cap.
14. Confirm the content-free successful deletion receipt and safe aggregate
    report, then revoke or rotate the credential under the approved procedure.
15. On cancellation or failure, run
    `php artisan speech:benchmark --phase=purge --run=<run-id>`. The hourly
    benchmark-environment schedule and
    `php artisan speech:benchmark --phase=purge --expired` handle expiry.

The DR-0012 implementation is complete, but steps 1–7 and every live request
remain unauthorized and incomplete. No Azure credential, provider spend,
listener evidence or live benchmark result exists in this repository.

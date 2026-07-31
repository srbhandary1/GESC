# PR-009 speech benchmark evidence handoff

## Purpose and authority

This document describes the external evidence files consumed by the
fail-closed PR-009 Azure Speech benchmark implementation. It does not provide
vendor evidence, approve spend, authorize a credential or permit a provider
request.

The controlling decisions remain
[ADR-0008](adr/0008-azure-speech-development-provider-and-benchmark-policy.md),
its [locked benchmark plan](PR-009_SPEECH_PROVIDER_BENCHMARK_PLAN.md) and
[APP-0013](APPROVALS.md#app-0013).

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

### DR-0012 listener-workflow blocker

The implementation currently requires this external listener record before
the runner can dispatch a provider request. That is safely fail closed, but a
valid record cannot be created until the two listeners have assessed the
actual 96 Azure TTS outputs.

Persisting those outputs for later review would introduce an encrypted
ephemeral fixture bundle, key/access boundary, playback mechanism, expiry
worker and deletion receipt. Streaming them to reviewers during one process
would introduce a different local playback and reviewer-attestation boundary.
ADR-0008 specifies private encrypted storage and a 24-hour maximum but does
not select either exact workflow.

[DR-0012](DECISIONS.md) must resolve this circular dependency before the
listener workflow or a live request is implemented. The gate must not be
weakened by pre-approving placeholder evidence or writing plaintext audio.

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
- the dedicated `APP_ENV=speech-benchmark`,
  `SPEECH_BENCHMARK_ENABLED=true` and
  `AZURE_SPEECH_LIVE_REQUESTS_ENABLED=true` values.

These values belong only in the isolated benchmark process environment. They
must not be added to the repository's ordinary `.env`.

## No-call handoff sequence

1. Resolve DR-0012 with an exact reviewed listener-fixture workflow.
2. Record exact vendor and privacy/legal evidence outside Git.
3. Obtain a separate bounded provider-spend approval.
4. Provision and verify the dedicated Central India resource and credential.
5. Implement and verify only the approved DR-0012 workflow.
6. Complete the accountable listener evidence workflow without learner data.
7. Set the isolated benchmark environment using external secret/evidence
   paths.
8. Run `php artisan speech:benchmark --preflight`.
9. Stop if preflight reports any safe error code.
10. Obtain explicit authorization for the live synthetic request.
11. Run `make ai-eval` once under the approved cap.
12. Revoke or rotate the credential and handle the safe report and external
    evidence under their retention policies.

Steps 1–10 are currently incomplete. Therefore no live benchmark command is
authorized now.

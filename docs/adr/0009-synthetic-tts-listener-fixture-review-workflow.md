---
status: proposed
date: 2026-07-31
deciders: []
consulted:
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: false
approval_evidence: []
approval_reference: null
---

# ADR-0009: Synthetic TTS listener fixture review workflow

## Status

Proposed.

This proposal addresses DR-0012 within PR-009. It does not authorize a
credential, provider spend, a live request, implementation, benchmark success,
PR-009 acceptance, learner data, PR-010 or production.

## Context

The accepted
[PR-009 speech benchmark plan](../PR-009_SPEECH_PROVIDER_BENCHMARK_PLAN.md)
requires two accountable development listeners to assess all 96 clean Azure
TTS outputs. The current implementation safely requires listener evidence
before the benchmark runner dispatches a provider request and persists no
audio.

Those two properties form a circular dependency: listener evidence cannot
honestly exist until the actual outputs exist, while generating or retaining
the outputs introduces a sensitive-media access, encryption, playback,
expiry, deletion and operation-accounting boundary.

ADR-0008 already permits private encrypted fixture storage for at most 24
hours. It does not select the exact preparation, local review, resume,
crash-recovery or deletion mechanism. Weakening preflight, creating evidence
from HTTP fakes, synthesizing an uncounted preview corpus or writing plaintext
WAV files would violate the locked plan.

## Decision

Subject to exact-artifact approval, PR-009 will use a three-step,
development-only workflow: prepare, review and complete.

### Prepare phase

The dedicated benchmark command will expose an explicit `prepare` phase that:

1. passes every ADR-0008 vendor, region, endpoint, credential-reference,
   price, content-scan, runner-commit and separate spend-approval gate except
   listener evidence;
2. creates a random opaque benchmark-run identifier with no tenant, learner,
   reviewer, subscription or account component;
3. generates the 96 clean TTS outputs from the exact 48 strings and two locked
   voices;
4. counts those 96 logical operations, all attempts, characters and estimated
   spend against the existing 544-operation, 1,088-attempt and approved INR
   caps;
5. validates each WAV before persistence;
6. encrypts every WAV separately with authenticated application encryption
   and random nonces;
7. writes only ciphertext files with opaque names to one external
   owner-controlled directory whose directory mode is `0700` and file mode is
   `0600`;
8. writes an authenticated encrypted resume state containing fixture mapping,
   TTS usage/latency, attempt count, spend used, configuration/corpus/runner
   hashes and a fixed expiry no later than 24 hours after creation; and
9. emits only a content-free safe summary containing run ID, counts, hashes,
   expiry and status.

The external fixture directory must:

- be outside the repository and ordinary web/storage roots;
- be on local non-backed-up storage approved for the benchmark;
- reject symlinks, pre-existing nonempty directories and ownership or mode
  mismatch;
- contain no plaintext audio, SSML, transcript, evidence document, credential
  or human identifier; and
- be excluded from backup, indexing, synchronization and malware-upload
  workflows.

Preparation is idempotent by run ID and locked manifest. It never silently
restarts or creates a second set of billable outputs after a partial failure.
A failed or partial preparation cannot proceed to review or completion.

### Local listener review

The command will expose an explicit `review` phase that:

1. runs only in the named `speech-benchmark` environment on the benchmark
   workstation;
2. binds an ephemeral HTTP listener to `127.0.0.1` only, using an
   operating-system-assigned port;
3. refuses `0.0.0.0`, LAN addresses, containers with published ports, proxy
   headers and remote hosts;
4. generates a random 256-bit review-session token kept in the browser URL
   fragment and sends it only in an `Authorization` header from the review
   page;
5. disables access logs and returns `Cache-Control: no-store`, a restrictive
   Content Security Policy and no service-worker registration;
6. decrypts one requested WAV into process memory only, returns it to the
   authenticated local page, then releases the plaintext buffer;
7. uses memory-only browser blobs, revokes object URLs after playback and
   stores no audio or token in localStorage, IndexedDB, Cache Storage or
   ordinary browser preferences;
8. presents only fixture ID, approved source text, locked voice and the
   intelligibility/acceptability/critical-defect choices required by the
   benchmark;
9. records no reviewer name, contact detail, IP address, browser identifier or
   free text; and
10. stops automatically after 30 minutes of inactivity, four hours absolute
    duration, explicit completion or process termination.

Each accountable listener uses a separate review session. Their durable human
identifiers and accountability remain in the access-controlled review
register. The application exports only the safe aggregate listener-evidence
record described in
[the handoff document](../PR-009_SPEECH_BENCHMARK_EVIDENCE_HANDOFF.md).

The local review page is a benchmark utility, not a learner or operator
product surface. It is never included in the normal API route set, web bundle,
mobile client or production build.

### Complete phase

The `complete` phase will:

1. require both accountable listener dispositions and every ordinary
   preflight gate;
2. verify the encrypted resume state, run ID, expiry, configuration, corpus,
   code revision, dependency locks and all 96 ciphertext hashes;
3. refuse expired, partial, modified, replayed or already-completed runs;
4. reuse the exact 96 reviewed outputs for the three deterministic transforms
   and 288 STT operations;
5. perform the locked 80 repetition TTS and 80 repetition STT operations;
6. resume logical-operation, attempt and spend counters from preparation so
   the complete run remains exactly 544 logical operations and at most 1,088
   attempts;
7. calculate the locked aggregate metrics and safe report;
8. delete the encrypted fixture bundle and resume state whether the benchmark
   passes or fails terminally; and
9. retain only a content-free deletion receipt containing run ID, ciphertext
   manifest hash, deletion instant, deleted-file count and success/failure
   status.

The runner never represents a prepared, reviewed or partial run as a benchmark
pass. A completion crash leaves only encrypted content until an idempotent
purge command deletes it.

### Expiry, withdrawal and emergency behavior

- A scheduled and manually invokable purge command deletes every expired or
  explicitly cancelled bundle.
- Review cannot extend the original 24-hour expiry.
- Revoked spend, vendor, privacy, credential or configuration evidence blocks
  new requests and deletes the bundle.
- Credential revocation does not block local deletion.
- A failed deletion is visible, retried and prevents benchmark acceptance.
- The emergency disable switch blocks prepare and complete provider requests
  while preserving review only long enough to export a current disposition or
  delete the bundle.

### Authority and rollout

The workflow contains synthetic project-authored content only and has no
organization or learner scope. It cannot update marks, mastery, progression,
review schedules, curriculum, conversation sessions or any deterministic
learning record.

Implementation remains disabled by default and available only in
`speech-benchmark`. Acceptance of this ADR would authorize only the bounded
DR-0012 implementation inside PR-009. Separate vendor evidence, spend approval
and explicit live-request authorization remain required.

## Alternatives considered

### Pre-create listener evidence

Rejected. HTTP-fake output, provider marketing samples or a previous unrelated
run cannot establish disposition for the actual 96 locked outputs.

### Run an additional uncounted preview synthesis

Rejected. Another 96 TTS operations would exceed the locked logical-operation
plan, complicate spend accounting and may not match the benchmark outputs.

### Write plaintext WAV files for ordinary desktop playback

Rejected. Plaintext persistence violates ADR-0008's encrypted ephemeral
fixture requirement and expands backup, indexing, synchronization and
accidental-disclosure risk.

### Require both reviewers to listen synchronously inside the original runner

Rejected. A long interactive provider process is fragile, complicates
credential lifetime and retry accounting, and cannot safely recover from
browser, audio-device or reviewer interruption.

### Keep the current circular fail-closed gate indefinitely

Safe but incomplete. It prevents live requests and therefore cannot produce
the benchmark evidence required to finish PR-009.

## Consequences

### Positive

- Listener evidence derives from the exact outputs counted in the benchmark.
- The locked 544-operation and spend caps remain intact across phases.
- Synthetic audio is encrypted at rest and plaintext exists only in bounded
  process/browser memory.
- Review is local-only, content-minimized and separate from application users.
- Crash recovery, expiry and deletion have explicit, testable states.

### Negative and trade-offs

- The benchmark runner becomes a resumable state machine rather than one
  command invocation.
- A local review utility adds token, browser-memory, CSP and loopback-server
  security tests.
- Application encryption relies on the benchmark environment's protected
  `APP_KEY`; rotation during a run invalidates the bundle and forces deletion.
- Two-phase evidence and purge operations increase implementation and review
  effort.
- Local browser/audio behavior still does not constitute accessibility,
  language or production review.

## Security, privacy and tenancy impact

- No learner, employee, customer, tenant or production data is permitted.
- External directories and ciphertext files require exact owner/mode,
  symlink, path, backup and expiry checks.
- The local server binds only `127.0.0.1`, rejects proxy/remote behavior and
  uses a short-lived high-entropy authorization token.
- Raw audio and tokens are excluded from logs, traces, audit, outbox, database,
  safe reports and browser persistence.
- Tests must cover same-host unauthorized access, token replay, cache/storage
  absence, symlink/path races, corrupt ciphertext, expiry, cancellation,
  deletion failure, crash resume and operation/spend replay.
- There is no organization-scoped resource. Tests must prove that no tenant,
  membership or learning table is read or changed.

## Authoritative records and consistency impact

The encrypted resume state is authoritative only for this development
benchmark run. It is authenticated, immutable after each monotonic phase
transition and bound to the exact configuration, corpus, code and evidence
hashes.

Prepare, review and completion transitions use atomic file replacement and an
exclusive run lock. A run ID cannot be reused after completion or deletion.
The safe deletion receipt is immutable and content free.

No database migration or product-authoritative record is introduced.

## API, client and operational impact

- No public API, OpenAPI, generated client, learner UI or mobile contract
  changes.
- New benchmark-only CLI phases, an isolated loopback review page and a purge
  command are required.
- The normal `make ai-eval` target completes only a prepared and reviewed run;
  preparation and review are explicit preceding commands.
- Operational documentation must identify the external directory, encryption
  key custody, browser profile, audio device, expiry monitor and deletion
  verification.
- Production builds and services must not package or route the review utility.

## Master-plan amendment

This ADR does not amend the master plan. It makes the accepted PR-009
synthetic-benchmark listener requirement executable without changing scope,
thresholds or authority.

## Approval evidence

Acceptance must:

1. identify this exact proposal by SHA-256;
2. name the Technical Decision Owner, Security Reviewer, Privacy Reviewer and
   Tenancy Reviewer roles exercised;
3. explicitly accept the encrypted two-phase fixture bundle, loopback
   browser-memory review and 24-hour expiry/deletion trade-offs;
4. state that implementation only, not credentials, spend, live requests,
   benchmark success, PR-009 acceptance, learner data, PR-010 or production,
   is authorized; and
5. be recorded in `docs/APPROVALS.md`.

Codex is the author and evidence recorder only, never the approver.

## Verification

Before approval:

- validate front matter, headings, local links and final newline;
- hash the exact proposal;
- confirm application source, API, schema, configuration and environment files
  did not change; and
- confirm all provider calls remain blocked.

After approval and implementation:

- unit-test encryption, path/mode/symlink, state, cap and expiry rules;
- browser-test loopback authorization, no-store/CSP and storage absence;
- fault-test crash, replay, cancellation and deletion reconciliation;
- prove the two phases total exactly 544 logical operations under HTTP fakes;
- prove no tenant or learning table changes;
- run `make verify`, `make e2e`, blocked preflight without evidence and
  `make build`; and
- do not run live until the separate vendor, spend and request approvals exist.

## Follow-up

- Obtain exact-artifact approval or revise this proposal.
- Implement only the approved DR-0012 workflow within PR-009.
- Keep Azure credentials and live requests blocked until every separate gate
  is satisfied.

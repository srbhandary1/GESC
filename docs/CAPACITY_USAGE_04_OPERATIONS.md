# Capacity and provider evidence — 25 September 2026

Target: 1,000 simultaneous speakers. This is a capacity target, not a passed
release gate. Current live hosting and vendor allocation cannot meet it.

## Workload and purchase requirements

Planning assumption: one student turn every 30 seconds, including recording,
response generation and listening. 1,000 active students produce 2,000 model
requests/minute, approximately 3.2 million model tokens/minute at 1,600 tokens
per turn, 2,000 transcription requests/minute and 2,000–4,000 synthesis
requests/minute (English reply plus optional explanation). These are assumptions;
measure the distribution before procurement. Reserve twice the sustained model
rate for peaks: 4,000 RPM and 6.4 million TPM. A synchronized classroom also
creates bursts that must be smoothed or rejected with a retry delay.

At five seconds of provider wait, 33 turns/second occupies roughly 167 workers
and database connections if requests remain synchronous. Increasing an HTTP
worker setting to 1,000 on the current 4 CPU / 8 GiB VPS is not a solution.
Provider calls currently occupy database transactions. A bounded worker pool,
short admission/completion transactions, separate provider work, bounded queues,
connection budgets, shared private recovery storage and queue age monitoring are
required before certifying the target. The existing health-only load script
does not exercise these paths and cannot certify speaker capacity.

## Provider selection

Verified live configuration: Sarvam Saaras v3 recorded STT with Azure fallback;
Azure gpt-4.1-mini model; Azure neural TTS. ElevenLabs TTS is disabled and its
kill switch is active. Sarvam Bulbul TTS currently has a rejecting placeholder
adapter, not a working live route. Routing must be judged separately for STT,
the language model and TTS; changing a speech provider does not raise model quota.

Public prices checked 25 September, excluding taxes and negotiated discounts:

| Operation | Published price | Implication |
|---|---:|---|
| Sarvam Saaras STT | ₹30/hour | Current inexpensive primary for Indian speech |
| Azure Fast Transcription, Central India | ₹34.3967/hour | Reasonable existing fallback |
| Azure S1 neural TTS, Central India | ₹1,433.1938/million characters | About ₹14.33/10,000 characters |
| Sarvam Bulbul v3 | ₹30/10,000 characters | More expensive than the verified Azure neural meter |
| ElevenLabs Flash/Turbo TTS | Advertised from $0.05/1,000 characters | No demonstrated cost reason to enable it by default |

Sources: [Sarvam pricing](https://docs.sarvam.ai/api/getting-started/pricing),
[ElevenLabs API pricing](https://elevenlabs.io/pricing/api),
[Azure retail price API](https://learn.microsoft.com/en-us/rest/api/cost-management/retail-prices/azure-retail-prices).
Azure rows were retrieved without credentials from `prices.azure.com/api/retail/prices`
with `armRegionName eq 'centralindia' and contains(productName, 'Speech')` and
`currencyCode=INR`. Microsoft describes non-USD rates as budget estimates;
the subscription invoice and actual resource region remain authoritative.
No claim that price alone establishes speech quality or an account-specific SLA.

Sarvam Business advertises 4,000 STT REST RPM but only 1,000 TTS REST RPM and
120 model RPM. Its STT WebSocket concurrency is 100. These limits do not cover
1,000 continuous live streams automatically. See [Sarvam rate limits](https://docs.sarvam.ai/api/getting-started/ratelimits).
Enterprise capacity needs an account-specific agreement and representative
English/Kannada quality checks; switching vendors without those checks is unsafe.

## External capacity blocker

The existing Azure model deployment last reported 3 RPM / 3,000 TPM, shared by
all students. Management token refresh was retried using Azure CLI on 25 September
and again returned `AADSTS50076` (MFA required). The earlier device login was
denied access. Code cannot grant account permissions or quota. A subscription
administrator must restore usable CLI access and allocate approved throughput;
no repeated device-code request or invented successful quota increase is recorded.

## Verification required for 1,000 speakers

Run an isolated synthetic authenticated turn workload with recorded WAV fixtures,
consent, STT, model, TTS, partial playback, token summaries and reconnection. Stub
provider latency/failure only in the isolated environment. Measure p95/p99 turn
and playback latency, 429s, queue age, memory, database locks/connections and
duplicate charges through a sustained run and synchronized burst. Separately
verify contracted vendor RPM/TPM/concurrency and run a bounded paid-provider
quality test. A synthetic pass never substitutes for those vendor checks.

Measured playback requires the matching web/Android client. Older installed APKs
do not report playback progress. Do not declare the billing rollout complete until
the updated client is installed. Live start/read/turn/speech routes require
`X-Conversation-Playback: 1` and return 426 for older clients. The header is a
compatibility signal, not proof of playback or authentication.
Client reports measure delivered playback, not human attention. Unknown provider
usage must remain unknown rather than being reported as zero tokens.

## Candidate controls and bounded runtime

Shared admission defaults to the measured 3 RPM / 3,000 TPM, with 2,000 estimated
reserved tokens/turn and a per-student 6 RPM fairness limit. The conservative
pacing is therefore one new model turn every 40 seconds at the current allocation.
It smooths bursts without sleeping in an HTTP worker. Token reservations are
planning estimates, not provider-reported usage, and unusually long requests can
still hit a vendor TPM limit. A busy Redis lock returns 429; unavailable Redis or
invalid configuration returns 503. Both carry Retry-After. Exact replay and
local explanation-permission controls do not reserve model capacity. The limiter
does not turn the small vendor allocation into 1,000-speaker capacity.

Configuration: `CONVERSATION_CAPACITY_ENABLED`, `CONVERSATION_CAPACITY_STORE`
(Redis required outside tests), `CONVERSATION_CAPACITY_MODEL_RPM`,
`CONVERSATION_CAPACITY_MODEL_TPM`, `CONVERSATION_CAPACITY_RESERVED_TOKENS_PER_TURN`,
and `CONVERSATION_CAPACITY_LEARNER_RPM`. All servers serving a deployment must use
the same Redis connection/prefix and synchronized clocks. Raise limits only after
the account allocation is verified. A cache restart clears pacing state; account
quotas and independent spend controls remain necessary.

`make soft-launch-fpm-image` builds pinned PHP 8.4.23 FPM with PostgreSQL, intl,
pcntl and opcache. Select `GE_SOFT_LAUNCH_API_RUNTIME=fpm` in the protected runtime
environment, `GE_SOFT_LAUNCH_FPM_WORKERS=8` (allowed 4–32) and an unused loopback
`GE_SOFT_LAUNCH_FPM_PORT` (default 19001). The existing HTTP port and edge stay in
place. `GE_SOFT_LAUNCH_FPM_SECRET_ROOTS` and `GE_SOFT_LAUNCH_FPM_WRITABLE_ROOTS`
are colon-separated exact directories for approved provider files and private
recovery/media paths. The launcher mounts application code read-only, Laravel
storage writable, and only explicitly listed extra paths. It passes environment
values in a private temporary file, drops container capabilities, runs as the
operator UID, bounds workers/memory/backlog and cleans up on stop. Never mount
the host root or a blanket home directory. Missing mounts must be resolved before
live cutover. This runtime supports parallel requests; it does not remove provider
I/O from database transactions or certify capacity beyond its measured workload.

Local evidence: official pinned FPM image builds; Laravel health responds through
the loopback Caddy/FPM path; 200 requests at concurrency 16 pass (p95 48 ms).
This is explicitly a health-path check. A separate real Redis admission burst of
1,000 attempts across eight processes admits one and rejects 999, with no cache
errors or provider calls. It verifies shared overload protection, not 1,000 served
conversations.

The old default cost estimates understated neural synthesis and gpt-4.1-mini.
New defaults use ₹0.0014331938/TTS character and conservative rounded regional
model rates of 42/169 INR microunits per input/output token. Retail South India
regional model rows were ₹0.042/₹0.1682 per 1,000 tokens; global rows were
₹0.0382/₹0.1529. Explicit environment overrides remain authoritative. Cached
discounts and negotiated rates can reduce actual expense. Historical ledger rows
and student minute balances are not rewritten. Neither these estimates nor
fallback-attempt telemetry replaces reconciliation against provider invoices.


## Live smoke and rollout repair

Release `capacity-usage-20260925-r1` is active. Migration 000477 is applied;
FPM uses eight workers and image `sha256:024095a5ae26af22205ef6903f5515e27c0cbc63b2fecdc934b54bd9c8757a5a`.
All three live provider configuration preflights pass inside the container and
private conversation recovery remains on the existing non-swappable tmpfs.
An initial host-specific Caddy listener returned empty 200s for the public Host.
The corrected listener binds loopback and accepts the forwarded public Host;
health checks now require the expected JSON body using that Host instead of
accepting any HTTP 200. Public readiness and authentication-mode JSON pass.

Android 1.0.3/code 4 is installed with data preserved and published. After the
proxy repair, programmatic controls on the attached phone verified sign-in,
free-speech opener, one synthetic live model reply and audio playback without
retry/error. Meter rows total 18,222 ms played / 19 seconds charged across two
plays; model tokens persist independently and the usage panel displays totals.
The phone is restored to Kannada/dark Home. These checks do not establish
microphone quality or representative load. Synthetic fixture text and aggregate
counters are used; no learner transcript, token credential or raw audio is stored
in repository evidence.

The 2,000-token admission reservation would require 8 million TPM to allow a
4,000 RPM burst continuously; the earlier 6.4 million TPM workload estimate uses
1,600 tokens/turn. Size the account to the actual configured reservation and
measured prompt distribution, rather than assuming those estimates are identical.

## Changed files and verification record

This checkpoint changes these areas; earlier task changes in the dirty workspace
are not attributed to this checkpoint:

- `apps/api/app/Conversation/Metering/`: bounded playback charging, client support
  requirement and scoped provider token summaries.
- `apps/api/app/Conversation/Capacity/`, `ConversationTurnService.php`,
  `Entitlements/EntitlementAccess.php`, and speech services: admission, exact replay,
  session-only row locks and removal of arbitrary time deductions.
- Conversation/subscription controllers, operator entitlement/privacy queries,
  `apps/api/routes/`, and migration
  `2026_09_25_000477_create_conversation_playback_meters.php`: authenticated API,
  scoped metadata, erasure/purge and durable unit provenance.
- `apps/web/src/conversation/meterSpeechPlayback.ts`, audio playback helpers,
  subscription hook/panels and operator panel: actual playback progress, balance
  refresh and separate token totals. Generated contracts, OpenAPI and six preview
  language catalogues are updated; no independent linguistic approval is claimed.
- `scripts/run-fpm-api.sh`, `scripts/run-soft-launch.sh`,
  `infra/soft-launch/Dockerfile.api-fpm`, `Makefile` and conversation configuration:
  bounded workers, shared pacing, corrected estimates and JSON/Host health checks.
- Android build version, homepage download and APK publication: 1.0.3/code 4.
- Master plan, ADR-0043, STATUS, DECISIONS and QUALITY_REVIEW record the owner's
  billing/capacity choice, verification and remaining scale work.

Passed: `make verify` (354 SQLite / 435 PostgreSQL / 543 web tests),
`make e2e` (38 browser + 4 API tests), `make ai-eval` (15 cases + 26 tests),
`make build`, `make android-closed-beta` (7 native tests), APK verification,
runtime shell contracts and `git diff --check`. The initial PostgreSQL fixture
failures were repaired without weakening append-only records or constraints.
The final public Host health regression and phone checks are described above.
Builds retain the pre-existing large-JavaScript-chunk warning and exclude the
unlicensed tutor-avatar model; neither is a newly passed acceptance gate.

Security/privacy: organization and membership checks, monotonic/idempotent
charges, bounded server grants, exact replay, private no-store API responses and
15-minute metadata cleanup are preserved. No credentials are added to ordinary
browser storage; the APK continues native secure storage. No raw audio/transcript
or learner identity is committed in this evidence. Existing historical financial
records are retained. Runtime env rollback copies remain protected on the server;
no new secret credential was created.

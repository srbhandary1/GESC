# DR-0048 — Multi-provider speech observability and low-latency Kannada conversation

**Status:** Governance resolved under
[APP-0077](APPROVALS.md#app-0077),
[ADR-0038](adr/0038-multiprovider-speech-routing-kannada-help-and-first-audible-gate.md)
and D-0306 on 9 September 2026. The Accountable Product Owner accepted the exact
product/data boundaries and master-plan v3.15 amendments and authorized only
SPEECH-OBS-01 next. Implementation remains one numbered checkpoint at a time.
The approval does not authorize a new provider call, learner-content
processing, production, credentials or provider spend.

**Opened:** 9 September 2026

**Needed by:** Production speech-provider selection and the proposed Kannada
Help / Ask Swathi mode

**Authority affected if accepted:** `ENTERPRISE_PRODUCT_MASTER_PLAN.md` §§4,
14 and 20; ADR-0003, ADR-0005, ADR-0007, ADR-0008, ADR-0009, ADR-0010,
ADR-0011, ADR-0014, ADR-0025, ADR-0027 and ADR-0037; the voice consent notice;
OpenAPI and generated clients. Kannada Help requires an accepted ADR and an
explicit master-plan amendment before implementation because the canonical
learning language and current model/TTS contracts remain English-first.

## 1. Request and recommendation

Before production, integrate Azure, Sarvam and ElevenLabs only through the
existing project-owned STT and TTS ports, meter every provider attempt, and
show comparable cost, latency, reliability and usage in the operator portal.

Use **routed comparison**, not automatic three-way learner-audio shadowing:

1. a learner turn is sent to one selected primary provider;
2. an approved fallback is called only after a bounded failure;
3. synthetic, licensed or separately consented evaluation material may be run
   through all candidates in the controlled benchmark lane; and
4. production selection is based on local Kannada/code-mix quality, first-audio
   latency, reliability, privacy and cost per successful turn.

Recommended starting hypothesis, to be proved rather than assumed:

| Stage | Primary candidate | Challenger | Fallback |
|---|---|---|---|
| English-practice and code-mixed STT | Sarvam Saaras realtime | ElevenLabs Scribe v2 Realtime | Azure Speech with the reviewed locale/profile |
| Kannada/code-mixed STT | Sarvam Saaras realtime | ElevenLabs Scribe v2 Realtime | Azure Speech explicitly configured for `kn-IN` |
| English TTS | Azure `en-IN-NeerjaNeural` streaming baseline | ElevenLabs v3 Conversational and Sarvam Bulbul v3 | The second reviewed speech provider selected by the bake-off |
| Conversational Kannada TTS | ElevenLabs v3 Conversational for Swathi quality | Sarvam Bulbul v3 streaming | Azure `kn-IN-SapnaNeural` |
| Slow instructional TTS | Sarvam Bulbul v3 or Azure after listener review | ElevenLabs v3 Conversational | The other reviewed Sarvam/Azure speech route |
| Conversation model | Existing project-owned Azure OpenAI adapter | Separately approved model benchmark | Curated safe fallback |

Visible text, replay and a bounded recorded-capture retry remain user-facing
recovery paths. They do not satisfy the requirement for a reviewed speech
provider fallback for each live STT/TTS stage.

Every teaching path preserves the native/code-mixed transcript as the canonical
learner evidence. A translated English intent may be derived only as a separate
field after preservation; provider translate output must not replace the
original or become grammar/correction evidence.

Do not use ElevenAgents or another provider-owned agent runtime for curriculum,
policy, safety, marks, mastery, progression or tenancy. Speech vendors remain
replaceable adapters.

For **lowest published Kannada speech latency**, Sarvam currently has the
slight paper advantage: Saaras streaming advertises sub-150 ms time to first
token and Bulbul v3 advertises sub-250 ms first audio byte. ElevenLabs publishes
about 150 ms for Scribe v2 Realtime and about 280 ms for v3 Conversational.
These vendor figures are not comparable end-to-end evidence and must not decide
production routing without the project benchmark.

Current official references, checked 9 September 2026:

- [Sarvam Saaras](https://docs.sarvam.ai/api/getting-started/models/saaras)
- [Sarvam realtime STT](https://docs.sarvam.ai/api/api-guides-tutorials/speech-to-text/realtime-streaming)
- [Sarvam published STT latency](https://www.sarvam.ai/speech-to-text)
- [Sarvam Bulbul](https://docs.sarvam.ai/api/getting-started/models/bulbul)
- [Sarvam published TTS latency](https://www.sarvam.ai/text-to-speech)
- [ElevenLabs models](https://elevenlabs.io/docs/overview/models)
- [ElevenLabs Scribe language support](https://elevenlabs.io/docs/overview/capabilities/speech-to-text/)
- [Azure Speech language support](https://learn.microsoft.com/en-us/azure/ai-services/speech-service/language-support)
- [Azure TTS latency guidance](https://learn.microsoft.com/en-us/azure/ai-services/speech-service/how-to-lower-speech-synthesis-latency)

ElevenLabs Multilingual v2 and Flash v2.5 do not currently list Kannada.
`eleven_v3_conversational`, not either of those models, is the relevant
ElevenLabs Kannada conversational-TTS candidate.

## 2. Existing foundation and exact gaps

The repository is not starting from zero:

- `ai_cost_ledger` already records organization, turn, learner membership,
  stage, provider, model, attempt, latency, input/output units, estimated cost,
  result and time;
- `voice_provider_runs` records the corresponding uploaded-audio STT attempts,
  including safe failure codes; and
- the browser measures release-to-first-audio stages during a session.

The following gaps prevent an honest provider comparison:

1. there is no operator API or portal view that unifies both speech ledgers;
2. browser end-to-end timings are held only in an in-memory development view;
3. `input_units` and `output_units` have provider-dependent meaning and are not
   accompanied by an explicit billable-unit kind;
4. costs do not carry a versioned rate card, billing currency or INR conversion
   source, and some live STT rows currently record zero estimated cost;
5. finish latency is recorded, but first partial transcript, first model token,
   first TTS byte and first audible playback are not all durable metrics;
6. estimates are not reconciled against provider invoices/credits; and
7. the existing per-stage attempt uniqueness cannot represent three candidates
   as if they were retries of the same live learner turn. The benchmark lane
   must model candidates separately rather than abusing retry numbers.

All new telemetry must remain content-free. It must not contain audio,
transcript text, reply text, prompts, personal learner data, secrets or raw
provider payloads.

## 3. Measured soft-launch baseline

A content-free aggregate query on 9 September 2026 over the latest 48-hour
soft-launch records (latest run 8 September) found:

| Stage | Successful runs | p50 provider finish | p95 provider finish |
|---|---:|---:|---:|
| Sarvam recorded STT | 25 | 665 ms | 1,470 ms |
| Azure OpenAI conversation model | 23 | 1,783 ms | 3,903 ms |
| Azure TTS, all enabled voices | 28 | 801 ms | 1,528 ms |

There were also six failed Sarvam STT attempts in that interval. This is staff
and soft-launch evidence, not a production SLO or a controlled provider
benchmark.

The serial medians already total about 3.25 seconds before remaining client,
upload, download and playback work. The conversation-model stage is the largest
measured median delay. Replacing only STT or TTS therefore cannot make the
current path feel immediate.

The current application also waits for complete REST STT, complete model JSON
and a complete WAV TTS response. When Kannada correction audio exists, it is
synthesized after the main reply and can delay returning audio that is already
ready. The first latency change should remove that unnecessary blocking and
stream validated main-reply audio.

## 4. Usage, retention and pricing records required before adding providers

Keep provider-call evidence append-only **while it is retained** and add an
approved additive usage schema plus a versioned rate-card registry. ADR-0007's
call-level provider/cost metadata is retained for at most 30 days, then
aggregated without learner linkage and deleted. SPEECH-GOV-01 must explicitly
resolve any conflict between that rule and economics/audit requirements before
a migration is approved. Rate cards may remain append-only longer because they
contain no learner, turn or recording linkage; a new price never rewrites an
earlier rate version.

The existing ADR-0007 maxima remain unchanged unless an accepted amendment says
otherwise: successfully transcribed source audio is deleted after commit
(target 15 minutes, hard maximum one hour); recoverable-failure audio and
unconfirmed transcript candidates expire within 24 hours; ordinary confirmed
text expires after the transaction (hard maximum one hour) unless the learner
separately enabled 30-day transcript history; generated TTS audio expires within
24 hours; and content-free provider/cost/state metadata expires or is aggregated
at 30 days. Provider-side retention must be no longer than the approved product
window, and consent withdrawal/cancellation must stop queued dispatch, prevent
retry and continue provider/object deletion reconciliation.

Every attempted provider operation must record, where applicable:

- organization, environment, correlation/turn/recording identifiers and time;
- `stt`, `model` or `tts`, semantic operation, route role (`primary`,
  `fallback`, `benchmark`) and provider attempt;
- whether the result caused a provider dispatch or was served from an approved
  cache/replay path;
- provider key, exact model/version, API version, voice, region/deployment
  class and a non-secret normalized configuration hash;
- success, failure, cancellation, timeout or partial-stream failure and the
  bounded safe failure/fallback reason;
- audio duration sent, characters sent, tokens, generated audio duration and
  provider-reported billable quantity with explicit unit kinds;
- server connection, first partial/token/byte and finish latency, plus sampled
  client turn-release, acknowledgement, first substantive signal and first
  audible playback timestamps under documented clock/origin definitions;
- timing-sample policy/version and aggregate sample size so a sampled percentile
  cannot be mistaken for the full provider population;
- immutable rate-card version, tier/minimum rules, native billing currency,
  gross cost, tax, discounts, credits, INR conversion rate/source/time and
  estimated INR cost; and
- reconciliation status and variance against a real capped provider invoice or
  credit statement.

Every provider dispatch is metered even when an institutional learner is
unmetered for entitlement. One logical submitted learner turn consumes its
approved entitlement once; retries, fallbacks and benchmark calls remain
separate provider-cost events and never charge the learner again. Rate-card
writes are append-only and audited. Anonymous long-lived aggregates must have a
documented re-identification/small-cell review and no learner, turn, recording
or pseudonymous portal identifier.

## 5. Operator portal: Speech Economics and Latency

Add a permission-gated, organization-scoped **Speech Economics** screen with:

- date, organization, environment, stage, provider, model, route role, locale,
  status, conversation-mode and provider-dispatch/cache filters;
- requests, dispatches, cache/replays, successes, failures, cancellations,
  partial streams, fallbacks and success rate;
- STT audio minutes, TTS characters and generated audio minutes;
- p50/p95/p99 first-result, finish and end-to-first-audio latency;
- estimated native cost, normalized INR cost, reconciled invoice cost and the
  variance between them;
- cost per successful STT minute, per 1,000 TTS characters, per generated audio
  minute, per successful turn and per learner speaking minute;
- daily trend and side-by-side provider/model comparison with timing and quality
  sample sizes; and
- an audited CSV export containing only the same content-free aggregates, with
  safe date granularity and small-cell suppression.

Commercial rate cards, negotiated prices, credits and provider invoices are
platform-administrator-only by default under a named capability, with every
read and export audited. Organization operators require an explicit permission
and see only active-organization usage and approved allocated cost, never the
vendor's negotiated commercial terms. Neither role receives transcripts,
audio, provider secrets, per-learner/pseudonymous identifiers or cross-tenant
identifiers through this screen.

The query/API must pass organization scoping and cross-tenant denial for list,
filter, export, background job and cache paths; use RFC 9457 failures; update
OpenAPI and generated clients; meet the master-plan operator API p95 ≤800ms
target; and provide accessible loading, empty, partial, stale-rate,
unreconciled, error and export states.

Economy must be judged by **cost per successful usable turn**, not advertised
unit price alone. Retry rate, fallback rate, failures and unusable output can
make a nominally cheaper provider more expensive.

## 6. Ordered pre-production checkpoints

Implement one bounded checkpoint at a time. Passing an earlier checkpoint does
not authorize credentials, evaluation dispatch, learner-content processing,
spend or release for a later checkpoint.

### SPEECH-GOV-01 — Accept the product and data boundaries

Raise and accept the bounded ADR/master-plan amendments for multi-provider
routing, the separate first-audible launch gate, realtime transport and Kannada
Help before changing those contracts. Freeze the STT result and conversation
reply contracts, Kannada Help access/entitlement behavior, call-level retention
versus anonymous aggregates, provider-selection authority and the separate
approvals needed for credentials, capped spend, evaluation material,
processing and production release. Complete security, privacy, tenancy, legal,
bilingual-content and accessibility review.

The accepted decision is
[ADR-0038](adr/0038-multiprovider-speech-routing-kannada-help-and-first-audible-gate.md).
APP-0077 provides the ADR-0001 approval evidence for its exact master-plan v3.15
amendment and authorizes only SPEECH-OBS-01. Provider credentials, calls,
spend, evaluation/learner-content processing and production remain separately
gated.

### SPEECH-OBS-01 — Complete measurement first

1. Add versioned rate cards and explicit usage-unit/currency fields, including
   tier/minimum, tax, discount, credit and invoice-reconciliation evidence.
2. Unify `voice_provider_runs` and `ai_cost_ledger` through a read model without
   rewriting provider-call history while it is retained; apply the accepted
   30-day aggregation/deletion policy.
3. Persist sampled, content-free release-to-first-audio timings with authenticated
   client telemetry, documented clocks, sample policy and retention review;
   never use transcript/audio content as metric labels.
4. Distinguish provider dispatch from cache/replay, and prove successes,
   failures, cancellations, partial streams, retries and fallbacks are charged
   and counted exactly once.
5. Reconcile at least one real, capped provider statement for every candidate
   before claiming comparative economics.

### SPEECH-PROVIDER-01 — Governed adapters and routing

1. Integrate Azure, Sarvam and ElevenLabs only behind the project-owned STT/TTS
   ports. Freeze exact provider/model/API/voice/region/profile identities and a
   non-secret configuration hash; detect and block unreviewed drift.
2. Server-owned, versioned routing sends an ordinary operation to one primary
   and only a bounded reviewed fallback. Allow no more than two total dispatch
   attempts per stage; define retry categories, timeouts, circuit breakers,
   quotas and independent kill switches.
3. Unselected providers remain disabled or benchmark-only. Clients cannot
   select a provider, and ordinary learner audio is never three-way shadowed.
4. Before any content dispatch, record the provider DPA, subprocessors,
   processing/storage locations, human-review/no-training settings, retention,
   deletion verification and incident path. ElevenLabs learner audio requires
   the approved India-residency/Zero Retention configuration for the exact
   chosen product; equivalent evidence is required for Azure and Sarvam.
5. Use least-privilege revocable secrets outside Git, browser and database;
   HTTPS destination allowlists with redirects disabled or revalidated; TLS
   verification; safe logs; capped spend; and provider-specific shutdown and
   deletion runbooks. Disabling new dispatch must not stop deletion work.

### SPEECH-ADMIN-01 — Operator comparison

Add the scoped query, API, OpenAPI/client types and accessible portal screen
described in §5. Include explicit platform and organization permissions,
audited exports, cross-tenant/cache/job tests, small-cell suppression and all
empty, partial, stale-rate and unreconciled states.

### SPEECH-LAT-01 — Fix the existing waterfall

1. Return or stream the validated main reply without waiting for optional
   Kannada correction TTS.
2. Record model time-to-first-token and completion separately; bound reply
   length, review allowlisted prompt/context overhead and benchmark the current
   deployment if the model stage continues to miss the gate.
3. For the first implementation, validate the complete bounded model reply and
   then stream its first audio chunk instead of embedding a full WAV in the
   final turn JSON. Speaking from an incomplete model stream requires a
   separate ADR and independently validated incremental envelope.
4. Generate optional guidance in parallel or after main playback begins.
5. Deliver generated media only through authenticated, private, `no-store`
   streaming or short-lived signed access, with a bounded polling recovery path;
   never expose a reusable public object URL.
6. Reuse/preconnect provider connections where supported and use a compressed
   mobile playback format where the verified client path supports it.
7. Define and measure turn submission acknowledgement, first substantive
   response signal and first audible audio separately. Keep visual/listening
   acknowledgement under 100 ms, but never present it as the answer latency.
8. Propagate cancellation, deadlines and backpressure across browser, API and
   provider adapters; never autoplay a stale or cancelled response.
9. Keep safety/schema validation before speech; never play unvalidated raw
   model tokens.

### SPEECH-RT-01 — Benchmark realtime recognition

Benchmark Sarvam Saaras realtime and ElevenLabs Scribe v2 Realtime against the
current recorded path. A WebSocket transport requires the ADR and evidence
required by master plan §14.2. Retain explicit learner turn submission and do
not convert this checkpoint into always-on capture or full duplex. Preconnect
only after consent; handle endpointing, reconnect, duplicate finalization and
network downgrade; retain the current bounded recorded path as the reviewed
recovery route.

### SPEECH-BAKEOFF-01 — Controlled three-provider evaluation

Before the first dispatch, freeze the corpus manifest, exact model/API/voice/
region/profile/configuration fingerprints, scoring rubric, weights, subgroup
floors, hard gates, spend cap and expiry. Use at least 200 stratified STT
utterances plus 20–50 Kannada, English and code-mixed TTS scripts. Material must
be synthetic, licensed or separately consented for provider comparison; encrypt
detailed fixtures/evidence, restrict access and delete them at the approved
expiry.

Include Bengaluru/Mysuru, Coastal, Malenadu and North Karnataka patterns;
English and Kannada-English code-mix; quiet/noisy rooms; cheap target Android
microphones; Wi-Fi and typical 4G; and cold/warm connections. Use blind,
independent bilingual listeners for TTS and report overall and subgroup results.
Compare:

- Kannada and English word/character error, semantic intent, names, numbers,
  negation, code-mix preservation, silence hallucination and edit rate;
- native-listener TTS intelligibility, naturalness, Swathi identity, beginner
  pace and Kannada/English code-mix pronunciation;
- first-result, finish and release-to-first-audio p50/p95 on target Android,
  Wi-Fi and typical 4G;
- success, timeout, retry, fallback and quota behavior; and
- estimated and invoice-reconciled cost per successful usable turn.

Quality, privacy, safety and tenancy are hard gates; cost ranks only candidates
that pass them. Exercise at least 50 concurrent voice turns and the approved 2×
forecast-peak load, with cold/warm results kept separate. Do not send ordinary
learner audio to three providers to create this dataset. Re-run the frozen suite
when model/configuration, price, contract, latency or quality drift is material.

### KN-HELP-01 — Kannada Help / Ask Swathi

After the usage and provider boundary is proven, raise and accept the required
ADR/master-plan amendment, then add the previously recommended mode: preserve
the original Kannada/code-mixed transcript within the approved consent and
retention window, allow a short validated Kannada reply, optionally provide one
English bridge sentence and route it through the chosen Kannada voice plus its
reviewed speech fallback.

Full Kannada replies are allowed only when the learner explicitly enters
Kannada Help. English Practice may recognize a Kannada question and provide at
most one short Kannada bridge before returning to English. The server derives
reply mode from the authorized session and intent, never detected language
alone. Kannada Help follows the backend free-speech entitlement; any demo use
must be separately explicit and capped. Its UI, safety and fallback copy require
exact-revision independent bilingual approval before production. This advisory
mode must not write marks, mastery, progression, review schedules or unlocks,
and a translated intent must never become grammar evidence.

### SPEECH-PROD-01 — Production gate

Select one primary and at least one reviewed fallback per stage. Production
means any public or paid learner release, Play production/public listing or
`APP_ENV=production`; it is blocked until:

- the master-plan end-of-turn-to-first-response-signal target demonstrates
  median ≤4 seconds and p95 ≤8 seconds on target networks;
- the separately accepted DR-0048 first-audible gate demonstrates median ≤4
  seconds and p95 ≤8 seconds, with a recommended launch objective of median
  ≤1.8 seconds and p95 ≤4 seconds from turn end to first audible response;
- quality and subgroup gates pass with accountable human review;
- provider DPA, processing location, retention/no-training configuration,
  deletion behavior, subprocessor evidence and emergency disablement pass;
- consent names every provider that can receive learner audio or text;
- rate cards, spend caps, circuit breakers and invoice reconciliation work;
- operator APIs prove permission and cross-tenant denial before reading usage;
- known provider/dependency failure becomes visible within two seconds, voice
  availability meets the accepted SLO, and the 50-concurrent plus 2× approved
  forecast-peak capacity gates pass;
- staging-clone migration, backup/restore, deletion, feature-flag, canary,
  provider-failure and one-command rollback rehearsals pass;
- `make openapi`, `make verify`, `make e2e`, `make ai-eval`, `make build`,
  PostgreSQL tenancy, load/soak, Android lifecycle and accessibility gates pass;
  and
- exact evidence is recorded in status/decision/quality/privacy/runbook
  documents and the Accountable Product Owner explicitly approves release.

A controlled, separately consented staff/evaluation lane may remain
non-production before this gate. An environment label cannot be used to expose
the incomplete route to public or paying learners.

## 7. Options to resolve

| Option | Result | Recommendation |
|---|---|---|
| **A — Metered routed comparison, streaming latency work and controlled bake-off** | All three providers are integrated behind project ports, but unselected candidates stay disabled or benchmark-only; one provider receives an ordinary learner operation plus bounded fallback; admin compares honest usage/cost/latency; production routing follows evidence | **Selected as the pre-production plan** |
| B — Send every learner recording to all three providers | Faster collection of comparison data, but triples processing, expands privacy exposure and confuses fallback/attempt accounting | Reject |
| C — Select one provider now from vendor claims | Smallest engineering scope, but no local Kannada/code-mix, network, reliability or effective-cost evidence | Reject |

APP-0077 accepts Option A's governance through ADR-0038 and authorizes only
SPEECH-OBS-01. It must not be interpreted as approval for production, provider
credentials or calls, learner-audio/evaluation processing, voice cloning or
provider spend.

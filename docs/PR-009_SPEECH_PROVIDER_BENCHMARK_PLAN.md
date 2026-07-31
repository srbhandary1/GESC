# PR-009 speech provider benchmark plan

## Authority and purpose

This is the proposed locked development benchmark referenced by
[ADR-0008](adr/0008-azure-speech-development-provider-and-benchmark-policy.md).
It becomes authoritative only if that exact ADR is accepted.

The plan evaluates the PR-009 Azure Speech adapter boundary using synthetic
content. It does not evaluate real learners, approve production, change English
as the learning language, authorize PR-010 or grant automated speech any
authority over deterministic learning records.

## Frozen stack

| Field | Required value |
|---|---|
| Benchmark ID | `pr009-azure-speech-synthetic-v1` |
| Region | `centralindia` |
| STT | Azure Fast Transcription REST `2025-10-15`, `en-IN`, default transcription Speech model |
| TTS | Azure real-time REST `/cognitiveservices/v1`, `en-IN-NeerjaNeural` |
| TTS fixture voices | `en-IN-NeerjaNeural` and `en-IN-PrabhatNeural` |
| TTS output | `riff-24khz-16bit-mono-pcm` |
| STT input | Locally normalized 16 kHz mono signed 16-bit PCM/WAV |
| Provider callbacks | Prohibited |
| Provider logging/customization | Off; `profanityFilterMode=None`; no batch, custom model, custom voice, language identification, diarization or pronunciation assessment |
| Maximum provider attempts | Two total attempts under the reviewed retry policy |
| Benchmark data | Project-authored synthetic strings and provider-generated synthetic audio only |

The exact endpoint resource, provider configuration fingerprint, runner commit,
dependency locks, operating-system/toolchain versions, run instant, price
source/currency conversion instant and safe provider metadata are recorded in
the run manifest.

## Preflight gates

Every item must pass before the first content request:

- ADR-0008 is accepted by exact proposal hash.
- The dedicated non-production Central India Speech resource exists.
- The exact contracting entity, subscription agreement, DPA and subprocessor
  evidence required by ADR-0008 is reviewed and current.
- Data/model-improvement logging and custom Speech features are confirmed off.
- The benchmark credential is dedicated, least privilege, outside Git and
  absent from both `.env` files.
- The exact endpoint host is allowlisted and redirect following is disabled.
- Upload/provider features remain off for every non-benchmark organization.
- The fixture text manifest and runner revision are committed and SHA-256
  locked before requests.
- The request cap and spend cap are configured server side and locally.
- The Accountable Product Owner has separately approved provider spend up to
  the hard cap of `₹1,000`, including retries but excluding tax.
- Private encrypted ephemeral fixture/result storage has a maximum 24-hour
  expiry and no backup/versioning path.
- Logs, traces, audit and outbox content scans are enabled.

A failed preflight produces no provider content request.

## Synthetic corpus

The corpus contains exactly 48 project-authored English utterances. They contain
no real name, phone number, address, organization, learner, employee, customer,
credential, licensed curriculum body or production conversation.

| Category | Count | Purpose |
|---|---:|---|
| Foundation phrases | 12 | Common greetings, requests, questions and short answers |
| Beginner-form utterances | 12 | Preserve plausible grammar errors rather than silently correcting them |
| Indian daily-life vocabulary | 8 | Synthetic references to bus, market, office, college and service situations |
| Numbers and spelled tokens | 8 | Synthetic non-routable numbers, times and letter sequences |
| Repair and clarification | 8 | Repetition, correction, hesitation and “please say that again” patterns |

### Exact utterance manifest

The fixture manifest must contain these exact NFC strings in this exact order:

| ID | Reference text |
|---|---|
| `foundation-01` | Hello, how are you today? |
| `foundation-02` | Good morning, I would like some tea. |
| `foundation-03` | Please tell me where the bus stop is. |
| `foundation-04` | I am learning to speak English. |
| `foundation-05` | Could you help me with this form? |
| `foundation-06` | What time does the office open? |
| `foundation-07` | I need a ticket to the city centre. |
| `foundation-08` | Thank you for explaining that clearly. |
| `foundation-09` | May I sit here? |
| `foundation-10` | I will call you after lunch. |
| `foundation-11` | Where can I charge my phone? |
| `foundation-12` | Please speak a little more slowly. |
| `beginner-01` | Yesterday I go to the market. |
| `beginner-02` | She do not understand my question. |
| `beginner-03` | I am working here since two years. |
| `beginner-04` | He have a meeting at ten. |
| `beginner-05` | Where you are going now? |
| `beginner-06` | I did not went to college today. |
| `beginner-07` | My brother live near the station. |
| `beginner-08` | Please give me one informations. |
| `beginner-09` | I can able to finish this work. |
| `beginner-10` | We was waiting outside the office. |
| `beginner-11` | I have seen him yesterday. |
| `beginner-12` | She is knowing the answer. |
| `india-01` | The next bus leaves from platform four. |
| `india-02` | I will meet the customer near the metro station. |
| `india-03` | Please send the document before the office closes. |
| `india-04` | The college interview starts after lunch. |
| `india-05` | I need change for a hundred rupee note. |
| `india-06` | The auto driver is waiting outside the gate. |
| `india-07` | Is this counter open on Saturday? |
| `india-08` | Please pack the parcel carefully. |
| `number-01` | The practice code is A B seven nine. |
| `number-02` | My appointment is at nine thirty tomorrow. |
| `number-03` | Please write zero zero four two on the sample form. |
| `number-04` | Room twelve is on the second floor. |
| `number-05` | The mock order number is five eight three one. |
| `number-06` | The bus arrives at six forty-five. |
| `number-07` | Spell the word train as T R A I N. |
| `number-08` | The sample price is two hundred and fifty rupees. |
| `repair-01` | Sorry, I did not understand that. |
| `repair-02` | Could you please say that again? |
| `repair-03` | I mean Tuesday, not Thursday. |
| `repair-04` | Let me try the sentence one more time. |
| `repair-05` | Did you say fifteen or fifty? |
| `repair-06` | Please wait, I need a moment to think. |
| `repair-07` | No, the second word was station. |
| `repair-08` | I said the blue bag, not the black bag. |

Each string is:

- 3–20 words and at most 160 Unicode code points;
- NFC normalized;
- assigned an opaque fixture ID;
- paired with one exact reference transcript;
- classified by category but not by a human demographic; and
- reviewed to exclude personal or licensed content.

Both locked TTS fixture voices synthesize every utterance. Each clean output is
normalized locally and transformed into three deterministic conditions:

1. `clean` — normalized only;
2. `snr20` — seeded white noise mixed at 20 dB signal-to-noise ratio; and
3. `snr10_lowpass` — seeded white noise at 10 dB plus a deterministic
   3.4 kHz low-pass transform.

This produces exactly `48 × 2 × 3 = 288` STT clips. The transform seed is
derived from `SHA-256(benchmark_id || fixture_id || voice || condition)` and
the transform implementation/revision is part of the locked runner.

The corpus deliberately does not label or simulate a Karnataka subgroup. Azure
synthetic voices are not evidence of learner accent coverage.

## Measurements

### STT quality

Normalization for word-error rate:

- Unicode NFKC for scoring only;
- Unicode case fold;
- remove punctuation except apostrophes inside words;
- collapse whitespace;
- express the reference number tokens exactly as authored; and
- publish both raw and normalized digests, never transcript bodies, in the safe
  report.

The ephemeral detailed report calculates:

- word-error rate overall, by condition, voice and corpus category;
- exact-match rate;
- blank-result rate;
- insertion count and hallucination count;
- finite confidence coverage and confidence/error buckets;
- returned locale; and
- provider duration versus locally decoded duration.

### TTS quality

For all 96 clean TTS outputs, measure:

- HTTP success and bounded response size;
- actual RIFF/WAVE structure, PCM format, sample rate, channels and duration;
- empty, truncated or malformed output;
- synthesis latency and characters billed;
- round-trip STT word-error rate after local normalization; and
- two accountable development listeners' intelligibility and acceptability
  disposition using only synthetic text.

Listener evidence records reviewer identifiers, date and category outcomes but
does not place audio or transcript text in the repository. This consolidated
development listening check is not independent language, accessibility or
production review.

### Performance, reliability and cost

Record:

- provider and end-to-end latency at p50, p95 and maximum;
- request/response byte counts;
- attempt and terminal failure categories;
- billed STT duration and TTS characters;
- actual or provider-calculated cost in billing currency;
- INR conversion source and timestamp; and
- projected cost for one 20-second STT plus 250-character TTS turn.

Run 20 additional repetitions of four fixed fixtures to detect warm/cold and
rate behavior. Each repetition runs one TTS and one STT operation for each
fixture, for 80 additional TTS and 80 additional STT logical operations.
Inject local 401, 403, 408, 413, 429, 500, 503, redirect,
timeout, malformed JSON, malformed WAV and oversized-body responses without
sending those injected cases to Azure.

The hard cap is 544 logical provider operations: 176 TTS and 368 STT. With the
two-total-attempt retry ceiling, the absolute HTTP attempt cap is 1,088. The
runner stops before either that cap or the separately approved `₹1,000`
provider-spend cap can be exceeded.

## Pass thresholds

Every critical gate and every threshold is conjunctive.

### Critical pass/fail gates

- All 288 provider STT inputs and 96 TTS inputs are synthetic and manifest
  locked.
- Every observed provider hostname and region matches the allowlist.
- No redirect, callback, public media URL, batch asset or remote stored object
  is created.
- No endpoint/data logging, customization, training or human-review option is
  enabled.
- No secret, audio, transcript, SSML body, object key, signed URL or provider
  payload appears in logs, traces, audit, outbox, database safe fields or the
  committed report.
- All fault-injection cases fail closed with at most two attempts.
- Withdrawal before dispatch prevents a provider call.
- Same-tenant other-learner and cross-tenant attempts cause no provider call.
- Learning attempt, mastery, progression and review tables are unchanged.
- Ephemeral detailed content and generated audio expire within 24 hours.

### Quantitative thresholds

| Measure | Required result |
|---|---:|
| Clean STT WER | `≤ 5%` overall and `≤ 10%` in every corpus category |
| 20 dB STT WER | `≤ 10%` overall and `≤ 18%` in every corpus category |
| 10 dB/low-pass STT WER | `≤ 20%` overall and `≤ 30%` in every corpus category |
| Blank STT result | `0` clean/20 dB; `≤ 1%` 10 dB/low-pass |
| Material hallucination | `0` in every condition |
| STT locale | `en-IN` for 100% of successful results |
| STT provider latency | p95 `≤ 4.0 s`, maximum `≤ 8.0 s` for the locked clips |
| Valid TTS media | 100% |
| TTS round-trip WER | `≤ 5%` overall |
| TTS provider latency | p95 `≤ 2.5 s`, maximum `≤ 5.0 s` |
| Listener intelligibility | `≥ 95%` outputs accepted by both listeners |
| Listener acceptability | `≥ 90%` outputs accepted by both listeners |
| Critical pronunciation defect | `0` |
| Successful request rate | `≥ 99%` before bounded retry; 100% terminally classified |
| Projected speech cost | `≤ ₹1.00` per 20-second STT + 250-character TTS turn, excluding tax and LLM |

A provider outage may prevent a benchmark pass but must still pass safe-failure
behavior. Thresholds are not loosened after results are seen. Any change
creates a new versioned proposal and invalidates comparisons.

For this plan:

- a **material hallucination** is any three-word consecutive insertion absent
  from the reference, any invented person/contact/account token, or any
  provider success over silence;
- a **critical pronunciation defect** is a meaning-changing number, negation
  or key noun error, or a phrase both listeners mark unintelligible; and
- provider latency starts immediately before HTTP dispatch and ends only after
  the bounded response is fully received and structurally validated.

## Result artifacts

The committed safe report contains only:

- benchmark, runner, corpus and configuration hashes;
- provider/service/API/region/voice labels;
- timestamps and environment/toolchain versions;
- aggregate counts, metrics, thresholds and pass/fail;
- bounded safe failure categories;
- aggregate usage and cost;
- evidence identifiers for DPA/subprocessor/credential review; and
- explicit limitations.

The following never enter Git:

- credentials or account/subscription details;
- input strings or SSML bodies if they have not already been approved as the
  synthetic corpus;
- audio, transcripts or word-level provider results;
- raw provider requests/responses or headers;
- listener contact details; or
- invoices, contracts and vendor-register contents.

The ephemeral detailed report is encrypted and automatically deleted within 24
hours. Its SHA-256 and deletion receipt may remain as content-free evidence.

## Commands and exit gate

After ADR acceptance and adapter/runner implementation, the required root
commands are:

```text
make verify
make e2e
make ai-eval
make build
```

`make ai-eval` must:

- fail unless the exact accepted policy, manifest hashes, safe secret reference
  and benchmark environment are present;
- require an explicit spend cap;
- reject production/learner organizations;
- never print content or credentials;
- exit nonzero for a missing threshold or partial run; and
- produce the safe report plus ephemeral-deletion evidence.

PR-009 remains incomplete until all commands pass, the safe benchmark evidence
is reviewed, provider limits are recorded, temporary servers are stopped and
the Accountable Product Owner accepts an exact implementation commit. PR-010
and production remain separately unauthorized.

## Known limitations

- Azure TTS generates the audio used to evaluate Azure STT, so the round trip
  is a deterministic development/integration benchmark with optimistic quality
  bias.
- Synthetic speech does not represent hesitant adults, Karnataka accents,
  code-switching, microphone variation, disability, illness or real background
  conditions.
- Two development listeners do not constitute independent language,
  accessibility or academic review.
- Managed model rotation can change results without an application deployment.
- Passing this plan does not authorize real learner audio or production.

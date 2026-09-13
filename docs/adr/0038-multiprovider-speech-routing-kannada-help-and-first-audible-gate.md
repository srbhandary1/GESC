---
status: accepted
date: 2026-09-09
deciders:
  - Accountable Product Owner and Technical Decision Owner
  - Accountable Product Owner exercising Security Reviewer, Privacy Reviewer and Tenancy Reviewer roles for development
consulted:
  - Codex as architecture author and recorder only
informed: []
amends_master_plan: true
approval_evidence:
  - APP-0077
approval_reference: Exact human approval statement in the active Codex conversation on 2026-09-09
amends:
  - ADR-0010
  - ADR-0011
  - ADR-0027
---

# ADR-0038: Multi-provider speech routing, Kannada Help and first-audible gate

## Status

Accepted on 9 September 2026 under
[APP-0077](../APPROVALS.md#app-0077). The approval exercises the Accountable
Product Owner, Technical Decision Owner, Security Reviewer, Privacy Reviewer
and Tenancy Reviewer roles for development and authorizes only
`SPEECH-OBS-01` next. It does not authorize a provider call, credential use,
spend, evaluation or learner-content processing, or production release.

## Context

DR-0048 requires Azure, Sarvam and ElevenLabs speech implementations to be
comparable before production and requires an explicit Kannada Help experience.
The present contracts cannot safely support that work:

- ADR-0027's Indic STT path translates speech to English and does not preserve
  a native or code-mixed transcript as the canonical learner utterance;
- ADR-0010 and the current model adapter require `assistant_reply_en`, while
  Kannada Help requires an intentionally Kannada reply;
- ADR-0011 allows Kannada correction guidance but keeps the main reply in
  English;
- existing provider evidence records finish latency and estimated cost but not
  stable billable units, rate-card provenance, first partial/token/byte/audio
  latency or invoice reconciliation;
- the current retry loop can repeat the same bound adapter but does not express
  a versioned primary/fallback route; and
- the master plan authorizes turn-based upload/SSE/media transport and requires
  an ADR before WebSocket adoption.

The learning language remains English. Kannada Help is a support path that lets
a learner ask a genuine question in Kannada and receive a bounded Kannada
answer from Swathi, followed by an English bridge back into practice. It must
not silently turn ordinary English Practice into a general Kannada assistant.

## Decision

### 1. Product modes and authority

1. Add the explicit conversation mode `kannada_help`. Its product label is
   **Kannada Help — Ask Swathi**. The canonical persona remains Swathi; a
   provider voice name must never become the product persona.
2. `kannada_help` is available only to an authenticated adult learner who has
   the same server-side Free Speech entitlement required by Friend/free-speech
   sessions. Demo access, if enabled later, must be explicit, separately capped
   and must not weaken the authenticated production path.
3. In Kannada Help, the learner may speak Kannada, English or an expected
   Kannada/English code mix. Swathi gives a concise Kannada answer and an
   actionable English bridge. The bridge must invite or model the next English
   utterance; it must not fabricate a score or learning record.
4. Tutor, Friend and Role-play remain English-practice modes. If Kannada is
   detected in one of those modes, the system may provide one short Kannada
   repair or explanation and then return to English. Input-language detection
   alone must not switch the session into Kannada Help.
5. The server derives reply policy and locale from the authorized session mode,
   learner intent, supported locale policy and safety state. The browser and
   speech providers cannot choose the authoritative mode, provider or locale.
6. All AI output remains advisory. It cannot change marks, mastery,
   progression, review schedules, certificates, entitlements or authoritative
   curriculum records.

### 2. Project-owned speech and reply contracts

Provider SDK objects and native payloads remain inside adapters. The stable STT
result contract must expose at least:

- `transcript_original`: the native or code-mixed transcript used as learner
  evidence;
- `transcript_locale`: the normalized detected/selected BCP 47 locale, with an
  explicit unknown value when the provider cannot determine it;
- `confidence`: optional normalized confidence with its provenance; and
- `english_intent_text`: an optional derived translation or semantic aid that
  is separate from `transcript_original`.

`english_intent_text` must never replace the original transcript and must not
be used as grammar, pronunciation or correction evidence. Both fields follow
ADR-0007's consent, encryption, retention, review, correction and deletion
rules. Provider metadata is content-free and must not include either value.

The stable conversation result contract must expose:

```json
{
  "understood_intent": "bounded advisory intent",
  "assistant_reply": {
    "text": "validated reply text",
    "locale": "en-IN or kn-IN"
  },
  "english_bridge_en": "optional English bridge",
  "priority_correction": null,
  "safety_state": "ok or fallback"
}
```

`assistant_reply.locale` is mandatory and must be allowlisted. During the
additive migration, `assistant_reply_en` may remain only as a compatibility
alias when `assistant_reply.locale` is `en-IN`; it must be an exact copy and
must be retired after supported clients migrate. Kannada Help must never place
Kannada text in a field named `_en`. The TTS request locale comes from the
validated reply envelope, not from UI locale or provider detection.

### 3. Provider routing and dispatch limits

1. Azure, Sarvam and ElevenLabs may be implemented only behind the existing
   project-owned STT/TTS ports, extended by project-owned realtime interfaces
   where needed.
2. Every enabled route is server-owned, environment-specific and versioned. It
   freezes provider, product/model, API version, voice, locale/profile,
   region/deployment class and a non-secret normalized configuration hash.
3. An ordinary learner operation dispatches to one selected primary provider.
   It may dispatch once to one reviewed fallback only for allowlisted failure
   categories. The hard limit is two provider dispatches per stage and logical
   operation. Transport retries that could be billable count as dispatches.
4. A fallback cannot consume a second learner entitlement. Every provider
   dispatch, including fallbacks, cancellations and failed/partial streams, is
   metered independently.
5. Clients cannot select or shadow providers. Unselected providers are disabled
   or benchmark-only. Ordinary learner audio is never sent to three providers.
   Multi-provider comparison uses synthetic, licensed or separately consented
   evaluation material in the governed benchmark lane.
6. Each provider has independent configuration validation, quota/spend cap,
   circuit breaker, dispatch kill switch and deletion/reconciliation path.
   Disabling new calls must not disable deletion work.

### 4. Realtime transport boundary

An authenticated first-party WebSocket relay may be implemented and benchmarked
for realtime STT. It remains inside the Laravel modular-monolith boundary and
must satisfy all of the following:

- the server holds provider credentials and validates the destination; no
  provider credential or direct provider socket is exposed to a client;
- voice consent, active-organization authorization, entitlement and rate/spend
  checks occur before provider dispatch;
- partial transcripts are ephemeral, advisory and never learner evidence;
- only an explicit completed turn submission creates the canonical transcript
  and conversation turn;
- capture is push-to-talk/turn-based, not always-on or full duplex;
- bounded recorded upload remains an accessible recovery path; and
- reply state continues over authenticated SSE/polling and private no-store or
  short-lived signed media unless a later accepted ADR changes it.

The complete bounded model reply must be validated before TTS begins. Speaking
unvalidated tokens from an incomplete model stream is outside this decision and
requires a later ADR. Main-reply audio may stream after validation; optional
correction/guidance audio must not block the first main audio.

### 5. Retention, economics and portal authority

ADR-0007's retention maxima remain unchanged. Content-free provider-call
records are append-only while retained, remain linked to a learner/turn for no
more than 30 days, and are then deleted after creation of reviewed anonymous
aggregates. Long-lived aggregates must contain no learner, turn, recording,
pseudonymous portal identifier or unsafe small cell. Rate cards may be retained
longer because they contain no learner-content linkage and are immutable by
version.

Every attempted provider operation must record an explicit billable unit kind,
quantity, route role, dispatch/cache status, exact provider fingerprint,
content-free timing milestones, immutable rate-card version, native currency,
INR conversion provenance, estimated cost and reconciliation status. A rate
change creates a new rate-card version and never rewrites historical evidence.

Provider operations are metered even when an institutional entitlement is
unmetered. One accepted logical learner turn is charged once to entitlement;
provider retries and fallback calls affect platform cost only.

Negotiated rates, discounts, credits and invoices require a platform-only
named permission and audited reads/exports. An organization operator needs a
separate named permission and may see only active-organization usage and
approved allocated cost. Neither view exposes audio, transcripts, reply text,
provider payloads, secrets or per-learner identifiers.

### 6. Latency gates

The existing end-of-turn to first-substantive-response-signal SLO remains
median no more than 4 seconds and p95 no more than 8 seconds. A UI acknowledgement
or animation does not satisfy it.

Add a separate production gate for end of explicit learner turn to first
audible main-reply playback: median no more than 4 seconds and p95 no more than
8 seconds under the approved device/network/corpus profile. The launch
objective, which is stricter but not initially contractual, is median no more
than 1.8 seconds and p95 no more than 4 seconds. An acknowledgement objective
of 100 ms is measured separately and never reported as model or speech latency.

Server connection, first STT partial/final, model first token/completion, first
TTS byte/completion and sampled client first-audible milestones use documented
clock origins and sample policies. Missing/partial/cancelled samples are counted
explicitly rather than dropped from successful percentiles.

### 7. Production selection and release

Production routing is selected only after a controlled bake-off with frozen
configurations and a predeclared rubric. At minimum it includes 200 STT items,
20–50 TTS scripts, Kannada/English/code-mix and supported dialect/noise/device/
network subgroups, blind bilingual human review, accessibility review, failure
and deletion drills, 50-concurrent-turn capacity and 2x burst evidence.

Selection is based on cost per successful usable turn, transcript/voice quality,
first-audible latency, reliability, privacy/residency/retention evidence and
operational recovery—not advertised price or provider latency claims alone.

Acceptance of this ADR authorizes only the next bounded checkpoint,
`SPEECH-OBS-01`, when the approval statement explicitly says so. It does not
authorize credentials, provider calls, spend, evaluation content, learner-data
processing or production. Those remain separate checkpoint approvals and
release gates. Independent security, privacy/legal, tenancy, bilingual-content
and accessibility review remain mandatory before production even if one human
holds consolidated development roles.

## Alternatives considered

### Send every learner turn to all three providers

Rejected because it multiplies sensitive-audio disclosure and cost, confuses
retry identity, and is unnecessary for ordinary delivery. Controlled benchmark
material provides comparable evidence without routine shadowing.

### Make a provider-owned agent runtime authoritative

Rejected because curriculum, safety, tenancy and learning authority must remain
inside project-owned deterministic boundaries.

### Translate all Kannada input to English and discard the original

Rejected because it loses learner evidence, conceals recognition errors and
can turn translated text into invalid grammar evidence.

### Use UI locale as the reply and TTS locale

Rejected because a Kannada interface does not imply a Kannada learning reply.
The authorized conversation mode and validated output envelope determine it.

### Keep HTTPS-only recorded STT and measure finish latency only

Rejected as the sole production design because it cannot prove or optimize
first-result and first-audible latency. It remains the recovery path.

## Consequences

### Positive

- Kannada questions and answers become explicit, reviewable product behavior.
- Provider quality, latency and economics can be compared on common contracts.
- Sensitive learner audio is not routinely multiplied across vendors.
- First-audible performance becomes a real gate instead of a perceived-speed
  proxy.
- Provider changes remain reversible behind project-owned ports and routing.

### Negative and trade-offs

- The additive contracts, usage ledger, rate cards, telemetry and migration
  compatibility period add implementation and operational complexity.
- Realtime streaming expands abuse, concurrency, cancellation and partial-state
  test requirements.
- A two-dispatch limit may return a visible fallback sooner instead of trying a
  third provider.
- The strict benchmark and specialist reviews delay production selection.

## Security, privacy and tenancy impact

- Every new resource is organization-owned or explicitly platform-global;
  organization queries, exports, jobs, caches and realtime channels require
  active-organization scoping and cross-tenant denial tests.
- Consent withdrawal and cancellation stop queued dispatch/retry and continue
  object/provider deletion reconciliation.
- Secrets remain outside Git, browsers, ordinary databases and logs, with
  least privilege, rotation evidence, HTTPS allowlists, TLS verification and
  redirect rejection/revalidation.
- No telemetry label or export contains raw audio, transcript/reply text,
  prompts, personal learner data, secrets or provider payloads.
- Each exact provider product requires DPA, subprocessors, processing/storage
  location, no-training/human-review, retention, deletion and incident evidence
  before any learner-content dispatch. ElevenLabs requires reviewed India
  residency and Zero Retention evidence for the selected product.

## Authoritative records and consistency impact

- Conversation mode, route version, submitted original transcript and validated
  reply envelope are immutable for a completed turn.
- Logical-turn idempotency remains separate from provider-dispatch identity.
  One entitlement consumption is transactionally/idempotently tied to the
  logical turn; each provider dispatch has its own append-only usage identity.
- Call evidence is append-only until the approved retention purge. Rate cards
  are immutable/versioned. Invoice reconciliation appends evidence and never
  rewrites the provider attempt.
- Additive schema migrations must provide PostgreSQL constraints, rollback/data
  compatibility analysis and concurrency tests. No migration is authorized by
  proposed status.

## API, client and operational impact

- OpenAPI and generated clients must add `kannada_help`, the original/derived
  transcript distinction, locale-bearing reply envelope and content-free
  telemetry contracts through an additive compatibility period.
- Realtime endpoints require authenticated connection and message contracts,
  origin/rate/concurrency limits, cancellation semantics and RFC 9457 setup
  failures.
- Operator APIs must meet p95 800 ms and provide accessible loading, empty,
  partial, stale-rate, unreconciled and export states.
- Rollback disables individual routes while retaining text/recorded recovery
  and deletion/reconciliation workers.

## Master-plan amendment

The master plan is updated from v3.14 to v3.15 in this accepted change with an
impact note naming ADR-0038 and these exact normative additions:

1. **§1.1 and §4.1:** English remains the learning language; add Kannada Help —
   Ask Swathi as a Free-Speech-entitled support mode with a Kannada answer and
   English bridge, not a fourth authoritative learning/assessment mode.
2. **§5.1 and §5.3:** preserve native/code-mixed transcript evidence; store any
   English translation/intent separately and prohibit it as grammar evidence.
3. **§14.1:** keep explicit turn submission and add non-authoritative realtime
   partial STT; the canonical turn begins only after submission.
4. **§14.2:** authorize the authenticated server-relayed WebSocket boundary in
   this ADR, retain SSE/poll/private-media response paths and recorded fallback,
   and continue to prohibit direct provider credentials and full duplex.
5. **§14.4–§14.5:** require versioned primary/fallback routing, two-dispatch
   limit, locale-bearing STT/reply project contracts and the temporary exact
   `assistant_reply_en` compatibility alias.
6. **§14.7–§14.8:** prohibit routine multi-provider learner-audio shadowing and
   require controlled bake-off evidence for production selection.
7. **§18.1 and §18.3:** add provider-specific dispatch, consent, residency,
   retention/deletion and content-free telemetry controls while preserving
   ADR-0007 maxima.
8. **§20.1:** retain the response-signal SLO and add the distinct first-audible
   production gate and stricter launch objective defined above.
9. **§20.4:** require billable-unit kinds, immutable rate cards, currency/INR
   provenance, all-dispatch metering and invoice reconciliation; entitlement
   charging remains once per logical turn.
10. **§21.2–§21.3 and §22:** add the governed STT/TTS bake-off, sample/subgroup,
    blinded bilingual/accessibility review and pre-production provider gate.
11. **§32:** record ADR-0038 as the controlling decision and state that provider
    credentials, spend, evaluation processing and production require their
    later explicit gates.

ADR-0038 also amends ADR-0010's English-only model field, ADR-0011's
English-only main-reply assumption and ADR-0027's translate-only Indic STT
contract only as specified above. All other controls in those ADRs and
ADR-0007 remain in force.

## Approval evidence

[APP-0077](../APPROVALS.md#app-0077) records the exact human statement,
exercised development roles, bounded authorization and retained production
gates. Codex authored and recorded the decision only and is not an approver.
Independent specialist, legal, bilingual-content and accessibility reviews
remain required before production.

## Verification

Before acceptance:

- verify ADR front matter and internal links;
- compare every amendment against master-plan v3.14 and ADR-0001;
- run `git diff --check` for documentation changes; and
- obtain the authorized human's exact approval and bounded next-checkpoint
  authorization.

Before `SPEECH-PROD-01`:

- run `make verify`, `make e2e`, `make ai-eval` and applicable capacity,
  deletion and provider-shutdown drills;
- pass OpenAPI/generated-client drift, PostgreSQL, tenancy, authorization,
  idempotency, cancellation and retention tests; and
- attach independent security, privacy/legal, tenancy, bilingual-content,
  accessibility and provider-contract evidence.

## Follow-up

Execute only in this order after the applicable explicit approvals:

1. `SPEECH-OBS-01` — measurement, rate cards, retention-safe aggregates and
   invoice reconciliation;
2. `SPEECH-PROVIDER-01` — governed adapters and routing;
3. `SPEECH-ADMIN-01` — scoped Speech Economics portal;
4. `SPEECH-LAT-01` — remove the existing response waterfall;
5. `SPEECH-RT-01` — realtime STT relay and streaming response path;
6. `SPEECH-BAKEOFF-01` — controlled provider evaluation;
7. `KN-HELP-01` — production-quality Kannada Help experience; and
8. `SPEECH-PROD-01` — final provider and release gate.

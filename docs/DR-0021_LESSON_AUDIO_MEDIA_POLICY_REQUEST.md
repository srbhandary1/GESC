# DR-0021 — Long-form lesson-audio media policy request

**Status:** Resolved — Option A accepted under APP-0048 / D-0145

**Needed by:** LPL-01 governed ingestion of the four authorized Level 1
teaching MP3s

**Authority:** `ENTERPRISE_PRODUCT_MASTER_PLAN.md` §§6.3–6.4, 10–11,
13–18 and 25; ADR-0004; ADR-0013; ADR-0015; D-0139–D-0143;
`docs/ADMIN-MEDIA-01_SPEC.md`; `docs/LESSON_PRACTICE_LOOP_PLAN.md`;
APP-0047

**Opened:** 10 August 2026

**Resolved:** 10 August 2026

## Problem

APP-0047 now authorizes four exact Level 1 teaching MP3s for private
non-production ingestion, transcription and AI-assisted derivative lesson
drafting. The authorization does not choose a runtime media kind or weaken the
current Media Library policy.

The current platform has only two governed media kinds:

- `model_audio`, for short daily-mission model lines, with a 10,000,000-byte
  and 300,000-millisecond Media Library ceiling; and
- `lesson_video`, for long-form MP4 lesson media, with its own private delivery
  and policy under ADR-0013.

The selected Level 1 files have these exact observed properties:

| Order | Part | SHA-256 | Bytes | Duration |
|---:|---|---|---:|---:|
| 1 | Introduction | `6838818d74481ffe38e13948dea2954886dcc519ed927112b703f8e2064573aa` | 4,968,408 | 310,525.500 ms |
| 2 | Practice | `75909e9baf340356d7ecf66435977ee1cce1fe93a597da58a24b5b9ea1833dd7` | 7,357,881 | 459,867.563 ms |
| 3 | Conversation | `8124b3bc6f71b65258358b0121cc3d72f1543948ca3ef3a1bb419f1fa0d65c70` | 2,157,215 | 134,825.938 ms |
| 4 | Interaction | `4862e927078ecb0ea56b7240f6d5b52356365115a6512836357bf340fdcf895e` | 4,276,686 | 267,292.875 ms |

All four were detected as MPEG Layer III, 128 kbps, 44.1 kHz, joint stereo.
Introduction and Practice exceed the existing five-minute `model_audio`
inspection limit. More importantly, these are ordered teaching recordings,
not short pronunciation/model lines. Treating them as `model_audio` would
conflate two pedagogical and delivery contracts and make future policy changes
unsafe.

This is a material media-policy and public-contract choice. LPL-01 must not
silently raise a mature media limit, split/re-encode owner-selected evidence,
or invent a third kind without an accepted decision.

## Decision profiles

| Option | Boundary | Trade-off |
|---|---|---|
| **A — Add a distinct governed `lesson_audio` kind (recommended)** | Preserve each original object and hash; give long-form teaching audio bounded inspection, metadata and later publication-pinned delivery contracts; leave `model_audio` unchanged | Small additive API/UI/config/adapter slice, but keeps semantics, limits, rights and future offline policy clear |
| B — Raise `model_audio` duration limits and reuse it | Accept the files under the current short-model kind | Less immediate code, but weakens a stable daily-mission boundary and makes attachment, preview, delivery and offline semantics ambiguous |
| C — Split or re-encode the source into shorter `model_audio` objects | Create derived files that fit current limits | Avoids a new kind, but the derived bytes no longer match the authorized source hashes and segmentation would precede transcript and academic review |
| D — Keep the MP3s as upstream-only evidence | Draft from source text without current governed audio | Preserves current runtime, but cannot satisfy LPL-01's exact governed-media, transcript-provenance and publishable-blueprint exit gate |

## Recommended Option A policy

Approval of Option A means all of the following.

### 1. Separate semantic contract

1. Add the project-owned media kind and usage key `lesson_audio` for ordered,
   long-form teaching recordings attached to a governed lesson-practice
   definition.
2. Keep `model_audio` and its existing Media Library, daily-mission, delivery
   and offline behavior unchanged. Existing objects, publications, attempts
   and generated contracts retain their prior meaning.
3. Keep `lesson_video` unchanged. `lesson_audio` follows the same principle of
   semantic separation accepted in ADR-0013; it is not implemented by
   overloading the model-audio responder.
4. The kind is data-driven. Domain code must not assume Level 1, `can`, four
   parts, Levels 1–17 or fixed part labels.

### 2. Exact bounded media policy

1. `lesson_audio` accepts only inspector-verified `audio/mpeg`, `audio/mp4`,
   `audio/ogg`, `audio/wav` or `audio/webm` with at least one audio stream and
   no video stream.
2. The maximum object size is 20,000,000 bytes; duration must be between 100
   and 900,000 milliseconds inclusive. Any future source outside either bound
   stops for an explicit policy successor rather than silently widening the
   limit.
3. The original accepted bytes and server-computed SHA-256 remain the source
   authority. Ingestion does not normalize, transcode, trim, concatenate or
   split them. A later delivery derivative must be a separately hashed,
   linked and reviewed media variant and cannot replace the source object.
4. One practice definition may reference any bounded ordered set of exact
   `lesson_audio` components. Transcript segments may use validated time
   offsets into an object; they do not create duplicate authority or change
   the source hash.

### 3. Existing governed Media Library lifecycle

1. Extend the current private resumable quarantine, exact-hash finalization,
   project-owned `MediaInspector`, project-owned `MediaMalwareScanner`, human
   quarantine acceptance, cleanup reconciliation, metadata, review, rights,
   replacement and retirement services to `lesson_audio`.
2. Operational ingestion requires a real reviewed ffprobe adapter and current
   ClamAV evidence for the same exact object hash. Missing, unavailable,
   errored or stale inspection/scanner evidence fails closed. Deterministic
   testing fakes cannot produce LPL-01 acceptance evidence.
3. An accepted `lesson_audio` asset requires an append-only English transcript
   revision and the current English/Kannada metadata and review evidence
   required by ADMIN-MEDIA-01 before publication. Provider transcription is a
   draft source for that revision, never review evidence.
4. APP-0047 may be recorded as exact owner authorization for the selected four
   hashes only. The normal program-scoped rights command must still record the
   allowed processing, web/Android channels, India territory, sharing=false,
   derivative drafting=true and production/public prohibition under an active
   appointment. Upload or acceptance alone creates no rights authority.

### 4. Private learner and operator delivery

1. Operator quarantine/accepted preview treats `lesson_audio` as audio and
   never exposes a storage key or public object URL.
2. LPL-01 adds no learner delivery route. LPL-02 may add a dedicated
   authenticated lesson-practice audio route only after the exact asset,
   metadata, reviews, rights, attachment and immutable publication component
   are selected.
3. Learner listing and streaming derive solely from the learner's active
   membership, enrollment and exact publication pin. Guessed, stale,
   suspended, retired, rights-invalid or cross-tenant identifiers fail closed.
4. The later responder must re-verify exact hash/size before streaming, hide
   object keys, use private `no-store` headers and support bounded single byte
   ranges so web and Android audio controls can seek safely. It must not reuse
   a generic asset-content route.
5. Offline lesson-audio packaging remains LPL-06 work. It requires its own
   exact bundle-size, device-storage, entitlement, expiry and purge evidence;
   this decision does not silently make long-form media downloadable.

### 5. Content, AI and publication boundary

1. Transcription and AI-assisted blueprint drafting use project-owned adapter
   interfaces only and bind the four exact source hashes, adapter/provider,
   model, prompt/policy, output hash, purpose, retention/no-training evidence,
   configured spend ceiling and kill-switch state.
2. Raw audio and transcripts remain absent from ordinary logs, audit, outbox,
   analytics and repository commits. No learner or Assignment media enters
   this workflow.
3. AI output remains a draft. It cannot approve media, rights, transcript,
   English/Kannada content, answers, rationales, publication, scores, mastery,
   review schedules or unlocks.
4. Publication requires immutable exact media, transcript, English/Kannada
   review, rights and lesson-practice components. A changed file, transcript,
   ordering, blueprint or policy requires a successor revision/publication;
   prior pins and learner evidence remain reproducible.
5. Sharing, public URLs and production/public release remain prohibited. The
   accepted web/Android India scope is eligibility for later governed learner
   delivery, not present release approval.

### 6. Bounded implementation and compatibility

1. Treat the `lesson_audio` Media Library extension as a necessary LPL-01
   prerequisite slice before exact ingestion. It may update media policy,
   project adapters, operator Media Library behavior, OpenAPI, generated
   clients, localization and tests; it may not implement LPL-02 practice-loop
   learner schema, scoring, conversation, stage or UI behavior.
2. No database migration is expected because current media authority stores
   project-owned kind/usage keys as constrained strings. If implementation
   discovers that a schema or trigger change is required, stop and record the
   exact additive migration scope before applying it.
3. Update ADMIN-MEDIA-01's supported-kind documentation and record an accepted
   ADR-0016 if this option is approved. The ADR refines existing generic media
   rules and does not amend the master plan.
4. Add focused policy, inspection, scanner, upload, metadata, rights,
   lifecycle, private preview, OpenAPI/client, UI, same-tenant/cross-tenant and
   compatibility tests. Run the applicable repository verification and build
   gates; run E2E because the operator upload/preview journey changes.
5. LPL-01 may ingest only the four APP-0047 hashes after this slice passes.
   It remains incomplete until one exact source-supported blueprint has
   transcript reconciliation and attributed human English/Kannada review.

## Required implementation consequence after approval

Approval authorizes one bounded non-production `lesson_audio` Media Library
extension, followed by ingestion of only the four APP-0047 objects through the
approved lifecycle. It also authorizes recording ADR-0016 and updating the
current media specification, OpenAPI/generated clients, all governed UI
catalogues, tests and checkpoint evidence needed for that slice.

It does not authorize LPL-02 learner-practice runtime, all-level legacy media
import, Assignment audio access, public URLs, cross-owner sharing, automatic
AI publication, production/public release or unbounded provider spend.

## Exact approval requested

To accept the recommended policy, reply exactly:

```text
Approve DR-0021 Option A as written.
```

Any changed wording will be treated as a new instruction to evaluate, not as
silent approval.

## Resolution

Option A was accepted exactly as written on 10 August 2026 under APP-0048,
D-0145 and ADR-0016. The exact approved proposal, before its status, resolved
date and this resolution section were added, has SHA-256
`c792ea46d2ace3ed3d517d9518fdf075000228b0b877131504cbbd5cf4ee472a`.

LPL-01 may now implement one bounded non-production `lesson_audio` Media
Library prerequisite and then ingest only the four APP-0047 hashes through the
approved lifecycle. LPL-02 learner-practice runtime, all-level import,
Assignment audio, public URLs, sharing, production/public release and
unbounded provider use remain unauthorized.

---
status: accepted
date: 2026-08-08
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner (implementation constraint for local/dev commissioning)
consulted:
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: false
approval_evidence: []
approval_reference: Legacy level-video learner-path vertical slice
---

# ADR-0013: Lesson-video learner delivery

## Status

Accepted as an implementation constraint for the bounded legacy level-video
learner-path slice. Independent specialist review remains required before
production curriculum approval or Play release.

## Context

Daily speaking delivery uses `usage_key=model_audio` with an audio mime
allowlist and a 10 MB ceiling. Legacy Global Spoken English level videos are
MP4 objects larger than that ceiling and are pedagogically distinct from model
audio.

The master plan forbids hard-coding Levels 1–17 into domain logic. Owner-
asserted rights exist for local commissioning of inventoried feedstock under
`artifacts/legacy-gse-content/` (gitignored); that feedstock is not itself an
approved academic publication.

## Decision

1. Introduce a separate media usage key: **`lesson_video`**.
2. Deliver lesson video through a dedicated authenticated stream route, not
   `/media-assets/{id}/content` (model audio).
3. Bound lesson video with its own config: `video/mp4` mime allowlist and a
   20 MB maximum, verified SHA-256 + byte size before streaming, private
   no-store headers, storage key never exposed.
4. Authorize listing and streaming only through the learner's exact enrollment
   publication pin and current media-rights / suspension predicates (ADR-0004).
5. Catalog entries are data-driven from the pinned publication graph (lesson /
   content payload labels). Domain code must not assume a fixed count of 17
   levels.
6. Local import from gitignored inventory records an initial **pending**
   owner-asserted rights revision for Global English Direct commissioning. The
   importer is draft-only: it creates no authority appointment, review,
   approved-rights revision, publication, enrollment or pin. It does not
   rewrite the live legacy site and does not claim Phase 1 academic, Kannada or
   media-rights approval.

## Alternatives considered

### Overload `model_audio`

Rejected: wrong mime family, wrong size bound, and conflates speaking-model
audio with level introduction video.

### Demo-only / Vite public static files

Rejected for this slice: the product owner selected the real learner path.

### Hard-coded Level 1–17 modules in domain services

Rejected by master plan §6.1.

## Consequences

### Positive

- Production-shaped private delivery consistent with model audio.
- Clear separation of audio vs video contracts in OpenAPI and config.
- Missing source videos simply omit catalog rows.

### Negative / follow-up

- Offline bundles do not include lesson video in this slice.
- Full academic/Kannada human approval of commissioned content remains a later
  gate.
- Android uses WebView `<video>` playback only; no native MediaPlayer work.

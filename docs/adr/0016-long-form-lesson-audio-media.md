---
status: accepted
date: 2026-08-10
deciders:
  - Codex task accountable product owner as Accountable Product Owner and Technical Decision Owner
  - Codex task accountable product owner as Security Reviewer, Privacy Reviewer and Tenancy Reviewer
consulted:
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: false
approval_evidence:
  - APP-0048
approval_reference: Exact human instruction in the active Codex conversation
---

# ADR-0016: Separate long-form lesson audio from short model audio

## Status

Accepted on 10 August 2026 under APP-0048 / D-0145. This ADR resolves DR-0021
by accepting Option A exactly as written. The approved pre-resolution DR has
SHA-256
`c792ea46d2ace3ed3d517d9518fdf075000228b0b877131504cbbd5cf4ee472a`.

## Context

The governed Media Library currently distinguishes short daily-mission
`model_audio` from long-form `lesson_video`. Two of the four exact Level 1
teaching MP3s authorized by APP-0047 exceed the five-minute `model_audio`
inspection ceiling, and all four are ordered teaching components rather than
short model lines.

Silently raising `model_audio` limits would weaken a stable delivery contract.
Splitting or re-encoding the recordings would create new bytes before
transcript and academic review and would no longer preserve the exact
authorized source hashes.

## Decision

Add a distinct governed media kind and usage key, `lesson_audio`, for bounded
long-form teaching recordings:

- accepted MIME types are `audio/mpeg`, `audio/mp4`, `audio/ogg`, `audio/wav`
  and `audio/webm`, verified as audio-only by the project inspector;
- source objects are bounded to 20,000,000 bytes and 100–900,000 milliseconds;
- accepted source bytes and server-computed SHA-256 remain immutable and are
  not normalized, split or transcoded during ingestion;
- the existing quarantine, inspection, malware scan, human acceptance,
  metadata, review, rights, replacement, retirement and cleanup lifecycle is
  extended to the new kind;
- `model_audio` and `lesson_video` contracts remain unchanged;
- LPL-01 may implement only the required Media Library/API/client/UI/test
  prerequisite and exact selected-asset ingestion; and
- learner delivery remains future LPL-02 work through a dedicated private,
  exact-publication-pinned responder. Offline packaging remains LPL-06 work.

DR-0021's detailed media, delivery, rights, AI, privacy, compatibility and
verification rules are normative.

## Alternatives considered

### Raise `model_audio` limits

Rejected because it conflates short model lines with long-form lesson media
and makes attachment, preview, delivery and offline policy ambiguous.

### Split or re-encode the source

Rejected because derived bytes would not match APP-0047's exact hashes and
would make unreviewed segmentation part of the content authority.

### Keep the MP3s upstream-only

Rejected because LPL-01 cannot establish governed transcript provenance or a
publishable blueprint without exact current media objects.

## Consequences

### Positive

- Existing daily-mission media behavior remains reproducible.
- Long-form teaching audio receives explicit limits and exact provenance.
- New lessons can add ordered audio components as governed data without
  level/topic conditionals.
- Later web/Android streaming and offline policy can evolve independently of
  short model audio.

### Trade-offs

- The Media Library API, generated clients, operator UI, localization and
  inspection tests gain a third media kind.
- Learner streaming is intentionally deferred; accepted media is not yet
  learner-deliverable merely because it exists.
- Any future source over 20 MB or 15 minutes requires a policy successor.

## Security, privacy and tenancy impact

Operational ingestion requires real ffprobe and ClamAV evidence over the same
exact object hash and fails closed if either adapter is unavailable or stale.
Objects remain private; previews and future delivery hide storage keys and
public URLs. Organization ownership, permissions, appointments, exact rights,
same/cross-tenant checks and immutable review/publication evidence remain
mandatory.

Raw audio/transcripts stay out of ordinary logs, audit, outbox, analytics and
repository commits. APP-0047 covers only the four selected teaching hashes;
Assignment or learner-originated media remains excluded.

## Authoritative records and consistency impact

Current media tables already store project-owned kind and usage keys as
bounded strings, so no schema migration is expected. Existing media rows and
publications retain their meaning. If implementation discovers a database
constraint requiring alteration, work stops for an exact additive migration
record rather than silently changing schema.

Accepted source objects, metadata revisions, rights decisions, attachments
and publication components remain immutable or append-only. Replacements and
delivery derivatives require separately hashed linked evidence.

## API, client and operational impact

The Media Library manifest, filters, uploads, previews, metadata validation,
rights/attachment guards, OpenAPI enums, generated clients, UI catalogues and
tests add `lesson_audio`. The operator preview uses an audio element. LPL-01
does not add a learner route or practice-loop schema.

The implementation must run focused media policy/inspection/scanner,
same/cross-tenant, API/client/UI and compatibility checks, plus applicable
repository verify, E2E and build gates.

## Master-plan amendment

This ADR does not amend the master plan. It applies the existing exact-media,
private-delivery, provider-neutral, deterministic-authority and immutable
publication rules to a newly evidenced media category.

## Approval evidence

- Evidence ID: `APP-0048`
- Decision date: 2026-08-10, Asia/Kolkata
- Human approver identifier: `Codex task accountable product owner`
- Roles exercised: Accountable Product Owner; Technical Decision Owner;
  Security Reviewer; Privacy Reviewer; Tenancy Reviewer
- Exact human statement: `Approve DR-0021 Option A as written.`
- Approved DR SHA-256:
  `c792ea46d2ace3ed3d517d9518fdf075000228b0b877131504cbbd5cf4ee472a`
- Master-plan impact: none

Codex is the author and evidence recorder only, never the approver. One human
exercised consolidated development roles; this is not independent specialist,
academic/Kannada, legal or production review.

## Follow-up

1. Implement the bounded `lesson_audio` Media Library prerequisite.
2. Ingest only the four APP-0047 hashes with real inspection/scanner evidence.
3. Continue LPL-01 transcript, blueprint and English/Kannada review work.
4. Do not begin LPL-02 until LPL-01's checkpoint exit passes.

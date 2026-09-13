# LPL-02 typed lesson-practice attachment specification

## Scope

Implement the contract in ADR-0024 for private, unpublished lesson-practice
drafts. No learner delivery, scoring, progression, or public publication is in
scope for the first slice.

## Authoritative records

`lesson_practice_blueprints` stores organization, program, curriculum version,
concept key, immutable revision number, state (`draft`, `published`,
`retired`), and canonical JSON. Canonical JSON must contain objective,
communication function, target patterns, misconception codes, ordered parts,
Talk, Check and Apply. `lesson_practice_reviews` stores immutable English and
Kannada decisions over the exact blueprint hash. `lesson_practice_media`
stores one exact attachment per ordered part and its asset, metadata and rights
heads. `lesson_practice_attachment_events` is append-only.

## Deterministic rules

- Exactly four parts: Introduction, Practice, Conversation, Interaction.
- Part order is unique and contiguous from 1 to 4.
- Check items number 3–7 and weights total 100.
- Correct-option indexes are in range; Apply evidence starts `not_recorded`.
- No executable markup, raw learner data, Assignment audio or provider SDK type.
- Blueprint hash is canonical JSON SHA-256 and never changes after review.
- Attachment requires both approved reviews over the same blueprint hash.

## API

Add organization-scoped operator routes:

- `POST /api/v1/operator/lesson-practice/blueprints`
- `POST /api/v1/operator/lesson-practice/blueprints/{blueprint}/reviews`
- `POST /api/v1/operator/lesson-practice/blueprints/{blueprint}/attachments`
- `GET /api/v1/operator/lesson-practice/blueprints/{blueprint}`

All writes require idempotency keys, selected organization context, exact
expected revision/hash heads, and return private no-store JSON. Cross-tenant or
guessed identifiers resolve as not found.

## Verification

Add SQLite/PostgreSQL migration tests, same/cross-tenant API tests, duplicate
and stale-head conflict tests, review/hash mismatch tests, four-part ordering
tests, and attachment idempotency tests. Update OpenAPI, generated clients,
status, decisions and quality review.

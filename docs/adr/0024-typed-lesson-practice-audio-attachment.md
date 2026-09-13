# ADR-0024: Typed lesson-practice blueprint and lesson-audio attachment

- Status: Accepted
- Date: 2026-08-14
- Amends: `ADMIN-MEDIA-01_SPEC.md` and `LPL-01_LESSON_AUDIO_SCHEMA_SCOPE.md`

## Decision

Introduce an immutable, organization-scoped `lesson_practice_blueprint` source
revision as the attachment target for governed `lesson_audio` assets. A
blueprint contains typed objective, target patterns, Talk, deterministic Check,
Apply rubric, and English/Kannada review references. Four ordered audio parts
may attach only to the same blueprint revision, each with the exact current
asset, metadata revision/content hash, and approved rights revision.

Attachment is transactional, idempotent and append-only. It requires the active
organization context, `curriculum.author` and `media.metadata.manage`, current
English and Kannada approvals, active rights, clean accepted assets, exact
metadata heads, and an unpublished draft blueprint. Publication remains a
separate command and creates an immutable successor snapshot.

## Rejected alternatives

- Reusing `model_audio` attachment: wrong learner semantics and duration policy.
- Copying legacy lesson rows: loses typed provenance and review lineage.
- Mutating a published blueprint: violates immutable publication history.

## Consequences

The LPL-01 four-part media remains un-attached until this contract is
implemented. The API and schema must add explicit lesson-practice records,
composite tenant constraints, attachment events and deterministic validation.

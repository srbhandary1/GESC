# LPL-01 lesson-audio additive schema scope

**Status:** Locked implementation scope under DR-0021 Option A / APP-0048 /
D-0145 / ADR-0016

**Recorded:** 10 August 2026, before applying the successor migration

## Discovery

DR-0021 expected no schema migration because ADMIN-MEDIA stores media kind and
usage keys in bounded string columns. Implementation inspection found seven
PostgreSQL checks in
`2026_08_09_000150_create_admin_media_library.php` that explicitly enumerate
only `model_audio` and `lesson_video`. The accepted decision requires a stop
and exact additive scope record before changing those constraints.

SQLite has no corresponding database checks in that migration and continues
to rely on the same application validation used by existing ADMIN-MEDIA tests.

## Exact additive migration

Create one successor migration. It may replace only these PostgreSQL checks:

| Constraint | Additive change |
|---|---|
| `media_uploads_values_check` | Allow `lesson_audio` as `asset_kind` |
| `media_security_scans_values_check` | Allow `lesson_audio` as `asset_kind` |
| `media_asset_lifecycles_values_check` | Allow `lesson_audio` as `asset_kind` |
| `media_metadata_revisions_values_check` | Allow `lesson_audio`; require a null text alternative for both audio kinds |
| `media_metadata_variants_values_check` | Allow `lesson_audio`; require a null text alternative for both audio kinds |
| `media_attachment_events_values_check` | Allow the reserved `lesson_audio` usage key while application attachment remains closed until LPL-02 |
| `publication_media_governance_values_check` | Allow the reserved `lesson_audio` usage key while publication construction remains closed until LPL-02 |

The migration must preserve every other predicate byte-for-byte in meaning.
It adds no table, column, key, index, trigger, data row, default, permission or
learner route. Existing values are a strict subset of the successor checks.

The down migration restores the seven original two-kind checks. It must fail
closed if later `lesson_audio` rows exist rather than deleting or rewriting
them.

## Application boundary

- Upload, inspection, scan, acceptance, metadata, review, rights, replacement,
  retirement and private operator preview may use `lesson_audio`.
- Current Content Studio attachment request validation and source-schema
  matching remain limited to `model_audio` and `lesson_video` until LPL-02
  supplies the exact lesson-practice source schema.
- No publication builder or learner delivery path may select `lesson_audio` in
  LPL-01.
- All accepted bytes remain exact and private; this migration creates no
  content, rights or publication authority.

## Verification required

1. SQLite application tests for upload/preview/metadata and attachment
   rejection.
2. PostgreSQL migration upgrade, rollback on an empty successor state,
   re-apply and schema protection.
3. PostgreSQL insertion checks proving `lesson_audio` is accepted only in the
   widened fields and existing invalid kinds/text-alternative shapes remain
   rejected.
4. Existing `model_audio` and `lesson_video` compatibility tests.

Any requirement to change another table, constraint or authoritative record
must stop for a new exact scope record.

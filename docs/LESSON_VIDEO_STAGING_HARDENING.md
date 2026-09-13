# Lesson-video staging hardening (ADR-0013)

**Status:** Staging/ops hardening package only.
**Does not authorize:** production learner traffic, F0 DoD Exact accept, Google
Play public listing, or independent academic/Kannada content approval.

Authority: [ADR-0013](adr/0013-lesson-video-learner-delivery.md), D-0118 / D-0119.

## Non-claims

| Claim | Status |
|---|---|
| Production-shaped private stream + pin gate | Implemented and testable with governed fixtures |
| Idempotent staging import onto private object storage | Draft-only; implemented |
| Academic / language content approval | **Not claimed** |
| Media-rights approval | **Not claimed by import; recorded pending** |
| Offline lesson-video bundles | Out of scope |
| F0 / Play / public production release | **Unauthorized** |

## Object-storage configuration

| Variable | Staging value | Notes |
|---|---|---|
| `LEARNING_LESSON_VIDEO_DISK` | `lesson_videos` | Dedicated private S3 disk |
| `AWS_LESSON_VIDEO_PREFIX` | `lesson-videos` | Object key prefix |
| `AWS_*` / `FILESYSTEM_DISK` | Environment-specific | Same bucket credentials as local SeaweedFS or real S3 |

Learners never receive storage keys or public object URLs. Delivery remains
`GET /api/v1/media-assets/{id}/lesson-video` with SHA-256 + byte verification
and `Cache-Control: private, no-store`.

Bounds (`apps/api/config/learning.php`):

- mime allowlist: `video/mp4`
- maximum bytes: `20_000_000`

## Draft-import checklist

Complete every row. Do not import if any row fails.

| # | Check | Owner |
|---|---|---|
| 1 | Product owner asserts rights to commission inventoried legacy MP4s for Global English Direct | APO |
| 2 | Feedstock inventory path is local/gitignored (`artifacts/legacy-gse-content/`); no credentials in repo | Operator |
| 3 | Each imported file SHA-256 matches `inventory.json` | Operator |
| 4 | Import records an initial `pending` rights revision with `rights_basis_key=owner_asserted_legacy_import`; this is provenance/request metadata, not approval | System |
| 5 | Requested scope records channels `android`+`web`, territory `IN`, and intended learner delivery; `pending` status remains non-publishable | System |
| 6 | Import creates no users, memberships, authority appointments, reviews, publications, cohorts, enrollments or pins | System |
| 7 | Staging import outcome records draft version id, organization id, pending-rights ids and missing levels | Operator |
| 8 | Independent graph, academic, Kannada and rights review remains **pending** until distinct appointed humans act | APO |

## Source gaps (data-driven; not hard-coded)

From the legacy admin inventory (as of local feedstock capture):

| Level label | Status |
|---|---|
| Level 1, 2, 3, 6, 7, 8, 9, 10, 11, 13, 14, 17 | MP4 present in feedstock |
| Level 4, 5, 12, 15, 16 | **No uploaded MP4** on legacy edit pages |

Staging catalogs must list only commissioned rows. Do not fabricate placeholders
for missing levels. Domain code must not assume a fixed count of 17.

## Staging import runbook

Preconditions:

1. Staging API with Sanctum/Passport and org context working.
2. `LEARNING_LESSON_VIDEO_DISK=lesson_videos` and reachable private object store.
3. Feedstock present on the operator host (not committed):
   `artifacts/legacy-gse-content/inventory.json` + `videos/level-NN/*.mp4`.
4. Operator membership has `curriculum.author` and
   `curriculum.translate.kn`. Import does not require or exercise publication
   authority.
5. Choose a stable, non-secret idempotency key and reuse it only for exact
   retries of this inventory.
6. Draft-import checklist above signed off for this environment.

Commands (from `apps/api`, Exact staging commit):

```bash
php artisan learning:import-legacy-level-videos \
  --organization=<GE_DIRECT_ORG_ULID> \
  --actor-membership=<OPERATOR_MEMBERSHIP_ULID> \
  --idempotency-key=<STABLE_IMPORT_RETRY_KEY>
```

Optional inventory override:

```bash
  --inventory=/absolute/path/to/inventory.json
```

Record the command JSON summary (draft version id, program id, pending-rights
ids and per-level asset ids). A successful import must report
`workflow_state=draft_pending_independent_review`, `publication_id=null` and
`enrollment_id=null`. Do **not** paste credentials into tickets.

If feedstock is missing, the command must fail closed — do not pull from the
live legacy admin as part of staging deploy.

## Governance handoff before learner smoke

Draft import is not learner delivery. Before any publication or enrollment
pin, the ordinary ADR-0004 commands must record all of the following from real,
active, organization-scoped memberships:

1. an authority manager appoints distinct qualified English-academic,
   Kannada-language and rights reviewers to the imported program;
2. each appointee accepts their own appointment;
3. the English reviewer approves the exact graph head and every exact English
   source revision, without reviewing their own authorship;
4. the Kannada reviewer approves every exact Kannada variant, without
   reviewing their own translation;
5. the rights confirmer appends an approved successor to every initial
   pending-rights revision after checking the actual evidence and requested
   release scope; and
6. only then may a separately authorized publisher publish and an enrollment
   operator create/pin a learner enrollment.

The importer cannot perform, infer or bypass any of these steps. The repository
does not yet expose a complete staging CLI/HTTP workflow for this handoff, so a
staging learner smoke remains blocked until a reviewed operator workflow or
explicitly recorded domain-command procedure is supplied.

## Deploy smoke criteria (staging)

Run only after the governance handoff, publication and separate enrollment pin
against the staging API as the enrolled learner:

| # | Check | Pass criteria |
|---|---|---|
| S1 | `GET /api/v1/me/level-videos?enrollment_id=` | 200; `videos.length` equals commissioned count; no fabricated missing levels |
| S2 | Stream Level 1 (or first catalog row) | 200; `Content-Type: video/mp4`; `X-Content-SHA256` matches inventory; `Cache-Control` includes `private` and `no-store` |
| S3 | Wrong enrollment / other tenant | 404 |
| S4 | LearnerJourney UI | Level videos panel lists catalog; player loads stream under session auth |
| S5 | Android WebView (if pilot APK) | Same stream plays; no storage key in client logs |
| S6 | Object store | Objects under `lesson-videos/` (or configured prefix); bucket not world-readable |

Failure of S1–S3 blocks staging pilot use of lesson video. S4–S6 are required
before broadening pilot exposure.

## Quarantined local smoke artifacts (2026-08-08)

The earlier gitignored `staging-import-result.json` and S1–S4 smoke artifacts
were produced by the superseded importer, which manufactured synthetic
memberships, appointments, approvals and rights confirmation. They are
quarantined and must not be cited as staging, academic, language, rights or
publication evidence.

Automated integration coverage may still use clearly test-only, distinct
actors to prove that the legitimate draft → review → rights → publication →
pin path works and remains tenant-isolated. Those test identities and decisions
are fixtures only; they are not human production approval.

## Explicit follow-ups (not this package)

- Independent content/language approval APP for commissioned videos
- Reviewed operator workflow for authority, review, rights, publication and pin handoff
- Filling missing Levels 4 / 5 / 12 / 15 / 16 at source
- Offline video policy
- F0 Exact accept and Play public listing

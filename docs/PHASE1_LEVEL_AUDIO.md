# Phase 1 per-level Audio Lessons contract

Soft-launch Audio Lessons (My Levels → a level → Audio Lessons) must
**listen first**, then offer **Train with Swathi**. Daily Activity remains
a separate one-audio/day clip plus a 5-mark quiz.

The loader prefers a real file. Scripted TTS is an explicit stand-in only
when no authored bytes are available. The UI must not play TTS as if it
were the authored lesson.

Resolution order (Levels 1–17):

1. Signed-in sessions index
   `GET /api/v1/me/levels/{n}/sessions` (`credentials: include`, plus
   Bearer when the Android PKCE adapter has an access token). Prefer the
   allowlisted `audio_stream_path` for `introduction`.
2. Authorized `GET` of that path (or the hardcoded introduction path
   when the index has no allowlisted stream) → blob URL for `<audio src>`.
3. Same-origin drop-in `apps/web/public/demo/phase1/audio/level-N.{ext}`
   (or `manifest.json` `file` entry) if the private stream is 401/missing.
4. Otherwise **missing** (404 / no file) or **unauthenticated** (401/403)
   with a sign-in CTA. TTS is labelled stand-in only.

Pre-1 and Pre-2 have no numeric `/me/levels` slot. They use the drop-in
folder only, then the honest missing state.

## Why Real-Studioz looked “blocked”

`http://117.254.108.143:8080` is up. The SPA returns 200. Private media
does not. Live probe on 13 September 2026:

| Request | Result |
|---|---|
| `GET /` | 200 HTML (Caddy + PHP 8.4, bundle `/assets/index-grp1Ga5j.js`) |
| `GET /sanctum/csrf-cookie` | 204 + `XSRF-TOKEN` / `global_english_session` |
| `GET /api/v1/me/levels` | **401** `application/problem+json` |
| `GET /api/v1/me/levels/1/sessions/introduction/audio` | **401** |
| `GET /api/v1/media-assets/{id}/content?enrollment_id=&mission_id=` | **401** |

Earlier Cursor work on that host used the **synthetic client demo**
(`make demo-client` / D-0092 `/demo/tts`), not anonymous private blobs.
The host advertisement is in `scripts/run-client-static-ip-demo.sh`
(`GE_CLIENT_DEMO_PUBLIC_HOST` default `117.254.108.143`) and
[CLIENT_DEMO_RUNBOOK.md](CLIENT_DEMO_RUNBOOK.md). That path is
presentation-only: in-memory fixtures, optional Azure TTS, **no learner
accounts and no ordinary API media**.

There is **no** studio service token, demo password, or unsigned media
bypass in this repository. `AUTH_OTP_LOCAL_FIXED_CODE=000000` is
local-only (`APP_ENV=local`) and is not a studio credential.

What is needed now: a **learner Sanctum cookie session** on that origin
(or Passport for Android), plus published `lesson_audio` objects on the
configured disk. If the disk has no bytes, that is a missing bind-mount /
S3 object / operator upload — not a SPA outage.

Soft-launch web (`password_only`) signs in from the shell Session
panel: `GET /api/v1/auth/password/mode` then CSRF
`POST /api/v1/auth/password/sign-ins`. After a 200, the SPA calls
`GET /api/v1/me` with the same cookies. If that `/me` call is still
401, the host SESSION/redis cookie is not sticking — confirm
`SESSION_DRIVER`, cookie `domain`/`secure`/`same-site` on the Caddy
`:8080` origin, and that `/sanctum/csrf-cookie` and `/api/*` share
that origin. Android PKCE / mobile Bearer already work for
`auth.first-party` and lesson audio; do not treat a web `/me` 401 as
a missing password form.

## Authenticated download routes

### This branch (main lineage / PR-006 daily model audio)

OpenAPI `streamLearnerModelAudio`:

```text
GET /api/v1/media-assets/{asset_id}/content?enrollment_id=&mission_id=
```

Laravel: `apps/api/routes/api.php` → `LearnerMediaController` →
`VerifiedPrivateAudioResponder`. Sanctum session or Passport
`mobile:api`. `Accept: audio/*`. Storage key is never exposed.

This is the **daily mission** clip. It is not a per-level Audio Lessons
substitute (one pin, not Pre-1…17).

### Live studio / elevate stack (not registered on this branch’s Laravel)

```text
GET /api/v1/me/levels
GET /api/v1/me/levels/{1-17}/sessions/{introduction|practice|conversation|interaction}
GET /api/v1/me/levels/{n}/sessions/{part}/audio
```

OpenAPI and `LearnerLevelSessionController::audio` live on
`origin/cursor/learner-ux-elevate-5f47` (and the Caddy+PHP host). The
Phase 1 client prefers the **sessions index** `audio_stream_path` (Audio
Lessons uses `introduction`) and fetches it with the same credentials
the SPA already uses for `/api/v1/me/*`. The hardcoded introduction
path is the fallback when the index has no allowlisted stream. A 200
HTML SPA fallback (Vite / Caddy) is treated as missing, not as authored
audio. Only `audio/*` bodies are played. Off-origin or non-allowlisted
stream paths are rejected.

`/me/levels/pre-1/...` is not a route. `/me/levels/0/...` is not a
Pre-level alias.

## Where the binaries live (not in git)

No WAV/MP3/M4A/OGG/WebM exists in this tree, git history, LFS, or Android
assets. Seeders (`FoundationSeeder`, `DatabaseSeeder`) do not attach real
lesson audio.

### Daily model audio (this branch)

| Setting | Source |
|---|---|
| Disk name | `LEARNING_MODEL_AUDIO_DISK` else `FILESYSTEM_DISK` (`apps/api/config/learning.php`) |
| `.env.example` | both default to **s3** |
| Local disk root | `apps/api/storage/app/private` (`filesystems.disks.local`) |
| Object key | `media_assets.storage_object_key` on the published mission pin |
| Public disk | `storage/app/public` — **not** the private media path |

Tests use synthetic keys such as `synthetic/*.mp3`.

### Lesson audio (studio / elevate)

| Setting | Source |
|---|---|
| Disk name | `LEARNING_LESSON_AUDIO_DISK` else `LEARNING_MODEL_AUDIO_DISK` else `FILESYSTEM_DISK` (`config/media.php` `accepted_disks.lesson_audio`) |
| Local disk root | same `storage/app/private` when the disk is `local` |
| Object key | `media_assets.storage_object_key` where `asset_kind = lesson_audio` |
| Attachment | `lesson_practice_media` row for the level blueprint + `introduction` part |
| Kind bounds | up to 20 MB; MIME `audio/mpeg`, `audio/mp4`, `audio/ogg`, `audio/wav`, `audio/webm` |

If `FILESYSTEM_DISK=s3`, bytes are in the configured bucket, not a git
path. If `local`, the operator must place or bind-mount the object at
`apps/api/storage/app/private/{storage_object_key}` on the studio box.
The repo does not document a bind-mount; an empty private disk is a
server-filesystem gap, not a client bug.

`APP_URL` in `.env.example` is `http://127.0.0.1:8000`. The static-IP
demo script only **advertises** `http://117.254.108.143:8080`; it does
not write Laravel `APP_URL`. The live studio `APP_URL` is on that host’s
`.env` (not in git).

## Storage inventory (operator, on the studio box)

Do this on Real-Studioz before chasing login UI. The disk name is **not**
stored on `media_assets`; it comes from env. Object keys are **not**
`level-1.mp3`.

### Env vars and disks

Resolution for **lesson_audio** (elevate / live studio
`config/media.php` `accepted_disks.lesson_audio`):

```text
LEARNING_LESSON_AUDIO_DISK
  else LEARNING_MODEL_AUDIO_DISK
  else FILESYSTEM_DISK
  else local
```

Resolution for **daily model_audio** (this branch
`config/learning.php`):

```text
LEARNING_MODEL_AUDIO_DISK
  else FILESYSTEM_DISK
  else local
```

| Env / disk | This branch `.env.example` | Elevate `.env.example` | Meaning |
|---|---|---|---|
| `FILESYSTEM_DISK` | `s3` | `s3` | Laravel default disk name |
| `LEARNING_MODEL_AUDIO_DISK` | `s3` | `s3` | Daily mission model audio |
| `LEARNING_LESSON_AUDIO_DISK` | **absent** (not on this branch) | `s3` | Per-level `lesson_audio` |
| `filesystems.disks.local.root` | `storage/app/private` | same | Used when the resolved name is `local` |
| `filesystems.disks.s3` | `AWS_*` | `AWS_*` | Used when the resolved name is `s3` |
| `MEDIA_QUARANTINE_DISK` | absent | `media_quarantine` | Upload staging only |
| `LEARNING_LESSON_VIDEO_DISK` | absent | `lesson_videos` | **Not** lesson audio |

S3 disk fields (`apps/api/config/filesystems.php`):

| Env | Example only (not studio) |
|---|---|
| `AWS_BUCKET` | `global-english-local` |
| `AWS_ENDPOINT` | `http://127.0.0.1:8333` (compose SeaweedFS) |
| `AWS_DEFAULT_REGION` | `us-east-1` |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `true` |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | local placeholders — **do not commit studio values** |

Compose binds SeaweedFS to `127.0.0.1:${S3_PORT:-8333}` with volume
`object-storage-data` → container `/data`. **No deploy doc, Makefile
target, or `.env.example` names a Real-Studioz bucket, endpoint, or
bind-mount.** Live `APP_URL` / `AWS_*` live only in that host’s `.env`.

Print on the studio API root (redact secrets):

```bash
cd /path/to/apps/api   # studio checkout
grep -E '^(APP_URL|FILESYSTEM_DISK|LEARNING_LESSON_AUDIO_DISK|LEARNING_MODEL_AUDIO_DISK|AWS_BUCKET|AWS_ENDPOINT|AWS_DEFAULT_REGION|AWS_USE_PATH_STYLE_ENDPOINT)=' .env \
  | sed -E 's/(AWS_SECRET_ACCESS_KEY|AWS_ACCESS_KEY_ID)=.*/\1=[redacted]/'
```

### Object key pattern

Accepted `lesson_audio` rows from the operator upload/promote path
(`MediaUploadCommands` on elevate):

| Field | Pattern |
|---|---|
| `media_assets.asset_kind` | `lesson_audio` |
| `media_assets.key` | `managed-{assetUlid}` |
| `media_assets.storage_object_key` | `accepted/{assetUlid}/{sha256Hex}` |
| `media_assets.asset_hash` | 64-char SHA-256 of the bytes |
| `media_assets.id` | same ULID as `{assetUlid}` in the key |

There is **no** `level-N` segment in the object key. Level mapping is
`level_sessions.level_concepts` (1=`can` … 17=`used_to`) →
`lesson_practice_blueprints.concept_key` → `lesson_practice_media`
(`part_id` = `introduction` for Audio Lessons). A level is streamable
only when **four** parts are attached (`introduction`, `practice`,
`conversation`, `interaction`).

Upload intermediates (not streamed to learners):

```text
parts/{uploadUlid}/{partNumber}-{partHash}
objects/{uploadUlid}/{wholeObjectHash}
```

Quarantine root when `MEDIA_QUARANTINE_DRIVER=local`:
`storage/app/media-quarantine`.

On disk `local`, the accepted file is:

```text
apps/api/storage/app/private/accepted/{assetUlid}/{sha256Hex}
```

On disk `s3`, the same key is at bucket root (no `lesson-audio/` prefix;
that prefix exists only for `LEARNING_LESSON_VIDEO_DISK` /
`AWS_LESSON_VIDEO_PREFIX`).

APP-0062 ingest stages bytes at `/tmp/legacy-audio-bytes/{local_name}`
then promotes them to `accepted/…`. That staging path is not a runtime
bind-mount.

This branch’s tests use synthetic keys such as `synthetic/*.mp3` for
daily `model_audio` / `instruction_audio`, not `lesson_audio`.

### Artisan / make (no unsigned list)

This branch: `inspire`, `voice:purge-expired`, `speech:benchmark`,
`ai:evaluate`. **No** media inventory command. Makefile has `migrate`,
`seed`, `verify` — none list lesson audio.

Elevate / studio extras (ingest and cleanup, **not** a coverage table):

```text
php artisan media:reconcile --limit=100
php artisan media:ingest-app0062-lesson-audio   # APP-0062; needs operator password
php artisan lesson-practice:commission-app0063-levels
php artisan lesson-practice:publish-direct-pathway --approval=WAVE-7.1
```

Authenticated operator list (401 without a session):
`GET /api/v1/operator/media/manifest` and
`GET /api/v1/operator/media/assets`.

Live probe 13 Sep 2026 without credentials: API
`/api/v1/health/ready` → `ready`; operator media routes → **401**.
Cannot see keys or buckets from outside.

### SQL (studio / elevate schema)

Disk is config, so select it as a literal after you read `.env`.

```sql
WITH levels(level_number, concept_key) AS (
  VALUES
    (1, 'can'), (2, 'should'), (3, 'do_does'), (4, 'is_are_ing'),
    (5, 'did'), (6, 'was_were_ing'), (7, 'have_has'),
    (8, 'have_been_has_been'), (9, 'had'), (10, 'will'),
    (11, 'gonna_wanna'), (12, 'would'), (13, 'may'), (14, 'if'),
    (15, 'must'), (16, 'should_have'), (17, 'used_to')
)
SELECT
  l.level_number,
  l.concept_key,
  b.id AS blueprint_id,
  b.state AS blueprint_state,
  m.part_id,
  a.id AS asset_id,
  a.storage_object_key,
  a.mime_type,
  a.byte_size
FROM levels l
LEFT JOIN lesson_practice_blueprints b
  ON b.concept_key = l.concept_key
LEFT JOIN lesson_practice_media m
  ON m.blueprint_id = b.id
 AND m.part_id = 'introduction'
LEFT JOIN media_assets a
  ON a.id = m.media_asset_id
 AND a.asset_kind = 'lesson_audio'
ORDER BY l.level_number;
```

There is no `disk` column — print the resolved disk name from env next
to the result. If several organizations exist, add
`AND b.organization_id = '…'`.

This-branch fallback (no `lesson_practice_*` tables):

```sql
SELECT id, key, asset_kind, storage_object_key, mime_type, byte_size
FROM media_assets
WHERE asset_kind IN ('lesson_audio', 'instruction_audio', 'audio')
ORDER BY asset_kind, created_at;
```

### Eloquent / tinker one-liner (studio)

Run from `apps/api` on the studio checkout (elevate stack):

```php
php artisan tinker --execute='
$disk = config("media.accepted_disks.lesson_audio");
$concepts = config("level_sessions.level_concepts");
foreach (range(1, 17) as $n) {
    $concept = $concepts[$n] ?? null;
    $bp = is_string($concept)
        ? \App\Models\LessonPracticeBlueprint::query()
            ->where("concept_key", $concept)
            ->whereIn("state", ["draft", "published"])
            ->orderByDesc("revision_number")
            ->first()
        : null;
    $media = $bp
        ? \App\Models\LessonPracticeMedia::query()
            ->where("blueprint_id", $bp->getKey())
            ->where("part_id", "introduction")
            ->first()
        : null;
    $asset = $media
        ? \Illuminate\Support\Facades\DB::table("media_assets")
            ->where("id", $media->media_asset_id)
            ->where("asset_kind", "lesson_audio")
            ->first()
        : null;
    $exists = $asset
        ? \Illuminate\Support\Facades\Storage::disk($disk)
            ->exists($asset->storage_object_key)
        : false;
    dump([
        "level" => $n,
        "concept" => $concept,
        "asset_id" => $asset->id ?? null,
        "storage_object_key" => $asset->storage_object_key ?? null,
        "disk" => $disk,
        "bytes_on_disk" => $exists,
    ]);
}
'
```

### Filesystem / S3 checklist (on studio)

After the query, for each non-null `storage_object_key`:

```bash
# If resolved disk is local:
ls -l storage/app/private/accepted

# If resolved disk is s3 (endpoint/bucket from .env; do not paste secrets):
aws s3 ls "s3://${AWS_BUCKET}/accepted/" --endpoint-url "${AWS_ENDPOINT}"
```

A row with `asset_id` but `bytes_on_disk=false` is a missing bind-mount
or missing S3 object. A row with no `asset_id` is commissioning /
attachment, not a down SPA. Pre-1 / Pre-2 never appear in this table.

## Drop path (unsigned local / Pre-levels)

```text
apps/web/public/demo/phase1/audio/
```

Served at `{BASE_URL}demo/phase1/audio/` (`/demo/phase1/audio/` in
`make demo`).

## Naming

`{levelId}.{mp3|wav|m4a|ogg|webm}`

| Level | `levelId` | Expected file (mp3 shown) | Private stream |
|---|---|---|---|
| Pre-1 | `pre-1` | `apps/web/public/demo/phase1/audio/pre-1.mp3` | none |
| Pre-2 | `pre-2` | `apps/web/public/demo/phase1/audio/pre-2.mp3` | none |
| Level 1 | `level-1` | `…/level-1.mp3` | `/api/v1/me/levels/1/sessions/introduction/audio` |
| Level 2 | `level-2` | `…/level-2.mp3` | `/api/v1/me/levels/2/sessions/introduction/audio` |
| … | … | … | … |
| Level 17 | `level-17` | `…/level-17.mp3` | `/api/v1/me/levels/17/sessions/introduction/audio` |

Accepted MIME types: `audio/mpeg`, `audio/wav`, `audio/mp4`, `audio/ogg`,
`audio/webm`.

Optional: set `"file"` (and `"mimeType"`) for a level in
`apps/web/public/demo/phase1/audio/manifest.json` if the filename is not
the conventional `{levelId}.{ext}`.

Binary audio in this folder is gitignored so unlicensed media is not
committed by accident. After rights review, an operator may force-add a
licensed file.

Optional `VITE_API_BASE_URL` (no trailing path beyond `/api/v1`) points
the private GET at another origin. Cross-origin cookies will not be sent
under `SameSite=lax`. Prefer serving this SPA on the studio origin after
login.

## Inventory (this checkout)

Searched the monorepo, git history, every origin branch, Git LFS,
Android trees, and file magic (RIFF/ID3/Ogg/ftyp/webm). **No authored
audio files are present in git.** Live studio file presence for Levels
1–17 is **unknown until a learner session** (401 without auth). Pre-1 /
Pre-2 remain drop-in only.

| Level | Git file | Private route |
|---|---|---|
| Pre-1 | MISSING | no slot |
| Pre-2 | MISSING | no slot |
| Levels 1–17 | MISSING | exists on studio; bytes unknown until login |

Client scope records that the 20-lesson content package (audio master +
transcript) is supplied separately
(`deliverables/source/global_english_client_scope.md` §4).

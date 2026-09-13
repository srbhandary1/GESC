# Phase 1 per-level Audio Lessons contract

Soft-launch Audio Lessons (My Levels → a level → Audio Lessons) must
**listen first**, then offer **Train with Swathi**. Daily Activity remains
a separate one-audio/day clip plus a 5-mark quiz.

The loader prefers a real file. Scripted TTS is an explicit stand-in only
when no authored bytes are available. The UI must not play TTS as if it
were the authored lesson.

Resolution order:

1. Drop-in file under `apps/web/public/demo/phase1/audio/` (or
   `manifest.json` `file` entry).
2. Same-origin authenticated introduction stream for Levels 1–17:
   `GET /api/v1/me/levels/{n}/sessions/introduction/audio`
   (`credentials: include`, `Accept: audio/*`).
3. Otherwise **missing** (404 / no file) or **unauthenticated** (401/403).

Pre-1 and Pre-2 have no numeric `/me/levels` slot. They use the drop-in
folder only.

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
Phase 1 client calls the **introduction** path for Levels 1–17 when the
drop-in file is absent so a signed-in studio session can play the same
bytes the elevate Learn path uses.

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

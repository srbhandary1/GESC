# Run LessonPractice API so the client hits the product path

This is the `codex/confidence-conversation` LessonPractice stack restored
onto PR #6. The web client prefers `/api/v1/me/levels/*` (streamer +
bound ConversationOrchestrator). Authored Phase 1 rooms + `/demo/tts`
are the fallback only when the API is down or no level is commissioned.

## real-studioz (local Laravel)

From the repo root on the machine that already runs the API
(`/home/srbhandary/projects/global-spoken-english`):

```text
cd apps/api
php artisan migrate
php artisan lesson-practice:replace-app0065-authored-levels --approval=APP-0065
php artisan serve --host=127.0.0.1 --port=8000
```

Optional after APP-0063 inventory + reviewed audio hashes exist:

```text
php artisan lesson-practice:commission-app0063-levels \
  --inventory=/path/to/levels-1-17-teaching-audio.json \
  --approval=APP-0063
```

Named reviews (when the organization already has APP-0065 blueprints):

```text
php artisan lesson-practice:record-app0066-english-review --approval=APP-0066
php artisan lesson-practice:record-app0067-kannada-review --approval=APP-0067
```

Point the web app at that API (Sanctum cookie session, not localStorage):

```text
# apps/web/.env.local or the Vite host env
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

Sign in through the ordinary first-party cookie flow. The learner Learn
surface probes `GET /api/v1/me/levels`. If any level is `available`,
`LevelPathPanel` opens the streamer and
`POST /me/levels/{n}/sessions/{part}/conversation-sessions`. If the probe
fails, My Levels stays on the authored rooms and plays `/demo/tts`.

## Compose

If you use the repository compose stack:

```text
make up          # or the project compose target that starts apps/api
# wait until GET /api/v1/health/ready is 200
docker compose exec api php artisan migrate
docker compose exec api php artisan lesson-practice:replace-app0065-authored-levels --approval=APP-0065
```

The web service should proxy `/api/v1` and `/sanctum/csrf-cookie` to the
API container. Do not put Sanctum credentials in `localStorage`.

## Indic STT (Talk)

Off until explicitly enabled. Secrets stay outside the repo.

```text
VOICE_UPLOAD_ENABLED=true
VOICE_PROVIDER_DRIVER=indic
VOICE_INDIC_TRANSCRIBE_URL=https://<reviewed-host>/speech-to-text
VOICE_INDIC_ALLOWED_HOST=<reviewed-host>
VOICE_INDIC_SECRET_FILE=/path/outside/repo/indic.key   # mode 0600
VOICE_INDIC_AUTH_HEADER=api-subscription-key
VOICE_INDIC_MODE=translate
VOICE_INDIC_OUTPUT_LANGUAGE=en-IN
```

`VoicePracticePanel` appears when the conversation API exposes
`uploadVoiceRecording`. Demo Talk still has browser `SpeechRecognition`
when that method is absent.

## Pre-Levels

Pre-Level 1 **Be** and Pre-Level 2 **This / That** were not in the
LessonPractice archive. They are authored in
`apps/web/src/demo/curriculum/levelCurriculum.ts` and
`apps/api/app/Curriculum/LessonPractice/AuthoredPreLevels.php` so the
listen / chapter-chat / L1-correction loop is closed in-product. The
numeric streamer (`/me/levels/{1-17}/…/audio`) remains Levels 1–17 after
APP-0065. Pre-Levels play via TTS until they are commissioned with media.

## Honest restore table

| Surface | Source |
|---|---|
| LessonPractice PHP, streamer, `/me/levels` | Restored from `9818fe2` / `c4c810f` |
| Indic HTTP STT provider + `voice.indic` | Restored from `fb0748c` / `9818fe2` |
| `WebLevelSessionsApi` / `LevelPathPanel` | Restored from `9818fe2` |
| Levels 2–17 copy | `AuthoredFoundationLessons.php` (APP-0065) |
| Level 1 Can | `9818fe2` localDemo + LPL-01 |
| Pre-Level 1 Be, Pre-Level 2 This/That | Newly authored (not in CC history) |
| Demo TTS failover | Current-branch D-0117 (not overwritten) |

# Demo Azure TTS (client presentation)

## Purpose

Accountable Product Owner client-demo request (D-0092): spoken demo surfaces
use Azure neural TTS matched to the UI language, with no human lesson
recordings and no Azure key in the browser.

This is a **local presentation exception**. It does not change the ordinary
application Azure binding (ADR-0008 / D-0074) or the locked PR-009 benchmark.

## Credentials (local only)

Export before `make demo`:

```bash
export AZURE_SPEECH_ENDPOINT='https://<your-resource>.cognitiveservices.azure.com'
export AZURE_SPEECH_KEY='<subscription-key>'
# or:
# export AZURE_SPEECH_SECRET_FILE="$HOME/ge-speech-benchmark/secret.key"
```

Never put the key in `VITE_*` variables, git, `dist-demo`, or the Android APK.

`make demo` / `scripts/run-local-demo.sh` auto-loads
`$HOME/ge-speech-benchmark/env/speech-benchmark.env` when present (and uses
`AZURE_SPEECH_SECRET_FILE` from that file). It warns only when credentials are
still missing; the UI then falls back to on-device browser speech.

## Voice map

| UI locale | Azure neural voice |
|---|---|
| `en-IN` | `en-IN-NeerjaNeural` |
| `hi-IN` | `hi-IN-SwaraNeural` |
| `kn-IN` | `kn-IN-SapnaNeural` |
| `ml-IN` | `ml-IN-SobhanaNeural` |
| `ta-IN` | `ta-IN-PallaviNeural` |
| `te-IN` | `te-IN-ShrutiNeural` |

Region: `centralindia`. Route: `/tts/cognitiveservices/v1`.

English learning content (Friend replies, English model lines) uses `en-IN`.
AI Teacher replies use the selected guidance locale (or English when toggled).

## Endpoints (Vite middleware and demo-client static server)

| Method | Path | Behaviour |
|---|---|---|
| `GET` | `/demo/tts/status` | `{ configured: boolean, voices: … }` |
| `POST` | `/demo/tts` | Body `{ locale, text }` → `audio/wav` or JSON error |

The same handler is attached to `make demo` / Vite preview **and**
`scripts/demo-client-static-server.mjs` (tunnel / `dist-demo` hosting).
Credentials remain process-env only. The browser bundle never receives a key.

Optional `VITE_DEMO_TTS_ORIGIN` (no trailing slash) lets the Android WebView
call that host’s `/demo/tts` when the APK origin is `https://localhost`.
Do not put the Azure key in any `VITE_*` variable.

Character cap: 500. Character-count logs are written to the Vite process
stdout for rough spend awareness; no learner PII is logged.

## Surfaces

- Phase 1 AI Teacher (auto-speak + Listen)
- Phase 1 Daily Activity audio button
- Mission model audio (`demo-speech:` player)
- Friend reply Listen (English)

## Limitations

- Generative LLM Teacher replies are not included (scripted catalogue text).
- Static `dist-demo` / Android APK still cannot embed Azure secrets. The
  **host process** for `dist-demo` may serve `/demo/tts` when
  `AZURE_SPEECH_*` is set (same as Vite). Android needs
  `VITE_DEMO_TTS_ORIGIN` pointing at that host, or it falls back to
  on-device speech. Sarvam and ElevenLabs are not implemented in this
  repository; Azure neural is the configured demo voice path.
- STT for spoken turns remains on-device browser recognition where available.

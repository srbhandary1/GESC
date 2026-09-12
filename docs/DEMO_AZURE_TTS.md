# Demo Azure TTS (client presentation)

Azure remains one neural provider in the demo-host TTS layer. For Sarvam,
ElevenLabs, Admin key entry, failover, and `/demo/tts/status`, see
**[DEMO_TTS.md](DEMO_TTS.md)** (D-0117).

## Purpose

Accountable Product Owner client-demo request (D-0092): spoken demo surfaces
may use Azure neural TTS matched to the UI language, with no human lesson
recordings and no Azure key in the browser.

This is a **local presentation exception**. It does not change the ordinary
application Azure binding (ADR-0008 / D-0074) or the locked PR-009 benchmark.

## Credentials (local only)

Export before `make demo` — never as `VITE_*`:

```bash
export AZURE_SPEECH_ENDPOINT='https://<your-resource>.cognitiveservices.azure.com'
export AZURE_SPEECH_KEY='<subscription-key>'
# or:
# export AZURE_SPEECH_SECRET_FILE="$HOME/ge-speech-benchmark/secret.key"
```

`make demo` / `scripts/run-local-demo.sh` auto-loads
`$HOME/ge-speech-benchmark/env/speech-benchmark.env` when present.

Admin UI (Profile → Voice providers) can store the same values on the demo
host when `DEMO_TTS_ADMIN_TOKEN` is set. See [DEMO_TTS.md](DEMO_TTS.md).

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

## Endpoints

Shared with Sarvam and ElevenLabs. Status is booleans only:

| Method | Path | Behaviour |
|---|---|---|
| `GET` | `/demo/tts/status` | `{ configured, providers: { azure, sarvam, elevenlabs }, … }` |
| `POST` | `/demo/tts` | Body `{ locale, text }` → audio or JSON error |
| `GET`/`POST` | `/demo/tts/settings` | Admin token; masked secrets |

## Limitations

- Static `dist-demo` / Android APK still cannot embed secrets. The host
  process may serve `/demo/tts` when any provider is configured.
- Optional `VITE_DEMO_TTS_ORIGIN` is an origin override only.
- STT remains on-device browser recognition where available.

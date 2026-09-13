# Demo multi-provider TTS

## Purpose

Learner Tap-to-Play and lesson listen on the disclosed synthetic demo use
neural TTS when any provider is configured on the **demo host**. Browser
`speechSynthesis` is last-resort fallback only.

This is a **local presentation exception** (D-0092 / D-0116 / D-0117). It
does not change ordinary application provider DI (ADR-0008 / D-0074) or the
locked PR-009 benchmark.

## What was found in git history

A full-repo search on this branch, plus `git log --all -S` across other
lineages, found:

- **Sarvam** existed as Indic HTTP **STT** (`VOICE_INDIC_TRANSCRIBE_URL`,
  ADR-0030 / DR-0042) on a later enterprise lineage. That is not a TTS
  product path and is **not** pulled into this demo.
- **ElevenLabs** was explicitly **not accepted** (DR-0038 / D-0219): docs
  said no new TTS provider.
- No committed Sarvam or ElevenLabs **TTS** proxy, Admin key form, or
  `VITE_*` keys existed on this learner-UX branch.

This slice therefore **re-wires demo-local TTS** (Vite middleware +
`scripts/demo-client-static-server.mjs`) rather than restoring a missing
Laravel provider.

## Routing

Whenever any neural provider is configured, the demo host tries neural
synthesis before browser speech.

Default order (locale-aware):

| Locale | Order |
|---|---|
| `en-IN` | Sarvam → ElevenLabs → Azure |
| Indic (`hi-IN`, `kn-IN`, `ml-IN`, `ta-IN`, `te-IN`) | Sarvam → Azure → ElevenLabs |

An explicit `TTS_PROVIDER_ORDER` or Admin preference overrides the default.
If a provider errors, the host fails over to the next configured provider.
Browser `speechSynthesis` runs only after neural synthesis is unconfigured
or every configured provider fails.

ElevenLabs is treated as configured only when **both** an API key and a
voice ID are set. No voice ID is invented.

## Endpoints

Same handler on `make demo` / Vite preview **and** the demo-client static
server.

| Method | Path | Auth | Behaviour |
|---|---|---|---|
| `GET` | `/demo/tts/status` | none | Booleans only: `{ configured, providers: { azure, sarvam, elevenlabs }, order, voices }` |
| `POST` | `/demo/tts` | none | Body `{ locale, text }` → audio (`audio/wav` or `audio/mpeg`) or JSON error |
| `GET` | `/demo/tts/settings` | admin token | Masked settings (last four characters only) |
| `POST` | `/demo/tts/settings` | admin token | Save or rotate host settings |

`/demo/tts/status` never includes keys, tokens, or raw secrets. Verify with:

```bash
curl -sS http://127.0.0.1:5173/demo/tts/status
```

Expect `configured: true` and the matching `providers.*` flag when a key is
present. The JSON must not contain the key itself.

## Operator env (interim, before or instead of Admin)

Export on the demo host — **never** as `VITE_*`:

```bash
export AZURE_SPEECH_ENDPOINT='https://<your-resource>.cognitiveservices.azure.com'
export AZURE_SPEECH_KEY='<subscription-key>'
# or: export AZURE_SPEECH_SECRET_FILE="$HOME/ge-speech-benchmark/secret.key"

export SARVAM_API_KEY='<sarvam-key>'
# optional: SARVAM_API_KEY_FILE, SARVAM_BASE_URL, SARVAM_SPEAKER, SARVAM_MODEL

export ELEVENLABS_API_KEY='<elevenlabs-key>'
# optional override; otherwise the public catalog voice Rachel is used:
# export ELEVENLABS_VOICE_ID='<voice-id>'
# optional: ELEVENLABS_API_KEY_FILE, ELEVENLABS_MODEL_ID, ELEVENLABS_BASE_URL

# optional explicit order, comma-separated:
# export TTS_PROVIDER_ORDER='sarvam,elevenlabs,azure'
```

`make demo` / `scripts/run-local-demo.sh` auto-loads, when present:

- `$HOME/ge-speech-benchmark/env/speech-benchmark.env` (Azure)
- `$HOME/ge-demo-tts/tts.env` (or `DEMO_TTS_ENV_FILE`) for
  `SARVAM_API_KEY`, `ELEVENLABS_API_KEY`, Azure, admin token, and
  `TTS_PROVIDER_ORDER`

Vite / the static server also read that host file at request time. The
file is gitignored. Never put these names in `VITE_*`.

ElevenLabs is configured from the API key alone. The public catalog
voice `21m00Tcm4TlvDq8ikWAM` (Rachel) is the default until
`ELEVENLABS_VOICE_ID` or Admin sets another id. That id is not a secret.

Optional `VITE_DEMO_TTS_ORIGIN` (no trailing slash) lets the Android
WebView call that host’s `/demo/tts`. It is an origin only — never a key.

## Admin UI

Profile → **Voice providers**. The form writes
`GET|POST /demo/tts/settings` with header `X-Demo-Tts-Admin`.

Host setup:

```bash
export DEMO_TTS_ADMIN_TOKEN='<long random token>'
# or: export DEMO_TTS_ADMIN_TOKEN_FILE="$HOME/ge-demo-tts/admin.token"
# optional: export DEMO_TTS_SETTINGS_FILE="$HOME/ge-demo-tts/settings.json"
```

If the admin token is unset, settings returns `503 admin_unconfigured`.
Wrong token returns `401`. The browser does **not** store the admin token
(no `localStorage`, IndexedDB, or Capacitor preferences).

Settings file defaults to `$HOME/ge-demo-tts/settings.json` with directory
`0700` and file `0600`. Environment variables always override file values.

## Rotate keys

1. Open Profile → Voice providers.
2. Enter the host admin token (typed each time).
3. Paste the new key. Leave a field **blank** to keep the current value.
4. Type `__clear__` in a secret field to remove the stored value.
5. Save. Confirm `/demo/tts/status` shows the provider `true` and does not
   contain the new key.
6. Restart is not required for the static/Vite process to re-read env on
   the next request; file saves apply immediately. If you changed only
   process env, restart `make demo` / the static server.

Env-provided keys cannot be overwritten by Admin; change or unset the env
variable, then save.

## Security

- Keys never enter git, `dist-demo`, the APK, or `VITE_*` bundles.
- Status and settings responses mask secrets (`••••` + last four).
- Sarvam requests are allowlisted to `https://api.sarvam.ai`.
- ElevenLabs requests are allowlisted to `https://api.elevenlabs.io`.
- Logs record provider, locale, voice id, and character count only — never
  raw audio, transcripts, keys, or learner text.

## Surfaces

- Phase 1 AI Teacher (auto-speak + Listen)
- Phase 1 Daily Activity audio button
- Mission / lesson model audio (`demo-speech:` player and Tap to Play)
- Friend reply Listen (English)

## Limitations

- Generative LLM Teacher replies are not included (scripted catalogue text).
- This environment does not invent provider keys. Neural supply requires
  operator credentials.
- Ordinary Laravel Azure / Sarvam / ElevenLabs DI remains unauthorized.
- STT for spoken turns remains on-device browser recognition where available.

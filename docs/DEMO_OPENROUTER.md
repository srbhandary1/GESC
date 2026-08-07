# Demo OpenRouter free LLM (client presentation)

## Purpose

Accountable Product Owner presentation path (D-0094): the local demo Friend
on **Practice** may call an OpenRouter **free** model through Vite middleware
`POST /demo/chat`. Keys never enter the browser bundle. Ordinary Laravel
`ConversationModelProvider` bindings stay Fake/Disabled.

This does **not** authorize production LLMs, Azure OpenAI, tool/search
routing, or flawless Indic generative quality.

## Best approach (locked)

| Layer | Choice |
|---|---|
| Chat model (demo) | OpenRouter free (`openrouter/free` by default) |
| Voice | Existing Azure Speech TTS demo path (D-0092) |
| Production app DI | Unchanged — fail-closed / fake |
| Fallback | Deterministic Friend pattern replies |

Why not Azure OpenAI first: the current Azure account is **Speech-only**.
Why not browser WebLLM keys: unsafe. Why free OpenRouter: zero token cost for
presentation, same local-proxy pattern as Azure TTS.

## Setup

```bash
scripts/setup-demo-llm-workspace.sh
# paste key into $HOME/ge-demo-llm/secrets/openrouter.api-key
make demo
```

Or export directly:

```bash
export OPENROUTER_API_KEY='sk-or-...'
# optional pin:
# export OPENROUTER_MODEL='openrouter/free'
make demo
```

Create a key at https://openrouter.ai/keys.

## Endpoints

| Method | Path | Behaviour |
|---|---|---|
| `GET` | `/demo/chat/status` | `{ configured, model, provider }` |
| `POST` | `/demo/chat` | `{ message }` → `{ reply, model, provider }` |

## Limits and quality

- Free tier rate limits apply (commonly 20/min and a low daily cap until
  credits are purchased — see OpenRouter docs).
- English practice replies are the target. Free models are **not** claimed
  flawless for kn/hi/ml/ta/te generative chat.
- No live weather/time tools; the system prompt forbids inventing live facts.

## Surfaces

- Practice → AI Friend turns (`localDemo` conversation API).
- AI Teacher topics remain scripted catalogue text + Azure/browser voice.

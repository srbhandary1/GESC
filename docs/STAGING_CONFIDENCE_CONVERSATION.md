# Staging Confidence Conversation (enterprise Friend)

**Authority:** [APP-0022](APPROVALS.md#app-0022) / [ADR-0010](adr/0010-conversation-model-provider-policy.md);
capped live-dev spend only.  
**Plan:** Enterprise Friend production Step 2.  
**Non-authorization:** production promote, Play, unlimited spend, demo `/demo/*`
paths as evidence.

## Purpose

Run free-flow Practice Friend (Warm-up → Listen → Talk → Reflect) against
**ordinary Laravel DI** in non-production environments (`local`,
`conversation-model-dev`, or `staging`) with Azure OpenAI conversation-model
and optional Azure Speech TTS — without enabling production
`CONVERSATION_PROVIDER_DRIVER`.

## Fail-closed defaults

| Setting | Production default |
|---|---|
| `APP_ENV` | `production` |
| `CONVERSATION_PROVIDER_DRIVER` | `disabled` |
| `CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED` | `false` |

Production must keep the disabled driver. Staging uses a reviewed env file
outside the repository (see workspace script below).

## Workspace setup

```bash
# Creates ~/ge-conversation-model (or $GE_CONVERSATION_MODEL_ROOT)
bash scripts/setup-conversation-model-workspace.sh
```

Then:

1. Fill `vendor-evidence.json` and `spend-approval.json` (₹500 APP-0022 cap).
2. Place API key in `secrets/azure-openai.secret` (mode 0600).
3. Copy `env/conversation-model.env.template` → `conversation-model.env`.
4. Set `LIVE_AUTHORIZATION.txt` to exactly:
   `Authorize live Confidence Conversation Azure OpenAI up to ₹500`
5. Set `CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED=true` only after step 4.
6. Source that env for the API process; never commit it.

Recommended staging binding:

```bash
APP_ENV=staging   # or conversation-model-dev
CONVERSATION_PROVIDER_DRIVER=azure-openai-dev
```

With `azure-openai-dev` in a non-production env, DI binds:

- `ConversationModelProvider` → Azure OpenAI adapter
- `TextToSpeechProvider` → Azure Speech TTS when speech evidence allows; otherwise
  text-first Friend replies remain valid under ADR-0011
- `SpeechToTextProvider` → remains disabled for conversation STT until a
  separate conversation-STT adapter is authorized; learners **type** turns

## Student path under test

1. Authenticated product shell (not `make demo`).
2. Navigate to **Practice**.
3. Select coaching mode (Gentle / Tutor / Fluency).
4. Start Confidence Conversation → Continue through Warm-up and Listen.
5. Type an English turn → Send → observe Friend reply + at most one priority
   correction with `explanation_l1`.
6. Finish practice → Reflect.
7. Confirm no marks/mastery/unlock mutations from AI output.

## Verification

```bash
make ai-eval
# Focused adapter tests when credentials/evidence present:
# cd apps/api && php artisan test --filter=AzureOpenAi
```

Cross-tenant and advisory-only regressions must remain green on fakes in CI
(`CONVERSATION_PROVIDER_DRIVER=fake` or disabled).

## Explicit non-goals

- Dashboard Phase 1 “AI Teacher” generative chat
- Demo OpenRouter `/demo/chat` as production evidence
- Production `APP_ENV=production` with live drivers
- Google Play public release

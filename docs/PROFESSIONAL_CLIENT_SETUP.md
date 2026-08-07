# Professional client setup — presentation to Azure speech

## Tracks

| Track | Cost | When to use |
|---|---|---|
| Phase A — disclosed synthetic demo | ₹0 | Client meeting / remote review now |
| Phase B — Azure Speech synthetic benchmark | ≤ ₹1,000 capped | After APO live-request + vendor/spend evidence |
| Phase C — production pilot | Later checkpoints | Only after PR-009 acceptance (PR-010→PR-015) |

Phase A commands and disclosure:
[CLIENT_DEMO_RUNBOOK.md](CLIENT_DEMO_RUNBOOK.md).

Phase B controlling documents:
[ADR-0008](adr/0008-azure-speech-development-provider-and-benchmark-policy.md),
[PR-009_SPEECH_PROVIDER_BENCHMARK_PLAN.md](PR-009_SPEECH_PROVIDER_BENCHMARK_PLAN.md),
[PR-009_SPEECH_BENCHMARK_EVIDENCE_HANDOFF.md](PR-009_SPEECH_BENCHMARK_EVIDENCE_HANDOFF.md).

## Workstation evidence workspace (outside Git)

Create or refresh the local workspace (never commit its contents):

```bash
sh scripts/setup-speech-benchmark-workspace.sh
# already applied on this workstation at $HOME/ge-speech-benchmark
```

Default root:

```text
$HOME/ge-speech-benchmark
```

Expected layout after the script runs:

```text
~/ge-speech-benchmark/
  README.md
  OPERATOR_CHECKLIST.md
  WAITING_FOR_LIVE_AUTHORIZATION.md
  ephemeral/                         # mode 0700
  evidence/
    vendor-evidence.template.json    # copy → vendor-evidence.json (0600)
    spend-approval.template.json     # copy → spend-approval.json (0600)
    listener-evidence.template.json  # filled after two human reviews
  secrets/
    azure-speech.secret              # mode 0600; paste key/token only
  env/
    speech-benchmark.env.example     # copy → speech-benchmark.env (0600)
  reports/                           # safe aggregate report output path
```

Ordinary application `.env` files must keep:

```text
VOICE_UPLOAD_ENABLED=false
VOICE_PROVIDER_DRIVER=disabled
AZURE_SPEECH_LIVE_REQUESTS_ENABLED=false
SPEECH_BENCHMARK_ENABLED=false
CONVERSATION_PROVIDER_DRIVER=disabled
```

Live benchmark values belong only in the isolated
`~/ge-speech-benchmark/env/speech-benchmark.env` process environment.

## Env var → path map

| Variable | Workspace path |
|---|---|
| `AZURE_SPEECH_SECRET_FILE` | `$HOME/ge-speech-benchmark/secrets/azure-speech.secret` |
| `AZURE_SPEECH_VENDOR_EVIDENCE_PATH` | `$HOME/ge-speech-benchmark/evidence/vendor-evidence.json` |
| `SPEECH_BENCHMARK_SPEND_APPROVAL_PATH` | `$HOME/ge-speech-benchmark/evidence/spend-approval.json` |
| `SPEECH_BENCHMARK_LISTENER_EVIDENCE_PATH` | `$HOME/ge-speech-benchmark/evidence/listener-evidence.json` |
| `SPEECH_BENCHMARK_SAFE_REPORT_PATH` | `$HOME/ge-speech-benchmark/reports/safe-report.json` |
| `SPEECH_BENCHMARK_EPHEMERAL_ROOT` | `$HOME/ge-speech-benchmark/ephemeral` |

Fill endpoint/host, evidence IDs, SHA-256 digests and price snapshot fields
only with real reviewed values. Template placeholders must never be used in an
authorized live run.

## Fail-closed preflight (no live call)

With the pinned PHP toolchain and without real evidence filled in:

```bash
sh scripts/run-speech-benchmark-preflight.sh
```

Expected result: preflight **rejects** incomplete gates (proves fail-closed
wiring). A successful live preflight requires completed external evidence,
credential, spend approval and explicit APO live-request authorization.

## Live sequence (APO spend authorized; credential/vendor still required)

[APP-0015](APPROVALS.md#app-0015) / D-0082 record the blanket demo approval and
₹1,000 spend cap. Outside-repo `LIVE_AUTHORIZATION.txt` and
`evidence/spend-approval.json` are written. Live prepare still waits on a real
`centralindia` credential and vendor/DPA evidence (not templates).

Do not run prepare/complete until:

1. Azure Speech resource exists in `centralindia`;
2. secret file contains the real key or Entra token (mode `0600`);
3. vendor + spend JSON are real reviewed records (cap ≤ 1000 INR);
4. Accountable Product Owner states:
   `Authorize live synthetic Azure benchmark up to ₹1000`.

Write that exact sentence into:

```text
$HOME/ge-speech-benchmark/LIVE_AUTHORIZATION.txt
```

Copy and fill `env/speech-benchmark.env`, set
`AZURE_SPEECH_LIVE_REQUESTS_ENABLED=true`, then:

```bash
sh scripts/run-speech-benchmark-live-prepare.sh
```

The script refuses to start without the authorization file, a non-empty secret
and the live-requests flag. Continue the numbered handoff in
[PR-009_SPEECH_BENCHMARK_EVIDENCE_HANDOFF.md](PR-009_SPEECH_BENCHMARK_EVIDENCE_HANDOFF.md)
(`prepare` → two `review` slots → listener record → `preflight --run` →
live-complete auth → `make ai-eval`).

## Cheap API note

- Presentation speech: free on-device browser Web Speech (already in the demo).
- Approved cloud speech: Azure AI Speech `centralindia` only.
- Do not buy alternate STT/TTS vendors for this checkpoint; they are outside
  ADR-0008.
- Demo OpenRouter remains presentation-only (D-0094).
- Confidence Conversation Azure OpenAI (ADR-0010 / APP-0022): use a **separate**
  outside-repo workspace — never reuse speech secrets:

```bash
sh scripts/setup-conversation-model-workspace.sh
# default: $HOME/ge-conversation-model
sh scripts/run-conversation-model-live-prepare.sh
```

Live phrase (exact): `Authorize live Confidence Conversation Azure OpenAI up to ₹500`.
Hard development ceiling: ₹500 INR. Production DI stays `disabled` until a
later promote APP.

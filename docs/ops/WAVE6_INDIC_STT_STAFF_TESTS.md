# Wave 6 — Indic STT staff hold-to-talk tests

**Scope:** Invite-only soft-launch on this workstation. Not Play, not
`APP_ENV=production`.

**Logging rule:** Never paste transcripts, audio, secrets, OTP codes, or learner
rows into chat or tickets. Staff evidence uses pass/fail counts and safe metadata
only.

## Before you start

1. Run automated preflight:

   ```bash
   make wave6-indic-preflight
   ```

2. Confirm the tunnel URL (HTTPS + microphone secure context):

   ```bash
   make soft-launch-ready
   ```

3. Sign in as a **Direct** learner with active voice consent and live-AI
   entitlement (courtesy Premium is fine).

4. Open **Speak** → hold-to-talk (not Type instead).

## Staff matrix (step 6.3)

Complete **three successful hold-to-talk turns** in each scenario. A success
means: upload completes, transcript appears (or fix/retry UI), Swathi speaks a
reply, HTTP is not 500.

| Scenario | What to say | Pass criterion |
|---|---|---|
| A — English only | Short English sentences about daily life | 3/3 turns complete |
| B — Kannada + English mix | Code-mixed Kannada/English (typical learner) | 3/3 turns complete; English transcript for Maya |
| C — Noisy room | Same as B with background TV/fan | 3/3 turns complete or clean recoverable error (not 500) |

Record results in
[`docs/evidence/wave6/wave6-indic-stt-staff-test-result.json`](../evidence/wave6/wave6-indic-stt-staff-test-result.json)
(copy from the template). Use counts and booleans only — no transcript text.

## Remaining Wave 6 checks

| Step | Action | Pass |
|---|---|---|
| 6.4 | Hold the button >25s | Client auto-stops; user sees limit copy |
| 6.5 | Temporarily break Sarvam (wrong key or block host), one turn | Azure failover or clean error, not HTTP 500 |
| 6.6 | Demo account | 300s live-AI meter increments; denies when exhausted |
| 6.7 | Premium tester | Role-play Perform still works |
| 6.8 | Any turn | Azure OpenAI reply + guidance TTS still fire |
| 6.9 | After a successful Indic turn | `voice_provider_runs` row with `provider_key=indic-http-stt` and `result_status=succeeded` (recorded STT path). Roll back to `VOICE_PROVIDER_DRIVER=azure` if provider runs fail to insert |

Query provider runs (counts/metadata only):

```bash
make soft-launch-ready
# Replace MEMBERSHIP_ID with a staff test membership id.
php apps/api/artisan tinker --execute="echo DB::table('voice_provider_runs')->where('provider_key','indic-http-stt')->where('learner_membership_id','MEMBERSHIP_ID')->count();"
```

## Rollback

See [SOFT_LAUNCH_ROLLBACK.md](SOFT_LAUNCH_ROLLBACK.md). One-line STT rollback:

```text
VOICE_PROVIDER_DRIVER=azure
```

Then `make soft-launch-restart`.

## Exit

Wave 6 is complete when preflight passes, the staff matrix is 3/3 in all three
scenarios, failover is tested once, and provider-run rows prove Indic STT on the
live path.

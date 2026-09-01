# Soft-launch rollback card (Wave 4)

**Scope:** Invite-only Ubuntu soft-launch on this workstation. Not Play,
not `APP_ENV=production`, not a public DNS cutover (Wave 5).

**Logging rule:** Never paste transcripts, audio, secrets, OTP codes, or
learner rows into chat or tickets.

## Current runtime (this host)

| Item | Value |
|---|---|
| Git SHA at Wave 4 start | `114305dd9658edf515fcea90cecb19f0bfdbdd44` (Wave 3 complete) |
| Branch | `codex/confidence-conversation` |
| `APP_ENV` | `soft-launch` |
| Speak STT driver on disk | `VOICE_PROVIDER_DRIVER=indic` (ahead of the Wave 6 staff checklist) |
| Payments | Dummy (`PAYMENT_DRIVER` unset) |
| Public host | Cloudflare Quick Tunnel still in use; Wave 5 replaces it |

## Previous Android APK (keep until Wave 5 rebuild)

| Field | Value |
|---|---|
| Path | `~/ge-soft-launch/artifacts/closed-group/global-spoken-english-in-app-login-20260901.apk` |
| SHA-256 | `aa94cacc3f4e244a9a258aee4f62e345f92bac6654ff3c22140678282213cca0` |
| Note | Sideload only. Rebuild on the stable origin is Wave 5. Older tunnel-pinned APKs in the same directory must not be reinstalled. |

## STT rollback (one env change)

Speak recorded STT rolls back without a code revert:

1. In the gitignored soft-launch env (`~/ge-soft-launch/env/soft-launch.env`), set:

   ```text
   VOICE_PROVIDER_DRIVER=azure
   ```

   Leave `VOICE_UPLOAD_ENABLED=true` unless you also need to stop all learner STT.

2. Restart API and worker: `make soft-launch-restart` (or restart the user unit).
3. Confirm with `make soft-launch-ready` and one staff Speak turn.
4. Do **not** set `APP_ENV=production`.

Other kill switches (Wave 3): `CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED=false` (chat),
`CONVERSATION_GUIDANCE_TTS_LIVE_REQUESTS_ENABLED=false` (TTS),
`VOICE_UPLOAD_ENABLED=false` or `VOICE_PROVIDER_DRIVER=disabled` (all STT).

## Database restore (disaster only)

The Wave 4 drill restores into a **temporary** database and then drops it.
It does **not** overwrite live.

To actually replace live Postgres after a Sev-1:

1. `make soft-launch-backup` (if the database is still readable).
2. `make soft-launch-stop`.
3. Decrypt the offsite ciphertext if the local gzip is gone (`openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -in FILE.sql.gz.enc -pass file:$HOME/ge-soft-launch/secrets/backup.pass | gzip -t` first).
4. Restore the gzip into a **new** database, verify migration counts, then cut the application `DB_DATABASE` over — do not `DROP` the live name until the replacement is verified.
5. Apply the current deletion/revocation overlay before serving traffic ([BACKUP_RESTORE_DRILL.md](BACKUP_RESTORE_DRILL.md)).
6. `make soft-launch-start` and `make soft-launch-health`.

Local gzip dumps: `~/ge-soft-launch/backups/soft-launch-YYYYMMDDTHHMMSSZ.sql.gz` (mode `0600`).
Wave 4 dump: `soft-launch-20260901T162027Z.sql.gz` SHA-256 `8bfc83d57c6deb9e4cc4a825ec4cb647649469fca96072c56738d417ba3e84ad`.
Encrypted copies: `$HOME/ge-offsite-backups/*.sql.gz.enc` (Wave 4 ciphertext SHA-256 `6437e45efd1533be6f78a79cd4b8d98becbb0e79b7f4a78b6c96223ae5a0a364`). This host has a single NVMe; copy the ciphertext to USB or another machine when you can.

## Code rollback

If a bad tree is running:

```bash
git -C /home/srbhandary/projects/global-spoken-english rev-parse HEAD
# Return to Wave 3 product land if Wave 4 ops scripts are the problem:
git checkout 114305dd9658edf515fcea90cecb19f0bfdbdd44
make soft-launch-restart
```

Prefer env/STT rollback before a git checkout. Do not force-push.

## Observe

- User timer `global-english-soft-launch-observe.timer` (every minute).
- Content-free JSONL: `~/ge-soft-launch/alerts/observe.jsonl`.
- Optional: set `GE_SOFT_LAUNCH_ALERT_MAILTO` or `GE_SOFT_LAUNCH_ALERT_COMMAND` in the gitignored runtime. Named SMS/Telegram tokens were not invented.

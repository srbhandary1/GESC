# STUDENT-CONVERSATION-02 private recovery operations

Authority: [ADR-0042](adr/0042-interactive-roleplay-and-consented-question-help.md)
and [the bounded specification](STUDENT_CONVERSATION_02_SPEC.md). This runbook
describes implementation requirements. The VPS deployment below is operational
evidence, not independent privacy acceptance or a production-release approval.

## VPS deployment, 25 September 2026

The six additive migrations were applied after a verified encrypted PostgreSQL
backup. The existing backup destination is on the same VPS: its historical
"offsite" label does not establish off-box recovery.

Recovery is mounted at the retained `adr0041` runtime storage path as a 256 MiB
`tmpfs` with `noswap,nodev,nosuid,noexec`, ownership `1000:1000` and mode `0700`.
The deployment verified these options. This keeps recovery bodies out of disk
backups/snapshots and swap; it is not a claim about hypervisor memory captures.
The runtime key remains unchanged and outside source control.

`infra/soft-launch/conversation-recovery-memory.sh` refuses nonempty underlying
storage and persistent-disk fallback. Its root systemd service is required before
`user@1000.service`; the app's user-service drop-in checks the mount before start.
The normal Laravel minute scheduler remains active. An additional root-managed,
unprivileged purge timer is pinned to the additive-schema release, so expiry
cleanup survives application rollback. Both cleanup paths are idempotent.
Keep `adr0041` (shared storage) and `student-conversation-20260925` (pinned purge
executable) until those dependencies are explicitly migrated.

Initial purge reported zero failures, and the first synthetic sessions' three
artifacts each were subsequently marked deleted with no files remaining.
The systemd cleanup service reported `Result=success`, `ExecMainStatus=0`.
Reboot recovery and alert delivery have not been simulated. Missing memory after
a reboot must remain an honest expired/restart result, never reconstructed text.

## Storage and release boundary

- Dedicated Laravel disk: `conversation_recovery`. Its root is
  `storage/app/conversation-recovery` relative to the runtime storage tree.
  It must remain local, private and `serve=false`; no public URL, route,
  `storage:link`, object CDN or ordinary cache may expose it.
- Bodies use Laravel `Crypt::encryptString` with the runtime application key.
  Keep the key out of source, logs, deployment manifests and test evidence.
  The disk requests file mode `0600` and directory mode `0700`. Verify ownership
  and effective modes as the runtime user during deployment.
- Each random opaque filename holds one encrypted, ownership-bound JSON
  snapshot. Maximum plaintext envelope size is 8 MiB, including bounded
  generated reply audio. This is not permission to keep raw microphone audio,
  arbitrary history or another artifact's expired content.
- Explicitly exclude this entire tree from filesystem backups, machine/image
  snapshots, support bundles and release archives. The current soft-launch
  backup script backs up PostgreSQL only; its recovery table contains no
  plaintext/ciphertext bodies. Source release transfer must exclude the entire
  `apps/api/storage` tree, including when runtime storage is a symlink.
- A restored database may contain old metadata whose bodies do not exist.
  Such recovery must fail closed; never fetch content from backup or replay a
  provider call to fabricate an exact saved response.

## Lifetime and cleanup

`config/conversation_recovery.php` sets 900 seconds, and code hard-caps it at
900 seconds even if configuration is raised. Each artifact's creation fixes
its deadline; reads/retries do not update it. Older context is referenced by
artifact ID, not copied into a fresh artifact to renew its lifetime.

At the deadline, content is logically unavailable immediately. A read at that
boundary requests deletion. Physical deletion also runs through Laravel's
scheduled `conversation:purge-recovery` every minute, without overlap. Thus a
healthy scheduler may have up to roughly one minute of cleanup lag, plus
execution time; **do not claim physical deletion at exactly 900 seconds**.
Scheduler or filesystem outages can increase that lag and require attention.
Orphan encrypted files from an enclosing transaction rollback have no reader;
the same worker removes them once their fixed file age reaches 900 seconds.

The runtime's normal Laravel scheduler must be running. From the repository
root, a manual retry is:

```text
php apps/api/artisan conversation:purge-recovery
```

Use the deployment's configured PHP executable and environment. Output is only
`deleted`, `failed` and `orphan_deleted` counts. Any failed cleanup returns exit
code 1; retain this content-free signal in operator monitoring and alert on a
nonzero failure count or missed scheduled execution. Do not dump artifact
bodies, object paths, transcripts, exception causes or learner IDs for triage.

Exit, processing-consent withdrawal, logout and account erasure request early
deletion. Membership cleanup locks owned conversation sessions and closes
dialogue state before object deletion. Persistence uses the same parent lock
and rejects a closed dialogue, preventing a late provider response from
resurrecting usable context. Explicit session exit uses the conversation
service's equivalent parent-lock barrier.

Deletion first records `deletion_pending`, making reads unavailable, then
confirms the private object is absent before recording `deleted`. Storage
failure preserves the pending intent for retry. Consent withdrawal remains
recorded even when cleanup fails. Account erasure remains
`partially_completed`, with no completion timestamp, until recovery and voice
deletions finish; the scheduled command reconciles these requests afterward.
Recoverable cleanup failures must not be described as successful deletion.

## Schema and privacy inventory

- Migration `2026_09_25_000470_create_conversation_recovery_artifacts.php`
  adds only scoped recovery metadata: ownership/FKs, kind, random private path,
  ciphertext digest, creation/expiry/deletion times and deletion state/attempts.
  PostgreSQL enforces the 15-minute maximum and same-session/learner turn FK.
- Migration `2026_09_25_000471_create_conversation_dialogue_states.php`
  adds content-free dialogue state and advisory outcome metadata.
- Migration `2026_09_25_000472_add_voice_transcript_provenance.php` adds
  original/translated-source, detected-locale and confirmed-text digest fields;
  it does not add transcript bodies to the database.
- Migration `2026_09_25_000473_create_conversation_session_speech_calls.php`
  adds the once-per-session role-play opener speech reservation/usage ledger
  and its encrypted recovery pointer. It does not fabricate a learner turn.
- Migration `2026_09_25_000474_allow_local_dialogue_control_cost_ledger.php`
  permits only the exact zero-unit, zero-cost deterministic permission-control
  provider branch. It does not relax paid-provider constraints. Its rollback
  refuses to discard immutable usage evidence once that branch has been used.
- Migration `2026_09_25_000475_create_dialogue_turn_reservations.php` adds
  scoped, body-free logical-turn reservations keyed by both command and server
  revision. It stores no request text, response, provider payload or guessed cost.
- Privacy inventory `learner-privacy-v13` includes recovery, dialogue states
  and dialogue outcomes. Exports omit recovery bodies, paths and digests and
  omit artifact references from dialogue state/outcomes. This does not turn
  temporary recovery into a saved transcript/history feature.

## Modern speech authorization and accounting

Recorded-voice admission, upload replay, transcript access/review and STT
dispatch also require an owned, open, unexpired modern dialogue. They use the
same parent-session lock as exit/withdrawal; legacy recording behavior remains
unchanged. A late rejected upload enters the existing durable voice-deletion
workflow instead of becoming retryable transcription work. If the deadline is
crossed during a provider request, known usage remains recorded but the late
transcript is not returned or persisted as a usable candidate. Modern external
dispatch transactions have no automatic database retry.

The new 15-minute artifact policy covers dialogue context/reply recovery.
Original microphone audio and optional transcript-history processing retain
their separately noticed ADR-0007 purposes and deletion controls; they are not
silently reclassified as dialogue recovery or newly consented by its checkbox.

Modern `/speech` first requires current provider-processing consent as well as
the accepted recovery notice and session entitlement. It accepts only the
server-owned opener while revision is zero, or the exact latest validated
reply/correction. An Ask Swathi local-language main answer additionally needs
the recorded `accept_explanation` action. Supplying text or an L1 purpose is
not permission to synthesize arbitrary narration or an unaccepted explanation.

Latest turn audio is replayed from its encrypted validated snapshot. Missing
audio is text-first, not a new provider dispatch. Only the requested audio
channel is returned; another channel's local-language bytes are not smuggled
into a main-playback response. A distinct corrected English sentence without
its own saved recording remains text-first.

An opener reserves its unique `roleplay.opener` operation before external I/O,
then holds the parent-session lock during its sole synthesis attempt. Its
server-owned voice/locale is English (`en-IN`). Returned usage records unit
kinds/quantities, estimated cost and nonsecret provider-configuration metadata.
Available opener playback consumes the existing speech allowance once. Replay
neither synthesizes nor charges again. Unknown/interrupted attempts or failed
provider calls never imply a known zero cost: the estimate remains null when
not supplied. A stuck `dispatching` row is an unresolved attempt, not grounds
for automatic redispatch; use content-free aggregate counts for reconciliation.
Known successful provider usage survives a recovery-write failure, with
text-first fallback and no retry dispatch.

The standalone speech endpoint must not be wrapped in a transaction that
rolls back its pre-dispatch reservation after external I/O. No provider-call
transaction has an automatic retry count. A session closed or advanced
between reservation and dispatch does not speak an out-of-date opener.

## Modern turn crash barrier

Modern turn admission commits its separate reservation before the existing
provider transaction starts. The admission callback validates current consent,
session/revision, transcript provenance, budget and entitlement before any
marker is inserted. A preflight rejection leaves no reservation. In the
approved admitted-logical-turn quota policy, successful admission consumes the
existing turn allowance once; the provider transaction's identical charge key
does not consume again.

If provider I/O finishes but the outcome transaction rolls back or the process
dies, the reservation remains. The same key cannot dispatch again, and a fresh
key at the unchanged server revision cannot disguise a retry. Respond with the
safe unknown-outcome conflict/restart path. Do not auto-refund or redispatch;
the admitted quota remains reserved, and is **not evidence of actual provider
spend**. Unknown usage must not be represented as zero/free.

A matching committed command, including a failed-turn result, still replays its
exact encrypted saved outcome. Reservation completion is linked atomically to
that command and turn. The provider transaction has no automatic retries.
An unrelated enclosing production transaction around reservation is rejected,
because rolling it back would defeat the durable barrier. Ordinary privacy
exports include safe reservation status/revision/times and omit fingerprints
and command keys. No long-term transcript history is introduced.

## Verification scope

`ConversationRecoveryStoreTest` covers encrypted/private exact snapshots,
cross-organization and same-organization ownership denial, fixed expiry,
scope-safe exit/logout cleanup, failed deletion/retry, withdrawal replay,
late-write rejection after withdrawal, erasure reconciliation, tampering,
metadata-only privacy export, rollback-orphan purge, size bounds and refusal
of publicly served disk configuration. The focused PostgreSQL recovery,
voice/privacy and authentication suite passed 32 tests / 431 assertions on
25 September 2026; the separate durable-reservation suite passed 6 tests /
43 assertions. Deployment storage modes, backup exclusions, scheduler
operation, representative usage volume and human privacy acceptance remain
separate operational checks.

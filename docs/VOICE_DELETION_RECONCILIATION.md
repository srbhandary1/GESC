# Voice deletion reconciliation

## Scope

This runbook covers ADR-0007 source-recording deletion for learner deletion,
consent withdrawal, scheduled retention and operator account erasure. It does
not authorize raw audio access, provider deletion, legal holds, production
voice enablement or a broader data-subject erasure inventory.

## Invariants

- The database transaction records a tenant-owned `voice_deletion_requests`
  row and a content-free `outbox_events` row before storage work starts.
- The outbox aggregate is the deletion-request ULID. Its payload is empty; it
  never contains an object key, audio, transcript or learner identifier.
- Object-store deletion runs after the intent transaction commits.
- `voice_recordings.source_object_key` remains present until deletion returns
  success. A failed or uncertain result remains unpublished and retryable.
- A lease plus monotonically increasing attempt number prevents an active or
  stale worker from finalizing another worker's claim.
- A failure at the configured maximum attempts records a terminal,
  content-free dead-letter state. It does not clear the object key, publish the
  event or complete the deletion receipt.
- Privacy-erasure requests link to the exact voice deletion receipts and are
  reconciled from those receipt states.

## Operation

`voice:purge-expired` runs every five minutes with overlap prevention. Each run
creates any due expiry intents, then processes up to 500 due voice-deletion
outbox rows. Retry delay uses capped exponential backoff.

Operational configuration:

```text
VOICE_DELETION_LEASE_SECONDS=300
VOICE_DELETION_RETRY_BASE_SECONDS=60
VOICE_DELETION_RETRY_MAX_SECONDS=3600
VOICE_DELETION_MAX_ATTEMPTS=5
VOICE_DELETION_OUTBOX_STALE_SECONDS=3600
```

The command reports recordings examined/purged, deletion attempts/failures and
aggregate outbox health. It exits non-zero when active work is stale or any
dead letter exists, so the scheduler result is an alertable signal. Run
`php artisan voice:deletion-status` for the same content-free health snapshot.
Safe persistent failure categories are limited to stable codes such as
`voice_source_delete_failed`; do not add exception messages or object keys.

## Failure response

1. Confirm the private voice disk and deletion capability are healthy without
   reading or copying learner objects.
2. Run `php artisan voice:deletion-status`. Inspect counts/statuses only:
   unpublished voice-deletion outbox rows, attempt counts, safe error codes and
   `voice_deletion_requests.status`.
3. Do not clear `source_object_key`, set `source_deleted_at`, publish the
   outbox row or mark a receipt complete manually.
4. For an ordinary deferred retry, restore storage capability and allow the
   scheduled worker to continue. For a dead letter, repair the dependency and
   run `php artisan voice:deletion-requeue <outbox-event-ulid>` once. The
   command only accepts an unpublished dead-lettered voice-deletion event and
   emits no tenant, learner, recording or object identifier.
5. Run `php artisan voice:purge-expired` for immediate reconciliation, then
   require `voice:deletion-status` to return zero.
6. Escalate a job that remains unpublished beyond the ADR-0007 active-store
   objective as a privacy incident. Preserve only content-free evidence.

Production remains blocked until the reviewed private object store,
independent privacy/security/tenancy review and production recovery/PITR
evidence are complete. The REL-01 logical restore drill is non-production
implementation evidence only.

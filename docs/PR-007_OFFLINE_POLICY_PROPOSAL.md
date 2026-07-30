# PR-007 offline policy proposal

## Purpose

This document was the review surface for
[ADR-0006](adr/0006-offline-retention-isolation-and-conflict-policy.md).
APP-0008 accepts that ADR at the exact proposal hash, resolves DR-0004 and
authorizes only PR-007 implementation. It does not authorize PR-008 or
production.

## Recommended policy

| Area | Proposed rule |
|---|---|
| Enablement | Persistent offline learning requires explicit personal-device opt-in and may be disabled by an organization; shared devices receive shell caching only |
| Public cache | Content-addressed application shell, reviewed interface catalogues, fonts and non-personal offline pages |
| Protected storage | Mission bundles, model audio and learner outbox use encrypted per-account IndexedDB partitions |
| Credentials | Never stored in Cache Storage, IndexedDB, localStorage or ordinary Capacitor preferences |
| Raw audio | Remains volatile and local-only; never enters the PR-007 outbox |
| Authorization | Server-signed, purpose-specific offline lease with a maximum 24-hour lifetime; reconnect always revalidates current authority |
| Mission retention | Maximum 30 days after the latest successful online revalidation, with cleanup on the next application/service-worker activation |
| Pending work | Active for 30 days, then read-only until same-account reauthentication and sync or explicit discard; never silently deleted as stale |
| Successful sync | Protected command payload deleted immediately after committing the exact server receipt |
| Logout | Bounded same-account sync attempt, explicit pending-work choice, key-first cryptographic purge, server revocation, then physical cleanup; offline server revocation remains visibly pending |
| Account switch | Old key and partition are purged before another account is displayed; replay under another identity is forbidden |
| Conflict rule | PostgreSQL authority, atomic command acceptance, exact idempotent replay and no last-write-wins |
| Multiple devices | Distinct valid attempts may be accepted; server acceptance order controls first/latest and deterministic score controls best |
| Rejection | Work remains visibly `needs-attention`; it never creates authoritative mastery, attendance or completion |

## Explicit trade-offs for approval

- A learner must refresh offline authorization at least every 24 hours.
- Offline data is permitted only after a personal-device warning and opt-in.
- Browser encryption does not protect an unlocked or compromised browser
  profile from same-origin code.
- The web platform cannot execute guaranteed timed deletion while the
  application is never opened.
- A membership, enrollment, rights or suspension change can prevent server
  acceptance of locally completed work. The work remains visible for recovery
  rather than being silently discarded or converted into authoritative
  progress.
- Logout with unsynchronized work requires a successful sync, cancellation or
  explicit permanent-discard confirmation.

## Unchanged boundaries

- English remains the learning language.
- UI translation review status and production fallback remain unchanged.
- PR-006 deterministic scoring, first/latest/best and spoken-mastery rules
  remain authoritative.
- Raw recording upload, persistent audio, transcription, AI conversation and
  provider processing remain excluded.
- No PR-008 or later-checkpoint behavior is authorized.

## Approval evidence

[APP-0008](APPROVALS.md#app-0008) records `S R Bhandary`'s explicit approval
as Technical Decision Owner, Security Reviewer, Privacy Reviewer and Tenancy
Reviewer at proposal SHA-256
`242a3cff4a5822ebb986515b670ed7c5ed0d243aa86622d80a3254892d348fbb`.

Those consolidated roles authorize development but are not independent
specialist review or production approval.

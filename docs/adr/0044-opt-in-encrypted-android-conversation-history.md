---
status: accepted
date: 2026-09-26
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
amends_master_plan: true
approval_reference: Owner selected encrypted text and audio history, retained on the phone until deletion, and free offline replay in the active task
---

# ADR-0044 — optional encrypted Android conversation history

The owner explicitly selected **encrypted text and audio history**, followed by
**free offline replay**. This amends ADR-0006/0007/0042 and master §§14.2/18.1
only for a separate, optional Android archive. Server recovery still expires at
15 minutes and server raw-audio deletion is unchanged. No cloud archive,
automatic AI memory, research permission or training use is introduced.

Account > Privacy offers an unchecked choice to save future conversation text,
learner recordings and available AI audio on this phone until deleted. Existing
conversations cannot be reconstructed after expiry. Stopping saving and deleting
existing history are distinct controls. Uninstall/app-data clearing also removes
history; no backup/restore or export is promised.

Use app-private no-backup files encrypted with AES-GCM, randomized nonces and
Android Keystore keys. Bind encryption and records to exact user, organization
and membership, with opaque filenames. Authentication changes immediately close
the active archive; another account cannot list, read or append its records.
No credentials, transcripts or audio belong in localStorage, ordinary preferences,
logs or telemetry. Persist only validated successful AI replies and learner-owned
input after its explicit submission. Failed/unsafe output is never saved as a
valid answer. A visible error reports storage failure; no silent eviction.

Bound file and total storage to protect device capacity. Reaching the limit stops
saving new history until the learner deletes items, rather than deleting old
history. Deletion cancels pending saves and playback. The account-deletion page directs the learner to clear the local archive or
app storage. Remote erasure cannot reach an offline phone; archives cannot be
opened without the matching authenticated identity. Do not claim a remote
delete has erased bytes on a disconnected device.

Saved replay makes no model/STT/TTS request and does not deduct live-AI minutes.
Online generation and initial playback keep existing metering. This archive is
read-only learning history, never assessment evidence or resumable server context.

Implementation/security checks are not independent specialist acceptance.
References: [Android Keystore](https://developer.android.com/privacy-and-security/keystore),
[app-private files](https://developer.android.com/training/data-storage/app-specific),
[backup exclusions](https://developer.android.com/identity/data/autobackup).

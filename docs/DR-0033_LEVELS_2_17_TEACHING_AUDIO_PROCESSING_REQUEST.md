# DR-0033 — Levels 2–17 teaching-audio processing authorization

**Status:** Resolved — Option A accepted under APP-0062 / D-0203

**Needed by:** Binding Levels 2–17 Introduction/Practice/Conversation/
Interaction media into the ADR-0025 four-session path

**Authority:** APP-0047; APP-0048 / ADR-0016; APP-0058 / DR-0029 Option A;
APP-0061 / ADR-0025; `docs/ops/MEDIA_INGESTION_RUNBOOK.md`;
`docs/evidence/lpl-01/levels-1-17-teaching-audio-hash-inventory.json`

**Opened:** 14 August 2026

**Resolved:** 14 August 2026

**Pre-resolution DR SHA-256:**
`85bbf170ee5fbaf98b33bb93d5253bed83955db309ceef2eb92cdc508171667a`

**Inventory SHA-256:**
`00b27b12a19af09bdadb5e580e5dfca998a69ab2b3e634243dfe5e5c6d695d30`

## 1. Decision needed

The product owner supplied credentials for the owner-designated legacy admin at
`globalspokenenglish.com` so teaching media can feed the current project's
per-level four-session path.

Read-only observation on 14 August 2026 confirmed:

- 17 Audio lessons and **67** unique teaching MP3s (~300 MB);
- Levels 1–14 and 16–17: Introduction, Practice, Conversation, Interaction;
- Level 15: Introduction/Practice, Conversation, Interaction (3 files);
- Level 1 hashes **exactly match** the four APP-0047 authorized objects already
  in the current private Media Library path;
- Assignment/user media was not accessed.

APP-0047 authorizes only those four Level 1 hashes. Levels 2–17 bytes now exist
in ephemeral local `/tmp` staging, but **must not** enter Media Library,
rights, attachment, learner delivery or Git without an exact-hash approval.

## 2. Recommended Option A

Authorize non-production private ingestion of the exact SHA-256 values in
`docs/evidence/lpl-01/levels-1-17-teaching-audio-hash-inventory.json` for the
63 non–Level-1 files (or all 67 if re-stating Level 1), as `lesson_audio`,
through the ordinary Media Library quarantine → inspect → ClamAV → accept
path, with program-scoped rights for private Direct pilot delivery in India on
web/Android.

Approval does **not** authorize:

- production/public/Google Play release;
- Assignment or unknown-provenance audio;
- skipping English/Kannada review or blueprint attachment rules;
- committing media bytes or credentials to Git;
- mutating the live `globalspokenenglish.com` site.

## 3. Requested decision

Reply with one of:

```text
Approve DR-0033 Option A for the exact inventory hashes.
```

or a bounded amendment (for example Levels 2–3 only first).

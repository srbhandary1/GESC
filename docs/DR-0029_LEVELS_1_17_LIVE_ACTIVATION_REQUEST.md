# DR-0029 — Levels 1–17 live activation and blanket-waiver request

**Status:** Resolved — Option A accepted under APP-0058 / D-0184  
**Opened:** 12 August 2026  
**Resolved:** 12 August 2026  
**Needed by:** LPL-01–LPL-07 and any learner-facing Levels 1–17 claim  
**Authority affected:** Master plan §§6, 18, 22, 26–27; ADR-0004, ADR-0007,
ADR-0010 and ADR-0015

## 1. Request

The product owner requested that Levels 1–17 and the AI steps beneath them be
made live under a blanket waiver.

A blanket waiver is not an executable publication input. It cannot create
missing curriculum records, media, transcripts, answers, learner consent,
provider evidence or tenant authority. It also cannot change immutable rights
or review evidence, database constraints, enrollment pins or provider kill
switches without a separately specified, implemented and verified change.

## 2. Verified current evidence

Read-only inspection of the current local PostgreSQL database and governed
inventory found:

- zero active `curriculum_levels` rows;
- two duplicate active staging-smoke imports, each containing one scaffold and
  12 numbered video modules for Levels 1, 2, 3, 6–11, 13, 14 and 17;
- no imported current modules for Levels 4, 5, 12, 15 or 16;
- one generic mission per smoke import, not a reviewed AI learning loop;
- no topic transcripts, reviewed per-level objectives, 3–7-item quizzes,
  answer rationales, Talk goals, Apply rubrics or delayed-review paths;
- four role-play/Friend scenarios constrained to `development_only`, with all
  four versions constrained to `synthetic_unreviewed`;
- two conversation sessions and zero persisted conversation turns;
- learner-facing Azure conversation and ordinary learner Speech still disabled
  by their provider/privacy/consent gates; and
- existing imported-video rights sufficient only for the bounded private
  smoke path, with derivative use disabled. APP-0047 covers only four exact
  Level 1 MP3 hashes for non-production processing and does not approve a
  curriculum publication or public production use.

The legacy source has labels and six source nodes for all 17 levels, but that
is lineage, not current reviewed content or publication authority. The demo
Level 1–17 shell is navigation only.

No learner-personal data, credential, transcript, audio or secret was read for
this determination.

## 3. Options

### Option A — Accelerated governed rollout in reviewed waves (recommended)

Keep the existing LPL ordering but commission in parallel where authority and
evidence allow:

1. Finish the exact Level 1 transcript reconciliation and publishable
   blueprint.
2. Deliver and verify the Level 1 Learn → Talk → Check → Apply → Review slice.
3. Inventory and commission Levels 2–3, then remaining source-complete levels.
4. Leave every incomplete level unavailable with an exact reason; never
   fabricate missing media or answers.
5. Record exact-hash English/Kannada/content/rights review for each release.
6. Enable typed live AI only after the model provider gate passes; enable
   learner voice only after its separate consent/privacy/provider gate passes.
7. Publish immutable versions and pin enrollments through ordinary tenant-
   scoped commands. Complete physical-device and release evidence before a
   public-production claim.

The product owner may consolidate qualified English and Kannada review roles
where ADR-0004 permits it, provided the reviewer did not author or translate
the selected revision. This accelerates review without converting a blanket
statement into fabricated per-item evidence.

Approval text:

```text
Approve DR-0029 Option A as written.
```

### Option B — Clearly labelled non-authoritative preview only

Create an adult, owner-known, non-production preview from the existing demo
shell and exact available source descriptors. Missing levels remain labelled
unavailable. Synthetic conversation remains visibly synthetic and uses no
live provider, learner marks, mastery, unlock, publication, enrollment pin or
production claim.

This can demonstrate navigation and UI but does **not** satisfy the request for
live Levels 1–17 with live AI.

Approval text:

```text
Approve DR-0029 Option B as written.
```

### Option C — Blanket production activation

Rejected as non-executable. It would require fabricated content for missing
levels, false rights/review claims, weakened tenant publication/enrollment
authority, or provider/consent bypasses. Those shortcuts are explicitly
prohibited by the master plan and accepted ADRs and would still not create a
complete working learning pathway.

## 4. Stop condition

Until Option A or B is explicitly selected, make no curriculum publication,
enrollment, provider, voice, permission, rights or production mutation for
this request. A statement such as “blanket waiver” is not acceptance of either
bounded option.

Stable-host activation remains a separate incomplete plan deferred to 13
August 2026.

## 5. Resolution

Option A was accepted exactly as written on 12 August 2026 under
[APP-0058](APPROVALS.md#app-0058) / D-0184. The approved pre-resolution
request has SHA-256
`8f2363579a58a84d55032d6e3e4aec2b996d77503b8afa1aa9424a08baebdafb`.

Acceptance authorizes the accelerated governed rollout and makes LPL-01 the
single active content checkpoint. It does not accept LPL-01 results, authorize
LPL-02+, publish any curriculum, pin an enrollment, enable a provider, waive
rights/consent/review/tenancy controls or authorize production/Google Play.

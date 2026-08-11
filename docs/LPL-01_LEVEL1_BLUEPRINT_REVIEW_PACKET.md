# LPL-01 Level 1 blueprint review packet

**State:** Blocked draft; governed ingestion, private pilot and four exact
program-scoped rights revisions complete; DR-0025 accepted, commissioning
implementation, transcript and human review pending

**Source inventory:** `LPL-01_CONTENT_INVENTORY.md`

**Schema target:** `global-english.lesson-practice-loop.v1`

## 1. Why this packet is blocked

There is now source evidence for a Level 1 `can` blueprint, but no governed and
reviewable blueprint to approve yet.

The current system contains one exact Level 1 MP4 with SHA-256
`8cc0ab3ceae5247e15b9feb17c2b9836d102a131b42232e6d74627b706edb5c6`.
Its governed wrapper says only `Level 1`; it has no transcript or topic-specific
objective. Its current rights revision permits private learner delivery in
India but explicitly disallows derivative use.

Authenticated read-only source inspection has separately identified four
upstream Level 1 MP3 parts—Introduction, Practice, Conversation and
Interaction—and a rich-text `CAN` lesson body. Exact source hashes are recorded
in `LPL-01_LEGACY_SOURCE_MAP.md`. Those exact MP3s are now active private
`lesson_audio` Media Library assets with one approved initial asset-bound
rights revision each under the standalone LPL pilot. They still have no
attachment, transcript or review evidence. APP-0047 authorizes the four exact
hashes for bounded private non-production processing, but does not create
those later content records.
DR-0021 Option A is accepted and its distinct `lesson_audio` Media Library
policy has passed repository verification, E2E and build gates. Reacquired
temporary copies of all four exact hashes also passed the project-owned real
ffprobe and ClamAV adapters, as recorded in
`LPL-01_INGESTION_PREFLIGHT.md`. DR-0023 then preserved the locked incident,
proved its bounded delta, restored the exact baseline, replayed APP-0049 and
retained a protected active MFA factor. A corrected ordinary HTTP run accepted
all four exact objects with persisted ffprobe/ClamAV evidence. Failed adapter
attempts remain failure evidence and created no asset.

DR-0024 Option A created the standalone private program
`global-spoken-english-lpl-pilot`, a separately revocable role with exactly
`curriculum.author` and `media.rights.approve`, and one accepted program-
scoped `rights_confirmer` appointment. Ordinary selected-membership/fresh-TOTP
HTTP commands recorded the four exact APP-0047 rights revisions. This packet
therefore stops before provider use or content drafting, not at rights
authority. [DR-0025](DR-0025_LPL01_TRANSCRIPTION_COMMISSIONING_REQUEST.md)
Option A is accepted under APP-0052 / D-0153 / ADR-0020 and fixes the finite
direct-MP3 Azure campaign, provider-neutral provenance, encrypted candidate
and human reconciliation boundary. That implementation and its live gates are
pending; acceptance itself made no provider request or content record.

Consequently, this packet may use `can` as the upstream source topic, but does
not infer that the MP4 or each MP3 exactly matches the rich-text body and does
not promote source examples into reviewed answer keys.

## 2. Exact candidate blueprint state

| Field | Current value | Status |
|---|---|---|
| Source lesson | Upstream Level 1: `Can — I can speak in English`, with Video, Audio, CAN body, Workshop, Group Practice and Assignment rows | Source exact; not current governed content |
| Source media | One current MP4 plus four active private `lesson_audio` assets with exact APP-0047 hashes, clean scans and approved program-scoped rights | Governed ingestion/rights complete; attachment pending |
| Ordered source parts | Introduction → Practice → Conversation → Interaction | Exact upstream order and asset/right IDs recorded; attachment pending |
| Transcript/language/segments | Unknown | Blocked |
| Primary and secondary topics | `can`: ability, possibility/impossibility, permission and polite requests | Source-supported; academic review pending |
| Can-do objective | Source promise “I can speak in English” is too broad; existing `legacy.level_video.orientation` is insufficient | Authored observable objective required |
| Target patterns/chunks/examples | Source body contains affirmative/negative, yes/no and WH questions, polite requests and substitution examples | Extraction/correction/review pending |
| Misconception codes | Not authored | Blocked |
| Talk scenario and elicitation goals | Directions/help conversation and Workshop tasks are source candidates | Structured reviewed goals pending |
| Check items/weights/answers/rationales | None | Blocked |
| Apply task/rubric | None | Blocked |
| Difficulty/prerequisites | Unknown | Blocked |
| English academic review | Source exists; exact blueprint not yet drafted | Pending |
| Kannada variant/review | Cannot begin before approved English source | Pending |
| Publishable | No | Blocked |

## 3. Required blueprint contract

For each exact Level 1 lesson, the reviewed packet must contain:

1. immutable source lesson and media identifiers, hashes, ordered part/segment
   references and transcript hash;
2. source and guidance languages, transcript segments and confidence with
   explicit human corrections for uncertainty;
3. one observable can-do objective, prerequisites, communication function,
   target patterns, chunks, examples and misconception codes;
4. bounded Talk scenario, elicitation goals, hints and authored provider-down
   fallback turns that contain no answer key;
5. three to seven single-choice `lesson-practice-v1` items with positive
   integer weights totalling 100, exact correct option and rationale, and an
   80-point pass threshold;
6. an Apply task and human rubric whose speaking evidence starts as
   `not_recorded` and cannot create mastery from AI feedback;
7. English learner-visible content and separately governed Kannada guidance;
8. deterministic validation for schema, weights, duplicate/ambiguous answers,
   unsupported claims, unsafe content and pre-submit answer leakage; and
9. named, authorized English academic and Kannada review evidence over the
   exact hashes.

## 4. Safe resume sequence

LPL-01 resumes only in this order:

1. **Complete:** resolve DR-0021's long-form lesson-audio kind and policy,
   then inspect and clean-scan the four exact sources through project-owned
   adapters;
2. **Complete:** resolve DR-0022/DR-0023, then ingest each MP3 through ordinary
   OTP, selected-membership and fresh-TOTP private Media Library commands,
   retaining exact server-computed SHA-256, inspection and clean-scan evidence;
3. **Complete:** DR-0024 created the standalone private pilot, exact role and
   accepted program-scoped rights-confirmer appointment; four ordinary HTTP
   rights commands bound APP-0047, exact hashes, web/Android, `IN`, sharing
   false, derivatives allowed, no expiry and approved retention/deletion;
4. **Pending:** snapshot/convert the upstream rich-text body into typed inert source fields
   with exact lineage, without importing Assignment audio;
5. **In progress — DR-0025 accepted:** implement and verify the bounded non-
   production provider run ceiling and kill switch, then, only after every live
   gate passes, transcribe the eligible exact assets through its dedicated
   project adapter, recording provider/model/configuration/input/output hashes,
   cost and safe failure state;
6. **Pending:** have a human reconcile/correct every transcript against the
   complete exact audio and source body;
7. draft and deterministically validate the ordered four-part blueprint;
8. obtain English academic review of the exact source blueprint;
9. create Kannada guidance from that approved source and obtain independent
   Kannada review; and
10. record the resulting immutable hashes and mark LPL-01 complete only if at
   least one Level 1 blueprint is publishable.

No LPL-02 migration, API, prompt-policy runtime, learner UI or scoring change
is authorized while this packet remains blocked.

## 5. Human review record

This section is intentionally unapproved.

| Review | Reviewer/authority evidence | Exact artifact hash | Decision | Rationale |
|---|---|---|---|---|
| English academic | — | — | pending | Source transcript and blueprint absent |
| Kannada | — | — | pending | Approved English source and Kannada variant absent |

APP-0047 is exact processing authorization for the four selected hashes, but
is not a substitute for either content review or the runtime asset-bound
rights revision selected by a later immutable publication.

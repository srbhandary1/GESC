# LPL-01 exact content and media inventory

**Checkpoint:** LPL-01 — exact inventory and Level 1 blueprint

**Observed:** 10 August 2026

**State:** Inventory, exact processing authorization, governed ingestion,
program-scoped rights and four human-confirmed English metadata revisions
recorded; checkpoint blocked on typed blueprint and English/Kannada review

**Authority:** `ENTERPRISE_PRODUCT_MASTER_PLAN.md` 3.4; ADR-0004; ADR-0013;
ADR-0015; ADR-0020; `LPL-00_SPEC.md`; `LESSON_PRACTICE_LOOP_PLAN.md`

## 1. Scope and evidence boundary

This inventory distinguishes four sources that must not be conflated:

1. the current local PostgreSQL development database and its private object
   store;
2. gitignored owner-supplied legacy feedstock under
   `artifacts/legacy-gse-content/`; and
3. the demonstration-only level shell in
   `apps/web/src/demo/phase1/phase1Fixtures.ts`; and
4. an authenticated read-only snapshot of the owner-designated legacy admin,
   recorded in `LPL-01_LEGACY_SOURCE_MAP.md`.

It is a non-production evidence snapshot. It does not claim that local smoke
publications are approved production curriculum, that demonstration labels
are governed lessons, or that a file's presence authorizes AI processing.
No learner-personal records were read. Database queries were restricted to
curriculum, publication, review and media-governance records.

The initial inventory snapshot had migrations through
`2026_08_07_000060_create_privacy_ops_tables`; its legacy imports therefore
demonstrate the older publication and private-video path. The local database
has since been restored under DR-0023 and advanced through reviewed migration
160, so the four selected Level 1 MP3s now use the complete current Media
Library lifecycle. The older imported publication evidence remains unchanged.

## 2. Reconciliation result

The product-owner report of **four Level 1 audio lessons** is reconciled as
four ordered media parts inside one Level 1 Audio lesson row. The exact files
are now current governed private objects, without implying four top-level
lesson records.

| Claim or source | Exact observed result | LPL-01 interpretation |
|---|---|---|
| Four Level 1 audio lessons | Four upstream MP3 parts identified and hashed: Introduction, Practice, Conversation and Interaction; APP-0047 authorizes bounded processing; four matching active `lesson_audio` assets have clean scans, program-scoped rights and human-confirmed English metadata | Source identity, authorization, ingestion and metadata-author reconciliation resolved; blueprint and specialist review remain blocked |
| Governed Level 1 media | One unique MP4, imported twice into two local staging-smoke programs with the same bytes/hash | A duplicate record, not two lessons and not four audio lessons |
| Audio assets in the two imports | One 34-byte OGG scaffold per import, both SHA-256 `6367d82662f1204d33941f7e23f59e6eddce69d63583258720a56b8f1d737e01` | Synthetic publication scaffold; not Level 1 legacy content |
| Demo level shell | Pre-Level 1, Pre-Level 2 and Levels 1–17 plus an available “Audio Lessons” room | Demonstration navigation only; it contains no governed lesson or media identity |
| Live legacy admin | 102 rows = 17 levels × Video, Audio, PDF/Text Book, Workshop, Group Practice and Assignment; Level 1 is explicitly `Can` | Source structure only; it does not create current publication/review authority |

The authenticated source snapshot was read without modifying the legacy site.
Credentials, cookies, user data and private object paths are not retained in
the repository. The exact source map is
[LPL-01_LEGACY_SOURCE_MAP.md](LPL-01_LEGACY_SOURCE_MAP.md).

## 3. Current governed curriculum snapshot

The local database contains three program/version pairs:

| Program | Graph | Lessons | Media | Publication state | Content status |
|---|---:|---:|---:|---|---|
| `synthetic-foundation-preview` | one module, one lesson, one item, one objective | 1 | 0 | unpublished | Synthetic fixture; not Level 1 commissioned content |
| `legacy-level-videos-01kzgea2b4agt5gsewg9benk6f` | 12 level-video nodes plus one scaffold mission | 13 | 12 MP4 + 1 scaffold OGG | local smoke publication 1 active | Generic wrappers reviewed; no topic transcripts or blueprints |
| `legacy-level-videos-01kzgeq63v4kbq715ff936jebh` | byte-duplicate second import | 13 | duplicate 12 MP4 + 1 scaffold OGG | local smoke publication 1 active | Duplicate smoke data; not additional lessons |

The two legacy programs use one shared generic objective per program:
`legacy-level-video-orientation` / `legacy.level_video.orientation`. The
objective is linked to all 13 nodes and does not identify the communication
function, grammar, vocabulary, difficulty or prerequisites of any video.

For every imported level video, the reviewed English source payload contains
only schema, level label and title (for example, `Level 1`). The reviewed
Kannada payload contains only equivalent generic guidance (for example,
`Level 1 ವೀಡಿಯೊ ಪಾಠ.`). Those exact wrapper reviews are present, but they do
not review a transcript, learning objective, answer key, rationale, discussion
scenario, Apply task or lesson-practice blueprint.

## 4. Exact unique legacy media inventory

The 12 local files match the SHA-256 and byte size recorded in the retained
inventory. They are gitignored feedstock, not committed source files.

| Level | Legacy lesson ID | Exact SHA-256 | Bytes | Current object/content state |
|---:|---:|---|---:|---|
| 1 | 1 | `8cc0ab3ceae5247e15b9feb17c2b9836d102a131b42232e6d74627b706edb5c6` | 11,515,204 | Private MP4 present; no transcript/topic |
| 2 | 16 | `6dcd54630c130392a40a8f619b0f8b7ffcf55c998eecb2a60bafbb93f66403da` | 7,660,177 | Private MP4; no transcript/topic |
| 3 | 22 | `5e14104b420cd6a0295b707a20f813bd2f72dc66f6c51c2af903e234100e3efc` | 5,820,708 | Private MP4; no transcript/topic |
| 4 | 29 | — | — | Legacy row exists; no uploaded MP4 |
| 5 | 35 | — | — | Legacy row exists; no uploaded MP4 |
| 6 | 41 | `8fd01f3630212aec78476a6c81937c64e1f1b0b867172701802997c111274795` | 5,908,373 | Private MP4; no transcript/topic |
| 7 | 48 | `a6f9e1d3db797ac6d1640fdce5843b5dd2b24995768bffe711e6e2d89339119c` | 7,048,254 | Private MP4; no transcript/topic |
| 8 | 54 | `2a7053951d181d2b995d9c386d6378492d569b6a7c23e821ceb598c5a8de34cc` | 8,315,504 | Private MP4; no transcript/topic |
| 9 | 61 | `5c15696c8bd8d84ee8a2433a38a38497ed411c749832b5f865fc98db3676360b` | 7,279,387 | Private MP4; no transcript/topic |
| 10 | 67 | `d3b2e3dc8e8e4534731d3a92f7727e2d386af9e4c2090ac475dc0a9ec71ca5e8` | 4,667,494 | Private MP4; no transcript/topic |
| 11 | 74 | `e8b9819d1693111d9ccaa7965c5561dcb8daa1e15bd67345c0ee88e57d575e9e` | 4,023,809 | Private MP4; no transcript/topic |
| 12 | 80 | — | — | Legacy row exists; no uploaded MP4 |
| 13 | 86 | `044d0e9bb0b4fb8d77820ea7a641ce91bdbf2674ede9672e41a379960f83b080` | 5,920,730 | Private MP4; no transcript/topic |
| 14 | 92 | `3c6fc2b5d411d2987266b7307007580f071571070bdb5ea3e4705b2e105b5fa6` | 3,998,728 | Private MP4; no transcript/topic |
| 15 | 98 | — | — | Legacy row exists; no uploaded MP4 |
| 16 | 104 | — | — | Legacy row exists; no uploaded MP4 |
| 17 | 110 | `c7d9bf1a95f75cf16112f02c5914b2ef56d42e1c8ec0051d2da9217bb050c52d` | 4,349,347 | Private MP4; no transcript/topic |

The exact first-import Level 1 private object was re-read from object storage
during LPL-01. Its SHA-256 was
`8cc0ab3ceae5247e15b9feb17c2b9836d102a131b42232e6d74627b706edb5c6`,
matching the media row and local feedstock. Its storage key remains private and
is intentionally omitted from this durable document.

## 5. Uniform rights and review state

All 26 local imported media rows (13 per smoke program) currently select this
same rights shape:

- evidence status `approved` under `owner_asserted_legacy_import` and
  `ADR-0013-OWNER-ASSERTED`;
- learner delivery allowed on web and Android in India;
- sharing not allowed;
- derivative use not allowed; and
- legacy history-preservation retention/deletion keys.

That state is sufficient for the bounded private learner-delivery smoke path.
It is not sufficient evidence for provider transcription, AI-derived content,
media transformation, cloning or a new practice publication. A successor
exact-asset rights confirmation must explicitly authorize the intended
processing before it occurs. APP-0044 remains umbrella ownership evidence,
not that successor confirmation.

The four newly identified Level 1 MP3s now have exact active `lesson_audio`
assets and selected ffprobe/ClamAV clean-scan evidence, recorded in
`LPL-01_INGESTION_PREFLIGHT.md`. They also have exact program-scoped rights
revisions and four human-confirmed English metadata revisions under D-0191.
They still have no content attachment, blueprint, academic/Kannada review or
publication component.
APP-0047 authorizes only those hashes for bounded private non-production
ingestion, transcription and derivative lesson drafting, with later
web/Android learner-delivery eligibility in India. It does not make a rights
row, review or publication automatically, and the MP3s do not inherit the
MP4's learner-delivery rights.

## 6. Content-completeness matrix

“Present” below means an exact current source exists. “Reviewed” means the
field itself, not merely a generic wrapper, has English and required Kannada
review.

| Required LPL-01 input | Level 1 | Other Levels 2–17 | Gate result |
|---|---|---|---|
| Exact lesson identities and order | One current video node plus four ordered upstream Audio parts | Six upstream lesson rows per level; current graph imports only available Video nodes | Partial |
| Exact media hash/bytes | Present for one current MP4 and four active exact-hash MP3 assets | Present for 11 current MP4s; 63 additional upstream Audio references not hashed in this checkpoint | Partial |
| Current private object | Level 1 MP4 hash matched; four MP3s accepted through governed private storage with exact scan evidence | Prior smoke evidence says all 12 MP4s were present; not re-read in this checkpoint | Partial |
| Processing/derivative rights | Current MP4 explicitly false; four exact MP3s have approved program-scoped APP-0047 rights revisions | Current imported media explicitly false; other upstream files ungoverned | Level 1 MP3 processing rights complete; later levels blocked |
| Timestamped transcript | Four exact audio parts have human-confirmed English metadata revisions; provider segment candidates were inspected and then deleted under retention policy | Absent | Level 1 metadata authoring complete; academic review pending |
| Topic and target patterns supported by source | Upstream body supports `can`; audio/body correspondence unverified | Upstream level themes present; not reviewed objectives | Partial |
| Topic-specific can-do objective/prerequisites | Upstream Level 1 says “Can — I can speak in English”; current objective remains generic orientation | Upstream grammar labels only; current objective remains generic orientation | Blocked |
| Examples/misconceptions/chunks | Rich upstream Level 1 examples exist but require academic correction/review | Not inventoried at body level | Partial |
| Three to seven valid quiz items | Absent | Absent | Blocked |
| Correct answers and rationales | Absent | Absent | Blocked |
| Talk scenario and elicitation goals | Conversation and Workshop source candidates exist; no reviewed goals | Not inventoried at body level | Blocked |
| Apply task and human rubric | Five Workshop source tasks exist; no reviewed Apply rubric | Not inventoried at body level | Blocked |
| English academic review of blueprint | Not reviewable | Not reviewable | Blocked |
| Kannada variant and independent review | Not reviewable | Not reviewable | Blocked |

## 7. Checkpoint determination

LPL-01 inventory step 1 and the truthful completeness matrix are complete.
The four reported Level 1 audio parts and the `can` source topic are
evidence-backed at the legacy source. Program-scoped rights, bounded provider
commissioning and human metadata-author reconciliation are also complete. The
checkpoint exit is **not** complete because the typed source snapshot,
deterministic blueprint, English academic review and Kannada creation/review
remain absent.

The accompanying `LPL-01_LEVEL1_BLUEPRINT_REVIEW_PACKET.md` records the exact
blocked blueprint state and the minimum evidence needed to resume. LPL-02 must
not start until one exact Level 1 blueprint has passed both English and Kannada
review.

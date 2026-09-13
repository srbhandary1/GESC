# LPL-01 legacy source structure map

**Observed:** 10 August 2026

**Method:** Authenticated read-only inspection of the owner-designated legacy
admin; no live record was created, edited, enabled, disabled or deleted

**State:** Source structure identified; content is not yet ingested into the
current governed curriculum

## 1. Evidence and privacy boundary

The supplied administrator account was used only to read course, level,
lesson-list and lesson-edit structure. Credentials, cookies, profile details,
private object paths and user records are not preserved in this repository.

The inspection did not open user lists, message bodies, reports, chats or
learner submissions. Audio references under the legacy Assignment lesson have
blank pedagogical metadata and may be learner-originated. Those files were not
downloaded or analyzed and must not be imported as course content without a
separate provenance/privacy review.

## 2. Legacy administrative structure

The admin navigation exposes these functional families:

| Family | Observed destinations | Current-program interpretation |
|---|---|---|
| Courses | Course, Levels, Lessons | Curriculum source feedstock |
| Media | List, Add | Legacy file/link library; not current Media Library evidence |
| Users | Non-Prime, Prime | Personal data; excluded from LPL-01 inspection |
| Post and Blog | Add/List | Editorial/CMS source; separate from lesson authority |
| User Learning | Workshop, Assignment | Potential learner evidence; separate privacy/provenance gate |
| Messages and Default Messages | Send/History/Add/List | Communications; not lesson content authority |
| Groups | Add/List | Legacy social/organizational structure |
| Office and Config | List/Add or List | Legacy operations/configuration |
| Activity Room | List | Group, description, questions, edit and report concepts |
| Daily Activity Room | List | Daily group, description, questions, edit and report concepts |
| Assignment Group | Level, group, description, questions, users and edit concepts | Separate from lesson Assignment source rows |
| Conversation Group | Group, description, publish, users, chat and edit concepts | Must not become unmoderated AI/social chat implicitly |

The current application should learn the content relationships, not copy the
legacy permission, user, message, configuration or mutable-delete model.

## 3. Course and level hierarchy

The legacy admin contains one course descriptor, **Global Spoken English
Courses**, and 19 level descriptors: Pre-Level 1, Pre-Level 2 and Levels 1–17.
The 102-row lesson list contains only Levels 1–17; no Pre-Level lesson rows
were returned.

| Level | Source theme/description |
|---:|---|
| 1 | Can — “I can speak in English” |
| 2 | Should |
| 3 | Do/Does |
| 4 | Is/Are/Ing |
| 5 | Did |
| 6 | Was/Were/Ing |
| 7 | Have/Has |
| 8 | Have Been/Has Been |
| 9 | Had |
| 10 | Will |
| 11 | Gonna/Wanna |
| 12 | Would |
| 13 | May |
| 14 | If |
| 15 | Must |
| 16 | Should Have |
| 17 | Used To |

These labels are useful source evidence, not yet reviewed can-do objectives.
The future current curriculum should convert each form label into observable
communication outcomes rather than treating grammar-name completion as
mastery.

## 4. Repeated lesson architecture

Every numbered level has exactly six enabled legacy lesson rows:

| Legacy type | Display name | Observed role | Safe current mapping |
|---:|---|---|---|
| 1 | Video | One level video slot | Ordered private lesson-video media component |
| 2 | Audio | Ordered teaching audio parts | Ordered model/instruction audio parts attached to one reviewed lesson blueprint |
| 3 | PDF/Text Book/topic name | Rich-text lesson body; Level 1 is named `CAN` | Governed typed source content, never raw executable HTML |
| 4 | Workshop | Authored practice tasks | Talk/Apply task candidates after review |
| 5 | Group Practice | Group-practice slot; Level 1 currently empty | Moderated/group roadmap input, not required for the first private loop |
| 6 | Assignment | Assignment slot; Level 1 contains unlabeled OGG/Opus references | Quarantine as unknown-provenance evidence; do not import as teaching media |

This confirms that the four Level 1 audio files are **parts inside one Audio
lesson row**, not four top-level lesson rows. The current model should preserve
both concepts: one ordered lesson/practice blueprint with several exact media
parts.

## 5. Audio-part pattern across all levels

The 17 Audio edit pages expose 67 current MP3 references:

- Levels 1–14 and 16–17: four parts named Introduction, Practice,
  Conversation and Interaction;
- Level 15: three parts named Introduction / Practice, Conversation and
  Interaction.

An additional text-only Level 1 row describes a `CAN` introductory session but
has no media file. It is source content, not a fifth audio object.

No all-level bytes were copied during this structural inspection. Only the
four Level 1 teaching MP3s were read ephemerally to establish exact hashes and
media shape.

## 6. Exact Level 1 teaching media

| Order | Source part | SHA-256 | Bytes | Detected media shape |
|---:|---|---|---:|---|
| 1 | Introduction | `6838818d74481ffe38e13948dea2954886dcc519ed927112b703f8e2064573aa` | 4,968,408 | MPEG Layer III, 128 kbps, 44.1 kHz, joint stereo |
| 2 | Practice | `75909e9baf340356d7ecf66435977ee1cce1fe93a597da58a24b5b9ea1833dd7` | 7,357,881 | MPEG Layer III, 128 kbps, 44.1 kHz, joint stereo |
| 3 | Conversation | `8124b3bc6f71b65258358b0121cc3d72f1543948ca3ef3a1bb419f1fa0d65c70` | 2,157,215 | MPEG Layer III, 128 kbps, 44.1 kHz, joint stereo |
| 4 | Interaction | `4862e927078ecb0ea56b7240f6d5b52356365115a6512836357bf340fdcf895e` | 4,276,686 | MPEG Layer III, 128 kbps, 44.1 kHz, joint stereo |

These exact bytes began as upstream source feedstock. Under APP-0047,
APP-0048 and APP-0050 they have now passed the current private quarantine,
ffprobe inspection, ClamAV clean scan and explicit acceptance lifecycle as
four active `lesson_audio` assets. DR-0024 subsequently established one exact
approved program-scoped rights revision per asset. They still have no
metadata/transcript, attachment, content review or publication. DR-0025 Option
A is accepted; its bounded implementation and verification are the current
transcription-commissioning work before any live request.

## 7. Level 1 source-content map

The legacy Level 1 body supports `can` as a source topic. It covers:

- ability, possibility/impossibility and requesting/granting permission;
- affirmative and negative forms;
- yes/no, negative and WH questions;
- polite `Can I please…?` / `Can you please…?` requests;
- controlled substitution practice;
- a directions/help conversation; and
- interaction prompts around help, requests, directions and practical advice.

The Workshop contains five source tasks: self-introduction, sentence creation
with `can`, reading the directions conversation, repeating it without reading,
and creating questions with `can`.

The source body includes wording, examples and distractor-like response
options that require English academic correction before learner publication.
It is source evidence for topic selection, not an approved answer key or
ready-to-publish transcript.

## 8. Current-system import boundary

The safe import sequence is:

1. create an immutable source snapshot of the course, levels, six lesson types,
   ordered part labels and source hashes;
2. **Complete:** upload each selected teaching asset through current Media
   Library quarantine and retain server-computed hash, inspection and
   clean-scan evidence;
3. explicitly classify legacy Assignment references before any byte access;
4. record program-scoped rights for transcription, AI-derived commissioning,
   learner delivery, channels, territory and retention;
5. convert source rich text into typed inert fields and retain source lineage;
6. transcribe eligible teaching audio through project-owned adapters, then
   reconcile it with the source body rather than assuming they match;
7. draft objectives, Talk goals, quiz items/rationales and Apply tasks;
8. run deterministic schema, answer, attribution and leakage validators; and
9. obtain exact English academic and Kannada review before publication.

No legacy identifier, title, status or file URL should become current
publication authority merely because an administrator can view it upstream.

# Phase 1 multilingual UI expansion scope

## Purpose

This bounded change brings the interface layer of the Phase 3 language-pack
roadmap into Phase 1 without expanding the learning language, governed
curriculum, speech, model-audio or AI-provider scope.

The authorization and master-plan amendment are recorded in
[ADR-0005](adr/0005-phase-1-multilingual-ui-language-packs.md) and
[APP-0006](APPROVALS.md#app-0006).

## Supported interface locales

| Locale | Language | Phase 1 status |
|---|---|---|
| `kn-IN` | Kannada | Implementation draft pending independent review |
| `ml-IN` | Malayalam | Implementation draft pending independent review |
| `ta-IN` | Tamil | Implementation draft pending independent review |
| `te-IN` | Telugu | Implementation draft pending independent review |
| `hi-IN` | Hindi | Implementation draft pending independent review |
| `en-IN` | English | Governed source and deterministic fallback |

`kn-IN` remains the default requested locale. `en-IN` remains the source and
fallback locale. Locale selection remains ephemeral and stores no credentials
or personal data.

## Included

- complete UI catalogue key parity for all six locales;
- source-linked catalogue revisions and canonical SHA-256 hashes;
- honest `pending-independent-review` metadata for the five translated
  catalogues;
- development/test/review preview rendering with a visible notice;
- strict production fallback to English until an exact current translation
  has independent approval evidence;
- language options, BCP 47 `lang` metadata and privacy-safe fallback events;
- self-hosted Noto Sans variable fonts for Kannada, Malayalam, Tamil, Telugu
  and Devanagari;
- unit, type, locale-governance, accessibility, reflow, font and build
  verification.

## Excluded

- changing English as the learning language;
- adding Malayalam, Tamil, Telugu or Hindi curriculum variants;
- changing the Kannada explanation requirement for governed curriculum;
- speech recognition, speech evaluation or language identification for the
  added languages;
- model audio, text-to-speech or AI explanation support for the added
  languages;
- changing OpenAPI governed-content locale enums or generated client types;
- persisting learner language preferences;
- claiming that Codex-authored draft wording has human linguistic approval;
- production enablement of any unapproved translated catalogue.

## Release gates

Each translated catalogue requires a distinct accountable translator and an
independent reviewer competent in that language. Approval evidence must bind
the exact catalogue revision and hash. Script rendering, wrapping,
screen-reader pronunciation, terminology, accessibility, and representative
device review remain human gates.

Curriculum, speech, audio and AI support remain part of a separately approved
language-pack rollout with language-specific learning, safety and speech
evaluation. UI approval does not approve any of those capabilities.

## Verification

Run from the repository root:

```text
pnpm run locale:check
pnpm run lint
pnpm run typecheck
pnpm run test
make e2e
make verify
make build
```

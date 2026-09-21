# `@global-english/localization`

Framework-free UI localization contracts for Global English.

This package owns only application-interface messages. Curriculum text,
learning explanations, mission content, transcripts and media metadata belong
to the versioned content workflow introduced in PR-005 or later. They must not
be added to these catalogues.

## Locale policy

- Default requested locale: `kn-IN`
- Additional UI-only preview locales: `ml-IN`, `ta-IN`, `te-IN`, `hi-IN`
- Deterministic fallback locale: `en-IN`
- Strict default resolution policy: `approved-only`
- Explicit non-production policy: `development-preview`

The Kannada catalogue is independently reviewed and `approved` for ordinary
`approved-only` resolution. Malayalam, Tamil, Telugu and Hindi remain
attributed implementation drafts with `pending-independent-review`;
`reviewedBy` and `approvedAt` are `null`, and `independentReview` is `false`.
The strict policy therefore returns English for those four locales.
A development shell may explicitly request `development-preview`, display the
returned `isPreview` state, and make the pending-review limitation visible.
Stale, rejected, missing and unsupported translations still fall back to
English under either policy.

The web application enables preview only for development, tests or an explicit
`VITE_UI_TRANSLATION_PREVIEW=true` review build. An ordinary production build
uses `approved-only`, shows a generic English fallback notice and emits a
structured `localization.fallback` event containing no message text or
parameters.

Do not change a translated record to `approved` until a distinct accountable
translator and independent reviewer for that language provide traceable
approval evidence. The validator rejects an approval claim without distinct
identities, a valid approval time and `independentReview: true`.

These are UI catalogues only. Their availability does not expand curriculum,
speech recognition, model audio or AI explanations beyond the governed
`en-IN`/`kn-IN` content boundary.

## Usage

```ts
import {
  resolveMessage,
  type MessageKey,
} from '@global-english/localization'

const key: MessageKey = 'nav.learn'
const resolved = resolveMessage(key, {
  requestedLocale: 'kn-IN',
  policy: 'development-preview',
  onFallback(event) {
    // Safe structured event: it never contains message text or parameters.
    reportLocalizationFallback(event)
  },
})

renderText(resolved.text, {
  lang: resolved.resolvedLocale,
  isPreview: resolved.isPreview,
})
```

`resolveMessage` returns:

- `text`
- `requestedLocale`
- `resolvedLocale`
- `usedFallback`
- `reason`
- `reviewStatus`
- `revision`
- `isPreview`

`MessageKey` is derived from the flat `en-IN` source catalogue. Adding a UI
message therefore requires the same key in all six catalogues, a source-linked
governance revision/hash update and successful locale validation.

## Unicode normalization

Messages must be Unicode NFC. `normalizeLocalizedText` performs NFC
composition and intentionally preserves U+200C ZERO WIDTH NON-JOINER and
U+200D ZERO WIDTH JOINER. It does not transliterate, strip format characters or
change script.

## Governance and validation

`src/catalogues/governance.json` links each catalogue revision to:

- its own SHA-256 content hash;
- the current `en-IN` source revision and hash;
- translation attribution;
- review state, reviewer and approval time.

`sha256-canonical-json-v1` hashes UTF-8 JSON containing lexicographically
sorted flat keys with no insignificant whitespace. The validator checks:

- exact supported/default/fallback locales;
- missing and unknown key parity;
- blank or whitespace-only messages;
- named placeholder parity and syntax;
- NFC normalization;
- catalogue and source revision/hash integrity;
- mandatory source status for the `en-IN` fallback catalogue;
- honest review-state claims.

Pending independent review is a visible warning, not a structural validation
failure. Publication approval remains a human gate.

Package-local commands, after workspace dependencies are installed:

```text
pnpm --filter @global-english/localization typecheck
pnpm --filter @global-english/localization test
pnpm run lint
node scripts/validate-locales.mjs
```

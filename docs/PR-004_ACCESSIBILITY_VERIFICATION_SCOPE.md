# PR-004 accessibility and localization verification scope

## Purpose

This document defines what PR-004 verifies for the localized web shell and
what remains a human release gate. It supplements the canonical accessibility
requirements in the
[enterprise master plan](../ENTERPRISE_PRODUCT_MASTER_PLAN.md#19-accessibility-and-inclusive-design)
and does not amend them.

## Governed variants

| Variant | Policy | Expected behavior |
|---|---|---|
| Ordinary production build | `approved-only` | Pending Kannada is rejected, English is rendered with a visible fallback notice, the document language is `en-IN`, and the requested locale remains recorded as `kn-IN` |
| Development/test/review build | `development-preview` | The attributed Kannada draft may render with a visible pending-independent-review notice |
| Explicit review build | `VITE_UI_TRANSLATION_PREVIEW=true` | Same as development preview; not authorized for production deployment |

The web shell uses the UI message catalogues only. Curriculum content,
transcripts, mission material and media metadata remain outside PR-004.

## Automated browser matrix

The Chromium suite in
[`apps/web/e2e/bootstrap.spec.ts`](../apps/web/e2e/bootstrap.spec.ts) verifies:

| Area | Evidence |
|---|---|
| Languages | axe scans in `kn-IN` preview and `en-IN`; document and per-span language metadata |
| Keyboard | first-tab skip link, focus transfer to main, primary-route keyboard activation and route-title focus |
| Route preservation | skip activation from `#learn` retains the route and reloads back into Learn |
| Reflow widths | 320, 360, 412, 768 and 1280 CSS pixels without horizontal document overflow |
| Enlarged text | 200% and 400% root-text stress proxies without horizontal document overflow |
| Targets | primary navigation links and locale selector are at least 44 by 44 CSS pixels |
| Font | Kannada sample text loads the bundled `noto-sans-kannada-kannada-wght-normal` WOFF2 face |
| Motion | reduced-motion preference changes scroll behavior to `auto` |
| Persistence | locale changes are ephemeral; `localStorage` and IndexedDB remain empty |

Component and unit tests additionally cover mixed-language span order, NFC
normalization, ZWJ/ZWNJ preservation, skip-link focus, brand-language
metadata, strict production fallback, safe fallback events, locale governance
and inert audio/recording contracts.

## Local visual inspection

The final local review inspected:

- the Kannada preview at 1280 CSS pixels;
- the Kannada preview at 320 CSS pixels with `scrollWidth === clientWidth`;
  and
- the strict production fallback at 320 CSS pixels with a visible English
  fallback notice, `lang="en-IN"`,
  `data-requested-locale="kn-IN"` and no horizontal overflow.

This confirms that the bundled font renders Kannada glyphs in the inspected
Chromium environment. It does not constitute Kannada language approval.

## Manual gates still required

Before a production accessibility claim, complete and record:

1. actual browser zoom at 200% and 400% in supported desktop browsers;
2. keyboard review with visible focus across the supported browser matrix;
3. screen-reader review of landmarks, language transitions, fallback notices,
   navigation state and error recovery;
4. TalkBack review on representative Android devices after an Android UI
   exists;
5. independent Kannada linguistic review, including terminology, wrapping,
   pronunciation implications and mixed-language reading; and
6. audio/recording control review when real playback/capture behavior is
   introduced.

Automated axe results and enlarged-root-text proxies are supporting evidence,
not substitutes for these human checks.

## Scope exclusions

PR-004 does not claim or test:

- curriculum or lesson accessibility;
- real audio playback, microphone capture, transcripts or speech feedback;
- Android native UI or secure credential-store implementation;
- offline screen-reader behavior;
- AI/provider output accessibility; or
- production deployment conformance.

## ADR-0005 Phase 1 UI-language amendment

[ADR-0005](adr/0005-phase-1-multilingual-ui-language-packs.md) adds
Malayalam (`ml-IN`), Tamil (`ta-IN`), Telugu (`te-IN`) and Hindi (`hi-IN`) as
UI-only development previews. The same strict production fallback,
source-linked governance, visible pending-review notice, ephemeral selection,
axe, reflow, target-size and credential-storage requirements apply to all six
interface locales.

Self-hosted Noto Sans Malayalam, Tamil, Telugu and Devanagari variable fonts
join the existing Kannada font. Independent linguistic review, script
wrapping, screen-reader pronunciation, browser zoom and representative
Android TalkBack testing are release gates for each translated catalogue.
This amendment does not expand curriculum, speech recognition, model audio or
AI explanations.

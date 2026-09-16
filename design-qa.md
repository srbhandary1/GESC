# Professional Path design QA

- Source visual truth: `artifacts/design-audit/professional-path-selected-reference.png`
- Browser implementation: `artifacts/design-audit/professional-path-implementation.png`
- Combined comparison: `artifacts/design-audit/professional-path-comparison.png`
- Viewport/state: authenticated Android learner Home, 390 × 844 CSS px, light
  theme, reduced motion, Kannada interface with governed mixed-language mission
  content.
- Density normalization: the 852 × 1856 generated source was normalized to
  390 × 844 in the combined browser comparison; the implementation is a native
  390 × 844 browser capture at deviceScaleFactor 1.

## Findings

No actionable P0, P1 or P2 visual difference remains within the authoritative
runtime data and localization constraints.

- Typography: the implementation preserves the reference's large, tightly
  tracked session title and compact supporting text while respecting the
  existing Kannada 28 px mobile-title ceiling and catalogue fonts.
- Spacing/layout rhythm: the implementation carries across the quiet canvas,
  compact header, generous module padding, full-width primary action, subtle
  dividers and fixed five-destination navigation. The generated reference's
  named path, four-step timeline and percentage module are intentionally not
  reproduced because the learner Home contract does not supply those values.
- Colors/tokens: warm near-white canvas, deep ink, emerald action surface,
  pale-sage selection surfaces and restrained elevation match the selected
  direction. The previously exposed pending-review contrast failure is fixed
  with white text on the dark notice surface.
- Image quality: the implementation reuses the licensed project-owned Swathi
  portrait rather than generating or substituting another identity. Its crop
  remains sharp at the rendered size.
- Copy/content: all displayed copy comes from the governed localization and
  learner-journey contracts. Mock-only labels and fabricated learner progress
  were not introduced.

## Comparison history

1. First capture: the coach portrait was hidden and the mission title exceeded
   the established mobile compactness gate. The coach treatment was restored
   and the mobile title was capped at 28 px.
2. Second capture: axe exposed insufficient contrast in the Kannada
   pending-independent-review notice. A notice-specific foreground override
   fixed the contrast without changing global muted text or brand colors.
3. Final capture: the target journey passed, including axe, minimum target,
   horizontal-overflow, portrait-size and title-size assertions.

## Focused comparison

No separate crop was required: the combined 840 × 900 comparison keeps the
header, main action typography, coach treatment and bottom navigation legible
at the same time. The remainder of the live page is intentionally below the
fold, as in a real 390 × 844 Android viewport.

## Follow-up polish

- P3: consider moving account, locale and theme controls behind a single
  accessible account sheet after that interaction is separately specified and
  tested; the current compact horizontal toolbar preserves existing access.
- P3: a future Home contract may expose authoritative level/session progress;
  only then should the mock's path timeline and percentage block be added.

## Interaction and console evidence

- Primary Continue action enabled.
- Five destination targets remained at least 44 CSS px tall.
- No horizontal viewport overflow.
- Playwright journey and axe scan passed.
- No page-console error was reported by the exercised journey.

final result: passed

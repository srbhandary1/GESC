# Global English design system

This package contains the accessible, multilingual React presentation
primitives used by Global English. It owns visual tokens and semantics;
applications own routing, localized messages, authentication, media
capture/playback, network requests and persistence.

## Use

Import the self-hosted Indian-script fonts and component styles once at the
application entry point:

```ts
import '@global-english/design-system/styles.css'
```

Then import React primitives from the package root:

```tsx
import {
  ApplicationFrame,
  MixedLanguageText,
} from '@global-english/design-system'
```

`ApplicationFrame` requires caller-supplied labels and links for all four
primary destinations, including an explicit brand locale. Its skip link
focuses main content without replacing an application route fragment.
`MixedLanguageText` renders ordered, visible `kn-IN`, `ml-IN`, `ta-IN`,
`te-IN`, `hi-IN` and `en-IN` text spans with the correct `lang` metadata. It
normalizes text to Unicode NFC by default; pass `normalization="caller"` only
when the caller has already applied its governed normalization policy.

`AudioControls` and `RecordingControls` are intentionally inert presentation
components. They accept state, labels and request callbacks but do not accept
media sources or captured data. This package must not access microphone or
playback APIs, make provider/network calls, or persist learner data.

## Font and accessibility boundary

The Noto Sans Kannada, Malayalam, Tamil, Telugu and Devanagari variable-font
packages are pinned dependencies and bundle OFL-licensed fonts into the
consuming application. The font stack starts with these self-hosted faces and
retains system-font fallbacks.

The styles provide visible focus, 44 CSS-pixel interactive targets,
content reflow, generous Kannada line height and reduced-motion behavior.
Automated tests cover component semantics and interactions. Browser coverage
adds keyboard, responsive-width and enlarged-text reflow checks, but those
checks do not replace actual browser zoom, screen-reader or representative
Android TalkBack review.

# Global English Web

This directory contains the React 19.2 TypeScript and Vite application shell.

PR-004 adds the accessible Home/Learn/Practice/Progress shell, governed
interface localization, semantic language tagging, keyboard focus behavior and
browser accessibility/reflow coverage. The routes remain inert: curriculum
content, learner journeys, media capture, speech/AI providers, offline behavior
and other later-checkpoint features are not implemented.

The Kannada catalogue is a visibly labelled development preview pending
independent human review. Ordinary production builds use strict approved-only
resolution and visibly fall back to English. Playwright explicitly builds the
review variant so Kannada rendering can be checked without weakening the
production default.

Use the repository-root `make` commands for bootstrap, verification and builds.

## Student Tailwind

`apps/web` uses Tailwind CSS 4 through `@tailwindcss/vite`. The entry is
`src/styles/tailwind.css`:

- `@theme inline` maps utilities to live `@global-english/design-system`
  `--ge-*` tokens (one palette; dark mode follows `data-theme`).
- Preflight is not imported, so DS base/components keep the document reset.
- `@source` scans student TS/TSX and excludes `src/operator`.
- Unused utilities are omitted from the production CSS. A demo production
  build measured 44.12 kB CSS / 7.71 kB gzip versus 40.04 kB / 6.84 kB
  before Tailwind (+0.87 kB gzip).

Do not introduce a second colour system or a runtime CSS-in-JS theme.

Learner chrome follows the Grove language: brand green `#006B3F`, charcoal
`#0F1F17` dark surfaces, mint tints, original tutor portraits, and a Speak
wave action. Do not introduce electric purple as a brand primary.

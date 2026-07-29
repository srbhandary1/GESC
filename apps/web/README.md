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

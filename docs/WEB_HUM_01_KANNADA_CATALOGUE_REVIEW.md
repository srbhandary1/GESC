# WEB-HUM-01 — Independent kn-IN catalogue review

**Status:** Complete for `ui-kn-IN-2026-09-19.4` under [APP-0105](APPROVALS.md#app-0105) / [D-0327](DECISIONS.md).

Codex cannot approve its own catalogues ([ADR-0005](adr/0005-phase-1-multilingual-ui-language-packs.md)). Named independent review of this exact revision is recorded; ordinary `approved-only` builds resolve Kannada chrome without English fallback.

## Current kn-IN record (`packages/localization/src/catalogues/governance.json`)

| Field | Value |
|---|---|
| Locale | `kn-IN` |
| Revision | `ui-kn-IN-2026-09-19.4` |
| Catalogue hash | `sha256:ae5b774c63ee4f11da769c8a29ebd0349a8a89acac0eb8a7c25ee1da2ca18381` |
| Source locale / revision | `en-IN` / `ui-en-IN-2026-09-19.2` |
| Review status | `approved` |
| Translated by | Codex implementation draft |
| Reviewed by | `S R Bhandary` |
| Approved at | `2026-09-21T11:52:00+05:30` |
| Independent review | `true` |

The four other pending packs (`ml-IN`, `ta-IN`, `te-IN`, `hi-IN`) stay frozen for review on the same source revision. A later Kannada wording change must create a new revision and return `kn-IN` to review.

[APP-0106](APPROVALS.md#app-0106) / [D-0328](DECISIONS.md) records that curriculum, Role-play, production and Play were already authorized. This Kannada UI approval does not grant, reopen or withdraw those authorities.

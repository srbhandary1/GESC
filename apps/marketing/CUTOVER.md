# Phase 1 marketing-only cutover runbook

Operator runbook for serving the static Superdesign homepage from
`apps/marketing/dist/` at `https://globalspokenenglish.com/` on the existing
Plesk / nginx PHP host.

This document does **not** deploy DNS, Plesk, checkout, or the learner app.
Codex is not an approver. Live cutover remains an operator action.

## Step 0 — Merge the marketing homepage (PR #5)

**Complete (13 September 2026).** The operator requested merge. The Superdesign
homepage from [PR #5](https://github.com/srbhandary1/GESC/pull/5) and this
runbook are on `main` at merge commit `1853ad6`. Continue from Preflight.

Build `dist/` from that default-branch tree (`git checkout 1853ad6` or later
`main`). Do not upload an unmerged working copy to live.

PR #5 / follow-up CI `verify` was red on a pre-existing `league/commonmark`
Composer audit in `apps/api`, not on `apps/marketing`. That API audit is
out of this marketing-only scope and was not changed.

## Hard guards — do not touch

| Area | Forbidden |
|---|---|
| Learner web app | Anything under `apps/web` |
| Android | Capacitor project, Gradle, APK rebuild, `apps/web/android`, `apps/web/android-app` |
| Lesson / practice UI | `LessonPractice`, listen-learn media screens |
| Live audio | `LevelSessionAudioStreamer`, `ConversationOrchestrator`, Indic STT, TTS |
| Curriculum | Institute level content, publications, rooms |
| Learner UX PR | PR #6 (`cursor/learner-ux-elevate-5f47`) — do not change or rebuild it |
| Secrets / learner data | Do not log or upload real learner audio, transcripts, or credentials |

Marketing cutover may change only:

- `apps/marketing/**` (this package and its `dist/` artifact)
- Makefile marketing targets
- root docs that already describe the marketing site
- Plesk **homepage** document-root files that you are replacing with `dist/`

If a file is needed to “fix login” or “fix audio” inside the learner app, **stop**.
That is out of scope. Roll back the homepage instead.

## Artifact paths

| Item | Path |
|---|---|
| Source | `apps/marketing/` |
| Static build output | `apps/marketing/dist/` |
| Optional upload tarball | `apps/marketing/dist.tar.gz` (gitignored) |

Build commands (repository root, after `pnpm install`):

```text
make marketing-build
```

Equivalent:

```text
pnpm --filter @global-english/marketing build
```

Create the Plesk upload tarball (rebuilds `dist/`, then packs it):

```text
make marketing-dist
```

Manual zip if Make is unavailable:

```text
pnpm --filter @global-english/marketing build
tar -C apps/marketing -czf apps/marketing/dist.tar.gz dist
# or: cd apps/marketing/dist && zip -r ../dist.zip .
```

Local preview of the built artifact: `pnpm --filter @global-english/marketing preview`
(`http://127.0.0.1:4174`).

**Pitfall:** Vite preview serves `index.html` for unknown paths, including
`/login` (HTTP 200 marketing HTML). That is **not** proof that live institute
login is preserved. On Plesk, `/login` must remain the PHP application.

Vite `base` is `./`, so relative asset URLs work at `/` or in a staging
subdirectory.

## Preflight checklist

Complete every row before a staging upload.

| # | Check | Pass when |
|---|---|---|
| 0 | Marketing homepage + this runbook are on the default branch | `apps/marketing/CUTOVER.md` exists on `main`; `make marketing-build` succeeds |
| 1 | Working tree is marketing-only | `git diff` / PR diff has **zero** `apps/web`, Capacitor, audio, or curriculum files |
| 2 | Marketing build | `make marketing-build` exits 0 and `apps/marketing/dist/index.html` exists |
| 3 | Dist is static-only | `dist/` contains `index.html`, `assets/`, `favicon.svg`, `robots.txt` — no PHP, no learner bundles |
| 4 | Single viewport | Built HTML has **one** `<meta name="viewport">` and it is `width=device-width, initial-scale=1` with **no** `maximum-scale` |
| 5 | Title / meta | `<title>Global Spoken English</title>`; description is clean (no live-site typos *Lets's / Lesssons / definately*) |
| 6 | Brand | CSS uses `#006B3F` / `#006b3f` |
| 7 | Primary CTA | Visible primary button copy is **Try free demo** |
| 8 | Secondary login | **Log in** points at `/login` (not a hash, not the learner Sanctum app) |
| 9 | Level copy | Hero / benefits / pricing say **17** institute levels (see [Level count](#level-count)) |
| 10 | PHP route inventory | Operator lists current live PHP paths (minimum set below) and confirms they will **not** be overwritten |
| 11 | Full host backup | Plesk backup or tarball of the current `httpdocs` (or equivalent) taken **before** any overwrite |
| 12 | Staging URL | A hostname or subdirectory that is **not** the live `/` is ready |

### Live PHP paths observed 13 September 2026

Homepage links on `https://globalspokenenglish.com/` that must keep working
after `/` becomes static:

| Path | Role |
|---|---|
| `/login` | Institute login — **must preserve** |
| `/register` | Registration, including `?planId=` query variants |
| `/about-us` | Existing about page |
| `/blog` | Existing blog |
| `/student-list` | Institute listing |
| `/terms-conditions-refund` | Legal / refund page |
| `/home_new_assets/*` | Assets for remaining PHP pages (keep until those pages are retired) |

Other PHP routes may exist behind login (dashboards, classrooms, payments).
Inventory them on the Plesk File Manager **before** replacing files. Do not
delete `*.php`, application folders, or databases.

The current live homepage also ships **two** viewport metas (one with
`maximum-scale=1`) and typo-ridden title/description. The `apps/marketing`
build is the replacement for that homepage only.

## Staging upload (Plesk)

Do this on a **staging** vhost or subdirectory first. Do not point the live
document root here until [Live cutover](#live-cutover).

1. On a clean checkout of the merged marketing tree, run `make marketing-dist`.
2. Confirm `apps/marketing/dist/index.html` and `apps/marketing/dist/assets/`.
3. In Plesk, create either:
   - a subdomain (`staging.globalspokenenglish.com`) with its own document
     root, or
   - a subdirectory such as `httpdocs/marketing-next/` (do not replace live
     `index.php` / `index.html` yet).
4. Upload the **contents** of `dist/` (or extract `dist.tar.gz`) into that
   staging document root so `index.html` sits at the staging `/`.
5. Do **not** upload into the learner app tree, Capacitor folders, or any
   host that serves lesson audio.
6. Browse the staging URL:
   - homepage renders (nav, hero, levels, pricing, footer);
   - one viewport meta, pinch-zoom works (no `maximum-scale=1`);
   - **Try free demo** scrolls to `#demo`;
   - **Log in** still requests the **live** `/login` if you used a relative
     `/login` link from a subdirectory — prefer staging on a subdomain so
     `/login` can be proxied or left on the live host.
7. Keep the existing PHP site untouched on the live vhost.

## Live cutover

Goal: static `dist/` is served at `/`. Existing PHP application routes stay
on the same host.

1. Re-run Preflight and take a fresh Plesk / `httpdocs` backup.
2. Upload `dist/` to a sibling folder on the live vhost, e.g.
   `httpdocs/marketing-next/`, and spot-check files there.
3. Identify the current homepage entry (`index.php`, `index.html`, or a
   Plesk “index files” order). **Rename, do not delete** (example:
   `index.php` → `index.php.pre-marketing-cutover`).
4. Copy `index.html`, `assets/`, `favicon.svg`, and `robots.txt` from
   `marketing-next/` into the live document root.
5. Set the vhost index order so `index.html` is tried for `/` and PHP
   remains available for named scripts and application routes.
6. Add host rules so these prefixes are **never** served as the marketing
   SPA fallback:
   - `/login`
   - `/register`
   - `/about-us`
   - `/blog`
   - `/student-list`
   - `/terms-conditions-refund`
   - any other inventoried PHP / app path
7. Example nginx extra-directive sketch (adapt to the vhost; do not paste
   blindly over an existing Plesk config):

   ```nginx
   location = / {
     try_files /index.html =404;
   }

   location /assets/ {
     try_files $uri =404;
   }

   location ~ ^/(login|register|about-us|blog|student-list|terms-conditions-refund) {
     try_files $uri $uri/ /index.php?$query_string;
   }
   ```

   Apache / Plesk “Additional directives” equivalent: serve existing files
   first; send only `/` to `index.html`; leave `.php` and the routes above
   to the PHP application.
8. Do **not** point the whole domain at a static-only host unless `/login`
   (and the other PHP routes) are reverse-proxied or redirected first.
   A static-only host will 404 those paths.
9. Paid checkout and the in-product demo are **not** wired. Primary CTAs
   stay on `#demo` until a later marketing-only change.

## Viewport

The marketing source (`apps/marketing/index.html`) must keep **exactly one**:

```html
<meta name="viewport" content="width=device-width, initial-scale=1" />
```

Forbidden on this page:

- a second viewport meta;
- `maximum-scale=1` (the live PHP homepage currently has this lock);
- `user-scalable=no`.

After upload, View Source on `/` and confirm the built file still has that
single tag. The homepage unit test fails if a second viewport or
`maximum-scale` is reintroduced.

## Copy

| Element | Required |
|---|---|
| Title | `Global Spoken English` |
| Description | Clean sentence; 17 institute levels; no live-site typos |
| Brand | `#006B3F` |
| Primary CTA | **Try free demo** (nav, hero, how-it-works, demo band, footer) |
| Secondary | **Log in** → `/login` |

Do not restore the live title
`Lets's Speak English fluently… Lesssons` or the description typo
`definately`.

## Level count

| Surface | Count | Meaning |
|---|---|---|
| Institute / live product | **17** speaking levels (demo chrome also lists Pre-1 / Pre-2 plus Levels 1–17) | Authoritative product count for marketing claims |
| Marketing hero, benefits, how-it-works, pricing, footer | **17** | Aligned in this package |
| Marketing levels grid | **12 named themes** | Public overview of the 17-level path, not a second product catalogue |
| Hero headline “12 weeks” | Duration promise | Not a level count |

If a later editor changes the grid to 17 named cards, map names to the
institute syllabus first. Do not invent five extra product levels in copy.

## Rollback triggers

Restore the pre-cutover homepage (renamed `index.php` / backup tarball)
immediately when any of the following is true:

| Trigger | Why |
|---|---|
| `/login` 404s, 500s, or no longer shows the institute login | PHP route was overwritten or index fallback swallowed it |
| `/register` or other inventoried PHP routes fail | Same |
| Homepage is blank or serves learner-app assets | Wrong artifact uploaded |
| Learner **audio** breaks (playback, mic, conversation stream) | **Should not happen if hard guards hold.** Treat as evidence that files were uploaded to the wrong tree or a shared document root was clobbered. Restore the homepage backup, then inspect the host — do **not** patch `apps/web` from this runbook |

Rollback is file restore + Plesk cache/nginx reload. It is not an Android
rebuild and not a learner-app revert.

## Verification (after live `/` cutover)

Operator-side only. Do not change learner or audio code to “make the check
pass”.

### Marketing host

1. `GET /` returns the new static homepage (title `Global Spoken English`).
2. View Source: single viewport, no `maximum-scale`.
3. **Try free demo** and **Log in** behave as specified.
4. `/login` still returns the institute PHP login (not the marketing HTML).
   Do not use `make marketing` / `vite preview` for this check — preview
   falls back to `index.html` for `/login`.
5. `/register`, `/about-us`, `/blog`, `/student-list`,
   `/terms-conditions-refund` still return their existing PHP pages.

### Learner audio baseline (must remain green)

Run this on the **learner product** you already use (web/PWA or Android).
Marketing files must not be on that path.

1. Sign in through the existing institute `/login` (or the learner auth you
   already operate — do not invent a new auth flow).
2. Open a known listen / lesson item and play **model audio**.
3. Start a practice turn: microphone permission prompt still appears; local
   capture still starts.
4. If Confidence Conversation / live STT is in use on that environment,
   complete one short turn and confirm audio still streams and a reply
   returns.
5. Confirm no new 404s for lesson media that worked before the homepage
   upload.

Record pass/fail with time and hostname. If audio fails, roll back the
marketing homepage first, then investigate host overlap. Do not open
`LessonPractice`, `LevelSessionAudioStreamer`, or related learner files.

## Out of scope

- Deploying this runbook (operator).
- DNS changes beyond pointing `/` at the uploaded `dist/`.
- Checkout / payment wiring.
- Learner theme, mic, or PR #6 UX work.
- Rebuilding the Android APK.
